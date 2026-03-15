<?php
session_start();
// Ensure server uses local timezone for login timestamps
date_default_timezone_set('Asia/Manila');

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
if (isset($_SESSION['faculty_id'])) {
    header("Location: faculty-dashboard.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "lending_db");
$login_error = $register_error = $register_success = "";
$login_email_val = $reg_fullname_val = $reg_studentid_val = $reg_email_val = "";

// ----------- LOGIN -----------
if (isset($_POST['login'])) {
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

    // ===== FACULTY LOGIN =====
    elseif ($user_type === 'faculty') {
        // Check if tbl_faculty table exists
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_faculty'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $stmt = $conn->prepare("SELECT faculty_id, fullname, password FROM tbl_faculty WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $faculty = $result->fetch_assoc();
                if (password_verify($password, $faculty['password'])) {
                    $_SESSION['faculty_id'] = $faculty['faculty_id'];
                    $_SESSION['faculty_name'] = $faculty['fullname'];
                    $_SESSION['faculty_email'] = $email;
                    $_SESSION['login_time'] = time();

                    // Update last login
                    $now_dt = date('Y-m-d H:i:s');
                    $stmt_up = $conn->prepare("UPDATE tbl_faculty SET last_login = ? WHERE faculty_id = ?");
                    if ($stmt_up) {
                        $stmt_up->bind_param("si", $now_dt, $faculty['faculty_id']);
                        $stmt_up->execute();
                        $stmt_up->close();
                    }

                    header("Location: faculty-dashboard.php");
                    exit();
                } else {
                    $login_error = "Incorrect faculty password.";
                }
            } else {
                $login_error = "Faculty account not found. Please contact admin.";
            }
        } else {
            $login_error = "Faculty portal is not yet set up. Please contact admin.";
        }
    }

    // ===== STUDENT LOGIN =====
    else {
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
                $login_error = "Incorrect student password.";
            }
        } else {
            $login_error = "Student account not found. Please register first.";
        }
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

    <link rel="stylesheet" href="CSS/landing-page.css">
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

            <!-- Center content — everything except the decorative scroll hint
                 lives here so the layout is driven by normal document flow,
                 never by absolute positioning that can collide on short screens -->
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

                <!-- Buttons come first -->
                <div class="hero-cta-group">
                    <button class="btn-primary" onclick="openModal()" aria-label="Access the system portal">
                        <span>Access Portal</span>
                        <span class="btn-icon"><i class="fa-solid fa-arrow-right"></i></span>
                    </button>
                </div>

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

                <!-- ROLE SELECTOR -->
                <div class="role-selector active" id="roleSelector">
                    <div class="selector-header">
                        <h2>Access Portal</h2>
                        <p>Choose your role to continue</p>
                    </div>
                    <div class="role-cards">
                        <button class="role-card" onclick="selectRole('student')">
                            <div class="role-icon">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <h3>Student</h3>
                            <p>Borrow equipment & reserve rooms</p>
                        </button>
                        <button class="role-card" onclick="selectRole('faculty')">
                            <div class="role-icon">
                                <i class="fa-solid fa-chalkboard-teacher"></i>
                            </div>
                            <h3>Faculty</h3>
                            <p>Manage & track resources</p>
                        </button>
                        <button class="role-card" onclick="selectRole('admin')">
                            <div class="role-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <h3>Admin</h3>
                            <p>System administration</p>
                        </button>
                    </div>
                </div>

                <!-- STUDENT LOGIN/REGISTER -->
                <div class="auth-section" id="studentSection">
                    <button class="back-btn" onclick="backToRoleSelector()">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>

                    <div class="auth-tabs" role="tablist">
                        <button class="auth-tab-btn active" id="student-tab-login" onclick="switchStudentTab('login')">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
                        </button>
                        <button class="auth-tab-btn" id="student-tab-register" onclick="switchStudentTab('register')">
                            <i class="fa-solid fa-user-plus"></i> Register
                        </button>
                    </div>

                    <!-- Student Login -->
                    <div class="auth-pane active" id="studentLogin">
                        <p class="pane-title">Student Login</p>
                        <p class="pane-subtitle">Sign in with your student account</p>
                        <?php if ($login_error && !isset($_POST['user_type'])): ?>
                            <div class="auth-alert error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($login_error) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="student-login-email">Email</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="email" id="student-login-email" name="email"
                                        placeholder="youremail@student.edu.ph"
                                        value="<?= htmlspecialchars($login_email_val) ?>"
                                        autocomplete="email" required>
                                    <i class="fa-solid fa-envelope input-icon-left"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="student-login-pass">Password</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="password" id="student-login-pass" name="password"
                                        placeholder="Enter your password"
                                        autocomplete="current-password" required>
                                    <i class="fa-solid fa-lock input-icon-left"></i>
                                    <button type="button" class="eye-toggle" onclick="toggleEye('student-login-pass', this)" tabindex="-1" aria-label="Show password">
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

                    <!-- Student Register -->
                    <div class="auth-pane" id="studentRegister">
                        <p class="pane-title">Create Student Account</p>
                        <p class="pane-subtitle">Join PUP Biñan's centralized resource system</p>
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
                                        placeholder="20XX-00XXX-BN-X"
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
                                        placeholder="youremail@student.edu.ph"
                                        value="<?= htmlspecialchars($reg_email_val) ?>"
                                        oninput="validateLettersEmail(this)"
                                        autocomplete="email" required>
                                    <i class="fa-solid fa-envelope input-icon-left"></i>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
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
                                <div class="form-group">
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
                            <button type="submit" name="register" class="btn-auth btn-auth--register">
                                <i class="fa-solid fa-user-plus"></i>
                                Create Account
                            </button>
                        </form>
                    </div>
                </div>

                <!-- FACULTY LOGIN -->
                <div class="auth-section" id="facultySection">
                    <button class="back-btn" onclick="backToRoleSelector()">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>

                    <div class="auth-pane active">
                        <p class="pane-title">Faculty Login</p>
                        <p class="pane-subtitle">Sign in with your faculty credentials</p>
                        <?php if ($login_error && isset($_POST['user_type']) && $_POST['user_type'] == 'faculty'): ?>
                            <div class="auth-alert error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($login_error) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="user_type" value="faculty">
                            <div class="form-group">
                                <label for="faculty-login-email">Faculty Email</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="email" id="faculty-login-email" name="email"
                                        placeholder="faculty@pup.edu.ph"
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
                                    <button type="button" class="eye-toggle" onclick="toggleEye('faculty-login-pass', this)" tabindex="-1" aria-label="Show password">
                                        <i class="fa-regular fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="login" class="btn-auth">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                Sign In
                            </button>
                        </form>
                        <div class="contact-admin-wrap">
                            <button class="contact-admin-btn" onclick="showContactAdminModal()">
                                Need an account? Contact Admin
                            </button>
                        </div>
                        <!-- Dev Button (temporary for development) -->
                        <div class="dev-access-wrap">
                            <span class="dev-label">⚠️ Development Mode</span>
                            <button class="dev-access-btn" onclick="window.location.href='faculty-dashboard.php'">
                                <i class="fa-solid fa-code"></i>
                                Access Faculty Dashboard (Dev)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ADMIN LOGIN -->
                <div class="auth-section" id="adminSection">
                    <button class="back-btn" onclick="backToRoleSelector()">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>

                    <div class="auth-pane active">
                        <p class="pane-title">Admin Login</p>
                        <p class="pane-subtitle">Administrative access only</p>
                        <?php if ($login_error && isset($_POST['user_type']) && $_POST['user_type'] == 'admin'): ?>
                            <div class="auth-alert error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($login_error) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
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
                                    <button type="button" class="eye-toggle" onclick="toggleEye('admin-login-pass', this)" tabindex="-1" aria-label="Show password">
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
                </div>

            </div><!-- /modal-body -->
        </div><!-- /modal-card -->
    </div><!-- /modal-overlay -->


    <script src="JavaScript/landing-page.js"></script>
    <script>
        /* ================================================================
           INIT — PHP-generated values injected here
        ================================================================ */
        <?php if ($auto_open_modal): ?>
            window.addEventListener('DOMContentLoaded', () => openModal());
        <?php endif; ?>
        <?php if (!empty($register_success)): ?>
            setTimeout(() => {
                selectRole('student');
                switchStudentTab('login');
            }, 2200);
        <?php endif; ?>
    </script>


</body>

</html>
</script>
<script>
    /* ================================================================
           INIT — PHP-generated values injected here
        ================================================================ */
    switchTab('<?= $open_tab ?>');
    <?php if ($auto_open_modal): ?>
        window.addEventListener('DOMContentLoaded', () => openModal('<?= $open_tab ?>'));
    <?php endif; ?>
    <?php if (!empty($register_success)): ?>
        setTimeout(() => switchTab('login'), 2200);
    <?php endif; ?>
</script>


</body>

</html>