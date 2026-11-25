<?php

declare(strict_types=1);

/**
 * room-reservation/core/room-buffer.php
 *
 * The 10-minute review buffer for SAME-DAY room reservations made by faculty.
 *
 *   1. A same-day faculty request is saved as Waiting with a "decide by" time (buffer_until):
 *        buffer_until = the earlier of (submitted + 10 minutes) and (the reservation start time)
 *      so a request that starts in less than 10 minutes still gets a (shorter) buffer.
 *   2. During the buffer an admin can approve or decline it instantly (review-room-reservation.php).
 *   3. When the buffer ends and nobody has acted (or every admin is offline), the system decides:
 *        - room is still free, no class assigned to it, no overlap at all  -> auto-APPROVED
 *        - anything overlaps (even a few minutes), a class is assigned, the room
 *          is no longer available                                          -> auto-DECLINED
 *      An overlap with a reservation that holds the room is refused up front, at submit time
 *      (first come, first served); it is checked again here because things can change in 10 minutes.
 *   4. There is no cron job in this project, so the decision is taken lazily: ps_room_expire_unreviewed()
 *      (room-expiry.php) calls ps_room_settle_buffers() on every page/poll that reads reservations.
 *
 * Only runs when the Super Admin's "same-day confirmation" setting is ON (RoomRules::sameDayAuto()).
 * When it is OFF a same-day request waits for an admin like a later-date one (no buffer).
 * Students are not affected: their requests always wait for an admin.
 *
 * Schema: one additive column, tbl_room_reservations.buffer_until (DATETIME NULL), added at runtime.
 */

require_once __DIR__ . '/room-rules.php';

if (!defined('PS_ROOM_BUFFER_MINUTES')) {
    define('PS_ROOM_BUFFER_MINUTES', 10);
}
if (!defined('PS_ROOM_BUFFER_RULE_APPROVED')) {
    // Stored in tbl_room_arbitration_log.rule_applied (varchar 60)
    define('PS_ROOM_BUFFER_RULE_APPROVED', 'rule_buffer_approved');
}
if (!defined('PS_ROOM_BUFFER_RULE_DECLINED')) {
    define('PS_ROOM_BUFFER_RULE_DECLINED', 'rule_buffer_declined');
}

/** Adds tbl_room_reservations.buffer_until when it is missing. Cheap after the first call. */
function ps_room_buffer_ensure_column(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM tbl_room_reservations LIKE 'buffer_until'");
        if ($col && $col->num_rows > 0) {
            return $ready = true;
        }
        $conn->query("ALTER TABLE tbl_room_reservations ADD COLUMN buffer_until DATETIME NULL DEFAULT NULL");
        $col = $conn->query("SHOW COLUMNS FROM tbl_room_reservations LIKE 'buffer_until'");
        return $ready = ($col && $col->num_rows > 0);
    } catch (Throwable $e) {
        error_log('[PUPSync] ps_room_buffer_ensure_column: ' . $e->getMessage());
        return $ready = false;
    }
}

/** True when a faculty reservation for $resDate gets the review buffer (today + same-day confirmation ON). */
function ps_room_is_buffered_same_day(string $resDate): bool
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    return $resDate === $today && RoomRules::sameDayAuto();
}

/** The "decide by" moment (Y-m-d H:i:s, Manila): earlier of now + buffer and the start time. */
function ps_room_buffer_deadline(string $resDate, string $startTime, ?DateTimeInterface $now = null): string
{
    $tz    = new DateTimeZone('Asia/Manila');
    $now   = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now', $tz);
    $limit = $now->modify('+' . PS_ROOM_BUFFER_MINUTES . ' minutes');
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $resDate . ' ' . substr($startTime, 0, 5), $tz);
    if ($start !== false && $start < $limit) {
        $limit = $start;
    }
    return $limit->format('Y-m-d H:i:s');
}

/**
 * Error text when ANY reservation that holds the room (Approved) or is waiting for it (Waiting)
 * overlaps the requested time, even by a few minutes. Back-to-back times (one ends exactly when the
 * next starts) do not overlap. Null when the slot is clear.
 */
