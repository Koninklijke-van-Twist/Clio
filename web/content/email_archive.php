<?php

/**
 * Constants
 */

const EMAIL_ARCHIVE_FOLDER_SEPARATOR = '~';

/**
 * Public methods
 */

function getEmailArchiveRoot(): string
{
    $override = $GLOBALS['emailArchiveRoot'] ?? null;
    if (is_string($override) && trim($override) !== '') {
        return rtrim($override, '\\/');
    }

    return __DIR__ . '/../data/emails';
}

function normalizeEmailArchiveChainId(string $chainId): string
{
    $chainId = strtolower(trim($chainId));
    $chainId = trim($chainId, "<> \t\n\r\0\x0B");
    $chainId = str_replace(EMAIL_ARCHIVE_FOLDER_SEPARATOR, '-', $chainId);
    $chainId = preg_replace('/[^a-z0-9@._+\-=]/', '_', $chainId) ?? '';
    $chainId = trim($chainId, '._-');

    return $chainId !== '' ? $chainId : 'unknown-chain';
}

function sanitizeEmailArchiveSubject(string $subject): string
{
    $subject = str_replace(EMAIL_ARCHIVE_FOLDER_SEPARATOR, '-', $subject);
    $subject = preg_replace('/\s+/', ' ', trim($subject)) ?? '';
    $subject = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', '', $subject) ?? '';
    $subject = trim($subject, " .\t\n\r\0\x0B");

    if ($subject === '') {
        $subject = 'Geen onderwerp';
    }

    return safeUtf8Substr($subject, 0, 120);
}

function parseEmailArchiveFolderName(string $folderName): ?array
{
    $separatorPosition = strrpos($folderName, EMAIL_ARCHIVE_FOLDER_SEPARATOR);
    if ($separatorPosition === false) {
        return null;
    }

    $subject = trim(substr($folderName, 0, $separatorPosition));
    $chainId = normalizeEmailArchiveChainId(substr($folderName, $separatorPosition + 1));

    if ($subject === '' || $chainId === '') {
        return null;
    }

    return [
        'folder_name' => $folderName,
        'subject' => $subject,
        'chain_id' => $chainId,
    ];
}

function formatEmailArchiveDate(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $months = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maart',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'augustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'december',
    ];

    $month = $months[(int) date('n', $timestamp)] ?? '';
    return date('j', $timestamp) . ' ' . $month . ' ' . date('Y, H:i', $timestamp);
}

function normalizeEmailArchiveSearchText(string $text): string
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

    return mb_strtolower($text, 'UTF-8');
}

function buildEmailArchiveEmailSearchText(string $threadPath, array $email): string
{
    $parts = [
        (string) ($email['subject'] ?? ''),
        (string) ($email['from'] ?? ''),
        implode(' ', (array) ($email['to'] ?? [])),
        implode(' ', (array) ($email['cc'] ?? [])),
        implode(' ', (array) ($email['bcc'] ?? [])),
    ];

    $textFile = basename((string) ($email['text_file'] ?? ''));
    if ($textFile !== '') {
        $textPath = $threadPath . DIRECTORY_SEPARATOR . $textFile;
        if (is_file($textPath)) {
            $parts[] = (string) file_get_contents($textPath);
        }
    }

    $htmlFile = basename((string) ($email['html_file'] ?? ''));
    if ($htmlFile !== '') {
        $htmlPath = $threadPath . DIRECTORY_SEPARATOR . $htmlFile;
        if (is_file($htmlPath)) {
            $parts[] = strip_tags((string) file_get_contents($htmlPath));
        }
    }

    return normalizeEmailArchiveSearchText(implode("\n", $parts));
}

function buildEmailArchiveThreadEmailSearchTexts(string $threadPath, array $meta): array
{
    $texts = [];

    foreach (($meta['emails'] ?? []) as $email) {
        if (!is_array($email)) {
            continue;
        }

        $texts[] = buildEmailArchiveEmailSearchText($threadPath, $email);
    }

    return $texts;
}

function buildEmailArchiveThreadSearchText(string $threadPath, array $meta): string
{
    return normalizeEmailArchiveSearchText(implode(' ', buildEmailArchiveThreadEmailSearchTexts($threadPath, $meta)));
}

function resolveEmailArchiveEmlPath(string $folderName, string $emlFile): ?string
{
    $parsed = parseEmailArchiveFolderName($folderName);
    if ($parsed === null) {
        return null;
    }

    $emlFile = basename($emlFile);
    if ($emlFile === '' || !str_ends_with(strtolower($emlFile), '.eml')) {
        return null;
    }

    $root = getEmailArchiveRoot();
    $threadPath = realpath($root . DIRECTORY_SEPARATOR . $folderName);
    $rootPath = realpath($root);
    if ($threadPath === false || $rootPath === false || !str_starts_with($threadPath, $rootPath . DIRECTORY_SEPARATOR)) {
        return null;
    }

    $meta = loadEmailArchiveMeta($threadPath);
    $allowed = false;
    foreach (($meta['emails'] ?? []) as $email) {
        if (!is_array($email)) {
            continue;
        }

        if (basename((string) ($email['eml_file'] ?? '')) === $emlFile) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        return null;
    }

    $emlPath = $threadPath . DIRECTORY_SEPARATOR . $emlFile;
    if (!is_file($emlPath)) {
        return null;
    }

    return $emlPath;
}

function getEmailArchiveEmlDownloadUrl(string $folderName, string $emlFile): string
{
    return appUrl('index.php', [
        'page' => 'emails',
        'action' => 'download_email_eml',
        'thread' => $folderName,
        'eml' => basename($emlFile),
    ]);
}

