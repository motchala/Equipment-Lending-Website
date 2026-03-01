<?php
session_start();

function validateStudentIDYear($student_id)
{
    $year = intval(substr($student_id, 0, 4));
    return $year >= 2000 && $year <= 2030;
}

if (isset($_SESSION['user_id'])) {
    header("Location: user-dashboard.php");
    exit();
}
if (isset($_SESSION['admin'])) {
    header("Location: admin-dashboard.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "lending_db");
$login_error = $register_error = $register_success = "";
$login_email_val = $reg_fullname_val = $reg_studentid_val = $reg_email_val = "";

// ----------- LOGIN -----------
if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $login_email_val = $email;

    if ($email === 'main@admin.edu' && $password === 'admin123') {
        $_SESSION['admin'] = true;
        $_SESSION['login_time'] = time();
        header("Location: admin-dashboard.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT student_id, fullname, password FROM tbl_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['student_id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['login_time'] = time();
            header("Location: user-dashboard.php");
            exit();
        } else {
            $login_error = "Incorrect password.";
        }
    } else {
        $login_error = "No account found with that email.";
    }
}

// ----------- REGISTRATION -----------
if (isset($_POST['register'])) {
    $fullname         = trim($_POST['fullname']);
    $student_id       = trim($_POST['student_id']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $reg_fullname_val  = $fullname;
    $reg_studentid_val = $student_id;
    $reg_email_val     = $email;

    if (!$fullname || !$student_id || !$email || !$password || !$confirm_password) {
        $register_error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    } elseif (strlen($student_id) != 15) {
        $register_error = "Student ID must be exactly 15 characters.";
    } elseif (!preg_match('/^2[0-9]{3}-[0-9]{5}-BN-[0-9]$/', $student_id)) {
        $register_error = "Invalid Student ID format. Use: YYYY-XXXXX-BN-X";
    } elseif (!validateStudentIDYear($student_id)) {
        $register_error = "Year must be between 2000 and 2030.";
    } elseif (strlen($password) < 4) {
        $register_error = "Password must be at least 4 characters.";
    } elseif (strlen($fullname) < 5 || strlen($fullname) > 70) {
        $register_error = "Full Name must be between 5 and 70 characters.";
    } elseif (strlen($email) < 15 || strlen($email) > 254) {
        $register_error = "Email must be between 15 and 254 characters.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM tbl_users WHERE email = ? OR student_id = ?");
        $stmt->bind_param("ss", $email, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $register_error = "Email or Student ID already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO tbl_users (fullname, student_id, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullname, $student_id, $email, $hashed);
            if ($stmt->execute()) {
                $register_success = "Account created! Redirecting to sign in…";
                $reg_fullname_val = $reg_studentid_val = $reg_email_val = "";
            } else {
                $register_error = "Error: " . $stmt->error;
            }
        }
    }
}

$open_tab = "login";
if (!empty($register_error) || !empty($register_success)) $open_tab = "register";
$auto_open_modal = (!empty($login_error) || !empty($register_error) || !empty($register_success));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>PUPSYNC — Student Equipment Lending</title>
    <!-- Performance: preconnect to font origins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Preload first hero image for faster LCP -->
    <link rel="preload" as="image" href="images/7-hero-page.jpg">
    <!-- Fonts with display=swap to avoid FOIT -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </noscript>

    <link rel="stylesheet" href="css/landing-page.css">
</head>

<body>

    <div id="phoneFrameWrap">

        <!-- ================================================================
     HERO SECTION
================================================================ -->
        <section class="hero" id="hero">

            <!-- ---- CAROUSEL SLIDES ---- -->
            <div class="carousel-track" id="carouselTrack">
                <div class="carousel-slide"></div><!-- 1 -->
                <div class="carousel-slide"></div><!-- 2 -->
                <div class="carousel-slide"></div><!-- 3 -->
                <div class="carousel-slide"></div><!-- 4 -->
                <div class="carousel-slide"></div><!-- 5 -->
                <div class="carousel-slide"></div><!-- 6 -->
                <div class="carousel-slide active"></div><!-- 7 → starts here -->
            </div>

            <!-- Progress bar -->
            <div class="carousel-progress" id="carouselProgress"></div>

            <!-- Atmosphere -->
            <div class="hero-grad"></div>
            <div class="hero-grain"></div>
            <div class="hero-grid"></div>
            <div class="blob-tl"></div>
            <div class="blob-br"></div>

            <!-- Prev / Next arrows -->
            <button class="carousel-arrow prev" onclick="carouselGo(-1)" aria-label="Previous image">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="carousel-arrow next" onclick="carouselGo(1)" aria-label="Next image">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- Dot indicators -->
            <div class="carousel-dots" id="carouselDots"></div>

            <!-- Slide counter e.g. "7 / 7" -->
            <div class="carousel-counter" id="carouselCounter"></div>

            <!-- Top bar -->
            <div class="brand-mark">
                <div class="brand-mark-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="brand-mark-text">
                    <span class="name">
                        <span class="brand-pup">PUP</span><span class="brand-sync">SYNC</span>
                    </span>
                    <span class="sub">Equipment Lending</span>
                </div>
            </div>

            <a class="school-link" href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">
                <i class="fa-solid fa-school"></i> PUP Biñan
            </a>

            <!-- Center content -->
            <div class="hero-content">
                <div class="hero-pill">
                    <span class="dot"></span>
                    Student Resource Portal &nbsp;·&nbsp; PUP Biñan
                </div>
                <h1 class="hero-h1">
                    <em>Borrow smart,</em><br>
                    <span class="line-accent">return proud.</span>
                </h1>
                <p class="hero-sub">
                    A secure, student-built platform that puts essential school equipment right at your fingertips — tracked, trusted, and always ready.
                </p>
                <div class="hero-cta-group">
                    <button class="btn-primary" onclick="openModal('login')" aria-label="Sign in to your account">
                        <span>Access Portal</span>
                        <span class="btn-icon"><i class="fa-solid fa-arrow-right"></i></span>
                    </button>
                    <button class="btn-ghost" onclick="openModal('register')" aria-label="Create a new account">
                        <i class="fa-solid fa-user-plus"></i>
                        New here? Join free
                    </button>
                </div>
            </div>

            <!-- Stat chips -->
            <div class="stat-chips">
                <div class="chip"><i class="fa-solid fa-circle-check"></i> 100% Student-Led</div>
                <div class="chip"><i class="fa-solid fa-shield-halved"></i> Secure Session Auth</div>
                <div class="chip"><i class="fa-solid fa-gift"></i> Free for All Students</div>
            </div>

            <!-- Scroll hint -->
            <div class="deco-line" onclick="document.getElementById('site-footer').scrollIntoView({behavior:'smooth'})">
                <span>Scroll</span>
                <div class="deco-line-bar"></div>
            </div>

        </section><!-- /hero -->


        <!-- ================================================================
     FOOTER
================================================================ -->
        <footer class="site-footer" id="site-footer">
            <div class="footer-accent-line"></div>
            <div class="footer-bg-blob"></div>

            <div class="footer-inner">

                <div class="footer-top">

                    <!-- Brand column -->
                    <div class="footer-brand-col">
                        <div class="footer-logo">
                            <div class="footer-logo-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                            <div class="footer-logo-text">
                                <span class="name">
                                    <span class="brand-pup">PUP</span><span class="brand-sync">SYNC</span>
                                </span>
                                <span class="tagline">A Centralized Resource Hub</span>
                            </div>
                        </div>
                        <p class="footer-brand-desc">
                            A student-built platform designed for the responsible borrowing and tracking of school equipment at PUP Biñan Campus — free, secure, and always available.
                        </p>
                        <div class="footer-socials">
                            <a class="social-btn" href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                            <a class="social-btn" href="#" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
                            <a class="social-btn" href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                            <a class="social-btn" href="mailto:pupsync@student.pup.edu.ph" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
                        </div>
                    </div>

                    <!-- Portal column -->
                    <div>
                        <p class="footer-col-title">Portal</p>
                        <ul class="footer-links">
                            <li><a href="#" onclick="openModal('login'); return false;"><i class="fa-solid fa-chevron-right"></i> Sign In</a></li>
                            <li><a href="#" onclick="openModal('register'); return false;"><i class="fa-solid fa-chevron-right"></i> Create Account</a></li>
                            <li><a href="user-dashboard.php"><i class="fa-solid fa-chevron-right"></i> My Dashboard</a></li>
                            <li><a href="user-dashboard.php"><i class="fa-solid fa-chevron-right"></i> Borrow Equipment</a></li>
                            <li><a href="user-dashboard.php"><i class="fa-solid fa-chevron-right"></i> My Requests</a></li>
                        </ul>
                    </div>

                    <!-- Info column -->
                    <div>
                        <p class="footer-col-title">Information</p>
                        <ul class="footer-links">
                            <li><a href="#"><i class="fa-solid fa-chevron-right"></i> How It Works</a></li>
                            <li><a href="#"><i class="fa-solid fa-chevron-right"></i> Equipment List</a></li>
                            <li><a href="#"><i class="fa-solid fa-chevron-right"></i> Lending Policy</a></li>
                            <li><a href="#"><i class="fa-solid fa-chevron-right"></i> Return Guidelines</a></li>
                            <li><a href="#"><i class="fa-solid fa-chevron-right"></i> FAQs</a></li>
                        </ul>
                    </div>

                    <!-- Contact column -->
                    <div>
                        <p class="footer-col-title">Contact</p>

                        <div class="footer-status">
                            <span class="status-dot"></span>
                            System Online
                        </div>

                        <div class="footer-info-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>PUP Biñan Campus, Biñan, Laguna 4024, Philippines</span>
                        </div>
                        <div class="footer-info-item">
                            <i class="fa-solid fa-envelope"></i>
                            <span><a href="mailto:pupsync@student.pup.edu.ph">pupsync@student.pup.edu.ph</a></span>
                        </div>
                        <div class="footer-info-item">
                            <i class="fa-solid fa-globe"></i>
                            <span><a href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">pup.edu.ph/binan</a></span>
                        </div>
                        <div class="footer-info-item">
                            <i class="fa-solid fa-clock"></i>
                            <span>Mon – Fri &nbsp;·&nbsp; 7:00 AM – 5:00 PM</span>
                        </div>
                    </div>

                </div><!-- /footer-top -->

                <div class="footer-rule"></div>

                <div class="footer-bottom">
                    <p class="footer-copy">
                        &copy; 2026 <strong>PUPSYNC</strong> — For Students, By Students.<br>
                        Part of <a href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">Polytechnic University of the Philippines — Biñan Campus</a>.
                    </p>
                    <div class="footer-badges">
                        <span class="footer-badge"><i class="fa-solid fa-shield-halved"></i> Secure Auth</span>
                        <span class="footer-badge"><i class="fa-solid fa-lock"></i> Encrypted</span>
                        <button class="footer-badge mobile-toggle-btn" id="mobileToggleBtn" onclick="toggleMobileView()" title="Switch to mobile preview layout" aria-pressed="false">
                            <i class="fa-solid fa-mobile-screen-button" id="mobileToggleIcon"></i>
                            <span id="mobileToggleLabel">Mobile Ready</span>
                        </button>
                    </div>
                </div>

                <!-- ── MOBILE COMPRESSED FOOTER ── shown only in .mobile-preview -->
                <div class="footer-mobile-bar">

                    <!-- Brand + status row -->
                    <div class="fmb-brand-row">
                        <div class="fmb-logo">
                            <div class="footer-logo-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                            <div class="footer-logo-text">
                                <span class="name"><span class="brand-pup">PUP</span><span class="brand-sync">SYNC</span></span>
                                <span class="tagline">Equipment Lending System</span>
                            </div>
                        </div>
                        <div class="footer-status">
                            <span class="status-dot"></span>
                            System Online
                        </div>
                    </div>

                    <!-- Link grid: 2 columns side by side -->
                    <div class="fmb-links-grid">
                        <div class="fmb-col">
                            <p class="footer-col-title">Portal</p>
                            <ul class="footer-links">
                                <li><a href="user-dashboard.php"><i class="fa-solid fa-chevron-right"></i> My Dashboard</a></li>
                                <li><a href="user-dashboard.php"><i class="fa-solid fa-chevron-right"></i> Borrow Equipment</a></li>
                                <li><a href="user-dashboard.php"><i class="fa-solid fa-chevron-right"></i> My Requests</a></li>
                            </ul>
                        </div>
                        <div class="fmb-col">
                            <p class="footer-col-title">Information</p>
                            <ul class="footer-links">
                                <li><a href="#"><i class="fa-solid fa-chevron-right"></i> How It Works</a></li>
                                <li><a href="#"><i class="fa-solid fa-chevron-right"></i> Equipment List</a></li>
                                <li><a href="#"><i class="fa-solid fa-chevron-right"></i> Lending Policy</a></li>
                                <li><a href="#"><i class="fa-solid fa-chevron-right"></i> FAQs</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Contact strip -->
                    <div class="fmb-contact-strip">
                        <div class="footer-info-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>PUP Biñan Campus, Biñan, Laguna 4024</span>
                        </div>
                        <div class="footer-info-item">
                            <i class="fa-solid fa-envelope"></i>
                            <span><a href="mailto:pupsync@student.pup.edu.ph">pupsync@student.pup.edu.ph</a></span>
                        </div>
                        <div class="footer-info-item">
                            <i class="fa-solid fa-clock"></i>
                            <span>Mon – Fri &nbsp;·&nbsp; 7:00 AM – 5:00 PM</span>
                        </div>
                    </div>

                    <!-- Socials -->
                    <div class="footer-socials fmb-socials">
                        <a class="social-btn" href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a class="social-btn" href="#" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
                        <a class="social-btn" href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a class="social-btn" href="mailto:equiplend@student.pup.edu.ph" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
                    </div>

                    <!-- Bottom rule + copyright + desktop toggle -->
                    <div class="fmb-bottom">
                        <p class="footer-copy">
                            &copy; 2026 <strong>PUPSYNC</strong> — For Students, By Students.<br>
                            <a href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">PUP Biñan Campus</a>
                        </p>
                        <button class="fmb-desktop-btn" onclick="toggleMobileView()" aria-label="Switch to desktop layout">
                            <i class="fa-solid fa-desktop"></i> Desktop View
                        </button>
                    </div>

                </div><!-- /footer-mobile-bar -->

            </div><!-- /footer-inner -->
        </footer>


        <!-- /phoneFrameWrap -->
    </div>

    <!-- ================================================================
     AUTH MODAL — lives OUTSIDE phoneFrameWrap so position:fixed
     is always relative to the actual viewport, not the frame element
================================================================ -->
    <div class="modal-overlay" id="authModal" role="dialog" aria-modal="true" aria-label="Sign in or Register">
        <div class="modal-backdrop" onclick="closeModal()"></div>
        <div class="modal-card" id="modalCard">

            <div class="modal-handle" onclick="toggleMinimize()" role="button" tabindex="0"
                aria-label="Minimize or restore auth panel"
                onkeydown="if(event.key==='Enter'||event.key===' ')toggleMinimize()">
                <div class="modal-handle-bar">
                    <div class="modal-handle-pill"></div>
                    <span class="modal-handle-label">Student Portal</span>
                    <span class="modal-minimized-hint">Tap to expand</span>
                </div>
                <div class="modal-handle-actions" onclick="event.stopPropagation()">
                    <div class="modal-action-btn" id="minimizeBtn" onclick="toggleMinimize()"
                        title="Minimize" aria-label="Minimize panel" role="button" tabindex="0"
                        onkeydown="if(event.key==='Enter')toggleMinimize()">
                        <i class="fa-solid fa-minus"></i>
                    </div>
                    <div class="modal-action-btn" onclick="closeModal()"
                        title="Close" aria-label="Close panel" role="button" tabindex="0"
                        onkeydown="if(event.key==='Enter')closeModal()">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                </div>
            </div>

            <div class="modal-body" id="modalBody">

                <div class="auth-tabs" role="tablist">
                    <button class="auth-tab-btn" id="tab-login" role="tab" aria-selected="true" onclick="switchTab('login')">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
                    </button>
                    <button class="auth-tab-btn" id="tab-register" role="tab" aria-selected="false" onclick="switchTab('register')">
                        <i class="fa-solid fa-user-plus"></i> Register
                    </button>
                </div>

                <!-- LOGIN PANE -->
                <div class="auth-pane" id="pane-login" role="tabpanel">
                    <p class="pane-title">Welcome back</p>
                    <p class="pane-subtitle">Access PUP Biñan's Resource Hub</p>
                    <?php if ($login_error): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($login_error) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="login-email" name="email"
                                    placeholder="your@student.edu.ph"
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
                                <button type="button" class="eye-toggle" onclick="toggleEye('login-pass', this)" tabindex="-1" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn-auth">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Sign In
                        </button>
                    </form>
                    <div class="modal-footer-link">
                        <span>No account?</span>
                        <button onclick="switchTab('register')">Register here</button>
                    </div>
                </div>

                <!-- REGISTER PANE -->
                <div class="auth-pane" id="pane-register" role="tabpanel">
                    <p class="pane-title">New account</p>
                    <p class="pane-subtitle">Join PUP Biñan's centralized resource system.</p>
                    <?php if ($register_error): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($register_error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($register_success): ?>
                        <div class="auth-alert success">
                            <i class="fa-solid fa-circle-check"></i>
                            <?= htmlspecialchars($register_success) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="reg-name">Full Name</label>
                            <div class="input-wrap">
                                <input class="form-field" type="text" id="reg-name" name="fullname"
                                    minlength="5" maxlength="70"
                                    placeholder="Juan Dela Cruz"
                                    value="<?= htmlspecialchars($reg_fullname_val) ?>"
                                    oninput="validateLettersName(this)"
                                    autocomplete="name" required>
                                <i class="fa-solid fa-user input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reg-sid">Student ID</label>
                            <div class="input-wrap">
                                <input class="form-field" type="text" id="reg-sid" name="student_id"
                                    minlength="15" maxlength="15"
                                    placeholder="2026-00001-BN-0"
                                    value="<?= htmlspecialchars($reg_studentid_val) ?>"
                                    oninput="validateLettersStudentID(this)"
                                    title="Format: YYYY-XXXXX-BN-X"
                                    autocomplete="off" required>
                                <i class="fa-solid fa-id-card input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reg-email">Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="reg-email" name="email"
                                    minlength="15" maxlength="254"
                                    placeholder="your@student.edu.ph"
                                    value="<?= htmlspecialchars($reg_email_val) ?>"
                                    oninput="validateLettersEmail(this)"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="margin-bottom:0">
                                <label for="reg-pass">Password</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="password" id="reg-pass" name="password"
                                        minlength="4" placeholder="Min. 4 characters"
                                        autocomplete="new-password" required>
                                    <i class="fa-solid fa-lock input-icon-left"></i>
                                    <button type="button" class="eye-toggle" onclick="toggleEye('reg-pass', this)" tabindex="-1" aria-label="Show password">
                                        <i class="fa-regular fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label for="reg-cpass">Confirm</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="password" id="reg-cpass" name="confirm_password"
                                        minlength="4" placeholder="Re-enter password"
                                        autocomplete="new-password" required>
                                    <i class="fa-solid fa-lock input-icon-left"></i>
                                    <button type="button" class="eye-toggle" onclick="toggleEye('reg-cpass', this)" tabindex="-1" aria-label="Show password">
                                        <i class="fa-regular fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="register" class="btn-auth" style="margin-top:1rem;">
                            <i class="fa-solid fa-user-plus"></i>
                            Create Account
                        </button>
                    </form>
                    <div class="modal-footer-link">
                        <span>Have an account?</span>
                        <button onclick="switchTab('login')">Sign in here</button>
                    </div>
                </div>

            </div><!-- /modal-body -->
        </div><!-- /modal-card -->
    </div><!-- /modal-overlay -->


    <script>
        /* ================================================================
   CAROUSEL
   7 images: images/1-hero-page.jpg … images/7-hero-page.jpg
   Start at index 6 (= slide 7), advance every 6 seconds
================================================================ */
        (function() {
            const TOTAL = 7;
            const INTERVAL = 6000; // 6 s between transitions
            const IMG_BASE = 'images-design/';
            const IMG_SUFFIX = '-hero-page.jpg';

            const track = document.getElementById('carouselTrack');
            const slides = Array.from(track.querySelectorAll('.carousel-slide'));
            const dotsWrap = document.getElementById('carouselDots');
            const counter = document.getElementById('carouselCounter');
            const progress = document.getElementById('carouselProgress');

            let current = 6; // start at index 6 (image 7)
            let timer = null;
            let progTimer = null;

            /* Assign background images */
            slides.forEach((s, i) => {
                s.style.backgroundImage = `url('${IMG_BASE}${i + 1}${IMG_SUFFIX}')`;
            });

            /* Build dots */
            for (let i = 0; i < TOTAL; i++) {
                const d = document.createElement('button');
                d.className = 'carousel-dot' + (i === current ? ' active' : '');
                d.setAttribute('aria-label', `Go to slide ${i + 1}`);
                d.addEventListener('click', () => goTo(i));
                dotsWrap.appendChild(d);
            }

            function getDots() {
                return dotsWrap.querySelectorAll('.carousel-dot');
            }

            function updateUI() {
                slides.forEach((s, i) => s.classList.toggle('active', i === current));
                getDots().forEach((d, i) => d.classList.toggle('active', i === current));
                counter.textContent = `${current + 1} / ${TOTAL}`;
            }

            /* Animated progress bar */
            function startProgress() {
                clearInterval(progTimer);
                progress.style.transition = 'none';
                progress.style.width = '0%';
                /* Force reflow */
                void progress.offsetWidth;
                progress.style.transition = `width ${INTERVAL}ms linear`;
                progress.style.width = '100%';
            }

            function goTo(idx) {
                current = (idx + TOTAL) % TOTAL;
                updateUI();
                resetTimer();
                startProgress();
            }

            window.carouselGo = function(dir) {
                goTo(current + dir);
            };

            function resetTimer() {
                clearInterval(timer);
                timer = setInterval(() => goTo(current + 1), INTERVAL);
            }

            /* Init */
            updateUI();
            startProgress();
            resetTimer();

            /* Pause on hover */
            track.closest('.hero').addEventListener('mouseenter', () => {
                clearInterval(timer);
                clearInterval(progTimer);
                progress.style.transition = 'none';
            });
            track.closest('.hero').addEventListener('mouseleave', () => {
                startProgress();
                resetTimer();
            });

            /* Touch swipe support */
            let touchStartX = 0;
            track.addEventListener('touchstart', e => {
                touchStartX = e.touches[0].clientX;
            }, {
                passive: true
            });
            track.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - touchStartX;
                if (Math.abs(dx) > 40) goTo(current + (dx < 0 ? 1 : -1));
            }, {
                passive: true
            });
        })();


        /* ================================================================
           FOOTER SCROLL-REVEAL (IntersectionObserver)
        ================================================================ */
        (function() {
            const footer = document.getElementById('site-footer');
            const obs = new IntersectionObserver(
                ([entry]) => {
                    if (entry.isIntersecting) {
                        footer.classList.add('visible');
                        obs.disconnect();
                    }
                }, {
                    threshold: 0.05
                }
            );
            obs.observe(footer);
        })();


        /* ================================================================
           MODAL
        ================================================================ */
        const overlay = document.getElementById('authModal');
        let isMin = false;

        function openModal(tab) {
            overlay.classList.remove('minimized');
            isMin = false;
            updateMinimizeIcon();
            overlay.classList.add('open');
            if (tab) switchTab(tab);
            /* Only lock scroll when NOT in mobile-preview (frame handles its own scroll) */
            const frame = document.getElementById('phoneFrameWrap');
            if (!frame.classList.contains('mobile-preview')) {
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal() {
            overlay.classList.remove('open', 'minimized');
            isMin = false;
            document.body.style.overflow = '';
        }

        function toggleMinimize() {
            isMin = !isMin;
            overlay.classList.toggle('minimized', isMin);
            updateMinimizeIcon();
        }

        function updateMinimizeIcon() {
            const btn = document.getElementById('minimizeBtn');
            btn.querySelector('i').className = isMin ?
                'fa-solid fa-up-right-and-down-left-from-center' :
                'fa-solid fa-minus';
            btn.title = isMin ? 'Restore' : 'Minimize';
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (isMin) {
                    isMin = false;
                    overlay.classList.remove('minimized');
                    updateMinimizeIcon();
                } else closeModal();
            }
        });


        /* ================================================================
           TAB SWITCHER
        ================================================================ */
        function switchTab(tab) {
            document.querySelectorAll('.auth-tab-btn').forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.auth-pane').forEach(p => p.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            document.getElementById('tab-' + tab).setAttribute('aria-selected', 'true');
            document.getElementById('pane-' + tab).classList.add('active');
        }


        /* ================================================================
           INPUT VALIDATORS
        ================================================================ */
        function validateLettersName(input) {
            if (input.value.length > 0 && !/^[a-zA-Z]/.test(input.value)) {
                input.value = '';
                return;
            }
            input.value = input.value.replace(/[^a-zA-Z\s.']/g, '');
        }

        function validateLettersStudentID(input) {
            let val = input.value.toUpperCase().replace(/[^0-9A-Z-]/g, '');
            let result = '';
            for (let i = 0; i < val.length && i < 17; i++) {
                let c = val[i];
                if (i < 4) {
                    if (!/[0-9]/.test(c)) continue;
                    if (i === 0 && c !== '2') continue;
                    if (i === 1 && result[0] === '2' && c !== '0') continue;
                    if (i === 2 && result === '20' && !/[0-3]/.test(c)) continue;
                    if (i === 3 && result === '203' && c !== '0') continue;
                    result += c;
                } else if (i === 4) {
                    if (c === '-') result += c;
                } else if (i < 10) {
                    if (/[0-9]/.test(c)) result += c;
                } else if (i === 10) {
                    if (c === '-') result += c;
                } else if (i === 11) {
                    if (c === 'B') result += c;
                } else if (i === 12) {
                    if (c === 'N') result += c;
                } else if (i === 13) {
                    if (c === '-') result += c;
                } else if (i === 14) {
                    if (/[0-9]/.test(c)) result += c;
                }
            }
            input.value = result;
        }

        function validateLettersEmail(input) {
            if (input.value.length > 0 && !/^[a-zA-Z0-9]/.test(input.value)) {
                input.value = '';
                return;
            }
            input.value = input.value.replace(/[^a-zA-Z0-9.@_-]/g, '');
        }


        /* ================================================================
           AUTO-DISMISS ALERTS
        ================================================================ */
        setTimeout(() => {
            document.querySelectorAll('.auth-alert').forEach(el => {
                el.style.transition = 'opacity 0.5s, max-height 0.4s, margin 0.4s';
                el.style.opacity = '0';
                el.style.maxHeight = '0';
                el.style.overflow = 'hidden';
                el.style.marginBottom = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);


        /* ================================================================
           INIT
        ================================================================ */
        <?php if ($auto_open_modal): ?>
            window.addEventListener('DOMContentLoaded', () => openModal('<?= $open_tab ?>'));
        <?php endif; ?>
        <?php if (!empty($register_success)): ?>
            setTimeout(() => switchTab('login'), 2200);
        <?php endif; ?>

        switchTab('<?= $open_tab ?>');


        /* ================================================================
           MOBILE VIEW TOGGLE
           Creates a phone-frame preview centered in the browser window.
           No browser resize needed — the UI itself scales to phone dimensions.
        ================================================================ */
        let isMobilePreview = false;

        function toggleMobileView() {
            isMobilePreview = !isMobilePreview;
            const btn = document.getElementById('mobileToggleBtn');
            const icon = document.getElementById('mobileToggleIcon');
            const label = document.getElementById('mobileToggleLabel');
            const frame = document.getElementById('phoneFrameWrap');
            const html = document.documentElement;

            if (isMobilePreview) {
                html.classList.add('mobile-preview-bg');
                frame.classList.add('mobile-preview');
                btn.setAttribute('aria-pressed', 'true');
                icon.className = 'fa-solid fa-desktop';
                label.textContent = 'Desktop Ready';
                btn.title = 'Exit mobile preview';
                closeModal();
                /* Scroll browser window to top so hero fills the phone frame */
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            } else {
                html.classList.remove('mobile-preview-bg');
                frame.classList.remove('mobile-preview');
                btn.setAttribute('aria-pressed', 'false');
                icon.className = 'fa-solid fa-mobile-screen-button';
                label.textContent = 'Mobile Ready';
                btn.title = 'Switch to mobile preview layout';
            }
        }
        /* ================================================================
           PASSWORD VISIBILITY TOGGLE
        ================================================================ */
        function toggleEye(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                /* Currently hidden → reveal */
                input.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                /* Currently visible → hide */
                input.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
                btn.setAttribute('aria-label', 'Show password');
            }
        }
    </script>

</body>

</html>