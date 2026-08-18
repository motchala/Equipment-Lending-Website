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
// Ensure server uses local timezone for login timestamps
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['faculty_id'])) {
    header("Location: faculty-dashboard.php");
    exit();
}
if (isset($_SESSION['admin'])) {
    header("Location: admin-dashboard.php");
    exit();
}
if (isset($_SESSION['user_id'])) {
    // Students have no dashboard yet — keep them on the landing page
    // (fall through to show the page normally)
}

require_once __DIR__ . '/config/db.php';
$conn = getDB();
$login_error = "";
$login_email_val = "";

// ----------- LOGIN -----------
if (isset($_POST['login'])) {
    csrf_verify();
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'] ?? 'student'; // Default to student if not specified
    $login_email_val = $email;

    // ===== ADMIN LOGIN =====
    if ($user_type === 'admin') {
        // Admin shortcut login (local dev) — validate against legacy `tbl_accounts` password
        if ($email === 'main@admin.edu') {
            $stmt_acc = $conn->prepare("SELECT fullName, password FROM tbl_accounts WHERE email = ? LIMIT 1");
            if ($stmt_acc) {
                $stmt_acc->bind_param("s", $email);
                $stmt_acc->execute();
                $res_acc = $stmt_acc->get_result();
                if ($res_acc && $row_acc = $res_acc->fetch_assoc()) {
                    // tbl_accounts stores the legacy plain-text password; require it to match
                    if ($password === $row_acc['password']) {
                        $_SESSION['admin'] = true;
                        $_SESSION['login_time'] = time();

                        $admin_name_db = 'Administrator';

                        // Ensure `last_login` column exists and load previous value into session
                        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_login'");
                        if ($col_check && mysqli_num_rows($col_check) === 0) {
                            // Add column (nullable) if it's missing
                            @mysqli_query($conn, "ALTER TABLE tbl_accounts ADD COLUMN last_login DATETIME NULL");
                        }

                        // Fetch previous last_login (may be null)
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

                        // Update last_login to now
                        $now_dt = date('Y-m-d H:i:s');
                        $stmt_up = $conn->prepare("UPDATE tbl_accounts SET last_login = ? WHERE email = ?");
                        if ($stmt_up) {
                            $stmt_up->bind_param("ss", $now_dt, $email);
                            $stmt_up->execute();
                            $stmt_up->close();
                        }

                        // Try tbl_users first (regular users table)
                        $stmt_admin = $conn->prepare("SELECT fullname FROM tbl_users WHERE email = ? LIMIT 1");
                        if ($stmt_admin) {
                            $stmt_admin->bind_param("s", $email);
                            $stmt_admin->execute();
                            $res_admin = $stmt_admin->get_result();
                            if ($res_admin && $row_admin = $res_admin->fetch_assoc()) {
                                if (!empty($row_admin['fullname'])) $admin_name_db = $row_admin['fullname'];
                            }
                        }

                        // If not found, use the legacy tbl_accounts fullName
                        if ($admin_name_db === 'Administrator' && !empty($row_acc['fullName'])) {
                            $admin_name_db = $row_acc['fullName'];
                        }

                        $_SESSION['admin_name'] = $admin_name_db;
                        $_SESSION['admin_email'] = $email;
                        header("Location: admin-dashboard.php");
                        exit();
                    } else {
                        $login_error = "Invalid admin credentials.";
                    }
                } else {
                    $login_error = "Admin account not found.";
                }
            }
        } else {
            $login_error = "Admin account not found. Please use the correct admin email.";
        }
    }

    // ===== FACULTY LOGIN (borrower — uses tbl_users, goes to faculty-dashboard) =====
    elseif ($user_type === 'student') {
        $stmt = $conn->prepare("SELECT faculty_id, fullname, password FROM tbl_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['faculty_id'] = $user['faculty_id'];
                $_SESSION['faculty_name'] = $user['fullname'];
                $_SESSION['faculty_email'] = $email;
                $_SESSION['login_time'] = time();
                header("Location: faculty-dashboard.php");
                exit();
            } else {
                $login_error = "Incorrect password.";
            }
        } else {
            $login_error = "Account not found. Please register first.";
        }
    }

    // ===== FACULTY LOGIN (view-only — uses tbl_faculty, no dashboard yet) =====
    else {
        $login_error = "Faculty portal is not yet available. Please contact admin.";
    }
}

// Faculty accounts are created through a different flow now (not self-registration
// on this page), so there is no registration POST handler here anymore.

