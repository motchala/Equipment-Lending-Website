<?php

declare(strict_types=1);

/**
 * equipment-booking/core/request-buffer.php
 *
 * The 10-minute approval buffer for SAME-DAY equipment requests (booking_mode = 'today').
 * Same idea as the room buffer (room-reservation/core/room-buffer.php), applied to equipment.
 *
 *   1. A same-day request that passes the hard blockers (ArbitrationEngine::preflight and the
 *      "impossible right now" check) is saved as Waiting with a "decide by" time:
 *          buffer_until = now + 10 minutes (PHP clock)    (tbl_requests.buffer_until)
 *      and arbitration_rule = rule_eq_buffer_hold.
 *   2. During the buffer an admin can approve or decline it at any time (admin-override.php,
 *      approve-borrow-bundle.php). Their decision replaces the hold rule with 'override', and the
 *      settler below only ever touches rows that are still Waiting, so an admin always wins.
 *   3. When the buffer ends and nobody has acted, the system decides:
 *        - APPROVED when every requested item exists, has the units on the shelf, has enough free
 *          units, and has NO overlapping booking (Approved / On Loan / Overdue) for that time;
 *        - otherwise DECLINED, with the reason. The declined request is KEPT, so the faculty member
 *          sees what happened in My Activity (unlike a request refused at the moment of submitting).
 *      A cart (several items sharing a batch_id) is decided as a whole: all approved (one shared
 *      Claim QR) or all declined.
 *   4. There is no cron job in this project, so the decision is taken lazily:
 *      equipment_auto_approve_tick() (auto-approve-tick.php) calls eq_buffer_settle() on every admin /
 *      faculty / student poll. It is cheap, safe to call any number of times and race-safe (one
 *      settler at a time through a DB lock; a row is only decided if it is still Waiting).
 *
 * Approvals go through ArbitrationEngine::process(), so stock handling (units leave the shelf when the
 * booking starts or is already due), QR tokens and approval receipts behave exactly like every other
 * approval; the buffer only relabels the decision (rule_buffer_approved).
 *
 * Schema: one additive column, tbl_requests.buffer_until (DATETIME NULL), added by
 * BookingSchedule::ensureSchema().
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/arbitration-engine.php';
require_once __DIR__ . '/request-decision.php';   // equipment_issue_tokens(); both files only call each other at run time

if (!defined('EQ_BUFFER_MINUTES')) {
    define('EQ_BUFFER_MINUTES', 10);
}
if (!defined('EQ_BUFFER_HOLD_RULE')) {
    // Marks a same-day request that is inside its review buffer (tbl_requests.arbitration_rule, varchar 50).
    define('EQ_BUFFER_HOLD_RULE', 'rule_eq_buffer_hold');
}
if (!defined('EQ_BUFFER_SETTLING_RULE')) {
    // Transient marker while the settler is deciding the row (see eq_buffer_claim()).
    define('EQ_BUFFER_SETTLING_RULE', 'rule_eq_buffer_settling');
}
if (!defined('EQ_BUFFER_RULE_APPROVED')) {
    // Same ids the room buffer logs, so the admin Activity feed already has their labels.
    define('EQ_BUFFER_RULE_APPROVED', 'rule_buffer_approved');
}
if (!defined('EQ_BUFFER_RULE_DECLINED')) {
    define('EQ_BUFFER_RULE_DECLINED', 'rule_buffer_declined');
}
if (!defined('EQ_BUFFER_LOCK')) {
    define('EQ_BUFFER_LOCK', 'pup_eq_buffer_settle');
}
if (!defined('EQ_BUFFER_NOTE')) {
    // Shown to the faculty member on the Waiting request (My Activity "reason" line).
    define('EQ_BUFFER_NOTE', 'Pending – an admin can approve or decline it within ' . EQ_BUFFER_MINUTES
        . ' minutes. If nobody does, it is approved automatically when the equipment is free for that time, otherwise declined.');
}
if (!defined('EQ_BUFFER_APPROVED_REASON')) {
    define('EQ_BUFFER_APPROVED_REASON', 'Automatically approved when the review window closed: the equipment is in stock and has no overlapping booking.');
}

/**
 * The "decide by" moment (Y-m-d H:i:s, Manila): 10 minutes from $from (default: now).
 * It is taken from the PHP clock, the same clock eq_buffer_settle() compares it with, so it does not
 * depend on the database server's time zone.
 */