function ps_room_overlap_error(mysqli $conn, int $roomId, string $resDate, string $start, string $end, int $excludeId = 0): ?string
{
    $st = $conn->prepare(
        "SELECT status, start_time, end_time
           FROM tbl_room_reservations
          WHERE room_id = ? AND reservation_date = ?
            AND status IN ('Approved', 'Waiting')
            AND start_time < ? AND end_time > ?
            AND id <> ?
          ORDER BY (status = 'Approved') DESC, start_time ASC
          LIMIT 1"
    );
    if (!$st) {
        return null;
    }
    $st->bind_param('isssi', $roomId, $resDate, $end, $start, $excludeId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        return null;
    }
    $span = RoomRules::clock((string)$row['start_time']) . ' - ' . RoomRules::clock((string)$row['end_time']);
    return ((string)$row['status'] === 'Approved')
        ? 'This facility is already reserved from ' . $span . ', which overlaps your time. Please choose another time.'
        : 'Another request for this facility (' . $span . ') is still being reviewed and overlaps your time. Please choose another time.';
}

/** Stamps buffer_until on a reservation that is (still) Waiting. */
function ps_room_buffer_mark(mysqli $conn, int $reservationId, string $deadline): void
{
    if (!ps_room_buffer_ensure_column($conn)) {
        return;
    }
    $up = $conn->prepare("UPDATE tbl_room_reservations SET buffer_until = ? WHERE id = ? AND status = 'Waiting'");
    if ($up) {
        $up->bind_param('si', $deadline, $reservationId);
        $up->execute();
        $up->close();
    }
}

/** What the faculty screens need to show the countdown: null when the row has no active buffer. */
function ps_room_buffer_payload(string $deadline): array
{
    $tz  = new DateTimeZone('Asia/Manila');
    $end = new DateTimeImmutable($deadline, $tz);
    $now = new DateTimeImmutable('now', $tz);
    return [
        'buffered'       => true,
        'buffer_until'   => $end->format('c'),
        'buffer_seconds' => max(0, $end->getTimestamp() - $now->getTimestamp()),
        'buffer_minutes' => PS_ROOM_BUFFER_MINUTES,
    ];
}

/**
 * Decide every buffered reservation whose buffer has ended.
 *
 * @return array{approved:int, declined:int}
 */
