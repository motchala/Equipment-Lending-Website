<?php
/**
 * sse/api/faculty-notifications.php — faculty notifications SSE stream
 *
 * Tracks the four cheapest signals that indicate the faculty member's
 * notification feed has changed:
 *   1. Latest equipment request date (new request or status change)
 *   2. Latest room reservation id (new reservation)
 *   3. Latest admin notice id (admin pushed an overdue notice)
 *   4. Unread notification count (read-state change via the bell)
 *
 * Emits `event: update` with `{"action":"reload"}` on any change;
 * the client calls the existing faculty-notif poll endpoint to fetch
 * the full payload.
 *
 * Each snapshot query is wrapped in its own try/catch so a missing table
 * (e.g. tbl_faculty_admin_notice on an older install) degrades gracefully
 * instead of killing the stream.
 *
 * Do NOT include config/security-headers.php — it sets Content-Type:
 * application/json which would corrupt the SSE stream.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');
set_time_limit(0);
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
require_once __DIR__ . '/../core/faculty-signature.php';

sse_send_headers();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
require_once __DIR__ . '/../../shared/core/pseudo-cron.php';   // scheduled work (see that file): the stream doubles as the clock

// ─── Snapshot ────────────────────────────────────────────────────────────────

/**
 * Build a cheap change signature for the faculty member's notification feed.
 *
 * The feed is computed live from the faculty member's requests, reservations, waitlist, room issues and
 * admin notices (see notifications/core/faculty-data.php), with the read / deleted state in
 * tbl_faculty_notif_state. So the signature is those same sources, via the shared helper:
 * any new row OR status change anywhere in them moves it.
 *
 * (The previous version read tbl_faculty_notif_state.user_id, a column that does not exist - the real one is
 * faculty_id - and watched only MAX(request_date) / MAX(id), which never move on an approve or a decline.)
 */
function sse_faculty_notifications_snapshot(mysqli $conn, string $uid): string {
    $p = ps_faculty_live_parts($conn, $uid, ['requests', 'rooms', 'notifs']);
    return sse_make_sig([$p['requests'] ?? '', $p['rooms'] ?? '', $p['notifs'] ?? '']);
}

// ─── Main loop ───────────────────────────────────────────────────────────────

sse_ensure_connection_table($conn);
sse_register_connection($conn, 'faculty', $uid, 'faculty-notifications');

$start   = time();
$lastHb  = time();
$lastSig = '';

while (!sse_should_close($start) && sse_connection_open()) {
    sse_maybe_heartbeat($lastHb);
    ps_pseudo_cron_tick($conn);   // decides due buffered requests (throttled, shared by every open stream)

    try {
        $sig = sse_faculty_notifications_snapshot($conn, $uid);
        if (sse_data_changed($sig, $lastSig)) {
            sse_ping_connection($conn, 'faculty', $uid, 'faculty-notifications');
            sse_emit('update', json_encode(['action' => 'reload']));
        }
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] faculty-notifications tick: ' . $e->getMessage());
    }

    sleep(2);
}

sse_emit('close', json_encode(['reason' => 'timeout']));
sse_remove_connection($conn, 'faculty', $uid, 'faculty-notifications');
$conn->close();
exit;
