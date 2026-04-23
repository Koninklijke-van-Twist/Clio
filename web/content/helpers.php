<?php

/**
 * Functies
 */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function safeUtf8Substr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($value, $start, $length, 'UTF-8');
    }

    return (string) substr($value, $start, $length);
}

function appUrl(string $path = '', array $query = []): string
{
    $base = 'index.php';
    if ($path !== '') {
        $base = ltrim($path, '/');
    }

    if ($query === []) {
        return $base;
    }

    return $base . '?' . http_build_query($query);
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function isValidCsrf(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    return hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function logUploadDiagnostic(string $event, array $context = []): void
{
    $payload = ['event' => $event];

    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $payload[$key] = $value;
            continue;
        }

        $payload[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    error_log('[Clio upload] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function getSharePointConfig(): array
{
    $settings = $GLOBALS['sharepointSettings'] ?? [];

    $uploadFolderFromSettings = array_key_exists('upload_folder', $settings)
        ? (string) $settings['upload_folder']
        : null;
    $uploadFolderFromEnv = getenv('SHAREPOINT_UPLOAD_FOLDER');
    $uploadFolder = $uploadFolderFromSettings;
    if ($uploadFolder === null) {
        $uploadFolder = $uploadFolderFromEnv !== false
            ? (string) $uploadFolderFromEnv
            : SHAREPOINT_DEFAULT_UPLOAD_FOLDER;
    }

    return [
        'site_id' => (string) ($settings['site_id'] ?? getenv('SHAREPOINT_SITE_ID') ?: ''),
        'drive_id' => (string) ($settings['drive_id'] ?? getenv('SHAREPOINT_DRIVE_ID') ?: ''),
        'list_id' => (string) ($settings['list_id'] ?? getenv('SHAREPOINT_LIST_ID') ?: ''),
        'upload_folder' => $uploadFolder,
        'status_field' => (string) ($settings['status_field'] ?? getenv('SHAREPOINT_STATUS_FIELD') ?: SHAREPOINT_DEFAULT_TRANSCRIPT_STATUS_FIELD),
        'access_token' => (string) ($settings['access_token'] ?? getenv('SHAREPOINT_ACCESS_TOKEN') ?: ''),
        'tenant_id' => (string) ($settings['tenant_id'] ?? getenv('SHAREPOINT_TENANT_ID') ?: ''),
        'client_id' => (string) ($settings['client_id'] ?? getenv('SHAREPOINT_CLIENT_ID') ?: ''),
        'client_secret' => (string) ($settings['client_secret'] ?? getenv('SHAREPOINT_CLIENT_SECRET') ?: ''),
        'token_scope' => (string) ($settings['token_scope'] ?? getenv('SHAREPOINT_TOKEN_SCOPE') ?: SHAREPOINT_DEFAULT_TOKEN_SCOPE),
        'token_url' => (string) ($settings['token_url'] ?? getenv('SHAREPOINT_TOKEN_URL') ?: ''),
        'verify_ssl' => $settings['verify_ssl'] ?? getenv('SHAREPOINT_VERIFY_SSL') ?? true,
        'ca_bundle' => (string) ($settings['ca_bundle'] ?? getenv('SHAREPOINT_CA_BUNDLE') ?: ''),
    ];
}

function toBool(mixed $value, bool $default): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
    }

    if (is_int($value)) {
        return $value !== 0;
    }

    return $default;
}

function assertSharePointConfig(array $config): void
{
    if ($config['site_id'] === '' || $config['drive_id'] === '' || $config['list_id'] === '') {
        throw new RuntimeException(LOC('error.sharepoint_config'));
    }

    $hasStaticToken = trim((string) ($config['access_token'] ?? '')) !== '';
    $hasOauthConfig = trim((string) ($config['tenant_id'] ?? '')) !== ''
        && trim((string) ($config['client_id'] ?? '')) !== ''
        && trim((string) ($config['client_secret'] ?? '')) !== '';

    if (!$hasStaticToken && !$hasOauthConfig) {
        throw new RuntimeException(LOC('error.sharepoint_token'));
    }
}

function getSharePointTokenCachePath(): string
{
    return __DIR__ . '/../data/sharepoint_token_cache.json';
}

function decodeJwtPayload(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) < 2) {
        return [];
    }

    $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
    if ($decoded === false) {
        return [];
    }

    $json = json_decode($decoded, true);
    return is_array($json) ? $json : [];
}