function eq_buffer_deadline(?DateTimeInterface $from = null): string
{
    $base = $from ? DateTimeImmutable::createFromInterface($from) : new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    return $base->modify('+' . EQ_BUFFER_MINUTES . ' minutes')->format('Y-m-d H:i:s');
}

/**
 * What the screens need to show the countdown.
 *
 * @return array{buffered:bool, buffer_until:string, buffer_seconds:int, buffer_minutes:int}
 */
function eq_buffer_payload(string $deadline): array
{
    $tz  = new DateTimeZone('Asia/Manila');
    $end = new DateTimeImmutable($deadline, $tz);
    $now = new DateTimeImmutable('now', $tz);
    return [
        'buffered'       => true,
        'buffer_until'   => $end->format('c'),
        'buffer_seconds' => max(0, $end->getTimestamp() - $now->getTimestamp()),
        'buffer_minutes' => EQ_BUFFER_MINUTES,
    ];
}

/**
 * Put a freshly submitted same-day request into its buffer (it stays Waiting).
 *
 * @return array{status:string, reason:string, buffered:bool, buffer_until:string, buffer_seconds:int, buffer_minutes:int}
 */
function eq_buffer_enter(mysqli $conn, int $id): array
{
    $out = ['status' => 'Waiting', 'reason' => EQ_BUFFER_NOTE, 'buffered' => false, 'buffer_until' => '', 'buffer_seconds' => 0, 'buffer_minutes' => EQ_BUFFER_MINUTES];
    try {
        if (!BookingSchedule::ensureSchema($conn)) {
            return $out;
        }
        $deadline = eq_buffer_deadline();
        $rule     = EQ_BUFFER_HOLD_RULE;
        $note     = EQ_BUFFER_NOTE;
        $upd = $conn->prepare("UPDATE tbl_requests SET buffer_until = ?, arbitration_rule = ?, reason = ? WHERE id = ? AND status = 'Waiting'");
        if (!$upd) {
            return $out;
        }
        $upd->bind_param('sssi', $deadline, $rule, $note, $id);
        $upd->execute();
        $changed = $upd->affected_rows === 1;
        $upd->close();
        return $changed ? array_merge($out, eq_buffer_payload($deadline)) : $out;
    } catch (\Throwable $e) {
        error_log('eq_buffer_enter[' . $id . '] - ' . $e->getMessage());
        return $out;
    }
}

/**
 * Load the requests (by id) that the check below needs.
 *
 * @param  list<int> $ids
 * @return list<array<string, mixed>>
 */
function eq_buffer_load_rows(mysqli $conn, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
    if (!$ids) {
        return [];
    }
    $in  = implode(',', $ids);   // integers only, cast above
    $res = $conn->query(
        "SELECT id, equipment_name, COALESCE(borrow_qty, 1) AS qty, borrow_date, return_date, borrow_time, return_time,
                COALESCE(batch_id, '') AS batch_id, request_date, buffer_until
           FROM tbl_requests
          WHERE id IN ($in)
          ORDER BY request_date ASC, id ASC"
    );
    $rows = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $rows[] = $r;
    }
    return $rows;
}

/**
 * The automatic check, as a pure read: may this group of requests (one item, or a whole cart) be
 * approved right now? Returns null when it may, otherwise the decline reason (shown to the borrower).
 *
 * Every item must: exist and not be archived, have enough units on the shelf, have enough free units
 * for the time, and have NO overlapping booking at all (Approved / On Loan / Overdue, the same bookings
 * BookingSchedule::occupancy() counts), and the borrow period must not already be over.
 *
 * @param list<array<string, mixed>> $rows
 */
