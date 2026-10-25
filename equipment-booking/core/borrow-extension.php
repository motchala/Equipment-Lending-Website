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
 * LIMIT
 *   At most MAX_EXTENSIONS extensions per loan. After that the item has to be returned.
 *   (Without a limit, availability would be the only brake and a loan nobody else wants could be
 *   extended forever.)
 *
 * WHEN IT IS ALLOWED
 *   The request row keeps its stock (nothing is returned or re-taken), gets the new return moment,
 *   goes back to Approved (so an Overdue item stops blocking new borrow requests), and
 *   extension_count goes up by one. The decision is made inside a transaction that locks the item's
 *   inventory row, like every other stock decision, so two people can never be given the same units.
 *
 * LATENESS IS KEPT, NOT ERASED
 *   An extension can only be granted after the return moment has passed, so the loan WAS late. Moving the
 *   return date must not make it look on time, so the first extension stores the original return moment
 *   and every extension adds how long the loan had already been overdue. "Returned late" (My Activity,
 *   notifications) is judged against the ORIGINAL return moment, and the admin sees "Extended xN".
 *
 * ADMIN TRAIL
 *   Every granted extension writes one row to tbl_request_events (who, what, until when, how overdue it
 *   already was). The admin Activity feed reads that table. (tbl_arbitration_log keeps one row per request,
 *   so it cannot hold a history of several extensions without losing the approval record.)
 *
 * New tbl_requests columns and table (added automatically by ensureSchema()):
 *   extension_count         INT NOT NULL DEFAULT 0
 *   original_return_date    DATE NULL      return date before the first extension
 *   original_return_time    TIME NULL      return time before the first extension
 *   extension_late_seconds  INT UNSIGNED NOT NULL DEFAULT 0   overdue time accumulated across extensions
 *   tbl_request_events      one row per granted extension
 */

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/../../room-reservation/core/faculty-room-quota.php';   // fact_day_label(), fact_time()

final class BorrowExtension
{
    /** Extensions allowed per loan. */
    public const MAX_EXTENSIONS = 2;

    private static ?bool $ready = null;