function validateGraphTokenClaims(string $token): void
{
    $payload = decodeJwtPayload($token);
    if ($payload === []) {
        return;
    }

    $audience = (string) ($payload['aud'] ?? '');
    if ($audience !== '' && $audience !== 'https://graph.microsoft.com') {
        throw new RuntimeException(LOC('error.sharepoint_permissions'));
    }

    $roles = $payload['roles'] ?? [];
    $scope = trim((string) ($payload['scp'] ?? ''));

    if (($roles === [] || !is_array($roles)) && $scope === '') {
        throw new RuntimeException(LOC('error.sharepoint_permissions'));
    }
}

function fetchSharePointAccessTokenWithClientCredentials(array $config): array
{
    if (isset($GLOBALS['sharepointTokenProvider']) && is_callable($GLOBALS['sharepointTokenProvider'])) {
        $provided = $GLOBALS['sharepointTokenProvider']($config);
        if (!is_array($provided) || !isset($provided['access_token'], $provided['expires_at'])) {
            throw new RuntimeException('Invalid token provider response.');
        }
        return $provided;
    }

    $tenantId = trim((string) ($config['tenant_id'] ?? ''));
    $tokenUrl = trim((string) ($config['token_url'] ?? ''));
    if ($tokenUrl === '') {
        $tokenUrl = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
    }

    $curl = curl_init($tokenUrl);
    if ($curl === false) {
        throw new RuntimeException(LOC('upload.error.http_client'));
    }

    $body = http_build_query([
        'client_id' => (string) ($config['client_id'] ?? ''),
        'client_secret' => (string) ($config['client_secret'] ?? ''),
        'scope' => (string) ($config['token_scope'] ?? SHAREPOINT_DEFAULT_TOKEN_SCOPE),
        'grant_type' => 'client_credentials',
    ]);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
    ]);

    $verifySsl = toBool($config['verify_ssl'] ?? true, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifySsl);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
    $caBundle = trim((string) ($config['ca_bundle'] ?? ''));
    if ($caBundle !== '') {
        curl_setopt($curl, CURLOPT_CAINFO, $caBundle);
    }

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('SharePoint token request failed: ' . $error);
    }

    $json = json_decode($response, true);
    if (!is_array($json) || $status < 200 || $status >= 300 || !isset($json['access_token'])) {
        throw new RuntimeException('SharePoint token request failed: ' . $response);
    }

    $expiresIn = max(300, (int) ($json['expires_in'] ?? 3600));

    return [
        'access_token' => (string) $json['access_token'],
        'expires_at' => time() + $expiresIn - 120,
    ];
}

function getSharePointAccessToken(array $config): string
{
    $staticToken = trim((string) ($config['access_token'] ?? ''));
    if ($staticToken !== '') {
        validateGraphTokenClaims($staticToken);
        return $staticToken;
    }

    $tenantId = trim((string) ($config['tenant_id'] ?? ''));
    $clientId = trim((string) ($config['client_id'] ?? ''));
    $clientSecret = trim((string) ($config['client_secret'] ?? ''));
    if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
        throw new RuntimeException(LOC('error.sharepoint_token'));
    }

    static $runtimeCache = [];
    $cacheKey = hash('sha256', $tenantId . '|' . $clientId);

    if (isset($runtimeCache[$cacheKey]) && (int) $runtimeCache[$cacheKey]['expires_at'] > time()) {
        $cachedToken = (string) $runtimeCache[$cacheKey]['access_token'];
        try {
            validateGraphTokenClaims($cachedToken);
            return $cachedToken;
        } catch (RuntimeException) {
            unset($runtimeCache[$cacheKey]);
        }
    }

    $cachePath = getSharePointTokenCachePath();
    if (is_file($cachePath)) {
        $cachedJson = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cachedJson) && isset($cachedJson[$cacheKey])) {
            $cached = $cachedJson[$cacheKey];
            if (is_array($cached) && isset($cached['access_token'], $cached['expires_at']) && (int) $cached['expires_at'] > time()) {
                $runtimeCache[$cacheKey] = $cached;
                $cachedToken = (string) $cached['access_token'];
                try {
                    validateGraphTokenClaims($cachedToken);
                    return $cachedToken;
                } catch (RuntimeException) {
                    unset($runtimeCache[$cacheKey]);
                }
            }
        }
    }

    $tokenData = fetchSharePointAccessTokenWithClientCredentials($config);
    $runtimeCache[$cacheKey] = $tokenData;

    $cacheDir = dirname($cachePath);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0750, true);
    }

    $fullCache = [];
    if (is_file($cachePath)) {
        $existing = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($existing)) {
            $fullCache = $existing;
        }
    }
    $fullCache[$cacheKey] = $tokenData;
    file_put_contents($cachePath, json_encode($fullCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $accessToken = (string) $tokenData['access_token'];
    validateGraphTokenClaims($accessToken);

    return $accessToken;
}

function sanitizeTitleToFilename(string $title): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($title));
    $safe = preg_replace('/[^a-zA-Z0-9 _\-.]/', '', $normalized ?? '');
    $safe = trim((string) $safe);

    if ($safe === '') {
        $safe = 'meeting-transcript-' . date('Ymd-His');
    }

    return safeUtf8Substr($safe, 0, 120);
}