function eq_buffer_check(mysqli $conn, array $rows, ?DateTimeInterface $now = null): ?string
{
    if (!$rows) {
        return 'The request could not be found.';
    }
    $now_ts = $now ? $now->getTimestamp() : time();

    // Units wanted per item across the whole group (an item listed twice must fit both times)
    $need = [];
    foreach ($rows as $r) {
        $name = (string)$r['equipment_name'];
        $need[$name] = ($need[$name] ?? 0) + max(1, (int)$r['qty']);
    }

    foreach ($rows as $r) {
        $name    = (string)$r['equipment_name'];
        $startTs = $r['borrow_date'] . ' ' . (!empty($r['borrow_time']) ? $r['borrow_time'] : '00:00:00');
        $endTs   = $r['return_date'] . ' ' . (!empty($r['return_time']) ? $r['return_time'] : '23:59:59');

        $end = strtotime($endTs);
        if ($end !== false && $end <= $now_ts) {
            return 'Not confirmed in time: the borrow period for "' . $name . '" had already ended.';
        }

        $shelf = BookingSchedule::shelfQuantity($conn, $name);
        if ($shelf === null) {
            return '"' . $name . '" is no longer available for borrowing.';
        }
        if ($shelf < 1) {
            return '"' . $name . '" is out right now – book it for a later date instead.';
        }
        if ($shelf < $need[$name]) {
            return 'Only ' . $shelf . ' unit(s) of "' . $name . '" are available right now (' . $need[$name] . ' requested).';
        }

        $occupied = BookingSchedule::occupancy($conn, $name, $startTs, $endTs, (int)$r['id']);
        if ($occupied > 0) {
            return '"' . $name . '" is already booked for part of that time, so it could not be approved automatically.';
        }
        $free = BookingSchedule::totalStock($conn, $name) - $occupied;
        if ($free < $need[$name]) {
            return 'Only ' . max(0, $free) . ' unit(s) of "' . $name . '" are free for that time (' . $need[$name] . ' requested).';
        }
    }
    return null;
}

/**
 * Would the automatic check approve these requests right now? Used by the admin Approve screens: an
 * admin approving something the system would approve anyway is not asked for a reason / internal note.
 *
 * @param list<int> $ids
 */
function eq_buffer_ids_pass(mysqli $conn, array $ids): bool
{
    try {
        return eq_buffer_check($conn, eq_buffer_load_rows($conn, $ids)) === null;
    } catch (\Throwable $e) {
        error_log('eq_buffer_ids_pass - ' . $e->getMessage());
        return false;
    }
}

/**
 * Take the row for the settler: only succeeds while it is still Waiting in its buffer, so a decision
 * an admin saved a moment ago (which replaces the hold rule with 'override') is never overwritten.
 */
function eq_buffer_claim(mysqli $conn, int $id): bool
{
    $hold    = EQ_BUFFER_HOLD_RULE;
    $settling = EQ_BUFFER_SETTLING_RULE;
    $st = $conn->prepare("UPDATE tbl_requests SET arbitration_rule = ? WHERE id = ? AND status = 'Waiting' AND arbitration_rule = ?");
    if (!$st) {
        return false;
    }
    $st->bind_param('sis', $settling, $id, $hold);
    $st->execute();
    $won = $st->affected_rows === 1;
    $st->close();
    return $won;
}

/** Give a claimed row back (the engine could not decide it); the next poll retries. */
function eq_buffer_release(mysqli $conn, int $id): void
{
    $hold     = EQ_BUFFER_HOLD_RULE;
    $settling = EQ_BUFFER_SETTLING_RULE;
    $st = $conn->prepare("UPDATE tbl_requests SET arbitration_rule = ? WHERE id = ? AND status = 'Waiting' AND arbitration_rule = ?");
    if ($st) {
        $st->bind_param('sis', $hold, $id, $settling);
        $st->execute();
        $st->close();
    }
}