    /**
     * Adds the extension columns and the events table if they are missing.
     * Call it BEFORE opening a transaction (ALTER ends one).
     */
    public static function ensureSchema(mysqli $conn): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        $defs = [
            'extension_count'        => 'INT NOT NULL DEFAULT 0',
            'original_return_date'   => 'DATE NULL DEFAULT NULL',
            'original_return_time'   => 'TIME NULL DEFAULT NULL',
            'extension_late_seconds' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        ];
        try {
            $columns = static function () use ($conn): array {
                $have = [];
                $res  = $conn->query('SHOW COLUMNS FROM tbl_requests');
                while ($res && ($row = $res->fetch_assoc())) {
                    $have[$row['Field']] = true;
                }
                return $have;
            };
            $have = $columns();
            $adds = [];
            foreach ($defs as $col => $def) {
                if (!isset($have[$col])) {
                    $adds[] = "ADD COLUMN `{$col}` {$def}";
                }
            }
            if ($adds) {
                $conn->query('ALTER TABLE tbl_requests ' . implode(', ', $adds));
                $have = $columns();
            }

            // Same table the admin Activity feed creates (config/admin-activity.php), so either side can be first
            $conn->query(
                "CREATE TABLE IF NOT EXISTS `tbl_request_events` (
                    `id`             int(11) NOT NULL AUTO_INCREMENT,
                    `request_id`     int(11) NOT NULL,
                    `borrower_id`    varchar(50) NOT NULL,
                    `borrower_name`  varchar(100) NOT NULL DEFAULT '',
                    `equipment_name` varchar(100) NOT NULL DEFAULT '',
                    `event`          varchar(30) NOT NULL,
                    `detail`         varchar(255) NOT NULL DEFAULT '',
                    `created_at`     datetime NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `idx_request` (`request_id`),
                    KEY `idx_time` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );

            self::$ready = true;
            foreach ($defs as $col => $_) {
                if (!isset($have[$col])) {
                    self::$ready = false;
                }
            }
        } catch (\Throwable $e) {
            error_log('BorrowExtension::ensureSchema — ' . $e->getMessage());
            self::$ready = false;
        }
        return self::$ready;
    }

    /** True when this loan has used up its extensions. */
    public static function atLimit(array $row): bool
    {
        return (int)($row['extension_count'] ?? 0) >= self::MAX_EXTENSIONS;
    }

    private static function limitMessage(): string
    {
        return 'This item has already been extended ' . self::MAX_EXTENSIONS . ' times, which is the limit. Please return it to the Admin Office.';
    }

    /** "3 hours" / "1 day" — how long a loan had been overdue. */
    private static function duration(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);
        if ($minutes < 60) {
            $n = max(1, $minutes);
            return $n . ($n === 1 ? ' minute' : ' minutes');
        }
        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return $hours . ($hours === 1 ? ' hour' : ' hours');
        }
        $days = intdiv($hours, 24);
        return $days . ($days === 1 ? ' day' : ' days');
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
            'SELECT id, faculty_id, faculty_name, equipment_name, borrow_qty, room, room_id, booking_mode, status,
                    borrow_date, borrow_time, return_date, return_time,
                    extension_count, original_return_date, original_return_time, extension_late_seconds
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
        if (self::atLimit($row)) {
            return ['ok' => false, 'error' => self::limitMessage()];
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
            'used'      => (int)($row['extension_count'] ?? 0),
            'max'       => self::MAX_EXTENSIONS,
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
            if (self::atLimit($row)) {
                $conn->rollback();
                return ['ok' => false, 'error' => self::limitMessage()];
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

            // The first extension remembers when the loan was really due; later ones keep it
            $hadOriginal = (string)($row['original_return_date'] ?? '') !== '';
            $origDate    = $hadOriginal ? (string)$row['original_return_date'] : (string)$row['return_date'];
            $origTime    = $hadOriginal
                ? ((string)($row['original_return_time'] ?? '') !== '' ? (string)$row['original_return_time'] : null)
                : ((string)($row['return_time'] ?? '') !== '' ? (string)$row['return_time'] : null);
            // How long the loan had already been overdue when this extension was granted
            $lateNow = max(0, $now->getTimestamp() - self::dueAt($row)->getTimestamp());
            $nth     = (int)($row['extension_count'] ?? 0) + 1;

            $upd = $conn->prepare(
                "UPDATE tbl_requests
                    SET return_date = ?, return_time = ?, status = 'Approved',
                        extension_count = extension_count + 1, reason = ?,
                        original_return_date = ?, original_return_time = ?,
                        extension_late_seconds = extension_late_seconds + ?
                  WHERE id = ? AND faculty_id = ? AND status IN ('Overdue','Approved')"
            );
            if (!$upd) {
                throw new \RuntimeException('prepare update failed: ' . $conn->error);
            }
            $upd->bind_param('sssssiis', $newDate, $newTime, $note, $origDate, $origTime, $lateNow, $requestId, $facultyId);
            $upd->execute();
            $changed = $upd->affected_rows > 0;
            $upd->close();
            if (!$changed) {
                $conn->rollback();
                return ['ok' => false, 'error' => 'This item changed while you were extending it. Please check My Activity and try again.'];
            }

            // Admin trail (same transaction: it exists if and only if the extension does)
            $origLabel = (new DateTimeImmutable($origDate . ' ' . ($origTime ?? '23:59:59'), self::tz()))->format($origTime !== null ? 'M j, g:i A' : 'M j');
            $detail = 'Until ' . $chosen['due']->format('M j, g:i A') . ' (' . $chosen['label'] . ', extension ' . $nth . ' of ' . self::MAX_EXTENSIONS . '). '
                . 'Was overdue by ' . self::duration($lateNow) . '; originally due ' . $origLabel . '.';
            $ev = $conn->prepare(
                "INSERT INTO tbl_request_events (request_id, borrower_id, borrower_name, equipment_name, `event`, detail, created_at)
                 VALUES (?, ?, ?, ?, 'extended', ?, NOW())"
            );
            if ($ev) {
                $bid    = (string)$row['faculty_id'];
                $bname  = (string)($row['faculty_name'] ?? '');
                $item   = (string)$row['equipment_name'];
                $detail = function_exists('mb_substr') ? mb_substr($detail, 0, 250) : substr($detail, 0, 250);
                $ev->bind_param('issss', $requestId, $bid, $bname, $item, $detail);
                $ev->execute();
                $ev->close();
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