// Which panel view should be active on page load (a server-side login error
// needs to land the user back on the right form instead of the role picker).
$panel_target_role = null;
if ($login_error) {
    $panel_target_role = (($_POST['user_type'] ?? '') === 'admin') ? 'admin' : 'faculty';
}
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

            <!-- panel-stage swaps its content in place: role picker <-> login form.
                 Nothing here is a modal/popup — it's the same right-hand panel,
                 just switching which view is active. -->
            <div class="panel-stage" id="panelStage">

                <!-- ROLE PICKER -->
                <div class="panel-view panel-view-access<?= $panel_target_role ? '' : ' active' ?>" id="accessView">
                    <div class="access-cards">
                        <button class="access-card" id="gwStudentBtn" aria-label="Continue as Student">
                            <span class="access-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                            <span class="access-text">
                                <span class="access-title">Student</span>
                                <span class="access-desc">Access services &amp; check availability</span>
                            </span>
                            <span class="access-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </button>

                        <button class="access-card" id="gwFacultyBtn" aria-label="Continue as Faculty">
                            <span class="access-icon"><i class="fa-solid fa-chalkboard-teacher"></i></span>
                            <span class="access-text">
                                <span class="access-title">Faculty</span>
                                <span class="access-desc">Borrow equipment &amp; reserve rooms</span>
                            </span>
                            <span class="access-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </button>

                        <button class="access-card" id="gwAdminBtn" aria-label="Continue as Admin">
                            <span class="access-icon"><i class="fa-solid fa-gear"></i></span>
                            <span class="access-text">
                                <span class="access-title">Admin</span>
                                <span class="access-desc">System administration &amp; security</span>
                            </span>
                            <span class="access-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </button>
                    </div>
                </div>

                <!-- FACULTY LOGIN -->
                <div class="panel-view panel-view-auth<?= $panel_target_role === 'faculty' ? ' active' : '' ?>" id="facultyView">
                    <button class="back-btn" id="facultyBackBtn" type="button">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>

                    <div class="auth-head">
                        <span class="access-icon"><i class="fa-solid fa-chalkboard-teacher"></i></span>
                        <div class="auth-head-text">
                            <p class="pane-title">Faculty Portal</p>
                            <p class="pane-subtitle">Borrow equipment &amp; reserve rooms</p>
                        </div>
                    </div>

                    <?php if ($login_error && isset($_POST['user_type']) && $_POST['user_type'] == 'student'): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($login_error) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <!-- NOTE: user_type stays "student" here on purpose — that's the
                             backend key the faculty (tbl_users) login branch checks for. -->
                        <input type="hidden" name="user_type" value="student">
                        <div class="form-group">
                            <label for="faculty-login-email">Faculty Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="faculty-login-email" name="email"
                                    placeholder="faculty@pup.edu.ph"
                                    value="<?= htmlspecialchars($login_email_val) ?>"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="faculty-login-pass">Password</label>
                            <div class="input-wrap">
                                <input class="form-field" type="password" id="faculty-login-pass" name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password" required>
                                <i class="fa-solid fa-lock input-icon-left"></i>
                                <button type="button" class="eye-toggle" data-target="faculty-login-pass" tabindex="-1" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn-auth">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Sign In
                        </button>
                    </form>
                </div>

                <!-- ADMIN LOGIN -->
                <div class="panel-view panel-view-auth<?= $panel_target_role === 'admin' ? ' active' : '' ?>" id="adminView">
                    <button class="back-btn" id="adminBackBtn" type="button">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>

                    <div class="auth-head">
                        <span class="access-icon"><i class="fa-solid fa-gear"></i></span>
                        <div class="auth-head-text">
                            <p class="pane-title">Admin Login</p>
                            <p class="pane-subtitle">Administrative access only</p>
                        </div>
                    </div>

                    <?php if ($login_error && isset($_POST['user_type']) && $_POST['user_type'] == 'admin'): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($login_error) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_type" value="admin">
                        <div class="form-group">
                            <label for="admin-login-email">Admin Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="admin-login-email" name="email"
                                    placeholder="admin@pup.edu.ph"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="admin-login-pass">Password</label>
                            <div class="input-wrap">
                                <input class="form-field" type="password" id="admin-login-pass" name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password" required>
                                <i class="fa-solid fa-lock input-icon-left"></i>
                                <button type="button" class="eye-toggle" data-target="admin-login-pass" tabindex="-1" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn-auth">
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