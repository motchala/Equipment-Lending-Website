<?php
/**
 * sse/api/faculty-equipment.php — Faculty equipment SSE stream
 *
 * Tracks changes to the signed-in faculty member's borrow requests and the
 * shared inventory. Emits event: update with {"action":"reload"} when the
 * cheap change signature shifts; the client then calls the existing poll
 * endpoint to fetch the full data payload.
 *
 * Auth: faculty session ($_SESSION['faculty_id'] must be non-empty).
 * Does NOT include config/security-headers.php — that file sets
 * Content-Type: application/json and would corrupt the SSE stream.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');
set_time_limit(60);
ignore_user_abort(true);

require_once __DIR__ . '/../../config/session.php';

if (empty($_SESSION['faculty_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$uid = (string)$_SESSION['faculty_id'];
session_write_close();

require_once __DIR__ . '/../core/stream.php';
require_once __DIR__ . '/../core/change-detection.php';
require_once __DIR__ . '/../core/connection-table.php';

sse_send_headers();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
require_once __DIR__ . '/../../shared/core/pseudo-cron.php';   // scheduled work (see that file): the stream doubles as the clock

// ─── Snapshot ────────────────────────────────────────────────────────────────

/**
 * Build a cheap change signature for the signed-in faculty member.
 *
 * Two scalar queries:
 *   1. MAX(request_date) for this faculty's requests — detects new borrows,
 *      status changes recorded via request_date updates, or returns.
 *   2. SUM(quantity) across all non-archived inventory — detects restocks,
 *      edits, or archive/restore actions that would affect availability.
 */
function sse_faculty_equipment_snapshot(mysqli $conn, string $uid): string {
    $stmt = $conn->prepare("SELECT MAX(request_date) AS v FROM tbl_requests WHERE faculty_id = ?");
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $maxReqDate = $stmt->get_result()->fetch_assoc()['v'] ?? '';
    $stmt->close();

    $r2 = $conn->query("SELECT SUM(quantity) AS v FROM tbl_inventory WHERE is_archived = 0");
    $invSum = $r2 ? ($r2->fetch_assoc()['v'] ?? '0') : '0';

    return sse_make_sig([$maxReqDate, $invSum]);
}

// ─── Main loop ───────────────────────────────────────────────────────────────

sse_ensure_connection_table($conn);
sse_register_connection($conn, 'faculty', $uid, 'faculty-equipment');

$start   = time();
$lastHb  = time();
$lastSig = '';

while (!sse_should_close($start) && sse_connection_open()) {
    sse_maybe_heartbeat($lastHb);
    ps_pseudo_cron_tick($conn);   // decides due buffered requests (throttled, shared by every open stream)

    try {
        $sig = sse_faculty_equipment_snapshot($conn, $uid);
        if (sse_data_changed($sig, $lastSig)) {
            sse_ping_connection($conn, 'faculty', $uid, 'faculty-equipment');
            sse_emit('update', json_encode(['action' => 'reload']));
        }
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] faculty-equipment tick: ' . $e->getMessage());
    }

    sleep(2);
}

sse_emit('close', json_encode(['reason' => 'timeout']));
sse_remove_connection($conn, 'faculty', $uid, 'faculty-equipment');
$conn->close();
exit;
