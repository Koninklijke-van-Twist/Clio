<?php

/**
 * Includes/requires
 */

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/localization.php';
require_once __DIR__ . '/content/constants.php';
require_once __DIR__ . '/content/helpers.php';

/**
 * Functies
 */

function apiResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Page load
 */

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
if (!validateApiKey($apiKey)) {
    apiResponse(401, [
        'status' => 'error',
        'message' => LOC('api.error.unauthorized'),
    ]);
}

$summaryId = trim((string) ($_GET['summary_id'] ?? ''));
if ($summaryId === '') {
    apiResponse(400, [
        'status' => 'error',
        'message' => LOC('api.error.summary_id_required'),
    ]);
}

try {
    $status = getDriveItemStatusById($summaryId);
    if ($status !== SHAREPOINT_STATUS_MEETING_SUMMARY) {
        apiResponse(422, [
            'status' => 'error',
            'message' => LOC('api.error.invalid_summary_type'),
        ]);
    }

    $text = getDriveItemTextById($summaryId);
    if ($text === '') {
        apiResponse(404, [
            'status' => 'error',
            'message' => LOC('api.error.not_found'),
        ]);
    }

    $actionPoints = parseActionPointsByPerson($text);

    apiResponse(200, [
        'status' => LOC('api.ok'),
        'summary_id' => $summaryId,
        'action_points_by_person' => $actionPoints,
    ]);
} catch (Throwable $exception) {
    apiResponse(500, [
        'status' => 'error',
        'message' => $exception->getMessage(),
    ]);
}
