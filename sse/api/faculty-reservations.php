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
set_time_limit(60);
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

sse_send_headers();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ─── Snapshot ────────────────────────────────────────────────────────────────

/**
 * Build a cheap change signature for a faculty member's room reservations.
 *
 * Two queries are combined into a single signature:
 *  - MAX(id): detects new reservation rows.
 *  - GROUP_CONCAT of the 10 most-recent (id:status) pairs: detects status
 *    changes on existing rows (e.g. admin approving a Waiting reservation).
 */
function sse_faculty_reservations_snapshot(mysqli $conn, string $uid): string
{
    $stmt1 = $conn->prepare(
        'SELECT MAX(id) AS v FROM tbl_room_reservations WHERE faculty_id = ?'
    );
    $stmt1->bind_param('s', $uid);
    $stmt1->execute();
    $maxId = $stmt1->get_result()->fetch_assoc()['v'] ?? '0';
    $stmt1->close();

    // GROUP_CONCAT catches status changes on existing rows
    // (e.g. admin approves a waiting reservation)
    $stmt2 = $conn->prepare(
        "SELECT GROUP_CONCAT(id, ':', status ORDER BY id SEPARATOR ',') AS v
         FROM (SELECT id, status FROM tbl_room_reservations
               WHERE faculty_id = ? ORDER BY id DESC LIMIT 10) sub"
    );
    $stmt2->bind_param('s', $uid);
    $stmt2->execute();
    $statusList = $stmt2->get_result()->fetch_assoc()['v'] ?? '';
    $stmt2->close();

    return sse_make_sig([$maxId, $statusList]);
}

// ─── Main loop ───────────────────────────────────────────────────────────────

sse_ensure_connection_table($conn);
sse_register_connection($conn, 'faculty', $uid, 'faculty-reservations');

$start   = time();
$lastHb  = time();
$lastSig = '';

while (!sse_should_close($start) && sse_connection_open()) {
    sse_maybe_heartbeat($lastHb);

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
