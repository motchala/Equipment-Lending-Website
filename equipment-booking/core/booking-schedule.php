<?php

declare(strict_types=1);

/**
 * booking-schedule.php
 *
 * Scheduling rules for equipment borrowing (the Borrow modal on the faculty Equipment tab).
 *
 *  - Today bookings:  hourly start time (7:00 AM through 8:00 PM) + duration in hours
 *                     (1 / 2 / 3 / 6). No request letter.
 *  - Later bookings:  borrow date + start time + duration in hours (1 / 2 / 3 / 6)
 *                     + quantity + an optional (recommended) request letter.
 *  - Later bookings are checked against the SCHEDULE (overlapping approved
 *    bookings versus the item's total stock), not against what happens to be on
 *    the shelf right now. Today bookings need the item to be available right now.
 *  - Bookings end by 9:00 PM and can start at most MAX_ADVANCE_DAYS days from today;
 *    Sundays are not bookable.
 *
 * "Total stock" is the stock the admin set. tbl_inventory.quantity only holds what is
 * on the shelf right now (approvals subtract, returns add back), so the total is
 * quantity + the units currently out on loan.
 *
 * New tbl_requests columns (added automatically by ensureSchema(), or run
 * database/migrations/2026_10_borrow_scheduling.sql yourself):
 *   borrow_time, return_time, borrow_qty, room_id, booking_mode, stock_applied
 * (the quantity column is deliberately NOT called "quantity": the arbitration engine
 *  joins tbl_inventory.quantity and the two names would collide.)
 */

date_default_timezone_set('Asia/Manila');

final class BookingSchedule
{
    // ── Tunable rules ────────────────────────────────────────────────────────
    public const DAY_START            = '07:00';   // earliest start time
    public const LAST_START            = '20:00';   // latest start time
    public const DAY_END              = '21:00';   // bookings must end by this time
    public const SLOT_MINUTES         = 60;        // start-time step
    public const MAX_ADVANCE_DAYS     = 14;        // how far ahead a booking may start (2 weeks)
    public const IMMEDIATE_GRACE_MIN  = 15;        // a "today" booking starting within this many minutes takes stock right away
    public const DURATION_HOURS       = [1, 2, 3, 6];

    private static ?bool $ready = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Schema
    // ═════════════════════════════════════════════════════════════════════════

    /** Adds the scheduling columns if they are missing. Returns true when they all exist. */
    public static function ensureSchema(mysqli $conn): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        $defs = [
            'borrow_time'   => 'TIME NULL DEFAULT NULL',
            'return_time'   => 'TIME NULL DEFAULT NULL',
            'borrow_qty'    => 'INT NOT NULL DEFAULT 1',
            'room_id'       => 'INT NULL DEFAULT NULL',
            'booking_mode'  => 'VARCHAR(10) NULL DEFAULT NULL',
            'stock_applied' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'batch_id'      => 'CHAR(36) NULL DEFAULT NULL',
            'bundle_claim_token' => 'VARCHAR(64) NULL DEFAULT NULL',
        ];

