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
 *   tbl_requests            → submitted / approved / declined / due soon /
 *                             overdue / returned   (+ tbl_arbitration_log
 *                             for the decision rule, reason and admin notes)
 *   tbl_room_reservations   → confirmed / reminder / declined / cancelled
 *                             (+ tbl_room_arbitration_log for the rule)
 *   tbl_room_waitlist       → on waitlist / slot now available
 *   tbl_room_issues         → issue received / resolved / dismissed
 *   tbl_users               → recent password change
 *   tbl_login_attempts      → failed sign-in attempts on the account
 *
 * Every source is optional: if a table/column hasn't been migrated yet, or a
 * query fails, that one source is skipped (and logged) instead of taking the
 * whole feed down.
 *
 * USAGE:
 *   require_once __DIR__ . '/faculty-notif-functions.php';
 *   $list   = fnotif_build_list($conn, $_SESSION['faculty_id']);
 *   $unread = fnotif_count_unread($list);
 */

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

        $valid = array_flip(array_column(fnotif_build_list($conn, $facultyId, true), 'key'));
        $keys  = array_values(array_unique(array_filter(
            $keys,
            fn($k) => is_string($k) && preg_match('/^[a-z]+-\d+$/', $k) && isset($valid[$k])
        )));
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
                    $r = (int)$isRead; $d = (int)$isDeleted;
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
        $today = strtotime('today');
        if ($ts >= $today)                 return date('g:i A', $ts);
        if ($ts >= $today - 86400)         return 'Yesterday';
        if (date('Y', $ts) === date('Y'))  return date('M j', $ts);
        return date('M j, Y', $ts);
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
        ];
        if (isset($map[$rule])) return $map[$rule];
        return ucfirst(trim(str_replace('_', ' ', preg_replace('/^rule_(\d+_)?/', '', $rule))));
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
        $add = function (array $n) use (&$items, $state, $now, $todayTs) {
            $ts  = min((int)$n['ts'], $now);
            $n['ts'] = $ts;
            $n['urgent']     = $n['urgent'] ?? false;
            $n['time_label'] = $n['time_label'] ?? fnotif_time_label($ts);
            $n['detail']     = array_values(array_filter($n['detail'] ?? []));
            $n['link']       = $n['link'] ?? null;
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
                $reqTs   = strtotime($r['request_date']) ?: $now;
                $decidedTs = (!empty($r['log_at']) && in_array($r['log_decision'] ?? '', ['Approved', 'Declined'], true))
                           ? (strtotime($r['log_at']) ?: $reqTs) : $reqTs;
                $borrowStr = date('M j, Y', strtotime($r['borrow_date']));
                $returnStr = date('M j, Y', strtotime($r['return_date']));
                $override  = !empty($r['log_override_by']);
                $common = [
                    $d('Equipment', $equip),
                    $d('Borrow Date', $borrowStr),
                    $d('Return Date', $returnStr),
                    $d('Room / Instructor', (($r['room'] ?? '') !== '' ? $r['room'] : '—') . ' / ' . (($r['instructor'] ?? '') !== '' ? $r['instructor'] : '—')),
                ];
                $submittedBy = (($r['submitted_as'] ?? '') === 'adviser' && !empty($r['submitted_by_name']))
                    ? $d('Submitted By', $r['submitted_by_name'] . ' (organization adviser)') : null;

                switch ($status) {
                    case 'Waiting':
                        $add([
                            'key' => 'reqpending-' . $id, 'cat' => 'borrow', 'icon' => 'hourglass_top', 'icon_class' => 'ni-alert',
                            'title' => 'Request Submitted: ' . $equip,
                            'body'  => 'Your request for <strong>' . $e($equip) . '</strong> is awaiting admin approval.',
                            'ts'    => $reqTs,
                            'detail' => array_merge([$d('Status', 'Waiting for approval')], $common, [
                                $submittedBy,
                                $d('Submitted On', date('M j, Y g:i A', $reqTs)),
                            ]),
                            'link' => $lendingLink,
                        ]);
                        break;

                    case 'Approved':
                        $add([
                            'key' => 'reqapproved-' . $id, 'cat' => 'borrow', 'icon' => 'check_circle', 'icon_class' => 'ni-success',
                            'title' => 'Borrow Request Approved: ' . $equip,
                            'body'  => 'Your request for <strong>' . $e($equip) . '</strong> was approved. Borrow on <strong>'
                                        . $e(date('M j', strtotime($r['borrow_date']))) . '</strong>, return by <strong>'
                                        . $e(date('M j', strtotime($r['return_date']))) . '</strong>.',
                            'ts'    => $decidedTs,
                            'detail' => array_merge([$d('Status', 'Approved')], $common, [
                                $submittedBy,
                                $d('Decision Rule', fnotif_rule_label($r['log_rule'] ?? $r['arbitration_rule'] ?? null)),
                                $d('Admin Note', $r['log_override_reason'] ?? ''),
                                $d('Return QR', !empty($r['return_token']) ? 'Available in My Requests' : ''),
                                $d('Requested On', date('M j, Y g:i A', $reqTs)),
                            ]),
                            'link' => $lendingLink,
                        ]);

                        // Return reminder: due today / tomorrow / in 2 days
                        $daysLeft = (int)round((strtotime($r['return_date']) - $todayTs) / 86400);
                        if ($daysLeft >= 0 && $daysLeft <= 2) {
                            $when = $daysLeft === 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : 'in 2 days');
                            $add([
                                'key' => 'duesoon-' . $id, 'cat' => 'borrow', 'icon' => 'alarm', 'icon_class' => 'ni-warn',
                                'title' => 'Return Reminder: ' . $equip,
                                'body'  => '<strong>' . $e($equip) . '</strong> is due <strong>' . $when . '</strong> (' . $e(date('M j', strtotime($r['return_date'])))
                                            . '). Please return it on time to avoid penalties.',
                                'ts'    => $todayTs,
                                'time_label' => $daysLeft === 0 ? 'Due today' : ($daysLeft === 1 ? 'Due tomorrow' : 'Due ' . date('M j', strtotime($r['return_date']))),
                                'detail' => [
                                    $d('Equipment', $equip),
                                    $d('Due Date', $returnStr, $daysLeft === 0),
                                    $d('Time Left', $daysLeft === 0 ? 'Due today' : ($daysLeft === 1 ? '1 day' : '2 days'), $daysLeft === 0),
                                    $d('Room / Instructor', (($r['room'] ?? '') !== '' ? $r['room'] : '—') . ' / ' . (($r['instructor'] ?? '') !== '' ? $r['instructor'] : '—')),
                                ],
                                'link' => $lendingLink,
                            ]);
                        }
                        break;

                    case 'Declined':
                        if ($decidedTs < $windowTs) break;
                        $reason = trim((string)($r['reason'] ?? '')) !== '' ? trim($r['reason']) : trim((string)($r['log_reason'] ?? ''));
                        $add([
                            'key' => 'reqdeclined-' . $id, 'cat' => 'borrow', 'icon' => 'cancel', 'icon_class' => 'ni-overdue',
                            'title' => 'Borrow Request Declined: ' . $equip,
                            'body'  => 'Your request for <strong>' . $e($equip) . '</strong> was declined.'
                                        . ($reason !== '' ? ' Reason: ' . $e(fnotif_clip($reason, 120)) : ''),
                            'ts'    => $decidedTs,
                            'detail' => array_merge([$d('Status', 'Declined')], $common, [
                                $d('Reason', $reason, true),
                                $d('Decision Rule', fnotif_rule_label($r['log_rule'] ?? $r['arbitration_rule'] ?? null)),
                                $d('Decision', $override ? 'Overridden by an administrator' : ''),
                                $d('Admin Note', $r['log_override_reason'] ?? ''),
                                $d('Requested On', date('M j, Y g:i A', $reqTs)),
                            ]),
                            'link' => $lendingLink,
                        ]);
                        break;

                    case 'Overdue':
                        $dueTs    = strtotime($r['return_date']);
                        $daysLate = max(0, (int)floor(($now - $dueTs) / 86400));
                        $lateStr  = $daysLate . ' day' . ($daysLate !== 1 ? 's' : '');
                        $add([
                            'key' => 'overdue-' . $id, 'cat' => 'overdue', 'icon' => 'schedule', 'icon_class' => 'ni-overdue',
                            'urgent' => true,
                            'title' => 'Overdue: ' . $equip,
                            'body'  => 'You have not returned <strong>' . $e($equip) . '</strong>. ' . $lateStr . ' overdue.',
                            'ts'    => $dueTs,
                            'time_label' => 'Due ' . date('M j', $dueTs),
                            'detail' => [
                                $d('Equipment', $equip),
                                $d('Borrow Date', $borrowStr),
                                $d('Due Date', $returnStr, true),
                                $d('Days Overdue', $lateStr, true),
                                $d('Room / Instructor', (($r['room'] ?? '') !== '' ? $r['room'] : '—') . ' / ' . (($r['instructor'] ?? '') !== '' ? $r['instructor'] : '—')),
                                $d('Return QR', !empty($r['return_token']) ? 'Available in My Requests' : ''),
                            ],
                            'link' => $lendingLink,
                        ]);
                        break;

                    case 'Returned':
                        $retTs = !empty($r['returned_at']) ? strtotime($r['returned_at'])
                               : (!empty($r['log_at']) ? strtotime($r['log_at']) : strtotime($r['return_date']));
                        if ($retTs < $windowTs) break;
                        $onTime = $retTs <= strtotime($r['return_date'] . ' 23:59:59');
                        $add([
                            'key' => 'returned-' . $id, 'cat' => 'borrow', 'icon' => 'task_alt', 'icon_class' => 'ni-success',
                            'title' => 'Return Confirmed: ' . $equip,
                            'body'  => '<strong>' . $e($equip) . '</strong> was returned on ' . $e(date('M j, g:i A', $retTs)) . '. Thank you!',
                            'ts'    => $retTs,
                            'detail' => [
                                $d('Equipment', $equip),
                                $d('Borrow Date', $borrowStr),
                                $d('Due Date', $returnStr),
                                $d('Returned On', date('M j, Y g:i A', $retTs)),
                                $d('Timeliness', $onTime ? 'Returned on time' : 'Returned after the due date', !$onTime),
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

        /* ── 2. Room reservations ─────────────────────────────────────── */
        try {
            if (fnotif_table_exists($conn, 'tbl_room_reservations')
                && fnotif_table_exists($conn, 'tbl_rooms')
                && fnotif_table_exists($conn, 'tbl_buildings')) {

                $hasCancelled = fnotif_col_exists($conn, 'tbl_room_reservations', 'cancelled_at');
                $hasRoomLog   = fnotif_table_exists($conn, 'tbl_room_arbitration_log');

                $sql = "SELECT rr.id, rr.reservation_date, rr.start_time, rr.end_time, rr.purpose, rr.attendees,
                               rr.status, rr.reason, rr.request_date, rr.submitted_as, rr.submitted_by_name,
                               r.room_name, b.name AS building_name,
                               COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label"
                     . ($hasCancelled ? ", rr.cancelled_at" : ", NULL AS cancelled_at")
                     . ($hasRoomLog   ? ", rl.rule_applied AS log_rule" : ", NULL AS log_rule")
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
                    $reqTs  = strtotime($r['request_date']) ?: $now;
                    $startTs = strtotime($r['reservation_date'] . ' ' . $r['start_time']);
                    $endTs   = strtotime($r['reservation_date'] . ' ' . $r['end_time']);
                    $dateStr = date('M j, Y', strtotime($r['reservation_date']));
                    $timeStr = date('g:i A', $startTs) . ' – ' . date('g:i A', $endTs);
                    $slot    = date('M j', strtotime($r['reservation_date'])) . ', ' . $timeStr;
                    $common = [
                        $d('Room', $room),
                        $d('Location', $r['floor_label'] . ', ' . $r['building_name']),
                        $d('Date', $dateStr),
                        $d('Time', $timeStr),
                        $d('Purpose', $r['purpose']),
                        $d('Attendees', $r['attendees']),
                    ];

                    if ($status === 'Approved') {
                        $upcoming = $endTs >= $now;
                        if ($upcoming || $reqTs >= $windowTs) {
                            $add([
                                'key' => 'roomapproved-' . $id, 'cat' => 'room', 'icon' => 'meeting_room', 'icon_class' => 'ni-success',
                                'title' => 'Room Reserved: ' . $room,
                                'body'  => 'Your reservation for <strong>' . $e($room) . '</strong> on <strong>' . $e($slot) . '</strong> is confirmed.',
                                'ts'    => $reqTs,
                                'detail' => array_merge([$d('Status', 'Confirmed')], $common, [
                                    (($r['submitted_as'] ?? '') === 'adviser' && !empty($r['submitted_by_name']))
                                        ? $d('Submitted By', $r['submitted_by_name'] . ' (organization adviser)') : null,
                                    $d('Decision Rule', fnotif_rule_label($r['log_rule'] ?? null)),
                                    $d('Reserved On', date('M j, Y g:i A', $reqTs)),
                                ]),
                                'link' => $resvLink,
                            ]);
                        }
                        // Reminder: today (not yet over) or tomorrow
                        $dayDiff = (int)round((strtotime($r['reservation_date']) - $todayTs) / 86400);
                        if (($dayDiff === 0 && $endTs >= $now) || $dayDiff === 1) {
                            $isToday = $dayDiff === 0;
                            $add([
                                'key' => 'roomsoon-' . $id, 'cat' => 'room', 'icon' => 'event_upcoming', 'icon_class' => 'ni-warn',
                                'title' => 'Reservation ' . ($isToday ? 'Today' : 'Tomorrow') . ': ' . $room,
                                'body'  => 'You have <strong>' . $e($room) . '</strong> reserved <strong>' . ($isToday ? 'today' : 'tomorrow')
                                            . '</strong> from ' . $e($timeStr) . '.',
                                'ts'    => $todayTs,
                                'time_label' => $isToday ? 'Today' : 'Tomorrow',
                                'detail' => $common,
                                'link'   => $resvLink,
                            ]);
                        }
                    } elseif ($status === 'Declined') {
                        if ($reqTs < $windowTs) continue;
                        $reason = trim((string)($r['reason'] ?? ''));
                        $add([
                            'key' => 'roomdeclined-' . $id, 'cat' => 'room', 'icon' => 'event_busy', 'icon_class' => 'ni-overdue',
                            'title' => 'Reservation Declined: ' . $room,
                            'body'  => 'Your reservation for <strong>' . $e($room) . '</strong> on ' . $e($slot) . ' was declined.'
                                        . ($reason !== '' ? ' Reason: ' . $e(fnotif_clip($reason, 120)) : ''),
                            'ts'    => $reqTs,
                            'detail' => array_merge([$d('Status', 'Declined')], $common, [
                                $d('Reason', $reason, true),
                                $d('Decision Rule', fnotif_rule_label($r['log_rule'] ?? null)),
                                $d('Requested On', date('M j, Y g:i A', $reqTs)),
                            ]),
                            'link' => $resvLink,
                        ]);
                    } elseif ($status === 'Cancelled') {
                        $cTs = !empty($r['cancelled_at']) ? (strtotime($r['cancelled_at']) ?: $reqTs) : $reqTs;
                        if ($cTs < $windowTs) continue;
                        $add([
                            'key' => 'roomcancelled-' . $id, 'cat' => 'room', 'icon' => 'event_busy', 'icon_class' => 'ni-alert',
                            'title' => 'Reservation Cancelled: ' . $room,
                            'body'  => 'Your reservation for <strong>' . $e($room) . '</strong> on ' . $e($slot) . ' was cancelled.',
                            'ts'    => $cTs,
                            'detail' => array_merge([$d('Status', 'Cancelled')], $common, [
                                $d('Reason', $r['reason'] ?? ''),
                                $d('Cancelled On', date('M j, Y g:i A', $cTs)),
                            ]),
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
            if (fnotif_table_exists($conn, 'tbl_room_waitlist')
                && fnotif_table_exists($conn, 'tbl_room_reservations')
                && fnotif_table_exists($conn, 'tbl_rooms')
                && fnotif_table_exists($conn, 'tbl_buildings')) {

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
                    $joinTs  = strtotime($r['created_at']) ?: $now;
                    $timeStr = date('g:i A', strtotime($r['start_time'])) . ' – ' . date('g:i A', strtotime($r['end_time']));
                    $slot    = date('M j', strtotime($r['reservation_date'])) . ', ' . $timeStr;
                    $common = [
                        $d('Room', $room),
                        $d('Location', $r['floor_label'] . ', ' . $r['building_name']),
                        $d('Date', date('M j, Y', strtotime($r['reservation_date']))),
                        $d('Time', $timeStr),
                        $d('Joined Waitlist', date('M j, Y g:i A', $joinTs)),
                    ];
                    if ((int)$r['taken'] === 0) {
                        $add([
                            'key' => 'waitavail-' . $id, 'cat' => 'room', 'icon' => 'event_available', 'icon_class' => 'ni-success',
                            'title' => 'Slot Now Available: ' . $room,
                            'body'  => 'The <strong>' . $e($slot) . '</strong> slot you waitlisted for <strong>' . $e($room) . '</strong> is open. Book it before someone else does.',
                            'ts'    => $todayTs,
                            'time_label' => 'Available now',
                            'detail' => array_merge([$d('Status', 'Slot is open')], $common),
                            'link'   => $roomsLink,
                        ]);
                    } else {
                        $add([
                            'key' => 'waitlist-' . $id, 'cat' => 'room', 'icon' => 'hourglass_top', 'icon_class' => 'ni-alert',
                            'title' => 'On Waitlist: ' . $room,
                            'body'  => 'You\'re on the waitlist for <strong>' . $e($room) . '</strong> on ' . $e($slot) . '. You\'ll be emailed if the slot opens up.',
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
            if (fnotif_table_exists($conn, 'tbl_room_issues')
                && fnotif_table_exists($conn, 'tbl_rooms')
                && fnotif_table_exists($conn, 'tbl_buildings')) {

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
                    $crTs   = strtotime($r['created_at']) ?: $now;
                    $snip   = fnotif_clip((string)$r['description'], 90);
                    $common = [
                        $d('Room', $room),
                        $d('Location', $r['floor_label'] . ', ' . $r['building_name']),
                        $d('Your Report', $r['description']),
                        $d('Reported On', date('M j, Y g:i A', $crTs)),
                    ];
                    if ($status === 'Open') {
                        $add([
                            'key' => 'issueopen-' . $id, 'cat' => 'room', 'icon' => 'report_problem', 'icon_class' => 'ni-alert',
                            'title' => 'Issue Report Received: ' . $room,
                            'body'  => 'Your report is with the admin team — “' . $e($snip) . '”',
                            'ts'    => $crTs,
                            'detail' => array_merge([$d('Status', 'Open — under review')], $common),
                            'link'   => $roomsLink,
                        ]);
                    } elseif ($status === 'Resolved' || $status === 'Dismissed') {
                        $doneTs = !empty($r['resolved_at']) ? (strtotime($r['resolved_at']) ?: $crTs) : $crTs;
                        if ($doneTs < $windowTs) continue;
                        $isRes = $status === 'Resolved';
                        $note  = trim((string)($r['admin_notes'] ?? ''));
                        $add([
                            'key' => ($isRes ? 'issueresolved-' : 'issuedismissed-') . $id, 'cat' => 'room',
                            'icon' => $isRes ? 'build_circle' : 'report_off', 'icon_class' => $isRes ? 'ni-success' : 'ni-alert',
                            'title' => ($isRes ? 'Issue Resolved: ' : 'Issue Report Closed: ') . $room,
                            'body'  => 'Your report about <strong>' . $e($room) . '</strong> was ' . ($isRes ? 'resolved' : 'reviewed and closed') . '.'
                                        . ($note !== '' ? ' Admin note: ' . $e(fnotif_clip($note, 120)) : ''),
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
                    $pwTs = strtotime($u['last_password_change']);
                    if ($pwTs && $pwTs >= $now - 14 * 86400) {
                        $add([
                            'key' => 'passchange-' . $pwTs, 'cat' => 'system', 'icon' => 'lock_reset', 'icon_class' => 'ni-alert',
                            'title' => 'Password Changed',
                            'body'  => 'Your account password was changed. If this wasn\'t you, contact the administrator right away.',
                            'ts'    => $pwTs,
                            'detail' => [$d('Changed On', date('M j, Y g:i A', $pwTs))],
                        ]);
                    }
                }

                if (!empty($u['email']) && fnotif_table_exists($conn, 'tbl_login_attempts')) {
                    $since = date('Y-m-d H:i:s', $now - 7 * 86400);
                    $stmt = $conn->prepare(
                        "SELECT COALESCE(SUM(attempt_count), 0) AS attempts, MAX(last_attempt) AS last_at, MAX(locked_until) AS locked_until
                           FROM tbl_login_attempts
                          WHERE identifier = ? AND login_ok = 0 AND attempt_count > 0 AND last_attempt >= ?"
                    );
                    $stmt->bind_param('ss', $u['email'], $since);
                    $stmt->execute();
                    $la = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($la && (int)$la['attempts'] > 0 && !empty($la['last_at'])) {
                        $n      = (int)$la['attempts'];
                        $lastTs = strtotime($la['last_at']);
                        $locked = !empty($la['locked_until']) && strtotime($la['locked_until']) > $now;
                        $add([
                            'key' => 'failedlogin-' . $lastTs, 'cat' => 'system', 'icon' => 'gpp_maybe', 'icon_class' => 'ni-warn',
                            'title' => 'Failed Sign-in Attempts',
                            'body'  => '<strong>' . $n . '</strong> failed sign-in attempt' . ($n !== 1 ? 's were' : ' was')
                                        . ' recorded on your account, most recently ' . $e(date('M j, g:i A', $lastTs))
                                        . '. If this wasn\'t you, consider changing your password.',
                            'ts'    => $lastTs,
                            'detail' => [
                                $d('Failed Attempts', $n, $n >= 5),
                                $d('Most Recent', date('M j, Y g:i A', $lastTs)),
                                $d('Account Lockout', $locked ? 'Temporarily locked until ' . date('g:i A', strtotime($la['locked_until'])) : ''),
                            ],
                        ]);
                    }
                }
            }
        } catch (Throwable $ex) {
            error_log('[PUPSync] fnotif account source failed: ' . $ex->getMessage());
        }

        if (!$includeDeleted) {
            $items = array_values(array_filter($items, fn($i) => !$i['is_deleted']));
        }

        // Urgent (overdue) first, then newest first; key as a stable tie-break.
        usort($items, function ($a, $b) {
            if ($a['urgent'] !== $b['urgent']) return $b['urgent'] <=> $a['urgent'];
            if ($a['ts'] !== $b['ts'])         return $b['ts'] <=> $a['ts'];
            return strcmp($a['key'], $b['key']);
        });

        $items = array_slice($items, 0, $FEED_LIMIT);

        // Internal flag — clients only ever receive live, non-deleted items.
        foreach ($items as &$i) unset($i['is_deleted']);
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
