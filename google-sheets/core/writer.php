<?php

declare(strict_types=1);

/**
 * google-sheets/core/writer.php
 *
 * Writes one inventory item back to the connected Google Sheet.
 * Called after every add / edit / archive in the Equipment tab.
 * Silent on all errors — the DB save already succeeded; the sheet is a mirror.
 *
 * Two sheet formats are detected automatically from the header row:
 *
 *   Format A — one row per physical unit (Chem Lab, Dispensing Room)
 *     Col A: ITEM NO.   Col B: DESCRIPTION   Col C: CONTROL NO.
 *     Col D: LOCATION   Col E: STATUS         Col F: REMARKS
 *     Write-back: set STATUS of the first $qty rows matching the item to OK/NG,
 *                 remaining matching rows to NG.
 *
 *   Format B — one row per item type with a Quantity column
 *     Col A: NO.   Col B: NAME   Col C: DESCRIPTION
 *     Col D: Quantity   Col E: STATUS   Col F: REMARKS   Col G: LOCATION
 *     Write-back: update Quantity (col D) and STATUS (col E) of the matching row.
 *
 * Public API:
 *   gs_sync_item(mysqli $conn, int $item_id, string $action): void
 *     action: 'upsert' (add or edit) | 'archive'
 */

require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/client.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Map PUPSync condition to the sheet STATUS value.
 * Format A sheets (one row per unit, CONTROL NO. column): OK / NG
 * Format B sheets (one row per item type, Quantity column): Working / Not Working
 * Fair maps to the "usable" value in both formats — it means the item still works.
 */
function gs_condition_to_status(string $condition, string $format = 'A'): string
{
    if ($format === 'B') {
        return $condition === 'For Repair' ? 'Not Working' : 'Working';
    }
    // Format A: binary OK / NG
    return $condition === 'For Repair' ? 'NG' : 'OK';
}

/**
 * Escape a tab name for use in an A1 range notation.
 * Wraps in single quotes and escapes any internal single quotes.
 */
function gs_tab_ref(string $tab): string
{
    return "'" . str_replace("'", "''", $tab) . "'";
}

/**
 * Fetch all values from a tab as a 2D array (rows × columns).
 * Returns empty array on error.
 */
function gs_read_tab(string $sheet_id, string $tab, string $token): array
{
    $range = gs_tab_ref($tab);
    $url   = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id
           . '/values/' . rawurlencode($range)
           . '?valueRenderOption=UNFORMATTED_VALUE';
    $data  = gs_api('GET', $url, null, $token);
    if (isset($data['error'])) {
        error_log('[gs_read_tab] ' . $tab . ': ' . json_encode($data['error']));
        return [];
    }
    return $data['values'] ?? [];
}

/**
 * Detect which format a tab uses by inspecting its header row.
 * Returns 'A' (control-number format) or 'B' (quantity format).
 * Falls back to 'B' when the header cannot be found.
 */
function gs_detect_format(array $rows): string
{
    foreach ($rows as $row) {
        $headers = array_map(fn($c) => strtolower(trim((string)$c)), $row);
        if (in_array('control no.', $headers, true) || in_array('control no', $headers, true)) {
            return 'A';
        }
        if (in_array('quantity', $headers, true)) {
            return 'B';
        }
    }
    return 'B';
}

/**
 * Find the 0-based index of the header row (the row containing DESCRIPTION or NAME).
 * Returns -1 when not found.
 */
function gs_header_row_index(array $rows): int
{
    foreach ($rows as $i => $row) {
        $headers = array_map(fn($c) => strtolower(trim((string)$c)), $row);
        if (in_array('description', $headers, true) || in_array('name', $headers, true)) {
            return $i;
        }
    }
    return -1;
}

/**
 * Update a single cell in the sheet.
 * $row and $col are 1-based.
 */
function gs_update_cell(string $sheet_id, string $tab, int $row, string $col, mixed $value, string $token): void
{
    $range  = gs_tab_ref($tab) . '!' . $col . $row;
    $url    = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id
            . '/values/' . rawurlencode($range)
            . '?valueInputOption=RAW';
    $result = gs_api('PUT', $url, ['range' => $range, 'values' => [[(string)$value]], 'majorDimension' => 'ROWS'], $token);
    if (isset($result['error'])) {
        error_log('[gs_update_cell] ' . $tab . '!' . $col . $row . ': ' . json_encode($result['error']));
    }
}

/**
 * Batch-update a range of STATUS cells (column E) for Format A.
 * $row_numbers is an array of 1-based row numbers to update.
 */
