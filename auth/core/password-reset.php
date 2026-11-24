<?php

/**
 * password-reset.php
 *
 * Rules for the Super-Admin-assisted "forgot password" reset of FACULTY accounts.
 *
 * How it works
 * ------------
 *   1. Every wrong password typed for a faculty account is counted PER ACCOUNT in
 *      tbl_faculty_pw_reset.fail_count (not per browser or IP). A correct sign-in sets it back to 0.
 *      This is separate from the 3-try lockout in rate-limiter.php, which only slows guessing down and
 *      starts again after every lockout round.
 *   2. At PWR_MAX_ATTEMPTS (5) wrong passwords the sign-in form offers "Notify Super Admin"
 *      (auth/api/request-password-reset.php). That stamps notified_at, which makes a notification appear
 *      for Super Admins (notifications/core/admin-data.php).
 *   3. A reset is only possible while fail_count >= 5. The Super Admin opens Faculty > Edit > Reset password,
 *      confirms with their OWN password and presses Generate
 *      (account-management/api/reset-faculty-password.php). That overwrites the faculty password with a
 *      random one, sets must_change_password = 1 (so the existing first-sign-in screen asks for a new
 *      password) and reset_pending = 1 (so that screen asks for the password only, not the department again).
 *   4. When the faculty saves their own password (account-management/api/first-login-setup.php),
 *      reset_pending goes back to 0.
 *
 * The table is created on first use (same self-healing pattern as the other ensureSchema() helpers).
 * Everything here fails safe: a database hiccup never blocks a sign-in.
 */

/** Wrong passwords in a row (per account) before a reset can be requested. */
const PWR_MAX_ATTEMPTS = 5;

/** The row key: the account email, lower-cased. */
function pwr_key(string $email): string
{
    return substr(strtolower(trim($email)), 0, 191);
}

/** "Now" as a Manila date-time string (the project's clock, independent of the DB server's timezone). */
function pwr_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
}

