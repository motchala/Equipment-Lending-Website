<?php

/**
 * admin-auth.php
 *
 * Central place for the rules that decide whether an admin account may
 * still be used:
 *
 *   • dormant_until  – unix time until which the account is locked
 *                      (exists, but cannot log in / keep a session).
 *   • session_epoch  – revocation timestamp used for credential changes and
 *                      global sign-out. Sessions authenticated before it end.
 *
 * admin_session_guard() is called from config/session.php, so it runs on
 * every request that carries an admin session (pages AND api endpoints).
 * When it ends a session it only clears the admin keys and leaves a flash
 * message; the existing "is this an admin?" checks on each page/endpoint
 * then redirect to the landing page or answer 401, exactly as they do for
 * any logged-out visitor.
 */

/** Maximum number of days an account can be locked. */
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

/** Message shown on the landing page for a locked account. */
function admin_dormant_message(int $until): string
{
    return 'This account is locked until ' . admin_format_time($until)
        . '. Please contact a Super Admin if you need access sooner.';
}

/** Remove the admin identity from the current session (keeps the session itself). */
function admin_session_end(string $message): void
{
    foreach (
        [
            'admin',
            'admin_name',
            'admin_email',
            'admin_role',
            'admin_last_login',
            'admin_last_pw_change',
            'login_time',
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
 * Fails closed for accounts that were deleted, locked, or had their
 * credentials changed after this session started.
 */
function admin_session_guard(): void
{
    if (($_SESSION['admin'] ?? null) !== true) {
        return; // not an admin session (visitor, faculty, student)
    }

    require_once __DIR__ . '/../../config/db.php';
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

/**
 * Is the logged-in admin a Super Admin *right now*? Read fresh from the
 * database rather than trusting the session, so a demoted or deleted
 * account loses Super Admin powers immediately. Used by the endpoints
 * behind the Super-Admin-only Faculty tab.
 */
function admin_is_super_admin(mysqli $conn): bool
{
    $email = (string)($_SESSION['admin_email'] ?? '');
    if (($_SESSION['admin'] ?? null) !== true || $email === '') {
        return false;
    }

    $role = null;
    if ($stmt = $conn->prepare('SELECT role FROM tbl_accounts WHERE email = ? LIMIT 1')) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
        $stmt->close();
        $role = $row['role'] ?? null;
    }
    return $role === 'Super Admin';
}

/** Remove the faculty identity from the current session (keeps the session itself). */
function faculty_session_end(string $message): void
{
    foreach (['faculty_id', 'faculty_name', 'faculty_email'] as $k) {
        unset($_SESSION[$k]);
    }
    // login_time is shared with admin sessions - only clear it when no admin is logged in.
    if (($_SESSION['admin'] ?? null) !== true) {
        unset($_SESSION['login_time']);
    }
    $_SESSION['flash_login_error'] = $message;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Re-check the logged-in faculty member against the database.
 * If a Super Admin deleted the account while this person was still logged
 * in, end the session so the portal and its API endpoints stop working
 * for them (every faculty page/endpoint already treats a missing
 * $_SESSION['faculty_id'] as "logged out").
 *
 * Fails open when the lookup itself errors, so a database hiccup can't
 * log every faculty member out - only a definite "no such account" does.
 */
function faculty_session_guard(): void
{
    $faculty_id = (string)($_SESSION['faculty_id'] ?? '');
    if ($faculty_id === '') {
        return; // not a faculty session (visitor, student, admin)
    }

    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../account-management/core/faculty-first-login.php';
    $conn = getDB();

    // One lookup answers two questions: does the account still exist, and is it still waiting for its
    // first-sign-in setup (must_change_password)? An older database without that column is upgraded on
    // the spot (faculty_fl_ensure_schema) and the lookup is repeated once.
    $must_change = null;   // null = account not found
    $lookup_ok   = false;
    for ($try = 0; $try < 2 && !$lookup_ok; $try++) {
        try {
            $stmt = $conn->prepare('SELECT must_change_password FROM tbl_users WHERE faculty_id = ? LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('prepare failed');
            }
            $stmt->bind_param('s', $faculty_id);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('execute failed');
            }
            $res = $stmt->get_result();
            $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
            $stmt->close();
            $must_change = $row ? (int)$row['must_change_password'] : null;
            $lookup_ok   = true;
        } catch (Throwable $e) {
            if ($try === 0 && faculty_fl_ensure_schema($conn)) {
                continue;      // column was missing: it exists now, ask again
            }
            return;            // fail open on a database hiccup, as before
        }
    }
    if (!$lookup_ok) {
        return;
    }

    if ($must_change === null) {
        // A faculty member can set their own Faculty ID once (it replaces the id
        // they were created with). If that happened in another tab or device, this
        // session still holds the old id: find the account by email and follow it.
        $email = (string)($_SESSION['faculty_email'] ?? '');
        if ($email !== '') {
            $st = $conn->prepare('SELECT faculty_id, must_change_password FROM tbl_users WHERE email = ? LIMIT 1');
            if ($st) {
                $st->bind_param('s', $email);
                if ($st->execute()) {
                    $res = $st->get_result();
                    $row = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
                    if ($row) {
                        $st->close();
                        $_SESSION['faculty_id'] = (string)$row['faculty_id'];
                        $must_change = (int)$row['must_change_password'];
                    }
                }
                if ($must_change === null) {
                    $st->close();
                }
            }
        }
        if ($must_change === null) {
            faculty_session_end('This faculty account is no longer available. Please contact your administrator.');
            return;
        }
    }

    // ── First sign-in lock ─────────────────────────────────────────────────
    // A brand-new account (created by a Super Admin with a generated password) can do nothing
    // until its owner has chosen their own password: pages go to the setup screen, API calls get a
    // 403. Only the setup screen, its save endpoint and log out stay reachable.
    if ($must_change === 1) {
        $script = faculty_fl_current_script();
        if (in_array($script, FACULTY_FIRST_LOGIN_ALLOWED, true)) {
            return;
        }
        $setup_url = faculty_fl_web_base() . 'account-management/faculty-setup.php';
        // Scripts in the site root are the page entry points; everything in a module's api/ folder is an endpoint.
        if (strpos($script, '/') === false || faculty_fl_is_page_request()) {
            header('Location: ' . $setup_url);
            exit;
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success'        => false,
            'status'         => 'error',
            'setup_required' => true,
            'redirect'       => $setup_url,
            'msg'            => 'Finish setting up your account first.',
            'message'        => 'Finish setting up your account first.',
        ]);
        exit;
    }
}
