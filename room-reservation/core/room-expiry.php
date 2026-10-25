<?php

/**
 * room-reservation/core/room-expiry.php
 *
 * "Decide before the day" rule for room reservations that are waiting for an admin.
 *
 * A reservation can be Waiting because it is for a LATER DATE (every future-dated
 * reservation now waits for an admin's decision instead of being auto-approved) or
 * because an Organization Adviser attached a supporting letter (Super Admin review).
 *
 * If nobody has decided by the deadline, it is declined automatically:
 *
 *   deadline = 12:01 AM (Asia/Manila) on the reservation date
 *              - NOT the start time the requester asked for -
 *
 * so the requester finds out on the morning of the day, with the whole day left to
 * make other arrangements, instead of at the moment they needed the room.
 *
 * One exception keeps the rule sensible: a request that was only SUBMITTED after
 * 12:01 AM of its own reservation day (a same-day letter review) has no "before the
 * day" left, so its deadline is its start time.
 *
 * There is no cron job in this project, so this runs lazily - at the top of every
 * read that depends on it (admin Reservations list, the review endpoint, the faculty
 * My Activity feed, the room limit check). It is cheap (one indexed-ish SELECT), safe
 * to call any number of times and race-safe (each row is only declined if it is still
 * Waiting at that instant).
 */

if (!defined('PS_ROOM_EXPIRY_RULE')) {
    // Stored in tbl_room_arbitration_log.rule_applied so the admin Activity feed and the
    // reservation drawer can say why it was declined.
    define('PS_ROOM_EXPIRY_RULE', 'rule_expired_unreviewed');
}
if (!defined('PS_ROOM_EXPIRY_REASON_DAY')) {
    define('PS_ROOM_EXPIRY_REASON_DAY', 'Not reviewed in time – automatically declined at 12:01 AM on the reservation date.');
}
if (!defined('PS_ROOM_EXPIRY_REASON_START')) {
    define('PS_ROOM_EXPIRY_REASON_START', 'Not reviewed before the reservation start time – automatically declined.');
}

/**
 * Decline every Waiting reservation whose deadline has passed.
 *
 * @param DateTimeInterface|null $now  injectable for tests; defaults to "now" in Asia/Manila
 * @return int how many reservations were declined by this call
 */
function ps_room_expire_unreviewed(mysqli $conn, ?DateTimeInterface $now = null): int
{
    $tz  = new DateTimeZone('Asia/Manila');
    $now = $now ?: new DateTimeImmutable('now', $tz);
    $now_s = $now->format('Y-m-d H:i:s');

    $declined = 0;
    try {
        // request_date is compared with the deadline on the DB side so both values live
        // in the same clock; "now" is the Manila wall clock the rest of the app uses.
        $sel = $conn->prepare(
            "SELECT rr.id, rr.room_id, rr.faculty_id, rr.faculty_name, r.room_name,
                    (COALESCE(rr.request_date, '1970-01-01 00:00:00')
                        >= TIMESTAMP(rr.reservation_date, '00:01:00')) AS submitted_on_the_day
               FROM tbl_room_reservations rr
               JOIN tbl_rooms r ON r.room_id = rr.room_id
              WHERE rr.status = 'Waiting'
                AND (
                      (COALESCE(rr.request_date, '1970-01-01 00:00:00') <  TIMESTAMP(rr.reservation_date, '00:01:00')
                        AND ? >= TIMESTAMP(rr.reservation_date, '00:01:00'))
                   OR (COALESCE(rr.request_date, '1970-01-01 00:00:00') >= TIMESTAMP(rr.reservation_date, '00:01:00')
                        AND ? >= TIMESTAMP(rr.reservation_date, rr.start_time))
                )"
        );
        if (!$sel) {
            return 0;
        }
        $sel->bind_param('ss', $now_s, $now_s);
        $sel->execute();
        $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
        $sel->close();

        foreach ($rows as $row) {
            $reason = ((int)$row['submitted_on_the_day'] === 1) ? PS_ROOM_EXPIRY_REASON_START : PS_ROOM_EXPIRY_REASON_DAY;

            $upd = $conn->prepare("UPDATE tbl_room_reservations SET status = 'Declined', reason = ? WHERE id = ? AND status = 'Waiting'");
            if (!$upd) {
                continue;
            }
            $id = (int)$row['id'];
            $upd->bind_param('si', $reason, $id);
            $upd->execute();
            $changed = $upd->affected_rows === 1;
            $upd->close();
            if (!$changed) {
                continue;   // someone decided it a moment ago - leave their decision alone
            }
            $declined++;

            $log = $conn->prepare(
                "INSERT INTO tbl_room_arbitration_log
                    (reservation_id, room_id, room_name, borrower_id, borrower_name, decision, rule_applied, reason)
                 VALUES (?, ?, ?, ?, ?, 'Declined', ?, ?)"
            );
            if ($log) {
                $room_id = (int)$row['room_id'];
                $room    = (string)$row['room_name'];
                $fid     = (string)$row['faculty_id'];
                $fname   = (string)$row['faculty_name'];
                $rule    = PS_ROOM_EXPIRY_RULE;
                $log->bind_param('iisssss', $id, $room_id, $room, $fid, $fname, $rule, $reason);
                $log->execute();
                $log->close();
            }
        }
    } catch (Throwable $e) {
        // Housekeeping must never take a page down.
        error_log('[PUPSync] ps_room_expire_unreviewed: ' . $e->getMessage());
    }
    return $declined;
}

/**
 * Make sure the Waiting value exists in the status ENUM (older databases were created
 * without it). Idempotent; the same statement submit-faculty-reserve.php has always used.
 */
function ps_room_ensure_waiting_status(mysqli $conn): void
{
    $col = $conn->query("SHOW COLUMNS FROM tbl_room_reservations LIKE 'status'");
    $def = $col ? (string)(($col->fetch_assoc()['Type'] ?? '')) : '';
    if (stripos($def, "'waiting'") === false) {
        $conn->query("ALTER TABLE tbl_room_reservations MODIFY status ENUM('Waiting','Approved','Declined','Cancelled') NOT NULL DEFAULT 'Approved'");
    }
}

/**
 * True when the reservation date is after today in Asia/Manila - i.e. it is a "future"
 * reservation that must wait for an admin's decision. Same-day reservations keep the
 * instant flow.
 */
function ps_room_is_future_date(string $res_date): bool
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    return $res_date > $today;
}
