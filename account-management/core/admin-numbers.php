<?php

/**
 * admin-numbers.php - the "Admin 1 ... Admin 5" seat numbers.
 *
 * Admin 1 is always the Super Admin. Each admin account the Super Admin creates gets the
 * lowest free number from 2 to 5, so the first one added is Admin 2, the next Admin 3, and
 * so on up to Admin 5 (the 5-seat limit). A number belongs to the account: nobody is
 * renumbered when another admin is deleted, and a deleted admin's number is handed to the
 * next admin that is created.
 *
 * Stored in tbl_accounts.admin_no. admin_no_ensure() adds the column (and a unique index)
 * the first time it runs and numbers existing accounts by when they were created, so no
 * manual SQL import is needed.
 */

if (!function_exists('admin_no_ensure')) {
    /** Makes sure tbl_accounts.admin_no exists and every account has a number. Returns false if the column is unavailable. */
    function admin_no_ensure(mysqli $conn): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $col = $conn->query("SHOW COLUMNS FROM tbl_accounts LIKE 'admin_no'");
            if (!$col || $col->num_rows === 0) {
                if (!@$conn->query("ALTER TABLE tbl_accounts ADD COLUMN `admin_no` tinyint(3) UNSIGNED DEFAULT NULL AFTER `role`")) {
                    error_log('[admin-numbers] Could not add tbl_accounts.admin_no: ' . $conn->error);
                    return $ready = false;
                }
            }

            // Number any account that has none: the oldest Super Admin is Admin 1, the rest follow by creation order.
            $used = [];
            $todo = [];
            $res = $conn->query("SELECT id, role, admin_no FROM tbl_accounts ORDER BY created_at ASC, id ASC");
            while ($res && ($r = $res->fetch_assoc())) {
                if ($r['admin_no'] !== null) {
                    $used[(int)$r['admin_no']] = true;
                } else {
                    $todo[] = $r;
                }
            }

            if ($todo) {
                $assign = [];
                if (empty($used[1])) {
                    foreach ($todo as $i => $r) {
                        if ($r['role'] === 'Super Admin') {
                            $assign[(int)$r['id']] = 1;
                            $used[1] = true;
                            unset($todo[$i]);
                            break;
                        }
                    }
                }
                foreach ($todo as $r) {
                    for ($n = 2; $n <= 5; $n++) {
                        if (empty($used[$n])) {
                            $assign[(int)$r['id']] = $n;
                            $used[$n] = true;
                            break;
                        }
                    }
                }
                $st = $conn->prepare("UPDATE tbl_accounts SET admin_no = ? WHERE id = ? AND admin_no IS NULL");
                if ($st) {
                    foreach ($assign as $id => $n) {
                        $st->bind_param('ii', $n, $id);
                        $st->execute();
                    }
                    $st->close();
                }
            }

            $idx = $conn->query("SHOW INDEX FROM tbl_accounts WHERE Key_name = 'uq_admin_no'");
            if ($idx && $idx->num_rows === 0) {
                @$conn->query("ALTER TABLE tbl_accounts ADD UNIQUE KEY `uq_admin_no` (`admin_no`)");
            }

            return $ready = true;
        } catch (\Throwable $e) {
            // mysqli may throw instead of returning false (PHP 8.1+): never break the page over numbering.
            error_log('[admin-numbers] ' . $e->getMessage());
            return $ready = false;
        }
    }
}

if (!function_exists('admin_no_next')) {
    /** The number the next new admin gets: the lowest free one from 2 to 5 (then 1), or 0 when all five are taken. */
    function admin_no_next(mysqli $conn): int
    {
        $used = [];
        $res = $conn->query("SELECT admin_no FROM tbl_accounts WHERE admin_no IS NOT NULL");
        while ($res && ($r = $res->fetch_assoc())) {
            $used[(int)$r['admin_no']] = true;
        }
        foreach ([2, 3, 4, 5, 1] as $n) {
            if (empty($used[$n])) {
                return $n;
            }
        }
        return 0;
    }
}
