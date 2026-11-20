<?php

/**
 * room-reservation/api/buffer-status.php
 *
 * Called by the countdown (assets/js/room-buffer-countdown.js) when a same-day request's
 * 10-minute review buffer has run out. It lets the server settle every buffered request that is due
 * (core/room-buffer.php, the same housekeeping every page load runs) and returns the CURRENT status of
 * the reservations asked about, so the screen can update at once instead of waiting for the next refresh.
 *
 *   GET ?ids=12,15,18        (at most 25 ids)
 *   -> { "rows": [ { "id": 12, "status": "Approved", "reason": "..." }, ... ] }
 *
 * Admin or Super Admin: any reservation. Faculty: only their own. Read-only for the caller; the only
 * write is the automatic decision the system would take anyway, so no CSRF token is involved.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

$is_admin   = ($_SESSION['admin'] ?? false) === true;
$faculty_id = isset($_SESSION['faculty_id']) && is_scalar($_SESSION['faculty_id']) ? (string)$_SESSION['faculty_id'] : '';
if (!$is_admin && $faculty_id === '') {
    http_response_code(401);
    echo json_encode(['rows' => []]);
    exit();
}

$ids = [];
foreach (explode(',', (string)($_GET['ids'] ?? '')) as $part) {
    $n = (int)trim($part);
    if ($n > 0) {
        $ids[$n] = $n;
    }
    if (count($ids) >= 25) {
        break;
    }
}
$ids = array_values($ids);
if (!$ids) {
    echo json_encode(['rows' => []]);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/room-expiry.php';

$conn = getDB();
ps_room_expire_unreviewed($conn);   // settles every buffered request that is due

$marks = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$sql   = "SELECT id, status, reason FROM tbl_room_reservations WHERE id IN ($marks)";
$args  = $ids;
if (!$is_admin) {
    $sql   .= ' AND faculty_id = ?';
    $types .= 's';
    $args[] = $faculty_id;
}
$st = $conn->prepare($sql);
if (!$st) {
    http_response_code(500);
    echo json_encode(['rows' => []]);
    exit();
}
$st->bind_param($types, ...$args);
$st->execute();
$rows = [];
$res  = $st->get_result();
while ($res && ($r = $res->fetch_assoc())) {
    $rows[] = ['id' => (int)$r['id'], 'status' => (string)$r['status'], 'reason' => (string)($r['reason'] ?? '')];
}
$st->close();

echo json_encode(['rows' => $rows]);
