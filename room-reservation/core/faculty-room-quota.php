<?php
/**
 * room-reservation/core/faculty-room-quota.php
 *
 * Shared rules for faculty room reservations. Used by:
 *   - api/submit-faculty-reserve.php  (enforces the rules)
 *   - api/get-room-quota.php          (tells the UI where the faculty stands)
 *
 * RULES
 *   1. Equipment never blocks a room: a faculty member may reserve a room even
 *      while equipment is out or overdue (the arbitration engine no longer
 *      applies the overdue block to rooms).
 *   2. One faculty account may hold at most FCTY_MAX_ACTIVE_ROOM_RESERVATIONS
 *      active room reservations at the same time (2 is fine, 3 is not).
 *        "Active" = status Approved AND not finished yet (the end time is still
 *        ahead). Declined / Cancelled / already-finished ones do not count, so
 *        a slot is freed by cancelling or simply by waiting for it to end.
 *        Reservations made by a faculty member's students through their room
 *        code (submitted_as = student) are not the faculty member's own
 *        bookings and do not count.
 *   3. Only Organization Advisers may attach a supporting document (JPG, PNG or
 *      PDF, max 5 MB). It is optional. Attaching one files the reservation as
 *      submitted_as = adviser and stores the path in document_path, which the
 *      arbitration engine already reads for priority.
 */

if (!defined('FCTY_MAX_ACTIVE_ROOM_RESERVATIONS')) {
    define('FCTY_MAX_ACTIVE_ROOM_RESERVATIONS', 2);
}
if (!defined('FCTY_ROOM_DOC_MAX_BYTES')) {
    define('FCTY_ROOM_DOC_MAX_BYTES', 5 * 1024 * 1024);
}

/**
 * The faculty member's own active room reservations, soonest first.
 *
 * @return array<int, array{id:int, room_name:string, date:string, start:string, end:string}>
 */
function fcty_active_room_reservations(mysqli $conn, string $faculty_id): array
{
    $now   = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $today = $now->format('Y-m-d');
    $time  = $now->format('H:i:s');

    $stmt = $conn->prepare(
        "SELECT rr.id, r.room_name, rr.reservation_date, rr.start_time, rr.end_time
           FROM tbl_room_reservations rr
           JOIN tbl_rooms r ON r.room_id = rr.room_id
          WHERE rr.faculty_id   = ?
            AND rr.status       = 'Approved'
            AND rr.submitted_as <> 'student'
            AND (rr.reservation_date > ?
                 OR (rr.reservation_date = ? AND rr.end_time > ?))
          ORDER BY rr.reservation_date ASC, rr.start_time ASC"
    );
    if ($stmt === false) {
        error_log('[PUPSync] fcty_active_room_reservations prepare failed: ' . $conn->error);
        return [];
    }
    $stmt->bind_param('ssss', $faculty_id, $today, $today, $time);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id'        => (int)$row['id'],
            'room_name' => (string)$row['room_name'],
            'date'      => (string)$row['reservation_date'],
            'start'     => substr((string)$row['start_time'], 0, 5),
            'end'       => substr((string)$row['end_time'], 0, 5),
        ];
    }
    $stmt->close();
    return $rows;
}

/** True when the account is an Organization Adviser (read fresh from the DB). */
function fcty_is_org_adviser(mysqli $conn, string $faculty_id): bool
{
    $stmt = $conn->prepare('SELECT role FROM tbl_users WHERE faculty_id = ? LIMIT 1');
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param('s', $faculty_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row['role'] ?? '') === 'Organization Adviser';
}

/** Per-faculty advisory lock so two simultaneous submissions cannot both slip past the limit. */
function fcty_quota_lock(mysqli $conn, string $faculty_id): void
{
    $key  = 'pup_room_quota_' . md5($faculty_id);
    $stmt = $conn->prepare('SELECT GET_LOCK(?, 5)');
    if ($stmt) {
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}

function fcty_quota_unlock(mysqli $conn, string $faculty_id): void
{
    $key  = 'pup_room_quota_' . md5($faculty_id);
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if ($stmt) {
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}

/** Human-friendly "limit reached" message. */
function fcty_quota_message(): string
{
    return 'You already have ' . FCTY_MAX_ACTIVE_ROOM_RESERVATIONS
        . ' active room reservations. Cancel one or wait until it ends to reserve another room.';
}
