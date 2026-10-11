<?php
/**
 * Admin activity history (Super Admin → Admins panel).
 *
 *  • tbl_admin_activity  – one row per thing an admin did that the app did not
 *                          already record: sign-ins, equipment changes, and
 *                          admin / faculty account changes.
 *  • Request decisions and QR returns are NOT copied here. They already live in
 *    tbl_arbitration_log (override_by = the admin's name) and the activity API
 *    merges them in when it reads, so older history shows up immediately.
 *
 * Logging must never break the action being logged, so every failure is
 * swallowed (and written to the PHP error log).
 */

if (!function_exists('admin_activity_ensure_table')) {
    /** Create the table on first use (same self-migrating approach as admin-auth.php). */
    function admin_activity_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        @$conn->query(
            "CREATE TABLE IF NOT EXISTS `tbl_admin_activity` (
                `id`         int(11) NOT NULL AUTO_INCREMENT,
                `admin_id`   int(11) NOT NULL,
                `admin_name` varchar(255) NOT NULL,
                `category`   varchar(20) NOT NULL,
                `summary`    varchar(255) NOT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_admin_time` (`admin_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

if (!function_exists('admin_log_activity')) {
    /**
     * Record something the signed-in admin just did.
     *
     * @param string      $category    login | equipment | account | faculty
     * @param string      $summary     short sentence, e.g. "Added equipment: HDMI Cable"
     * @param string|null $actor_email override when the session isn't set yet (sign-in)
     */
    function admin_log_activity(mysqli $conn, string $category, string $summary, ?string $actor_email = null): void
    {
        try {
            admin_activity_ensure_table($conn);

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

            $summary = mb_substr(trim($summary), 0, 255);
            if ($ins = $conn->prepare('INSERT INTO tbl_admin_activity (admin_id, admin_name, category, summary) VALUES (?, ?, ?, ?)')) {
                $ins->bind_param('isss', $admin_id, $admin_name, $category, $summary);
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
