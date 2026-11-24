<?php

/**
 * notif-functions.php
 *
 * Builds the admin notification feed. Notifications are NOT stored —
 * they're computed live from tbl_requests, tbl_room_issues and
 * tbl_inventory every time this runs, so the feed always reflects the
 * current state of the system. The only thing that IS persisted is
 * per-notification read/deleted state, in tbl_notif_state, keyed by a
 * stable synthetic id such as "overdue-12" or "roomissue-3".
 *
 * Included by admin-dashboard.php (initial render) and by the
 * equipment-booking/api/notif-*.php endpoints (AJAX actions + polling).
 *
 * USAGE:
 *   require_once __DIR__ . '/admin-data.php';
 *   notif_ensure_table($conn);
 *   $notifications = notif_build_list($conn);
 *   $unread        = notif_count_unread($notifications);
 */

require_once __DIR__ . '/preferences.php';
require_once __DIR__ . '/datetime.php';
require_once __DIR__ . '/grouping.php';

if (!function_exists('notif_ensure_table')) {
    /**
     * Idempotent guard — creates tbl_notif_state if this is the first run
     * against a database that hasn't had the latest lending_db.sql
     * re-imported yet. Mirrors the pattern already used for tbl_room_issues.
     */
    function notif_ensure_table(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $exists = $conn->query(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_notif_state' LIMIT 1"
        );
        if ($exists && $exists->num_rows > 0) return;

        $conn->query(
            "CREATE TABLE IF NOT EXISTS `tbl_notif_state` (
                `id`         int(11)      NOT NULL AUTO_INCREMENT,
                `notif_key`  varchar(64)  NOT NULL,
                `is_read`    tinyint(1)   NOT NULL DEFAULT 0,
                `is_deleted` tinyint(1)   NOT NULL DEFAULT 0,
                `updated_at` datetime     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_notif_key` (`notif_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

if (!function_exists('notif_state_map')) {
    /**
     * @return array<string, array{is_read:bool, is_deleted:bool}>
     */
    function notif_state_map(mysqli $conn): array
    {
        notif_ensure_table($conn);
        $map = [];
        $res = $conn->query("SELECT notif_key, is_read, is_deleted FROM tbl_notif_state");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[$row['notif_key']] = [
                    'is_read'    => (bool)$row['is_read'],
                    'is_deleted' => (bool)$row['is_deleted'],
                ];
            }
        }
        return $map;
    }
}

if (!function_exists('notif_set_state')) {
    /** Upsert read/deleted flags for one notif_key. Pass null to leave a flag untouched. */
    function notif_set_state(mysqli $conn, string $key, ?bool $isRead = null, ?bool $isDeleted = null): bool
    {
        notif_ensure_table($conn);

        $stmt = $conn->prepare(
            "INSERT INTO tbl_notif_state (notif_key, is_read, is_deleted)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                is_read    = VALUES(is_read),
                is_deleted = VALUES(is_deleted),
                updated_at = NOW()"
        );
        if (!$stmt) return false;

        // Merge with existing state so a null flag doesn't clobber the other one.
        $existing   = notif_state_map($conn)[$key] ?? ['is_read' => false, 'is_deleted' => false];
        $readVal    = $isRead    === null ? (int)$existing['is_read']    : (int)$isRead;
        $deletedVal = $isDeleted === null ? (int)$existing['is_deleted'] : (int)$isDeleted;

        $stmt->bind_param('sii', $key, $readVal, $deletedVal);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('notif_set_state_many')) {
    /**
     * Upsert read/deleted flags for MANY keys at once (one transaction) --
     * the bulk counterpart of notif_set_state(), used by api/notif-bulk.php
     * (Select / Mark read / Mark unread / Delete selected / Delete all).
     * Pass null to leave a flag untouched. Keys are checked against the live
     * feed first (deleted ones included), so only notifications that really
     * exist can ever be written.
     *
     * @return int number of keys written
     */
    function notif_set_state_many(mysqli $conn, array $keys, ?bool $isRead, ?bool $isDeleted): int
    {
        notif_ensure_table($conn);
        if ($isRead === null && $isDeleted === null) return 0;

        // A merged notification (several of one stream) stands for every notification inside it
        $expand = notif_expand_keys(notif_build_list($conn, false), notif_build_list($conn, true));
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
            $sql = "INSERT INTO tbl_notif_state (notif_key, is_read, is_deleted) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_read = VALUES(is_read), is_deleted = VALUES(is_deleted), updated_at = NOW()";
        } elseif ($isRead !== null) {
            $sql = "INSERT INTO tbl_notif_state (notif_key, is_read) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE is_read = VALUES(is_read), updated_at = NOW()";
        } else {
            $sql = "INSERT INTO tbl_notif_state (notif_key, is_deleted) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE is_deleted = VALUES(is_deleted), updated_at = NOW()";
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
                    $stmt->bind_param('sii', $key, $r, $d);
                } elseif ($isRead !== null) {
                    $r = (int)$isRead;
                    $stmt->bind_param('si', $key, $r);
                } else {
                    $d = (int)$isDeleted;
                    $stmt->bind_param('si', $key, $d);
                }
                if ($stmt->execute()) $n++;
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('[PUPSync] notif_set_state_many failed: ' . $e->getMessage());
            $n = 0;
        }
        $stmt->close();
        return $n;
    }
}