        try {
            $have = [];
            $res = $conn->query('SHOW COLUMNS FROM tbl_requests');
            while ($res && ($row = $res->fetch_assoc())) {
                $have[$row['Field']] = true;
            }

            $adds = [];
            foreach ($defs as $col => $def) {
                if (!isset($have[$col])) {
                    $adds[] = "ADD COLUMN `{$col}` {$def}";
                }
            }
            if ($adds) {
                $conn->query('ALTER TABLE tbl_requests ' . implode(', ', $adds));
                // re-check
                $have = [];
                $res = $conn->query('SHOW COLUMNS FROM tbl_requests');
                while ($res && ($row = $res->fetch_assoc())) {
                    $have[$row['Field']] = true;
                }
            }

            self::$ready = true;
            foreach ($defs as $col => $_) {
                if (!isset($have[$col])) {
                    self::$ready = false;
                }
            }

            try {
                $indexes = [];
                $indexResult = $conn->query('SHOW INDEX FROM tbl_requests');
                while ($indexResult && ($indexRow = $indexResult->fetch_assoc())) {
                    $indexes[(string)$indexRow['Key_name']] = true;
                }
                $indexAdds = [];
                if (!isset($indexes['idx_batch_id'])) {
                    $indexAdds[] = 'ADD KEY `idx_batch_id` (`batch_id`)';
                }
                if (!isset($indexes['idx_bundle_claim_token'])) {
                    $indexAdds[] = 'ADD KEY `idx_bundle_claim_token` (`bundle_claim_token`)';
                }
                if ($indexAdds) {
                    $conn->query('ALTER TABLE tbl_requests ' . implode(', ', $indexAdds));
                }
            } catch (\Throwable $e) {
                error_log('BookingSchedule::ensureSchema index setup — ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            error_log('BookingSchedule::ensureSchema — ' . $e->getMessage());
            self::$ready = false;
        }

        return self::$ready;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Housekeeping (runs on dashboard loads / polls)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Housekeeping on every dashboard load / poll:
     *   - a loan that is OUT (On Loan) and past its return moment becomes Overdue;
     *   - an Approved booking nobody collected, whose borrow period is over, ends as a no-show;
     *   - Approved bookings that start now take their units off the shelf.
     */
    public static function tick(mysqli $conn): void
    {
        $ready = self::ensureSchema($conn);

        try {
            self::markOverdue($conn, $ready);
            self::expireNoShows($conn, $ready);
            if ($ready) {
                self::activateDue($conn);
            }
        } catch (\Throwable $e) {
            error_log('BookingSchedule::tick - ' . $e->getMessage());
        }
    }

    /**
     * SQL condition (no leading AND) that is true when the return moment has passed.
     * Time-aware when the booking columns exist: the return time on the return date, or the end of
     * that day when no time was set. Always three string parameters: today, today, now.
     */
    private static function pastReturnSql(bool $ready): string
    {
        return $ready
            ? '(return_date < ? OR (return_date = ? AND return_time IS NOT NULL AND return_time < ?))'
            : '(return_date < ? AND ? = ? )';
    }

    /**
     * Only a loan that is physically OUT (status On Loan: the admin scanned the Claim QR) can be
     * overdue. Before the On Loan status existed this flagged Approved rows instead, which meant an
     * item nobody collected showed as overdue while a real unreturned loan never did.
     */
    public static function markOverdue(mysqli $conn, ?bool $ready = null): void
    {
        $ready = $ready ?? self::ensureSchema($conn);
        $today = date('Y-m-d');
        $now   = date('H:i:s');
        $stmt  = $conn->prepare("UPDATE tbl_requests SET status = 'Overdue' WHERE status = 'On Loan' AND " . self::pastReturnSql($ready));
        if ($stmt) {
            // Without the booking columns only the date can be compared; the 2nd/3rd values are equal so the clause reduces to "return_date < today"
            $a = $today; $b = $ready ? $today : 'x'; $c = $ready ? $now : 'x';
            $stmt->bind_param('sss', $a, $b, $c);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * An Approved booking that was never collected and whose borrow period is now over is a
     * no-show: it is Cancelled with that reason (not "Overdue": nothing is out), and any units it
     * had taken off the shelf go back.
     *
     * @return int how many bookings were closed by this call
     */
    public static function expireNoShows(mysqli $conn, ?bool $ready = null): int
    {
        $ready  = $ready ?? self::ensureSchema($conn);
        $today  = date('Y-m-d');
        $now    = date('H:i:s');
        $reason = 'Not collected – the borrow period ended before it was picked up.';
        $rule   = 'rule_no_show';
        $closed = 0;

        $sel = $conn->prepare(
            "SELECT id, equipment_name, borrow_qty, stock_applied FROM tbl_requests
              WHERE status = 'Approved' AND " . self::pastReturnSql($ready)
        );
        if (!$sel) {
            return 0;
        }
        $a = $today; $b = $ready ? $today : 'x'; $c = $ready ? $now : 'x';
        $sel->bind_param('sss', $a, $b, $c);
        $sel->execute();
        $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
        $sel->close();

        foreach ($rows as $row) {
            $id  = (int)$row['id'];
            $upd = $conn->prepare(
                "UPDATE tbl_requests SET status = 'Cancelled', arbitration_rule = ?, reason = ?, stock_applied = 0
                  WHERE id = ? AND status = 'Approved'"
            );
            if (!$upd) {
                continue;
            }
            $upd->bind_param('ssi', $rule, $reason, $id);
            $upd->execute();
            $won = $upd->affected_rows === 1;
            $upd->close();
            if (!$won) {
                continue;   // collected or cancelled a moment ago
            }
            $closed++;
            if ((int)($row['stock_applied'] ?? 0) === 1) {
                $back = $conn->prepare('UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?');
                if ($back) {
                    $q    = max(1, (int)$row['borrow_qty']);
                    $name = (string)$row['equipment_name'];
                    $back->bind_param('is', $q, $name);
                    $back->execute();
                    $back->close();
                }
            }
        }
        return $closed;
    }

    /**
     * Approved bookings that were scheduled for later take their units off the shelf
     * the moment they start (until then the equipment stays available to others).
     */
    public static function activateDue(mysqli $conn): void
    {
        if (!self::ensureSchema($conn)) {
            return;
        }
        $nowTs = date('Y-m-d H:i:s');
        try {
            $stmt = $conn->prepare(
                "SELECT id, equipment_name, borrow_qty
                   FROM tbl_requests
                  WHERE status = 'Approved' AND stock_applied = 0
                    AND booking_mode IN ('today','future')
                    AND TIMESTAMP(borrow_date, COALESCE(borrow_time, '00:00:00')) <= ?"
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('s', $nowTs);
            $stmt->execute();
            $due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($due as $row) {
                $claim = $conn->prepare('UPDATE tbl_requests SET stock_applied = 1 WHERE id = ? AND stock_applied = 0');
                if (!$claim) {
                    continue;
                }
                $rid = (int)$row['id'];
                $claim->bind_param('i', $rid);
                $claim->execute();
                $won = $claim->affected_rows > 0;   // idempotent: only one caller applies the stock
                $claim->close();
                if (!$won) {
                    continue;
                }
                $q   = max(1, (int)$row['borrow_qty']);
                $dec = $conn->prepare('UPDATE tbl_inventory SET quantity = GREATEST(quantity - ?, 0) WHERE item_name = ?');
                if ($dec) {
                    $name = (string)$row['equipment_name'];
                    $dec->bind_param('is', $q, $name);
                    $dec->execute();
                    $dec->close();
                }
            }
        } catch (\Throwable $e) {
            error_log('BookingSchedule::activateDue — ' . $e->getMessage());
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Rooms (same tables / ordering as the Facilities section)
    // ═════════════════════════════════════════════════════════════════════════

    private static function ordinalFloor(int $n): string
    {
        if ($n === 0) {
            return 'Ground Floor';
        }
        $suffix = 'th';
        if ($n % 100 < 11 || $n % 100 > 13) {
            $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th';
        }
        return $n . $suffix . ' Floor';
    }

    /**
     * Non-archived rooms grouped building > floor > room.
     * $availableOnly = true leaves out rooms the admin set to Maintenance / Not Bookable
     * (the Borrow Request room picker only offers rooms equipment can actually go to).
     * @return array<int, array<string,mixed>>
     */
    public static function roomsTree(mysqli $conn, bool $availableOnly = false): array
    {
        $out = [];
        $statusSql = $availableOnly ? "AND r.status = 'Available'" : '';
        $res = $conn->query(
            "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label, r.status,
                    b.building_id, b.building_key, b.image_path AS building_image,
                    b.name AS building_name, b.wing, c.campus_name
               FROM tbl_rooms r
               JOIN tbl_buildings b ON b.building_id = r.building_id
               JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
              WHERE r.is_archived = 0 $statusSql
              ORDER BY b.campus_id ASC, b.sort_order ASC, b.building_id ASC,
                       r.floor_number ASC, r.sort_order ASC, r.room_id ASC"
        );
        while ($res && ($row = $res->fetch_assoc())) {
            $bid = (int)$row['building_id'];
            $fn  = (int)$row['floor_number'];
            if (!isset($out[$bid])) {
                $out[$bid] = [
                    'building_id' => $bid,
                    'name'        => $row['building_name'],
                    'key'         => (string)($row['building_key'] ?? ''),
                    'image'       => (string)($row['building_image'] ?? ''),
                    'wing'        => (string)($row['wing'] ?? ''),
                    'campus'      => (string)$row['campus_name'],
                    'floors'      => [],
                ];
            }
            if (!isset($out[$bid]['floors'][$fn])) {
                $out[$bid]['floors'][$fn] = [
                    'floor' => $fn,
                    'label' => !empty($row['floor_label']) ? $row['floor_label'] : self::ordinalFloor($fn),
                    'rooms' => [],
                ];
            }
            $out[$bid]['floors'][$fn]['rooms'][] = [
                'room_id' => (int)$row['room_id'],
                'name'    => $row['room_name'],
                'status'  => $row['status'],
            ];
        }
        // JSON-friendly (lists, not maps)
        foreach ($out as &$b) {
            $b['floors'] = array_values($b['floors']);
        }
        unset($b);
        return array_values($out);
    }

    /** One non-archived room with its building + floor names, or null. */
    public static function findRoom(mysqli $conn, int $room_id): ?array
    {
        if ($room_id <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label, r.status,
                    b.name AS building_name
               FROM tbl_rooms r
               JOIN tbl_buildings b ON b.building_id = r.building_id
              WHERE r.room_id = ? AND r.is_archived = 0
              LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $row['floor_text'] = !empty($row['floor_label']) ? $row['floor_label'] : self::ordinalFloor((int)$row['floor_number']);
        return $row;
    }

    /** Text stored in tbl_requests.room (always built from the live room record). */
    public static function roomLabel(array $room): string
    {
        $label = $room['room_name'] . ' · ' . $room['building_name'] . ' · ' . $room['floor_text'];
        return mb_substr($label, 0, 100);
    }

    /**
     * Turns what the form sent into a room: a live room id, or "outside" (no room).
     * @return array{ok:bool,error?:string,label?:string,id?:?int}
     */
    public static function resolveRoom(mysqli $conn, string $input): array
    {
        $input = trim($input);
        if ($input === 'outside') {
            return ['ok' => true, 'label' => 'Outside', 'id' => null];
        }
        $row = ctype_digit($input) ? self::findRoom($conn, (int)$input) : null;
        if (!$row) {
            return ['ok' => false, 'error' => 'Please choose a room.'];
        }
        if ($row['status'] === 'Maintenance') {
            return ['ok' => false, 'error' => 'That room is under maintenance. Please choose another room.'];
        }
        if ($row['status'] === 'Not Bookable') {
            return ['ok' => false, 'error' => 'That room is not available for equipment. Please choose another room.'];
        }
        return ['ok' => true, 'label' => self::roomLabel($row), 'id' => (int)$row['room_id']];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Room reservations (tbl_room_reservations) versus equipment going to a room
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Approved room reservations that overlap [startTs, endTs).
     * @return array<int, array{room_id:int,faculty_id:string,start:string,end:string}>
     */
    public static function reservationsIn(mysqli $conn, string $startTs, string $endTs): array
    {
        $stmt = $conn->prepare(
            "SELECT room_id, faculty_id,
                    TIMESTAMP(reservation_date, start_time) AS s_ts,
                    TIMESTAMP(reservation_date, end_time)   AS e_ts
               FROM tbl_room_reservations
              WHERE status = 'Approved'
                AND TIMESTAMP(reservation_date, start_time) < ?
                AND TIMESTAMP(reservation_date, end_time)   > ?"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ss', $endTs, $startTs);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => [
            'room_id'    => (int)$r['room_id'],
            'faculty_id' => (string)$r['faculty_id'],
            'start'      => (string)$r['s_ts'],
            'end'        => (string)$r['e_ts'],
        ], $rows);
    }

    /**
     * Rooms that are reserved by SOMEONE ELSE during the window (equipment cannot go there),
     * and rooms the given faculty reserved themselves (good suggestions).
     * 'info' says WHEN each blocked room is taken (time range only, never who) so the room
     * picker can show "Reserved 9:00 - 11:00 AM" on hover.
     * @return array{reserved:int[],mine:int[],info:array<int,array<int,array{start:string,end:string}>>}
     */
    public static function roomsByReservation(mysqli $conn, string $startTs, string $endTs, string $facultyId): array
    {
        $reserved = [];
        $mine     = [];
        $info     = [];
        foreach (self::reservationsIn($conn, $startTs, $endTs) as $r) {
            if ($r['faculty_id'] === $facultyId) {
                $mine[$r['room_id']] = true;
            } else {
                $reserved[$r['room_id']] = true;
                $info[$r['room_id']][] = ['start' => $r['start'], 'end' => $r['end']];
            }
        }
        // a room both reserved by me and by someone else stays blocked
        return [
            'reserved' => array_keys($reserved),
            'mine'     => array_values(array_diff(array_keys($mine), array_keys($reserved))),
            'info'     => (object)$info,
        ];
    }

    /** Error text when another faculty holds a reservation on this room during the window, else null. */
    public static function roomConflictMessage(mysqli $conn, int $roomId, string $startTs, string $endTs, string $facultyId): ?string
    {
        $map = self::roomsByReservation($conn, $startTs, $endTs, $facultyId);
        if (!in_array($roomId, $map['reserved'], true)) {
            return null;
        }
        $room = self::findRoom($conn, $roomId);
        return ($room ? $room['room_name'] : 'That room') . ' is reserved by another faculty for that time. Choose another room or time.';
    }

    /**
     * The faculty member's own approved room reservations that are still running or coming up
     * (within the booking window): the room picker recommends these first (using now / later today /
     * another day).
     * @return array<int, array<string,mixed>>
     */
    public static function myUpcomingReservations(mysqli $conn, string $facultyId, int $limit = 12): array
    {
        $stmt = $conn->prepare(
            "SELECT rr.room_id, r.room_name, b.name AS building_name,
                    CONCAT(rr.reservation_date, ' ', rr.start_time) AS start_at,
                    CONCAT(rr.reservation_date, ' ', rr.end_time)   AS end_at
               FROM tbl_room_reservations rr
               JOIN tbl_rooms     r ON r.room_id     = rr.room_id AND r.is_archived = 0
               JOIN tbl_buildings b ON b.building_id = r.building_id
              WHERE rr.faculty_id = ? AND rr.status = 'Approved'
                AND TIMESTAMP(rr.reservation_date, rr.end_time) > NOW()
                AND rr.reservation_date <= DATE_ADD(CURDATE(), INTERVAL " . (int)self::MAX_ADVANCE_DAYS . " DAY)
              ORDER BY rr.reservation_date ASC, rr.start_time ASC
              LIMIT " . (int)$limit
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $facultyId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => [
            'room_id'  => (int)$r['room_id'],
            'name'     => $r['room_name'],
            'building' => $r['building_name'],
            'start'    => $r['start_at'],
            'end'      => $r['end_at'],
        ], $rows);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Stock & schedule
    // ═════════════════════════════════════════════════════════════════════════

    /** @return array<string,int> item_name => total stock (shelf + out on loan) */
    public static function totalStockMap(mysqli $conn): array
    {
        $map = [];
        if (self::ensureSchema($conn)) {
            $sql = "SELECT i.item_name, i.quantity + COALESCE(x.out_qty, 0) AS total
                      FROM tbl_inventory i
                      LEFT JOIN (SELECT equipment_name, SUM(borrow_qty) AS out_qty
                                   FROM tbl_requests
                                  WHERE status IN ('Approved','On Loan','Overdue') AND stock_applied = 1
                                  GROUP BY equipment_name) x ON x.equipment_name = i.item_name
                     WHERE i.is_archived = 0";
        } else {
            $sql = "SELECT i.item_name, i.quantity + COALESCE(x.out_qty, 0) AS total
                      FROM tbl_inventory i
                      LEFT JOIN (SELECT equipment_name, COUNT(*) AS out_qty
                                   FROM tbl_requests
                                  WHERE status IN ('Approved','On Loan','Overdue')
                                  GROUP BY equipment_name) x ON x.equipment_name = i.item_name
                     WHERE i.is_archived = 0";
        }
        $res = $conn->query($sql);
        while ($res && ($row = $res->fetch_assoc())) {
            $map[$row['item_name']] = max(0, (int)$row['total']);
        }
        return $map;
    }

    public static function totalStock(mysqli $conn, string $item): int
    {
        $map = self::totalStockMap($conn);
        return $map[$item] ?? 0;
    }

    /** Units already promised to other approved bookings that overlap [startTs, endTs). */
    public static function occupancy(mysqli $conn, string $item, string $startTs, string $endTs, int $excludeId = 0): int
    {
        $tomorrow = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(borrow_qty), 0) AS used
               FROM tbl_requests
              WHERE equipment_name = ? AND id <> ?
                AND (
                      (status IN ('Approved','On Loan')
                        AND TIMESTAMP(borrow_date, COALESCE(borrow_time, '00:00:00')) < ?
                        AND TIMESTAMP(return_date, COALESCE(return_time, '23:59:59')) > ?)
                   OR (status = 'Overdue' AND ? < ?)
                    )"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('sissss', $item, $excludeId, $endTs, $startTs, $startTs, $tomorrow);
        $stmt->execute();
        $used = (int)($stmt->get_result()->fetch_assoc()['used'] ?? 0);
        $stmt->close();
        return $used;
    }

    public static function freeUnits(mysqli $conn, string $item, string $startTs, string $endTs, int $excludeId = 0): int
    {
        $total = self::totalStock($conn, $item);
        return max(0, $total - self::occupancy($conn, $item, $startTs, $endTs, $excludeId));
    }

    /** Current shelf quantity (null when the item does not exist / is archived). */
    public static function shelfQuantity(mysqli $conn, string $item): ?int
    {
        $stmt = $conn->prepare('SELECT quantity FROM tbl_inventory WHERE item_name = ? AND is_archived = 0 LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $item);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['quantity'] : null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Parsing & validation
    // ═════════════════════════════════════════════════════════════════════════

    /** Allowed booking durations, in hours, for both today and future dates. */
    public static function allowedLengths(string $mode): array
    {
        return self::DURATION_HOURS;
    }

    /**
     * Turns the form values into a booking window (no database access).
     * @return array{ok:bool,error?:string,data?:array<string,mixed>}
     */
    public static function window(string $mode, string $date, string $time, int $length): array
    {
        if (!in_array($mode, ['today', 'future'], true)) {
            return ['ok' => false, 'error' => 'Please choose when you need the equipment.'];
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return ['ok' => false, 'error' => 'Please choose a start time.'];
        }
        if ($time < self::DAY_START || $time > self::LAST_START) {
            return ['ok' => false, 'error' => 'Start time must be between ' . self::DAY_START . ' and ' . self::LAST_START . '.'];
        }
        if (substr($time, 3, 2) !== '00') {
            return ['ok' => false, 'error' => 'Start times are available on the hour only.'];
        }
        if (!in_array($length, self::allowedLengths($mode), true)) {
            return ['ok' => false, 'error' => 'Please choose a duration of 1, 2, 3, or 6 hours.'];
        }

        $today = date('Y-m-d');
        if ($mode === 'today') {
            if ((int)date('w') === 0) {
                return ['ok' => false, 'error' => 'Borrowing equipment today is unavailable on Sundays. Please choose a later date.'];
            }
            if (date('H:i') > self::LAST_START) {
                return ['ok' => false, 'error' => 'Same-day borrowing is unavailable after 8:00 PM. Please choose a later date.'];
            }
            $startDate = $today;
            $startTs   = strtotime($startDate . ' ' . $time . ':00');
            if ($startTs < time() - 10 * 60) {
                return ['ok' => false, 'error' => 'That start time has already passed.'];
            }
            $endTs = $startTs + $length * 3600;
            if ($endTs > strtotime($today . ' ' . self::DAY_END . ':00')) {
                return ['ok' => false, 'error' => 'Today bookings must end by ' . self::DAY_END . '.'];
            }
        } else {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dateParts) ||
                !checkdate((int)$dateParts[2], (int)$dateParts[3], (int)$dateParts[1])) {
                return ['ok' => false, 'error' => 'Please choose a borrow date.'];
            }
            $startDate = $date;
            $tomorrow  = date('Y-m-d', strtotime('+1 day'));
            $latest    = date('Y-m-d', strtotime('+' . self::MAX_ADVANCE_DAYS . ' days'));
            if ($startDate < $tomorrow) {
                return ['ok' => false, 'error' => 'Later bookings start tomorrow or after. Use "Today" for same-day use.'];
            }
            if ($startDate > $latest) {
                return ['ok' => false, 'error' => 'You can only book up to two weeks ahead (latest start: ' . date('M j, Y', strtotime($latest)) . ').'];
            }
            if ((int)date('w', strtotime($startDate)) === 0) {
                return ['ok' => false, 'error' => 'Equipment borrowing is unavailable on Sundays. Please choose another date.'];
            }
            $startTs = strtotime($startDate . ' ' . $time . ':00');
            $endTs   = $startTs + $length * 3600;
        }
        if ($endTs > strtotime($startDate . ' ' . self::DAY_END . ':00')) {
            return ['ok' => false, 'error' => 'Bookings must end by ' . self::DAY_END . '.'];
        }

        return ['ok' => true, 'data' => [
            'mode'        => $mode,
            'borrow_date' => date('Y-m-d', $startTs),
            'borrow_time' => date('H:i:s', $startTs),
            'return_date' => date('Y-m-d', $endTs),
            'return_time' => date('H:i:s', $endTs),
            'start_ts'    => date('Y-m-d H:i:s', $startTs),
            'end_ts'      => date('Y-m-d H:i:s', $endTs),
            'immediate'   => $mode === 'today' && $startTs <= time() + self::IMMEDIATE_GRACE_MIN * 60,
        ]];
    }

    /**
     * Full server-side validation for one item: window + quantity + stock rules.
     * @return array{ok:bool,error?:string,data?:array<string,mixed>}
     */
    public static function parse(mysqli $conn, array $post, string $item, int $qty): array
    {
        $mode   = (string)($post['booking_mode'] ?? '');
        $length = (int)($post['duration'] ?? 0);
        $w = self::window($mode, trim((string)($post['borrow_date'] ?? '')), trim((string)($post['start_time'] ?? '')), $length);
        if (!$w['ok']) {
            return $w;
        }

        $shelf = self::shelfQuantity($conn, $item);
        if ($shelf === null) {
            return ['ok' => false, 'error' => '"' . $item . '" is not available for borrowing.'];
        }

        if ($mode === 'today') {
            if ($shelf < $qty) {
                $available = max(0, $shelf);
                return ['ok' => false, 'error' => '"' . $item . '" only has ' . $available . ' unit(s) available right now.'];
            }
        } else {
            $total = self::totalStock($conn, $item);
            if ($qty < 1) {
                return ['ok' => false, 'error' => 'Quantity must be at least 1.'];
            }
            if ($qty > $total) {
                return ['ok' => false, 'error' => 'Only ' . $total . ' of "' . $item . '" exist in stock.'];
            }
        }

        $w['data']['qty'] = $qty;
        return $w;
    }

    /** First start date (on/after $fromDate, within the booking window) where $qty units are free. */
    public static function earliestFreeDate(mysqli $conn, string $item, int $qty, string $time, int $lengthHours, string $fromDate): ?string
    {
        $latest = date('Y-m-d', strtotime('+' . self::MAX_ADVANCE_DAYS . ' days'));
        for ($d = $fromDate; $d <= $latest; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ((int)date('w', strtotime($d)) === 0) {
                continue;
            }
            $start = strtotime($d . ' ' . $time . ':00');
            $end   = $start + $lengthHours * 3600;
            if (self::freeUnits($conn, $item, date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)) >= $qty) {
                return $d;
            }
        }
        return null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Display helpers
    // ═════════════════════════════════════════════════════════════════════════

    public static function fmtTime(?string $t): string
    {
        if ($t === null || $t === '') {
            return '';
        }
        $ts = strtotime('2000-01-01 ' . $t);
        return $ts ? date('g:i A', $ts) : '';
    }
}
