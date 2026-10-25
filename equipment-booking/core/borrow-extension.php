<?php

declare(strict_types=1);

/**
 * equipment-booking/core/borrow-extension.php
 *
 * "Extend" for a borrowed item that has reached its return time (faculty My Activity).
 *
 * WHO CAN EXTEND
 *   The faculty member who holds the loan, once its return moment has been reached:
 *   status Overdue, or Approved with the return moment already behind us.
 *   (return moment = return_date + return_time, end of that day when no time was set.)
 *
 * HOW LONG
 *   Same choices as the Borrow form for how the loan was booked, counted from NOW
 *   (see mode(): counter loans made with a faculty item code are hourly too):
 *     hourly ("today") loans   +1 / 2 / 3 / 5 hours (Organization Advisers: 1-12), rounded up to
 *                              the next 5 minutes, never past closing time (BookingSchedule::DAY_END)
 *     day loans                +1 / 2 / 3 days (Advisers: +5), keeping the original return time of day
 *
 * AVAILABILITY (the same ledger the Borrow form and the arbitration engine use)
 *   The units already in the faculty member's hands stay theirs; what must hold is that no
 *   OTHER commitment needs them during the extra period [now, new return moment):
 *     total stock  -  units promised to other Approved bookings that overlap the period
 *                     (today's and future bookings alike) and to other people's Overdue loans
 *     must be  >=  the quantity this faculty member holds.
 *   Pending (Waiting) requests are not commitments here, exactly as in BookingSchedule::occupancy().
 *   If the loan is going to a room, another faculty's reservation of that room during the period
 *   also blocks the extension (same rule as the Borrow form: BookingSchedule::roomConflictMessage()).
 *
 * WHEN IT IS ALLOWED
 *   The request row keeps its stock (nothing is returned or re-taken), gets the new return moment,
 *   goes back to Approved (so an Overdue item stops blocking new borrow requests), and
 *   extension_count goes up by one. The decision is made inside a transaction that locks the item's
 *   inventory row, like every other stock decision, so two people can never be given the same units.
 *
 * New tbl_requests column (added automatically by ensureSchema()):
 *   extension_count  INT NOT NULL DEFAULT 0
 */

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/../../room-reservation/core/faculty-room-quota.php';   // fact_day_label(), fact_time()

final class BorrowExtension
{
    private static ?bool $ready = null;