if (!function_exists('notif_mark_all_read')) {
    /** Marks every key currently in the live feed as read (deleted ones are skipped automatically). */
    function notif_mark_all_read(mysqli $conn): int
    {
        $list = notif_build_list($conn);
        $n = 0;
        foreach ($list as $item) {
            if (!$item['is_read']) {
                // a merged notification marks everything inside it
                foreach (($item['keys'] ?? [$item['key']]) as $k) {
                    notif_set_state($conn, (string)$k, true, null);
                    $n++;
                }
            }
        }
        return $n;
    }
}

if (!function_exists('notif_time_ago')) {
    function notif_time_ago(DateTimeImmutable $datetime): string
    {
        $diff = time() - $datetime->getTimestamp();
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) {
            $m = floor($diff / 60);
            return $m . ' min' . ($m != 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $h = floor($diff / 3600);
            return $h . ' hour' . ($h != 1 ? 's' : '') . ' ago';
        }
        if ($diff < 172800) return 'Yesterday, ' . $datetime->format('g:i A');
        return $datetime->format('M d, g:i A');
    }
}

if (!function_exists('notif_compose_group')) {
    /**
     * Builds the ONE notification that stands for several of the same stream:
     * overdue items of one borrower, items of one borrow request, open reports of one facility,
     * or all out-of-stock / low-stock items. Members are items from notif_build_list().
     */
    function notif_compose_group(array $members): array
    {
        $n    = count($members);
        $kind = (string)($members[0]['_m']['kind'] ?? '');
        $m0   = $members[0]['_m'] ?? [];
        $esc  = fn($v) => htmlspecialchars((string)$v);
        $list = function (array $names, int $show = 2) use ($esc): string {
            $head = array_slice($names, 0, $show);
            $more = count($names) - count($head);
            return implode(', ', array_map($esc, $head)) . ($more > 0 ? ' +' . $more . ' more' : '');
        };
        $units = array_map(fn($m) => (string)($m['_m']['unit'] ?? ''), $members);

        $rep      = $members[0];              // oldest / first listed
        $newest   = $members[0];
        foreach ($members as $m) {
            if ($m['sort_ts'] > $newest['sort_ts']) $newest = $m;
            if ($m['sort_ts'] < $rep['sort_ts'])    $rep    = $m;
        }

        switch ($kind) {
            case 'overdue':
                $maxDays = max(array_map(fn($m) => (int)$m['_m']['days'], $members));
                $title   = 'Overdue: ' . $m0['who'];
                $body    = '<strong>' . notif_plural($n, 'item') . '</strong> · ' . ($maxDays === 0 ? 'due today' : 'up to ' . notif_plural($maxDays, 'day') . ' overdue');
                $detail  = [
                    ['label' => 'Borrower', 'value' => $rep['detail'][0]['value']],
                    ['label' => 'Items', 'value' => implode(', ', $units)],
                    ['label' => 'Oldest Due', 'value' => $rep['detail'][2]['value'], 'danger' => true],
                    ['label' => 'Most Days Overdue', 'value' => $maxDays . ' day' . ($maxDays != 1 ? 's' : ''), 'danger' => true],
                ];
                $time = $rep['time_label'];
                $ts   = $rep['sort_ts'];
                $link = $rep['link'];
                break;

            case 'request':
                $title  = 'New request: ' . $m0['who'];
                $body   = '<strong>' . notif_plural($n, 'item') . '</strong> · ' . $list($units);
                $detail = [
                    ['label' => 'Borrower', 'value' => $newest['detail'][0]['value']],
                    ['label' => 'Items', 'value' => implode(', ', $units)],
                    ['label' => 'Borrow – Return', 'value' => $newest['_m']['dates']],
                    $newest['detail'][3],
                ];
                $time = $newest['time_label'];
                $ts   = $newest['sort_ts'];
                $link = $rep['link'];
                break;

            case 'issue':
                $title = 'Issue: ' . $m0['room'];
                $body  = '<strong>' . notif_plural($n, 'report') . '</strong> · latest “' . $esc(notif_clip((string)$newest['_m']['desc'], 50)) . '”';
                $detail = [$rep['detail'][0]];
                foreach ($members as $m) {
                    $detail[] = ['label' => (string)$m['_m']['who'], 'value' => notif_clip((string)$m['_m']['desc'], 140)];
                }
                $time = $newest['time_label'];
                $ts   = $newest['sort_ts'];
                $link = $rep['link'];
                break;

            case 'stock':
                $out   = !empty($m0['out']);
                $names = array_map(fn($m) => (string)$m['_m']['unit'], $members);
                $title = ($out ? 'Out of stock: ' : 'Low stock: ') . notif_plural($n, 'item');
                $body  = $list($names) . ($out ? '' : ' · ' . (int)$m0['threshold'] . ' or fewer left');
                // the card lists a short preview only; the full list is in Inventory
                $shown  = array_slice($members, 0, 8);
                $detail = [['label' => 'Items', 'value' => implode(', ', array_map(
                    fn($m) => $m['_m']['unit'] . ($out ? '' : ' (' . (int)$m['_m']['qty'] . ')'), $shown)) . ($n > 8 ? ' +' . ($n - 8) . ' more' : '')]];
                if (!$out) $detail[] = ['label' => 'Low Stock Limit', 'value' => (int)$m0['threshold']];
                $time = $rep['time_label'];
                $ts   = $rep['sort_ts'];
                $link = ['tab' => 'inventory'];
                break;

            default:
                return $members[0];
        }

        return array_merge($rep, [
            'key'        => notif_group_key('g' . preg_replace('/[^a-z]/', '', explode('-', (string)$rep['key'])[0]), $members),
            'title'      => $title,
            'body'       => $body,
            'detail'     => $detail,
            'time_label' => $time,
            'sort_ts'    => $ts,
            'link'       => $link,
            'view_label' => $kind === 'stock' ? 'View in Inventory' : $rep['view_label'],
        ]);
    }
}

