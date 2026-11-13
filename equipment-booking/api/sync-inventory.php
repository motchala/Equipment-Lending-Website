<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Super Admin only.']);
    exit;
}

// ── Load SimpleXLSX ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../../vendor/SimpleXLSX/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

// ── Sheets that are never borrowable — always skip ───────────────────────────
const SKIP_SHEETS = [
    'offices inventory', 'software list', 'sports & development equipment',
    'sports and development equipment', 'sports&devt equipment',
    'ventilation', 'fire extinguisher', 'fire extinguishers',
];

// ── Campus detection keywords ─────────────────────────────────────────────────
// Canlalay = CITE (campus_id 2), Zapote = Main (campus_id 1)
function gsheet_detect_campus(array $metaRows): int
{
    foreach ($metaRows as $row) {
        foreach ($row as $i => $cell) {
            if (stripos((string)$cell, 'Campus:') !== false) {
                $next = trim((string)($row[$i + 1] ?? ''));
                if (stripos($next, 'Canlalay') !== false) return 2; // CITE
                if (stripos($next, 'Zapote') !== false)   return 1; // Main
            }
        }
    }
    return 2; // default to CITE when both campuses appear (Canlalay comes first)
}

// ── Normalise status string to condition value ────────────────────────────────
function gsheet_condition(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'ok' || $s === 'working') return 'Good';
    if ($s === 'ng' || $s === 'not working' || $s === 'for repair') return 'For Repair';
    return 'Fair'; // any other value
}

// ── Get or create a category for a campus ────────────────────────────────────
function gsheet_ensure_category(mysqli $conn, int $campus_id, string $name): void
{
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO tbl_equipment_categories (campus_id, name) VALUES (?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('is', $campus_id, $name);
        $stmt->execute();
        $stmt->close();
    }
}

// ── Fetch the Google Sheet as XLSX bytes ─────────────────────────────────────
$cfg_stmt = $conn->prepare("SELECT config_key, value FROM tbl_sync_config WHERE config_key IN ('gsheet_url','gsheet_sync_enabled')");
$cfg_stmt->execute();
$cfg_rows = $cfg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cfg_stmt->close();
$cfg = [];
foreach ($cfg_rows as $r) $cfg[$r['config_key']] = $r['value'];

$gsheet_url = trim((string)($cfg['gsheet_url'] ?? ''));
if ($gsheet_url === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'No Google Sheets URL configured. Save the link in Settings → Imports → Equipment Inventory first.']);
    exit;
}

// Extract the spreadsheet ID from the URL
if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $gsheet_url, $m)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid Google Sheets URL.']);
    exit;
}
$sheet_id = $m[1];
$xlsx_url  = "https://docs.google.com/spreadsheets/d/{$sheet_id}/export?format=xlsx";

// Download the XLSX
$ctx = stream_context_create([
    'http' => [
        'timeout'         => 30,
        'follow_location' => 1,
        'max_redirects'   => 5,
        'header'          => "User-Agent: PUPSync/1.0\r\n",
    ],
    'ssl' => ['verify_peer' => false],
]);
$xlsx_bytes = @file_get_contents($xlsx_url, false, $ctx);
if ($xlsx_bytes === false || strlen($xlsx_bytes) < 100) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'error' => 'Could not fetch the Google Sheet. Make sure sharing is set to "Anyone with the link can view".']);
    exit;
}

// Parse it
$xlsx = SimpleXLSX::parseData($xlsx_bytes);
if (!$xlsx) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'error' => 'Could not parse the spreadsheet: ' . SimpleXLSX::parseError()]);
    exit;
}

// ── Process each sheet ────────────────────────────────────────────────────────
$summary = [
    'added'          => 0,
    'updated'        => 0,
    'archived'       => 0,
    'skipped_sheets' => [],
    'errors'         => [],
];

// Collect all item_names currently seen in the sync (to detect deletions)
$synced_item_keys = []; // "item_name||campus_id"

$sheet_names = $xlsx->sheetNames();

