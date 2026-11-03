<?php
// Login/landing request handling for landing-page.php: headers, redirects for signed-in users, the unified login POST handler and flash messages.
// Moved verbatim from landing-page.php (Pass 3); paths now use PS_ROOT because this file sits in auth/core/.
// fix for application disclosure vulnerability.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// fix for csp vulnerability. nonce is generated per request and is unique.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$csp_nonce}' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");
require_once PS_ROOT . '/config/session.php';
require_once PS_ROOT . '/config/csrf.php';
require_once PS_ROOT . '/auth/core/role-splash.php';
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['faculty_id'])) {
    header("Location: faculty-dashboard.php");
    exit();
}
if (isset($_SESSION['admin'])) {
    header("Location: admin-dashboard.php");
    exit();
}

// "Continue as Student": students don't authenticate, so this hand-off from the
// landing page is their entry event. Arm the one-shot role splash, then go on to
// the portal. (Opening or refreshing student-dashboard.php directly stays splash-free.)
if (($_GET['go'] ?? '') === 'student') {
    role_splash_arm('Student');
    header("Location: student-dashboard.php");
    exit();
}

require_once PS_ROOT . '/config/db.php';
require_once PS_ROOT . '/shared/core/mailer.php';
require_once PS_ROOT . '/auth/core/rate-limiter.php';
$conn = getDB();
$rl_ip = rl_get_client_ip();

// ── PRG: read and immediately clear any flash data from the session ────────
// Flash keys are written by the POST handler below and consumed exactly once
// on the following GET request. Clearing them here prevents stale state on
// a subsequent page load.
$login_error     = $_SESSION['flash_login_error']     ?? "";
$login_email_val = $_SESSION['flash_login_email']     ?? "";
$lockout_seconds = (int)($_SESSION['flash_lockout_seconds'] ?? 0);
$lockout_until   = $_SESSION['flash_lockout_until']   ?? "";
$remember_me     = !empty($_SESSION['flash_remember_me']);

unset(
    $_SESSION['flash_login_error'],
    $_SESSION['flash_login_email'],
    $_SESSION['flash_lockout_seconds'],
    $_SESSION['flash_lockout_until'],
    $_SESSION['flash_remember_me']
);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $ip_check = checkIpAllowed($rl_ip, $conn);
    $lockout_email = (string)($_SESSION['login_lockout_email'] ?? '');
    $email_check = $lockout_email !== ''
        ? checkLoginAllowed($lockout_email, $rl_ip, $conn)
        : ['allowed' => true, 'seconds_left' => 0, 'locked_until' => ''];

    if (!$ip_check['allowed']) {
        $lockout_seconds = (int)$ip_check['seconds_left'];
        $lockout_until = (string)$ip_check['locked_until'];
        $login_error = 'Too many failed attempts from this location.';
        $login_email_val = $lockout_email !== '' ? $lockout_email : $login_email_val;
        if ($lockout_email !== '') {
            $_SESSION['login_lockout_email'] = $lockout_email;
        }
    } elseif (!$email_check['allowed']) {
        $lockout_seconds = (int)$email_check['seconds_left'];
        $lockout_until = (string)$email_check['locked_until'];
        $login_error = 'Too many failed attempts.';
        $login_email_val = $lockout_email;
    } elseif ($lockout_email !== '') {
        unset($_SESSION['login_lockout_email']);
    }
}

