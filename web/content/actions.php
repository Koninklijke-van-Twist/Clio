<?php

/**
 * Page load
 */

if ((string) ($_GET['action'] ?? '') === 'download_summary') {
    try {
        $summaryId = trim((string) ($_GET['summary_id'] ?? ''));
        if ($summaryId === '') {
            throw new RuntimeException(LOC('api.error.summary_id_required'));
        }

        $text = getDriveItemTextById($summaryId);
        $info = getDriveItemInfoById($summaryId);
        $filename = summaryDownloadFilename((string) ($info['name'] ?? ''), $summaryId);

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
        $uploadResult = uploadTranscriptToSharePoint($parsed['title'], $parsed['content']);

        setFlash('success', LOC('upload.success', $uploadResult['file_name']));
    } catch (Throwable $exception) {
        setFlash('error', LOC('upload.error.sharepoint', $exception->getMessage()));
    }

    header('Location: ' . appUrl('index.php', ['page' => 'upload']));
    exit;
}
