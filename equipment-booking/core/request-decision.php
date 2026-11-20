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
 *      NOW GOES THROUGH THE 10-MINUTE APPROVAL BUFFER (core/request-buffer.php): after the checks that
 *      make a request impossible (step a below) it is saved as Waiting with buffer_until = submitted + 10
 *      minutes. An admin can approve or decline it at any time during the buffer; if nobody does, the
 *      system approves it when every item has stock and NO overlapping booking, otherwise declines it.
 *      The auto-approve rules described in b / c below (faculty only, stock-health caps, cart rule...) are
 *      no longer applied to 'today' requests; they only remain for old rows without a booking mode.
 *      Decided immediately, in this order:
 *        a. Not possible at all (out right now, not enough free units): Declined with the reason.
 *        b. Possible, but not an automatic approval: left WAITING for an admin to approve or decline
 *           (rule_same_day_review). It is auto-approved ONLY when ALL of these hold:
 *             - a faculty member submitted it (not a student);
 *             - no letter / photo / file is attached;
 *             - it starts and ends on the same day;
 *             - the quantity of the item keeps its stock healthy (see equipment_same_day_quantity_limit()):
 *               fewer than 3 in stock = never; 3 to 9 in stock = at most one third of the stock (never
 *               fewer than 1 unit), so the item is not left low; 10 or more = at most 50% of the stock;
 *             - a cart of several items (see EQ_CART_FACULTY_AUTO) is the exception: when a FACULTY
 *               member submitted it and it is returned the same day, the whole cart is approved
 *               automatically whatever the stock-health / already-booked rules above say about its items.
 *               Only the rules that make a request impossible still apply: an item that cannot be done at
 *               all is declined on its own, and the borrowing limits (at most 3 items at a time, overdue,
 *               duplicates, ...) are still enforced by ArbitrationEngine. A cart that runs for more than
 *               one day (a 2 or 3 day loan) is never an exception: like a single item, it waits for an
 *               admin. So does a cart a student submitted;
 *             - nobody else has the item booked for any part of that time;
 *             - no other request for the item is still waiting for review for that time.
 *           A same-day request that is not decided by the time it starts is declined by the deadline below.
 *        c. Everything holds: Approved right away with its Claim / Return QR codes (Ready for Pick Up).
 *           An Approved request nobody collects is Cancelled once its end date/time passes
 *           (BookingSchedule::expireNoShows).
 *
 *   3. LATER-DATE request (booking_mode = 'future')
 *      Waits for an admin, exactly like a room reservation for a later date. If nobody has decided
 *      by the deadline it is DECLINED automatically (a later-date request is never auto-approved):
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
require_once __DIR__ . '/request-buffer.php';   // the 10-minute buffer every same-day request now goes through

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
if (!defined('EQ_SAMEDAY_REVIEW_RULE')) {
    // Marks a same-day request that is waiting for an admin because it did not meet the auto-approve rules.
    // equipment_decide_stuck() leaves these alone; an admin decision replaces the rule with 'override'.
    define('EQ_SAMEDAY_REVIEW_RULE', 'rule_same_day_review');
}
if (!defined('EQ_SAMEDAY_MIN_STOCK')) {
    // A same-day request is only auto-approved for an item with at least this many in total stock.
    define('EQ_SAMEDAY_MIN_STOCK', 3);
}
if (!defined('EQ_SAMEDAY_BIG_STOCK')) {
    // From this much total stock up, the "at most 50% of the stock" rule applies; below it the stricter
    // low-stock rule (at most one third of the stock) applies.
    define('EQ_SAMEDAY_BIG_STOCK', 10);
}
if (!defined('EQ_SAMEDAY_BIG_SHARE_PCT')) {
    define('EQ_SAMEDAY_BIG_SHARE_PCT', 50);
}
if (!defined('EQ_SAMEDAY_SMALL_DIVISOR')) {
    define('EQ_SAMEDAY_SMALL_DIVISOR', 3);
}
if (!defined('EQ_SAMEDAY_MAX_CART_ITEMS')) {
    // Only used for carts that do NOT qualify for EQ_CART_FACULTY_AUTO (see below).
    define('EQ_SAMEDAY_MAX_CART_ITEMS', 3);
}
if (!defined('EQ_CART_FACULTY_AUTO')) {
    // true  = a same-day cart of several items submitted by a faculty member is approved automatically
    //         (only a student's cart waits for an admin).
    // false = every item of the cart must also pass the per-item stock rules, else the whole cart waits.
    define('EQ_CART_FACULTY_AUTO', true);
}
if (!defined('EQ_AUTO_APPROVE_REASON')) {
    // Reason stored when an admin approves a request that already meets every automatic-approval rule
    // and so needs no typed reason or internal note.
    define('EQ_AUTO_APPROVE_REASON', 'Approved – meets the automatic approval rules.');
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
 * Give a student-submitted request its status key and return it (null if it could not be saved).
 * The Student Portal has no login, so this random key is what proves "this is my request" when the
 * portal later asks equipment-booking/api/student-request-status.php whether an admin approved it.
 */
function equipment_issue_student_key(mysqli $conn, int $id): ?string
{
    try {
        $key  = bin2hex(random_bytes(24));
        $stmt = $conn->prepare('UPDATE tbl_requests SET student_key = ? WHERE id = ?');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('si', $key, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $key : null;
    } catch (\Throwable $e) {
        error_log('equipment_issue_student_key[' . $id . '] - ' . $e->getMessage());
        return null;
    }
}

/**
 * Give a request that is still Waiting for an admin its Claim QR and Return QR right away, so the student's
 * receipt can show the Claim QR immediately. The Claim QR does nothing until the request is Approved
 * (claim_confirm.php only accepts Approved requests), and the admin's approval keeps these same tokens
 * (admin-override.php), so the QR the student already holds is the one that works.
 *
 * @return array{claim_token:?string, return_token:?string}
 */
function equipment_issue_pending_tokens(mysqli $conn, int $id): array
{
    $none = ['claim_token' => null, 'return_token' => null];
    try {
        $sel = $conn->prepare(
            "SELECT status, COALESCE(batch_id, '') AS batch_id, COALESCE(claim_token, '') AS claim_token,
                    COALESCE(bundle_claim_token, '') AS bundle_claim_token, COALESCE(return_token, '') AS return_token
               FROM tbl_requests WHERE id = ? LIMIT 1"
        );
        if (!$sel) {
            return $none;
        }
        $sel->bind_param('i', $id);
        $sel->execute();
        $r = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!$r) {
            return $none;
        }
        $claim  = $r['bundle_claim_token'] !== '' ? $r['bundle_claim_token'] : $r['claim_token'];
        $return = $r['return_token'];

        if ($r['status'] === 'Waiting' && ($claim === '' || $return === '')) {
            if ($return === '') {
                $return = bin2hex(random_bytes(32));
            }
            if ($claim === '' && $r['batch_id'] !== '') {
                // Items of one request share ONE Claim QR (bundle_claim_token): reuse the one its siblings already hold.
                $q = $conn->prepare(
                    "SELECT bundle_claim_token FROM tbl_requests
                      WHERE batch_id = ? AND id <> ? AND COALESCE(bundle_claim_token, '') <> ''
                        AND status IN ('Waiting', 'Approved') LIMIT 1"
                );
                if ($q) {
                    $q->bind_param('si', $r['batch_id'], $id);
                    $q->execute();
                    $claim = (string)($q->get_result()->fetch_assoc()['bundle_claim_token'] ?? '');
                    $q->close();
                }
                if ($claim === '') {
                    $claim = bin2hex(random_bytes(32));
                }
                $upd = $conn->prepare(
                    "UPDATE tbl_requests SET bundle_claim_token = ?, claim_token = NULL,
                            return_token = COALESCE(NULLIF(return_token, ''), ?)
                      WHERE id = ? AND status = 'Waiting'"
                );
            } else {
                if ($claim === '') {
                    $claim = bin2hex(random_bytes(32));
                }
                $upd = $conn->prepare(
                    "UPDATE tbl_requests SET claim_token = COALESCE(NULLIF(claim_token, ''), ?),
                            return_token = COALESCE(NULLIF(return_token, ''), ?)
                      WHERE id = ? AND status = 'Waiting'"
                );
            }
            if (!$upd) {
                return $none;
            }
            $upd->bind_param('ssi', $claim, $return, $id);
            $upd->execute();
            $upd->close();
        }
        return ['claim_token' => $claim !== '' ? $claim : null, 'return_token' => $return !== '' ? $return : null];
    } catch (\Throwable $e) {
        error_log('equipment_issue_pending_tokens[' . $id . '] - ' . $e->getMessage());
        return $none;
    }
}

/**
 * Most units of one item a same-day request may ask for and still be auto-approved (0 = never).
 * The point is to never let an automatic approval leave an item low on stock:
 *   - under 3 in stock ........ 0 (always reviewed by an admin)
 *   - 3 to 9 in stock ......... one third of the stock, at least 1 (3 -> 1, 6 -> 2, 9 -> 3), so at least
 *                               two thirds of the stock stays available for everybody else
 *   - 10 or more in stock ..... 50% of the stock (10 -> 5, 11 -> 5, 20 -> 10)
 */
function equipment_same_day_quantity_limit(int $total_stock): int
{
    if ($total_stock < EQ_SAMEDAY_MIN_STOCK) {
        return 0;
    }
    if ($total_stock < EQ_SAMEDAY_BIG_STOCK) {
        return max(1, intdiv($total_stock, EQ_SAMEDAY_SMALL_DIVISOR));
    }
    return intdiv($total_stock * EQ_SAMEDAY_BIG_SHARE_PCT, 100);
}

/**
 * The same-day auto-approval rules, as a pure check (no database), so they can be tested on their own.
 * Returns null when the request may be auto-approved, otherwise the reason it needs an admin
 * (a short phrase that completes "Waiting for an admin to decide because ...").
 *
 * @param array{submitted_as:string, has_document:bool, borrow_date:string, return_date:string, qty:int} $req
 * @param int $total_stock      total units of the item (on the shelf + out)
 * @param int $other_bookings   units of the item already booked by others for any part of the time
 * @param int $other_waiting    other requests for the item still waiting for review for that time
 */
function equipment_same_day_review_reason(array $req, int $total_stock, int $other_bookings, int $other_waiting): ?string
{
    if ($req['submitted_as'] !== 'personal') {
        return 'same-day requests from students are reviewed by an admin';
    }
    if ($req['has_document']) {
        return 'it has a letter or photo attached';
    }
    if ($req['return_date'] !== $req['borrow_date']) {
        return 'it runs for more than one day';
    }
    if ($total_stock < EQ_SAMEDAY_MIN_STOCK) {
        return 'this item has fewer than ' . EQ_SAMEDAY_MIN_STOCK . ' in stock';
    }
    $limit = equipment_same_day_quantity_limit($total_stock);
    if ($req['qty'] > $limit) {
        return $total_stock >= EQ_SAMEDAY_BIG_STOCK
            ? 'the quantity is more than half of the ' . $total_stock . ' in stock (up to ' . $limit . ' can be approved automatically)'
            : 'this item is low on stock (' . $total_stock . ' in stock), so only up to ' . $limit . ' can be approved automatically';
    }
    if ($other_bookings > 0) {
        return 'the item is already reserved for part of that time';
    }
    if ($other_waiting > 0) {
        return 'another request for this item is still waiting for review';
    }
    return null;
}

/**
 * What should happen to a same-day request: be declined, wait for an admin, or be auto-approved.
 *
 * @return array{block:?array{rule:string, reason:string}, review:?string, personal?:bool, one_day?:bool}
 *         block  = cannot be done at all (declined on the spot, same messages the engine uses);
 *         review = reason it needs an admin; both null = auto-approve it;
 *         personal = a faculty member (not a student) submitted it;
 *         one_day  = it is returned the same day it starts (a 2 or 3 day loan is not).
 */
function equipment_same_day_gate(mysqli $conn, int $id): array
{
    $wait = static fn (string $why): array => ['block' => null, 'review' => $why];

    if (!BookingSchedule::ensureSchema($conn)) {
        return $wait('the booking schedule could not be checked');
    }
    $stmt = $conn->prepare(
        "SELECT equipment_name, COALESCE(borrow_qty, 1) AS qty, borrow_date, return_date,
                borrow_time, return_time, COALESCE(submitted_as, '') AS submitted_as,
                COALESCE(document_path, '') AS document_path
           FROM tbl_requests WHERE id = ? LIMIT 1"
    );
    if (!$stmt) {
        return $wait('the request could not be checked');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        return $wait('the request could not be checked');
    }

    $item    = (string)$r['equipment_name'];
    $qty     = max(1, (int)$r['qty']);
    $startTs = $r['borrow_date'] . ' ' . (!empty($r['borrow_time']) ? $r['borrow_time'] : '00:00:00');
    $endTs   = $r['return_date'] . ' ' . (!empty($r['return_time']) ? $r['return_time'] : '23:59:59');

    $shelf = BookingSchedule::shelfQuantity($conn, $item);
    if ($shelf === null) {
        return $wait('the item could not be found in the inventory');
    }

    // a. Not possible at all: declined on the spot (same rules and messages as ArbitrationEngine::processScheduled)
    $total    = BookingSchedule::totalStock($conn, $item);
    $occupied = BookingSchedule::occupancy($conn, $item, $startTs, $endTs, $id);
    $free     = $total - $occupied;
    if ($shelf < 1) {
        return ['block' => ['rule' => ArbitrationEngine::RULE_OUT_OF_STOCK, 'reason' => 'Out right now – book it for a later date instead.'], 'review' => null];
    }
    if ($free < $qty) {
        $why = $free <= 0
            ? 'Already fully booked for the requested schedule.'
            : 'Only ' . $free . ' unit(s) are free for the requested schedule (' . $qty . ' requested).';
        return ['block' => ['rule' => ArbitrationEngine::RULE_SCHEDULE_CONFLICT, 'reason' => $why], 'review' => null];
    }

    // b. Possible: auto-approve only when every rule holds, otherwise an admin decides
    $waiting = 0;
    $q = $conn->prepare(
        "SELECT COUNT(*) AS n FROM tbl_requests
          WHERE equipment_name = ? AND id <> ? AND status = 'Waiting'
            AND TIMESTAMP(borrow_date, COALESCE(borrow_time, '00:00:00')) < ?
            AND TIMESTAMP(return_date, COALESCE(return_time, '23:59:59')) > ?"
    );
    if ($q) {
        $q->bind_param('siss', $item, $id, $endTs, $startTs);
        $q->execute();
        $waiting = (int)($q->get_result()->fetch_assoc()['n'] ?? 0);
        $q->close();
    }

    $why = equipment_same_day_review_reason(
        [
            'submitted_as' => (string)$r['submitted_as'],
            'has_document' => trim((string)$r['document_path']) !== '',
            'borrow_date'  => (string)$r['borrow_date'],
            'return_date'  => (string)$r['return_date'],
            'qty'          => $qty,
        ],
        $total,
        $occupied,
        $waiting
    );
    return [
        'block'    => null,
        'review'   => $why,
        'personal' => (string)$r['submitted_as'] === 'personal',
        'one_day'  => (string)$r['return_date'] === (string)$r['borrow_date'],   // returned the same day it starts
    ];
}

/**
 * Does a request that is still Waiting meet EVERY automatic-approval rule right now (same-day, faculty,
 * one day, no attachment, healthy quantity, nothing else booked or waiting for that time)? The admin
 * Approve screen uses this to skip the Reason / Internal Note fields, and admin-override.php re-checks
 * it on the server so the fields can only be skipped for such a request.
 */
function equipment_waiting_meets_auto_rules(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare("SELECT status, COALESCE(booking_mode, '') AS booking_mode FROM tbl_requests WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r || $r['status'] !== 'Waiting' || !in_array($r['booking_mode'], ['today', ''], true)) {
        return false;
    }
    if ($r['booking_mode'] === 'today') {
        // Same day: "meets the rules" = the automatic check at the end of the buffer would approve it right now
        return eq_buffer_ids_pass($conn, [$id]);
    }
    $gate = equipment_same_day_gate($conn, $id);
    return $gate['block'] === null && $gate['review'] === null;
}

/**
 * Same check for a whole cart (batch): a faculty member's same-day cart (EQ_CART_FACULTY_AUTO), or one with
 * at most EQ_SAMEDAY_MAX_CART_ITEMS items where every item still Waiting meets the rules. Used by the
 * "approve all" (bundle) screen.
 */
function equipment_cart_meets_auto_rules(mysqli $conn, string $batch_id): bool
{
    static $cache = [];
    if ($batch_id === '') {
        return false;
    }
    if (isset($cache[$batch_id])) {
        return $cache[$batch_id];
    }
    $ok = false;
    $stmt = $conn->prepare("SELECT id, status, borrow_date, return_date, COALESCE(booking_mode, '') AS booking_mode, COALESCE(submitted_as, '') AS submitted_as FROM tbl_requests WHERE batch_id = ? ORDER BY id");
    if ($stmt) {
        $stmt->bind_param('s', $batch_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        // Same-day cart (10-minute buffer): it meets the rules when the automatic check would approve the whole cart now
        $wait_ids  = [];
        $all_today = true;
        foreach ($rows as $row) {
            if ($row['status'] === 'Waiting') {
                $wait_ids[] = (int)$row['id'];
                if ($row['booking_mode'] !== 'today') {
                    $all_today = false;
                }
            }
        }
        if ($wait_ids && $all_today) {
            return $cache[$batch_id] = eq_buffer_ids_pass($conn, $wait_ids);
        }

        $ok = $rows !== [] && count($rows) <= EQ_SAMEDAY_MAX_CART_ITEMS;
        $waiting = 0;
        foreach ($rows as $row) {
            if ($ok && $row['status'] === 'Waiting') {
                $waiting++;
                $ok = equipment_waiting_meets_auto_rules($conn, (int)$row['id']);
            }
        }
        $ok = $ok && $waiting > 0;

        // A faculty member's same-day cart is an automatic approval whatever its items' own rules say
        // (EQ_CART_FACULTY_AUTO): it qualifies when it has several items, none of them from a student,
        // and it is returned the same day (a 2 or 3 day loan always waits for an admin).
        if (!$ok && EQ_CART_FACULTY_AUTO && count($rows) > 1 && $waiting > 0) {
            $faculty = true;
            foreach ($rows as $row) {
                if ($row['status'] === 'Waiting' && ($row['submitted_as'] !== 'personal' || !in_array($row['booking_mode'], ['today', ''], true)
                    || (string)$row['return_date'] !== (string)$row['borrow_date'])) {
                    $faculty = false;
                }
            }
            $ok = $faculty;
        }
    }
    return $cache[$batch_id] = $ok;
}

/**
 * Keep a same-day request Waiting for an admin, with the reason shown to the borrower.
 *
 * @return string the note written to the request
 */
function equipment_same_day_hold(mysqli $conn, int $id, string $because): string
{
    $text = 'Waiting for an admin to decide because ' . $because
        . '. If nobody decides before the start time, it is declined automatically.';
    $rule = EQ_SAMEDAY_REVIEW_RULE;
    $note = $conn->prepare("UPDATE tbl_requests SET reason = ?, arbitration_rule = ? WHERE id = ? AND status = 'Waiting'");
    if ($note) {
        $note->bind_param('ssi', $text, $rule, $id);
        $note->execute();
        $note->close();
    }
    return $text;
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
    $sameDay  = [];   // id => ['review' => ?string, 'batch' => string, 'personal' => bool, 'one_day' => bool]: same-day rows that passed the hard checks

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

            // 2. Same day (and old rows without a booking mode): decide now.
            //    Impossible -> declined; possible but not an automatic case -> waits for an admin; else approved.
            $gate = equipment_same_day_gate($conn, $id);
            if ($gate['block'] !== null) {
                ArbitrationEngine::record($conn, $id, 'Declined', $gate['block']['rule'], $gate['block']['reason']);
                ArbitrationEngine::discardIfDeclined($conn, $id);
                $out[$id] = ['status' => 'Declined', 'reason' => $gate['block']['reason']];
                continue;
            }
            // Same day: into the 10-minute buffer (core/request-buffer.php). It stays Waiting; an admin may decide it
            // meanwhile, otherwise eq_buffer_settle() approves or declines it when the buffer ends.
            if ($row['booking_mode'] === 'today') {
                $out[$id] = eq_buffer_enter($conn, $id);
                continue;
            }
            // Old rows without a booking mode only: remember it. Whether it is approved depends on the WHOLE cart (see below).
            $sameDay[$id] = ['review' => $gate['review'], 'batch' => $row['batch_id'], 'personal' => !empty($gate['personal']), 'one_day' => !empty($gate['one_day'])];
        } catch (\Throwable $e) {
            error_log('equipment_decide_on_submit[' . $id . '] - ' . $e->getMessage());
        }
    }

    // Same-day rows, grouped by cart (a single item is its own group).
    //   - a cart a FACULTY member submitted (EQ_CART_FACULTY_AUTO) is approved as a whole;
    //   - anything else (a single item, or a cart with a student's item) is approved only when EVERY item
    //     passes the rules and it has at most EQ_SAMEDAY_MAX_CART_ITEMS items; otherwise the whole group
    //     waits for an admin.
    // (Items that cannot be done at all were declined above, so they do not count as part of the cart.)
    $groups = [];
    foreach ($sameDay as $id => $g) {
        $key = $g['batch'] !== '' ? 'b:' . $g['batch'] : 's:' . $id;
        $groups[$key][$id] = $g;
    }
    foreach ($groups as $key => $members) {
        try {
            $cart_why = null;
            $is_cart  = strncmp($key, 'b:', 2) === 0;
            $faculty_cart = EQ_CART_FACULTY_AUTO && $is_cart
                && count(array_filter($members, static fn ($m) => empty($m['personal']) || empty($m['one_day']))) === 0;
            if ($is_cart && !$faculty_cart) {
                $cnt = $conn->prepare('SELECT COUNT(*) AS n FROM tbl_requests WHERE batch_id = ?');
                if ($cnt) {
                    $bid = substr($key, 2);
                    $cnt->bind_param('s', $bid);
                    $cnt->execute();
                    $n = (int)($cnt->get_result()->fetch_assoc()['n'] ?? 0);
                    $cnt->close();
                    if ($n > EQ_SAMEDAY_MAX_CART_ITEMS) {
                        $cart_why = 'the request has more than ' . EQ_SAMEDAY_MAX_CART_ITEMS . ' items';
                    }
                }
            }
            $needs_admin = !$faculty_cart
                && ($cart_why !== null || count(array_filter($members, static fn ($m) => $m['review'] !== null)) > 0);

            foreach ($members as $id => $meta) {
                if ($needs_admin) {
                    $because = $meta['review'] ?? $cart_why ?? 'it was requested together with another item that needs an admin\'s review';
                    $out[$id] = ['status' => 'Waiting', 'reason' => equipment_same_day_hold($conn, $id, $because)];
                    continue;
                }
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
            }
        } catch (\Throwable $e) {
            error_log('equipment_decide_on_submit[group ' . $key . '] - ' . $e->getMessage());
        }
    }

    if ($approved) {
        equipment_issue_tokens($conn, $approved);
        // Approved by the automatic rules: send the faculty their approval receipt.
        require_once __DIR__ . '/../../notifications/core/approval-receipts.php';
        ps_receipts_sweep($conn);
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
                    ((COALESCE(request_date, '1970-01-01 00:00:00')
                        >= TIMESTAMP(borrow_date, '00:01:00'))
                      OR COALESCE(booking_mode, '') <> 'future') AS submitted_on_the_day
               FROM tbl_requests
              WHERE status = 'Waiting' AND borrow_date IS NOT NULL
                AND buffer_until IS NULL   /* a buffered same-day request is decided by eq_buffer_settle() */
                AND (
                      (COALESCE(request_date, '1970-01-01 00:00:00') <  TIMESTAMP(borrow_date, '00:01:00')
                        AND COALESCE(booking_mode, '') = 'future'
                        AND ? >= TIMESTAMP(borrow_date, '00:01:00'))
                   OR ((COALESCE(request_date, '1970-01-01 00:00:00') >= TIMESTAMP(borrow_date, '00:01:00')
                          OR COALESCE(booking_mode, '') <> 'future')
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
 * before this flow existed): decide them now. Later-date requests are never touched here, and neither
 * are same-day requests already sent to an admin on purpose (rule_same_day_review).
 */
function equipment_decide_stuck(mysqli $conn): void
{
    try {
        $cutoff = date('Y-m-d H:i:s', time() - EQ_DECIDE_GRACE_SECONDS);
        $stmt   = $conn->prepare(
            "SELECT id FROM tbl_requests
              WHERE status = 'Waiting'
                AND buffer_until IS NULL   /* a buffered same-day request is decided by eq_buffer_settle() */
                AND COALESCE(booking_mode, '') <> 'future'
                AND COALESCE(arbitration_rule, '') <> '" . EQ_SAMEDAY_REVIEW_RULE . "'
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
