<?php
/**
 * sse/api/admin-equipment.php — Admin equipment SSE stream.
 *
 * Tracks changes in tbl_requests (new requests, status changes, returns)
 * and emits {"action":"reload"} when the signature changes.  The client
 * then calls the existing poll function to fetch the full data payload
 * ("ping then fetch" pattern — no rendering logic lives here).
 *
 * Do NOT include config/security-headers.php here: that file sets
 * Content-Type: application/json and would corrupt the SSE stream.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../../config/session.php';

// Admin auth check — must match the convention used throughout this codebase.
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Capture what we need from the session before releasing the lock.
$adminId = (string)($_SESSION['admin_id'] ?? $_SESSION['admin_email'] ?? 'admin');
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
 * Build a cheap change signature for the admin equipment view.
 *
 * Three fast scalar queries — no joins, no user input.
 * Covers new requests (request_date), returns (returned_at), and
 * waiting-queue depth (countWaiting).
 */
function sse_admin_equipment_snapshot(mysqli $conn): string
{
    $r1 = $conn->query("SELECT MAX(request_date) AS v FROM tbl_requests");
    $maxReqDate = $r1 ? ($r1->fetch_assoc()['v'] ?? '') : '';

    $r2 = $conn->query("SELECT MAX(returned_at) AS v FROM tbl_requests");
    $maxReturnedAt = $r2 ? ($r2->fetch_assoc()['v'] ?? '') : '';

    $r3 = $conn->query("SELECT COUNT(*) AS v FROM tbl_requests WHERE status='Waiting'");
    $countWaiting = $r3 ? ($r3->fetch_assoc()['v'] ?? '0') : '0';

    return sse_make_sig([$maxReqDate, $maxReturnedAt, $countWaiting]);
}

// ─── Setup ───────────────────────────────────────────────────────────────────

sse_ensure_connection_table($conn);
sse_register_connection($conn, 'admin', $adminId, 'admin-equipment');

$start   = time();
$lastHb  = time();
$lastSig = '';   // empty string → first tick always emits (Property 1)

// ─── Tick loop ───────────────────────────────────────────────────────────────

while (!sse_should_close($start) && sse_connection_open()) {
    sse_maybe_heartbeat($lastHb);
    ps_pseudo_cron_tick($conn);   // decides due buffered requests (throttled, shared by every open stream)

    try {
        $sig = sse_admin_equipment_snapshot($conn);
        if (sse_data_changed($sig, $lastSig)) {
            sse_ping_connection($conn, 'admin', $adminId, 'admin-equipment');
            sse_emit('update', json_encode(['action' => 'reload']));
        }
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] admin-equipment tick: ' . $e->getMessage());
    }

    sleep(2);
}

// ─── Clean exit ──────────────────────────────────────────────────────────────

sse_emit('close', json_encode(['reason' => 'timeout']));
sse_remove_connection($conn, 'admin', $adminId, 'admin-equipment');
$conn->close();
exit;
