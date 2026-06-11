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

function formatEmailArchiveContacts(array $contacts): array
{
    $formatted = [];
    $seen = [];

    foreach ($contacts as $contact) {
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

        $emails[] = $email;
    }

    return [
        'folder_name' => $parsed['folder_name'],
        'subject' => $parsed['subject'],
        'chain_id' => $parsed['chain_id'],
        'contacts' => is_array($meta['contacts'] ?? null) ? $meta['contacts'] : [],
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