function gs_batch_update_status(string $sheet_id, string $tab, array $row_numbers, string $status, string $token): void
{
    if (empty($row_numbers)) return;

    $data = [];
    foreach ($row_numbers as $row) {
        $range    = gs_tab_ref($tab) . '!E' . $row;
        $data[]   = ['range' => $range, 'values' => [[$status]], 'majorDimension' => 'ROWS'];
    }

    $url    = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id . '/values:batchUpdate';
    $result = gs_api('POST', $url, ['valueInputOption' => 'RAW', 'data' => $data], $token);
    if (isset($result['error'])) {
        error_log('[gs_batch_update_status] ' . $tab . ': ' . json_encode($result['error']));
    }
}

// ── Main function ─────────────────────────────────────────────────────────────

function gs_sync_item(mysqli $conn, int $item_id, string $action): void
{
    try {
        // ── 1. Check OAuth connection ─────────────────────────────────────────
        $token = gs_token($conn);
        if ($token === null) return;

        // ── 2. Load sheet ID from DB ──────────────────────────────────────────
        $sheet_id = '';
        $us = $conn->prepare("SELECT value FROM tbl_sync_config WHERE config_key = 'gsheet_url' LIMIT 1");
        if ($us) {
            $us->execute();
            $ur = $us->get_result()->fetch_assoc();
            $us->close();
            $raw = trim((string)($ur['value'] ?? ''));
            if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $raw, $m)) {
                $sheet_id = $m[1];
            }
        }
        if ($sheet_id === '') return;

        // ── 3. Load item from DB ──────────────────────────────────────────────
        $is = $conn->prepare(
            'SELECT item_name, category, quantity, `condition` FROM tbl_inventory WHERE item_id = ? LIMIT 1'
        );
        if (!$is) return;
        $is->bind_param('i', $item_id);
        $is->execute();
        $item = $is->get_result()->fetch_assoc();
        $is->close();
        if (!$item) return;

        $item_name    = (string)$item['item_name'];
        $category     = (string)$item['category'];   // = tab name
        $qty          = $action === 'archive' ? 0 : (int)$item['quantity'];
        $item_cond    = (string)$item['condition'];  // resolved after format detection

        // ── 4. Read the tab ───────────────────────────────────────────────────
        $rows = gs_read_tab($sheet_id, $category, $token);
        if (empty($rows)) {
            error_log('[gs_sync_item] Could not read tab "' . $category . '" for item "' . $item_name . '"');
            return;
        }

        $format        = gs_detect_format($rows);
        $header_idx    = gs_header_row_index($rows);
        // Now that we know the format, map condition to the correct status string
        $condition     = $action === 'archive' ? ($format === 'B' ? 'Not Working' : 'NG') : gs_condition_to_status($item_cond, $format);
        if ($header_idx < 0) {
            error_log('[gs_sync_item] No header row found in tab "' . $category . '"');
            return;
        }

        // Name column index: col B (index 1) for both formats
        $name_col_idx = 1;

        $search = mb_strtolower(trim($item_name));

        if ($format === 'A') {
            // ── Format A: one row per physical unit ───────────────────────────
            // Find all data rows where column B matches the item name.
            $matching_rows = [];   // 1-based sheet row numbers
            foreach ($rows as $i => $row) {
                if ($i <= $header_idx) continue;   // skip header and meta rows
                $cell = mb_strtolower(trim((string)($row[$name_col_idx] ?? '')));
                if ($cell === $search) {
                    $matching_rows[] = $i + 1;   // 1-based
                }
            }

            if (empty($matching_rows)) {
                // Item not in sheet — append $qty new rows
                if ($qty > 0) {
                    // Scan ALL control numbers in the tab to find the dominant prefix
                    // and the highest numeric suffix used so far.
                    $prefix_counts = [];  // prefix => count of occurrences
                    $prefix_max    = [];  // prefix => highest numeric suffix seen
                    $last_item_no  = 0;
                    foreach ($rows as $row) {
                        $no = (int)($row[0] ?? 0);
                        if ($no > $last_item_no) $last_item_no = $no;

                        $ctrl = trim((string)($row[2] ?? ''));
                        if ($ctrl === '') continue;
                        // Match any BIN-XX- or BIN-XX-XX- style prefix followed by digits
                        if (preg_match('/^(BIN(?:-[A-Z0-9]+)+-)(\d+)$/i', $ctrl, $pm)) {
                            $p = strtoupper($pm[1]);
                            $n = (int)$pm[2];
                            $prefix_counts[$p] = ($prefix_counts[$p] ?? 0) + 1;
                            if ($n > ($prefix_max[$p] ?? 0)) $prefix_max[$p] = $n;
                        }
                    }
                    // Use the prefix with the most rows — that is the sheet's real format
                    $prefix       = 'BIN-NEW-';
                    $last_ctrl_no = 0;
                    if (!empty($prefix_counts)) {
                        arsort($prefix_counts);
                        $prefix       = array_key_first($prefix_counts);
                        $last_ctrl_no = $prefix_max[$prefix] ?? 0;
                    }
                    $new_rows = [];
                    for ($n = 1; $n <= $qty; $n++) {
                        $item_no    = $last_item_no + $n;
                        $ctrl_no    = $last_ctrl_no + $n;
                        $ctrl       = $prefix . str_pad((string)$ctrl_no, 3, '0', STR_PAD_LEFT);
                        $new_rows[] = [$item_no, $item_name, $ctrl, $category, $condition, ''];
                    }
                    $append_range = gs_tab_ref($category) . '!A:F';
                    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id
                         . '/values/' . rawurlencode($append_range)
                         . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';
                    $result = gs_api('POST', $url, [
                        'range'          => $append_range,
                        'values'         => $new_rows,
                        'majorDimension' => 'ROWS',
                    ], $token);
                    if (isset($result['error'])) {
                        error_log('[gs_sync_item] Format A append error for "' . $item_name . '": ' . json_encode($result['error']));
                    }
                }
                return;
            }

            // Update STATUS: first $qty rows → condition status, the rest → NG
            $ok_rows = array_slice($matching_rows, 0, $qty);
            $ng_rows = array_slice($matching_rows, $qty);

            if (!empty($ok_rows)) {
                gs_batch_update_status($sheet_id, $category, $ok_rows, $condition, $token);
            }
            if (!empty($ng_rows)) {
                gs_batch_update_status($sheet_id, $category, $ng_rows, 'NG', $token);
            }

        } else {
            // ── Format B: one row per item type, has Quantity column ──────────
            // Find the header to locate Quantity and Status column indices.
            $header_row = array_map(fn($c) => strtolower(trim((string)$c)), $rows[$header_idx]);
            $qty_col_idx    = array_search('quantity', $header_row);
            $status_col_idx = array_search('status', $header_row);

            // Find the data row matching the item name in column B
            $found_row = null;
            foreach ($rows as $i => $row) {
                if ($i <= $header_idx) continue;
                $cell = mb_strtolower(trim((string)($row[$name_col_idx] ?? '')));
                if ($cell === $search) {
                    $found_row = $i + 1;   // 1-based
                    break;
                }
            }

            if ($found_row !== null) {
                // Update Quantity and STATUS in-place
                if ($qty_col_idx !== false) {
                    $qty_col_letter = chr(65 + (int)$qty_col_idx);   // 0→A, 3→D, etc.
                    gs_update_cell($sheet_id, $category, $found_row, $qty_col_letter, $qty, $token);
                }
                if ($status_col_idx !== false) {
                    $status_col_letter = chr(65 + (int)$status_col_idx);
                    gs_update_cell($sheet_id, $category, $found_row, $status_col_letter, $condition, $token);
                }
            } else {
                // Not found — append a new row
                if ($qty > 0) {
                    // Build a row matching the header column order
                    $last_no    = 0;
                    foreach ($rows as $row) {
                        $no = (int)($row[0] ?? 0);
                        if ($no > $last_no) $last_no = $no;
                    }
                    $new_row = array_fill(0, count($header_row), '');
                    $new_row[0] = $last_no + 1;
                    $new_row[$name_col_idx] = $item_name;
                    if ($qty_col_idx !== false)    $new_row[(int)$qty_col_idx]    = $qty;
                    if ($status_col_idx !== false) $new_row[(int)$status_col_idx] = $condition;

                    $append_range = gs_tab_ref($category) . '!A:' . chr(65 + count($header_row) - 1);
                    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id
                         . '/values/' . rawurlencode($append_range)
                         . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';
                    $result = gs_api('POST', $url, [
                        'range'          => $append_range,
                        'values'         => [$new_row],
                        'majorDimension' => 'ROWS',
                    ], $token);
                    if (isset($result['error'])) {
                        error_log('[gs_sync_item] Format B append error for "' . $item_name . '": ' . json_encode($result['error']));
                    }
                }
            }
        }

    } catch (Throwable $e) {
        error_log('[gs_sync_item] ' . $e->getMessage());
        // Never propagate — sheet sync failure must not block the admin's action
    }
}
