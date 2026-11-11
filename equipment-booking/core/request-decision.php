<?php

declare(strict_types=1);

/**
 * equipment-booking/core/request-decision.php
 *
 * ONE place that decides an equipment borrow request. Every way of submitting a request
 * (faculty single item, faculty cart, student with a faculty code, item code) calls
 * equipment_decide_on_submit() right after the row is inserted, so the same rules apply
 * no matter who or what submitted it:
 *
 *   1. The rules that block any request (overdue borrower, duplicate, archived item):
 *      ArbitrationEngine::preflight().  A blocked request is Declined on the spot, with the reason.
 *      Existing policy kept: a request declined at the moment of submitting is not saved (the row is
 *      removed, see ArbitrationEngine::discardIfDeclined) and the reason is shown to the user right away.
 *      A request that is declined LATER (by an admin, or by the deadline below) stays in My Activity history.
 *
 *   2. SAME-DAY request (booking_mode = 'today')
 *      Decided immediately against the schedule (ArbitrationEngine::process): Approved with its
 *      Claim / Return QR codes, or Declined with the reason (already booked, out right now).
 *      It is never left "Waiting".
 *
 *   3. LATER-DATE request (booking_mode = 'future')
 *      Waits for an admin, exactly like a room reservation for a later date. If nobody has decided
 *      by the deadline it is DECLINED automatically (never auto-approved):
 *
 *          deadline = 12:01 AM (Asia/Manila) on the borrow date
 *
 *      so the faculty member finds out on the morning of the day with the whole day left. One
 *      exception: a request only SUBMITTED after 12:01 AM of its own borrow day has no "before the
 *      day" left, so its deadline is its start time. (Same rule as room-reservation/core/room-expiry.php.)
 *
 * There is no cron job in this project, so equipment_auto_approve_tick() (kept under that name for its
 * callers, see auto-approve-tick.php) runs the deadline lazily on every admin / faculty poll. It is
 * cheap, safe to call any number of times and race-safe (a row is only touched if it is still Waiting).
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/arbitration-engine.php';

if (!defined('EQ_EXPIRY_RULE')) {
    // Same rule id the room reservations use, so the admin Activity feed already knows its label.
    define('EQ_EXPIRY_RULE', 'rule_expired_unreviewed');
}
if (!defined('EQ_EXPIRY_REASON_DAY')) {
    define('EQ_EXPIRY_REASON_DAY', 'Not reviewed in time – automatically declined at 12:01 AM on the borrow date.');
}
if (!defined('EQ_EXPIRY_REASON_START')) {
    define('EQ_EXPIRY_REASON_START', 'Not reviewed before the borrow start time – automatically declined.');
}
if (!defined('EQ_WAITING_NOTE')) {
    // Shown to the faculty member on a Waiting request (My Activity "reason" line).
    define('EQ_WAITING_NOTE', 'Waiting for an admin to review it. If nobody decides, it is declined automatically at 12:01 AM on the borrow date.');
}
if (!defined('EQ_EXPIRY_SETTLE_SECONDS')) {
    // For a request submitted AFTER 12:01 AM of its own borrow day (its deadline is its start time):
    // how long it must have existed before the deadline may decline it.
    define('EQ_EXPIRY_SETTLE_SECONDS', 300);
}
if (!defined('EQ_DECIDE_GRACE_SECONDS')) {
    // A same-day request still Waiting after this long was missed at submit time (error, crash):
    // the next poll decides it. Short enough to feel instant, long enough not to race the submit.
    define('EQ_DECIDE_GRACE_SECONDS', 30);
}

/** @return array{id:int, status:string, reason:string, booking_mode:string, batch_id:string}|null */
function equipment_decision_row(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, status, COALESCE(reason, '') AS reason, COALESCE(booking_mode, '') AS booking_mode,
                COALESCE(batch_id, '') AS batch_id
           FROM tbl_requests WHERE id = ? LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return [
        'id'           => (int)$row['id'],
        'status'       => (string)$row['status'],
        'reason'       => (string)$row['reason'],
        'booking_mode' => (string)$row['booking_mode'],
        'batch_id'     => (string)$row['batch_id'],
    ];
}

/**
 * The QR codes of an Approved request, for the receipt shown right after submitting.
 *
 * @return array{claim_token:?string, return_token:?string}  claim_token is the bundle token when the
 *         request was approved together with others from one cart
 */
