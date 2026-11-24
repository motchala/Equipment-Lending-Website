<?php

/**
 * faculty-status.php
 *
 * Active / deactivated state of a faculty account.
 *
 * How it works
 * ------------
 *   - A faculty account is never deleted. A Super Admin DEACTIVATES it (Faculty tab -> Edit ->
 *     Deactivate), which sets tbl_users.is_active = 0. The row, the profile and all borrowing /
 *     reservation history stay on file, and the Super Admin can REACTIVATE the account at any time.
 *   - While is_active = 0 the person cannot get in:
 *       * login-handler.php refuses the sign-in (only after the password was right, so the status is
 *         never revealed to someone who does not know it),
 *       * remember-me.php refuses to restore a "stay signed in" session,
 *       * faculty_session_guard() (auth/core/admin-auth.php, runs on every request) ends a session
 *         that is already open.
 *   - The deactivated account keeps its Faculty ID and email, so neither can be reused for a new
 *     account while it exists.
 *
 * Accounts that existed before this feature keep the column default (1), so they stay active.
 */

/** Shown on the sign-in page when a deactivated account tries to get in. */
const FACULTY_DEACTIVATED_MESSAGE = 'This account has been deactivated. Please contact your administrator.';

/**
 * Add tbl_users.is_active when it is missing (older databases catch up by themselves, same pattern as
 * faculty_fl_ensure_schema()). Returns true when the column exists afterwards.
 */
function faculty_status_ensure_schema(mysqli $conn): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $res = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'is_active'");
        if ($res && $res->num_rows > 0) {
            return $ok = true;
        }
        $conn->query("ALTER TABLE tbl_users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
        $res = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'is_active'");
        return $ok = (bool)($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        error_log('[faculty-status] ensure schema: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * Column list fragment for a SELECT on tbl_users: the real is_active column, or a constant 1 ("everyone
 * is active") if the column could not be created, so a read-only database never locks anybody out.
 * $alias is the table alias used in the query ('' when there is none).
 */
function faculty_status_select(mysqli $conn, string $alias = ''): string
{
    if (faculty_status_ensure_schema($conn)) {
        return ($alias !== '' ? $alias . '.' : '') . 'is_active';
    }
    return '1 AS is_active';
}
