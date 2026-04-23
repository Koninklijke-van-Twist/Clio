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
