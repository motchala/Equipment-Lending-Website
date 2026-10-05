<?php

declare(strict_types=1);

/**
 * booking-schedule.php
 *
 * Scheduling rules for equipment borrowing (Borrow Request modal).
 *
 *  - Today bookings:  start hour + duration in hours (1 / 2 / 3 / 5; Organization
 *                     Advisers may use a custom length).
 *  - Later bookings:  borrow date + start hour + duration in days (1 / 2 / 3;
 *                     Organization Advisers also get 5) + quantity.
 *  - Later bookings are checked against the SCHEDULE (overlapping approved
 *    bookings versus the item's total stock), not against what happens to be on
 *    the shelf right now. Today bookings need the item to be available right now.
 *  - A booking can start at most MAX_ADVANCE_DAYS days from today.
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
    public const DAY_END              = '21:00';   // bookings must end by this time (today bookings)
    public const SLOT_MINUTES         = 30;        // start-time step
    public const MAX_ADVANCE_DAYS     = 21;        // how far ahead a booking may start (3 weeks)
    public const IMMEDIATE_GRACE_MIN  = 15;        // a "today" booking starting within this many minutes takes stock right away
    public const TODAY_HOURS          = [1, 2, 3, 5];
    public const ADVISER_MAX_HOURS    = 12;
    public const FUTURE_DAYS          = [1, 2, 3];
    public const ADVISER_FUTURE_DAYS  = [1, 2, 3, 5];

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
        } catch (\Throwable $e) {
            error_log('BookingSchedule::ensureSchema — ' . $e->getMessage());
            self::$ready = false;
        }

        return self::$ready;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Housekeeping (runs on dashboard loads / polls)
    // ═════════════════════════════════════════════════════════════════════════

    /** Time-aware overdue marking + start-of-booking stock activation. */
    public static function tick(mysqli $conn): void
    {
        $ready = self::ensureSchema($conn);
        $today = date('Y-m-d');
        $now   = date('H:i:s');

        try {
            if ($ready) {
                $stmt = $conn->prepare(
                    "UPDATE tbl_requests SET status = 'Overdue'
                      WHERE status = 'Approved'
                        AND (return_date < ?
                             OR (return_date = ? AND return_time IS NOT NULL AND return_time < ?))"
                );
                if ($stmt) {
                    $stmt->bind_param('sss', $today, $today, $now);
                    $stmt->execute();
                    $stmt->close();
                }
                self::activateDue($conn);
            } else {
                $stmt = $conn->prepare("UPDATE tbl_requests SET status = 'Overdue' WHERE status = 'Approved' AND return_date < ?");
                if ($stmt) {
                    $stmt->bind_param('s', $today);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (\Throwable $e) {
            error_log('BookingSchedule::tick — ' . $e->getMessage());
        }
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
     * @return array<int, array<string,mixed>>
     */
    public static function roomsTree(mysqli $conn): array
    {
        $out = [];
        $res = $conn->query(
            "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label, r.status,
                    b.building_id, b.name AS building_name, b.wing, c.campus_name
               FROM tbl_rooms r
               JOIN tbl_buildings b ON b.building_id = r.building_id
               JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
              WHERE r.is_archived = 0
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
                                  WHERE status IN ('Approved','Overdue') AND stock_applied = 1
                                  GROUP BY equipment_name) x ON x.equipment_name = i.item_name
                     WHERE i.is_archived = 0";
        } else {
            $sql = "SELECT i.item_name, i.quantity + COALESCE(x.out_qty, 0) AS total
                      FROM tbl_inventory i
                      LEFT JOIN (SELECT equipment_name, COUNT(*) AS out_qty
                                   FROM tbl_requests
                                  WHERE status IN ('Approved','Overdue')
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
                      (status = 'Approved'
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

    public static function isAdviserRole(string $role): bool
    {
        return $role === 'Organization Adviser';
    }

    /** Allowed lengths for the mode: hours (today) or days (later). */
    public static function allowedLengths(string $mode, bool $adviser): array
    {
        if ($mode === 'today') {
            return $adviser ? range(1, self::ADVISER_MAX_HOURS) : self::TODAY_HOURS;
        }
        return $adviser ? self::ADVISER_FUTURE_DAYS : self::FUTURE_DAYS;
    }

    /**
     * Turns the form values into a booking window (no database access).
     * @return array{ok:bool,error?:string,data?:array<string,mixed>}
     */
    public static function window(string $mode, string $date, string $time, int $length, bool $adviser): array
    {
        if (!in_array($mode, ['today', 'future'], true)) {
            return ['ok' => false, 'error' => 'Please choose when you need the equipment.'];
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return ['ok' => false, 'error' => 'Please choose a start time.'];
        }
        if ($time < self::DAY_START || $time >= self::DAY_END) {
            return ['ok' => false, 'error' => 'Start time must be between ' . self::DAY_START . ' and ' . self::DAY_END . '.'];
        }
        if (!in_array($length, self::allowedLengths($mode, $adviser), true)) {
            return ['ok' => false, 'error' => $mode === 'today'
                ? 'That length of time is not allowed for your account.'
                : 'That number of days is not allowed for your account.'];
        }

        $today = date('Y-m-d');
        if ($mode === 'today') {
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
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
                return ['ok' => false, 'error' => 'Please choose a borrow date.'];
            }
            $startDate = $date;
            $tomorrow  = date('Y-m-d', strtotime('+1 day'));
            $latest    = date('Y-m-d', strtotime('+' . self::MAX_ADVANCE_DAYS . ' days'));
            if ($startDate < $tomorrow) {
                return ['ok' => false, 'error' => 'Later bookings start tomorrow or after. Use "Today" for same-day use.'];
            }
            if ($startDate > $latest) {
                return ['ok' => false, 'error' => 'You can only book up to ' . (self::MAX_ADVANCE_DAYS / 7) . ' weeks ahead (latest start: ' . date('M j, Y', strtotime($latest)) . ').'];
            }
            $startTs = strtotime($startDate . ' ' . $time . ':00');
            $endTs   = strtotime('+' . $length . ' days', $startTs);
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
    public static function parse(mysqli $conn, array $post, bool $adviser, string $item, int $qty): array
    {
        $mode   = (string)($post['booking_mode'] ?? '');
        $length = (int)($post['duration'] ?? 0);
        $w = self::window($mode, trim((string)($post['borrow_date'] ?? '')), trim((string)($post['start_time'] ?? '')), $length, $adviser);
        if (!$w['ok']) {
            return $w;
        }

        $shelf = self::shelfQuantity($conn, $item);
        if ($shelf === null) {
            return ['ok' => false, 'error' => '"' . $item . '" is not available for borrowing.'];
        }

        if ($mode === 'today') {
            $qty = 1;
            if ($shelf < 1) {
                return ['ok' => false, 'error' => '"' . $item . '" is out right now. Book it for a later date instead.'];
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
    public static function earliestFreeDate(mysqli $conn, string $item, int $qty, string $time, int $lengthDays, string $fromDate): ?string
    {
        $latest = date('Y-m-d', strtotime('+' . self::MAX_ADVANCE_DAYS . ' days'));
        for ($d = $fromDate; $d <= $latest; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $start = strtotime($d . ' ' . $time . ':00');
            $end   = strtotime('+' . $lengthDays . ' days', $start);
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

    // ═════════════════════════════════════════════════════════════════════════
    // HTML helpers used by the Borrow Request modal
    // ═════════════════════════════════════════════════════════════════════════

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Room picker: building > floor > room name > availability, straight from the same
     * tables the Facilities section uses (so renamed / moved / archived rooms follow along).
     */
    public static function renderRoomSelect(string $id, array $tree): string
    {
        $status = ['Available' => 'Available', 'Maintenance' => 'Under maintenance', 'Not Bookable' => 'Not bookable'];
        $h  = '<div class="form-group">';
        $h .= '<label class="form-label" for="' . self::h($id) . '">Room / Laboratory</label>';
        $h .= '<select name="room_id" id="' . self::h($id) . '" class="form-input bm-select" required>';
        if (!$tree) {
            $h .= '<option value="" selected disabled>No rooms have been set up yet</option>';
        } else {
            $h .= '<option value="" selected disabled>Choose where you will use it</option>';
        }
        foreach ($tree as $b) {
            foreach ($b['floors'] as $f) {
                $h .= '<optgroup label="' . self::h($b['name'] . ' · ' . $f['label']) . '">';
                foreach ($f['rooms'] as $r) {
                    $dis = $r['status'] === 'Maintenance' ? ' disabled' : '';
                    $h  .= '<option value="' . (int)$r['room_id'] . '"' . $dis . '>'
                         . self::h($r['name'] . ' — ' . ($status[$r['status']] ?? $r['status'])) . '</option>';
                }
                $h .= '</optgroup>';
            }
        }
        $h .= '</select>';
        $h .= '<p class="bm-hint">Where the equipment will be used (same list as Facilities).</p>';
        $h .= '</div>';
        return $h;
    }

    /**
     * The shared "when / how long / how many" block. JS (faculty-dashboard.js, BorrowSchedule)
     * fills the time list and the length chips from window.BOOKING_META.
     * $multi = true hides the single quantity stepper (the adviser checklist has one per item).
     */
    public static function renderSchedule(string $p, bool $multi = false): string
    {
        $p = self::h($p);
        $qty = $multi ? '' : '
            <div class="form-group" data-bm-future-only hidden>
                <span class="form-label">Quantity</span>
                <div class="bm-stepper" data-bm-qty-wrap>
                    <button type="button" data-bm-qty-dec aria-label="Decrease quantity"><span class="material-symbols-outlined">remove</span></button>
                    <input type="number" name="qty" value="1" min="1" max="1" inputmode="numeric" data-bm-qty aria-label="Quantity">
                    <button type="button" data-bm-qty-inc aria-label="Increase quantity"><span class="material-symbols-outlined">add</span></button>
                </div>
                <p class="bm-hint" data-bm-qty-hint></p>
            </div>';
        return '
        <div class="bm-sched" data-bm-sched data-bm-multi="' . ($multi ? '1' : '0') . '" id="' . $p . 'Sched">
            <input type="hidden" name="booking_mode" value="today" data-bm-mode-input>
            <input type="hidden" name="duration" value="" data-bm-duration-input>

            <div class="form-group">
                <span class="form-label">When do you need it?</span>
                <div class="bm-seg" role="tablist" aria-label="When do you need it?">
                    <button type="button" class="bm-seg-btn active" role="tab" aria-selected="true" data-bm-mode="today">
                        <span class="material-symbols-outlined">bolt</span> Today
                    </button>
                    <button type="button" class="bm-seg-btn" role="tab" aria-selected="false" data-bm-mode="future">
                        <span class="material-symbols-outlined">event</span> Later date
                    </button>
                </div>
            </div>

            <label class="bm-switch-row" data-bm-outnow hidden>
                <span class="bm-switch-text">
                    <strong>Out right now</strong>
                    <small>Book it ahead for a later date. It is checked against the schedule, not today\'s stock.</small>
                </span>
                <span class="bm-switch">
                    <input type="checkbox" data-bm-outnow-toggle checked>
                    <span class="bm-switch-track" aria-hidden="true"></span>
                </span>
            </label>

            <div class="bm-sched-fields" data-bm-fields>
                <div class="form-group" data-bm-future-only hidden>
                    <label class="form-label" for="' . $p . 'Date">Borrow date</label>
                    <input type="date" id="' . $p . 'Date" name="borrow_date" class="form-input" data-bm-date>
                    <p class="bm-hint" data-bm-date-hint></p>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="' . $p . 'Time">Start time</label>
                        <select id="' . $p . 'Time" name="start_time" class="form-input bm-select" data-bm-time></select>
                    </div>' . $qty . '
                </div>

                <div class="form-group">
                    <span class="form-label" data-bm-length-label>For how long?</span>
                    <div class="bm-chips" data-bm-chips role="radiogroup" aria-label="For how long?"></div>
                    <div class="bm-custom" data-bm-custom hidden>
                        <label for="' . $p . 'Custom">Custom length</label>
                        <input type="number" id="' . $p . 'Custom" min="1" step="1" inputmode="numeric" data-bm-custom-input>
                        <span>hours</span>
                    </div>
                </div>

                <div class="bm-summary" data-bm-summary aria-live="polite"></div>
                <p class="bm-avail" data-bm-avail aria-live="polite"></p>
            </div>
        </div>';
    }
}
