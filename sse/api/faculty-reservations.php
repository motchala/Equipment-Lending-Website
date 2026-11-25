<?php
/**
 * sse/api/faculty-reservations.php — faculty room-reservations SSE stream
 *
 * Emits `event: update` with `{"action":"reload"}` whenever the faculty
 * member's room-reservation data changes (new row or status change on an
 * existing row). The client calls the existing poll function to fetch the
 * full payload; this endpoint only carries the "ping" signal.
 *
 * Do NOT include config/security-headers.php — it sets Content-Type:
 * application/json which would corrupt the SSE stream.
 */

ini_set('display_errors', '0');
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../../config/session.php';

if (empty($_SESSION['faculty_id'])) {
    http_response_code(401);
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
 * Build a cheap change signature for a faculty member's room reservations.
 *
 * Fingerprints every reservation (status, date, times, cancelled_at, reason) and the waitlist entries,
 * so new rows, approvals, declines, cancellations and expiries are all noticed - not only the last 10.
 */
function sse_faculty_reservations_snapshot(mysqli $conn, string $uid): string
{
    $p = ps_faculty_live_parts($conn, $uid, ['rooms']);
    return sse_make_sig([$p['rooms'] ?? '']);
}

// ─── Main loop ───────────────────────────────────────────────────────────────

sse_ensure_connection_table($conn);
sse_register_connection($conn, 'faculty', $uid, 'faculty-reservations');

$start   = time();
$lastHb  = time();
$lastSig = '';

while (!sse_should_close($start) && sse_connection_open()) {
    sse_maybe_heartbeat($lastHb);
    ps_pseudo_cron_tick($conn);   // decides due buffered requests (throttled, shared by every open stream)

    try {
        $sig = sse_faculty_reservations_snapshot($conn, $uid);
        if (sse_data_changed($sig, $lastSig)) {
            sse_ping_connection($conn, 'faculty', $uid, 'faculty-reservations');
            sse_emit('update', json_encode(['action' => 'reload']));
        }
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] faculty-reservations tick: ' . $e->getMessage());
    }

    sleep(2);
}

sse_emit('close', json_encode(['reason' => 'timeout']));
sse_remove_connection($conn, 'faculty', $uid, 'faculty-reservations');
$conn->close();
exit;
