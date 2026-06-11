<?php

/**
 * Variabelen
 */

$page = (string) ($_GET['page'] ?? 'upload');
if (!in_array($page, ['upload', 'summaries', 'emails'], true)) {
    $page = 'upload';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$selectedSummaryId = trim((string) ($_GET['summary_id'] ?? ''));
$summaries = [];
$selectedSummaryText = '';
$selectedSummaryName = '';
$selectedSummaryPendingMessage = '';
$selectedSummaryCacheUrl = '';
$pageError = '';

$selectedEmailThreadFolder = trim((string) ($_GET['thread'] ?? ''));
$emailThreads = [];
$selectedEmailThread = null;
$emailSearchData = [
    'threads' => [],
    'messages' => [],
];