if (!function_exists('notif_build_list')) {
    /**
     * Builds the full live notification feed, merged with persisted
     * read/deleted state. Deleted items are excluded unless $includeDeleted.
     *
     * Each item:
     *   key, cat, group_label, group_icon, group_danger, icon, icon_class,
     *   urgent, title, body, time_label, sort_ts, detail[], link,
     *   is_read, view_label
     */
    function notif_build_list(mysqli $conn, bool $includeDeleted = false): array
    {
        $state = notif_state_map($conn);
        $items = [];

        // ── Overdue requests (one notification per borrower) ─────────────
        $res = $conn->query("SELECT * FROM tbl_requests WHERE status='Overdue' ORDER BY return_date ASC LIMIT 60");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $key = 'overdue-' . $r['id'];
                $daysLate = max(0, (int)floor((time() - strtotime($r['return_date'])) / 86400));
                $qty  = max(1, (int)($r['borrow_qty'] ?? 1));
                $unit = $r['equipment_name'] . ($qty > 1 ? ' ×' . $qty : '');
                $late = $daysLate === 0 ? 'due today' : notif_plural($daysLate, 'day') . ' overdue';
                $items[] = [
                    'key'          => $key,
                    'cat'          => 'overdue',
                    'group_label'  => 'Overdue — Immediate Action Needed',
                    'group_icon'   => 'warning',
                    'group_danger' => true,
                    'icon'         => 'schedule',
                    'icon_class'   => 'notif-icon--danger',
                    'urgent'       => true,
                    'title'        => 'Overdue: ' . $r['equipment_name'],
                    'body'         => '<strong>' . htmlspecialchars($r['faculty_name']) . '</strong> · ' . $late,
                    'time_label'   => 'Due ' . date('M d', strtotime($r['return_date'])),
                    'sort_ts'      => strtotime($r['return_date']),
                    'detail' => [
                        ['label' => 'Borrower', 'value' => $r['faculty_name'] . ' (' . $r['faculty_id'] . ')'],
                        ['label' => 'Equipment', 'value' => $unit],
                        ['label' => 'Due Date', 'value' => date('M d, Y', strtotime($r['return_date'])), 'danger' => true],
                        ['label' => 'Days Overdue', 'value' => $daysLate . ' day' . ($daysLate != 1 ? 's' : ''), 'danger' => true],
                        ['label' => 'Facility', 'value' => $r['room'] ?? '—'],
                    ],
                    'view_label' => 'View in Loans',
                    'link'       => ['tab' => 'inventory', 'sub' => 'eq-onloan', 'chip' => 'overdue', 'request_id' => (int)$r['id']],
                    'is_read'    => $state[$key]['is_read']    ?? false,
                    'is_deleted' => $state[$key]['is_deleted'] ?? false,
                    'merge'      => 'overdue|' . $r['faculty_id'],
                    '_m'         => ['kind' => 'overdue', 'who' => (string)$r['faculty_name'], 'unit' => $unit, 'days' => $daysLate,
                                     'due' => date('M d', strtotime($r['return_date']))],
                ];
            }
        }

        // ── Pending (Waiting) requests (items of one request become one notification) ──
        $res = $conn->query("SELECT * FROM tbl_requests WHERE status='Waiting' ORDER BY request_date DESC LIMIT 60");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $key = 'request-' . $r['id'];
                $requestedAt = notif_datetime($conn, (string)$r['request_date']);
                $qty   = max(1, (int)($r['borrow_qty'] ?? 1));
                $unit  = $r['equipment_name'] . ($qty > 1 ? ' ×' . $qty : '');
                $dates = date('M j', strtotime($r['borrow_date'])) . ' – ' . date('M j', strtotime($r['return_date']));
                $batch = trim((string)($r['batch_id'] ?? ''));
                $items[] = [
                    'key'          => $key,
                    'cat'          => 'request',
                    'group_label'  => 'Pending Requests',
                    'group_icon'   => 'assignment',
                    'group_danger' => false,
                    'icon'         => 'pending_actions',
                    'icon_class'   => 'notif-icon--maroon',
                    'urgent'       => false,
                    'title'        => 'New request: ' . $r['faculty_name'],
                    'body'         => '<strong>' . htmlspecialchars($unit) . '</strong> · ' . htmlspecialchars($dates),
                    'time_label'   => notif_time_ago($requestedAt),
                    'sort_ts'      => $requestedAt->getTimestamp(),
                    'detail' => [
                        ['label' => 'Borrower', 'value' => $r['faculty_name'] . ' (' . $r['faculty_id'] . ')'],
                        ['label' => 'Equipment', 'value' => $unit],
                        ['label' => 'Borrow – Return', 'value' => $dates],
                        ['label' => 'Facility / Instructor', 'value' => ($r['room'] ?? '—') . ' / ' . ($r['instructor'] ?? '—')],
                    ],
                    'view_label' => 'View in Loans',
                    'link'       => ['tab' => 'inventory', 'sub' => 'eq-onloan', 'chip' => 'waiting', 'request_id' => (int)$r['id']],
                    'is_read'    => $state[$key]['is_read']    ?? false,
                    'is_deleted' => $state[$key]['is_deleted'] ?? false,
                    'merge'      => $batch !== '' ? 'req|' . $batch : '',
                    '_m'         => ['kind' => 'request', 'who' => (string)$r['faculty_name'], 'unit' => $unit, 'dates' => $dates],
                ];
            }
        }

        // ── Open room issues ────────────────────────────────────────────
        $issuesTbl = $conn->query(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_room_issues' LIMIT 1"
        );
        if ($issuesTbl && $issuesTbl->num_rows > 0) {
            $res = $conn->query(
                "SELECT ri.id, ri.room_id, ri.reported_by_name, ri.description, ri.created_at,
                        r.room_name,
                        b.name AS building_name,
                        COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
                   FROM tbl_room_issues ri
                   JOIN tbl_rooms     r ON r.room_id     = ri.room_id
                   JOIN tbl_buildings b ON b.building_id = r.building_id
                  WHERE ri.status = 'Open'
                  ORDER BY ri.created_at DESC LIMIT 60"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $key = 'roomissue-' . $r['id'];
                    $reportedAt = notif_datetime($conn, (string)$r['created_at']);
                    $items[] = [
                        'key'          => $key,
                        'cat'          => 'room',
                        'group_label'  => 'Facility Issues',
                        'group_icon'   => 'report_problem',
                        'group_danger' => false,
                        'icon'         => 'meeting_room',
                        'icon_class'   => 'notif-icon--warning',
                        'urgent'       => false,
                        'title'        => 'Issue: ' . $r['room_name'],
                        'body'         => '<strong>' . htmlspecialchars($r['reported_by_name']) . '</strong> · “' . htmlspecialchars(notif_clip((string)$r['description'], 60)) . '”',
                        'time_label'   => notif_time_ago($reportedAt),
                        'sort_ts'      => $reportedAt->getTimestamp(),
                        'detail' => [
                            ['label' => 'Location', 'value' => $r['floor_label'] . ', ' . $r['building_name']],
                            ['label' => 'Reported By', 'value' => $r['reported_by_name']],
                            ['label' => 'Description', 'value' => $r['description']],
                            ['label' => 'Reported At', 'value' => $reportedAt->format('M d, Y g:i A')],
                        ],
                        'view_label' => 'Review Issue',
                        'link'       => ['tab' => 'rooms', 'sub' => 'rooms-issues', 'issue_id' => (int)$r['id']],
                        'is_read'    => $state[$key]['is_read']    ?? false,
                        'is_deleted' => $state[$key]['is_deleted'] ?? false,
                        'merge'      => 'issue|' . (int)$r['room_id'],
                        '_m'         => ['kind' => 'issue', 'room' => (string)$r['room_name'], 'who' => (string)$r['reported_by_name'],
                                         'desc' => (string)$r['description']],
                    ];
                }
            }
        }

        // ── Low / out of stock inventory ────────────────────────────────
        $threshold = 2;
        $cfg = $conn->query("SELECT config_value FROM tbl_arbitration_config WHERE config_key='low_stock_threshold' LIMIT 1");
        if ($cfg && $row = $cfg->fetch_assoc()) {
            $threshold = max(0, (int)$row['config_value']);
        }
        $res = $conn->query(
            "SELECT item_id, item_name, category, quantity FROM tbl_inventory
              WHERE is_archived = 0 AND quantity <= " . (int)$threshold . "
              ORDER BY quantity ASC, item_name ASC LIMIT 60"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $key = 'lowstock-' . $r['item_id'];
                $outOfStock = ((int)$r['quantity'] === 0);
                $items[] = [
                    'key'          => $key,
                    'cat'          => 'system',
                    'group_label'  => 'System — Stock Alerts',
                    'group_icon'   => 'inventory_2',
                    'group_danger' => false,
                    'icon'         => $outOfStock ? 'production_quantity_limits' : 'inventory_2',
                    'icon_class'   => $outOfStock ? 'notif-icon--danger' : 'notif-icon--info',
                    'urgent'       => false,
                    'title'        => ($outOfStock ? 'Out of stock: ' : 'Low stock: ') . $r['item_name'],
                    'body'         => $outOfStock
                        ? '<strong>0 units</strong> left — cannot be lent out.'
                        : '<strong>' . notif_plural((int)$r['quantity'], 'unit') . '</strong> left.',
                    'time_label'   => 'Ongoing',
                    'sort_ts'      => time() - (int)$r['item_id'], // stable order, most-recently-added items first
                    'detail' => [
                        ['label' => 'Category', 'value' => $r['category']],
                        ['label' => 'Current Stock', 'value' => (int)$r['quantity'], 'danger' => true],
                        ['label' => 'Low Stock Limit', 'value' => $threshold],
                    ],
                    'view_label' => 'View in Inventory',
                    'link'       => ['tab' => 'inventory', 'item_id' => (int)$r['item_id']],
                    'is_read'    => $state[$key]['is_read']    ?? false,
                    'is_deleted' => $state[$key]['is_deleted'] ?? false,
                    'merge'      => $outOfStock ? 'stock|out' : 'stock|low',
                    '_m'         => ['kind' => 'stock', 'unit' => (string)$r['item_name'], 'qty' => (int)$r['quantity'], 'out' => $outOfStock, 'threshold' => $threshold],
                ];
            }
        }

        // Notification Preferences (Settings > Preferences): categories this admin
        // turned off, or everything when the master switch is off, are never part of
        // the feed -- so they are not listed, counted, polled or marked read either.
        $items = notif_prefs_filter_items(
            $items,
            'admin',
            notif_prefs_get($conn, 'admin', notif_prefs_current_key($conn, 'admin'))
        );

        if (!$includeDeleted) {
            $items = array_values(array_filter($items, fn($i) => !$i['is_deleted']));
        }

        // Notifications of one stream become one (see notif_compose_group)
        $items = notif_group_merge($items, 'notif_compose_group');
        foreach ($items as &$i) {
            unset($i['_m']);
            // lets the open notification list notice that a merged card's content changed
            $i['sig'] = substr(md5($i['title'] . '|' . $i['body']), 0, 8);
        }
        unset($i);

        // Sort: urgent groups first (overdue > request > room > system), then most recent within group.
        $groupOrder = ['overdue' => 0, 'request' => 1, 'room' => 2, 'system' => 3];
        usort($items, function ($a, $b) use ($groupOrder) {
            $ga = $groupOrder[$a['cat']] ?? 9;
            $gb = $groupOrder[$b['cat']] ?? 9;
            if ($ga !== $gb) return $ga <=> $gb;
            return $b['sort_ts'] <=> $a['sort_ts'];
        });

        return $items;
    }
}

if (!function_exists('notif_count_unread')) {
    function notif_count_unread(array $notifications): int
    {
        $n = 0;
        foreach ($notifications as $item) {
            if (!$item['is_read']) $n++;
        }
        return $n;
    }
}