function ps_room_settle_buffers(mysqli $conn, ?DateTimeInterface $now = null): array
{
    $out = ['approved' => 0, 'declined' => 0];
    $lock_held = false;
    try {
        if (!ps_room_buffer_ensure_column($conn)) {
            return $out;
        }
        $tz    = new DateTimeZone('Asia/Manila');
        $now   = $now ?: new DateTimeImmutable('now', $tz);
        $now_s = $now->format('Y-m-d H:i:s');

        $sel = $conn->prepare(
            "SELECT rr.id, rr.room_id, rr.faculty_id, rr.faculty_name, rr.reservation_date,
                    rr.start_time, rr.end_time, rr.request_date, rr.submitted_as, rr.document_path,
                    r.room_name, r.status AS room_status, r.is_archived
               FROM tbl_room_reservations rr
               JOIN tbl_rooms r ON r.room_id = rr.room_id
              WHERE rr.status = 'Waiting' AND rr.buffer_until IS NOT NULL AND rr.buffer_until <= ?
              ORDER BY rr.request_date ASC, rr.id ASC"
        );
        if (!$sel) {
            return $out;
        }
        $sel->bind_param('s', $now_s);
        $sel->execute();
        $due = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
        $sel->close();
        if (!$due) {
            return $out;
        }
        // Requests filed within a few seconds of each other are decided in priority order (Settings -> Arbitration)
        require_once __DIR__ . '/../../equipment-booking/core/arbitration-engine.php';
        $due = ArbitrationEngine::tieSort($conn, $due);

        // One settler at a time, so two page loads cannot both approve overlapping requests.
        $lk = $conn->prepare("SELECT GET_LOCK('pup_room_buffer_settle', 5)");
        if ($lk) {
            $lk->execute();
            $lock_held = true;
            $lk->close();
        }

        require_once __DIR__ . '/class-schedule.php';

        foreach ($due as $row) {
            $id      = (int)$row['id'];
            $room_id = (int)$row['room_id'];
            $date    = (string)$row['reservation_date'];
            $start   = (string)$row['start_time'];
            $end     = (string)$row['end_time'];

            // A higher-priority request for an overlapping time, filed within the tie-break window, is about to
            // come due (seconds): leave this one for the next pass so that one is decided first.
            $tw   = ArbitrationEngine::tieWindow($conn);
            $capS = $now->modify('+' . ($tw + 2) . ' seconds')->format('Y-m-d H:i:s');
            $riv  = $conn->prepare(
                "SELECT rr.id, rr.faculty_id, rr.request_date, rr.submitted_as, rr.document_path
                   FROM tbl_room_reservations rr
                  WHERE rr.room_id = ? AND rr.reservation_date = ? AND rr.status = 'Waiting'
                    AND rr.buffer_until IS NOT NULL AND rr.buffer_until > ? AND rr.buffer_until <= ?
                    AND rr.start_time < ? AND rr.end_time > ? AND rr.id <> ?"
            );
            if ($riv) {
                $riv->bind_param('isssssi', $room_id, $date, $now_s, $capS, $end, $start, $id);
                $riv->execute();
                $rivals = $riv->get_result()->fetch_all(MYSQLI_ASSOC);
                $riv->close();
                if ($rivals && ArbitrationEngine::outrankedBy($conn, $row, $rivals)) {
                    continue;
                }
            }

            $decision = 'Approved';
            $reason   = 'Automatically approved when the review window closed: no conflicts with other reservations or classes.';

            $ends_at = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . substr($end, 0, 5), $tz);

            if ($ends_at !== false && $now >= $ends_at) {
                $decision = 'Declined';
                $reason   = 'Not confirmed in time: the reservation time had already passed.';
            } elseif ((int)$row['is_archived'] === 1 || (string)$row['room_status'] !== 'Available') {
                $decision = 'Declined';
                $reason   = 'This facility is no longer available for reservation.';
            } else {
                $classes = [];
                try {
                    $classes = cs_room_conflicts($conn, $room_id, $date, $start, $end);
                } catch (Throwable $e) {
                    error_log('[PUPSync] ps_room_settle_buffers class check: ' . $e->getMessage());
                }
                if ($classes) {
                    $c        = $classes[0];
                    $decision = 'Declined';
                    $reason   = 'This facility is assigned to a class at that time (' . cs_class_label($c) . ', '
                              . $c['start_hm'] . '-' . $c['end_hm'] . ').';
                } else {
                    $clash = $conn->prepare(
                        "SELECT 1 FROM tbl_room_reservations
                          WHERE room_id = ? AND reservation_date = ? AND status = 'Approved'
                            AND start_time < ? AND end_time > ? AND id <> ?
                          LIMIT 1"
                    );
                    if ($clash) {
                        $clash->bind_param('isssi', $room_id, $date, $end, $start, $id);
                        $clash->execute();
                        $taken = $clash->get_result()->num_rows > 0;
                        $clash->close();
                        if ($taken) {
                            $decision = 'Declined';
                            $reason   = 'Another reservation took an overlapping time while this request was waiting.';
                        }
                    }
                }
            }

            $upd = $conn->prepare("UPDATE tbl_room_reservations SET status = ?, reason = ? WHERE id = ? AND status = 'Waiting'");
            if (!$upd) {
                continue;
            }
            $upd->bind_param('ssi', $decision, $reason, $id);
            $upd->execute();
            $changed = $upd->affected_rows === 1;
            $upd->close();
            if (!$changed) {
                continue;   // an admin decided it a moment ago: leave their decision alone
            }
            $decision === 'Approved' ? $out['approved']++ : $out['declined']++;

            $log = $conn->prepare(
                "INSERT INTO tbl_room_arbitration_log
                    (reservation_id, room_id, room_name, borrower_id, borrower_name, decision, rule_applied, reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if ($log) {
                $room_name = (string)$row['room_name'];
                $fid       = (string)$row['faculty_id'];
                $fname     = (string)$row['faculty_name'];
                $rule      = $decision === 'Approved' ? PS_ROOM_BUFFER_RULE_APPROVED : PS_ROOM_BUFFER_RULE_DECLINED;
                $log->bind_param('iissssss', $id, $room_id, $room_name, $fid, $fname, $decision, $rule, $reason);
                $log->execute();
                $log->close();
            }
        }

        if ($out['approved'] > 0) {
            // The faculty is probably not on the page: send the approval receipt now.
            try {
               
            } catch (Throwable $e) {
                error_log('[PUPSync] ps_room_settle_buffers receipts: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('[PUPSync] ps_room_settle_buffers: ' . $e->getMessage());   // housekeeping must never take a page down
    } finally {
        if ($lock_held) {
            try {
                $conn->query("SELECT RELEASE_LOCK('pup_room_buffer_settle')");
            } catch (Throwable $e) {
            }
        }
    }
    return $out;
}