foreach ($sheet_names as $sheet_idx => $sheet_name) {
    $sheet_name_clean = trim($sheet_name);

    // Skip non-borrowable sheets
    if (in_array(strtolower($sheet_name_clean), SKIP_SHEETS, true)) {
        $summary['skipped_sheets'][] = $sheet_name_clean;
        continue;
    }

    /** @var array $rows  SimpleXLSX::rows() has no return type, so editors would otherwise see each row as TYield */
    $rows = $xlsx->rows($sheet_idx);
    if (empty($rows)) {
        $summary['skipped_sheets'][] = $sheet_name_clean . ' (empty)';
        continue;
    }

    // ── Find the header row ───────────────────────────────────────────────────
    $header_row_idx = -1;
    $header_map     = []; // column name (lowercase) => column index
    foreach ($rows as $ri => $row) {
        foreach ($row as $ci => $cell) {
            $val = strtolower(trim((string)$cell));
            if ($val === 'description' || $val === 'name') {
                // Confirm this is the real header row by checking for STATUS nearby
                $row_lower = array_map(fn($c) => strtolower(trim((string)$c)), $row);
                if (in_array('status', $row_lower, true)) {
                    $header_row_idx = $ri;
                    foreach ($row as $ci2 => $hcell) {
                        $header_map[strtolower(trim((string)$hcell))] = $ci2;
                    }
                    break 2;
                }
            }
        }
    }

    if ($header_row_idx < 0) {
        $summary['skipped_sheets'][] = $sheet_name_clean . ' (no recognisable header)';
        continue;
    }

    // ── Detect campus from metadata rows above the header ─────────────────────
    $meta_rows = array_slice($rows, 0, $header_row_idx);
    $campus_id = gsheet_detect_campus($meta_rows);

    // ── Determine sheet type ──────────────────────────────────────────────────
    $has_qty     = isset($header_map['quantity']);
    $has_ctrl    = isset($header_map['control no.']) || isset($header_map['control no']);
    $name_col    = $header_map['name']        ?? ($header_map['description'] ?? null);
    $status_col  = $header_map['status']      ?? null;
    $qty_col     = $header_map['quantity']    ?? null;

    if ($name_col === null || $status_col === null) {
        $summary['skipped_sheets'][] = $sheet_name_clean . ' (missing name or status column)';
        continue;
    }

    // ── Use sheet name as category ────────────────────────────────────────────
    $category = $sheet_name_clean;
    gsheet_ensure_category($conn, $campus_id, $category);

    // ── Parse data rows ───────────────────────────────────────────────────────
    $data_rows = array_slice($rows, $header_row_idx + 1);

    if ($has_qty && !$has_ctrl) {
        // TYPE 1: one row per item type, Quantity column present
        foreach ($data_rows as $row) {
            $item_name = trim((string)($row[$name_col] ?? ''));
            if ($item_name === '' || is_numeric($item_name)) continue; // skip row numbers

            $qty_raw   = trim((string)($row[$qty_col] ?? '1'));
            $qty       = max(0, (int)filter_var($qty_raw, FILTER_SANITIZE_NUMBER_INT));
            $status    = trim((string)($row[$status_col] ?? 'Working'));
            $condition = gsheet_condition($status);

            $key = strtolower($item_name) . '||' . $campus_id;
            $synced_item_keys[$key] = ['name' => $item_name, 'campus_id' => $campus_id];

            // Upsert
            $chk = $conn->prepare("SELECT item_id FROM tbl_inventory WHERE item_name = ? AND campus_id = ? AND is_archived = 0 LIMIT 1");
            $chk->bind_param('si', $item_name, $campus_id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                // Subtract units currently out on loan so an active borrow is not
                // wiped by a sync. "Out" = Approved/On Loan/Overdue with stock_applied=1.
                $out_stmt = $conn->prepare(
                    "SELECT COALESCE(SUM(COALESCE(borrow_qty,1)),0) AS units_out
                       FROM tbl_requests
                      WHERE equipment_name = ?
                        AND status IN ('Approved','On Loan','Overdue')
                        AND stock_applied = 1"
                );
                $out_stmt->bind_param('s', $item_name);
                $out_stmt->execute();
                $out_row = $out_stmt->get_result()->fetch_assoc();
                $out_stmt->close();
                $units_out   = (int)($out_row['units_out'] ?? 0);
                $safe_qty    = max(0, $qty - $units_out);

                $upd = $conn->prepare("UPDATE tbl_inventory SET quantity = ?, `condition` = ?, category = ?, synced_from_sheet = 1, image_path = COALESCE(NULLIF(image_path, ''), 'uploads/default.png') WHERE item_id = ?");
                $upd->bind_param('issi', $safe_qty, $condition, $category, $existing['item_id']);
                $upd->execute();
                $upd->close();
                $summary['updated']++;
            } else {
                $ins = $conn->prepare("INSERT INTO tbl_inventory (campus_id, item_name, category, quantity, `condition`, synced_from_sheet, image_path) VALUES (?, ?, ?, ?, ?, 1, 'uploads/default.png')");
                $ins->bind_param('issis', $campus_id, $item_name, $category, $qty, $condition);
                $ins->execute();
                $ins->close();
                $summary['added']++;
            }
        }
    } else {
        // TYPE 2: one row per unit (Control No. present), aggregate by item name
        $item_counts = []; // item_name => ['ok' => int, 'total' => int]
        foreach ($data_rows as $row) {
            $item_name = trim((string)($row[$name_col] ?? ''));
            if ($item_name === '' || is_numeric($item_name)) continue;
            $status = strtolower(trim((string)($row[$status_col] ?? '')));
            if (!isset($item_counts[$item_name])) {
                $item_counts[$item_name] = ['ok' => 0, 'total' => 0];
            }
            $item_counts[$item_name]['total']++;
            if ($status === 'ok' || $status === 'working') {
                $item_counts[$item_name]['ok']++;
            }
        }

        foreach ($item_counts as $item_name => $counts) {
            $qty       = $counts['ok'];
            $condition = $counts['ok'] === 0 ? 'For Repair' : ($counts['ok'] < $counts['total'] ? 'Fair' : 'Good');

            $key = strtolower($item_name) . '||' . $campus_id;
            $synced_item_keys[$key] = ['name' => $item_name, 'campus_id' => $campus_id];

            $chk = $conn->prepare("SELECT item_id FROM tbl_inventory WHERE item_name = ? AND campus_id = ? AND is_archived = 0 LIMIT 1");
            $chk->bind_param('si', $item_name, $campus_id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                // Subtract units currently out on loan so an active borrow is not
                // wiped by a sync. "Out" = Approved/On Loan/Overdue with stock_applied=1.
                $out_stmt = $conn->prepare(
                    "SELECT COALESCE(SUM(COALESCE(borrow_qty,1)),0) AS units_out
                       FROM tbl_requests
                      WHERE equipment_name = ?
                        AND status IN ('Approved','On Loan','Overdue')
                        AND stock_applied = 1"
                );
                $out_stmt->bind_param('s', $item_name);
                $out_stmt->execute();
                $out_row = $out_stmt->get_result()->fetch_assoc();
                $out_stmt->close();
                $units_out = (int)($out_row['units_out'] ?? 0);
                $safe_qty  = max(0, $qty - $units_out);

                $upd = $conn->prepare("UPDATE tbl_inventory SET quantity = ?, `condition` = ?, category = ?, synced_from_sheet = 1, image_path = COALESCE(NULLIF(image_path, ''), 'uploads/default.png') WHERE item_id = ?");
                $upd->bind_param('issi', $safe_qty, $condition, $category, $existing['item_id']);
                $upd->execute();
                $upd->close();
                $summary['updated']++;
            } else {
                $ins = $conn->prepare("INSERT INTO tbl_inventory (campus_id, item_name, category, quantity, `condition`, synced_from_sheet, image_path) VALUES (?, ?, ?, ?, ?, 1, 'uploads/default.png')");
                $ins->bind_param('issis', $campus_id, $item_name, $category, $qty, $condition);
                $ins->execute();
                $ins->close();
                $summary['added']++;
            }
        }
    }
}

