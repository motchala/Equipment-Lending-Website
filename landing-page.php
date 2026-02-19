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
    <title>EQUIPLEND — Student Equipment Lending</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* ================================================================
   TOKENS
================================================================ */
        :root {
            --maroon: #6d1b23;
            --maroon-deep: #4a0f15;
            --maroon-dim: #2d0509;
            --maroon-glow: rgba(109, 27, 35, 0.6);
            --cream: #f5f0e8;
            --white: #ffffff;
            --border: rgba(255, 255, 255, 0.12);
            --card-bg: rgba(15, 5, 7, 0.82);

            /* Modal tokens */
            --modal-bg: #0e0406;
            --modal-sur: #180608;
            --modal-bdr: rgba(109, 27, 35, 0.3);
            --text-hi: #f0e8e0;
            --text-mid: #9e8a80;
            --text-dim: #5a4840;

            --r-sm: 10px;
            --r-md: 16px;
            --r-lg: 22px;

            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --ease-in: cubic-bezier(0.7, 0, 0.84, 0);
        }

        /* ================================================================
   RESET & BASE
================================================================ */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--maroon-dim);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ================================================================
   HERO — full-screen first view
================================================================ */
        .hero {
            position: relative;
            width: 100vw;
            height: 100vh;
            min-height: 500px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* ================================================================
   CAROUSEL — cross-fade slides
================================================================ */
        .carousel-track {
            position: absolute;
            inset: 0;
            z-index: 0;
        }

        .carousel-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transform: scale(1.06);
            transition: opacity 1.4s ease, transform 7s ease-out;
            filter: saturate(0.35) brightness(0.55);
            will-change: opacity, transform;
        }

        /* The 7 slide images — start from #7 (index 6) */
        .carousel-slide:nth-child(1) {
            background-image: url('images/1-hero-page.jpg');
        }

        .carousel-slide:nth-child(2) {
            background-image: url('images/2-hero-page.jpg');
        }

        .carousel-slide:nth-child(3) {
            background-image: url('images/3-hero-page.jpg');
        }

        .carousel-slide:nth-child(4) {
            background-image: url('images/4-hero-page.jpg');
        }

        .carousel-slide:nth-child(5) {
            background-image: url('images/5-hero-page.jpg');
        }

        .carousel-slide:nth-child(6) {
            background-image: url('images/6-hero-page.jpg');
        }

        .carousel-slide:nth-child(7) {
            background-image: url('images/7-hero-page.jpg');
        }

        .carousel-slide.active {
            opacity: 1;
            transform: scale(1.12);
        }

        /* Prev / Next arrows */
        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 8;
            width: 40px;
            height: 40px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(15, 3, 5, 0.45);
            backdrop-filter: blur(8px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.25s;
            opacity: 0;
            animation: fadeIn 1s 1.5s both;
        }

        .carousel-arrow:hover {
            background: rgba(109, 27, 35, 0.7);
            border-color: rgba(109, 27, 35, 0.6);
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-arrow.prev {
            left: 1.4rem;
        }

        .carousel-arrow.next {
            right: 1.4rem;
        }

        /* Dot indicators */
        .carousel-dots {
            position: absolute;
            bottom: 1.4rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 8;
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0;
            animation: fadeIn 1s 1.6s both;
        }

        .carousel-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            cursor: pointer;
            transition: all 0.3s var(--ease-out);
            border: 1px solid transparent;
        }

        .carousel-dot.active {
            background: rgba(255, 255, 255, 0.85);
            transform: scale(1.35);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Slide counter */
        .carousel-counter {
            position: absolute;
            bottom: 1.35rem;
            right: 1.6rem;
            z-index: 8;
            font-size: 0.62rem;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.3);
            font-weight: 500;
            font-variant-numeric: tabular-nums;
            opacity: 0;
            animation: fadeIn 1s 1.7s both;
        }

        /* Progress bar (thin line at very bottom of hero) */
        .carousel-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 2px;
            background: var(--maroon);
            z-index: 8;
            width: 0%;
            transition: width linear;
            opacity: 0.7;
        }

        /* ================================================================
   ATMOSPHERE OVERLAYS (sit on top of carousel)
================================================================ */
        .hero-grad {
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(to top, rgba(45, 5, 9, 1) 0%, rgba(45, 5, 9, 0.5) 35%, transparent 65%),
                linear-gradient(to right, rgba(45, 5, 9, 0.7) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 30%, rgba(109, 27, 35, 0.25) 0%, transparent 55%);
            pointer-events: none;
        }

        .hero-grain {
            position: absolute;
            inset: -50%;
            width: 200%;
            height: 200%;
            z-index: 2;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='1'/%3E%3C/svg%3E");
            opacity: 0.04;
            animation: grainShift 0.8s steps(1) infinite;
            pointer-events: none;
        }

        @keyframes grainShift {
            0% {
                transform: translate(0, 0);
            }

            25% {
                transform: translate(-3%, 2%);
            }

            50% {
                transform: translate(2%, -3%);
            }

            75% {
                transform: translate(-1%, 3%);
            }
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            z-index: 2;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: radial-gradient(ellipse at center, white 20%, transparent 75%);
            pointer-events: none;
        }

        .blob-tl,
        .blob-br {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 2;
        }

        .blob-tl {
            width: 600px;
            height: 600px;
            top: -200px;
            left: -200px;
            background: radial-gradient(circle, rgba(109, 27, 35, 0.35) 0%, transparent 70%);
        }

        .blob-br {
            width: 700px;
            height: 700px;
            bottom: -250px;
            right: -200px;
            background: radial-gradient(circle, rgba(109, 27, 35, 0.25) 0%, transparent 65%);
        }

        /* ================================================================
   SCROLL HINT
================================================================ */
        .deco-line {
            position: absolute;
            left: 50%;
            bottom: 2.8rem;
            transform: translateX(-50%);
            z-index: 8;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            opacity: 0.4;
            animation: fadeIn 1s 2s both;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .deco-line:hover {
            opacity: 0.7;
        }

        .deco-line span {
            font-size: 0.6rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--cream);
            font-weight: 500;
        }

        .deco-line-bar {
            width: 1px;
            height: 40px;
            background: linear-gradient(to bottom, var(--cream), transparent);
            animation: scrollPulse 2s ease-in-out infinite;
        }

        @keyframes scrollPulse {

            0%,
            100% {
                opacity: 0.4;
                transform: scaleY(1);
            }

            50% {
                opacity: 0.8;
                transform: scaleY(1.1);
            }
        }

        /* ================================================================
   HERO CONTENT
================================================================ */
        .hero-content {
            position: relative;
            z-index: 5;
            text-align: center;
            padding: 1rem 1.5rem;
            max-width: 860px;
            width: 100%;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            border-radius: 100px;
            padding: 6px 18px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 2rem;
            animation: fadeDown 0.8s 0.1s var(--ease-out) both;
        }

        .hero-pill .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #e8908a;
            animation: pulseDot 2s ease infinite;
        }

        @keyframes pulseDot {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(232, 144, 138, 0.5);
            }

            50% {
                box-shadow: 0 0 0 5px rgba(232, 144, 138, 0);
            }
        }

        .hero-h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3.2rem, 8vw, 7rem);
            font-weight: 300;
            color: var(--cream);
            line-height: 1.05;
            letter-spacing: -0.5px;
            margin-bottom: 1.5rem;
            animation: fadeDown 0.8s 0.25s var(--ease-out) both;
        }

        .hero-h1 em {
            font-style: italic;
            color: rgba(232, 190, 180, 0.9);
            font-weight: 300;
        }

        .hero-h1 .line-accent {
            display: block;
            font-weight: 600;
            font-style: normal;
            color: white;
            text-shadow: 0 0 60px rgba(109, 27, 35, 0.8);
        }

        .hero-sub {
            font-size: clamp(0.85rem, 2vw, 1rem);
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.8;
            max-width: 440px;
            margin: 0 auto 2.8rem;
            font-weight: 300;
            animation: fadeDown 0.8s 0.4s var(--ease-out) both;
        }

        .hero-cta-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeDown 0.8s 0.55s var(--ease-out) both;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 2rem;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 100px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s var(--ease-out), box-shadow 0.3s;
            box-shadow: 0 4px 24px rgba(109, 27, 35, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), transparent 60%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(109, 27, 35, 0.65);
        }

        .btn-primary:hover::before {
            opacity: 1;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary .btn-icon {
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: background 0.2s;
        }

        .btn-primary:hover .btn-icon {
            background: rgba(255, 255, 255, 0.25);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.85rem 1.6rem;
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 100px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.87rem;
            font-weight: 500;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.25s;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        /* Stat chips */
        .stat-chips {
            position: absolute;
            z-index: 5;
            bottom: 6rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
            animation: fadeIn 0.8s 0.8s both;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 100px;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(8px);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .chip i {
            color: rgba(232, 144, 138, 0.8);
            font-size: 0.65rem;
        }

        /* Top-left brand */
        .brand-mark {
            position: absolute;
            top: 1.8rem;
            left: 2rem;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.6s 0.05s both;
        }

        .brand-mark-icon {
            width: 38px;
            height: 38px;
            background: rgba(109, 27, 35, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            backdrop-filter: blur(8px);
        }

        .brand-mark-text .name {
            display: block;
            font-weight: 700;
            font-size: 0.95rem;
            color: white;
            letter-spacing: 0.5px;
            line-height: 1;
        }

        .brand-mark-text .sub {
            display: block;
            font-size: 0.58rem;
            color: rgba(255, 255, 255, 0.4);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Top-right school link */
        .school-link {
            position: absolute;
            top: 1.8rem;
            right: 2rem;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 100px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-decoration: none;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
            transition: all 0.2s;
            animation: fadeIn 0.6s 0.1s both;
        }

        .school-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.25);
        }

        .school-link i {
            color: rgba(232, 144, 138, 0.7);
        }

        /* ================================================================
   FOOTER
================================================================ */
        .site-footer {
            background: #080102;
            border-top: 1px solid rgba(109, 27, 35, 0.35);
            position: relative;
            overflow: hidden;

            /* scroll-reveal — hidden by default, revealed by JS */
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.9s var(--ease-out), transform 0.9s var(--ease-out);
        }

        .site-footer.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Decorative top accent line */
        .footer-accent-line {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--maroon), var(--maroon-deep), transparent);
            opacity: 0.7;
        }

        /* Maroon glow blob behind footer */
        .footer-bg-blob {
            position: absolute;
            width: 600px;
            height: 300px;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            background: radial-gradient(ellipse, rgba(109, 27, 35, 0.18) 0%, transparent 70%);
            pointer-events: none;
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2.5rem 2.5rem;
            position: relative;
            z-index: 2;
        }

        /* Top section: brand + columns */
        .footer-top {
            display: grid;
            grid-template-columns: 1.8fr 1fr 1fr 1fr;
            gap: 3rem;
            padding-bottom: 3rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 2.5rem;
        }

        /* Brand column */
        .footer-brand-col .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.2rem;
        }

        .footer-logo-icon {
            width: 40px;
            height: 40px;
            background: rgba(109, 27, 35, 0.5);
            border: 1px solid rgba(109, 27, 35, 0.5);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
        }

        .footer-logo-text .name {
            display: block;
            font-weight: 700;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.85);
            letter-spacing: 0.5px;
            line-height: 1;
        }

        .footer-logo-text .tagline {
            display: block;
            font-size: 0.6rem;
            color: rgba(255, 255, 255, 0.3);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .footer-brand-desc {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.3);
            line-height: 1.8;
            max-width: 280px;
            margin-bottom: 1.5rem;
        }

        /* Social links */
        .footer-socials {
            display: flex;
            gap: 8px;
        }

        .social-btn {
            width: 32px;
            height: 32px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.35);
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .social-btn:hover {
            background: rgba(109, 27, 35, 0.4);
            border-color: rgba(109, 27, 35, 0.6);
            color: rgba(255, 255, 255, 0.8);
            transform: translateY(-2px);
        }

        /* Link columns */
        .footer-col-title {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.25);
            margin-bottom: 1.1rem;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .footer-links li a {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.35);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: color 0.2s, gap 0.2s;
        }

        .footer-links li a i {
            font-size: 0.62rem;
            color: rgba(109, 27, 35, 0.7);
            transition: color 0.2s;
        }

        .footer-links li a:hover {
            color: rgba(255, 255, 255, 0.7);
            gap: 10px;
        }

        .footer-links li a:hover i {
            color: #e8908a;
        }

        /* Status badge */
        .footer-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: rgba(0, 135, 90, 0.08);
            border: 1px solid rgba(0, 135, 90, 0.2);
            border-radius: 100px;
            font-size: 0.68rem;
            color: rgba(85, 201, 160, 0.7);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .status-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #55c9a0;
            animation: pulseDot 2s ease infinite;
        }

        .footer-info-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 0.7rem;
        }

        .footer-info-item i {
            color: rgba(109, 27, 35, 0.6);
            font-size: 0.75rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .footer-info-item span {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.3);
            line-height: 1.5;
        }

        .footer-info-item a {
            color: rgba(192, 112, 112, 0.7);
            text-decoration: none;
        }

        .footer-info-item a:hover {
            color: #e8908a;
            text-decoration: underline;
        }

        /* Bottom bar */
        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-copy {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.2);
        }

        .footer-copy strong {
            color: rgba(255, 255, 255, 0.35);
            font-weight: 600;
        }

        .footer-copy a {
            color: rgba(192, 112, 112, 0.5);
            text-decoration: none;
        }

        .footer-copy a:hover {
            color: #e8908a;
        }

        .footer-badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .footer-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 100px;
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.25);
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .footer-badge i {
            font-size: 0.6rem;
            color: rgba(109, 27, 35, 0.6);
        }

        /* Divider line inside footer */
        .footer-rule {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.04) 30%, rgba(255, 255, 255, 0.04) 70%, transparent);
            margin-bottom: 2rem;
        }

        /* ================================================================
   MODAL OVERLAY
================================================================ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.35s var(--ease-out);
        }

        .modal-overlay.open {
            pointer-events: all;
            opacity: 1;
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 3, 5, 0.6);
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.35s;
            cursor: pointer;
        }

        .modal-overlay.open .modal-backdrop {
            opacity: 1;
        }

        .modal-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 480px;
            background: var(--modal-bg);
            border-radius: var(--r-lg) var(--r-lg) 0 0;
            border: 1px solid var(--modal-bdr);
            border-bottom: none;
            transform: translateY(100%);
            transition: transform 0.5s var(--ease-out);
            overflow: hidden;
            max-height: 92vh;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.open .modal-card {
            transform: translateY(0);
        }

        .modal-overlay.minimized .modal-card {
            transform: translateY(calc(100% - 56px));
        }

        .modal-overlay.minimized .modal-backdrop {
            opacity: 0;
            pointer-events: none;
        }

        .modal-handle {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1.4rem 0.6rem;
            cursor: pointer;
            gap: 1rem;
            background: var(--modal-bg);
            border-bottom: 1px solid rgba(109, 27, 35, 0.2);
        }

        .modal-handle-bar {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-handle-pill {
            width: 36px;
            height: 4px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 2px;
        }

        .modal-handle-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-mid);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .modal-handle-actions {
            display: flex;
            gap: 6px;
        }

        .modal-action-btn {
            width: 28px;
            height: 28px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-mid);
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .modal-action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-hi);
        }

        .modal-minimized-hint {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.25);
            letter-spacing: 1px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .modal-overlay.minimized .modal-minimized-hint {
            opacity: 1;
        }

        .modal-overlay.minimized .modal-handle-label {
            opacity: 0;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.4rem 2rem 2rem;
            scrollbar-width: thin;
            scrollbar-color: var(--modal-bdr) transparent;
        }

        .modal-body::-webkit-scrollbar {
            width: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--modal-bdr);
            border-radius: 2px;
        }

        .auth-tabs {
            display: flex;
            background: var(--modal-sur);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: var(--r-sm);
            padding: 4px;
            margin-bottom: 1.8rem;
            gap: 4px;
        }

        .auth-tab-btn {
            flex: 1;
            padding: 9px;
            border: none;
            border-radius: 7px;
            background: none;
            color: var(--text-mid);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .auth-tab-btn.active {
            background: var(--maroon);
            color: white;
            box-shadow: 0 2px 12px rgba(109, 27, 35, 0.4);
        }

        .auth-tab-btn:not(.active):hover {
            color: var(--text-hi);
            background: rgba(255, 255, 255, 0.04);
        }

        .auth-pane {
            display: none;
        }

        .auth-pane.active {
            display: block;
            animation: paneFadeIn 0.3s var(--ease-out) both;
        }

        @keyframes paneFadeIn {
            from {
                opacity: 0;
                transform: translateY(6px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .pane-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.7rem;
            font-weight: 600;
            color: var(--text-hi);
            margin-bottom: 3px;
            line-height: 1.1;
        }

        .pane-subtitle {
            font-size: 0.78rem;
            color: var(--text-mid);
            margin-bottom: 1.4rem;
        }

        .form-group {
            margin-bottom: 0.85rem;
        }

        .form-group label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--text-dim);
            margin-bottom: 5px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 0.78rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-field {
            width: 100%;
            padding: 10px 12px 10px 34px;
            background: var(--modal-sur);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: var(--r-sm);
            color: var(--text-hi);
            font-size: 0.85rem;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            -webkit-text-fill-color: var(--text-hi);
        }

        .form-field::placeholder {
            color: var(--text-dim);
            font-size: 0.8rem;
        }

        .form-field:focus {
            border-color: rgba(109, 27, 35, 0.7);
            box-shadow: 0 0 0 3px rgba(109, 27, 35, 0.2);
        }

        .form-field:-webkit-autofill,
        .form-field:-webkit-autofill:hover,
        .form-field:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0px 1000px var(--modal-sur) inset;
            -webkit-text-fill-color: var(--text-hi);
            border-color: rgba(255, 255, 255, 0.07);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .auth-alert {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 0.7rem 1rem;
            border-radius: var(--r-sm);
            font-size: 0.78rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
            animation: paneFadeIn 0.3s var(--ease-out) both;
            line-height: 1.4;
        }

        .auth-alert.error {
            background: rgba(192, 57, 43, 0.12);
            color: #e8908a;
            border: 1px solid rgba(192, 57, 43, 0.25);
        }

        .auth-alert.success {
            background: rgba(0, 135, 90, 0.1);
            color: #55c9a0;
            border: 1px solid rgba(0, 135, 90, 0.25);
        }

        .auth-alert i {
            margin-top: 1px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .btn-auth {
            width: 100%;
            padding: 11px;
            margin-top: 0.6rem;
            background: linear-gradient(135deg, var(--maroon), var(--maroon-deep));
            color: white;
            border: none;
            border-radius: var(--r-sm);
            cursor: pointer;
            font-weight: 700;
            font-size: 0.875rem;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 16px rgba(109, 27, 35, 0.35);
        }

        .btn-auth:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(109, 27, 35, 0.5);
        }

        .btn-auth:active {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-footer-link {
            text-align: center;
            margin-top: 1.1rem;
        }

        .modal-footer-link span {
            font-size: 0.78rem;
            color: var(--text-dim);
        }

        .modal-footer-link button {
            background: none;
            border: none;
            color: #c07070;
            font-weight: 600;
            font-size: 0.78rem;
            cursor: pointer;
            margin-left: 4px;
            font-family: 'Outfit', sans-serif;
            text-decoration: underline;
            text-decoration-style: dotted;
        }

        .modal-footer-link button:hover {
            color: #e8908a;
        }

        .modal-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1.4rem 0 0;
        }

        .modal-divider hr {
            flex: 1;
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .modal-divider span {
            font-size: 0.65rem;
            color: var(--text-dim);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .modal-school-note {
            text-align: center;
            margin-top: 0.8rem;
            font-size: 0.7rem;
            color: var(--text-dim);
            line-height: 1.6;
        }

        .modal-school-note a {
            color: #c07070;
            text-decoration: none;
            font-weight: 600;
        }

        .modal-school-note a:hover {
            text-decoration: underline;
        }

        /* ================================================================
   ANIMATIONS
================================================================ */
        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* ================================================================
   RESPONSIVE
