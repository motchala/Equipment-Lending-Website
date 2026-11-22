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

sse_send_headers();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
require_once __DIR__ . '/../../shared/core/pseudo-cron.php';   // scheduled work (see that file): the stream doubles as the clock

// ─── Snapshot ────────────────────────────────────────────────────────────────

/**
 * Build a cheap change signature for the faculty member's notification feed.
 *
 * Four scalar probes, each in its own try/catch:
 *  1. MAX(request_date) on tbl_requests for this faculty — detects new borrows
 *     and any status change that gets recorded as a request_date update.
 *  2. MAX(id) on tbl_room_reservations — detects new reservation rows or (via
 *     the fact that IDs are monotonic) any reservation activity.
 *  3. MAX(id) on tbl_faculty_admin_notice — detects overdue notices pushed by
 *     admin. The table may not exist on older installs; if missing, falls back
 *     to an empty string rather than crashing the stream.
 *  4. COUNT(*) of unread rows in tbl_faculty_notif_state — detects when the
 *     faculty reads or dismisses a notification from another device/tab.
 */
function sse_faculty_notifications_snapshot(mysqli $conn, string $uid): string {
    $parts = [];

    // 1. Latest equipment request date for this faculty
    try {
        $s = $conn->prepare("SELECT MAX(request_date) AS v FROM tbl_requests WHERE faculty_id = ?");
        $s->bind_param('s', $uid);
        $s->execute();
        $parts[] = $s->get_result()->fetch_assoc()['v'] ?? '';
        $s->close();
    } catch (Throwable $e) { $parts[] = ''; }

    // 2. Latest room reservation id for this faculty
    try {
        $s = $conn->prepare("SELECT MAX(id) AS v FROM tbl_room_reservations WHERE faculty_id = ?");
        $s->bind_param('s', $uid);
        $s->execute();
        $parts[] = $s->get_result()->fetch_assoc()['v'] ?? '';
        $s->close();
    } catch (Throwable $e) { $parts[] = ''; }

    // 3. Latest admin notice (table may not exist on older installs)
    try {
        $check = $conn->query("SHOW TABLES LIKE 'tbl_faculty_admin_notice'");
        if ($check && $check->num_rows > 0) {
            $s = $conn->prepare("SELECT MAX(id) AS v FROM tbl_faculty_admin_notice WHERE faculty_id = ?");
            $s->bind_param('s', $uid);
            $s->execute();
            $parts[] = $s->get_result()->fetch_assoc()['v'] ?? '';
            $s->close();
        } else {
            $parts[] = '';
        }
    } catch (Throwable $e) { $parts[] = ''; }

    // 4. Unread notification count
    try {
        $check2 = $conn->query("SHOW TABLES LIKE 'tbl_faculty_notif_state'");
        if ($check2 && $check2->num_rows > 0) {
            $s = $conn->prepare("SELECT COUNT(*) AS v FROM tbl_faculty_notif_state WHERE user_id = ? AND is_read = 0");
            $s->bind_param('s', $uid);
            $s->execute();
            $parts[] = (string)($s->get_result()->fetch_assoc()['v'] ?? '0');
            $s->close();
        } else {
            $parts[] = '0';
        }
    } catch (Throwable $e) { $parts[] = '0'; }

    return sse_make_sig($parts);
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
