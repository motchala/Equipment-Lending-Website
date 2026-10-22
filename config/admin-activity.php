<?php

/**
 * Admin activity — the single source of truth for "what happened, and who did it".
 *
 *  • tbl_admin_activity   every MANUAL thing an admin does: request decisions and
 *                         returns, room changes, equipment changes, account changes,
 *                         rule changes and sign-ins. One row per action, always with
 *                         the acting admin's account id.
 *  • tbl_arbitration_log  the AUTOMATIC request decisions (override_by empty) — read
 *                         as-is by the activity feed, never copied.
 *  • tbl_room_arbitration_log  the automatic room-reservation decisions — same.
 *
 * Logging must never break the action being logged, so every failure is swallowed
 * (and written to the PHP error log).
 *
 * admin_activity_bootstrap() migrates older installs in place (same self-migrating
 * approach as admin-auth.php): it creates / extends the table, adds
 * tbl_arbitration_log.override_by_id, and — once — copies the older manual
 * request decisions into tbl_admin_activity so history is not lost.
 */

if (!function_exists('admin_activity_has_column')) {
    function admin_activity_has_column(mysqli $conn, string $table, string $column): bool
    {
        $res = @$conn->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE '" . $conn->real_escape_string($column) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('admin_activity_table_exists')) {
    function admin_activity_table_exists(mysqli $conn, string $table): bool
    {
        $res = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('admin_activity_bootstrap')) {
    /** Create / migrate everything the activity feature needs. Cheap after the first call. */
    function admin_activity_bootstrap(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $existed = admin_activity_table_exists($conn, 'tbl_admin_activity');

            @$conn->query(
                "CREATE TABLE IF NOT EXISTS `tbl_admin_activity` (
                    `id`         int(11) NOT NULL AUTO_INCREMENT,
                    `admin_id`   int(11) NOT NULL,
                    `admin_name` varchar(255) NOT NULL,
                    `category`   varchar(20) NOT NULL,
                    `action`     varchar(30) DEFAULT NULL,
                    `summary`    varchar(255) NOT NULL,
                    `ref_type`   varchar(12) DEFAULT NULL,
                    `ref_id`     int(11) DEFAULT NULL,
                    `ref`        varchar(40) DEFAULT NULL,
                    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `idx_admin_time` (`admin_id`, `created_at`),
                    KEY `idx_time` (`created_at`),
                    KEY `idx_ref` (`ref`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );

            // Older installs (the first version of this table had fewer columns).
            $added_ref = false;
            $alters = [
                'action'   => "ALTER TABLE `tbl_admin_activity` ADD COLUMN `action` varchar(30) DEFAULT NULL AFTER `category`",
                'ref_type' => "ALTER TABLE `tbl_admin_activity` ADD COLUMN `ref_type` varchar(12) DEFAULT NULL AFTER `summary`",
                'ref_id'   => "ALTER TABLE `tbl_admin_activity` ADD COLUMN `ref_id` int(11) DEFAULT NULL AFTER `ref_type`",
                'ref'      => "ALTER TABLE `tbl_admin_activity` ADD COLUMN `ref` varchar(40) DEFAULT NULL AFTER `ref_id`",
            ];
            foreach ($alters as $col => $sql) {
                if (!admin_activity_has_column($conn, 'tbl_admin_activity', $col)) {
                    @$conn->query($sql);
                    if ($col === 'ref') {
                        $added_ref = true;
                    }
                }
            }

            // Which admin made a manual decision (the log used to store only a name).
            $added_by_id = false;
            if (
                admin_activity_table_exists($conn, 'tbl_arbitration_log')
                && !admin_activity_has_column($conn, 'tbl_arbitration_log', 'override_by_id')
            ) {
                @$conn->query("ALTER TABLE `tbl_arbitration_log` ADD COLUMN `override_by_id` int(11) DEFAULT NULL AFTER `override_by`");
                $added_by_id = admin_activity_has_column($conn, 'tbl_arbitration_log', 'override_by_id');
            }

            // Fill override_by_id for existing rows — only for names that belong to exactly one account.
            if ($added_by_id) {
                @$conn->query(
                    "UPDATE tbl_arbitration_log l
                       JOIN (SELECT MIN(id) AS id, fullName FROM tbl_accounts GROUP BY fullName HAVING COUNT(*) = 1) a
                         ON a.fullName = l.override_by
                        SET l.override_by_id = a.id
                      WHERE l.override_by_id IS NULL AND l.override_by IS NOT NULL AND l.override_by <> ''"
                );
            }

            // One-time copy of the older manual request decisions / QR returns into the activity table.
            if (($added_ref || !$existed)
                && admin_activity_table_exists($conn, 'tbl_arbitration_log')
                && admin_activity_has_column($conn, 'tbl_arbitration_log', 'override_by_id')
            ) {
                if (!@$conn->query(
                    "INSERT INTO tbl_admin_activity (admin_id, admin_name, category, action, summary, ref_type, ref_id, ref, created_at)
                     SELECT COALESCE(l.override_by_id, 0), l.override_by, 'request',
                            CASE l.decision WHEN 'Approved' THEN 'request_approved'
                                            WHEN 'Declined' THEN 'request_declined'
                                            WHEN 'Returned' THEN 'request_returned'
                                            ELSE 'request_other' END,
                            LEFT(CASE l.decision
                                    WHEN 'Returned' THEN CONCAT('Marked ', l.equipment_name, ' as returned',
                                                                IF(l.borrower_name <> '', CONCAT(' (', l.borrower_name, ')'), ''))
                                    ELSE CONCAT(l.decision, ' request #', l.request_id, ': ', l.equipment_name,
                                                IF(l.borrower_name <> '', CONCAT(' for ', l.borrower_name), ''))
                                 END, 255),
                            'request', l.request_id, CONCAT('arb:', l.id), COALESCE(l.created_at, NOW())
                       FROM tbl_arbitration_log l
                      WHERE l.override_by IS NOT NULL AND l.override_by <> ''
                        AND NOT EXISTS (SELECT 1 FROM tbl_admin_activity t WHERE t.ref = CONCAT('arb:', l.id))"
                )) {
                    error_log('[admin-activity] backfill failed: ' . $conn->error);
                }
            }
        } catch (Throwable $e) {
            error_log('[admin-activity] bootstrap: ' . $e->getMessage());
        }
    }
}

if (!function_exists('admin_activity_ensure_table')) {
    /** Kept for older callers. */
    function admin_activity_ensure_table(mysqli $conn): void
    {
        admin_activity_bootstrap($conn);
    }
}

if (!function_exists('admin_current_id')) {
    /** tbl_accounts.id of the signed-in admin (or an explicit email), or null. */
    function admin_current_id(mysqli $conn, ?string $email = null): ?int
    {
        static $cache = [];
        $email = $email ?? (string)($_SESSION['admin_email'] ?? '');
        if ($email === '') {
            return null;
        }
        if (array_key_exists($email, $cache)) {
            return $cache[$email];
        }
        $id = null;
        try {
            if ($st = $conn->prepare('SELECT id FROM tbl_accounts WHERE email = ? LIMIT 1')) {
                $st->bind_param('s', $email);
                $st->execute();
                $st->bind_result($rid);
                if ($st->fetch()) {
                    $id = (int)$rid;
                }
                $st->close();
            }
        } catch (Throwable $e) {
            error_log('[admin-activity] admin_current_id: ' . $e->getMessage());
        }
        return $cache[$email] = $id;
    }
}

if (!function_exists('admin_log_activity')) {
    /**
     * Record something the signed-in admin just did.
     *
     * @param string      $category    login | request | room | equipment | account | faculty | settings
     * @param string      $summary     short sentence WITHOUT the admin's name, e.g. "Added equipment: HDMI Cable"
     * @param string|null $actor_email override when the session isn't set yet (sign-in)
     * @param array       $opts        action (icon key), ref_type + ref_id (what it points at), ref (dedupe key)
     */
    function admin_log_activity(mysqli $conn, string $category, string $summary, ?string $actor_email = null, array $opts = []): void
    {
        try {
            admin_activity_bootstrap($conn);

            $email = $actor_email ?? (string)($_SESSION['admin_email'] ?? '');
            if ($email === '') {
                return;
            }

            $admin_id   = null;
            $admin_name = (string)($_SESSION['admin_name'] ?? 'Admin');
            if ($st = $conn->prepare('SELECT id, fullName FROM tbl_accounts WHERE email = ? LIMIT 1')) {
                $st->bind_param('s', $email);
                $st->execute();
                $st->bind_result($rid, $rname);
                if ($st->fetch()) {
                    $admin_id = (int)$rid;
                    if ($rname !== null && $rname !== '') {
                        $admin_name = (string)$rname;
                    }
                }
                $st->close();
            }
            if ($admin_id === null) {
                return; // not an admin account — nothing to attribute this to
            }

            $summary  = mb_substr(trim($summary), 0, 255);
            $action   = isset($opts['action']) ? mb_substr((string)$opts['action'], 0, 30) : null;
            $ref_type = isset($opts['ref_type']) ? mb_substr((string)$opts['ref_type'], 0, 12) : null;
            $ref_id   = isset($opts['ref_id']) ? (int)$opts['ref_id'] : null;
            $ref      = isset($opts['ref']) ? mb_substr((string)$opts['ref'], 0, 40) : null;

            if ($ins = $conn->prepare(
                'INSERT INTO tbl_admin_activity (admin_id, admin_name, category, action, summary, ref_type, ref_id, ref)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )) {
                $ins->bind_param('isssssis', $admin_id, $admin_name, $category, $action, $summary, $ref_type, $ref_id, $ref);
                $ins->execute();
                $ins->close();
            }
        } catch (Throwable $e) {
            error_log('[admin-activity] ' . $e->getMessage());
        }
    }
}

if (!function_exists('admin_activity_item_name')) {
    /** Equipment name for a log line (falls back to the id). */
    function admin_activity_item_name(mysqli $conn, int $item_id): string
    {
        try {
            if ($st = $conn->prepare('SELECT item_name FROM tbl_inventory WHERE item_id = ? LIMIT 1')) {
                $st->bind_param('i', $item_id);
                $st->execute();
                $st->bind_result($n);
                $found = $st->fetch();
                $st->close();
                if ($found && $n !== null && $n !== '') {
                    return (string)$n;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
        return '#' . $item_id;
    }
}

if (!function_exists('admin_activity_room_name')) {
    /** Room name for a log line (falls back to the id). */
    function admin_activity_room_name(mysqli $conn, int $room_id): string
    {
        try {
            if ($st = $conn->prepare('SELECT room_name FROM tbl_rooms WHERE room_id = ? LIMIT 1')) {
                $st->bind_param('i', $room_id);
                $st->execute();
                $st->bind_result($n);
                $found = $st->fetch();
                $st->close();
                if ($found && $n !== null && $n !== '') {
                    return (string)$n;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
        return 'facility #' . $room_id;
    }
}