function equipment_request_tokens(mysqli $conn, int $id): array
{
    $none = ['claim_token' => null, 'return_token' => null];
    $stmt = $conn->prepare(
        "SELECT COALESCE(NULLIF(claim_token, ''), NULLIF(bundle_claim_token, '')) AS claim, NULLIF(return_token, '') AS ret
           FROM tbl_requests WHERE id = ? AND status = 'Approved' LIMIT 1"
    );
    if (!$stmt) {
        return $none;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? ['claim_token' => $row['claim'] ?: null, 'return_token' => $row['ret'] ?: null] : $none;
}

/**
 * Decide freshly submitted requests (all of them Waiting, usually the items of one cart).
 *
 * @param  list<int> $request_ids
 * @return array<int, array{status:string, reason:string}>  request id => what happened to it
 */
function equipment_decide_on_submit(mysqli $conn, array $request_ids): array
{
    $out      = [];
    $approved = [];

    foreach (array_values(array_unique(array_map('intval', $request_ids))) as $id) {
        $out[$id] = ['status' => 'Waiting', 'reason' => ''];
        try {
            $row = equipment_decision_row($conn, $id);
            if ($row === null) {
                continue;
            }
            if ($row['status'] !== 'Waiting') {
                // Someone (an admin, a concurrent poll) already decided it.
                $out[$id] = ['status' => $row['status'], 'reason' => $row['reason']];
                continue;
            }

            // 1. Rules that block any request, today or later
            $blocked = ArbitrationEngine::preflight($conn, $id);
            if ($blocked !== null) {
                ArbitrationEngine::record($conn, $id, 'Declined', $blocked['rule'], $blocked['reason']);
                ArbitrationEngine::discardIfDeclined($conn, $id);
                $out[$id] = ['status' => 'Declined', 'reason' => $blocked['reason']];
                continue;
            }

            // 3. Later date: waits for an admin; the deadline in equipment_expire_unreviewed() applies
            if ($row['booking_mode'] === 'future') {
                $note = $conn->prepare("UPDATE tbl_requests SET reason = ? WHERE id = ? AND status = 'Waiting'");
                if ($note) {
                    $text = EQ_WAITING_NOTE;
                    $note->bind_param('si', $text, $id);
                    $note->execute();
                    $note->close();
                }
                $out[$id] = ['status' => 'Waiting', 'reason' => EQ_WAITING_NOTE];
                continue;
            }

            // 2. Same day (and old rows without a booking mode): decide now
            ArbitrationEngine::process($conn, $id);
            $after = equipment_decision_row($conn, $id);
            if ($after === null) {
                continue;
            }
            if ($after['status'] === 'Approved') {
                $approved[] = $id;
            } elseif ($after['status'] === 'Declined') {
                ArbitrationEngine::discardIfDeclined($conn, $id);
            }
            // Still Waiting here means the engine hit an error and could not decide; the next poll retries.
            $out[$id] = ['status' => $after['status'], 'reason' => $after['reason']];
        } catch (\Throwable $e) {
            error_log('equipment_decide_on_submit[' . $id . '] - ' . $e->getMessage());
        }
    }

    if ($approved) {
        equipment_issue_tokens($conn, $approved);
    }
    return $out;
}

/**
 * Give freshly approved requests their QR tokens, the same way an admin approval does
 * (approve-borrow-bundle.php): several items approved together from one cart share ONE Claim QR
 * (bundle_claim_token); a single item gets its own claim_token. Every row gets its own return_token.
 *
 * @param list<int> $ids  ids of requests that are now Approved
 */
function equipment_issue_tokens(mysqli $conn, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    $in  = implode(',', $ids);   // integers only, cast above
    $res = $conn->query(
        "SELECT id, COALESCE(batch_id, '') AS batch_id,
                COALESCE(return_token, '') AS return_token,
                COALESCE(claim_token, '') AS claim_token,
                COALESCE(bundle_claim_token, '') AS bundle_claim_token
           FROM tbl_requests
          WHERE id IN ($in) AND status = 'Approved'"
    );
    if (!$res) {
        return;
    }
    $groups = [];
    while ($r = $res->fetch_assoc()) {
        $key = $r['batch_id'] !== '' ? 'b:' . $r['batch_id'] : 's:' . $r['id'];
        $groups[$key][] = $r;
    }

    foreach ($groups as $rows) {
        $bundle       = count($rows) > 1;
        $bundle_token = $bundle ? bin2hex(random_bytes(32)) : null;

        foreach ($rows as $r) {
            if ($r['return_token'] !== '' && ($r['claim_token'] !== '' || $r['bundle_claim_token'] !== '')) {
                continue;   // already has its codes
            }
            $rid          = (int)$r['id'];
            $return_token = bin2hex(random_bytes(32));
            if ($bundle) {
                $stmt = $conn->prepare(
                    'UPDATE tbl_requests SET return_token = ?, claim_token = NULL, bundle_claim_token = ? WHERE id = ?'
                );
                if ($stmt) {
                    $stmt->bind_param('ssi', $return_token, $bundle_token, $rid);
                }
            } else {
                $claim_token = bin2hex(random_bytes(32));
                $stmt = $conn->prepare(
                    'UPDATE tbl_requests SET return_token = ?, claim_token = ?, bundle_claim_token = NULL WHERE id = ?'
                );
                if ($stmt) {
                    $stmt->bind_param('ssi', $return_token, $claim_token, $rid);
                }
            }
            if ($stmt) {
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

/**
 * Decline every Waiting request whose deadline has passed (see the header for the rule).
 *
 * @param DateTimeInterface|null $now  injectable for tests; defaults to "now" in Asia/Manila
 * @return int how many requests were declined by this call
 */
function equipment_expire_unreviewed(mysqli $conn, ?DateTimeInterface $now = null): int
{
    $now   = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $now_s = $now->format('Y-m-d H:i:s');

    $declined = 0;
    try {
        $sel = $conn->prepare(
            "SELECT id,
                    (COALESCE(request_date, '1970-01-01 00:00:00')
                        >= TIMESTAMP(borrow_date, '00:01:00')) AS submitted_on_the_day
               FROM tbl_requests
              WHERE status = 'Waiting' AND borrow_date IS NOT NULL
                AND (
                      (COALESCE(request_date, '1970-01-01 00:00:00') <  TIMESTAMP(borrow_date, '00:01:00')
                        AND ? >= TIMESTAMP(borrow_date, '00:01:00'))
                   OR (COALESCE(request_date, '1970-01-01 00:00:00') >= TIMESTAMP(borrow_date, '00:01:00')
                        AND ? >= TIMESTAMP(borrow_date, COALESCE(borrow_time, '23:59:59'))
                        AND COALESCE(request_date, '1970-01-01 00:00:00') < ?)
                )"
        );
        if (!$sel) {
            return 0;
        }
        // A request submitted only moments ago is never expired: it is still being decided by its own submit
        $settled_s = date('Y-m-d H:i:s', $now->getTimestamp() - EQ_EXPIRY_SETTLE_SECONDS);
        $sel->bind_param('sss', $now_s, $now_s, $settled_s);
        $sel->execute();
        $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
        $sel->close();

        foreach ($rows as $row) {
            $id     = (int)$row['id'];
            $reason = ((int)$row['submitted_on_the_day'] === 1) ? EQ_EXPIRY_REASON_START : EQ_EXPIRY_REASON_DAY;
            $rule   = EQ_EXPIRY_RULE;

            $upd = $conn->prepare(
                "UPDATE tbl_requests SET status = 'Declined', arbitration_rule = ?, reason = ?
                  WHERE id = ? AND status = 'Waiting'"
            );
            if (!$upd) {
                continue;
            }
            $upd->bind_param('ssi', $rule, $reason, $id);
            $upd->execute();
            $changed = $upd->affected_rows === 1;
            $upd->close();
            if (!$changed) {
                continue;   // an admin decided it a moment ago: leave their decision alone
            }
            $declined++;
            // Log row for the admin Activity feed. The request itself is KEPT (unlike a request declined at the
            // moment of submitting, which is removed): the faculty member must see in My Activity what happened and why.
            ArbitrationEngine::record($conn, $id, 'Declined', $rule, $reason);
        }
    } catch (\Throwable $e) {
        error_log('equipment_expire_unreviewed - ' . $e->getMessage());   // housekeeping never takes a page down
    }
    return $declined;
}

/**
 * Same-day requests that were left Waiting (the submit crashed before deciding, or an old row from
 * before this flow existed): decide them now. Later-date requests are never touched here.
 */
function equipment_decide_stuck(mysqli $conn): void
{
    try {
        $cutoff = date('Y-m-d H:i:s', time() - EQ_DECIDE_GRACE_SECONDS);
        $stmt   = $conn->prepare(
            "SELECT id FROM tbl_requests
              WHERE status = 'Waiting'
                AND COALESCE(booking_mode, '') <> 'future'
                AND request_date < ?
              ORDER BY request_date ASC
              LIMIT 50"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $stmt->close();
        if ($ids) {
            equipment_decide_on_submit($conn, $ids);
        }
    } catch (\Throwable $e) {
        error_log('equipment_decide_stuck - ' . $e->getMessage());
    }
}
