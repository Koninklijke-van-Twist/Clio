<?php

/**
 * Page load
 */

if ((string) ($_GET['action'] ?? '') === 'toggle_favorite') {
    $summaryId = trim((string) ($_GET['summary_id'] ?? ''));
    $userEmail = getCurrentUserEmail();

    if ($summaryId !== '' && $userEmail !== '') {
        $favoriteStates = loadUserFavoriteStates($userEmail);
        // Default: als nog niet expliciet opgeslagen, gebruik de weergegeven huidige staat
        // die meegegeven wordt door de view via de query-parameter.
        $currentState = (bool) ($favoriteStates[$summaryId] ?? (bool) (int) ($_GET['is_favorite'] ?? '0'));
        setUserFavoriteState($userEmail, $summaryId, !$currentState);
    }

    $selectedId = trim((string) ($_GET['selected_summary_id'] ?? ''));
    $redirectQuery = ['page' => 'summaries'];
    if ($selectedId !== '') {
        $redirectQuery['summary_id'] = $selectedId;
    }

    header('Location: ' . appUrl('index.php', $redirectQuery));
    exit;
}

if ((string) ($_GET['action'] ?? '') === 'download_summary') {
    try {
        $summaryId = trim((string) ($_GET['summary_id'] ?? ''));
        if ($summaryId === '') {
            throw new RuntimeException(LOC('api.error.summary_id_required'));
        }

        $summariesForDownload = fetchMeetingSummaries();
        $selectedDownloadItem = null;
        foreach ($summariesForDownload as $item) {
            if ((string) ($item['drive_item_id'] ?? '') === $summaryId) {
                $selectedDownloadItem = $item;
                break;
            }
        }

        if ($selectedDownloadItem === null || (($selectedDownloadItem['is_openable'] ?? false) !== true)) {
            throw new RuntimeException(LOC('api.error.invalid_summary_type'));
        }

        $text = getDriveItemTextById($summaryId);
        if ($text === '') {
            throw new RuntimeException(LOC('summary.load_failed', LOC('error.unexpected')));
        }

        $cachePath = getSummaryCachePath($summaryId);
        if (is_file($cachePath)) {
            header('Location: ' . getSummaryCacheWebPath($summaryId));
            exit;
        }

        $filename = summaryDownloadFilename((string) ($selectedDownloadItem['name'] ?? ''), $summaryId);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo $text;
        exit;
    } catch (Throwable $exception) {
        setFlash('error', LOC('summary.load_failed', $exception->getMessage()));
        header('Location: ' . appUrl('index.php', ['page' => 'summaries']));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'upload_transcript') {
    try {
        if (!isValidCsrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException(LOC('error.unexpected'));
        }

        if (empty($_POST['confirm_companywide'])) {
            throw new RuntimeException(LOC('upload.error.confirm_required'));
        }

        if (!isset($_FILES['transcript_file']) || !is_array($_FILES['transcript_file'])) {
            throw new RuntimeException(LOC('upload.error.file_required'));
        }

        $parsed = parseUploadedTranscriptFile($_FILES['transcript_file']);
        $uploadResult = uploadTranscriptToSharePoint(
            $parsed['title'],
            (string) $parsed['raw_content'],
            (string) $parsed['original_name'],
            getCurrentUserEmail()
        );

        setFlash('success', LOC('upload.success', $uploadResult['file_name']));
    } catch (Throwable $exception) {
        setFlash('error', LOC('upload.error.sharepoint', $exception->getMessage()));
    }

    header('Location: ' . appUrl('index.php', ['page' => 'upload']));
    exit;
}
