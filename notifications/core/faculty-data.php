<?php

/**
 * faculty-notif-functions.php
 *
 * Builds the FACULTY notification feed (faculty-dashboard.php bell/modal).
 *
 * Same idea as notif-functions.php (the admin feed): notifications are NOT
 * stored, they're computed live from the logged-in faculty member's own rows
 * every time this runs, so the feed always mirrors the real state of the
 * system. The only thing persisted is per-faculty read/deleted state, in
 * tbl_faculty_notif_state, keyed by a stable synthetic id such as
 * "overdue-12" or "roomapproved-7".
 *
 * Sources (all filtered to the logged-in faculty_id):
 *   tbl_requests            → submitted / approved (stays while the item is On Loan) / declined / booking cancelled /
 *                             due soon / overdue / returned   (+ tbl_arbitration_log
 *                             for the decision rule, reason and admin notes)
 *   tbl_room_reservations   → submitted (waiting) / confirmed / reminder / declined / cancelled
 *                             (+ tbl_room_arbitration_log for the rule)
 *   tbl_room_waitlist       → on waitlist / slot now available
 *   tbl_room_issues         → issue received / resolved / dismissed
 *   tbl_users               → recent password change
 *   tbl_login_attempts      → failed sign-in attempts on the account
 *   fnotif_static_notices() → fixed System notices every faculty account gets
 *
 * Every source is optional: if a table/column hasn't been migrated yet, or a
 * query fails, that one source is skipped (and logged) instead of taking the
 * whole feed down.
 *
 * USAGE:
 *   require_once __DIR__ . '/faculty-data.php';
 *   $list   = fnotif_build_list($conn, $_SESSION['faculty_id']);
 *   $unread = fnotif_count_unread($list);
 */

require_once __DIR__ . '/preferences.php';
require_once __DIR__ . '/datetime.php';
require_once __DIR__ . '/approval-receipts.php';
require_once __DIR__ . '/grouping.php';

