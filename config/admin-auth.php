<?php

/**
 * admin-auth.php
 *
 * Central place for the rules that decide whether an admin account may
 * still be used:
 *
 *   • dormant_until  – unix time until which the account is dormant
 *                      (exists, but cannot log in / keep a session).
 *   • session_epoch  – unix time of the last credential change made by a
 *                      Super Admin. Any session that started before it is
 *                      ended, so a changed password/email takes effect
 *                      everywhere immediately.
 *
 * admin_session_guard() is called from config/session.php, so it runs on
 * every request that carries an admin session (pages AND api endpoints).
 * When it ends a session it only clears the admin keys and leaves a flash
 * message; the existing "is this an admin?" checks on each page/endpoint
 * then redirect to the landing page or answer 401, exactly as they do for
 * any logged-out visitor.
 */

/** Maximum number of days an account can be made dormant. */
const ADMIN_DORMANT_MAX_DAYS = 30;

/** Quick-pick durations offered in the UI (days). */
const ADMIN_DORMANT_CHOICES = [1, 3, 7, 14, 30];

/**
 * Add the dormant_until / session_epoch columns if they are missing.
 * Idempotent and cheap (cached per request). Uses SHOW COLUMNS + ALTER
 * like the other self-healing migrations in this project.
 */
function admin_accounts_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $cols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM tbl_accounts')) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    if (!$cols) {
        return; // table missing — nothing we can safely do here
    }
    if (!in_array('dormant_until', $cols, true)) {
        @$conn->query('ALTER TABLE tbl_accounts ADD COLUMN `dormant_until` BIGINT UNSIGNED NULL DEFAULT NULL');
    }
    if (!in_array('session_epoch', $cols, true)) {
        @$conn->query('ALTER TABLE tbl_accounts ADD COLUMN `session_epoch` BIGINT UNSIGNED NOT NULL DEFAULT 0');
    }
}

/** True when a dormant_until value is still in the future. */
function admin_is_dormant($dormant_until, ?int $now = null): bool
{
    return $dormant_until !== null && (int)$dormant_until > ($now ?? time());
}

/** Human readable "Oct 7, 2026, 3:00 PM" in the campus timezone. */
function admin_format_time(int $ts): string
{
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format('M j, Y, g:i A');
}

/** Message shown on the landing page for a dormant account. */
function admin_dormant_message(int $until): string
{
    return 'This account is dormant until ' . admin_format_time($until)
        . '. Please contact a Super Admin if you need access sooner.';
}

/** Remove the admin identity from the current session (keeps the session itself). */
function admin_session_end(string $message): void
{
    foreach (
        [
            'admin', 'admin_name', 'admin_email', 'admin_role',
            'admin_last_login', 'admin_last_pw_change', 'login_time',
        ] as $k
    ) {
        unset($_SESSION[$k]);
    }
    $_SESSION['flash_login_error'] = $message;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Re-check the logged-in admin against the database.
 * Fails closed for accounts that were deleted, made dormant, or had their
 * credentials changed after this session started.
 */
function admin_session_guard(): void
{
    if (($_SESSION['admin'] ?? null) !== true) {
        return; // not an admin session (visitor, faculty, student)
    }

    require_once __DIR__ . '/db.php';
    $conn = getDB();
    admin_accounts_ensure_schema($conn);

    $email = (string)($_SESSION['admin_email'] ?? '');
    $row = null;
    if ($email !== '' && ($stmt = $conn->prepare(
        'SELECT role, dormant_until, session_epoch FROM tbl_accounts WHERE email = ? LIMIT 1'
    ))) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if (!$row) {
        admin_session_end('This account is no longer available. Please log in with a different account.');
        return;
    }

    if (admin_is_dormant($row['dormant_until'])) {
        admin_session_end(admin_dormant_message((int)$row['dormant_until']));
        return;
    }

    if ((int)$row['session_epoch'] > (int)($_SESSION['login_time'] ?? 0)) {
        admin_session_end('Your login details were changed by a Super Admin. Please log in again.');
        return;
    }

    // Keep the role in the session in step with the database (fail closed).
    $_SESSION['admin_role'] = ($row['role'] === 'Super Admin') ? 'Super Admin' : 'Admin';
}
