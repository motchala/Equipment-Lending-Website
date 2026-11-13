<?php
/**
 * notifications/core/preferences.php
 *
 * Notification preferences (Settings > Preferences > Notifications) for BOTH
 * portals. One master switch ("all") plus a few category toggles ("families")
 * per account. Turning something off means it is not delivered in ANY form:
 *   - the bell feed, its unread badge, its live polling and the mark-all-read action
 *   - the pop-up toasts the dashboards raise on their own
 *   - the emails PUPSync sends
 *
 * Storage: tbl_notif_prefs, one row per (audience, account, preference).
 *   audience  'admin'   -> user_key = tbl_accounts.id (so it survives an email change)
 *             'faculty' -> user_key = tbl_users.faculty_id
 * A missing row means ON, so existing accounts keep getting everything until
 * they switch something off. The table is created on first use (guarded), so a
 * freshly imported lending_db.sql is not required.
 *
 * Include-only: no output, no session side effects.
 */

if (!function_exists('notif_pref_definitions')) {
    /**
     * The toggles shown in Settings, in display order.
     * key => [label, desc, icon]. The master switch is the extra key "all".
     */
    function notif_pref_definitions(string $audience): array
    {
        if ($audience === 'admin') {
            return [
                'requests' => ['label' => 'New equipment requests', 'desc' => 'A faculty member submits a borrow request waiting for your approval', 'icon' => 'assignment'],
                'overdue'  => ['label' => 'Overdue equipment',      'desc' => 'A borrowed item passes its return time',                               'icon' => 'schedule'],
                'stock'    => ['label' => 'Stock alerts',           'desc' => 'An item runs low or is out of stock',                                  'icon' => 'inventory_2'],
                'facility' => ['label' => 'Facility issues',        'desc' => 'Someone reports a problem with a room',                                'icon' => 'report_problem'],
                'security' => ['label' => 'Security alerts',        'desc' => 'Emails about unusual sign-in activity on your account',                'icon' => 'shield'],
            ];
        }
        if ($audience === 'faculty') {
            return [
                'equipment'     => ['label' => 'Equipment requests', 'desc' => 'Approvals, declines, cancellations and return confirmations', 'icon' => 'inventory_2'],
                'facilities'    => ['label' => 'Facility reservations', 'desc' => 'Reservation results, waitlist openings and room issue reports', 'icon' => 'meeting_room'],
                'reminders'     => ['label' => 'Reminders', 'desc' => 'Return due soon and upcoming reservations', 'icon' => 'alarm'],
                'overdue'       => ['label' => 'Overdue notices', 'desc' => 'Overdue alerts and notices sent by the admin', 'icon' => 'schedule'],
                'security'      => ['label' => 'Account & security', 'desc' => 'Password changes, failed sign-ins and security emails', 'icon' => 'shield'],
                'announcements' => ['label' => 'Announcements', 'desc' => 'General notices from PUPSync', 'icon' => 'campaign'],
            ];
        }
        return [];
    }
}

if (!function_exists('notif_pref_family_map')) {
    /** Feed-key prefix (the part before the first "-") => preference family. */
    function notif_pref_family_map(string $audience): array
    {
        if ($audience === 'admin') {
            return [
                'request'   => 'requests',
                'overdue'   => 'overdue',
                'lowstock'  => 'stock',
                'roomissue' => 'facility',
            ];
        }
        if ($audience === 'faculty') {
            return [
                'reqpending'      => 'equipment',
                'reqapproved'     => 'equipment',
                'reqdeclined'     => 'equipment',
                'reqcancelled'    => 'equipment',
                'reqselfcancelled' => 'equipment',
                'returned'        => 'equipment',
                'roomapproved'    => 'facilities',
                'roomdeclined'    => 'facilities',
                'roomcancelled'   => 'facilities',
                'waitavail'       => 'facilities',
                'waitlist'        => 'facilities',
                'issueopen'       => 'facilities',
                'issueresolved'   => 'facilities',
                'issuedismissed'  => 'facilities',
                'duesoon'         => 'reminders',
                'roomsoon'        => 'reminders',
                'overdue'         => 'overdue',
                'adminnotice'     => 'overdue',
                'passchange'      => 'security',
                'failedlogin'     => 'security',
                'sysnotice'       => 'announcements',
            ];
        }
        return [];
    }
}

if (!function_exists('notif_prefs_defaults')) {
    /** Everything on. */
    function notif_prefs_defaults(string $audience): array
    {
        $out = ['all' => true];
        foreach (array_keys(notif_pref_definitions($audience)) as $k) $out[$k] = true;
        return $out;
    }
}

