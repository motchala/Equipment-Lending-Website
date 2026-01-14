<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: user-dashboard.php");
    exit();
}

if (isset($_SESSION['admin'])) {
    header("Location: admin-dashboard.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "lending_db");
$login_error = "";
$register_error = "";
$register_success = "";


// ----------- LOGIN -----------
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Admin login
    if ($email === 'main@admin.edu' && $password === 'admin123') {
        $_SESSION['admin'] = true;
        $_SESSION['login_time'] = time();
        header("Location: admin-dashboard.php");
        exit();
    }

    // User login
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
    $fullname = trim($_POST['fullname']);
    $student_id = trim($_POST['student_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (!$fullname || !$student_id || !$email || !$password || !$confirm_password) {
        $register_error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    } else {
        // Check if email or student_id exists
        $stmt = $conn->prepare("SELECT * FROM tbl_users WHERE email = ? OR student_id = ?");
        $stmt->bind_param("ss", $email, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $register_error = "Email or Student ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO tbl_users (fullname, student_id, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullname, $student_id, $email, $hashed_password);
            if ($stmt->execute()) {
                $register_success = "Registration successful. You can now sign in.";
            } else {
                $register_error = "Error: " . $stmt->error;
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <title>Equipment Lending</title>

    <style>
        /**
         * SECTION 2: CSS STYLES
         */
        body {
            margin: 0;
            background: #000;
            font-family: 'Segoe UI', sans-serif;
        }

        /* NAVBAR */
        .navbar {
            background: transparent;
            transition: background 0.4s ease;
            z-index: 1060;
        }

        .navbar-scrolled {
            background: rgba(47, 4, 4, 0.85);
            backdrop-filter: blur(6px);
        }

        /* HERO */
        .hero {
            height: 100vh;
            position: relative;
            overflow: hidden;
        }

        .hero img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.8);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom,
                    rgba(0, 0, 0, 0.6),
                    rgba(0, 0, 0, 0.9));
        }

        /* SIDEBAR PANEL */
        .hero-right {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 500px;
            background: rgba(47, 4, 4, 0.98);
            backdrop-filter: blur(12px);
            z-index: 1070;
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            padding: 2rem;
            justify-content: flex-start;
            color: #fff;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.5);
        }

        .hero-right.active {
            transform: translateX(0);
        }

        /* CUSTOM TABS STYLING */
        .nav-tabs {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.6);
            border: none;
            font-weight: 600;
            padding: 10px 20px;
        }

        .nav-tabs .nav-link.active {
            background: transparent;
            color: #fff;
            border-bottom: 3px solid #fff;
        }

        /* Dimmer Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1065;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
            backdrop-filter: blur(10px);
        }

        #close-sidebar {
            position: absolute;
            top: 25px;
            right: 25px;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.7;
            transition: 0.3s;
        }

        #close-sidebar:hover {
            opacity: 1;
        }

        /* Sign In & Register Form Styles */
        .auth-container h2 {
            max-width: 400px;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .auth-container input {
            margin-bottom: .75rem;
            padding: 0.5rem;
            width: 100%;
            border-radius: 0.5rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .auth-container input:focus {
            background: rgba(255, 255, 255, 0.1);
            outline: none;
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .auth-container button {
            padding: 0.75rem;
            width: 100%;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 1rem;
        }

        .custom-about-btn {
            border-width: 1px !important;
            padding: 15px 40px;
            display: inline-block;
            min-width: 200px;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .custom-about-btn:hover {
            border-width: 10px !important;
            background-color: white;
            color: #8B0000;
        }

        /* Rest of Hero Content */
        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: #fff;
            padding: 0 1rem;
        }

        .hero h1 {
            font-size: clamp(3rem, 6vw, 5rem);
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .hero p {
            max-width: 600px;
            margin-top: 1rem;
            opacity: 0.9;
        }

        .hero-actions {
            margin-top: 2.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-actions .btn {
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
        }

        /* QUICK STATS */
        .hero-stats {
            position: absolute;
            bottom: 40px;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 3rem;
            color: #fff;
            z-index: 2;
        }

        .stat {
            text-align: center;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .stat i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 0.3rem;
        }

        /* FAQ FOOTER */
        .faq-footer {
            background: rgba(47, 4, 4, 0.85);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem 0;
            color: #f5f5dc;
        }

        .footer-title {
            font-weight: 700;
        }

        .footer-text {
            max-width: 420px;
            opacity: 0.85;
            font-size: 0.95rem;
        }

        .footer-meta {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .footer-copy {
            opacity: 0.6;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>
    <div class="overlay" id="ui-overlay"></div>

    <nav class="navbar navbar-expand-sm navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-box-seam me-2"></i>EquipmentLending
            </a>
            <div class="ms-auto">
                <button id="navSignInBtn" class="btn btn-outline-light rounded-pill px-4">Sign In</button>
            </div>
        </div>
    </nav>

    <section class="hero">
        <img src="images/7-hero-page.jpg" alt="School Equipment Lending">
        <div class="hero-overlay"></div>

        <div class="hero-content">
            <h1>Access When It Matters Most</h1>
            <p>A student-built platform ensuring essential school equipment is always within reach.</p>
            <div class="hero-actions">
                <a href="https://www.pup.edu.ph/binan/" target="_blank" class="btn btn-outline-light custom-about-btn">About Us</a>
            </div>
        </div>

        <div class="hero-stats">
            <div class="stat"><i class="bi bi-box"></i>Shared Resources</div>
            <div class="stat"><i class="bi bi-people"></i>Student-Led System</div>
            <div class="stat"><i class="bi bi-shield-check"></i>Reliable & Secure</div>
        </div>

        <div class="hero-right" id="hero-right">
            <button id="close-sidebar"><i class="bi bi-x-lg"></i></button>

            <div class="auth-container">
                <ul class="nav nav-tabs" id="authTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane"
                            type="button" role="tab">Sign In</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane"
                            type="button" role="tab">Register</button>
                    </li>
                </ul>

                <div class="tab-content" id="authTabContent">


                    <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                        <h2>Welcome Back</h2>
                        <form method="POST">
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="password" name="password" placeholder="Password" required>

                            <?php if ($login_error): ?>
                                <div class="alert alert-danger mt-2">
                                    <?= $login_error ?>
                                </div>
                            <?php endif; ?>
                            <button type="submit" name="login" class="btn btn-light">Sign In</button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="register-pane" role="tabpanel">
                        <h2>Create Account</h2>
                        <form method="POST">
                            <input type="text" name="fullname" placeholder="Full Name" required>
                            <input type="text" name="student_id" placeholder="Student ID" required>
                            <input type="email" name="email" placeholder="Student Email" required>
                            <input type="password" name="password" placeholder="Create Password" required>
                            <input type="password" name="confirm_password" placeholder="Confirm Password" required>

                            <?php if ($register_error): ?>
                                <div class="alert alert-danger mt-2">
                                    <?= $register_error ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($register_success): ?>
                                <div class="alert alert-success mt-2">
                                    <?= $register_success ?>
                                </div>
                            <?php endif; ?>

                            <button type="submit" name="register" class="btn btn-light">Register</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <footer class="faq-footer">
        <div class="container">
            <div class="row gy-4 align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <h5 class="footer-title">EquipmentLending</h5>
                    <p class="footer-text">A student-led equipment lending platform built to support academic needs
                        through shared access and responsibility.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="footer-meta">Need more help?<br><span>Contact an Admin or Lending Committee</span></p>
                    <small class="footer-copy">© 2026 EquipmentLending · For Students, By Students</small>
                </div>
            </div>
        </div>
    </footer>



    <script>
        const nav = document.querySelector('.navbar');
        const signInBtn = document.getElementById('navSignInBtn');
        const closeBtn = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('hero-right');
        const overlay = document.getElementById('ui-overlay');

        window.addEventListener('scroll', () => {
            nav.classList.toggle('navbar-scrolled', window.scrollY > 50);
        });

        signInBtn.addEventListener('click', (e) => {
            e.preventDefault();
            sidebar.classList.add('active');
            overlay.classList.add('active');
        });

        const closeAll = () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        };

        closeBtn.addEventListener('click', closeAll);
        overlay.addEventListener('click', closeAll);

        <?php if (!empty($login_error) || !empty($register_error)): ?>
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.getElementById('login-tab').click();
        <?php endif; ?>
    </script>

</body>

</html>