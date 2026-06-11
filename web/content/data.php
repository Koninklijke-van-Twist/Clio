<?php

/**
 * Page load
 */

if ($page === 'summaries') {
    try {
        $summaries = fetchMeetingSummaries();

        if ($selectedSummaryId !== '') {
            $selectedItem = null;
            foreach ($summaries as $summaryItem) {
                if ($summaryItem['drive_item_id'] === $selectedSummaryId) {
                    $selectedItem = $summaryItem;
                    break;
                }
            }

            if ($selectedItem === null) {
                throw new RuntimeException(LOC('api.error.not_found'));
            }

            $selectedSummaryName = (string) ($selectedItem['name'] ?? '');

            if (($selectedItem['is_openable'] ?? false) === true) {
                $selectedSummaryText = getDriveItemTextById($selectedSummaryId);
                $selectedSummaryCacheUrl = getSummaryCacheWebPath($selectedSummaryId);
            } else {
                $etaText = (string) ($selectedItem['eta_text'] ?? LOC('summary.eta_less_than_minute'));
                $selectedSummaryPendingMessage = LOC('summary.unprocessed_eta', $etaText);
            }
        }
    } catch (Throwable $exception) {
        $pageError = LOC('summary.load_failed', $exception->getMessage());
    }
}

if ($page === 'emails') {
    try {
        $emailThreads = loadEmailArchiveThreads();

        if ($selectedEmailThreadFolder === '' && $emailThreads !== []) {
            $selectedEmailThreadFolder = (string) ($emailThreads[0]['folder_name'] ?? '');
        }

        if ($selectedEmailThreadFolder !== '') {
            $selectedEmailThread = loadEmailArchiveThread($selectedEmailThreadFolder);
            if ($selectedEmailThread === null) {
                throw new RuntimeException(LOC('email.thread_not_found'));
            }
        }
    } catch (Throwable $exception) {
        $pageError = LOC('email.load_failed', $exception->getMessage());
    }
}