if (!function_exists('notif_prefs_ensure_table')) {
    function notif_prefs_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS `tbl_notif_prefs` (
                    `id`         int(11)     NOT NULL AUTO_INCREMENT,
                    `audience`   varchar(10) NOT NULL,
                    `user_key`   varchar(64) NOT NULL,
                    `pref_key`   varchar(32) NOT NULL,
                    `enabled`    tinyint(1)  NOT NULL DEFAULT 1,
                    `updated_at` datetime    DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_notif_pref` (`audience`, `user_key`, `pref_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Throwable $e) {
            error_log('[PUPSync] notif_prefs_ensure_table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('notif_prefs_current_key')) {
    /** user_key of whoever is signed in for this audience ('' if nobody). */
    function notif_prefs_current_key(mysqli $conn, string $audience): string
    {
        if ($audience === 'faculty') {
            return (string)($_SESSION['faculty_id'] ?? '');
        }
        if ($audience === 'admin') {
            static $cache = [];
            $email = strtolower(trim((string)($_SESSION['admin_email'] ?? '')));
            if ($email === '') return '';
            if (isset($cache[$email])) return $cache[$email];
            $key = 'e_' . md5($email);              // fallback if the id cannot be read
            try {
                $st = $conn->prepare('SELECT id FROM tbl_accounts WHERE email = ? LIMIT 1');
                if ($st) {
                    $st->bind_param('s', $email);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    if ($row && isset($row['id'])) $key = (string)(int)$row['id'];
                }
            } catch (Throwable $e) {
                error_log('[PUPSync] notif_prefs_current_key: ' . $e->getMessage());
            }
            return $cache[$email] = $key;
        }
        return '';
    }
}

if (!function_exists('notif_prefs_get')) {
    /**
     * @return array<string,bool>  'all' + one entry per family; defaults to ON.
     */
    function notif_prefs_get(mysqli $conn, string $audience, string $userKey): array
    {
        $prefs = notif_prefs_defaults($audience);
        if ($userKey === '') return $prefs;
        try {
            notif_prefs_ensure_table($conn);
            $st = $conn->prepare('SELECT pref_key, enabled FROM tbl_notif_prefs WHERE audience = ? AND user_key = ?');
            if (!$st) return $prefs;
            $st->bind_param('ss', $audience, $userKey);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                if (array_key_exists($r['pref_key'], $prefs)) $prefs[$r['pref_key']] = ((int)$r['enabled'] === 1);
            }
            $st->close();
        } catch (Throwable $e) {
            error_log('[PUPSync] notif_prefs_get: ' . $e->getMessage());   // fail open: everything stays on
        }
        return $prefs;
    }
}

if (!function_exists('notif_prefs_save')) {
    /** Save only the given keys (unknown keys are ignored). */
    function notif_prefs_save(mysqli $conn, string $audience, string $userKey, array $changes): bool
    {
        if ($userKey === '') return false;
        $allowed = notif_prefs_defaults($audience);
        notif_prefs_ensure_table($conn);
        try {
            $st = $conn->prepare(
                'INSERT INTO tbl_notif_prefs (audience, user_key, pref_key, enabled) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = NOW()'
            );
            if (!$st) return false;
            $conn->begin_transaction();
            foreach ($changes as $k => $v) {
                if (!is_string($k) || !array_key_exists($k, $allowed)) continue;
                $on = $v ? 1 : 0;
                $st->bind_param('sssi', $audience, $userKey, $k, $on);
                $st->execute();
            }
            $conn->commit();
            $st->close();
            return true;
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $e2) {}
            error_log('[PUPSync] notif_prefs_save: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notif_prefs_reset')) {
    /** Back to defaults (everything on). */
    function notif_prefs_reset(mysqli $conn, string $audience, string $userKey): bool
    {
        if ($userKey === '') return false;
        try {
            notif_prefs_ensure_table($conn);
            $st = $conn->prepare('DELETE FROM tbl_notif_prefs WHERE audience = ? AND user_key = ?');
            if (!$st) return false;
            $st->bind_param('ss', $audience, $userKey);
            $ok = $st->execute();
            $st->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            error_log('[PUPSync] notif_prefs_reset: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notif_prefs_filter_items')) {
    /**
     * Drops feed items whose family (or the master switch) is off.
     * Items whose key has no family are kept unless the master switch is off.
     */
    function notif_prefs_filter_items(array $items, string $audience, array $prefs): array
    {
        if (empty($prefs['all'])) return [];
        $map = notif_pref_family_map($audience);
        return array_values(array_filter($items, function ($i) use ($map, $prefs) {
            $prefix = explode('-', (string)($i['key'] ?? ''), 2)[0];
            $fam = $map[$prefix] ?? null;
            return $fam === null || !empty($prefs[$fam]);
        }));
    }
}

if (!function_exists('notif_family_enabled')) {
    /** Convenience check for non-feed channels (emails, toasts). Fails open. */
    function notif_family_enabled(mysqli $conn, string $audience, string $userKey, string $family): bool
    {
        $prefs = notif_prefs_get($conn, $audience, $userKey);
        return !empty($prefs['all']) && ($prefs[$family] ?? true);
    }
}

if (!function_exists('notif_security_email_allowed_for_login')) {
    /**
     * Gate for the "unusual sign-in activity" email. $loginEmail is whatever was
     * typed at sign-in; it is matched to an admin (tbl_accounts) or a faculty
     * (tbl_users) account. Unknown account, or any error -> send it (fail open).
     */
    function notif_security_email_allowed_for_login(mysqli $conn, string $loginEmail): bool
    {
        $loginEmail = strtolower(trim($loginEmail));
        if ($loginEmail === '') return true;
        try {
            $st = $conn->prepare('SELECT id FROM tbl_accounts WHERE email = ? LIMIT 1');
            if ($st) {
                $st->bind_param('s', $loginEmail);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) return notif_family_enabled($conn, 'admin', (string)(int)$row['id'], 'security');
            }
            $st = $conn->prepare('SELECT faculty_id FROM tbl_users WHERE email = ? OR faculty_id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('ss', $loginEmail, $loginEmail);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) return notif_family_enabled($conn, 'faculty', (string)$row['faculty_id'], 'security');
            }
        } catch (Throwable $e) {
            error_log('[PUPSync] notif_security_email_allowed_for_login: ' . $e->getMessage());
        }
        return true;
    }
}
