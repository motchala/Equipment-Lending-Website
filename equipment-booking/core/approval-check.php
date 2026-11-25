<?php

declare(strict_types=1);

/**
 * equipment-booking/core/approval-check.php
 *
 * "Can an admin approve this request without overbooking the item?"
 *
 * A same-day or later-date booking is judged against the SCHEDULE (the units already promised to other
 * Approved / On Loan / Overdue bookings that overlap its time), never against what is on the shelf right
 * now. The automatic decision (request-decision.php, ArbitrationEngine) already works that way. This
 * file gives the ADMIN approvals the same rule, so every way of approving a booking agrees:
 *
 *   - admin-override.php          approve a single request (or re-approve a declined one)
 *   - approve-borrow-bundle.php   approve a whole cart
 *
 * Without it an admin could approve two overlapping requests for the last unit, and the stock
 * would only be "taken" later, silently stopping at zero when the second booking started.
 *
 * Older requests that do not belong to the schedule (booking_mode other than today / future) are not
 * covered here; their callers keep the shelf-quantity check they always had.
 *
 * USAGE (inside the caller's transaction, so the lock below holds until it ends):
 *   require_once __DIR__ . '/../core/approval-check.php';
 *   $why = equipment_approval_blocker($conn, $request_id);
 *   if ($why !== null) { $conn->rollback(); ... "Cannot approve: " . $why ... }
 */

require_once __DIR__ . '/booking-schedule.php';

if (!function_exists('equipment_approval_blocker')) {
    /**
     * Why this request cannot be approved right now, or null when it can.
     * Returns a sentence that continues "Cannot approve: ..." (no capital, no leading words).
     */
    function equipment_approval_blocker(mysqli $conn, int $requestId): ?string
    {
        $st = $conn->prepare(
            "SELECT equipment_name, borrow_qty, booking_mode, borrow_date, borrow_time, return_date, return_time
               FROM tbl_requests WHERE id = ? LIMIT 1"
        );
        if (!$st) {
            return null;   // cannot check: leave the decision to the caller's own rules
        }
        $st->bind_param('i', $requestId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row || !in_array((string)($row['booking_mode'] ?? ''), ['today', 'future'], true)) {
            return null;
        }

        $item  = (string)$row['equipment_name'];
        $qty   = max(1, (int)($row['borrow_qty'] ?? 1));
        $start = (string)$row['borrow_date'] . ' ' . substr((string)($row['borrow_time'] ?: '00:00:00'), 0, 8);
        $end   = (string)$row['return_date'] . ' ' . substr((string)($row['return_time'] ?: '23:59:59'), 0, 8);

        // Lock the item's inventory rows: two admins approving at the same moment cannot both take the last unit.
        $lock = $conn->prepare('SELECT quantity FROM tbl_inventory WHERE item_name = ? FOR UPDATE');
        if ($lock) {
            $lock->bind_param('s', $item);
            $lock->execute();
            $lock->get_result();
            $lock->close();
        }

        $free = BookingSchedule::freeUnits($conn, $item, $start, $end, $requestId);
        if ($free >= $qty) {
            return null;
        }
        if ($free <= 0) {
            return 'every unit of "' . $item . '" is already booked for that time. Decline this request, or ask the faculty member to choose another time.';
        }
        return 'only ' . $free . ' of the ' . $qty . ' requested units of "' . $item . '" ' . ($free === 1 ? 'is' : 'are')
            . ' free for that time. Decline this request, or ask the faculty member to choose another time.';
    }
}
