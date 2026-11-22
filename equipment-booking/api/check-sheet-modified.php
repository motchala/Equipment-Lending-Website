<?php
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Must be a logged-in admin
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['changed' => false, 'error' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';
load_env();

$conn = getDB();
$conn->set_charset('utf8mb4');

// ── Read config ──────────────────────────────────────────────────────────────
$cfg_stmt = $conn->prepare(
    "SELECT config_key, value FROM tbl_sync_config
      WHERE config_key IN ('gsheet_url','gsheet_last_modified','gsheet_sync_enabled')"
);
$cfg_stmt->execute();
$cfg_rows = $cfg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cfg_stmt->close();
$cfg = [];
foreach ($cfg_rows as $r) $cfg[$r['config_key']] = $r['value'];

$gsheet_url     = trim((string)($cfg['gsheet_url'] ?? ''));
$sync_enabled   = (string)($cfg['gsheet_sync_enabled'] ?? '0');

if ($gsheet_url === '' || $sync_enabled !== '1') {
    echo json_encode(['changed' => false]);
    exit;
}

// ── Extract sheet ID ─────────────────────────────────────────────────────────
if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $gsheet_url, $m)) {
    echo json_encode(['changed' => false, 'error' => 'Invalid Google Sheets URL.']);
    exit;
}
$sheet_id = $m[1];

// ── Get modifiedTime — OAuth path first, API key fallback, interval fallback ──
require_once __DIR__ . '/../../google-sheets/core/oauth.php';
require_once __DIR__ . '/../../google-sheets/core/reader.php';

$token = gs_token($conn);
$current_modified = null;

if ($token !== null) {
    // OAuth path — works on private sheets
    $current_modified = gs_sheet_modified_time($sheet_id, $token);
} else {
    // API key path — public sheets only
    $api_key = trim((string)($_ENV['GOOGLE_SHEETS_API_KEY'] ?? ''));
    if ($api_key !== '') {
        $drive_url = "https://www.googleapis.com/drive/v3/files/{$sheet_id}?fields=modifiedTime&key=" . urlencode($api_key);
        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'follow_location' => 1, 'header' => "User-Agent: PUPSync/1.0\r\n"],
            'ssl'  => ['verify_peer' => false],
        ]);
        $response = @file_get_contents($drive_url, false, $ctx);
        if ($response !== false) {
            $data = json_decode($response, true);
            $current_modified = $data['modifiedTime'] ?? null;
        }
    }
}

if ($current_modified === null) {
    // No token and no API key — fall back to time-based interval
    $last_sync_row = $conn->prepare(
        "SELECT value FROM tbl_sync_config WHERE config_key = 'gsheet_last_sync'"
    );
    $last_sync_row->execute();
    $last_sync_val = $last_sync_row->get_result()->fetch_assoc();
    $last_sync_row->close();
    $last_sync_ts  = $last_sync_val ? strtotime((string)$last_sync_val['value']) : 0;
    $seconds_since = time() - (int)$last_sync_ts;
    if ($seconds_since < 15) {
        echo json_encode(['changed' => false, 'reason' => 'too_soon', 'next_in' => 15 - $seconds_since]);
    } else {
        echo json_encode(['changed' => true, 'reason' => 'interval_elapsed']);
    }
    exit;
}
$stored_modified   = trim((string)($cfg['gsheet_last_modified'] ?? ''));

// ── Compare ──────────────────────────────────────────────────────────────────
if ($current_modified === $stored_modified) {
    echo json_encode(['changed' => false]);
    exit;
}

// ── Changed — store the new timestamp so the next check is accurate ───────────
$upd = $conn->prepare(
    "INSERT INTO tbl_sync_config (config_key, value) VALUES ('gsheet_last_modified', ?)
     ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()"
);
$upd->bind_param('ss', $current_modified, $current_modified);
$upd->execute();
$upd->close();

echo json_encode(['changed' => true, 'modifiedTime' => $current_modified]);
