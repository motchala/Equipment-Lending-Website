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
 *   require_once __DIR__ . '/notif-functions.php';
 *   notif_ensure_table($conn);
 *   $notifications = notif_build_list($conn);
 *   $unread        = notif_count_unread($notifications);
 */

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

if (!function_exists('notif_mark_all_read')) {
    /** Marks every key currently in the live feed as read (deleted ones are skipped automatically). */
    function notif_mark_all_read(mysqli $conn): int
    {
        $list = notif_build_list($conn);
        $n = 0;
        foreach ($list as $item) {
            if (!$item['is_read']) {
                notif_set_state($conn, $item['key'], true, null);
                $n++;
            }
        }
        return $n;
    }
}

if (!function_exists('notif_time_ago')) {
    function notif_time_ago(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) { $m = floor($diff / 60); return $m . ' min' . ($m != 1 ? 's' : '') . ' ago'; }
        if ($diff < 86400) { $h = floor($diff / 3600); return $h . ' hour' . ($h != 1 ? 's' : '') . ' ago'; }
        if ($diff < 172800) return 'Yesterday, ' . date('g:i A', strtotime($datetime));
        return date('M d, g:i A', strtotime($datetime));
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

        // ── Overdue requests ────────────────────────────────────────────
        $res = $conn->query("SELECT * FROM tbl_requests WHERE status='Overdue' ORDER BY return_date ASC LIMIT 10");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $key = 'overdue-' . $r['id'];
                $daysLate = max(0, (int)floor((time() - strtotime($r['return_date'])) / 86400));
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
                    'body'         => '<strong>' . htmlspecialchars($r['faculty_name']) . '</strong> has not returned this item. '
                                        . $daysLate . ' day' . ($daysLate != 1 ? 's' : '') . ' overdue.',
                    'time_label'   => 'Due ' . date('M d', strtotime($r['return_date'])),
                    'sort_ts'      => strtotime($r['return_date']),
                    'detail' => [
                        ['label' => 'Borrower', 'value' => $r['faculty_name'] . ' (' . $r['faculty_id'] . ')'],
                        ['label' => 'Equipment', 'value' => $r['equipment_name']],
                        ['label' => 'Due Date', 'value' => date('M d, Y', strtotime($r['return_date'])), 'danger' => true],
                        ['label' => 'Days Overdue', 'value' => $daysLate . ' day' . ($daysLate != 1 ? 's' : ''), 'danger' => true],
                        ['label' => 'Borrow Date', 'value' => date('M d, Y', strtotime($r['borrow_date']))],
                        ['label' => 'Room / Instructor', 'value' => ($r['room'] ?? '—') . ' / ' . ($r['instructor'] ?? '—')],
                    ],
                    'view_label' => 'View in Overdue',
                    'link'       => ['tab' => 'requests', 'chip' => 'rq-overdue', 'request_id' => (int)$r['id']],
                    'is_read'    => $state[$key]['is_read']    ?? false,
                    'is_deleted' => $state[$key]['is_deleted'] ?? false,
                ];
            }
        }

        // ── Pending (Waiting) requests ──────────────────────────────────
        $res = $conn->query("SELECT * FROM tbl_requests WHERE status='Waiting' ORDER BY request_date DESC LIMIT 10");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $key = 'request-' . $r['id'];
                $items[] = [
                    'key'          => $key,
                    'cat'          => 'request',
                    'group_label'  => 'Pending Requests',
                    'group_icon'   => 'assignment',
                    'group_danger' => false,
                    'icon'         => 'pending_actions',
                    'icon_class'   => 'notif-icon--maroon',
                    'urgent'       => false,
                    'title'        => 'New Borrow Request',
                    'body'         => '<strong>' . htmlspecialchars($r['faculty_name']) . '</strong> requested <strong>'
                                        . htmlspecialchars($r['equipment_name']) . '</strong> — awaiting approval.',
                    'time_label'   => notif_time_ago($r['request_date']),
                    'sort_ts'      => strtotime($r['request_date']),
                    'detail' => [
                        ['label' => 'Borrower', 'value' => $r['faculty_name'] . ' (' . $r['faculty_id'] . ')'],
                        ['label' => 'Equipment', 'value' => $r['equipment_name']],
                        ['label' => 'Borrow Date', 'value' => date('M d, Y', strtotime($r['borrow_date']))],
                        ['label' => 'Return Date', 'value' => date('M d, Y', strtotime($r['return_date']))],
                        ['label' => 'Requested On', 'value' => date('M d, Y g:i A', strtotime($r['request_date']))],
                        ['label' => 'Room / Instructor', 'value' => ($r['room'] ?? '—') . ' / ' . ($r['instructor'] ?? '—')],
                    ],
                    'view_label' => 'View in Requests',
                    'link'       => ['tab' => 'requests', 'chip' => 'rq-waiting', 'request_id' => (int)$r['id']],
                    'is_read'    => $state[$key]['is_read']    ?? false,
                    'is_deleted' => $state[$key]['is_deleted'] ?? false,
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
                  ORDER BY ri.created_at DESC LIMIT 10"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $key = 'roomissue-' . $r['id'];
                    $items[] = [
                        'key'          => $key,
                        'cat'          => 'room',
                        'group_label'  => 'Room Issues',
                        'group_icon'   => 'report_problem',
                        'group_danger' => false,
                        'icon'         => 'meeting_room',
                        'icon_class'   => 'notif-icon--warning',
                        'urgent'       => false,
                        'title'        => 'Issue Reported: ' . $r['room_name'],
                        'body'         => '<strong>' . htmlspecialchars($r['reported_by_name']) . '</strong> reported an issue — '
                                            . htmlspecialchars(mb_substr($r['description'], 0, 80)) . (mb_strlen($r['description']) > 80 ? '…' : ''),
                        'time_label'   => notif_time_ago($r['created_at']),
                        'sort_ts'      => strtotime($r['created_at']),
                        'detail' => [
                            ['label' => 'Room', 'value' => $r['room_name']],
                            ['label' => 'Location', 'value' => $r['floor_label'] . ', ' . $r['building_name']],
                            ['label' => 'Reported By', 'value' => $r['reported_by_name']],
                            ['label' => 'Description', 'value' => $r['description']],
                            ['label' => 'Reported At', 'value' => date('M d, Y g:i A', strtotime($r['created_at']))],
                        ],
                        'view_label' => 'Review Issue',
                        'link'       => ['tab' => 'rooms', 'sub' => 'rooms-issues', 'issue_id' => (int)$r['id']],
                        'is_read'    => $state[$key]['is_read']    ?? false,
                        'is_deleted' => $state[$key]['is_deleted'] ?? false,
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
              ORDER BY quantity ASC, item_name ASC LIMIT 10"
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
                    'title'        => $outOfStock ? 'Out of Stock: ' . $r['item_name'] : 'Low Stock: ' . $r['item_name'],
                    'body'         => $outOfStock
                                        ? 'This item has <strong>0 units</strong> available and cannot be lent out.'
                                        : 'Only <strong>' . (int)$r['quantity'] . ' unit' . ($r['quantity'] != 1 ? 's' : '') . '</strong> remaining — at or below the configured threshold.',
                    'time_label'   => 'Ongoing',
                    'sort_ts'      => time() - (int)$r['item_id'], // stable order, most-recently-added items first
                    'detail' => [
                        ['label' => 'Item', 'value' => $r['item_name']],
                        ['label' => 'Category', 'value' => $r['category']],
                        ['label' => 'Current Stock', 'value' => (int)$r['quantity'], 'danger' => true],
                        ['label' => 'Low Stock Threshold', 'value' => $threshold],
                    ],
                    'view_label' => 'View in Inventory',
                    'link'       => ['tab' => 'inventory', 'item_id' => (int)$r['item_id']],
                    'is_read'    => $state[$key]['is_read']    ?? false,
                    'is_deleted' => $state[$key]['is_deleted'] ?? false,
                ];
            }
        }

        if (!$includeDeleted) {
            $items = array_values(array_filter($items, fn($i) => !$i['is_deleted']));
        }

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
