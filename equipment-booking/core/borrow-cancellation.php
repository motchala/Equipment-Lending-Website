<?php

declare(strict_types=1);

/**
 * equipment-booking/core/borrow-cancellation.php
 *
 * A faculty member cancelling an equipment request that has NOT been collected yet (My Activity).
 * The equipment twin of room-reservation/core/room-cancellation.php, but with a rule that fits instant
 * same-day approval: nothing is out until the item is collected, so there is nothing to protect.
 *
 *   WHAT CAN BE CANCELLED
 *     - Pending (Waiting) requests, any time.
 *     - Approved SAME-DAY bookings, any time until the item is collected (even after the start time, so a
 *       booking made by mistake a few minutes before its slot can always be undone).
 *     - Approved LATER-DATE bookings, until the cancellation cutoff before the start (Settings → Arbitration,
 *       default 1 hour: an admin may already be preparing it). After the start time, if nobody collected it,
 *       it can be cancelled again.
 *     Not: items already collected (use the Return QR), Overdue, Returned, Declined, or already Cancelled.
 *
 *   CANCEL ALL
 *     A request made together with others (a bundle) can be cancelled as a whole: cancelBundle() cancels
 *     every item of that bundle that can still be cancelled, one at a time, with the same rules.
 *
 *   WHAT HAPPENS
 *     - tbl_requests.status becomes 'Cancelled' (reason "Cancelled by faculty") and the return QR is voided.
 *     - The units go back to the schedule at once: availability is worked out from Approved/Overdue
 *       bookings (BookingSchedule::occupancy), so the Borrow form, the arbitration engine and the
 *       Extend check all see them free with no extra bookkeeping.
 *     - The shelf count is only touched when the booking had already taken its units off the shelf
 *       (stock_applied = 1: older requests approved before scheduling existed). Later bookings never
 *       took any, so nothing is given back for them (same rule as the admin override).
 *     - One row in tbl_arbitration_log (decision 'Cancelled', rule 'faculty_cancelled') so the admin
 *       Activity feed shows it. It replaces the earlier decision row for that request, the same way
 *       an admin override or a return does.
 *     - Everything happens in one transaction that locks the item's inventory row first (the order
 *       every other stock decision uses), so a cancel can never race a booking for the same item.
 *
 *   WHO SEES IT
 *     Faculty: My Activity (history shows Cancelled), Home (recent activity, key figures), notifications.
 *     Admin:   the request leaves the Pending / Approved queues; Activity feed, Latest requests and the
 *              equipment chart show it as Cancelled; it is left out of request totals (it never ran).
 */

require_once __DIR__ . '/booking-schedule.php';

final class BorrowCancellation
{
    /** Same cutoff as room reservations. */
    public const CUTOFF_SECONDS = 3600;   // default; the live value is Settings → Arbitration → Cancellation cutoff

    /** Seconds before the start time after which an approved booking can no longer be cancelled. */
    public static function cutoffSeconds(): int
    {
        return BookingSchedule::cancelCutoffSeconds();
    }

    /** "1 hour" / "2 hours" / "30 minutes": the cutoff in words. */
    public static function cutoffLabel(): string
    {
        $min = intdiv(self::cutoffSeconds(), 60);
        if ($min >= 60 && $min % 60 === 0) {
            $h = intdiv($min, 60);
            return $h . ($h === 1 ? ' hour' : ' hours');
        }
        return $min . ($min === 1 ? ' minute' : ' minutes');
    }

    private static function tz(): DateTimeZone
    {
        return new DateTimeZone('Asia/Manila');
    }

