<?php

/**
 * Page load
 */

if ($page === 'summaries') {
    try {
        $summaries = fetchMeetingSummaries();

        if ($selectedSummaryId !== '') {
            $selectedSummaryText = getDriveItemTextById($selectedSummaryId);

            foreach ($summaries as $summaryItem) {
                if ($summaryItem['drive_item_id'] === $selectedSummaryId) {
                    $selectedSummaryName = $summaryItem['name'];
                    break;
                }
            }
        }
    } catch (Throwable $exception) {
        $pageError = LOC('summary.load_failed', $exception->getMessage());
    }
}