/**
 * Decide every buffered same-day request whose buffer has ended.
 *
 * A cart is decided together, once the LAST of its items has finished its buffer.
 *
 * @param DateTimeInterface|null $now injectable for tests; defaults to "now" in Asia/Manila
 * @return array{approved:int, declined:int}  number of requests (rows) decided by this call
 */
function eq_buffer_settle(mysqli $conn, ?DateTimeInterface $now = null): array
{
    $out       = ['approved' => 0, 'declined' => 0];
    $lock_held = false;
    try {
        if (!BookingSchedule::ensureSchema($conn)) {
            return $out;
        }
        $now   = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $now_s = $now->format('Y-m-d H:i:s');

        // Cheap look first: nothing due -> no lock, no work (this runs on every poll)
        $peek = $conn->prepare("SELECT 1 FROM tbl_requests WHERE status = 'Waiting' AND buffer_until IS NOT NULL AND buffer_until <= ? LIMIT 1");
        if (!$peek) {
            return $out;
        }
        $peek->bind_param('s', $now_s);
        $peek->execute();
        $any = $peek->get_result()->num_rows > 0;
        $peek->close();
        if (!$any) {
            return $out;
        }

        // One settler at a time, so two polls cannot both approve overlapping requests.
        $lk = $conn->query("SELECT GET_LOCK('" . EQ_BUFFER_LOCK . "', 5) AS got");
        $got = $lk ? (int)($lk->fetch_assoc()['got'] ?? 0) : 0;
        if ($got !== 1) {
            return $out;   // another poll is settling right now; it will do the work
        }
        $lock_held = true;

        // Under the lock nobody else is settling, so a row still marked 'settling' was orphaned by a crash:
        // give it back to its buffer.
        $orph = $conn->prepare("UPDATE tbl_requests SET arbitration_rule = ? WHERE status = 'Waiting' AND arbitration_rule = ?");
        if ($orph) {
            $to   = EQ_BUFFER_HOLD_RULE;
            $from = EQ_BUFFER_SETTLING_RULE;
            $orph->bind_param('ss', $to, $from);
            $orph->execute();
            $orph->close();
        }

        // Re-read under the lock: another settler may have decided some of them meanwhile.
        // Every Waiting row still inside a buffer, oldest first, so groups are decided in submission order.
        $sel = $conn->prepare(
            "SELECT r.id, r.equipment_name, COALESCE(r.borrow_qty, 1) AS qty, r.borrow_date, r.return_date, r.borrow_time, r.return_time,
                    COALESCE(r.batch_id, '') AS batch_id, r.request_date, r.buffer_until,
                    r.faculty_id, r.submitted_as, r.document_path, u.role
               FROM tbl_requests r
               LEFT JOIN tbl_users u ON u.faculty_id = r.faculty_id
              WHERE r.status = 'Waiting' AND r.buffer_until IS NOT NULL
                AND r.arbitration_rule = ?
              ORDER BY r.request_date ASC, r.id ASC"
        );
        if (!$sel) {
            return $out;
        }
        $hold = EQ_BUFFER_HOLD_RULE;
        $sel->bind_param('s', $hold);
        $sel->execute();
        $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
        $sel->close();

        // Requests filed within a few seconds of each other are decided in priority order (Settings -> Arbitration),
        // not just first come first served. Rows still inside their own window are the ones a due request may have to wait for.
        $rows    = ArbitrationEngine::tieSort($conn, $rows);
        $tie_win = ArbitrationEngine::tieWindow($conn);
        $cap_s   = $now->modify('+' . ($tie_win + 2) . ' seconds')->format('Y-m-d H:i:s');
        $pending = array_values(array_filter($rows, static fn (array $r): bool => (string)$r['buffer_until'] > $now_s && (string)$r['buffer_until'] <= $cap_s));

        // Group by cart (a single item is its own group)
        $groups = [];
        foreach ($rows as $r) {
            $key = $r['batch_id'] !== '' ? 'b:' . $r['batch_id'] : 's:' . $r['id'];
            $groups[$key][] = $r;
        }

        $approved_ids = [];
        foreach ($groups as $members) {
            // Due only when the whole cart's buffers have ended
            $latest = max(array_map(static fn (array $m): string => (string)$m['buffer_until'], $members));
            if ($latest > $now_s) {
                continue;
            }

            // A higher-priority request for the same item, filed within the tie-break window, is about to
            // come due (seconds): leave this one for the next pass so that one is decided first.
            $outranked = false;
            foreach ($members as $m) {
                $rivals = array_filter($pending, static fn (array $p): bool => $p['equipment_name'] === $m['equipment_name'] && (int)$p['id'] !== (int)$m['id']);
                if ($rivals && ArbitrationEngine::outrankedBy($conn, $m, $rivals)) {
                    $outranked = true;
                    break;
                }
            }
            if ($outranked) {
                continue;
            }

            $why = eq_buffer_check($conn, $members, $now);
            if ($why !== null) {
                foreach ($members as $m) {
                    $id = (int)$m['id'];
                    if (!eq_buffer_claim($conn, $id)) {
                        continue;   // an admin decided it a moment ago
                    }
                    $rule = EQ_BUFFER_RULE_DECLINED;
                    $upd  = $conn->prepare("UPDATE tbl_requests SET status = 'Declined', arbitration_rule = ?, reason = ? WHERE id = ? AND status = 'Waiting'");
                    if (!$upd) {
                        eq_buffer_release($conn, $id);
                        continue;
                    }
                    $upd->bind_param('ssi', $rule, $why, $id);
                    $upd->execute();
                    $won = $upd->affected_rows === 1;
                    $upd->close();
                    if (!$won) {
                        eq_buffer_release($conn, $id);
                        continue;
                    }
                    // Log row for the admin Activity feed. The request is KEPT so the faculty sees it in My Activity.
                    ArbitrationEngine::record($conn, $id, 'Declined', $rule, $why);
                    $out['declined']++;
                }
                continue;
            }

            // Passed: approve every item through the engine (stock + schedule handled exactly as usual)
            foreach ($members as $m) {
                $id = (int)$m['id'];
                if (!eq_buffer_claim($conn, $id)) {
                    continue;   // an admin decided it a moment ago
                }
                ArbitrationEngine::process($conn, $id);
                $after = equipment_decision_row($conn, $id);
                if ($after !== null && $after['status'] === 'Approved') {
                    ArbitrationEngine::record($conn, $id, 'Approved', EQ_BUFFER_RULE_APPROVED, EQ_BUFFER_APPROVED_REASON);
                    $approved_ids[] = $id;
                    $out['approved']++;
                } elseif ($after !== null && $after['status'] === 'Declined') {
                    $out['declined']++;   // the engine's own blocker (overdue / duplicate): its rule and reason stand
                } else {
                    eq_buffer_release($conn, $id);   // engine error: stays in its buffer, retried on the next poll
                }
            }
        }

        if ($approved_ids) {
            // Same QR codes and receipts as every other approval (items of one cart share ONE Claim QR)
            equipment_issue_tokens($conn, $approved_ids);
            require_once __DIR__ . '/../../notifications/core/approval-receipts.php';
            ps_receipts_sweep($conn);
        }
    } catch (\Throwable $e) {
        error_log('eq_buffer_settle - ' . $e->getMessage());   // housekeeping never takes a page down
    } finally {
        if ($lock_held) {
            try {
                $conn->query("SELECT RELEASE_LOCK('" . EQ_BUFFER_LOCK . "')");
            } catch (\Throwable $e) {
            }
        }
    }
    return $out;
}