function parseTxtFile(string $tmpFilePath): array
{
    $content = file_get_contents($tmpFilePath);
    if ($content === false) {
        throw new RuntimeException(LOC('upload.error.parse_failed'));
    }

    $content = trim(str_replace("\r\n", "\n", (string) $content));
    if ($content === '') {
        throw new RuntimeException(LOC('upload.error.empty_content'));
    }

    $lines = preg_split('/\n+/', $content);
    $title = trim((string) ($lines[0] ?? ''));
    if ($title === '') {
        $title = 'meeting-transcript-' . date('Ymd-His');
    }

    return [
        'title' => $title,
        'content' => $content,
    ];
}

function parseDocxFile(string $tmpFilePath): array
{
    $xml = false;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpFilePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
        }
    }

    if ($xml === false && function_exists('shell_exec')) {
        $escaped = escapeshellarg($tmpFilePath);
        $xml = shell_exec('unzip -p ' . $escaped . ' word/document.xml 2>NUL');
        if (!is_string($xml) || trim($xml) === '') {
            $xml = shell_exec('unzip -p ' . $escaped . ' word/document.xml 2>/dev/null');
        }
    }

    if ($xml === false) {
        throw new RuntimeException(LOC('upload.error.docx_support'));
    }

    $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
    $text = trim((string) $text);

    if ($text === '') {
        throw new RuntimeException(LOC('upload.error.empty_content'));
    }

    $lines = preg_split('/\n+/', $text);
    $title = trim((string) ($lines[0] ?? ''));
    if ($title === '') {
        $title = 'meeting-transcript-' . date('Ymd-His');
    }

    return [
        'title' => $title,
        'content' => $text,
    ];
}

function parseUploadedTranscriptFile(array $file): array
{
    $name = (string) ($file['name'] ?? '');
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);

    if ($error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE => LOC('upload.error.server_size'),
            UPLOAD_ERR_FORM_SIZE => LOC('upload.error.form_size'),
            UPLOAD_ERR_PARTIAL => LOC('upload.error.partial'),
            UPLOAD_ERR_NO_FILE => LOC('upload.error.file_required'),
            UPLOAD_ERR_NO_TMP_DIR => LOC('upload.error.no_tmp_dir'),
            UPLOAD_ERR_CANT_WRITE => LOC('upload.error.cant_write'),
            UPLOAD_ERR_EXTENSION => LOC('upload.error.extension_blocked'),
            default => LOC('upload.error.parse_failed'),
        };

        throw new RuntimeException($message);
    }

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(LOC('upload.error.file_required'));
    }

    if ($size <= 0 || $size > MAX_UPLOAD_SIZE_BYTES) {
        throw new RuntimeException(LOC('upload.error.parse_failed'));
    }

    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, ACCEPTED_UPLOAD_EXTENSIONS, true)) {
        throw new RuntimeException(LOC('upload.error.invalid_extension'));
    }

    $parsed = $extension === 'txt' ? parseTxtFile($tmpName) : parseDocxFile($tmpName);

    $parsed['original_name'] = $name;
    $parsed['extension'] = $extension;

    return $parsed;
}