/** Create tbl_faculty_pw_reset when it is missing. Returns true when the table exists afterwards. */
function pwr_ensure_schema(mysqli $conn): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $res = $conn->query(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_faculty_pw_reset' LIMIT 1"
        );
        if ($res && $res->num_rows > 0) {
            return $ok = true;
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS `tbl_faculty_pw_reset` (
                `email`           VARCHAR(191)    NOT NULL,
                `fail_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
                `last_failed_at`  DATETIME        NULL DEFAULT NULL,
                `notified_at`     DATETIME        NULL DEFAULT NULL,
                `reset_pending`   TINYINT(1)      NOT NULL DEFAULT 0,
                `reset_issued_at` DATETIME        NULL DEFAULT NULL,
                `reset_issued_ts` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `reset_issued_by` VARCHAR(255)    NULL DEFAULT NULL,
                PRIMARY KEY (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $res = $conn->query(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_faculty_pw_reset' LIMIT 1"
        );
        return $ok = (bool)($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        error_log('[password-reset] ensure schema: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * Count one more wrong password for this faculty account. Returns the new count (0 if it could not be saved).
 */
function pwr_record_failed_login(mysqli $conn, string $email): int
{
    $key = pwr_key($email);
    if ($key === '' || !pwr_ensure_schema($conn)) {
        return 0;
    }
    try {
        $now = pwr_now();
        $st  = $conn->prepare(
            'INSERT INTO tbl_faculty_pw_reset (email, fail_count, last_failed_at)
                  VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE fail_count = LEAST(fail_count + 1, 99), last_failed_at = VALUES(last_failed_at)'
        );
        if (!$st) {
            return 0;
        }
        $st->bind_param('ss', $key, $now);
        $st->execute();
        $st->close();

        $sel = $conn->prepare('SELECT fail_count FROM tbl_faculty_pw_reset WHERE email = ? LIMIT 1');
        if (!$sel) {
            return 0;
        }
        $sel->bind_param('s', $key);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        return $row ? (int)$row['fail_count'] : 0;
    } catch (Throwable $e) {
        error_log('[password-reset] record failed login: ' . $e->getMessage());
        return 0;
    }
}

/** A correct sign-in: the wrong-password count and any open "notify" request are cleared. */
function pwr_clear_on_success(mysqli $conn, string $email): void
{
    $key = pwr_key($email);
    if ($key === '' || !pwr_ensure_schema($conn)) {
        return;
    }
    try {
        $st = $conn->prepare('UPDATE tbl_faculty_pw_reset SET fail_count = 0, notified_at = NULL WHERE email = ?');
        if ($st) {
            $st->bind_param('s', $key);
            $st->execute();
            $st->close();
        }
    } catch (Throwable $e) {
        error_log('[password-reset] clear on success: ' . $e->getMessage());
    }
}

/**
 * Where an account stands.
 * @return array{fail_count:int,max:int,eligible:bool,notified_at:?string,reset_pending:bool,last_failed_at:?string}
 */
function pwr_status(mysqli $conn, string $email): array
{
    $out = [
        'fail_count'     => 0,
        'max'            => PWR_MAX_ATTEMPTS,
        'eligible'       => false,
        'notified_at'    => null,
        'reset_pending'  => false,
        'last_failed_at' => null,
    ];
    $key = pwr_key($email);
    if ($key === '' || !pwr_ensure_schema($conn)) {
        return $out;
    }
    try {
        $st = $conn->prepare(
            'SELECT fail_count, notified_at, reset_pending, last_failed_at FROM tbl_faculty_pw_reset WHERE email = ? LIMIT 1'
        );
        if (!$st) {
            return $out;
        }
        $st->bind_param('s', $key);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $out['fail_count']     = (int)$row['fail_count'];
            $out['eligible']       = $out['fail_count'] >= PWR_MAX_ATTEMPTS;
            $out['notified_at']    = $row['notified_at'] !== null ? (string)$row['notified_at'] : null;
            $out['reset_pending']  = (int)$row['reset_pending'] === 1;
            $out['last_failed_at'] = $row['last_failed_at'] !== null ? (string)$row['last_failed_at'] : null;
        }
    } catch (Throwable $e) {
        error_log('[password-reset] status: ' . $e->getMessage());
    }
    return $out;
}

/**
 * The faculty pressed "Notify Super Admin". Only works at 5+ wrong passwords.
 * @return string 'sent' | 'already' | 'not_eligible' | 'error'
 */
function pwr_mark_notified(mysqli $conn, string $email): string
{
    $key = pwr_key($email);
    if ($key === '' || !pwr_ensure_schema($conn)) {
        return 'error';
    }
    try {
        $now = pwr_now();
        $max = PWR_MAX_ATTEMPTS;
        $st  = $conn->prepare(
            'UPDATE tbl_faculty_pw_reset SET notified_at = ?
              WHERE email = ? AND fail_count >= ? AND notified_at IS NULL'
        );
        if (!$st) {
            return 'error';
        }
        $st->bind_param('ssi', $now, $key, $max);
        $st->execute();
        $changed = $st->affected_rows;
        $st->close();
        if ($changed === 1) {
            return 'sent';
        }
        $status = pwr_status($conn, $key);
        if ($status['eligible'] && $status['notified_at'] !== null) {
            return 'already';
        }
        return 'not_eligible';
    } catch (Throwable $e) {
        error_log('[password-reset] mark notified: ' . $e->getMessage());
        return 'error';
    }
}

/** Open requests for the Super Admin notification feed (newest first). */
function pwr_open_requests(mysqli $conn, int $limit = 10): array
{
    if (!pwr_ensure_schema($conn)) {
        return [];
    }
    $rows = [];
    try {
        $max = PWR_MAX_ATTEMPTS;
        $lim = max(1, $limit);
        $st  = $conn->prepare(
            'SELECT email, fail_count, notified_at FROM tbl_faculty_pw_reset
              WHERE notified_at IS NOT NULL AND fail_count >= ?
              ORDER BY notified_at DESC LIMIT ' . $lim
        );
        if ($st) {
            $st->bind_param('i', $max);
            $st->execute();
            $res = $st->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $rows[] = $r;
            }
            $st->close();
        }
    } catch (Throwable $e) {
        error_log('[password-reset] open requests: ' . $e->getMessage());
    }
    return $rows;
}

/**
 * Super Admin pressed Generate: claim the reset (only while still at 5+ wrong passwords) and
 * mark it pending. Returns true when THIS call claimed it, so two clicks can never both reset.
 * Run inside the caller's transaction, together with the tbl_users update.
 */
function pwr_claim_reset(mysqli $conn, string $email, string $issued_by): bool
{
    $key = pwr_key($email);
    if ($key === '' || !pwr_ensure_schema($conn)) {
        return false;
    }
    $now = pwr_now();
    $ts  = time();
    $max = PWR_MAX_ATTEMPTS;
    $st  = $conn->prepare(
        'UPDATE tbl_faculty_pw_reset
            SET fail_count = 0, notified_at = NULL, reset_pending = 1,
                reset_issued_at = ?, reset_issued_ts = ?, reset_issued_by = ?
          WHERE email = ? AND fail_count >= ?'
    );
    if (!$st) {
        return false;
    }
    $st->bind_param('sissi', $now, $ts, $issued_by, $key, $max);
    $st->execute();
    $changed = $st->affected_rows;
    $st->close();
    return $changed === 1;
}

/** Is this account waiting for its owner to replace a Super-Admin-generated password? */
function pwr_is_reset_pending(mysqli $conn, string $email): bool
{
    $key = pwr_key($email);
    if ($key === '') {
        return false;
    }
    try {
        $st = $conn->prepare('SELECT reset_pending FROM tbl_faculty_pw_reset WHERE email = ? LIMIT 1');
        if (!$st) {
            return false;
        }
        $st->bind_param('s', $key);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? ((int)$row['reset_pending'] === 1) : false;
    } catch (Throwable $e) {
        return false;   // table not created yet = nothing was ever reset
    }
}

/** The faculty chose their own password: the reset is finished. */
function pwr_complete_reset(mysqli $conn, string $email): void
{
    $key = pwr_key($email);
    if ($key === '') {
        return;
    }
    try {
        $st = $conn->prepare(
            'UPDATE tbl_faculty_pw_reset SET reset_pending = 0, fail_count = 0, notified_at = NULL WHERE email = ?'
        );
        if ($st) {
            $st->bind_param('s', $key);
            $st->execute();
            $st->close();
        }
    } catch (Throwable $e) {
        error_log('[password-reset] complete reset: ' . $e->getMessage());
    }
}

/**
 * Was this faculty session started BEFORE the Super Admin issued the reset password?
 * Such a session must not be allowed to walk into the "choose a new password" screen without ever
 * knowing the temporary password, so faculty_session_guard() ends it. Fails open (false) on any error.
 */
function pwr_session_predates_reset(mysqli $conn, string $email, int $login_time): bool
{
    $key = pwr_key($email);
    if ($key === '') {
        return false;
    }
    try {
        $st = $conn->prepare('SELECT reset_pending, reset_issued_ts FROM tbl_faculty_pw_reset WHERE email = ? LIMIT 1');
        if (!$st) {
            return false;
        }
        $st->bind_param('s', $key);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row || (int)$row['reset_pending'] !== 1) {
            return false;
        }
        $issued = (int)$row['reset_issued_ts'];
        return $issued > 0 && $login_time < $issued;
    } catch (Throwable $e) {
        return false;
    }
}