    /**
     * Why this request cannot be cancelled by its owner right now, or null when it can.
     * Needs: status, borrow_date, borrow_time (and booking_mode when known). No database access.
     */
    public static function blocker(array $row, DateTimeImmutable $now): ?string
    {
        $status = (string)($row['status'] ?? '');
        if ($status === 'Cancelled') {
            return 'This request was already cancelled.';
        }
        if ($status === 'On Loan' || $status === 'Overdue') {
            return 'This item is already with you. Return it with the Return QR instead.';
        }
        if ($status !== 'Waiting' && $status !== 'Approved') {
            return 'Only requests that have not been collected yet can be cancelled.';
        }

        // A pending request holds nothing for anyone else, so it can go any time.
        if ($status === 'Waiting') {
            return null;
        }

        // Approved and not collected. A same-day booking was approved on the spot, so it can be undone
        // any time; only a later-date booking has a cutoff before its start.
        $mode = (string)($row['booking_mode'] ?? '');
        if ($mode !== 'today' && $mode !== 'future') {
            $mode = (string)($row['borrow_date'] ?? '') > $now->format('Y-m-d') ? 'future' : 'today';
        }
        if ($mode === 'future' && self::cutoffSeconds() > 0) {
            $time  = trim((string)($row['borrow_time'] ?? ''));
            $start = new DateTimeImmutable((string)$row['borrow_date'] . ' ' . ($time !== '' ? $time : '00:00:00'), self::tz());
            $left  = $start->getTimestamp() - $now->getTimestamp();
            if ($left > 0 && $left <= self::cutoffSeconds()) {
                return 'Cancellations are not allowed within ' . self::cutoffLabel() . ' of the borrow start time.';
            }
        }
        return null;
    }