function sharePointRequest(string $method, string $path, ?string $accessToken, array $headers = [], ?string $body = null): array
{
    $url = rtrim(SHAREPOINT_GRAPH_BASE, '/') . '/' . ltrim($path, '/');

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException(LOC('upload.error.http_client'));
    }

    $defaultHeaders = [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ];

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
    ]);

    $config = getSharePointConfig();
    $verifySsl = toBool($config['verify_ssl'] ?? true, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifySsl);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
    $caBundle = trim((string) ($config['ca_bundle'] ?? ''));
    if ($caBundle !== '') {
        curl_setopt($curl, CURLOPT_CAINFO, $caBundle);
    }

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('SharePoint request failed: ' . $error);
    }

    return [
        'status' => $status,
        'body' => (string) $response,
    ];
}

function getGraphErrorMessage(string $responseBody): string
{
    $json = json_decode($responseBody, true);
    if (is_array($json) && isset($json['error']['message']) && is_string($json['error']['message'])) {
        return trim($json['error']['message']);
    }

    return LOC('error.unexpected');
}

function hasMeetingSummaryStatus(mixed $value): bool
{
    if (is_string($value)) {
        return trim($value) === SHAREPOINT_STATUS_MEETING_SUMMARY;
    }

    if (is_array($value) && isset($value['Value']) && is_string($value['Value'])) {
        return trim($value['Value']) === SHAREPOINT_STATUS_MEETING_SUMMARY;
    }

    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) === SHAREPOINT_STATUS_MEETING_SUMMARY) {
                return true;
            }
        }
    }

    return false;
}

function hasUnprocessedTranscriptStatus(mixed $value): bool
{
    if (is_string($value)) {
        return trim($value) === SHAREPOINT_STATUS_UNPROCESSED;
    }

    if (is_array($value) && isset($value['Value']) && is_string($value['Value'])) {
        return trim($value['Value']) === SHAREPOINT_STATUS_UNPROCESSED;
    }

    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) === SHAREPOINT_STATUS_UNPROCESSED) {
                return true;
            }
        }
    }

    return false;
}

function sortSummariesByCreatedDesc(array $items): array
{
    usort($items, static function (array $left, array $right): int {
        $leftTs = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
        $rightTs = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

        return $rightTs <=> $leftTs;
    });

    return $items;
}

function getCurrentUserEmail(): string
{
    return strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
}

function sanitizeUploadedFilename(string $fileName): string
{
    $base = trim(basename($fileName));
    $base = preg_replace('/[\x00-\x1F]/', '', $base) ?? '';
    $base = preg_replace('/[<>:"\/\\|?*]+/', '_', $base) ?? '';
    $base = preg_replace('/\s+/', ' ', $base) ?? '';
    $base = trim($base, " .\t\n\r\0\x0B");

    if ($base === '') {
        $base = 'meeting-transcript-' . date('Ymd-His') . '.txt';
    }

    return safeUtf8Substr($base, 0, 180);
}

function sanitizeEmailForFilenamePrefix(string $email): string
{
    $normalized = strtolower(trim($email));
    if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return preg_replace('/[^a-z0-9@._+\-]/', '', $normalized) ?? '';
}

function buildUploadFilenameForUser(string $originalName, string $userEmail): string
{
    $safeOriginal = sanitizeUploadedFilename($originalName);
    $emailPrefix = sanitizeEmailForFilenamePrefix($userEmail);
    if ($emailPrefix === '') {
        return $safeOriginal;
    }

    $prefixed = $emailPrefix . '_' . $safeOriginal;
    return safeUtf8Substr($prefixed, 0, 220);
}