if (!function_exists('fnotif_ensure_table')) {
    /** Idempotent guard — creates tbl_faculty_notif_state on first use. */
    function fnotif_ensure_table(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $exists = $conn->query(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_faculty_notif_state' LIMIT 1"
        );
        if ($exists && $exists->num_rows > 0) return;

        $conn->query(
            "CREATE TABLE IF NOT EXISTS `tbl_faculty_notif_state` (
                `id`         int(11)      NOT NULL AUTO_INCREMENT,
                `faculty_id` varchar(50)  NOT NULL,
                `notif_key`  varchar(64)  NOT NULL,
                `is_read`    tinyint(1)   NOT NULL DEFAULT 0,
                `is_deleted` tinyint(1)   NOT NULL DEFAULT 0,
                `updated_at` datetime     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_faculty_notif` (`faculty_id`, `notif_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

if (!function_exists('fnotif_ensure_admin_notice_table')) {
    /** One-off notices sent by an administrator to a specific faculty member. */
    function fnotif_ensure_admin_notice_table(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        $conn->query(
            "CREATE TABLE IF NOT EXISTS `tbl_faculty_admin_notice` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `faculty_id` varchar(50) NOT NULL,
                `request_id` int(11) DEFAULT NULL,
                `admin_id` int(11) DEFAULT NULL,
                `admin_name` varchar(255) NOT NULL,
                `title` varchar(255) NOT NULL,
                `message` text NOT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_faculty_time` (`faculty_id`, `created_at`),
                KEY `idx_request` (`request_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

if (!function_exists('fnotif_table_exists')) {
    function fnotif_table_exists(mysqli $conn, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $ok = false;
        if ($stmt) {
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $ok = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }
        return $cache[$table] = $ok;
    }
}

if (!function_exists('fnotif_col_exists')) {
    function fnotif_col_exists(mysqli $conn, string $table, string $col): bool
    {
        static $cache = [];
        $k = $table . '.' . $col;
        if (isset($cache[$k])) return $cache[$k];
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $ok = false;
        if ($stmt) {
            $stmt->bind_param('ss', $table, $col);
            $stmt->execute();
            $ok = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }
        return $cache[$k] = $ok;
    }
}

if (!function_exists('fnotif_state_map')) {
    /** @return array<string, array{is_read:bool, is_deleted:bool}> */
    function fnotif_state_map(mysqli $conn, string $facultyId): array
    {
        fnotif_ensure_table($conn);
        $map = [];
        $stmt = $conn->prepare(
            "SELECT notif_key, is_read, is_deleted FROM tbl_faculty_notif_state WHERE faculty_id = ?"
        );
        if (!$stmt) return $map;
        $stmt->bind_param('s', $facultyId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $map[$row['notif_key']] = [
                'is_read'    => (bool)$row['is_read'],
                'is_deleted' => (bool)$row['is_deleted'],
            ];
        }
        $stmt->close();
        return $map;
    }
}

if (!function_exists('fnotif_set_state_many')) {
    /**
     * Upsert read/deleted flags for many keys at once (one transaction).
     * Pass null to leave a flag untouched. Keys are validated against the
     * live feed first, so only real notifications of THIS faculty member can
     * ever be written.
     *
     * @return int number of keys written
     */
    function fnotif_set_state_many(mysqli $conn, string $facultyId, array $keys, ?bool $isRead, ?bool $isDeleted): int
    {
        fnotif_ensure_table($conn);
        if ($isRead === null && $isDeleted === null) return 0;

        // A merged notification (several of one stream) stands for every notification inside it
        $expand = notif_expand_keys(fnotif_build_list($conn, $facultyId, false), fnotif_build_list($conn, $facultyId, true));
        $keys   = array_values(array_unique(array_filter(
            $keys,
            fn($k) => is_string($k) && preg_match('/^[a-z]+-\d+$/', $k) && isset($expand[$k])
        )));
        $real = [];
        foreach ($keys as $k) {
            foreach ($expand[$k] as $inner) $real[$inner] = true;
        }
        $keys = array_keys($real);
        if (!$keys) return 0;

        if ($isRead !== null && $isDeleted !== null) {
            $sql = "INSERT INTO tbl_faculty_notif_state (faculty_id, notif_key, is_read, is_deleted)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_read = VALUES(is_read), is_deleted = VALUES(is_deleted)";
        } elseif ($isRead !== null) {
            $sql = "INSERT INTO tbl_faculty_notif_state (faculty_id, notif_key, is_read)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_read = VALUES(is_read)";
        } else {
            $sql = "INSERT INTO tbl_faculty_notif_state (faculty_id, notif_key, is_deleted)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_deleted = VALUES(is_deleted)";
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;

        $n = 0;
        $conn->begin_transaction();
        try {
            foreach ($keys as $key) {
                if ($isRead !== null && $isDeleted !== null) {
                    $r = (int)$isRead;
                    $d = (int)$isDeleted;
                    $stmt->bind_param('ssii', $facultyId, $key, $r, $d);
                } elseif ($isRead !== null) {
                    $r = (int)$isRead;
                    $stmt->bind_param('ssi', $facultyId, $key, $r);
                } else {
                    $d = (int)$isDeleted;
                    $stmt->bind_param('ssi', $facultyId, $key, $d);
                }
                if ($stmt->execute()) $n++;
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('[PUPSync] fnotif_set_state_many failed: ' . $e->getMessage());
            $n = 0;
        }
        $stmt->close();
        return $n;
    }
}

if (!function_exists('fnotif_key_parts')) {
    /** "reqapproved-12" → ['reqapproved', 12] */
    function fnotif_key_parts(string $key): array
    {
        return preg_match('/^([a-z]+)-(\d+)$/', $key, $m) ? [$m[1], (int)$m[2]] : [$key, 0];
    }
}

if (!function_exists('fnotif_supersede')) {
    /**
     * One request / reservation / report walks through stages (sent → approved → due soon → overdue → returned).
     * The feed only needs the LATEST stage of each, so an earlier stage's notification is dropped once a later
     * one exists (the later one carries the receipt number, so nothing is lost; My Activity keeps the history).
     */
    function fnotif_supersede(array $items): array
    {
        static $stages = [
            'reqapproved' => ['req', 2],
            'duesoon' => ['req', 3],
            'overdue' => ['req', 4],
            'returned' => ['req', 5],
            'reqdeclined' => ['req', 5],
            'reqcancelled' => ['req', 5],
            'reqselfcancelled' => ['req', 5],
            'roomapproved' => ['room', 2],
            'roomsoon' => ['room', 3],
            'roomdeclined' => ['room', 5],
            'roomcancelled' => ['room', 5],
            'issueopen' => ['issue', 1],
            'issueresolved' => ['issue', 5],
            'issuedismissed' => ['issue', 5],
        ];
        $top = [];
        $of  = [];
        foreach ($items as $i => $it) {
            [$p, $id] = fnotif_key_parts((string)$it['key']);
            if ($id > 0 && isset($stages[$p])) {
                $stream = $stages[$p][0] . ':' . $id;
                $of[$i] = [$stream, $stages[$p][1]];
                $top[$stream] = max($top[$stream] ?? 0, $stages[$p][1]);
            }
        }
        $out = [];
        foreach ($items as $i => $it) {
            if (isset($of[$i]) && $of[$i][1] < $top[$of[$i][0]]) {
                continue;
            }
            $out[] = $it;
        }
        return $out;
    }
}

if (!function_exists('fnotif_merge_id')) {
    /** Which notifications belong to one stream: the items of one borrow request (bundle), or reminders due the same day. */
    function fnotif_merge_id(array $n): string
    {
        static $types = ['reqapproved', 'duesoon', 'overdue', 'returned', 'reqdeclined', 'reqcancelled', 'reqselfcancelled'];
        [$p] = fnotif_key_parts((string)$n['key']);
        if (!in_array($p, $types, true)) {
            return '';
        }
        $batch = trim((string)($n['batch'] ?? ''));
        if ($batch !== '') {
            return $p . '|b|' . $batch;
        }
        $due = trim((string)($n['due'] ?? ''));
        return ($due !== '' && in_array($p, ['duesoon', 'overdue'], true)) ? $p . '|d|' . $due : '';
    }
}

if (!function_exists('fnotif_compose_group')) {
    /** One notification for several: "Approved: Projector +2 more", with the items listed in its details. */
    function fnotif_compose_group(array $members): array
    {
        $rep = $members[0];
        foreach ($members as $m) {
            if ((int)$m['ts'] > (int)$rep['ts']) $rep = $m;
        }
        $n = count($members);
        [$p] = fnotif_key_parts((string)$rep['key']);

        $units = array_values(array_filter(array_map(fn($m) => (string)($m['unit'] ?? ''), $members), fn($u) => $u !== ''));
        $base  = trim(explode(':', (string)$rep['title'], 2)[0]);
        $title = $base . ($units ? ': ' . fnotif_clip($units[0], 26) . ' +' . ($n - 1) . ' more' : ' (' . $n . ' items)');

        $shorts = array_values(array_unique(array_map(fn($m) => (string)($m['short'] ?? ''), $members)));
        $body   = '<strong>' . $n . ' items</strong>' . (count($shorts) === 1 && $shorts[0] !== '' ? ' · ' . $shorts[0] : '');

        $val = function (array $m, string $label): ?string {
            foreach ($m['detail'] ?? [] as $row) {
                if (($row['label'] ?? '') === $label) return (string)$row['value'];
            }
            return null;
        };

        $detail = [];
        if ($units) $detail[] = ['label' => 'Items', 'value' => implode(', ', $units)];
        $receipts = array_values(array_filter(array_map(fn($m) => $val($m, 'Receipt No.'), $members)));
        if ($receipts) $detail[] = ['label' => count($receipts) > 1 ? 'Receipts' : 'Receipt No.', 'value' => implode(', ', $receipts)];

        // Reasons: one row when they all agree, otherwise one row per item
        $reasons = array_map(fn($m) => $val($m, 'Reason'), $members);
        if (count(array_filter($reasons)) > 0) {
            if (count(array_unique($reasons)) === 1) {
                $detail[] = ['label' => 'Reason', 'value' => (string)$reasons[0], 'danger' => true];
            } else {
                foreach ($members as $i => $m) {
                    if (!empty($reasons[$i])) $detail[] = ['label' => (string)($m['unit'] ?? 'Item'), 'value' => (string)$reasons[$i], 'danger' => true];
                }
            }
        }

        // Anything else is listed once, but only when every item has the same value for it
        $skip = ['Equipment', 'Quantity', 'Receipt No.', 'Reason', 'Returned On'];
        foreach ($rep['detail'] ?? [] as $row) {
            $label = (string)($row['label'] ?? '');
            if (in_array($label, $skip, true)) continue;
            $same = true;
            foreach ($members as $m) {
                if ($val($m, $label) !== (string)$row['value']) {
                    $same = false;
                    break;
                }
            }
            if ($same) $detail[] = $row;
        }

        $urgent = in_array(true, array_map(fn($m) => !empty($m['urgent']), $members), true);
        return array_merge($rep, [
            'key'    => notif_group_key('g' . $p, $members),
            'title'  => $title,
            'body'   => $body,
            'detail' => $detail,
            'ts'     => max(array_map(fn($m) => (int)$m['ts'], $members)),
            'urgent' => $urgent,
            'group'  => $urgent ? 'overdue' : $rep['group'],
        ]);
    }
}

if (!function_exists('fnotif_clip')) {
    /** Trim long free text (reasons, notes) for the one-line card preview. */
    function fnotif_clip(string $s, int $max): string
    {
        $s = trim($s);
        if (function_exists('mb_strimwidth')) return mb_strimwidth($s, 0, $max, '…');
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
    }
}

if (!function_exists('fnotif_time_label')) {
    /** Short label for the card's top-right corner: "9:42 AM" / "Yesterday" / "Jun 12". */
    function fnotif_time_label(int $ts): string
    {
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->getTimestamp();
        if ($ts >= $today)                 return date('g:i A', $ts);
        if ($ts >= $today - 86400)         return 'Yesterday';
        if (date('Y', $ts) === date('Y'))  return date('M j', $ts);
        return date('M j, Y', $ts);
    }
}

if (!function_exists('fnotif_db_timestamp')) {
    function fnotif_db_timestamp(mysqli $conn, ?string $datetime, int $fallback): int
    {
        $datetime = trim((string)$datetime);
        return $datetime !== '' ? notif_datetime($conn, $datetime)->getTimestamp() : $fallback;
    }
}

if (!function_exists('fnotif_date_ts')) {
    /**
     * Unix timestamp for a user-entered DATE (+ optional TIME) column such as
     * borrow_date/borrow_time, return_date/return_time or reservation_date/
     * start_time. These hold the wall-clock Manila values the person picked in
     * the booking form; the database never converted them, so they must be read
     * as Asia/Manila. Do NOT route them through notif_datetime(): that applies
     * the database server's clock offset, which is only meant for columns the
     * database stamped itself (CURRENT_TIMESTAMP / NOW()) and would shift these
     * by +8h whenever the DB runs on UTC.
     */
    function fnotif_date_ts(mysqli $conn, ?string $date, ?string $time = '00:00:00'): int
    {
        static $manila = null;
        $date = trim((string)$date);
        if ($date === '') return 0;
        $time = trim((string)$time);
        if ($time === '') $time = '00:00:00';
        $manila = $manila ?? new DateTimeZone('Asia/Manila');
        try {
            return (new DateTimeImmutable($date . ' ' . $time, $manila))->getTimestamp();
        } catch (Exception $ex) {
            return 0;
        }
    }
}

if (!function_exists('fnotif_decision_reason')) {
    /**
     * The reason an admin (or the decision rule) gave for a request's outcome.
     * The decision log holds it: the admin's own note first, then the rule's reason. tbl_requests.reason is only a
     * fallback, and its "Waiting for an admin …" wording is the holding text shown while the request was
     * waiting, so it is never presented as a reason.
     */
    function fnotif_decision_reason(?string $adminNote, ?string $logReason, ?string $requestReason): string
    {
        foreach ([$adminNote, $logReason] as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
        $fallback = trim((string)$requestReason);
        return strncmp($fallback, 'Waiting for an admin', 20) === 0 ? '' : $fallback;
    }
}

if (!function_exists('fnotif_rule_label')) {
    /** Human wording for an arbitration rule code (request or room). */
    function fnotif_rule_label(?string $rule): ?string
    {
        $rule = trim((string)$rule);
        if ($rule === '') return null;
        static $map = [
            'rule_overdue_block'    => 'Blocked — you have an overdue item',
            'rule_duplicate_block'  => 'Blocked — duplicate request',
            'rule_missing_doc_hold' => 'On hold — supporting document missing',
            'rule_archived'         => 'Item no longer available',
            'rule_out_of_stock'     => 'Item out of stock',
            'rule_past_datetime'    => 'Requested date/time already passed',
            'rule_1_fifo'           => 'First come, first served',
            'rule_2_signatory'      => 'Signatory priority',
            'rule_3_role'           => 'Role priority',
            'rule_4_return_history' => 'Return history',
            'rule_5_id_order'       => 'Tie-break by ID order',
            'qr_return'             => 'QR code return',
            'rule_buffer_approved'  => 'Approved when the 10-minute review ended',
            'rule_buffer_declined'  => 'Declined when the 10-minute review ended',
            'rule_expired_unreviewed' => 'Not reviewed in time',
            'rule_schedule_conflict'  => 'Schedule conflict',
            'rule_item_limit'       => 'Borrowing limit reached',
            'rule_letter_required'  => 'Request letter required',
            'rule_no_show'          => 'Not collected in time',
        ];
        if (isset($map[$rule])) return $map[$rule];
        return ucfirst(trim(str_replace('_', ' ', preg_replace('/^rule_(\d+_)?/', '', $rule))));
    }
}

if (!function_exists('fnotif_static_notices')) {
    /**
     * Static System notices — the same for every faculty account.
     *
     * Nothing is stored or "sent": they're part of the feed that's computed for
     * whoever is logged in, so a faculty member created yesterday and one who's
     * been here for years both get them. Only their read/deleted state is saved
     * per faculty (tbl_faculty_notif_state, key "sysnotice-<id>").
     *
     * To edit the wording: change it here (read/deleted state is kept).
     * To push a NEW notice to everyone: add another entry with the next id.
     * Keep 'body' short — it has to fit one small notification card.
     * Plain text only (it's escaped when rendered).
     */
    function fnotif_static_notices(): array
    {
        return [
            [
                'id'         => 1,
                'title'      => 'Welcome to PUPSync',
                'body'       => 'Request updates, due-date reminders, room bookings and admin notices will show up here.',
                'icon'       => 'waving_hand',
                'icon_class' => 'ni-success',
                'published'  => '2026-10-01 08:00:00',
            ],
            [
                'id'         => 2,
                'title'      => 'About the Faculty Portal',
                'body'       => 'Borrow equipment, reserve rooms, and track your requests and returns in one place.',
                'icon'       => 'info',
                'icon_class' => 'ni-alert',
                'published'  => '2026-10-01 08:00:00',
            ],
        ];
    }
}

if (!function_exists('fnotif_build_list')) {
    /**
     * Builds the full live feed for one faculty member, merged with their
     * persisted read/deleted state. Deleted items are excluded unless
     * $includeDeleted.
     *
     * Each item:
     *   key, cat (overdue|borrow|room|system), group (overdue|today|yesterday|
     *   week|earlier), icon, icon_class, urgent, title (plain text),
     *   body (HTML — every dynamic value already escaped), time_label, ts,
     *   detail[{label,value,danger?}], link{tab,sub,label}|null, is_read
     */
    function fnotif_build_list(mysqli $conn, string $facultyId, bool $includeDeleted = false): array
    {
        date_default_timezone_set('Asia/Manila');

        $HISTORY_DAYS = 30;     // one-off events (approved/declined/returned/...) age out after this
        $FEED_LIMIT   = 200;


        $state    = fnotif_state_map($conn, $facultyId);
        $now      = time();
        $todayTs  = strtotime('today', $now);
        $todayStr = date('Y-m-d', $now);
        $windowTs = $now - $HISTORY_DAYS * 86400;
        $items    = [];

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $d = function (string $label, $value, bool $danger = false): ?array {
            $value = trim((string)$value);
            if ($value === '') return null;
            $row = ['label' => $label, 'value' => $value];
            if ($danger) $row['danger'] = true;
            return $row;
        };
        // What the request row being processed belongs to (its bundle, due date and item name); $add stamps it on the
        // notification so related ones can be merged afterwards. Empty for everything that is not a borrow request.
        $ctx = ['batch' => '', 'due' => '', 'unit' => ''];
        $add = function (array $n) use (&$items, &$ctx, $state, $now, $todayTs) {
            $ts  = min((int)$n['ts'], $now);
            $n['ts'] = $ts;
            $n['urgent']     = $n['urgent'] ?? false;
            $n['time_label'] = $n['time_label'] ?? fnotif_time_label($ts);
            $n['detail']     = array_values(array_filter($n['detail'] ?? []));
            $n['link']       = $n['link'] ?? null;
            $n['batch']      = $ctx['batch'];
            $n['due']        = $ctx['due'];
            $n['unit']       = $ctx['unit'];
            $n['group']      = $n['urgent'] ? 'overdue'
                : ($ts >= $todayTs ? 'today'
                    : ($ts >= $todayTs - 86400 ? 'yesterday'
                        : ($ts >= $todayTs - 6 * 86400 ? 'week' : 'earlier')));
            $st = $state[$n['key']] ?? null;
            $n['is_read']    = $st['is_read']    ?? false;
            $n['is_deleted'] = $st['is_deleted'] ?? false;
            $items[] = $n;
        };

        $lendingLink = ['tab' => 'lending', 'sub' => 'requests', 'label' => 'View in My Requests'];
        $resvLink    = ['tab' => 'rooms',   'sub' => 'history',  'label' => 'View My Reservations'];
        $roomsLink   = ['tab' => 'rooms',   'sub' => 'browse',   'label' => 'Open Facilities'];

        /* ── 1. Equipment borrow requests ─────────────────────────────── */
        try {
            $hasLog = fnotif_table_exists($conn, 'tbl_arbitration_log');
            $sql = $hasLog
                ? "SELECT r.*, l.decision AS log_decision, l.rule_applied AS log_rule, l.reason AS log_reason,
                          l.override_by AS log_override_by, l.override_reason AS log_override_reason,
                          l.created_at AS log_at
                     FROM tbl_requests r
                     LEFT JOIN tbl_arbitration_log l
                            ON l.id = (SELECT MAX(l2.id) FROM tbl_arbitration_log l2 WHERE l2.request_id = r.id)
                    WHERE r.faculty_id = ?
                    ORDER BY r.request_date DESC LIMIT 200"
                : "SELECT r.* FROM tbl_requests r WHERE r.faculty_id = ? ORDER BY r.request_date DESC LIMIT 200";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $facultyId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $id      = (int)$r['id'];
                $status  = trim((string)$r['status']);
                $equip   = (string)$r['equipment_name'];
                $reqTs   = fnotif_db_timestamp($conn, $r['request_date'] ?? null, $now);
                $decidedTs = (!empty($r['log_at']) && in_array($r['log_decision'] ?? '', ['Approved', 'Declined'], true))
                    ? fnotif_db_timestamp($conn, $r['log_at'], $reqTs) : $reqTs;
                $borrowStr = date('M j, Y', fnotif_date_ts($conn, $r['borrow_date']));
                $returnStr = date('M j, Y', fnotif_date_ts($conn, $r['return_date']));
                $override  = !empty($r['log_override_by']);
                // A student who borrowed with this faculty member's code (the faculty is still the borrower of record)
                $studentBy = ((string)($r['submitted_as'] ?? '') === 'student') ? trim((string)($r['submitted_by_name'] ?? '')) : '';
                $qtyU = max(1, (int)($r['borrow_qty'] ?? 1));
                $ctx  = [
                    'batch' => trim((string)($r['batch_id'] ?? '')),
                    'due'   => (string)($r['return_date'] ?? ''),
                    'unit'  => $equip . ($qtyU > 1 ? ' ×' . $qtyU : ''),
                ];
                $common = [
                    $d('Equipment', $ctx['unit']),
                    $d('Borrow Date', $borrowStr),
                    $d('Return Date', $returnStr),
                    $d('Room', $r['room'] ?? ''),
                    $d('Requested By (Student)', $studentBy),
                ];
                switch ($status) {
                    // 'Waiting' deliberately has no notification: a request that was just sent is already shown in
                    // My Activity. The feed only carries the RECEIPT once an admin or the system approves / declines it.

                    case 'Approved':
                    case 'On Loan':
                        // The approval receipt: same number the admin sees (#R-0123) and the push carries.
                        // It stays in the feed while the item is out (On Loan) so the borrow never drops out of the
                        // notifications between pick-up and return; the return reminder below follows it too.
                        $qty       = max(1, (int)($r['borrow_qty'] ?? 1));
                        $equipLbl  = $equip . ($qty > 1 ? ' ×' . $qty : '');
                        $recNo     = ps_receipt_no('equipment', $id);
                        $fromStamp = ps_receipts_stamp($r['borrow_date'] ?? '', $r['borrow_time'] ?? '');
                        $toStamp   = ps_receipts_stamp($r['return_date'] ?? '', $r['return_time'] ?? '');
                        $ruleText  = fnotif_rule_label($r['log_rule'] ?? $r['arbitration_rule'] ?? null);
                        $approvedBy = $override
                            ? 'Admin — ' . trim((string)$r['log_override_by'])
                            : 'Automatic' . ($ruleText ? ' — ' . $ruleText : '');
                        // A student named to collect the items (the faculty is still the borrower)
                        $pickupBy  = ((string)($r['submitted_as'] ?? '') !== 'student') ? trim((string)($r['submitted_by_name'] ?? '')) : '';
                        $shortOk   = 'Borrow <strong>' . $e(date('M j', fnotif_date_ts($conn, $r['borrow_date']))) . '</strong>, return <strong>'
                            . $e(date('M j', fnotif_date_ts($conn, $r['return_date']))) . '</strong>';
                        $noteTxt   = fnotif_clip(trim((string)($r['log_override_reason'] ?? '')), 70);
                        $add([
                            'key' => 'reqapproved-' . $id,
                            'cat' => 'borrow',
                            'icon' => 'check_circle',
                            'icon_class' => 'ni-success',
                            'title' => 'Approved: ' . $equip,
                            'body'  => $shortOk . ' · ' . $e($recNo) . ($noteTxt !== '' ? '. Note: ' . $e($noteTxt) : ''),
                            'short' => $shortOk,
                            'ts'    => $decidedTs,
                            'detail' => [
                                $d('Receipt No.', $recNo),
                                $d('Equipment', $equipLbl),
                                $d('Borrow', $fromStamp !== '' ? $fromStamp : $borrowStr),
                                $d('Return', $toStamp !== '' ? $toStamp : $returnStr),
                                $d('Approved By', $approvedBy),
                                $d('Admin Note', $r['log_override_reason'] ?? ''),
                                $d('Pick-up By (Student)', $pickupBy),
                                $d('Requested By (Student)', $studentBy),
                            ],
                            'link' => $lendingLink,
                        ]);

                        // Return reminder: due today / tomorrow / in 2 days
                        $daysLeft = (int)round((fnotif_date_ts($conn, $r['return_date']) - $todayTs) / 86400);
                        if ($daysLeft >= 0 && $daysLeft <= 2) {
                            $when = $daysLeft === 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : 'in 2 days');
                            $add([
                                'key' => 'duesoon-' . $id,
                                'cat' => 'borrow',
                                'icon' => 'alarm',
                                'icon_class' => 'ni-warn',
                                'title' => 'Return reminder: ' . $equip,
                                'body'  => 'Due <strong>' . $when . '</strong> (' . $e(date('M j', fnotif_date_ts($conn, $r['return_date']))) . ').',
                                'short' => 'Due <strong>' . $when . '</strong> (' . $e(date('M j', fnotif_date_ts($conn, $r['return_date']))) . ')',
                                'ts'    => $todayTs,
                                'time_label' => $daysLeft === 0 ? 'Due today' : ($daysLeft === 1 ? 'Due tomorrow' : 'Due ' . date('M j', fnotif_date_ts($conn, $r['return_date']))),
                                'detail' => [
                                    $d('Receipt No.', $recNo),
                                    $d('Equipment', $equipLbl),
                                    $d('Due Date', $returnStr, $daysLeft === 0),
                                ],
                                'link' => $lendingLink,
                            ]);
                        }
                        break;

                    case 'Declined':
                        if ($decidedTs < $windowTs) break;

                        // A booking an admin cancelled after approving it (admin-override.php marks the decision
                        // log row 'booking_cancelled'). Gets its own "Booking Cancelled" notice instead of the
                        // generic "Declined" one, and shows the ADMIN'S reason (tbl_requests.reason is the
                        // borrower's own purpose, so it is deliberately not used here).
                        if (($r['log_rule'] ?? '') === 'booking_cancelled') {
                            $cQty    = max(1, (int)($r['borrow_qty'] ?? 1));
                            $cReason = trim((string)($r['log_override_reason'] ?? ''));
                            if ($cReason === '') $cReason = trim((string)($r['log_reason'] ?? ''));
                            $cStartTs = fnotif_date_ts($conn, $r['borrow_date'], !empty($r['borrow_time']) ? $r['borrow_time'] : '00:00:00');
                            $cStart   = !empty($r['borrow_time']) ? date('M j, Y g:i A', $cStartTs) : $borrowStr;
                            $cDue     = !empty($r['return_time'])
                                ? date('M j, Y g:i A', fnotif_date_ts($conn, $r['return_date'], $r['return_time'])) : $returnStr;
                            $add([
                                'key' => 'reqcancelled-' . $id,
                                'cat' => 'borrow',
                                'icon' => 'event_busy',
                                'icon_class' => 'ni-overdue',
                                'title' => 'Booking cancelled: ' . $equip,
                                'body'  => $cReason !== '' ? 'Reason: ' . $e(fnotif_clip($cReason, 90)) : 'Cancelled by an administrator.',
                                'short' => $cReason !== '' ? 'Reason: ' . $e(fnotif_clip($cReason, 90)) : 'Cancelled by an administrator.',
                                'ts'    => $decidedTs,
                                'detail' => [
                                    $d('Equipment', $ctx['unit']),
                                    $d('Was Scheduled For', $cStart),
                                    $d('Reason', $cReason, true),
                                ],
                                'link' => $lendingLink,
                            ]);
                            break;
                        }

                        // The ADMIN'S reason (decision log) comes first; the request's own text is only a fallback
                        $reason = fnotif_decision_reason($r['log_override_reason'] ?? '', $r['log_reason'] ?? '', $r['reason'] ?? '');
                        // The decline receipt: same receipt number as the approval one (#R-0123), and who decided
                        $decRecNo  = ps_receipt_no('equipment', $id);
                        $decRule   = fnotif_rule_label($r['log_rule'] ?? $r['arbitration_rule'] ?? null);
                        $declinedBy = $override
                            ? 'Admin — ' . trim((string)$r['log_override_by'])
                            : 'Automatic' . ($decRule ? ' — ' . $decRule : '');
                        $declShort = $reason !== '' ? 'Reason: ' . $e(fnotif_clip($reason, 90)) : 'Your request was declined.';
                        $add([
                            'key' => 'reqdeclined-' . $id,
                            'cat' => 'borrow',
                            'icon' => 'cancel',
                            'icon_class' => 'ni-overdue',
                            'title' => 'Declined: ' . $equip,
                            'body'  => $declShort . ' · ' . $e($decRecNo),
                            'short' => $declShort,
                            'ts'    => $decidedTs,
                            'detail' => array_merge(
                                [$d('Receipt No.', $decRecNo), $d('Reason', $reason, true)],
                                $common,
                                [$d('Declined By', $declinedBy)]
                            ),
                            'link' => $lendingLink,
                        ]);
                        break;

                    case 'Cancelled':
                        // The faculty member cancelled this request themselves (core/borrow-cancellation.php).
                        // A short record in the feed, aging out like the other one-off events.
                        $cancelTs = (!empty($r['log_at']) && ($r['log_decision'] ?? '') === 'Cancelled')
                            ? fnotif_db_timestamp($conn, $r['log_at'], $reqTs) : $reqTs;
                        if ($cancelTs < $windowTs) break;
                        $kQty   = max(1, (int)($r['borrow_qty'] ?? 1));
                        $kStart = fnotif_date_ts($conn, $r['borrow_date'], !empty($r['borrow_time']) ? $r['borrow_time'] : '00:00:00');
                        $add([
                            'key' => 'reqselfcancelled-' . $id,
                            'cat' => 'borrow',
                            'icon' => 'event_busy',
                            'icon_class' => 'ni-alert',
                            'title' => 'Request cancelled: ' . $equip,
                            'body'  => 'You cancelled this request (was for ' . $e(date('M j', $kStart)) . ').',
                            'short' => 'You cancelled this request.',
                            'ts'    => $cancelTs,
                            'detail' => [
                                $d('Equipment', $ctx['unit']),
                                $d('Was Scheduled For', !empty($r['borrow_time']) ? date('M j, Y g:i A', $kStart) : $borrowStr),
                                $d('Cancelled On', date('M j, Y g:i A', $cancelTs)),
                            ],
                            'link' => $lendingLink,
                        ]);
                        break;

                    case 'Overdue':
                        $dueTs    = fnotif_date_ts($conn, $r['return_date']);
                        $daysLate = max(0, (int)floor(($now - $dueTs) / 86400));
                        $lateStr  = $daysLate . ' day' . ($daysLate !== 1 ? 's' : '');
                        $add([
                            'key' => 'overdue-' . $id,
                            'cat' => 'overdue',
                            'icon' => 'schedule',
                            'icon_class' => 'ni-overdue',
                            'urgent' => true,
                            'title' => 'Overdue: ' . $equip,
                            'body'  => ($daysLate > 0 ? '<strong>' . $e($lateStr) . '</strong> overdue.' : '<strong>Past its return time.</strong>') . ' Please return it.',
                            'short' => ($daysLate > 0 ? '<strong>' . $e($lateStr) . '</strong> overdue' : '<strong>Past its return time</strong>') . ' — please return',
                            'ts'    => $dueTs,
                            'time_label' => 'Due ' . date('M j', $dueTs),
                            'detail' => [
                                $d('Equipment', $ctx['unit']),
                                $d('Due Date', $returnStr, true),
                                $d('Days Overdue', $lateStr, true),
                            ],
                            'link' => $lendingLink,
                        ]);
                        break;

                    case 'Returned':
                        $retTs = !empty($r['returned_at'])
                            ? fnotif_db_timestamp($conn, $r['returned_at'], $now)
                            : (!empty($r['log_at'])
                                ? fnotif_db_timestamp($conn, $r['log_at'], $now)
                                : fnotif_date_ts($conn, $r['return_date']));
                        if ($retTs < $windowTs) break;
                        // Judged against when it was ORIGINALLY due: an extension never makes a late return look on time
                        $hasOrig   = !empty($r['original_return_date']);
                        $dueDateOn = $hasOrig ? $r['original_return_date'] : $r['return_date'];
                        $dueTimeOn = $hasOrig ? ($r['original_return_time'] ?? '') : '';
                        $onTime = $retTs <= fnotif_date_ts($conn, $dueDateOn, ($dueTimeOn !== '' && $dueTimeOn !== null) ? $dueTimeOn : '23:59:59');
                        $extN   = (int)($r['extension_count'] ?? 0);
                        $add([
                            'key' => 'returned-' . $id,
                            'cat' => 'borrow',
                            'icon' => 'task_alt',
                            'icon_class' => 'ni-success',
                            'title' => 'Returned: ' . $equip,
                            'body'  => 'Returned ' . $e(date('M j, g:i A', $retTs)) . ($onTime ? ' · on time.' : ' · after the due date.'),
                            'short' => $onTime ? 'Returned on time' : 'Returned after the due date',
                            'ts'    => $retTs,
                            'detail' => [
                                $d('Equipment', $ctx['unit']),
                                $d('Returned On', date('M j, Y g:i A', $retTs)),
                                $d('Extensions', $extN > 0 ? ($extN === 1 ? 'Extended once' : 'Extended ' . $extN . ' times') : ''),
                                $d('Timeliness', $onTime ? 'On time' : 'After the due date', !$onTime),
                            ],
                            'link' => $lendingLink,
                        ]);
                        break;
                }
            }
            $stmt->close();
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif requests source failed: ' . $ex->getMessage());
        }

        $ctx = ['batch' => '', 'due' => '', 'unit' => ''];

        /* ── 2. Room reservations ─────────────────────────────────────── */
        try {
            if (
                fnotif_table_exists($conn, 'tbl_room_reservations')
                && fnotif_table_exists($conn, 'tbl_rooms')
                && fnotif_table_exists($conn, 'tbl_buildings')
            ) {

                $hasCancelled = fnotif_col_exists($conn, 'tbl_room_reservations', 'cancelled_at');
                $hasRoomLog   = fnotif_table_exists($conn, 'tbl_room_arbitration_log');

                $sql = "SELECT rr.id, rr.reservation_date, rr.start_time, rr.end_time, rr.purpose, rr.attendees,
                               rr.status, rr.reason, rr.request_date, rr.submitted_as, rr.submitted_by_name,
                               r.room_name, b.name AS building_name,
                               COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label"
                    . ($hasCancelled ? ", rr.cancelled_at" : ", NULL AS cancelled_at")
                    . ($hasRoomLog   ? ", rl.rule_applied AS log_rule, rl.created_at AS log_at" : ", NULL AS log_rule, NULL AS log_at")
                    . " FROM tbl_room_reservations rr
                         JOIN tbl_rooms     r ON r.room_id     = rr.room_id
                         JOIN tbl_buildings b ON b.building_id = r.building_id"
                    . ($hasRoomLog ? " LEFT JOIN tbl_room_arbitration_log rl
                                 ON rl.id = (SELECT MAX(x.id) FROM tbl_room_arbitration_log x WHERE x.reservation_id = rr.id)" : "")
                    . " WHERE rr.faculty_id = ?
                        ORDER BY rr.request_date DESC LIMIT 200";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $facultyId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $id     = (int)$r['id'];
                    $status = trim((string)$r['status']);
                    $room   = (string)$r['room_name'];
                    $reqTs  = fnotif_db_timestamp($conn, $r['request_date'] ?? null, $now);
                    $startTs = fnotif_date_ts($conn, $r['reservation_date'], $r['start_time']);
                    $endTs   = fnotif_date_ts($conn, $r['reservation_date'], $r['end_time']);
                    $dateStr = date('M j, Y', fnotif_date_ts($conn, $r['reservation_date']));
                    $timeStr = date('g:i A', $startTs) . ' – ' . date('g:i A', $endTs);
                    $slot    = date('M j', fnotif_date_ts($conn, $r['reservation_date'])) . ', ' . $timeStr;
                    // A student who reserved with this faculty member's code
                    $roomStudentBy = ((string)($r['submitted_as'] ?? '') === 'student') ? trim((string)($r['submitted_by_name'] ?? '')) : '';
                    $common = [
                        $d('Room', $room),
                        $d('Location', $r['floor_label'] . ', ' . $r['building_name']),
                        $d('Date', $dateStr),
                        $d('Time', $timeStr),
                        $d('Purpose', $r['purpose']),
                        $d('Requested By (Student)', $roomStudentBy),
                    ];
                    // Who made the decision: the room decision log marks an admin's own decision 'override' / 'manual';
                    // anything else is the system (a rule, or the review window ending)
                    $roomRule = trim((string)($r['log_rule'] ?? ''));
                    $roomBy   = in_array($roomRule, ['override', 'manual'], true)
                        ? 'Admin'
                        : ($roomRule !== '' ? 'Automatic — ' . fnotif_rule_label($roomRule) : '');

                    if ($status === 'Approved') {
                        $upcoming = $endTs >= $now;
                        // When the approval happened (a reservation for a later date can wait days for an admin)
                        $roomDecidedTs = !empty($r['log_at']) ? fnotif_db_timestamp($conn, $r['log_at'], $reqTs) : $reqTs;
                        if ($upcoming || $roomDecidedTs >= $windowTs) {
                            $add([
                                'key' => 'roomapproved-' . $id,
                                'cat' => 'room',
                                'icon' => 'meeting_room',
                                'icon_class' => 'ni-success',
                                'title' => 'Approved: ' . $room,
                                'body'  => '<strong>' . $e($slot) . '</strong> · ' . $e(ps_receipt_no('room', $id)),
                                'ts'    => $roomDecidedTs,
                                'detail' => array_merge([$d('Receipt No.', ps_receipt_no('room', $id))], $common, [$d('Approved By', $roomBy)]),
                                'link' => $resvLink,
                            ]);
                        }
                        // Reminder: today (not yet over) or tomorrow
                        $dayDiff = (int)round((fnotif_date_ts($conn, $r['reservation_date']) - $todayTs) / 86400);
                        if (($dayDiff === 0 && $endTs >= $now) || $dayDiff === 1) {
                            $isToday = $dayDiff === 0;
                            $add([
                                'key' => 'roomsoon-' . $id,
                                'cat' => 'room',
                                'icon' => 'event_upcoming',
                                'icon_class' => 'ni-warn',
                                'title' => 'Reserved ' . ($isToday ? 'today' : 'tomorrow') . ': ' . $room,
                                'body'  => '<strong>' . $e($timeStr) . '</strong>',
                                'ts'    => $todayTs,
                                'time_label' => $isToday ? 'Today' : 'Tomorrow',
                                'detail' => array_merge([$d('Receipt No.', ps_receipt_no('room', $id))], $common),
                                'link'   => $resvLink,
                            ]);
                        }
                        // 'Waiting' deliberately has no notification (the sent request is already in My Activity);
                        // the receipt below appears once an admin or the system approves / declines it.
                    } elseif ($status === 'Declined') {
                        // When it was DECIDED, not when it was sent: a later-date reservation can wait days for the decision
                        // (or be declined automatically at 12:01 AM on its day), same as the approval receipt above
                        $roomDeclinedTs = !empty($r['log_at']) ? fnotif_db_timestamp($conn, $r['log_at'], $reqTs) : $reqTs;
                        if ($roomDeclinedTs < $windowTs) continue;
                        $reason = trim((string)($r['reason'] ?? ''));
                        $add([
                            'key' => 'roomdeclined-' . $id,
                            'cat' => 'room',
                            'icon' => 'event_busy',
                            'icon_class' => 'ni-overdue',
                            'title' => 'Declined: ' . $room,
                            'body'  => '<strong>' . $e($slot) . '</strong>' . ($reason !== '' ? ' · Reason: ' . $e(fnotif_clip($reason, 90)) : '') . ' · ' . $e(ps_receipt_no('room', $id)),
                            'ts'    => $roomDeclinedTs,
                            'detail' => array_merge(
                                [$d('Receipt No.', ps_receipt_no('room', $id)), $d('Reason', $reason, true)],
                                $common,
                                [$d('Declined By', $roomBy)]
                            ),
                            'link' => $resvLink,
                        ]);
                    } elseif ($status === 'Cancelled') {
                        $cTs = !empty($r['cancelled_at'])
                            ? fnotif_db_timestamp($conn, $r['cancelled_at'], $reqTs)
                            : $reqTs;
                        if ($cTs < $windowTs) continue;
                        $add([
                            'key' => 'roomcancelled-' . $id,
                            'cat' => 'room',
                            'icon' => 'event_busy',
                            'icon_class' => 'ni-alert',
                            'title' => 'Cancelled: ' . $room,
                            'body'  => '<strong>' . $e($slot) . '</strong>' . (trim((string)($r['reason'] ?? '')) !== '' ? ' · ' . $e(fnotif_clip(trim((string)$r['reason']), 90)) : ''),
                            'ts'    => $cTs,
                            'detail' => array_merge([$d('Reason', $r['reason'] ?? '')], $common),
                            'link' => $resvLink,
                        ]);
                    }
                }
                $stmt->close();
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif room reservations source failed: ' . $ex->getMessage());
        }

        /* ── 3. Room waitlist ─────────────────────────────────────────── */
        try {
            if (
                fnotif_table_exists($conn, 'tbl_room_waitlist')
                && fnotif_table_exists($conn, 'tbl_room_reservations')
                && fnotif_table_exists($conn, 'tbl_rooms')
                && fnotif_table_exists($conn, 'tbl_buildings')
            ) {

                $nowTime = date('H:i:s', $now);
                $stmt = $conn->prepare(
                    "SELECT w.id, w.reservation_date, w.start_time, w.end_time, w.created_at,
                            r.room_name, b.name AS building_name,
                            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
                            (SELECT COUNT(*) FROM tbl_room_reservations x
                              WHERE x.room_id = w.room_id AND x.reservation_date = w.reservation_date
                                AND x.status = 'Approved'
                                AND x.start_time < w.end_time AND x.end_time > w.start_time) AS taken
                       FROM tbl_room_waitlist w
                       JOIN tbl_rooms     r ON r.room_id     = w.room_id
                       JOIN tbl_buildings b ON b.building_id = r.building_id
                      WHERE w.faculty_id = ?
                        AND (w.reservation_date > ? OR (w.reservation_date = ? AND w.end_time > ?))
                      ORDER BY w.created_at DESC LIMIT 50"
                );
                $stmt->bind_param('ssss', $facultyId, $todayStr, $todayStr, $nowTime);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $id      = (int)$r['id'];
                    $room    = (string)$r['room_name'];
                    $joinTs  = fnotif_db_timestamp($conn, $r['created_at'] ?? null, $now);
                    $timeStr = date('g:i A', strtotime($r['start_time'])) . ' – ' . date('g:i A', strtotime($r['end_time']));
                    $slot    = date('M j', fnotif_date_ts($conn, $r['reservation_date'])) . ', ' . $timeStr;
                    $common = [
                        $d('Room', $room),
                        $d('Location', $r['floor_label'] . ', ' . $r['building_name']),
                        $d('Date', date('M j, Y', fnotif_date_ts($conn, $r['reservation_date']))),
                        $d('Time', $timeStr),
                        $d('Joined Waitlist', date('M j, Y g:i A', $joinTs)),
                    ];
                    if ((int)$r['taken'] === 0) {
                        $add([
                            'key' => 'waitavail-' . $id,
                            'cat' => 'room',
                            'icon' => 'event_available',
                            'icon_class' => 'ni-success',
                            'title' => 'Slot open: ' . $room,
                            'body'  => '<strong>' . $e($slot) . '</strong> is open — book it now.',
                            'ts'    => $todayTs,
                            'time_label' => 'Available now',
                            'detail' => array_merge([$d('Status', 'Slot is open')], $common),
                            'link'   => $roomsLink,
                        ]);
                    } else {
                        $add([
                            'key' => 'waitlist-' . $id,
                            'cat' => 'room',
                            'icon' => 'hourglass_top',
                            'icon_class' => 'ni-alert',
                            'title' => 'On waitlist: ' . $room,
                            'body'  => '<strong>' . $e($slot) . '</strong> · we\'ll email you if it opens.',
                            'ts'    => $joinTs,
                            'detail' => array_merge([$d('Status', 'Waiting for the slot to open')], $common),
                            'link'   => $roomsLink,
                        ]);
                    }
                }
                $stmt->close();
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif waitlist source failed: ' . $ex->getMessage());
        }

        /* ── 4. Room issues this faculty member reported ──────────────── */
        try {
            if (
                fnotif_table_exists($conn, 'tbl_room_issues')
                && fnotif_table_exists($conn, 'tbl_rooms')
                && fnotif_table_exists($conn, 'tbl_buildings')
            ) {

                $stmt = $conn->prepare(
                    "SELECT ri.id, ri.description, ri.status, ri.admin_notes, ri.created_at, ri.resolved_at,
                            r.room_name, b.name AS building_name,
                            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
                       FROM tbl_room_issues ri
                       JOIN tbl_rooms     r ON r.room_id     = ri.room_id
                       JOIN tbl_buildings b ON b.building_id = r.building_id
                      WHERE ri.reported_by_id = ?
                      ORDER BY ri.created_at DESC LIMIT 100"
                );
                $stmt->bind_param('s', $facultyId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $id     = (int)$r['id'];
                    $status = trim((string)$r['status']);
                    $room   = (string)$r['room_name'];
                    $crTs   = fnotif_db_timestamp($conn, $r['created_at'] ?? null, $now);
                    $snip   = fnotif_clip((string)$r['description'], 90);
                    $common = [
                        $d('Room', $room),
                        $d('Location', $r['floor_label'] . ', ' . $r['building_name']),
                        $d('Your Report', $r['description']),
                        $d('Reported On', date('M j, Y g:i A', $crTs)),
                    ];
                    if ($status === 'Open') {
                        $add([
                            'key' => 'issueopen-' . $id,
                            'cat' => 'room',
                            'icon' => 'report_problem',
                            'icon_class' => 'ni-alert',
                            'title' => 'Report received: ' . $room,
                            'body'  => 'Under review — “' . $e(fnotif_clip((string)$r['description'], 60)) . '”',
                            'ts'    => $crTs,
                            'detail' => array_merge([$d('Status', 'Open — under review')], $common),
                            'link'   => $roomsLink,
                        ]);
                    } elseif ($status === 'Resolved' || $status === 'Dismissed') {
                        $doneTs = !empty($r['resolved_at'])
                            ? fnotif_db_timestamp($conn, $r['resolved_at'], $crTs)
                            : $crTs;
                        if ($doneTs < $windowTs) continue;
                        $isRes = $status === 'Resolved';
                        $note  = trim((string)($r['admin_notes'] ?? ''));
                        $add([
                            'key' => ($isRes ? 'issueresolved-' : 'issuedismissed-') . $id,
                            'cat' => 'room',
                            'icon' => $isRes ? 'build_circle' : 'report_off',
                            'icon_class' => $isRes ? 'ni-success' : 'ni-alert',
                            'title' => ($isRes ? 'Issue resolved: ' : 'Report closed: ') . $room,
                            'body'  => $note !== '' ? 'Note: ' . $e(fnotif_clip($note, 90)) : ($isRes ? 'Marked as resolved.' : 'Reviewed and closed.'),
                            'ts'    => $doneTs,
                            'detail' => array_merge([$d('Status', $isRes ? 'Resolved' : 'Dismissed')], $common, [
                                $d('Admin Note', $note),
                                $d($isRes ? 'Resolved On' : 'Closed On', !empty($r['resolved_at']) ? date('M j, Y g:i A', $doneTs) : ''),
                            ]),
                            'link' => $roomsLink,
                        ]);
                    }
                }
                $stmt->close();
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif room issues source failed: ' . $ex->getMessage());
        }

        /* ── 5. Account / security ────────────────────────────────────── */
        try {
            $stmt = $conn->prepare("SELECT email, last_password_change FROM tbl_users WHERE faculty_id = ? LIMIT 1");
            $stmt->bind_param('s', $facultyId);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($u) {
                if (!empty($u['last_password_change'])) {
                    $pwTs = fnotif_db_timestamp($conn, $u['last_password_change'], $now);
                    if ($pwTs && $pwTs >= $now - 14 * 86400) {
                        $add([
                            'key' => 'passchange-' . $pwTs,
                            'cat' => 'system',
                            'icon' => 'lock_reset',
                            'icon_class' => 'ni-alert',
                            'title' => 'Password changed',
                            'body'  => 'Not you? Contact the administrator.',
                            'ts'    => $pwTs,
                            'detail' => [$d('Changed On', date('M j, Y g:i A', $pwTs))],
                        ]);
                    }
                }

                if (!empty($u['email']) && fnotif_table_exists($conn, 'tbl_login_attempts')) {
                    $stmt = $conn->prepare(
                        "SELECT COALESCE(SUM(attempt_count), 0) AS attempts, MAX(last_attempt) AS last_at, MAX(locked_until) AS locked_until
                           FROM tbl_login_attempts
                          WHERE identifier = ? AND login_ok = 0 AND attempt_count > 0
                            AND last_attempt >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                    );
                    $stmt->bind_param('s', $u['email']);
                    $stmt->execute();
                    $la = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($la && (int)$la['attempts'] > 0 && !empty($la['last_at'])) {
                        $n      = (int)$la['attempts'];
                        $lastTs = fnotif_db_timestamp($conn, $la['last_at'], $now);
                        $lockedUntilTs = !empty($la['locked_until'])
                            ? fnotif_db_timestamp($conn, $la['locked_until'], $now)
                            : null;
                        $locked = $lockedUntilTs !== null && $lockedUntilTs > $now;
                        $add([
                            'key' => 'failedlogin-' . $lastTs,
                            'cat' => 'system',
                            'icon' => 'gpp_maybe',
                            'icon_class' => 'ni-warn',
                            'title' => 'Failed sign-ins',
                            'body'  => '<strong>' . $n . '</strong> failed attempt' . ($n !== 1 ? 's' : '') . ', latest ' . $e(date('M j, g:i A', $lastTs)) . '.',
                            'ts'    => $lastTs,
                            'detail' => [
                                $d('Failed Attempts', $n, $n >= 5),
                                $d('Most Recent', date('M j, Y g:i A', $lastTs)),
                                $d('Account Lockout', $locked ? 'Temporarily locked until ' . date('g:i A', $lockedUntilTs) : ''),
                            ],
                        ]);
                    }
                }
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif account source failed: ' . $ex->getMessage());
        }

        /* ── 6. Administrator-authored notices ───────────────────────── */
        try {
            fnotif_ensure_admin_notice_table($conn);
            $stmt = $conn->prepare(
                'SELECT id, request_id, admin_name, title, message, created_at
                   FROM tbl_faculty_admin_notice
                  WHERE faculty_id = ?
                  ORDER BY created_at DESC
                  LIMIT 100'
            );
            if ($stmt) {
                $stmt->bind_param('s', $facultyId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($notice = $res->fetch_assoc()) {
                    $sentTs = fnotif_db_timestamp($conn, $notice['created_at'] ?? null, $now);
                    $add([
                        'key'        => 'adminnotice-' . (int)$notice['id'],
                        'cat'        => 'overdue',
                        'icon'       => 'priority_high',
                        'icon_class' => 'ni-overdue',
                        'urgent'     => true,
                        'title'      => (string)$notice['title'],
                        'body'       => $e((string)$notice['message']),
                        'ts'         => $sentTs,
                        'time_label' => 'Admin notice',
                        'detail'     => [
                            $d('Sent By', (string)$notice['admin_name']),
                            $d('Sent On', date('M j, Y g:i A', $sentTs)),
                            $d('Message', (string)$notice['message'], true),
                        ],
                        'link'       => $lendingLink,
                    ]);
                }
                $stmt->close();
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif admin notices failed: ' . $ex->getMessage());
        }

        /* ── 7. Static System notices (everyone, new and old accounts) ── */
        try {
            foreach (fnotif_static_notices() as $sn) {
                $add([
                    'key'        => 'sysnotice-' . (int)$sn['id'],
                    'cat'        => 'system',
                    'icon'       => $sn['icon'],
                    'icon_class' => $sn['icon_class'],
                    'title'      => $sn['title'],
                    'body'       => $e($sn['body']),
                    'ts'         => strtotime($sn['published']) ?: $now,
                    'time_label' => 'Notice',
                    'detail'     => [],
                    'link'       => null,
                ]);
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif static notices failed: ' . $ex->getMessage());
        }

        // Notification Preferences (Settings > Notification Preferences): categories
        // this faculty turned off, or everything when the master switch is off, are
        // never part of the feed -- so they are not listed, counted or polled.
        // This runs before the FEED_LIMIT slice so muted items never use up a slot.
        $items = notif_prefs_filter_items($items, 'faculty', notif_prefs_get($conn, 'faculty', $facultyId));

        // Only the latest stage of each request/reservation stays, then notifications of one stream become one.
        // (Superseding runs before the deleted filter so deleting a reminder does not bring the old receipt back.)
        $items = fnotif_supersede($items);

        if (!$includeDeleted) {
            $items = array_values(array_filter($items, fn($i) => !$i['is_deleted']));
        }

        $items = notif_group_merge(
            array_map(fn($i) => $i + ['merge' => fnotif_merge_id($i)], $items),
            'fnotif_compose_group'
        );

        // Urgent (overdue) first, then newest first; key as a stable tie-break.
        usort($items, function ($a, $b) {
            if ($a['urgent'] !== $b['urgent']) return $b['urgent'] <=> $a['urgent'];
            if ($a['ts'] !== $b['ts'])         return $b['ts'] <=> $a['ts'];
            return strcmp($a['key'], $b['key']);
        });

        $items = array_slice($items, 0, $FEED_LIMIT);

        // Internal flag — clients only ever receive live, non-deleted items.
        foreach ($items as &$i) unset($i['is_deleted'], $i['batch'], $i['due'], $i['unit'], $i['short'], $i['merge']);
        unset($i);

        return $items;
    }
}

if (!function_exists('fnotif_count_unread')) {
    function fnotif_count_unread(array $notifications): int
    {
        $n = 0;
        foreach ($notifications as $item) {
            if (empty($item['is_read'])) $n++;
        }
        return $n;
    }
}