    /** @return array<string,mixed>|null the request, only when it belongs to this faculty member */
    private static function fetch(mysqli $conn, int $requestId, string $facultyId, bool $lock): ?array
    {
        $stmt = $conn->prepare(
            'SELECT id, faculty_id, faculty_name, equipment_name, borrow_qty, status, stock_applied, booking_mode,
                    borrow_date, borrow_time
               FROM tbl_requests WHERE id = ? AND faculty_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $requestId, $facultyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Cancels the request or says why not. Nothing changes unless it is cancelled.
     *
     * @return array{success:bool, error?:string, item?:string}
     */
    public static function cancel(mysqli $conn, int $requestId, string $facultyId): array
    {
        BookingSchedule::loadRules($conn);
        if (!BookingSchedule::ensureSchema($conn)) {
            return ['success' => false, 'error' => 'The request could not be cancelled right now. Please try again.'];
        }
        $now  = new DateTimeImmutable('now', self::tz());
        $peek = self::fetch($conn, $requestId, $facultyId, false);
        if (!$peek) {
            return ['success' => false, 'error' => 'We could not find that request.'];
        }

        // Is the log's override_by_id column there? (added by the admin activity bootstrap)
        $hasById = false;
        $res = $conn->query("SHOW COLUMNS FROM tbl_arbitration_log LIKE 'override_by_id'");
        if ($res && $res->num_rows > 0) {
            $hasById = true;
        }

        if (!$conn->begin_transaction()) {
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
        try {
            // Same order as every other stock decision: inventory row first, then the request
            $lock = $conn->prepare('SELECT quantity FROM tbl_inventory WHERE item_name = ? FOR UPDATE');
            if ($lock) {
                $name = (string)$peek['equipment_name'];
                $lock->bind_param('s', $name);
                $lock->execute();
                $lock->close();
            }

            $row = self::fetch($conn, $requestId, $facultyId, true);
            if (!$row) {
                $conn->rollback();
                return ['success' => false, 'error' => 'We could not find that request.'];
            }
            $why = self::blocker($row, $now);
            if ($why !== null) {
                $conn->rollback();
                return ['success' => false, 'error' => $why];
            }

            // Units only come back to the shelf if this booking had taken them off it
            $giveBack = ((string)$row['status'] === 'Approved' && (int)($row['stock_applied'] ?? 1) === 1)
                ? max(1, (int)($row['borrow_qty'] ?? 1))
                : 0;

            $note = 'Cancelled by faculty';
            $upd = $conn->prepare(
                "UPDATE tbl_requests
                    SET status = 'Cancelled', reason = ?, return_token = NULL,
                        claim_token = NULL, bundle_claim_token = NULL, stock_applied = 0
                  WHERE id = ? AND faculty_id = ? AND status IN ('Waiting','Approved')"
            );
            if (!$upd) {
                throw new \RuntimeException('prepare update failed: ' . $conn->error);
            }
            $upd->bind_param('sis', $note, $requestId, $facultyId);
            $upd->execute();
            $changed = $upd->affected_rows > 0;
            $upd->close();
            if (!$changed) {
                $conn->rollback();
                return ['success' => false, 'error' => 'This request changed while you were cancelling it. Please check My Activity.'];
            }

            if ($giveBack > 0) {
                $inc = $conn->prepare('UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?');
                if ($inc) {
                    $item = (string)$row['equipment_name'];
                    $inc->bind_param('is', $giveBack, $item);
                    $inc->execute();
                    $inc->close();
                }
            }

            // Audit trail for the admin Activity feed (one row per request, replaced like an override or a return)
            $resolved = 'Cancelled by the faculty member before it started.';
            $log = $conn->prepare(
                "INSERT INTO tbl_arbitration_log
                    (request_id, borrower_id, borrower_name, equipment_name, decision, rule_applied, reason, created_at)
                 VALUES (?, ?, ?, ?, 'Cancelled', 'faculty_cancelled', ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    decision = 'Cancelled', rule_applied = 'faculty_cancelled', reason = VALUES(reason),
                    override_by = NULL, override_reason = NULL" . ($hasById ? ', override_by_id = NULL' : '') . ',
                    created_at = NOW()'
            );
            if ($log) {
                $bid   = (string)$row['faculty_id'];
                $bname = (string)($row['faculty_name'] ?? '');
                $item  = (string)$row['equipment_name'];
                $log->bind_param('issss', $requestId, $bid, $bname, $item, $resolved);
                $log->execute();
                $log->close();
            }

            $conn->commit();
            return ['success' => true, 'item' => (string)$row['equipment_name']];
        } catch (\Throwable $e) {
            $conn->rollback();
            error_log('BorrowCancellation::cancel — ' . $e->getMessage());
            return ['success' => false, 'error' => 'Something went wrong while cancelling. Please try again.'];
        }
    }

    /**
     * Cancels every item of the request's bundle that can still be cancelled ("Cancel all"). A request that
     * is not part of a bundle is cancelled on its own. Each item goes through cancel(), so every rule and
     * side effect is the same as for a single item; items that cannot be cancelled are skipped and counted.
     *
     * @return array{success:bool, error?:string, item?:string, count?:int, skipped?:int}
     */
    public static function cancelBundle(mysqli $conn, int $requestId, string $facultyId): array
    {
        $stmt = $conn->prepare('SELECT batch_id FROM tbl_requests WHERE id = ? AND faculty_id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
        $stmt->bind_param('is', $requestId, $facultyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['success' => false, 'error' => 'We could not find that request.'];
        }
        $batch = trim((string)($row['batch_id'] ?? ''));
        if ($batch === '') {
            return self::cancel($conn, $requestId, $facultyId);
        }

        $ids  = [];
        $list = $conn->prepare("SELECT id FROM tbl_requests WHERE batch_id = ? AND faculty_id = ? AND status IN ('Waiting','Approved') ORDER BY id");
        if (!$list) {
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
        $list->bind_param('ss', $batch, $facultyId);
        $list->execute();
        $res = $list->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $ids[] = (int)$r['id'];
        }
        $list->close();

        $done    = 0;
        $skipped = 0;
        $first   = '';
        $error   = '';
        foreach ($ids as $id) {
            $one = self::cancel($conn, $id, $facultyId);
            if ($one['success']) {
                $done++;
                $first = $first !== '' ? $first : (string)($one['item'] ?? '');
            } else {
                $skipped++;
                $error = $error !== '' ? $error : (string)($one['error'] ?? '');
            }
        }
        if ($done === 0) {
            return ['success' => false, 'error' => $error !== '' ? $error : 'There is nothing left in this bundle to cancel.'];
        }
        return ['success' => true, 'item' => $first, 'count' => $done, 'skipped' => $skipped];
    }
}