function detectUploadContentType(string $filename): string
{
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    if ($extension === 'docx') {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    return 'text/plain; charset=utf-8';
}

function extractFirstEmailFromText(string $value): string
{
    if (preg_match('/[A-Z0-9][A-Z0-9._%+\-]*@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches) === 1) {
        return strtolower(trim($matches[0], " \t\n\r\0\x0B_-"));
    }

    return '';
}

function getUserFavoritesPath(string $email): ?string
{
    $normalized = strtolower(trim($email));
    if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $safe = preg_replace('/[^a-z0-9._\-]/', '_', $normalized);
    return __DIR__ . '/../data/favorites_' . $safe . '.json';
}

function loadUserFavoriteStates(string $email): array
{
    $path = getUserFavoritesPath($email);
    if ($path === null || !is_file($path)) {
        return [];
    }

    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json) ? $json : [];
}

function saveUserFavoriteStates(string $email, array $states): void
{
    $path = getUserFavoritesPath($email);
    if ($path === null) {
        return;
    }

    $clean = [];
    foreach ($states as $driveItemId => $value) {
        $key = trim((string) $driveItemId);
        if ($key === '') {
            continue;
        }
        $clean[$key] = (bool) $value;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    file_put_contents($path, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function setUserFavoriteState(string $email, string $driveItemId, bool $state): void
{
    $states = loadUserFavoriteStates($email);
    $states[$driveItemId] = $state;
    saveUserFavoriteStates($email, $states);
}

function isSummaryOwnedByUser(string $summaryName, string $userEmail): bool
{
    $ownerEmail = extractFirstEmailFromText($summaryName);
    return $ownerEmail !== '' && $ownerEmail === strtolower(trim($userEmail));
}

function isSummaryFavoritedForUser(array $item, array $favoriteStates, string $userEmail): bool
{
    if (($item['is_openable'] ?? false) !== true) {
        return false;
    }

    $driveItemId = (string) ($item['drive_item_id'] ?? '');
    if ($driveItemId !== '' && array_key_exists($driveItemId, $favoriteStates)) {
        return (bool) $favoriteStates[$driveItemId];
    }

    return isSummaryOwnedByUser((string) ($item['name'] ?? ''), $userEmail);
}

function applyUserSummaryFlags(array $items, string $userEmail): array
{
    $favoriteStates = loadUserFavoriteStates($userEmail);

    foreach ($items as $index => $item) {
        $isOwned = isSummaryOwnedByUser((string) ($item['name'] ?? ''), $userEmail);
        $items[$index]['is_owned_by_user'] = $isOwned;
        $items[$index]['is_favorite'] = isSummaryFavoritedForUser($item, $favoriteStates, $userEmail);
    }

    return $items;
}

function sortSummariesByFavoriteThenCreatedDesc(array $items): array
{
    usort($items, static function (array $left, array $right): int {
        $leftFav = ($left['is_favorite'] ?? false) === true ? 1 : 0;
        $rightFav = ($right['is_favorite'] ?? false) === true ? 1 : 0;

        if ($leftFav !== $rightFav) {
            return $rightFav <=> $leftFav;
        }

        $leftTs = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
        $rightTs = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

        return $rightTs <=> $leftTs;
    });

    return $items;
}

function getSummaryCacheDir(): string
{
    return __DIR__ . '/../cache/summaries';
}

function getSummaryCacheTtlSeconds(): int
{
    return 7 * 24 * 60 * 60;
}

function getSummaryCachePath(string $driveItemId): string
{
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $driveItemId);
    return getSummaryCacheDir() . '/' . $safeId . '.md';
}

function getSummaryCacheWebPath(string $driveItemId): string
{
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $driveItemId);
    return 'cache/summaries/' . $safeId . '.md';
}

function pruneSummaryCacheFiles(int $maxAgeSeconds = 0): void
{
    $age = $maxAgeSeconds > 0 ? $maxAgeSeconds : getSummaryCacheTtlSeconds();
    $cacheDir = getSummaryCacheDir();
    if (!is_dir($cacheDir)) {
        return;
    }

    $entries = glob($cacheDir . '/*.md');
    if (!is_array($entries)) {
        return;
    }

    $now = time();
    foreach ($entries as $entry) {
        if (!is_file($entry)) {
            continue;
        }

        $mtime = filemtime($entry);
        if ($mtime === false) {
            continue;
        }

        if (($now - $mtime) > $age) {
            @unlink($entry);
        }
    }
}

function isCacheFileFresh(string $cachePath, int $ttlSeconds): bool
{
    if (!is_file($cachePath)) {
        return false;
    }

    $mtime = filemtime($cachePath);
    if ($mtime === false) {
        return false;
    }

    return (time() - $mtime) <= $ttlSeconds;
}

function getCachedSummaryText(string $driveItemId, int $ttlSeconds = 0): ?string
{
    pruneSummaryCacheFiles();

    if ($ttlSeconds <= 0) {
        $ttlSeconds = getSummaryCacheTtlSeconds();
    }

    $cachePath = getSummaryCachePath($driveItemId);
    if (!isCacheFileFresh($cachePath, $ttlSeconds)) {
        return null;
    }

    $content = file_get_contents($cachePath);
    return $content === false ? null : $content;
}

function saveCachedSummaryText(string $driveItemId, string $text): void
{
    pruneSummaryCacheFiles();

    $cacheDir = getSummaryCacheDir();
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0750, true);
    }

    file_put_contents(getSummaryCachePath($driveItemId), $text);
}