// ── Archive items removed from the sheet ─────────────────────────────────────
// Per client policy: nothing is permanently deleted. Items no longer in the
// sheet are archived (hidden from borrowing but kept in the DB for records).
if (!empty($synced_item_keys)) {
    $all_inv = $conn->query(
        "SELECT item_id, item_name, campus_id FROM tbl_inventory
          WHERE is_archived = 0 AND synced_from_sheet = 1"
    );
    if ($all_inv) {
        while ($inv_row = $all_inv->fetch_assoc()) {
            $key = strtolower(trim($inv_row['item_name'])) . '||' . (int)$inv_row['campus_id'];
            if (!isset($synced_item_keys[$key])) {
                $arch = $conn->prepare(
                    "UPDATE tbl_inventory SET is_archived = 1
                      WHERE item_id = ? AND is_archived = 0"
                );
                if ($arch) {
                    $arch->bind_param('i', $inv_row['item_id']);
                    $arch->execute();
                    if ($arch->affected_rows > 0) {
                        $summary['archived']++;
                    }
                    $arch->close();
                }
            }
        }
    }
}

// ── Update last sync timestamp ─────────────────────────────────────────────────
$now = date('Y-m-d H:i:s');
$ts = $conn->prepare("INSERT INTO tbl_sync_config (config_key, value) VALUES ('gsheet_last_sync', ?) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()");
$ts->bind_param('ss', $now, $now);
$ts->execute();
$ts->close();

ob_end_clean();
echo json_encode(['status' => 'success', 'summary' => $summary]);