    /** Adds extension_count if it is missing. Call it BEFORE opening a transaction (ALTER ends one). */
    public static function ensureSchema(mysqli $conn): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $res = $conn->query("SHOW COLUMNS FROM tbl_requests LIKE 'extension_count'");
            $has = $res && $res->num_rows > 0;
            if (!$has) {
                $conn->query('ALTER TABLE tbl_requests ADD COLUMN `extension_count` INT NOT NULL DEFAULT 0');
                $res = $conn->query("SHOW COLUMNS FROM tbl_requests LIKE 'extension_count'");
                $has = $res && $res->num_rows > 0;
            }
            self::$ready = $has;
        } catch (\Throwable $e) {
            error_log('BorrowExtension::ensureSchema — ' . $e->getMessage());
            self::$ready = false;
        }
        return self::$ready;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Rules that need no database
    // ═════════════════════════════════════════════════════════════════════════

    private static function tz(): DateTimeZone
    {
        return new DateTimeZone('Asia/Manila');
    }

    /** The moment the loan is due back. */
    public static function dueAt(array $row): DateTimeImmutable
    {
        $time = trim((string)($row['return_time'] ?? ''));
        return new DateTimeImmutable(
            (string)$row['return_date'] . ' ' . ($time !== '' ? $time : '23:59:59'),
            self::tz()
        );
    }

    /**
     * 'today' (hourly) or 'future' (days). Requests made on the Borrow form carry their mode; older requests and
     * counter loans (a student using a faculty item code: borrowed and returned the same day, no mode stored)
     * are hourly when they start and end on the same date, and day loans otherwise.
     */
    public static function mode(array $row): string
    {
        $mode = (string)($row['booking_mode'] ?? '');
        if ($mode === 'today' || $mode === 'future') {
            return $mode;
        }
        $from = (string)($row['borrow_date'] ?? '');
        return ($from !== '' && $from === (string)($row['return_date'] ?? '')) ? 'today' : 'future';
    }

    /** True when this loan can be extended right now (return moment reached, loan still out). */
    public static function isDue(array $row, DateTimeImmutable $now): bool
    {
        $status = (string)($row['status'] ?? '');
        if ($status === 'Overdue') {
            return true;
        }
        return $status === 'Approved' && self::dueAt($row) <= $now;
    }

    /**
     * The extension lengths this loan may choose from, each with its new return moment.
     * May be empty (for example hourly loans when it is too late in the day).
     *
     * @return list<array{length:int, unit:string, label:string, due:DateTimeImmutable}>
     */
    public static function options(array $row, bool $adviser, DateTimeImmutable $now): array
    {
        $tz  = $now->getTimezone();
        $out = [];

        if (self::mode($row) === 'today') {
            // Hourly loan: more hours today, counted from now (office hours only)
            $day   = $now->format('Y-m-d');
            $open  = new DateTimeImmutable($day . ' ' . BookingSchedule::DAY_START . ':00', $tz);
            $close = new DateTimeImmutable($day . ' ' . BookingSchedule::DAY_END . ':00', $tz);
            $base  = $now < $open ? $open : $now;
            $base  = (new DateTimeImmutable('@' . (int)(ceil($base->getTimestamp() / 300) * 300)))->setTimezone($tz);

            foreach (BookingSchedule::allowedLengths('today', $adviser) as $hours) {
                $hours = (int)$hours;
                $due   = $base->modify('+' . $hours . ' hours');
                if ($due > $close) {
                    continue;
                }
                $out[] = ['length' => $hours, 'unit' => 'hours', 'label' => '+' . $hours . ($hours === 1 ? ' hour' : ' hours'), 'due' => $due];
            }
            return $out;
        }

        // Day loan (or an older request without a mode): more days, same return time of day
        $time = substr(trim((string)($row['return_time'] ?? '')), 0, 5);
        if ($time === '') {
            $time = BookingSchedule::DAY_END;
        }
        $time = max(BookingSchedule::DAY_START, min(BookingSchedule::DAY_END, $time));

        foreach (BookingSchedule::allowedLengths('future', $adviser) as $days) {
            $days  = (int)$days;
            $due   = new DateTimeImmutable($now->modify('+' . $days . ' days')->format('Y-m-d') . ' ' . $time . ':00', $tz);
            $out[] = ['length' => $days, 'unit' => 'days', 'label' => '+' . $days . ($days === 1 ? ' day' : ' days'), 'due' => $due];
        }
        return $out;
    }

    /** "Today, 5:30 PM" / "Tomorrow, 5:00 PM" / "Oct 22, 5:00 PM" */
    public static function label(DateTimeImmutable $due, DateTimeImmutable $now): string
    {
        return fact_day_label($now, $due) . ', ' . fact_time($due->format('H:i:s'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Availability
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Why this extension cannot be granted, or null when it can.
     * Call it with the item's inventory row locked when the answer is about to be acted on.
     */
    public static function denial(mysqli $conn, array $row, DateTimeImmutable $due, DateTimeImmutable $now): ?string
    {
        $item  = (string)$row['equipment_name'];
        $qty   = max(1, (int)($row['borrow_qty'] ?? 1));
        $until = self::label($due, $now);
        $from  = $now->format('Y-m-d H:i:s');
        $to    = $due->format('Y-m-d H:i:s');
        $id    = (int)$row['id'];

        // The item has to still exist in the catalogue
        $total = BookingSchedule::totalStockMap($conn)[$item] ?? null;
        if ($total === null) {
            return '"' . $item . '" is no longer available for borrowing, so it cannot be extended. Please return it to the Admin Office.';
        }

        // Other bookings (today and future) that need these units before the new return time
        $free = $total - BookingSchedule::occupancy($conn, $item, $from, $to, $id);
        if ($free < $qty) {
            return $free <= 0
                ? 'Another booking needs "' . $item . '" before ' . $until . ', so it cannot be kept that long.'
                : 'Another booking needs "' . $item . '" before ' . $until . ', so only ' . $free . ' of your ' . $qty . ' unit(s) could be kept that long.';
        }

        // Equipment cannot stay in a room another faculty has reserved for that time
        $roomId = (int)($row['room_id'] ?? 0);
        if ($roomId > 0 && BookingSchedule::roomConflictMessage($conn, $roomId, $from, $to, (string)$row['faculty_id']) !== null) {
            return 'The room this equipment is in is reserved by another faculty before ' . $until . ', so it cannot stay there that long.';
        }

        return null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Preview (read only) and apply
    // ═════════════════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null the request, only when it belongs to this faculty member */
    private static function fetch(mysqli $conn, int $requestId, string $facultyId, bool $lock): ?array
    {
        $stmt = $conn->prepare(
            'SELECT id, faculty_id, equipment_name, borrow_qty, room, room_id, booking_mode, status,
                    borrow_date, borrow_time, return_date, return_time, extension_count
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

    private static function notDueMessage(array $row): string
    {
        return match ((string)$row['status']) {
            'Approved' => 'You can extend this item once its return time has arrived.',
            'Returned' => 'This item has already been returned.',
            default    => 'This item is not currently on loan, so it cannot be extended.',
        };
    }

    /**
     * What the Extend dialog shows: the loan, and every length with whether it can be granted.
     *
     * @return array<string,mixed>
     */
    public static function preview(mysqli $conn, string $facultyId, int $requestId, bool $adviser): array
    {
        $ready = self::ensureSchema($conn);
        $now   = new DateTimeImmutable('now', self::tz());
        $row   = $ready ? self::fetch($conn, $requestId, $facultyId, false) : null;
        if (!$row) {
            return ['ok' => false, 'error' => 'We could not find that borrowed item.'];
        }
        if (!self::isDue($row, $now)) {
            return ['ok' => false, 'error' => self::notDueMessage($row)];
        }

        $due     = self::dueAt($row);
        $options = [];
        foreach (self::options($row, $adviser, $now) as $o) {
            $why = self::denial($conn, $row, $o['due'], $now);
            $options[] = [
                'length'    => $o['length'],
                'label'     => $o['label'],
                'until'     => self::label($o['due'], $now),
                'available' => $why === null,
                'reason'    => $why ?? '',
            ];
        }

        $out = [
            'ok'        => true,
            'item'      => (string)$row['equipment_name'],
            'qty'       => max(1, (int)$row['borrow_qty']),
            'status'    => (string)$row['status'],
            'due'       => fact_day_label($now, $due, false)
                . (trim((string)($row['return_time'] ?? '')) !== '' ? ', ' . fact_time((string)$row['return_time']) : ''),
            'extended'  => (int)($row['extension_count'] ?? 0),
            'options'   => $options,
        ];
        if (!$options) {
            $out['error'] = self::mode($row) === 'today'
                ? 'It is too late in the day to extend this item (the office closes at ' . fact_time(BookingSchedule::DAY_END . ':00') . '). Please return it to the Admin Office.'
                : 'No extension length is available for this item.';
        }
        return $out;
    }

    /**
     * Grants the extension or says why not. Nothing changes unless it is granted.
     *
     * @return array{ok:bool, error?:string, message?:string, until?:string}
     */
    public static function extend(mysqli $conn, string $facultyId, int $requestId, int $length, bool $adviser): array
    {
        $ready = self::ensureSchema($conn);          // before the transaction
        $now   = new DateTimeImmutable('now', self::tz());
        $peek  = $ready ? self::fetch($conn, $requestId, $facultyId, false) : null;
        if (!$peek) {
            return ['ok' => false, 'error' => 'We could not find that borrowed item.'];
        }

        // Same order as the arbitration engine: lock the item's inventory row first, then decide
        if (!$conn->begin_transaction()) {
            return ['ok' => false, 'error' => 'Something went wrong. Please try again.'];
        }
        try {
            $lock = $conn->prepare('SELECT quantity FROM tbl_inventory WHERE item_name = ? FOR UPDATE');
            if ($lock) {
                $name = (string)$peek['equipment_name'];
                $lock->bind_param('s', $name);
                $lock->execute();
                $lock->close();
            }

            // Re-read under the lock: it may have been returned or extended a moment ago
            $row = self::fetch($conn, $requestId, $facultyId, true);
            if (!$row) {
                $conn->rollback();
                return ['ok' => false, 'error' => 'We could not find that borrowed item.'];
            }
            if (!self::isDue($row, $now)) {
                $conn->rollback();
                return ['ok' => false, 'error' => self::notDueMessage($row)];
            }

            $chosen = null;
            foreach (self::options($row, $adviser, $now) as $o) {
                if ($o['length'] === $length) {
                    $chosen = $o;
                    break;
                }
            }
            if ($chosen === null) {
                $conn->rollback();
                return ['ok' => false, 'error' => 'That extension length is not available for this item.'];
            }

            $why = self::denial($conn, $row, $chosen['due'], $now);
            if ($why !== null) {
                $conn->rollback();
                return ['ok' => false, 'error' => $why];
            }

            $until   = self::label($chosen['due'], $now);
            $note    = 'Borrowing extended until ' . $chosen['due']->format('M j, g:i A') . '.';
            $newDate = $chosen['due']->format('Y-m-d');
            $newTime = $chosen['due']->format('H:i:s');
            $upd = $conn->prepare(
                "UPDATE tbl_requests
                    SET return_date = ?, return_time = ?, status = 'Approved',
                        extension_count = extension_count + 1, reason = ?
                  WHERE id = ? AND faculty_id = ? AND status IN ('Overdue','Approved')"
            );
            if (!$upd) {
                throw new \RuntimeException('prepare update failed: ' . $conn->error);
            }
            $upd->bind_param('sssis', $newDate, $newTime, $note, $requestId, $facultyId);
            $upd->execute();
            $changed = $upd->affected_rows > 0;
            $upd->close();
            if (!$changed) {
                $conn->rollback();
                return ['ok' => false, 'error' => 'This item changed while you were extending it. Please check My Activity and try again.'];
            }

            $conn->commit();
            return ['ok' => true, 'message' => 'Extended until ' . $until . '.', 'until' => $until];
        } catch (\Throwable $e) {
            $conn->rollback();
            error_log('BorrowExtension::extend — ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Something went wrong while extending. Please try again.'];
        }
    }
}