function summaryDownloadFilename(string $name, string $fallbackId): string
{
    $base = trim($name);
    if ($base === '') {
        $base = 'summary-' . $fallbackId;
    }

    $base = preg_replace('/\.[^.]+$/', '', $base);
    $base = sanitizeTitleToFilename((string) $base);

    return $base . '.md';
}

function extractCreatedTimestamp(array $item): int
{
    $createdAt = (string) ($item['created_at'] ?? '');
    return strtotime($createdAt) ?: 0;
}

function getLatestProcessedSummaryMinute(array $items): ?int
{
    $latestTimestamp = 0;

    foreach ($items as $item) {
        if (($item['is_openable'] ?? false) !== true) {
            continue;
        }

        $itemTimestamp = extractCreatedTimestamp($item);
        if ($itemTimestamp > $latestTimestamp) {
            $latestTimestamp = $itemTimestamp;
        }
    }

    if ($latestTimestamp <= 0) {
        return null;
    }

    return (int) gmdate('i', $latestTimestamp);
}

function estimateUnprocessedSummaryEta(array $items, string $uploadedAt, ?int $nowTimestamp = null): string
{
    $uploadedTimestamp = strtotime($uploadedAt);
    if ($uploadedTimestamp === false) {
        return LOC('summary.eta_less_than_minute');
    }

    $now = $nowTimestamp ?? time();
    $cycleMinute = getLatestProcessedSummaryMinute($items);
    if ($cycleMinute === null) {
        $cycleMinute = (int) gmdate('i', $uploadedTimestamp);
    }

    $hourStart = gmmktime((int) gmdate('H', $uploadedTimestamp), 0, 0, (int) gmdate('n', $uploadedTimestamp), (int) gmdate('j', $uploadedTimestamp), (int) gmdate('Y', $uploadedTimestamp));
    $nextCycle = $hourStart + ($cycleMinute * 60);
    while ($nextCycle <= $uploadedTimestamp) {
        $nextCycle += 3600;
    }

    if ($now >= $nextCycle) {
        return LOC('summary.eta_less_than_minute');
    }

    $minutesLeft = (int) ceil(($nextCycle - $now) / 60);
    if ($minutesLeft <= 0) {
        return LOC('summary.eta_less_than_minute');
    }

    return LOC('summary.eta_minutes', $minutesLeft);
}

function addEtaToUnprocessedSummaries(array $items, ?int $nowTimestamp = null): array
{
    foreach ($items as $index => $item) {
        if (($item['is_openable'] ?? false) === true) {
            $items[$index]['eta_text'] = '';
            continue;
        }

        $items[$index]['eta_text'] = estimateUnprocessedSummaryEta(
            $items,
            (string) ($item['created_at'] ?? ''),
            $nowTimestamp
        );
    }

    return $items;
}

