<?php
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Super Admin only.']);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));

// ── Save Google Sheets URL ────────────────────────────────────────────────────
if ($action === 'save_url') {
    $url = trim((string)($_POST['url'] ?? ''));
    if ($url !== '' && !preg_match('#^https://docs\.google\.com/spreadsheets/d/[a-zA-Z0-9_-]+#', $url)) {
        echo json_encode(['status' => 'error', 'error' => 'That does not look like a valid Google Sheets URL.']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO tbl_sync_config (config_key, value) VALUES ('gsheet_url', ?) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()");
    $stmt->bind_param('ss', $url, $url);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}

// ── Archive a pending deletion item ──────────────────────────────────────────
if ($action === 'pd_archive') {
    $pd_id = (int)($_POST['pd_id'] ?? 0);
    if ($pd_id <= 0) { echo json_encode(['status' => 'error', 'error' => 'Invalid.']); exit; }
    // Get item_id from pending deletions
    $chk = $conn->prepare("SELECT item_id FROM tbl_sync_pending_deletions WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $pd_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($row) {
        $arch = $conn->prepare("UPDATE tbl_inventory SET is_archived = 1 WHERE item_id = ?");
        $arch->bind_param('i', $row['item_id']);
        $arch->execute();
        $arch->close();
    }
    $del = $conn->prepare("DELETE FROM tbl_sync_pending_deletions WHERE id = ?");
    $del->bind_param('i', $pd_id);
    $del->execute();
    $del->close();
    echo json_encode(['status' => 'success']);
    exit;
}

// ── Keep a pending deletion item (dismiss the notification) ──────────────────
if ($action === 'pd_keep') {
    $pd_id = (int)($_POST['pd_id'] ?? 0);
    if ($pd_id <= 0) { echo json_encode(['status' => 'error', 'error' => 'Invalid.']); exit; }
    $del = $conn->prepare("DELETE FROM tbl_sync_pending_deletions WHERE id = ?");
    $del->bind_param('i', $pd_id);
    $del->execute();
    $del->close();
    echo json_encode(['status' => 'success']);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'error' => 'Unknown action.']);
