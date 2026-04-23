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

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $page === 'upload'
    && $_POST === []
    && $_FILES === []
) {
    logUploadDiagnostic('empty_post_request', [
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'session_id' => session_id(),
    ]);
    setFlash('error', LOC('upload.error.request_empty'));
    header('Location: ' . appUrl('index.php', ['page' => 'upload']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'upload_transcript') {
    $redirectQuery = ['page' => 'summaries'];

    try {
        logUploadDiagnostic('upload_post_received', [
            'post_keys' => array_keys($_POST),
            'file_keys' => array_keys($_FILES),
            'session_id' => session_id(),
            'user_email' => getCurrentUserEmail(),
        ]);

        if (!isValidCsrf($_POST['csrf_token'] ?? null)) {
            logUploadDiagnostic('upload_csrf_failed', [
                'session_id' => session_id(),
                'posted_token_present' => isset($_POST['csrf_token']),
                'session_token_present' => isset($_SESSION['csrf_token']),
            ]);
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
            (string) $parsed['content'],
            (string) $parsed['original_name'],
            getCurrentUserEmail()
        );

        logUploadDiagnostic('upload_sharepoint_success', [
            'file_name' => $uploadResult['file_name'] ?? null,
            'drive_item_id' => $uploadResult['drive_item_id'] ?? null,
        ]);

        if (!empty($uploadResult['drive_item_id'])) {
            $redirectQuery['summary_id'] = (string) $uploadResult['drive_item_id'];
        }

        setFlash('success', LOC('upload.success', $uploadResult['file_name']));
    } catch (Throwable $exception) {
        logUploadDiagnostic('upload_failed', [
            'message' => $exception->getMessage(),
            'file_error' => $_FILES['transcript_file']['error'] ?? null,
            'file_name' => $_FILES['transcript_file']['name'] ?? null,
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
        ]);
        setFlash('error', LOC('upload.error.sharepoint', $exception->getMessage()));
    }

    header('Location: ' . appUrl('index.php', $redirectQuery));
    exit;
}