function uploadTranscriptToSharePoint(string $title, string $fileContent, string $originalFilename, string $uploaderEmail = ''): array
{
    $config = getSharePointConfig();
    assertSharePointConfig($config);
    $accessToken = getSharePointAccessToken($config);

    $filename = buildUploadFilenameForUser($originalFilename, $uploaderEmail);
    $folderPath = trim($config['upload_folder'], '/');
    $uploadPath = rawurlencode($filename);
    if ($folderPath !== '') {
        $uploadPath = implode('/', array_map('rawurlencode', [$folderPath, $filename]));
    }

    $uploadResponse = sharePointRequest(
        'PUT',
        '/drives/' . rawurlencode($config['drive_id']) . '/root:/' . $uploadPath . ':/content',
        $accessToken,
        ['Content-Type: ' . detectUploadContentType($filename)],
        $fileContent
    );

    if ($uploadResponse['status'] < 200 || $uploadResponse['status'] >= 300) {
        throw new RuntimeException(getGraphErrorMessage($uploadResponse['body']));
    }

    $uploadJson = json_decode($uploadResponse['body'], true);
    $driveItemId = (string) ($uploadJson['id'] ?? '');

    if ($driveItemId === '') {
        throw new RuntimeException(LOC('upload.error.drive_item_missing'));
    }

    $fieldsPayload = json_encode([
        $config['status_field'] => SHAREPOINT_STATUS_UNPROCESSED,
        'Title' => $title,
    ], JSON_UNESCAPED_UNICODE);

    $metadataResponse = sharePointRequest(
        'PATCH',
        '/drives/' . rawurlencode($config['drive_id']) . '/items/' . rawurlencode($driveItemId) . '/listItem/fields',
        $accessToken,
        ['Content-Type: application/json'],
        $fieldsPayload
    );

    if ($metadataResponse['status'] < 200 || $metadataResponse['status'] >= 300) {
        $itemInfoResponse = sharePointRequest(
            'GET',
            '/drives/' . rawurlencode($config['drive_id']) . '/items/' . rawurlencode($driveItemId) . '?$select=sharepointIds',
            $accessToken
        );

        if ($itemInfoResponse['status'] >= 200 && $itemInfoResponse['status'] < 300) {
            $itemInfo = json_decode($itemInfoResponse['body'], true);
            $listItemId = (string) ($itemInfo['sharepointIds']['listItemId'] ?? '');

            if ($listItemId !== '') {
                $listMetadataResponse = sharePointRequest(
                    'PATCH',
                    '/sites/' . rawurlencode($config['site_id']) . '/lists/' . rawurlencode($config['list_id']) . '/items/' . rawurlencode($listItemId) . '/fields',
                    $accessToken,
                    ['Content-Type: application/json'],
                    $fieldsPayload
                );

                if ($listMetadataResponse['status'] < 200 || $listMetadataResponse['status'] >= 300) {
                    throw new RuntimeException(getGraphErrorMessage($listMetadataResponse['body']));
                }
            } else {
                throw new RuntimeException(getGraphErrorMessage($metadataResponse['body']));
            }
        } else {
            throw new RuntimeException(getGraphErrorMessage($metadataResponse['body']));
        }
    }

    return [
        'file_name' => $filename,
        'drive_item_id' => $driveItemId,
    ];
}

function fetchMeetingSummaries(): array
{
    $config = getSharePointConfig();
    assertSharePointConfig($config);
    $accessToken = getSharePointAccessToken($config);

    $path = '/drives/' . rawurlencode($config['drive_id']) . '/root/children?$expand=listItem($expand=fields)&$top=200';

    $response = sharePointRequest('GET', $path, $accessToken);

    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException(getGraphErrorMessage($response['body']));
    }

    $json = json_decode($response['body'], true);
    $items = $json['value'] ?? [];

    $results = [];
    foreach ($items as $item) {
        $fields = $item['listItem']['fields'] ?? [];
        $statusValue = $fields[$config['status_field']] ?? null;
        $isProcessed = hasMeetingSummaryStatus($statusValue);
        $isUnprocessed = hasUnprocessedTranscriptStatus($statusValue);

        if (!$isProcessed && !$isUnprocessed) {
            continue;
        }

        $results[] = [
            'list_item_id' => (string) ($item['listItem']['id'] ?? ''),
            'drive_item_id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? $fields['Title'] ?? 'Onbekend bestand'),
            'web_url' => (string) ($item['webUrl'] ?? ''),
            'title' => (string) ($fields['Title'] ?? ''),
            'status' => $isProcessed ? SHAREPOINT_STATUS_MEETING_SUMMARY : SHAREPOINT_STATUS_UNPROCESSED,
            'is_openable' => $isProcessed,
            'created_at' => (string) ($item['createdDateTime'] ?? $fields['Created'] ?? ''),
        ];
    }

    $results = sortSummariesByCreatedDesc($results);
    $results = addEtaToUnprocessedSummaries($results);

    $userEmail = getCurrentUserEmail();
    if ($userEmail !== '') {
        $results = applyUserSummaryFlags($results, $userEmail);
        $results = sortSummariesByFavoriteThenCreatedDesc($results);
    }

    return $results;
}

