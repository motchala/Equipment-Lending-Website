<?php

declare(strict_types=1);

/**
 * google-sheets/core/reader.php
 *
 * Reads a Google Sheet via the Sheets API v4 (OAuth — works on private sheets).
 * Returns data in the same shape sync-inventory.php expects so it can use
 * either this reader or the XLSX export path with the same downstream logic.
 *
 * Public API:
 *   gs_read_sheet(string $sheet_id, string $token): array|null
 *
 * Returns:
 *   [
 *     'sheet_name' => [   // tab name as key
 *       ['col0', 'col1', ...],  // each row is an array of cell strings
 *       ...
 *     ],
 *     ...
 *   ]
 *   null on failure.
 *
 * Also:
 *   gs_sheet_modified_time(string $sheet_id, string $token): ?string
 *     Returns ISO 8601 modifiedTime from Drive API v3 (OAuth), or null on error.
 */

require_once __DIR__ . '/client.php';

function gs_read_sheet(string $sheet_id, string $token): ?array
{
    // Step 1: get sheet metadata (tab names)
    $meta = gs_api(
        'GET',
        "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}?fields=sheets.properties",
        null,
        $token
    );

    if (isset($meta['error'])) {
        error_log('[gs_read_sheet] metadata error: ' . json_encode($meta['error']));
        return null;
    }

    $tabs = [];
    foreach ($meta['sheets'] ?? [] as $sheet) {
        $title = (string)($sheet['properties']['title'] ?? '');
        if ($title !== '') {
            $tabs[] = $title;
        }
    }

    if (empty($tabs)) {
        return null;
    }

    // Step 2: fetch rows for every tab in one batchGet call
    $ranges = array_map(fn($t) => urlencode("'" . str_replace("'", "''", $t) . "'"), $tabs);
    $query  = 'ranges=' . implode('&ranges=', $ranges) . '&valueRenderOption=UNFORMATTED_VALUE';

    $data = gs_api(
        'GET',
        "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values:batchGet?{$query}",
        null,
        $token
    );

    if (isset($data['error'])) {
        error_log('[gs_read_sheet] batchGet error: ' . json_encode($data['error']));
        return null;
    }

    $result = [];
    foreach ($data['valueRanges'] ?? [] as $i => $vr) {
        $tabName = $tabs[$i] ?? ('Sheet' . ($i + 1));
        $rows    = [];
        foreach ($vr['values'] ?? [] as $row) {
            // Pad short rows so every row has the same column count as the header
            $rows[] = array_map('strval', $row);
        }
        $result[$tabName] = $rows;
    }

    return $result;
}

/**
 * Get the last-modified timestamp of the sheet from Drive API v3 (OAuth path).
 * Returns ISO 8601 string or null on error.
 */
function gs_sheet_modified_time(string $sheet_id, string $token): ?string
{
    $data = gs_api(
        'GET',
        "https://www.googleapis.com/drive/v3/files/{$sheet_id}?fields=modifiedTime",
        null,
        $token
    );

    if (isset($data['error']) || empty($data['modifiedTime'])) {
        error_log('[gs_sheet_modified_time] error: ' . json_encode($data));
        return null;
    }

    return (string)$data['modifiedTime'];
}