================================================================ */
        @media (min-width:1200px) {
            .modal-overlay {
                align-items: flex-end;
                justify-content: flex-end;
                padding-right: 3rem;
            }

            .modal-card {
                border-radius: var(--r-lg) var(--r-lg) 0 0;
                max-width: 430px;
            }
        }

        @media (max-width:900px) {
            .footer-top {
                grid-template-columns: 1fr 1fr;
            }

            .footer-brand-col {
                grid-column: span 2;
            }
        }

        @media (max-width:768px) {
            .hero-h1 {
                font-size: clamp(2.6rem, 10vw, 3.5rem);
            }

            .hero-sub {
                max-width: 90%;
            }

            .stat-chips {
                bottom: 5.5rem;
            }

            .brand-mark-text .sub {
                display: none;
            }

            .modal-body {
                padding: 1.2rem 1.4rem 2rem;
            }

            .carousel-arrow {
                display: none;
            }

            .footer-inner {
                padding: 3rem 1.5rem 2rem;
            }
        }

        @media (max-width:600px) {
            .footer-top {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }

            .footer-brand-col {
                grid-column: span 2;
            }

            .footer-bottom {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.2rem;
            }
        }

        @media (max-width:480px) {
            .hero-pill {
                font-size: 0.62rem;
                letter-spacing: 1.5px;
                padding: 5px 14px;
            }

            .hero-h1 {
                font-size: clamp(2.4rem, 12vw, 3rem);
                margin-bottom: 1rem;
            }

            .hero-sub {
                font-size: 0.82rem;
                margin-bottom: 2rem;
            }

            .hero-cta-group {
                gap: 0.7rem;
            }

            .btn-primary {
                padding: 0.75rem 1.5rem;
                font-size: 0.85rem;
            }

            .btn-ghost {
                padding: 0.75rem 1.3rem;
                font-size: 0.82rem;
            }

            .brand-mark {
                top: 1.2rem;
                left: 1.2rem;
            }

            .school-link {
                top: 1.2rem;
                right: 1.2rem;
                font-size: 0.62rem;
                padding: 6px 10px;
            }

            .stat-chips {
                bottom: 4.5rem;
                gap: 0.5rem;
                padding: 0 1rem;
            }

            .chip {
                font-size: 0.67rem;
                padding: 5px 10px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0.85rem;
            }

            .modal-body {
                padding: 1rem 1.2rem 2rem;
            }

            .footer-top {
                grid-template-columns: 1fr;
            }

            .footer-brand-col {
                grid-column: span 1;
            }

            .footer-inner {
                padding: 2.5rem 1.2rem 1.5rem;
            }
        }

        @media (max-height:500px) and (orientation:landscape) {
            .modal-card {
                max-height: 96vh;
                border-radius: var(--r-md) var(--r-md) 0 0;
            }

            .hero-h1 {
                font-size: clamp(1.8rem, 5vw, 2.5rem);
                margin-bottom: 0.8rem;
            }

            .hero-sub {
                margin-bottom: 1.5rem;
            }

            .stat-chips {
                display: none;
            }

            .deco-line {
                display: none;
            }

            .modal-body {
                padding: 0.8rem 1.5rem 1.5rem;
            }
        }

        @media (max-width:360px) {
            .hero-h1 {
                font-size: 2.2rem;
            }

            .btn-primary,
            .btn-ghost {
                width: 100%;
                justify-content: center;
            }

            .hero-cta-group {
                flex-direction: column;
                width: 100%;
                padding: 0 1.5rem;
            }
        }

        @supports (padding-bottom:env(safe-area-inset-bottom)) {
            .modal-card {
                padding-bottom: env(safe-area-inset-bottom);
            }

            .site-footer {
                padding-bottom: env(safe-area-inset-bottom);
            }
        }
    </style>