function getDriveItemInfoById(string $driveItemId): array
{
    $config = getSharePointConfig();
    assertSharePointConfig($config);
    $accessToken = getSharePointAccessToken($config);

    $path = '/drives/' . rawurlencode($config['drive_id']) . '/items/' . rawurlencode($driveItemId) . '?$select=id,name,file';

    $response = sharePointRequest('GET', $path, $accessToken);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException(LOC('summary.load_failed', getGraphErrorMessage($response['body'])));
    }

    $json = json_decode($response['body'], true);
    if (!is_array($json)) {
        return ['name' => '', 'mime_type' => ''];
    }

    return [
        'name' => (string) ($json['name'] ?? ''),
        'mime_type' => (string) ($json['file']['mimeType'] ?? ''),
    ];
}

function getDriveItemTextById(string $driveItemId): string
{
    $cached = getCachedSummaryText($driveItemId);
    if ($cached !== null) {
        return $cached;
    }

    $config = getSharePointConfig();
    assertSharePointConfig($config);
    $accessToken = getSharePointAccessToken($config);
    $itemInfo = getDriveItemInfoById($driveItemId);
    $name = strtolower($itemInfo['name']);
    $mimeType = strtolower($itemInfo['mime_type']);
    $isDocx = str_ends_with($name, '.docx')
        || str_contains($mimeType, 'wordprocessingml.document')
        || str_contains($mimeType, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = sharePointRequest(
        'GET',
        '/drives/' . rawurlencode($config['drive_id']) . '/items/' . rawurlencode($driveItemId) . '/content',
        $accessToken,
        $isDocx ? ['Accept: application/vnd.openxmlformats-officedocument.wordprocessingml.document, */*'] : ['Accept: text/plain, text/markdown, */*']
    );

    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException(LOC('summary.load_failed', getGraphErrorMessage($response['body'])));
    }

    if ($isDocx) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'clio_sp_docx_');
        if ($tmpFile === false) {
            throw new RuntimeException(LOC('upload.error.parse_failed'));
        }

        try {
            file_put_contents($tmpFile, $response['body']);
            $parsed = parseDocxFile($tmpFile);
            $content = trim((string) ($parsed['content'] ?? ''));
            saveCachedSummaryText($driveItemId, $content);
            return $content;
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    $content = trim($response['body']);
    saveCachedSummaryText($driveItemId, $content);

    return $content;
}

function getDriveItemStatusById(string $driveItemId): string
{
    $config = getSharePointConfig();
    assertSharePointConfig($config);
    $accessToken = getSharePointAccessToken($config);

    $path = '/sites/' . rawurlencode($config['site_id'])
        . '/drives/' . rawurlencode($config['drive_id']) . '/items/' . rawurlencode($driveItemId) . '/listItem?$expand=fields';

    $path = '/drives/' . rawurlencode($config['drive_id']) . '/items/' . rawurlencode($driveItemId) . '/listItem?$expand=fields';

    $response = sharePointRequest('GET', $path, $accessToken);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException(LOC('api.error.not_found'));
    }

    $json = json_decode($response['body'], true);
    $statusField = getSharePointConfig()['status_field'];

    return (string) ($json['fields'][$statusField] ?? '');
}

function parseActionPointsByPerson(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $grouped = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (!preg_match('/^(?:[-*]\s+)?(?:\[.?\]\s+)?([A-Za-zÀ-ÿ0-9 ._\-]+)\s*[:\-]\s+(.+)$/u', $line, $matches)) {
            continue;
        }

        $person = trim($matches[1]);
        $action = trim($matches[2]);

        if ($person === '' || $action === '') {
            continue;
        }

        if (!isset($grouped[$person])) {
            $grouped[$person] = [];
        }

        $grouped[$person][] = $action;
    }

    return $grouped;
}

function validateApiKey(?string $provided): bool
{
    $provided = trim((string) $provided);
    if ($provided === '') {
        return false;
    }

    $apiKeys = $GLOBALS['apiKeys'] ?? [];
    foreach ($apiKeys as $value) {
        if (hash_equals((string) $value, $provided)) {
            return true;
        }
    }

    return false;
}
