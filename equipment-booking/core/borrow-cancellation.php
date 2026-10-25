<?php

declare(strict_types=1);

/**
 * equipment-booking/core/borrow-cancellation.php
 *
 * A faculty member cancelling an equipment request that has NOT started yet (My Activity > Upcoming).
 * The equipment twin of room-reservation/core/room-cancellation.php, with the same rules:
 *
 *   WHAT CAN BE CANCELLED
 *     - Pending (Waiting) requests, any time before they would start.
 *     - Approved bookings that have not started, up to 1 hour before the start time
 *       ("Cancellations are not allowed within 1 hour of the start time", like rooms).
 *     Not: loans already out (use the Return QR), Overdue, Returned, Declined, or already Cancelled.
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
    public const CUTOFF_SECONDS = 3600;

    private static function tz(): DateTimeZone
    {
        return new DateTimeZone('Asia/Manila');
    }

    /**
     * Why this request cannot be cancelled by its owner right now, or null when it can.
     * Needs: status, borrow_date, borrow_time. No database access.
     */
    public static function blocker(array $row, DateTimeImmutable $now): ?string
    {
        $status = (string)($row['status'] ?? '');
        if ($status === 'Cancelled') {
            return 'This request was already cancelled.';
        }
        if ($status !== 'Waiting' && $status !== 'Approved') {
            return 'Only requests that have not started yet can be cancelled.';
        }

        $time  = trim((string)($row['borrow_time'] ?? ''));
        $start = new DateTimeImmutable((string)$row['borrow_date'] . ' ' . ($time !== '' ? $time : '00:00:00'), self::tz());
        if ($start <= $now) {
            return 'This borrowing has already started. Return it with the Return QR instead.';
        }
        // A pending request holds nothing for anyone else, so it can go any time before it starts.
        if ($status === 'Approved' && $start->getTimestamp() - $now->getTimestamp() <= self::CUTOFF_SECONDS) {
            return 'Cancellations are not allowed within 1 hour of the borrow start time.';
        }
        return null;
    }

    /** @return array<string,mixed>|null the request, only when it belongs to this faculty member */
    private static function fetch(mysqli $conn, int $requestId, string $facultyId, bool $lock): ?array
    {
        $stmt = $conn->prepare(
            'SELECT id, faculty_id, faculty_name, equipment_name, borrow_qty, status, stock_applied,
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
                    SET status = 'Cancelled', reason = ?, return_token = NULL, stock_applied = 0
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
}