function getEmailArchiveMailboxAddress(): string
{
    $mailSettings = $GLOBALS['mailSettings'] ?? null;
    if (!is_array($mailSettings)) {
        return '';
    }

    return strtolower(trim((string) ($mailSettings['from_email'] ?? '')));
}

function filterEmailArchiveContacts(array $contacts): array
{
    $mailbox = getEmailArchiveMailboxAddress();
    $filtered = [];
    $seen = [];

    foreach ($contacts as $contact) {
        if (!is_array($contact)) {
            continue;
        }

        $email = strtolower(trim((string) ($contact['email'] ?? '')));
        if ($email === '' || isset($seen[$email])) {
            continue;
        }

        if ($mailbox !== '' && $email === $mailbox) {
            continue;
        }

        $seen[$email] = true;
        $filtered[] = $contact;
    }

    return $filtered;
}

function formatEmailArchiveContacts(array $contacts): array
{
    $formatted = [];
    $seen = [];

    foreach (filterEmailArchiveContacts($contacts) as $contact) {
        if (!is_array($contact)) {
            continue;
        }

        $email = strtolower(trim((string) ($contact['email'] ?? '')));
        if ($email === '' || isset($seen[$email])) {
            continue;
        }

        $name = trim((string) ($contact['name'] ?? ''));
        if ($name === '' && is_array($contact['names'] ?? null)) {
            $name = trim((string) ($contact['names'][0] ?? ''));
        }

        $formatted[] = $name !== '' ? $name . ' <' . $email . '>' : $email;
        $seen[$email] = true;
    }

    natcasesort($formatted);
    return array_values($formatted);
}

function loadEmailArchiveThreads(): array
{
    $root = getEmailArchiveRoot();
    if (!is_dir($root)) {
        return [];
    }

    $entries = scandir($root);
    if (!is_array($entries)) {
        return [];
    }

    $threads = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $root . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($path)) {
            continue;
        }

        $parsed = parseEmailArchiveFolderName($entry);
        if ($parsed === null) {
            continue;
        }

        $meta = loadEmailArchiveMeta($path);
        $threads[] = [
            'folder_name' => $parsed['folder_name'],
            'subject' => $parsed['subject'],
            'chain_id' => $parsed['chain_id'],
            'contacts' => formatEmailArchiveContacts(is_array($meta['contacts'] ?? null) ? $meta['contacts'] : []),
            'path' => $path,
            'email_count' => count($meta['emails'] ?? []),
            'updated_at' => (string) ($meta['updated_at'] ?? ''),
            'search_text' => buildEmailArchiveThreadSearchText($path, $meta),
            'email_search_texts' => buildEmailArchiveThreadEmailSearchTexts($path, $meta),
        ];
    }

    usort($threads, static function (array $left, array $right): int {
        $leftTime = strtotime((string) ($left['updated_at'] ?? '')) ?: 0;
        $rightTime = strtotime((string) ($right['updated_at'] ?? '')) ?: 0;

        if ($leftTime !== $rightTime) {
            return $rightTime <=> $leftTime;
        }

        return strcasecmp((string) $left['subject'], (string) $right['subject']);
    });

    return $threads;
}

function loadEmailArchiveThread(string $folderName): ?array
{
    $parsed = parseEmailArchiveFolderName($folderName);
    if ($parsed === null) {
        return null;
    }

    $root = getEmailArchiveRoot();
    $threadPath = realpath($root . DIRECTORY_SEPARATOR . $folderName);
    $rootPath = realpath($root);
    if ($threadPath === false || $rootPath === false || !str_starts_with($threadPath, $rootPath . DIRECTORY_SEPARATOR)) {
        return null;
    }

    $meta = loadEmailArchiveMeta($threadPath);
    $emails = [];
    foreach (($meta['emails'] ?? []) as $email) {
        if (!is_array($email)) {
            continue;
        }

        $textFile = basename((string) ($email['text_file'] ?? ''));
        if ($textFile === '') {
            continue;
        }

        $textPath = $threadPath . DIRECTORY_SEPARATOR . $textFile;
        $body = is_file($textPath) ? (string) file_get_contents($textPath) : '';
        $email['body_text'] = $body;

        $htmlFile = basename((string) ($email['html_file'] ?? ''));
        if ($htmlFile !== '') {
            $htmlPath = $threadPath . DIRECTORY_SEPARATOR . $htmlFile;
            $email['body_html'] = is_file($htmlPath) ? (string) file_get_contents($htmlPath) : '';
        } else {
            $email['body_html'] = '';
        }

        $email['search_text'] = buildEmailArchiveEmailSearchText($threadPath, $email);

        $emails[] = $email;
    }

    return [
        'folder_name' => $parsed['folder_name'],
        'subject' => $parsed['subject'],
        'chain_id' => $parsed['chain_id'],
        'contacts' => filterEmailArchiveContacts(is_array($meta['contacts'] ?? null) ? $meta['contacts'] : []),
        'contact_labels' => formatEmailArchiveContacts(is_array($meta['contacts'] ?? null) ? $meta['contacts'] : []),
        'emails' => $emails,
    ];
}

/**
 * Private Methods
 */

function loadEmailArchiveMeta(string $threadPath): array
{
    $metaPath = $threadPath . DIRECTORY_SEPARATOR . 'meta.json';
    if (!is_file($metaPath)) {
        return [
            'emails' => [],
            'contacts' => [],
        ];
    }

    $meta = json_decode((string) file_get_contents($metaPath), true);
    return is_array($meta) ? $meta : ['emails' => [], 'contacts' => []];
}
