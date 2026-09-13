<?php
// fix for application disclosure vulnerability.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// fix for csp vulnerability. nonce is generated per request and is unique.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$csp_nonce}' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/csrf.php';
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['faculty_id'])) {
    header("Location: faculty-dashboard.php");
    exit();
}
if (isset($_SESSION['admin'])) {
    header("Location: admin-dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/equipment-booking/core/mailer.php';
require_once __DIR__ . '/equipment-booking/core/rate-limiter.php';
$conn = getDB();

// ── PRG: read and immediately clear any flash data from the session ────────
// Flash keys are written by the POST handler below and consumed exactly once
// on the following GET request. Clearing them here prevents stale state on
// a subsequent page load.
$login_error     = $_SESSION['flash_login_error']     ?? "";
$login_email_val = $_SESSION['flash_login_email']     ?? "";
$lockout_seconds = (int)($_SESSION['flash_lockout_seconds'] ?? 0);
$lockout_until   = $_SESSION['flash_lockout_until']   ?? "";

unset(
    $_SESSION['flash_login_error'],
    $_SESSION['flash_login_email'],
    $_SESSION['flash_lockout_seconds'],
    $_SESSION['flash_lockout_until']
);

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

    // Preserve the submitted email so the field stays populated on redirect.
    $_SESSION['flash_login_email'] = $email;

    // ── Rate-limit pre-checks ──────────────────────────────────────────────
    $rl_ip = rl_get_client_ip();

    // Layer 1 — IP-wide throttle (runs first; broader restriction).
    $ip_check = checkIpAllowed($rl_ip, $conn);
    if (!$ip_check['allowed']) {
        $_SESSION['flash_login_error']     = "Too many failed attempts from this location. Try again in " . gmdate('i:s', $ip_check['seconds_left']) . ".";
        $_SESSION['flash_lockout_seconds'] = $ip_check['seconds_left'];
        $_SESSION['flash_lockout_until']   = $ip_check['locked_until'];
        header("Location: landing-page.php");
        exit();
    }

    // Layer 2 — per-account throttle (email + IP pair).
    $rl_check = checkLoginAllowed($email, $rl_ip, $conn);
    if (!$rl_check['allowed']) {
        $_SESSION['flash_login_error']     = "Too many failed attempts. Try again in " . gmdate('i:s', $rl_check['seconds_left']) . ".";
        $_SESSION['flash_lockout_seconds'] = $rl_check['seconds_left'];
        $_SESSION['flash_lockout_until']   = $rl_check['locked_until'];
        header("Location: landing-page.php");
        exit();
    }

    // ===== Try ADMIN account first (tbl_accounts) =====
    // NOTE: previously hardcoded to only accept email === 'main@admin.edu'.
    // Generalized to look up whichever email was submitted against
    // tbl_accounts, since the admin's email is now editable from
    // Settings → My Account and must not be able to lock them out.
    $stmt_acc  = $conn->prepare("SELECT fullName, password FROM tbl_accounts WHERE email = ? LIMIT 1");
    $row_acc   = null;
    if ($stmt_acc) {
        $stmt_acc->bind_param("s", $email);
        $stmt_acc->execute();
        $res_acc = $stmt_acc->get_result();
        $row_acc = ($res_acc && $res_acc->num_rows === 1) ? $res_acc->fetch_assoc() : null;
    }

    if ($row_acc) {
        if ($password === $row_acc['password']) {
            // ── Successful admin login ─────────────────────────
            $_SESSION['admin']      = true;
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

            // Clear flash_login_email — no longer needed on success.
            unset($_SESSION['flash_login_email']);

            recordSuccessfulLogin($email, $rl_ip, $conn);
            header("Location: admin-dashboard.php");
            exit();
        } else {
            // ── Email matches an admin account, wrong password ─────
            $rl_result = recordFailedAttempt($email, $rl_ip, true, $row_acc['fullName'] ?? 'Administrator', $conn);
            $_SESSION['flash_login_error'] = $rl_result['message'];
            if ($rl_result['locked']) {
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
            $_SESSION['faculty_id']    = $user['faculty_id'];
            $_SESSION['faculty_name']  = $user['fullname'];
            $_SESSION['faculty_email'] = $email;
            $_SESSION['login_time']    = time();

            // Clear flash_login_email — no longer needed on success.
            unset($_SESSION['flash_login_email']);

            recordSuccessfulLogin($email, $rl_ip, $conn);
            header("Location: faculty-dashboard.php");
            exit();
        } else {
            // ── Email matches a faculty account, wrong password ────────
            $rl_result = recordFailedAttempt($email, $rl_ip, true, $user['fullname'] ?? '', $conn);
            $_SESSION['flash_login_error'] = $rl_result['message'];
            if ($rl_result['locked']) {
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
            $_SESSION['flash_login_error']     = $ip_result['message'];
            $_SESSION['flash_lockout_seconds'] = $ip_result['seconds_left'];
            $_SESSION['flash_lockout_until']   = $ip_result['locked_until'];
        } elseif ($rl_result['locked']) {
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>PUPSYNC — Institutional Access Portal</title>
    <!-- Performance: preconnect to font origins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Preload the leftmost gateway photo for faster LCP -->
    <link rel="preload" as="image" href="assets/images/landing-page/1-hero-page.jpg">
    <!-- Fonts with display=swap to avoid FOIT -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="assets/css/landing-page.css">
</head>

<body>

    <!-- ================================================================
     GATEWAY — split-screen institutional access portal
================================================================ -->
    <main class="gateway" id="gateway">

        <!-- LEFT: campus photo strip + headline -->
        <section class="gateway-visual" aria-hidden="false">
            <div class="visual-strip">
                <div class="strip-photo strip-photo-1"></div>
                <div class="strip-photo strip-photo-2"></div>
                <div class="strip-photo strip-photo-3"></div>
            </div>
            <div class="visual-overlay"></div>

            <a class="visual-brand" href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">
                <i class="fa-solid fa-school"></i> PUP Biñan Campus
            </a>

            <div class="visual-content">
                <h1 class="visual-heading">
                    Borrow smart,<br>
                    <em>return proud.</em>
                </h1>
                <p class="visual-sub">
                    A secure, centralized platform that puts essential school equipment right at your fingertips — tracked, trusted, and always ready.
                </p>
            </div>
        </section>

        <!-- RIGHT: role access panel -->
        <section class="gateway-panel">

            <div class="panel-logo">
                <div class="panel-logo-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="panel-logo-text">
                    <span class="panel-logo-name">
                        <span class="brand-pup">PUP</span><span class="brand-sync">Sync</span>
                    </span>
                    <span class="panel-logo-sub">Institutional Access Portal</span>
                </div>
            </div>

            <!-- Single unified login form — the backend (landing-page.php
                 POST handler) determines from the submitted email whether
                 this is an Admin or Faculty account and redirects to the
                 matching dashboard. No role picker, no view-switching. -->
            <div class="panel-stage" id="panelStage">

                <div class="panel-view panel-view-auth active" id="loginView">

                    <div class="auth-head">
                        <p class="pane-title">Sign In</p>
                        <p class="pane-subtitle">Faculty &amp; Admin access</p>
                    </div>

                    <?php if (!empty($login_error)): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-<?= $lockout_seconds > 0 ? 'lock' : 'circle-exclamation' ?>"></i>
                            <?php if ($lockout_seconds > 0): ?>
                                <?= strpos($login_error, 'location') !== false ? 'Too many failed attempts from this location.' : 'Too many failed attempts.' ?>
                                Try again in <span id="lockout-countdown"><?= gmdate('i:s', $lockout_seconds) ?></span>.
                            <?php else: ?>
                                <?= htmlspecialchars($login_error) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="login-email" name="email"
                                    placeholder="you@pup.edu.ph"
                                    value="<?= htmlspecialchars($login_email_val) ?>"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="login-pass">Password</label>
                            <div class="input-wrap">
                                <input class="form-field" type="password" id="login-pass" name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password" required>
                                <i class="fa-solid fa-lock input-icon-left"></i>
                                <button type="button" class="eye-toggle" data-target="login-pass" tabindex="-1" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn-auth"
                            id="loginSubmitBtn"
                            <?php if ($lockout_seconds > 0): ?>
                            disabled
                            data-lockout-seconds="<?= (int)$lockout_seconds ?>"
                            <?php endif; ?>>
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Sign In
                        </button>
                    </form>
                </div>

            </div><!-- /panel-stage -->

            <div class="panel-bottom">
                <div class="panel-links">
                    <a href="#">Lending Policy</a>
                    <span class="panel-dot">&middot;</span>
                    <a href="#">FAQs</a>
                    <span class="panel-dot">&middot;</span>
                    <a href="student-dashboard.php" id="studentLink">Continue as Student</a>
                </div>
                <div class="panel-badges">
                    <span><i class="fa-solid fa-circle-check"></i> Secure Auth</span>
                    <span><i class="fa-solid fa-lock"></i> Encrypted</span>
                </div>
            </div>

        </section>

    </main><!-- /gateway -->


    <script src="assets/js/landing-page.js"></script>


</body>

</html>