// ── POST handler ───────────────────────────────────────────────────────────
// Single unified login — the form no longer asks the user whether they're
// Faculty or Admin. Instead: look the email up in tbl_accounts (Admin)
// first, then tbl_users (Faculty) if it's not an admin account, and send
// them to whichever dashboard actually matches their credentials.
// Every branch that does NOT redirect to a dashboard writes flash data to
// the session and redirects back to GET landing-page.php (PRG pattern).
// This prevents form resubmission when window.location.reload() fires at
// lockout expiry.
if (isset($_POST['login'])) {
    csrf_verify();
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $remember_me_requested = ($_POST['remember_me'] ?? '') === '1';

    // Preserve the submitted email so the field stays populated on redirect.
    $_SESSION['flash_login_email'] = $email;
    $_SESSION['flash_remember_me'] = $remember_me_requested;

    // ── Rate-limit pre-checks ──────────────────────────────────────────────
    // Layer 1 — IP-wide throttle (runs first; broader restriction).
    $ip_check = checkIpAllowed($rl_ip, $conn);
    if (!$ip_check['allowed']) {
        $_SESSION['login_lockout_email'] = $email;
        $_SESSION['flash_login_error']     = "Too many failed attempts from this location. Try again in " . gmdate('i:s', $ip_check['seconds_left']) . ".";
        $_SESSION['flash_lockout_seconds'] = $ip_check['seconds_left'];
        $_SESSION['flash_lockout_until']   = $ip_check['locked_until'];
        header("Location: landing-page.php");
        exit();
    }

    // Layer 2 — per-account throttle (email + IP pair).
    $rl_check = checkLoginAllowed($email, $rl_ip, $conn);
    if (!$rl_check['allowed']) {
        $_SESSION['login_lockout_email'] = $email;
        $_SESSION['flash_login_error']     = "Too many failed attempts. Try again in " . gmdate('i:s', $rl_check['seconds_left']) . ".";
        $_SESSION['flash_lockout_seconds'] = $rl_check['seconds_left'];
        $_SESSION['flash_lockout_until']   = $rl_check['locked_until'];
        header("Location: landing-page.php");
        exit();
    }
    unset($_SESSION['login_lockout_email']);

    // ===== Try ADMIN account first (tbl_accounts) =====
    // NOTE: previously hardcoded to only accept email === 'main@admin.edu'.
    // Generalized to look up whichever email was submitted against
    // tbl_accounts, since the admin's email is now editable from
    // Settings → My Account and must not be able to lock them out.
    //
    // Self-heal the `role` column here (same pattern already used below
    // for last_login) — this SELECT now needs it, but a site running an
    // older tbl_accounts export won't have it yet, and login must not
    // start throwing SQL errors just because that migration hasn't run.
    $role_col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'role'");
    if ($role_col_check && mysqli_num_rows($role_col_check) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_accounts ADD COLUMN role ENUM('Super Admin','Admin') NOT NULL DEFAULT 'Admin'");
        // The pre-existing account predates roles entirely — promote the
        // original main@admin.edu account to Super Admin specifically
        // (not just "whoever logs in first triggers this migration"),
        // so the one true original admin doesn't end up locked out of
        // Super-Admin-only screens after this runs.
        @mysqli_query($conn, "UPDATE tbl_accounts SET role = 'Super Admin' WHERE email = 'main@admin.edu'");
    }

    // dormant_until / session_epoch columns (Manage Admins → locked accounts)
    admin_accounts_ensure_schema($conn);

    $stmt_acc  = $conn->prepare("SELECT fullName, password, role, dormant_until FROM tbl_accounts WHERE email = ? LIMIT 1");
    $row_acc   = null;
    if ($stmt_acc) {
        $stmt_acc->bind_param("s", $email);
        $stmt_acc->execute();
        $res_acc = $stmt_acc->get_result();
        $row_acc = ($res_acc && $res_acc->num_rows === 1) ? $res_acc->fetch_assoc() : null;
    }

    if ($row_acc) {
        if (password_verify($password, $row_acc['password'])) {
            // ── Locked account: correct password, but not allowed in yet ──
            // Checked only AFTER the password is verified so the dormant
            // state is never revealed to someone who doesn't know it.
            if (admin_is_dormant($row_acc['dormant_until'])) {
                $_SESSION['flash_login_error'] = admin_dormant_message((int)$row_acc['dormant_until']);
                header("Location: landing-page.php");
                exit();
            }

            // ── Successful admin login ─────────────────────────
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Could not renew the session after login.');
            }
            $_SESSION['admin']      = true;
            require_once PS_ROOT . '/shared/core/activity-logger.php';
            admin_log_activity($conn, 'login', 'Signed in', $email);
            $_SESSION['login_time'] = time();

            $admin_name_db = 'Administrator';

            $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_login'");
            if ($col_check && mysqli_num_rows($col_check) === 0) {
                @mysqli_query($conn, "ALTER TABLE tbl_accounts ADD COLUMN last_login DATETIME NULL");
            }

            $stmt_last = $conn->prepare("SELECT last_login FROM tbl_accounts WHERE email = ? LIMIT 1");
            if ($stmt_last) {
                $stmt_last->bind_param("s", $email);
                $stmt_last->execute();
                $res_last = $stmt_last->get_result();
                if ($res_last && $row_last = $res_last->fetch_assoc()) {
                    $_SESSION['admin_last_login'] = $row_last['last_login'] ?? null;
                }
                $stmt_last->close();
            }

            $now_dt  = date('Y-m-d H:i:s');
            $stmt_up = $conn->prepare("UPDATE tbl_accounts SET last_login = ? WHERE email = ?");
            if ($stmt_up) {
                $stmt_up->bind_param("ss", $now_dt, $email);
                $stmt_up->execute();
                $stmt_up->close();
            }

            $stmt_admin = $conn->prepare("SELECT fullname FROM tbl_users WHERE email = ? LIMIT 1");
            if ($stmt_admin) {
                $stmt_admin->bind_param("s", $email);
                $stmt_admin->execute();
                $res_admin = $stmt_admin->get_result();
                if ($res_admin && $row_admin = $res_admin->fetch_assoc()) {
                    if (!empty($row_admin['fullname'])) $admin_name_db = $row_admin['fullname'];
                }
            }

            if ($admin_name_db === 'Administrator' && !empty($row_acc['fullName'])) {
                $admin_name_db = $row_acc['fullName'];
            }

            $_SESSION['admin_name']  = $admin_name_db;
            $_SESSION['admin_email'] = $email;
            // Defaults to 'Admin' (the more restrictive role) if the role
            // column is somehow missing/empty, rather than silently
            // granting Super Admin — fail closed, not open.
            $_SESSION['admin_role']  = (!empty($row_acc['role']) && $row_acc['role'] === 'Super Admin')
                ? 'Super Admin' : 'Admin';

            // Clear flash_login_email — no longer needed on success.
            unset($_SESSION['flash_login_email']);
            unset($_SESSION['flash_remember_me']);

            recordSuccessfulLogin($email, $rl_ip, $conn);
            if ($remember_me_requested) {
                remember_me_issue($conn, 'admin', $email, (string)$row_acc['password'], (int)$_SESSION['login_time']);
            } else {
                remember_me_remove_current($conn);
            }
            role_splash_arm($_SESSION['admin_role']); // one-shot: splash shows once, on this hand-off only
            $_SESSION['admin_login_toast'] = true;    // one-shot: welcome toast shows once, right after this sign-in
            header("Location: admin-dashboard.php");
            exit();
        } else {
            // ── Email matches an admin account, wrong password ─────
            $rl_result = recordFailedAttempt($email, $rl_ip, true, $row_acc['fullName'] ?? 'Administrator', $conn);
            $_SESSION['flash_login_error'] = $rl_result['message'];
            if ($rl_result['locked']) {
                $_SESSION['login_lockout_email'] = $email;
                $_SESSION['flash_lockout_seconds'] = $rl_result['seconds_left'];
                $_SESSION['flash_lockout_until']   = $rl_result['locked_until'];
            }
            header("Location: landing-page.php");
            exit();
        }
    }

    // ===== Not an admin account — try FACULTY (tbl_users) =====
    $stmt = $conn->prepare("SELECT faculty_id, fullname, password FROM tbl_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // ── Successful faculty login ───────────────────────────────
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Could not renew the session after login.');
            }
            $_SESSION['faculty_id']    = $user['faculty_id'];
            $_SESSION['faculty_name']  = $user['fullname'];
            $_SESSION['faculty_email'] = $email;
            $_SESSION['login_time']    = time();

            // Clear flash_login_email — no longer needed on success.
            unset($_SESSION['flash_login_email']);
            unset($_SESSION['flash_remember_me']);

            recordSuccessfulLogin($email, $rl_ip, $conn);
            if ($remember_me_requested) {
                remember_me_issue($conn, 'faculty', $email, (string)$user['password'], (int)$_SESSION['login_time']);
            } else {
                remember_me_remove_current($conn);
            }
            role_splash_arm('Faculty'); // one-shot: splash shows once, on this hand-off only
            header("Location: faculty-dashboard.php");
            exit();
        } else {
            // ── Email matches a faculty account, wrong password ────────
            $rl_result = recordFailedAttempt($email, $rl_ip, true, $user['fullname'] ?? '', $conn);
            $_SESSION['flash_login_error'] = $rl_result['message'];
            if ($rl_result['locked']) {
                $_SESSION['login_lockout_email'] = $email;
                $_SESSION['flash_lockout_seconds'] = $rl_result['seconds_left'];
                $_SESSION['flash_lockout_until']   = $rl_result['locked_until'];
            }
            header("Location: landing-page.php");
            exit();
        }
    } else {
        // ── Email not found in either tbl_accounts or tbl_users ────────
        $rl_result  = recordFailedAttempt($email, $rl_ip, false, '', $conn);
        $ip_result  = recordIpFailedAttempt($rl_ip, $conn);

        if ($ip_result['locked']) {
            $_SESSION['login_lockout_email'] = $email;
            $_SESSION['flash_login_error']     = $ip_result['message'];
            $_SESSION['flash_lockout_seconds'] = $ip_result['seconds_left'];
            $_SESSION['flash_lockout_until']   = $ip_result['locked_until'];
        } elseif ($rl_result['locked']) {
            $_SESSION['login_lockout_email'] = $email;
            $_SESSION['flash_login_error']     = $rl_result['message'];
            $_SESSION['flash_lockout_seconds'] = $rl_result['seconds_left'];
            $_SESSION['flash_lockout_until']   = $rl_result['locked_until'];
        } else {
            $_SESSION['flash_login_error'] = $rl_result['message'];
        }
        header("Location: landing-page.php");
        exit();
    }
}
// ── End POST handler — all paths above exit() ─────────────────────────────
// Everything below this line executes only on GET requests.