</head>

<body>

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
                <span class="name">EQUIPLEND</span>
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
                            <span class="name">EQUIPLEND</span>
                            <span class="tagline">Equipment Lending System</span>
                        </div>
                    </div>
                    <p class="footer-brand-desc">
                        A student-built platform designed for the responsible borrowing and tracking of school equipment at PUP Biñan Campus — free, secure, and always available.
                    </p>
                    <div class="footer-socials">
                        <a class="social-btn" href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a class="social-btn" href="#" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
                        <a class="social-btn" href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a class="social-btn" href="mailto:equiplend@student.pup.edu.ph" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
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
                        <span><a href="mailto:equiplend@student.pup.edu.ph">equiplend@student.pup.edu.ph</a></span>
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
                    &copy; 2026 <strong>EQUIPLEND</strong> — For Students, By Students.<br>
                    Part of <a href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">Polytechnic University of the Philippines — Biñan Campus</a>.
                </p>
                <div class="footer-badges">
                    <span class="footer-badge"><i class="fa-solid fa-shield-halved"></i> Secure Auth</span>
                    <span class="footer-badge"><i class="fa-solid fa-lock"></i> Encrypted</span>
                    <span class="footer-badge"><i class="fa-solid fa-mobile-screen-button"></i> Mobile Ready</span>
                </div>
            </div>

        </div><!-- /footer-inner -->
    </footer>


    <!-- ================================================================
     AUTH MODAL
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
                    <p class="pane-subtitle">Sign in to access the equipment lending portal.</p>
                    <?php if ($login_error): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($login_error) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="login-email" name="email"
                                    placeholder="your@student.edu.ph"
                                    value="<?= htmlspecialchars($login_email_val) ?>"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="login-pass">Password</label>
                            <div class="input-wrap">
                                <input class="form-field" type="password" id="login-pass" name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password" required>
                                <i class="fa-solid fa-lock"></i>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn-auth">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Sign In to Portal
                        </button>
                    </form>
                    <div class="modal-footer-link">
                        <span>Don't have an account?</span>
                        <button onclick="switchTab('register')">Register here</button>
                    </div>
                    <div class="modal-divider">
                        <hr><span>PUP Biñan · 2026</span>
                        <hr>
                    </div>
                    <p class="modal-school-note">
                        For students of <a href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">PUP Biñan Campus</a>.
                    </p>
                </div>

                <!-- REGISTER PANE -->
                <div class="auth-pane" id="pane-register" role="tabpanel">
                    <p class="pane-title">Create account</p>
                    <p class="pane-subtitle">Register to start borrowing school equipment.</p>
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
                                    placeholder="e.g. Juan Dela Cruz"
                                    value="<?= htmlspecialchars($reg_fullname_val) ?>"
                                    oninput="validateLettersName(this)"
                                    autocomplete="name" required>
                                <i class="fa-solid fa-user"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reg-sid">Student ID</label>
                            <div class="input-wrap">
                                <input class="form-field" type="text" id="reg-sid" name="student_id"
                                    minlength="15" maxlength="15"
                                    placeholder="2023-00251-BN-0"
                                    value="<?= htmlspecialchars($reg_studentid_val) ?>"
                                    oninput="validateLettersStudentID(this)"
                                    title="Format: YYYY-XXXXX-BN-X"
                                    autocomplete="off" required>
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reg-email">Student Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="reg-email" name="email"
                                    minlength="15" maxlength="254"
                                    placeholder="your@student.edu.ph"
                                    value="<?= htmlspecialchars($reg_email_val) ?>"
                                    oninput="validateLettersEmail(this)"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="margin-bottom:0">
                                <label for="reg-pass">Password</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="password" id="reg-pass" name="password"
                                        minlength="4" placeholder="Min. 4 chars"
                                        autocomplete="new-password" required>
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label for="reg-cpass">Confirm</label>
                                <div class="input-wrap">
                                    <input class="form-field" type="password" id="reg-cpass" name="confirm_password"
                                        minlength="4" placeholder="Repeat"
                                        autocomplete="new-password" required>
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="register" class="btn-auth" style="margin-top:1rem;">
                            <i class="fa-solid fa-user-plus"></i>
                            Create My Account
                        </button>
                    </form>
                    <div class="modal-footer-link">
                        <span>Already have an account?</span>
                        <button onclick="switchTab('login')">Sign in here</button>
                    </div>
                    <div class="modal-divider">
                        <hr><span>PUP Biñan · 2026</span>
                        <hr>
                    </div>
                    <p class="modal-school-note">
                        © 2026 EQUIPLEND — For Students, By Students.<br>
                        <a href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">Polytechnic University of the Philippines — Biñan</a>
                    </p>
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
            const IMG_BASE = 'images/';
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
            document.body.style.overflow = 'hidden';
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
    </script>

</body>

</html>