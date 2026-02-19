<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: landing-page.php");
    exit();
}
$fullname = $_SESSION['fullname'];
$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ── Auto-decline expired requests ──────────────────────────────────────────
$today = date('Y-m-d');
$reason_expired = "Request expired – borrow date has already passed";
$stmt_expired = $conn->prepare("UPDATE tbl_requests SET status='Declined', reason=? WHERE status='Waiting' AND borrow_date < ?");
$stmt_expired->bind_param("ss", $reason_expired, $today);
$stmt_expired->execute();

// ── Handle Borrow Request ──────────────────────────────────────────────────
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name'])) {
    if (!isset($_SESSION['user_id'])) die("Unauthorized access");

    $user_id = $_SESSION['user_id'];
    $user_query = mysqli_query($conn, "SELECT fullname, student_id FROM tbl_users WHERE student_id='" . mysqli_real_escape_string($conn, $user_id) . "'");
    $user = mysqli_fetch_assoc($user_query);
    if (!$user) die("User profile not found.");

    $student_name   = $user['fullname'];
    $student_id     = $user['student_id'];
    $borrow_date    = mysqli_real_escape_string($conn, $_POST['borrow_date']);
    $return_date    = mysqli_real_escape_string($conn, $_POST['return_date']);
    $equipment_name = mysqli_real_escape_string($conn, $_POST['equipment_name']);
    $room           = mysqli_real_escape_string($conn, $_POST['room']);
    $instructor     = preg_replace("/[^a-zA-Z\s.']/", "", $_POST['instructor']);
    $instructor     = mysqli_real_escape_string($conn, trim($instructor));
    $current_date   = date('Y-m-d');

    if ($borrow_date < $current_date) die("Error: You cannot select a borrow date in the past.");
    if ($return_date < $borrow_date)  die("Error: Return date cannot be before the borrow date.");

    $insert = "INSERT INTO tbl_requests (student_name,student_id,equipment_name,instructor,room,borrow_date,return_date,status,request_date)
               VALUES ('$student_name','$student_id','$equipment_name','$instructor','$room','$borrow_date','$return_date','Waiting',NOW())";
    if (mysqli_query($conn, $insert)) {
        header("Location: user-dashboard.php?success=1");
        exit();
    } else {
        die("Error processing request: " . mysqli_error($conn));
    }
}

// ── Inventory & Requests ───────────────────────────────────────────────────
$category_result  = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory ORDER BY item_name ASC");
$my_requests_result = null;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $my_requests_result = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid' ORDER BY request_date DESC");
}

// ── Avatar initials ────────────────────────────────────────────────────────
$name_parts = explode(' ', trim($fullname));
$firstname  = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

// ── Stats for Home tab ─────────────────────────────────────────────────────
$uid_safe = mysqli_real_escape_string($conn, $_SESSION['user_id']);
$stat_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe'"))['c'];
$stat_waiting  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Waiting'"))['c'];
$stat_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Approved'"))['c'];
$stat_declined = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Declined'"))['c'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EQUIPLEND | User Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* ── CSS Variables ─────────────────────────────────────────────── */
        :root {
            --primary-white: #ffffff;
            --secondary-cream: #f9f7f2;
            --accent-maroon: #6d1b23;
            --accent-dark: #4a0f15;
            --accent-light: #f3e5e6;
            --text-dark: #2c2c2c;
            --text-light: #666666;
            --khaki-border: #e6e0d4;
            --card-bg: #ffffff;
            --sidebar-bg: #f9f7f2;
            --input-bg: #ffffff;
            --dropdown-bg: #ffffff;
            --hover-bg: #f3e5e6;
            --radius: 16px;
            --shadow: 0 4px 20px rgba(109, 27, 35, 0.08);
            --success: #27ae60;
            --danger: #c0392b;
            --warning: #f39c12;
        }

        [data-theme="dark"] {
            --primary-white: #1e1e2e;
            --secondary-cream: #181825;
            --accent-maroon: #c1536b;
            --accent-dark: #8a2535;
            --accent-light: #2d1f23;
            --text-dark: #e0e0e0;
            --text-light: #a0a0b0;
            --khaki-border: #2e2e3e;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            --card-bg: #1e1e2e;
            --sidebar-bg: #13131f;
            --input-bg: #252535;
            --dropdown-bg: #252535;
            --hover-bg: #2d1f23;
        }

        [data-theme="high-contrast"] {
            --primary-white: #000000;
            --secondary-cream: #111111;
            --accent-maroon: #ff4455;
            --accent-dark: #cc0011;
            --accent-light: #1a0005;
            --text-dark: #ffffff;
            --text-light: #cccccc;
            --khaki-border: #444444;
            --shadow: 0 4px 20px rgba(255, 68, 85, 0.2);
            --card-bg: #111111;
            --sidebar-bg: #000000;
            --input-bg: #1a1a1a;
            --dropdown-bg: #1a1a1a;
            --hover-bg: #1a0005;
        }

        /* ── Reset ────────────────────────────────────────────────────── */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        /* Safe transitions — only on visual properties, never anything that
           could affect interactivity or layout flow. */
        .eq-item-card,
        .room-card,
        .qa-btn,
        .dd-item,
        .acc-nav-btn,
        .s-nav-item,
        .notif-tab,
        .lending-nav-btn,
        .nav-tab,
        .btn-borrow,
        .btn-submit-form,
        .btn-edit-acc,
        .btn-save-acc,
        .btn-cancel-acc,
        .btn-close-custom,
        .c-dot,
        .theme-opt,
        .info-card,
        .settings-card {
            transition: background-color 0.2s ease, color 0.2s ease,
                border-color 0.2s ease, opacity 0.2s ease,
                transform 0.15s ease, box-shadow 0.2s ease;
        }

        /* Theme variable transitions applied only to structural containers */
        body,
        header.app-header,
        main#app-main,
        .overlay-page,
        .account-sidebar,
        .settings-sidebar,
        .account-content,
        .settings-content {
            transition: background-color 0.3s ease, color 0.2s ease;
        }

        html,
        body {
            height: 100%;
        }

        body {
            background-color: var(--secondary-cream);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
        }

        /* ── Scrollbar ────────────────────────────────────────────────── */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--khaki-border);
            border-radius: 3px;
        }

        /* ── HEADER ───────────────────────────────────────────────────── */
        header.app-header {
            background-color: var(--primary-white);
            border-bottom: 1px solid var(--khaki-border);
            padding: 0 2.5rem;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .app-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
        }
        
        .app-logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        
        .app-logo-text strong {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--accent-maroon);
            letter-spacing: -0.5px;
        }

        .app-logo small {
            font-weight: 400;
            font-size: 0.7rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Image-based small logos/icons */
        .logo-icon {
            width: 34px;
            height: 34px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }

        .icon-img {
            width: 16px;
            height: 16px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }

        /* Larger icon used to emphasize very important headers */
        .important-icon {
            width: 24px;
            height: 24px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
        }

        /* ── TOP NAV TABS ─────────────────────────────────────────────── */
        .nav-tabs-wrap {
            display: flex;
            background: var(--secondary-cream);
            padding: 6px 10px;
            border-radius: 30px;
            border: 1px solid var(--khaki-border);
            gap: 4px;
        }

        .nav-tab {
            border: none;
            background: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-light);
            padding: 8px 22px;
            border-radius: 22px;
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.25s ease, color 0.2s ease, box-shadow 0.2s ease;
            white-space: nowrap;
        }

        .nav-tab.active,
        .nav-tab:hover {
            background-color: var(--accent-maroon);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(109, 27, 35, 0.25);
        }

        /* Nav disabled state — when any overlay is active */
        .nav-tabs-wrap.nav-disabled {
            opacity: 0.3;
            pointer-events: none;
            filter: blur(1.5px);
            user-select: none;
            transition: opacity 0.2s ease, filter 0.2s ease;
        }

        .nav-tabs-wrap {
            transition: opacity 0.2s ease, filter 0.2s ease;
        }

        /* ── HEADER RIGHT ─────────────────────────────────────────────── */
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .header-user-info {
            text-align: right;
            line-height: 1.3;
        }

        .header-user-info .u-name {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-dark);
        }

        .header-user-info .u-id {
            font-size: 0.72rem;
            color: var(--text-light);
            display: none;
        }

        .avatar-btn {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent-maroon), var(--accent-dark));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            border: 2px solid transparent;
            flex-shrink: 0;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .avatar-btn:hover {
            border-color: var(--accent-maroon);
            box-shadow: 0 0 0 4px var(--accent-light);
        }

        /* ── PROFILE DROPDOWN ─────────────────────────────────────────── */
        .profile-dropdown {
            position: absolute;
            top: calc(100% + 14px);
            right: 0;
            background: var(--dropdown-bg);
            border: 1px solid var(--khaki-border);
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            width: 265px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease;
            z-index: 500;
        }

        .profile-dropdown.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dd-header {
            padding: 1.2rem 1.4rem;
            background: linear-gradient(135deg, var(--accent-maroon), var(--accent-dark));
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dd-avatar {
            width: 46px;
            height: 46px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .dd-name {
            font-weight: 600;
            font-size: 0.92rem;
            display: block;
        }

        .dd-sub {
            font-size: 0.73rem;
            opacity: 0.8;
            display: block;
            margin-top: 1px;
        }

        .dd-menu {
            padding: 0.5rem;
        }

        .dd-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0.7rem 1rem;
            border-radius: 9px;
            cursor: pointer;
            color: var(--text-dark);
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: 'Poppins', sans-serif;
            transition: background 0.18s, color 0.18s;
        }

        .dd-item:hover {
            background: var(--hover-bg);
            color: var(--accent-maroon);
        }

        .dd-icon {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            background: var(--secondary-cream);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-maroon);
            font-size: 0.8rem;
            flex-shrink: 0;
            transition: background 0.18s, color 0.18s;
        }

        .dd-item:hover .dd-icon {
            background: var(--accent-maroon);
            color: white;
        }

        .dd-item.dd-logout {
            color: var(--danger);
        }

        .dd-item.dd-logout:hover {
            background: #ffeaea;
            color: var(--danger);
        }

        .dd-item.dd-logout .dd-icon {
            color: var(--danger);
            background: #ffeaea;
        }

        .dd-item.dd-logout:hover .dd-icon {
            background: var(--danger);
            color: white;
        }

        .dd-divider {
            height: 1px;
            background: var(--khaki-border);
            margin: 4px 0;
        }

        .notif-badge {
            margin-left: auto;
            background: var(--accent-maroon);
            color: white;
            font-size: 0.68rem;
            padding: 1px 7px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* ── MAIN CONTENT ─────────────────────────────────────────────── */
        main#app-main {
            flex: 1;
            overflow-y: auto;
            padding: 2rem 3rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* ── ALERT BANNER ─────────────────────────────────────────────── */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.9rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .alert-success {
            background: #e3fcef;
            color: #00875a;
            border: 1px solid #b7ebd3;
        }

        .alert-danger {
            background: #ffeaea;
            color: var(--danger);
            border: 1px solid #fcc;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }

        /* ── HOME TAB ─────────────────────────────────────────────────── */
        .section-header {
            margin-bottom: 2rem;
        }

        .section-header h2 {
            font-size: 1.8rem;
            color: var(--accent-maroon);
        }

        .section-header p {
            color: var(--text-light);
            margin-top: 4px;
        }

        .hero-card {
            background: linear-gradient(135deg, var(--accent-maroon) 0%, var(--accent-dark) 100%);
            color: white;
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .hero-card h1 {
            font-size: 1.9rem;
            margin-bottom: 0.4rem;
        }

        .hero-card p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--khaki-border);
            border-radius: var(--radius);
            padding: 1.4rem 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-light);
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-maroon);
        }

        .stat-card .stat-sub {
            font-size: 0.78rem;
            color: var(--text-light);
        }

        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--accent-light);
            color: var(--accent-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            margin-bottom: 0.6rem;
        }

        .home-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .home-grid {
                grid-template-columns: 1fr;
            }
        }

        .event-container {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--khaki-border);
        }

        .event-container h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .event-item {
            display: flex;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid var(--secondary-cream);
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .date-badge {
            background: var(--accent-light);
            color: var(--accent-maroon);
            padding: 8px 10px;
            border-radius: 10px;
            text-align: center;
            min-width: 54px;
            flex-shrink: 0;
        }

        .date-badge span {
            font-weight: 700;
            display: block;
            font-size: 1.1rem;
        }

        .date-badge small {
            font-size: 0.65rem;
            text-transform: uppercase;
        }

        .event-info h4 {
            font-size: 0.88rem;
            color: var(--text-dark);
            margin-bottom: 3px;
        }

        .event-info p {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .status-tag {
            font-size: 0.68rem;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 4px;
        }

        .tag-ongoing {
            background: #e3fcef;
            color: #00875a;
        }

        .tag-upcoming {
            background: #fff8e1;
            color: #c67c00;
        }

        .quick-actions {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--khaki-border);
        }

        .quick-actions h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .qa-btn {
            width: 100%;
            margin-bottom: 0.7rem;
            padding: 11px 16px;
            background: var(--secondary-cream);
            border: 1px solid var(--khaki-border);
            border-radius: 10px;
            cursor: pointer;
            color: var(--text-dark);
            font-size: 0.85rem;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            text-align: left;
        }

        .qa-btn:hover {
            background: var(--accent-light);
            color: var(--accent-maroon);
            border-color: var(--accent-maroon);
        }

        .qa-btn i {
            color: var(--accent-maroon);
            width: 16px;
            text-align: center;
        }

        /* ── LENDING TAB ──────────────────────────────────────────────── */
        .lending-sub {
            display: none;
        }

        .lending-sub.active {
            display: block;
        }

        .page-header {
            margin-bottom: 1.8rem;
        }

        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .page-header p {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        /* Equipment card container */
        .eq-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--khaki-border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .eq-card-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid var(--khaki-border);
        }

        .eq-card-header h2 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .eq-card-body {
            padding: 1.5rem;
        }

        .filter-row {
            display: flex;
            gap: 14px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-wrap {
            flex: 1;
            position: relative;
            min-width: 200px;
        }

        .search-wrap i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.85rem;
            pointer-events: none;
        }

        .search-wrap input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid var(--khaki-border);
            border-radius: 10px;
            background: var(--input-bg);
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
            font-size: 0.875rem;
            outline: none;
        }

        .search-wrap input:focus {
            border-color: var(--accent-maroon);
            box-shadow: 0 0 0 3px var(--accent-light);
        }

        .search-wrap .search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            pointer-events: none;
            opacity: 0.85;
        }

        .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--khaki-border);
            border-radius: 10px;
            background: var(--input-bg);
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
            font-size: 0.875rem;
            outline: none;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: var(--accent-maroon);
        }

        .eq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .eq-item-card {
            background: var(--card-bg);
            border-radius: calc(var(--radius) - 4px);
            border: 1px solid var(--khaki-border);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .eq-item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(109, 27, 35, 0.12);
            border-color: var(--accent-maroon);
        }

        .eq-item-img {
            width: 100%;
            height: 145px;
            object-fit: cover;
        }

        .eq-item-img-placeholder {
            width: 100%;
            height: 145px;
            background: var(--accent-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-maroon);
            font-size: 2rem;
        }

        .eq-item-body {
            padding: 1rem 1.1rem 1.1rem;
        }

        .eq-item-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .eq-item-meta {
            font-size: 0.78rem;
            color: var(--text-light);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 9px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .stock-avail {
            background: #e3fcef;
            color: #00875a;
        }

        .stock-unavail {
            background: #ffeaea;
            color: var(--danger);
        }

        .btn-borrow {
            width: 100%;
            margin-top: 10px;
            padding: 9px;
            background: var(--accent-maroon);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.82rem;
            font-family: 'Poppins', sans-serif;
            transition: opacity 0.2s, transform 0.15s;
        }

        .btn-borrow:hover:not(:disabled) {
            opacity: 0.88;
            transform: translateY(-1px);
        }

        .btn-borrow:disabled {
            background: var(--khaki-border);
            color: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }

        /* ── BORROW FORM ──────────────────────────────────────────────── */
        .form-card {
            max-width: 680px;
            margin: 0 auto;
        }

        .form-card-header {
            padding: 1.2rem 1.6rem;
            border-bottom: 1px solid var(--khaki-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-card-header h2 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn-close-custom {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            font-size: 1.1rem;
            width: 32px;
            height: 32px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }

        .btn-close-custom:hover {
            background: var(--accent-light);
            color: var(--accent-maroon);
        }

        .form-card-body {
            padding: 1.8rem;
        }

        .selected-item-banner {
            background: var(--accent-light);
            border: 1px solid var(--accent-maroon);
            border-radius: 10px;
            padding: 0.8rem 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--accent-maroon);
        }

        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .form-control-custom {
            width: 100%;
            padding: 10px 13px;
            border: 1px solid var(--khaki-border);
            border-radius: 9px;
            background: var(--input-bg);
            color: var(--text-dark);
            font-size: 0.875rem;
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control-custom:focus {
            border-color: var(--accent-maroon);
            box-shadow: 0 0 0 3px var(--accent-light);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .btn-submit-form {
            width: 100%;
            padding: 11px;
            margin-top: 1.2rem;
            background: linear-gradient(135deg, var(--accent-maroon), var(--accent-dark));
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            transition: opacity 0.2s, transform 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit-form:hover {
            opacity: 0.88;
            transform: translateY(-1px);
        }

        /* ── REQUESTS TABLE ───────────────────────────────────────────── */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table thead tr {
            background: var(--secondary-cream);
            border-bottom: 2px solid var(--khaki-border);
        }

        .requests-table thead th {
            padding: 0.8rem 1.1rem;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-light);
            text-align: left;
        }

        .requests-table tbody tr {
            border-bottom: 1px solid var(--khaki-border);
        }

        .requests-table tbody tr:last-child {
            border-bottom: none;
        }

        .requests-table tbody tr:hover {
            background: var(--secondary-cream);
        }

        .requests-table td {
            padding: 0.9rem 1.1rem;
            font-size: 0.85rem;
            color: var(--text-dark);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-waiting {
            background: #fff8e1;
            color: #c67c00;
        }

        .status-approved {
            background: #e3fcef;
            color: #00875a;
        }

        .status-declined {
            background: #ffeaea;
            color: var(--danger);
        }

        .table-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }

        .table-empty i {
            font-size: 2.5rem;
            color: var(--khaki-border);
            display: block;
            margin-bottom: 0.8rem;
        }

        /* Lending internal sub-nav */
        .lending-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .lending-nav-btn {
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid var(--khaki-border);
            background: var(--card-bg);
            color: var(--text-light);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .lending-nav-btn.active,
        .lending-nav-btn:hover {
            background: var(--accent-light);
            color: var(--accent-maroon);
            border-color: var(--accent-maroon);
        }

        /* ── ROOMS TAB ────────────────────────────────────────────────── */
        .room-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .room-card {
            background: var(--card-bg);
            display: flex;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--khaki-border);
            height: 190px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(109, 27, 35, 0.12);
        }

        .room-img {
            flex: 0 0 220px;
            background-color: var(--accent-light);
            background-image: radial-gradient(var(--khaki-border) 1px, transparent 1px);
            background-size: 20px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-maroon);
            font-size: 3rem;
        }

        .room-info {
            flex: 1;
            padding: 1.5rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .room-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .room-header p {
            font-size: 0.82rem;
            color: var(--text-light);
        }

        .capacity-badge {
            background: var(--secondary-cream);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            color: var(--accent-maroon);
            border: 1px solid var(--khaki-border);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .amenities {
            display: flex;
            gap: 14px;
            color: var(--text-light);
            font-size: 0.82rem;
            flex-wrap: wrap;
        }

        .amenities span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .room-avail-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .room-avail {
            color: #00875a;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .room-occupied {
            color: var(--danger);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .coming-soon-banner {
            background: linear-gradient(135deg, var(--accent-light), var(--secondary-cream));
            border: 1px dashed var(--accent-maroon);
            border-radius: var(--radius);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .coming-soon-banner i {
            font-size: 1.5rem;
            color: var(--accent-maroon);
            margin-bottom: 0.5rem;
            display: block;
        }

        .coming-soon-banner h3 {
            color: var(--accent-maroon);
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .coming-soon-banner p {
            color: var(--text-light);
            font-size: 0.85rem;
        }

        /* Room reservation form (pseudo) */
        .room-form-card {
            max-width: 680px;
            margin: 0 auto;
        }

        /* ── Overlay context strip (shows below header when overlay is open) */
        .overlay-context-strip {
            display: none;
            background: var(--accent-maroon);
            color: white;
            font-size: 0.78rem;
            font-weight: 500;
            padding: 6px 2.5rem;
            align-items: center;
            gap: 10px;
            position: sticky;
            top: 70px;
            z-index: 160;
            letter-spacing: 0.2px;
        }

        .overlay-context-strip.visible {
            display: flex;
        }

        .overlay-context-strip i {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        /* ── FULL-PAGE OVERLAYS ────────────────────────────────────────── */
        .overlay-page {
            display: none;
            position: fixed;
            /* Start at 70px (below header) so the sticky header remains visible.
               The header z-index is 200; overlays are z-index 150, so the header
               sits on top — this is what lets us blur the nav tabs. */
            inset: 70px 0 0 0;
            z-index: 150;
            background: var(--secondary-cream);
            overflow-y: auto;
            animation: fadeSlideIn 0.25s ease;
        }

        .overlay-page.active {
            display: flex;
        }

        @keyframes fadeSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── ACCOUNT OVERLAY ─────────────────────────────────────────── */
        .account-layout {
            display: flex;
            width: 100%;
            min-height: 100%;
        }

        .account-sidebar {
            width: 260px;
            flex-shrink: 0;
            background: var(--card-bg);
            border-right: 1px solid var(--khaki-border);
            padding: 1.8rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .account-sidebar-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: var(--text-light);
            padding: 0.6rem 0.5rem 0.2rem;
        }

        .acc-nav-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 9px;
            cursor: pointer;
            color: var(--text-light);
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: 'Poppins', sans-serif;
            transition: background 0.18s, color 0.18s;
        }

        .acc-nav-btn i {
            width: 16px;
            text-align: center;
        }

        .acc-nav-btn:hover {
            background: var(--hover-bg);
            color: var(--accent-maroon);
        }

        .acc-nav-btn.active {
            background: var(--accent-light);
            color: var(--accent-maroon);
            font-weight: 600;
        }

        .account-content {
            flex: 1;
            padding: 2.5rem 3.5rem;
            max-width: 860px;
        }

        .overlay-sub-panel {
            display: none;
        }

        .overlay-sub-panel.active {
            display: block;
        }

        .overlay-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 0.875rem;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            padding: 8px 0;
            margin-bottom: 0.5rem;
            transition: color 0.2s;
        }

        .overlay-back-btn:hover {
            color: var(--accent-maroon);
        }

        .account-hero-card {
            background: var(--card-bg);
            border: 1px solid var(--khaki-border);
            border-radius: var(--radius);
            padding: 1.8rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.6rem;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
        }

        .acc-avatar-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-maroon), var(--accent-dark));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.8rem;
            flex-shrink: 0;
            position: relative;
        }

        .cam-btn {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 24px;
            height: 24px;
            background: var(--accent-maroon);
            border: 2px solid var(--card-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.55rem;
            color: white;
        }

        .acc-hero-info h2 {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 3px;
        }

        .acc-hero-info p {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 7px;
        }

        .acc-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--accent-light);
            color: var(--accent-maroon);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.73rem;
            font-weight: 600;
        }

        .acc-action-wrap {
            margin-left: auto;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-edit-acc {
            padding: 9px 20px;
            background: var(--accent-maroon);
            color: white;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: opacity 0.2s;
        }

        .btn-edit-acc:hover {
            opacity: 0.85;
        }

        .btn-save-acc {
            padding: 9px 20px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: opacity 0.2s;
        }

        .btn-save-acc:hover {
            opacity: 0.85;
        }

        .btn-cancel-acc {
            padding: 9px 18px;
            background: none;
            border: 1px solid var(--khaki-border);
            border-radius: 9px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            transition: background 0.2s;
        }

        .btn-cancel-acc:hover {
            background: var(--secondary-cream);
        }

        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--khaki-border);
            border-radius: var(--radius);
            margin-bottom: 1.3rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .info-card-head {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid var(--khaki-border);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .info-card-head i {
            color: var(--accent-maroon);
            font-size: 0.9rem;
        }

        .info-card-head h3 {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.4rem;
            border-bottom: 1px solid var(--secondary-cream);
            gap: 1rem;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-lbl {
            width: 160px;
            flex-shrink: 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-light);
        }

        .info-val {
            flex: 1;
            font-size: 0.875rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .info-val.empty {
            color: var(--text-light);
            font-style: italic;
        }

        .info-input-f {
            flex: 1;
            padding: 7px 11px;
            border: 1px solid var(--khaki-border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-dark);
            font-size: 0.875rem;
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }

        .info-input-f:focus {
            border-color: var(--accent-maroon);
            box-shadow: 0 0 0 3px var(--accent-light);
        }

        .info-input-f:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--secondary-cream);
        }

        /* ── SETTINGS OVERLAY ────────────────────────────────────────── */
        .settings-layout {
            display: flex;
            width: 100%;
            min-height: 100%;
        }

        .settings-sidebar {
            width: 240px;
            flex-shrink: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--khaki-border);
            padding: 1.8rem 1rem;
        }

        .s-cat-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: var(--text-light);
            padding: 0.5rem 0.5rem 0.2rem;
            display: block;
        }

        .s-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 9px;
            cursor: pointer;
            color: var(--text-light);
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: 'Poppins', sans-serif;
            transition: background 0.18s, color 0.18s;
            margin-bottom: 2px;
        }

        .s-nav-item i {
            width: 16px;
            text-align: center;
        }

        .s-nav-item:hover {
            background: var(--hover-bg);
            color: var(--accent-maroon);
        }

        .s-nav-item.active {
            background: var(--accent-light);
            color: var(--accent-maroon);
            font-weight: 600;
        }

        .s-divider {
            height: 1px;
            background: var(--khaki-border);
            margin: 0.8rem 0;
        }

        .settings-content {
            flex: 1;
            padding: 2.5rem 3rem;
            overflow-y: auto;
        }

        .settings-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .settings-desc {
            color: var(--text-light);
            font-size: 0.875rem;
            margin-bottom: 1.6rem;
        }

        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--khaki-border);
            border-radius: var(--radius);
            margin-bottom: 1.3rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .settings-card-head {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid var(--khaki-border);
        }

        .settings-card-head h3 {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .settings-card-head p {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .s-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.4rem;
            border-bottom: 1px solid var(--secondary-cream);
            gap: 1rem;
        }

        .s-row:last-child {
            border-bottom: none;
        }

        .s-row-label h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .s-row-label p {
            font-size: 0.78rem;
            color: var(--text-light);
        }

        .toggle-sw {
            position: relative;
            display: inline-block;
            width: 42px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-sw input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-track {
            position: absolute;
            inset: 0;
            background: var(--khaki-border);
            border-radius: 24px;
            cursor: pointer;
            transition: background 0.25s;
        }

        .toggle-track::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: transform 0.25s;
        }

        .toggle-sw input:checked+.toggle-track {
            background: var(--accent-maroon);
        }

        .toggle-sw input:checked+.toggle-track::before {
            transform: translateX(18px);
        }

        .theme-grid {
            display: flex;
            gap: 12px;
            padding: 1.2rem 1.4rem;
            flex-wrap: wrap;
        }

        .theme-opt {
            cursor: pointer;
            border: 2px solid var(--khaki-border);
            border-radius: 10px;
            overflow: hidden;
            width: 100px;
            transition: border-color 0.2s;
        }

        .theme-opt.selected,
        .theme-opt:hover {
            border-color: var(--accent-maroon);
        }

        .theme-prev {
            height: 60px;
            display: flex;
            flex-direction: column;
            padding: 8px;
            gap: 5px;
        }

        .tp-light .theme-prev-bar {
            background: #e0e0e0;
            border-radius: 3px;
            height: 10px;
        }

        .tp-light {
            background: #ffffff;
        }

        .tp-dark {
            background: #1e1e2e;
        }

        .tp-dark .theme-prev-bar {
            background: #3a3a4e;
            border-radius: 3px;
            height: 10px;
        }

        .tp-hc {
            background: #000000;
        }

        .tp-hc .theme-prev-bar {
            background: #444;
            border-radius: 3px;
            height: 10px;
        }

        .theme-lbl {
            padding: 6px 10px;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-dark);
            background: var(--secondary-cream);
        }

        .color-dots {
            display: flex;
            gap: 12px;
            padding: 1.2rem 1.4rem;
            flex-wrap: wrap;
        }

        .c-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .c-dot:hover,
        .c-dot.selected {
            transform: scale(1.15);
            box-shadow: 0 0 0 3px white, 0 0 0 5px var(--accent-maroon);
        }

        .range-wrap {
            padding: 1.2rem 1.4rem;
        }

        .range-wrap h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: var(--text-light);
            margin-bottom: 6px;
        }

        input[type="range"] {
            width: 100%;
            accent-color: var(--accent-maroon);
            cursor: pointer;
        }

        .s-select {
            padding: 8px 12px;
            border: 1px solid var(--khaki-border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            outline: none;
        }

        .btn-danger-sm {
            padding: 8px 16px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .btn-danger-sm:hover {
            opacity: 0.85;
        }

        .danger-card {
            border-color: var(--danger) !important;
        }

        /* ── NOTIFICATIONS OVERLAY ───────────────────────────────────── */
        .notif-wrapper {
            max-width: 760px;
            width: 100%;
            margin: 0 auto;
            padding: 2.5rem 2rem;
        }

        .notif-filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .notif-tab {
            padding: 7px 16px;
            border-radius: 8px;
            border: 1px solid var(--khaki-border);
            background: var(--card-bg);
            color: var(--text-light);
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }

        .notif-tab.active {
            background: var(--accent-maroon);
            color: white;
            border-color: var(--accent-maroon);
        }

        .notif-group {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-light);
            padding: 0.5rem 0;
            margin: 0.5rem 0;
        }

        .notif-item {
            background: var(--card-bg);
            border: 1px solid var(--khaki-border);
            border-radius: var(--radius);
            padding: 1.1rem 1.2rem;
            display: flex;
            gap: 14px;
            margin-bottom: 10px;
            transition: background 0.2s;
        }

        .notif-item.unread {
            border-color: var(--accent-maroon);
            background: var(--accent-light);
        }

        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .ni-success {
            background: #e3fcef;
            color: #00875a;
        }

        .ni-alert {
            background: #fff8e1;
            color: #c67c00;
        }

        .ni-warn {
            background: #ffeaea;
            color: var(--danger);
        }

        .notif-body-wrap {
            flex: 1;
        }

        .notif-body-wrap h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 3px;
        }

        .notif-body-wrap p {
            font-size: 0.8rem;
            color: var(--text-light);
            line-height: 1.5;
        }

        .notif-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .notif-time {
            font-size: 0.72rem;
            color: var(--text-light);
            white-space: nowrap;
        }

        .unread-dot {
            width: 8px;
            height: 8px;
            background: var(--accent-maroon);
            border-radius: 50%;
        }

        .mark-read-btn {
            background: none;
            border: 1px solid var(--khaki-border);
            color: var(--text-light);
            padding: 5px 14px;
            border-radius: 7px;
            font-size: 0.76rem;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: color 0.2s, border-color 0.2s;
        }

        .mark-read-btn:hover {
            color: var(--accent-maroon);
            border-color: var(--accent-maroon);
        }

        /* ── LOADING OVERLAY ─────────────────────────────────────────── */
        #loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(249, 247, 242, 0.88);
            z-index: 9999;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 44px;
            height: 44px;
            border: 4px solid var(--accent-light);
            border-top-color: var(--accent-maroon);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── TOAST ───────────────────────────────────────────────────── */
        #app-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--accent-maroon);
            color: white;
            padding: 0.75rem 1.3rem;
            border-radius: 11px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
            z-index: 9998;
            opacity: 0;
            transform: translateY(16px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        #app-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* ── UTILITIES ───────────────────────────────────────────────── */
        .hidden {
            display: none !important;
        }

        .divider-line {
            height: 1px;
            background: var(--khaki-border);
            margin: 0.5rem 0 0.8rem;
        }
    </style>
</head>

<body>

    <!-- ================================================================
     HEADER
================================================================ -->
    <header class="app-header">
        <div class="header-left">
            <div class="app-logo">
                <img src="images/icon-boxes-stacked.svg" alt="EquipLend" class="logo-icon" style="color: var(--accent-maroon);" />
                <div class="app-logo-text">
                    <strong>EQUIPLEND</strong>
                    <small>User Portal</small>
                </div>
            </div>
        </div>

        <!-- Center: Top Navigation Tabs -->
        <nav class="nav-tabs-wrap" role="navigation" aria-label="Main Navigation">
            <button class="nav-tab active" id="tab-home" data-tab="home">
                Home
            </button>
            <button class="nav-tab" id="tab-lending" data-tab="lending">
                Lending
            </button>
            <button class="nav-tab" id="tab-rooms" data-tab="rooms">
                Rooms
            </button>
        </nav>

        <div class="header-right">
            <div class="header-user-info">
                <span class="u-name"><?php echo htmlspecialchars($fullname); ?></span>
                <span class="u-id">ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
            </div>

            <div class="avatar-btn" id="avatarBtn" title="Account menu"
                role="button" aria-haspopup="true" aria-expanded="false">
                <?php echo htmlspecialchars($initials); ?>
            </div>

            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profileDropdown" role="menu">
                <div class="dd-header">
                    <div class="dd-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div>
                        <span class="dd-name"><?php echo htmlspecialchars($fullname); ?></span>
                        <span class="dd-sub">ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                        <span class="dd-sub" style="margin-top:2px;">Student</span>
                    </div>
                </div>
                <div class="dd-menu">
                    <button class="dd-item" data-action="open-overlay" data-target="accountOverlay">
                        Account
                    </button>
                    <button class="dd-item" data-action="open-overlay" data-target="notifOverlay">
                        Notifications
                        <span class="notif-badge" id="notifBadge">3</span>
                    </button>
                    <button class="dd-item" data-action="open-overlay" data-target="settingsOverlay">
                        Settings
                    </button>
                    <div class="dd-divider"></div>
                    <button class="dd-item dd-logout" data-action="logout">
                        <div class="dd-icon"><img src="images/icon-logout.svg" alt="Logout" style="width:16px;height:16px;"/></div> Logout
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Context strip — visible when an overlay is active, shows current section -->
    <div id="overlayContextStrip" class="overlay-context-strip">
        <img src="images/icon-arrow-left.svg" class="icon-img" alt="Back" style="margin-right:6px;" />
        <span id="overlayContextLabel">Settings</span>
        <img src="images/icon-dot.svg" alt="dot" style="width:6px;height:6px;opacity:0.5;margin:0 8px;vertical-align:middle;" />
        <span style="opacity:0.7; font-size:0.72rem;">Nav tabs are paused while you're here</span>
    </div>

    <!-- ================================================================
     MAIN CONTENT
================================================================ -->
    <main id="app-main">

        <!-- Success Alert -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert-banner alert-success" id="success-alert">
                <img src="images/icon-circle-check.svg" class="icon-img" alt="Success" />
                <strong>Success!</strong> Your borrow request has been submitted for approval.
                <button class="alert-close" data-action="dismiss-alert" data-target="success-alert">
                    <img src="images/icon-xmark.svg" alt="Close" style="width:16px;height:16px;" />
                </button>
            </div>
        <?php endif; ?>

        <!-- Overdue Alert -->
        <div class="alert-banner alert-danger hidden" id="overdue-alert">
            <img src="images/icon-triangle-exclamation.svg" class="icon-img" alt="Alert" />
            <strong>Overdue Alert:</strong> You have overdue equipment — please return it immediately!
            <button class="alert-close" data-action="dismiss-alert" data-target="overdue-alert">
                <img src="images/icon-xmark.svg" alt="Close" style="width:16px;height:16px;" />
            </button>
        </div>

        <!-- ============================================================
         TAB: HOME
    ============================================================ -->
        <div class="tab-panel active" id="panel-home">
            <div class="section-header">
                <h2><img src="images/icon-home.svg" class="important-icon" alt="Home" />Welcome back, <?php echo htmlspecialchars($firstname); ?>! 👋</h2>
                <p><?php echo date('l, F j, Y'); ?> &mdash; Here's a summary of your activity.</p>
            </div>

            <!-- Hero Card -->
            <div class="hero-card">
                <h1>Equipment Lending Portal</h1>
                <p>Browse available equipment, submit borrow requests, and track your approvals — all in one place.</p>
            </div>

            <!-- Stats -->
            <p style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1.2px; color:var(--text-light); margin-bottom:0.8rem;">
                <img src="images/icon-chart.svg" class="important-icon" alt="Activity"/>Your Activity
            </p>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><img src="images/icon-layer.svg" alt="Total" style="width:20px;height:20px;"/></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value"><?php echo $stat_total; ?></div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff8e1; color:#c67c00;"><img src="images/icon-clock.svg" alt="Pending" style="width:20px;height:20px;"/></div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:var(--warning);"><?php echo $stat_waiting; ?></div>
                    <div class="stat-sub">Awaiting approval</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#e3fcef; color:#00875a;"><img src="images/icon-circle-check.svg" alt="Approved" style="width:20px;height:20px;"/></div>
                    <div class="stat-label">Approved</div>
                    <div class="stat-value" style="color:var(--success);"><?php echo $stat_approved; ?></div>
                    <div class="stat-sub">Ready to pick up</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ffeaea; color:var(--danger);"><img src="images/icon-xmark.svg" alt="Declined" style="width:20px;height:20px;"/></div>
                    <div class="stat-label">Declined</div>
                    <div class="stat-value" style="color:var(--danger);"><?php echo $stat_declined; ?></div>
                    <div class="stat-sub">Review reasons</div>
                </div>
            </div>

            <div class="home-grid">
                <!-- Events -->
                <div class="event-container">
                    <h3><img src="images/icon-calendar.svg" class="icon-img" style="width:20px;height:20px;color:var(--accent-maroon); margin-right:8px;" alt="Calendar"/>Upcoming Events</h3>
                    <div class="event-item">
                        <div class="date-badge"><span>17</span><small>Feb</small></div>
                        <div class="event-info">
                            <h4>Lab Equipment Audit</h4>
                            <p>Annual inventory check &mdash; Admin Office, 8 AM</p>
                            <span class="status-tag tag-ongoing">Ongoing</span>
                        </div>
                    </div>
                    <div class="event-item">
                        <div class="date-badge"><span>21</span><small>Feb</small></div>
                        <div class="event-info">
                            <h4>BSIT Capstone Defense</h4>
                            <p>Room 301 &mdash; All equipment must be returned by 7 AM</p>
                            <span class="status-tag tag-upcoming">Upcoming</span>
                        </div>
                    </div>
                    <div class="event-item">
                        <div class="date-badge"><span>28</span><small>Feb</small></div>
                        <div class="event-info">
                            <h4>System Maintenance</h4>
                            <p>Portal offline 11 PM – 1 AM for updates</p>
                            <span class="status-tag tag-upcoming">Upcoming</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3><img src="images/icon-bolt.svg" class="icon-img" style="width:20px;height:20px;color:var(--accent-maroon); margin-right:8px;" alt="Quick"/>Quick Actions</h3>
                    <button class="qa-btn" data-action="go-tab" data-tab="lending" data-lending="browse">
                        <img src="images/icon-search.svg" class="icon-img" alt="Search"/> Browse Equipment
                    </button>
                    <button class="qa-btn" data-action="go-tab" data-tab="lending" data-lending="requests">
                        <img src="images/icon-clipboard.svg" class="icon-img" alt="Requests"/> My Requests
                    </button>
                    <button class="qa-btn" data-action="go-tab" data-tab="rooms">
                        <img src="images/icon-rooms.svg" class="icon-img" alt="Rooms"/> Reserve a Room
                    </button>
                    <button class="qa-btn" data-action="open-overlay" data-target="notifOverlay">
                        <img src="images/icon-bell.svg" class="icon-img" alt="Notifications"/> Notifications <span class="notif-badge" style="font-size:0.7rem; padding: 1px 6px;">3</span>
                    </button>
                </div>
            </div>
        </div><!-- /panel-home -->


        <!-- ============================================================
         TAB: LENDING
    ============================================================ -->
        <div class="tab-panel" id="panel-lending">

            <!-- Lending Sub-Nav -->
            <div class="lending-nav">
                <button class="lending-nav-btn active" data-lending-nav="browse">
                    <img src="images/icon-search.svg" class="icon-img" alt="Browse"/> Browse Equipment
                </button>
                <button class="lending-nav-btn" data-lending-nav="requests">
                    <img src="images/icon-clipboard.svg" class="icon-img" alt="Requests"/> My Requests
                </button>
            </div>

            <!-- ── Sub: Browse Equipment ─────────────────────────── -->
            <div class="lending-sub active" id="lending-browse">
                <div class="page-header">
                    <h2><img src="images/icon-search.svg" class="important-icon" alt="Browse equipment" />Browse Equipment</h2>
                    <p>Search and request available school equipment for academic use.</p>
                </div>

                <div class="eq-card">
                    <div class="eq-card-body">
                        <div class="filter-row">
                            <div class="search-wrap">
                                <img src="images/icon-search.svg" class="search-icon" alt="Search" />
                                <input type="text" id="equipmentSearch" placeholder="Search by equipment name...">
                            </div>
                            <select id="categoryFilter" class="filter-select">
                                <option value="">All Categories</option>
                                <?php
                                mysqli_data_seek($category_result, 0);
                                while ($cat = mysqli_fetch_assoc($category_result)) {
                                    if (strtolower($cat['category']) === 'others') continue;
                                    echo '<option value="' . htmlspecialchars($cat['category']) . '">' . htmlspecialchars($cat['category']) . '</option>';
                                }
                                ?>
                                <option value="Others">Others</option>
                            </select>
                        </div>

                        <div class="eq-grid" id="equipmentList">
                            <?php if (mysqli_num_rows($inventory_result) == 0): ?>
                                <div style="grid-column:1/-1; text-align:center; padding:3rem; color:var(--text-light);">
                                    <img src="images/icon-box.svg" alt="No items" style="width:48px;height:48px;color:var(--khaki-border);display:block;margin-bottom:0.8rem;opacity:0.7;" />
                                    No equipment available at the moment.
                                </div>
                            <?php else: ?>
                                <?php while ($item = mysqli_fetch_assoc($inventory_result)): ?>
                                    <div class="eq-item-card item-node"
                                        data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>"
                                        data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>">

                                        <?php if (!empty($item['image_path'])): ?>
                                            <img class="eq-item-img"
                                                src="/Equipment-Lending-Website/<?php echo htmlspecialchars($item['image_path']); ?>"
                                                alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <?php else: ?>
                                            <div class="eq-item-img-placeholder">
                                                <img src="images/icon-box.svg" alt="Item" style="width:36px;height:36px;" />
                                            </div>
                                        <?php endif; ?>

                                        <div class="eq-item-body">
                                            <div class="eq-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="eq-item-meta">
                                                <img src="images/icon-tag.svg" alt="Category" style="width:14px;height:14px;" />
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <div style="margin-bottom:6px;">
                                                <?php if ($item['quantity'] > 0): ?>
                                                    <span class="stock-badge stock-avail">
                                                        <img src="images/icon-circle-check.svg" alt="Available" style="width:12px;height:12px;" />
                                                        <?php echo (int)$item['quantity']; ?> available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-badge stock-unavail">
                                                        <img src="images/icon-xmark.svg" alt="Unavailable" style="width:12px;height:12px;" />
                                                        Out of stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn-borrow"
                                                <?php if ($item['quantity'] <= 0) echo 'disabled'; ?>
                                                data-action="open-borrow-form"
                                                data-item="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                <img src="images/icon-hand.svg" alt="Borrow" style="width:14px;height:14px;" />
                                                <?php echo ($item['quantity'] > 0) ? 'Borrow' : 'Unavailable'; ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div><!-- /lending-browse -->

            <!-- ── Sub: Borrow Form ──────────────────────────────── -->
            <div class="lending-sub" id="lending-form">
                <div class="page-header">
                    <h2>Borrow Request</h2>
                    <p>Fill in the details below to submit your borrowing request.</p>
                </div>

                <div class="eq-card form-card">
                    <div class="form-card-header">
                        <h2>Borrowing Form</h2>
                        <button class="btn-close-custom" data-action="lending-back" title="Go back">
                            <img src="images/icon-xmark.svg" alt="Close" style="width:16px;height:16px;" />
                        </button>
                    </div>
                    <div class="form-card-body">
                        <div class="selected-item-banner" id="selectedItemBanner">
                            <img src="images/icon-box.svg" alt="Selected" style="width:18px;height:18px;" />
                            <span id="selectedItemLabel">No item selected</span>
                        </div>

                        <form id="borrowForm" method="POST" action="">
                            <input type="hidden" name="equipment_name" id="selectedItem">

                            <div class="form-group">
                                <label>Instructor</label>
                                <input type="text" name="instructor" id="instructorField"
                                    class="form-control-custom" placeholder="e.g. Sir. Migs" required>
                            </div>
                            <div class="form-group">
                                <label>Room / Laboratory</label>
                                <input type="text" name="room" class="form-control-custom"
                                    placeholder="e.g. Lab 301" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Borrow Date</label>
                                    <input type="date" name="borrow_date" id="borrow_date" class="form-control-custom" required>
                                </div>
                                <div class="form-group">
                                    <label>Return Date</label>
                                    <input type="date" name="return_date" id="return_date" class="form-control-custom" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-submit-form">
                                <img src="images/icon-paper-plane.svg" alt="Send" style="width:16px;height:16px;"/> Submit Borrow Request
                            </button>
                        </form>
                    </div>
                </div>
            </div><!-- /lending-form -->

            <!-- ── Sub: My Requests ──────────────────────────────── -->
            <div class="lending-sub" id="lending-requests">
                <div class="page-header">
                    <h2><img src="images/icon-clipboard.svg" class="important-icon" alt="Requests"/>My Borrow Requests</h2>
                    <p>Track the status of all your submitted borrow requests.</p>
                </div>

                <div class="eq-card">
                    <div class="eq-card-header">
                        <h2>Request History</h2>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th><img src="images/icon-box.svg" class="icon-img" alt="Equipment" />Equipment</th>
                                    <th><img src="images/icon-user.svg" class="icon-img" alt="Instructor" />Instructor</th>
                                    <th><img src="images/icon-rooms.svg" class="icon-img" alt="Room" />Room</th>
                                    <th><img src="images/icon-calendar.svg" class="icon-img" alt="Borrow date" />Borrow Date</th>
                                    <th><img src="images/icon-calendar.svg" class="icon-img" alt="Return date" />Return Date</th>
                                    <th><img src="images/icon-calendar.svg" class="icon-img" alt="Status" />Status</th>
                                    <th><img src="images/icon-comment.svg" class="icon-img" alt="Reason" />Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($my_requests_result && mysqli_num_rows($my_requests_result) > 0):
                                    while ($r = mysqli_fetch_assoc($my_requests_result)):
                                        $pill = 'status-waiting';
                                        $icon = 'fa-clock';
                                        if ($r['status'] === 'Approved') {
                                            $pill = 'status-approved';
                                            $icon = 'fa-circle-check';
                                        }
                                        if ($r['status'] === 'Declined') {
                                            $pill = 'status-declined';
                                            $icon = 'fa-circle-xmark';
                                        }
                                ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['equipment_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($r['instructor']); ?></td>
                                            <td><?php echo htmlspecialchars($r['room']); ?></td>
                                            <td><?php echo htmlspecialchars($r['borrow_date']); ?></td>
                                            <td><?php echo htmlspecialchars($r['return_date']); ?></td>
                                            <td>
                                                <span class="status-pill <?php echo $pill; ?>">
                                                    <?php
                                                        $icon_file = 'images/icon-clock.svg';
                                                        if ($icon === 'fa-circle-check') $icon_file = 'images/icon-circle-check.svg';
                                                        elseif ($icon === 'fa-circle-xmark') $icon_file = 'images/icon-xmark.svg';
                                                        elseif ($icon === 'fa-clock') $icon_file = 'images/icon-clock.svg';
                                                        echo '<img src="'.$icon_file.'" style="width:12px;height:12px;margin-right:6px;vertical-align:middle;" alt="status" />';
                                                    ?>
                                                    <?php echo htmlspecialchars($r['status']); ?>
                                                </span>
                                            </td>
                                            <td style="font-size:0.8rem; color:var(--text-light);">
                                                <?php echo ($r['status'] === 'Declined') ? htmlspecialchars($r['reason']) : '—'; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                else: ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="table-empty">
                                                <img src="images/icon-clipboard.svg" alt="Empty" style="width:36px;height:36px;display:block;margin:0 auto 8px;opacity:0.7;" />
                                                No borrow requests yet.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /lending-requests -->

        </div><!-- /panel-lending -->


        <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
        <div class="tab-panel" id="panel-rooms">
            <div class="section-header">
                <h2><img src="images/icon-rooms.svg" class="important-icon" alt="Rooms"/>Room Reservation</h2>
                <p>Browse available rooms and make a reservation for your class or event.</p>
            </div>

            <!-- Coming Soon Banner -->
            <div class="coming-soon-banner">
                <img src="images/icon-clock.svg" class="icon-img" alt="Coming soon" style="width:20px;height:20px;" />
                <h3>Room Reservation — Coming Soon</h3>
                <p>This feature is under development. You can preview available rooms below and fill a reservation form when it launches.</p>
            </div>

            <!-- Room Cards (Pseudo Design) -->
            <div class="room-list" id="roomList">
                <!-- Room 1 -->
                <div class="room-card">
                    <div class="room-img"><img src="images/icon-desktop.svg" alt="Lab" style="width:48px;height:48px;"/></div>
                    <div class="room-info">
                        <div>
                            <div class="room-header">
                                <div>
                                    <h3>Computer Laboratory 301</h3>
                                    <p>3rd Floor, Main Building</p>
                                </div>
                                <span class="capacity-badge"><img src="images/icon-users.svg" alt="Seats" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/>40 seats</span>
                            </div>
                            <div class="amenities" style="margin-top:10px;">
                                <span><img src="images/icon-wifi.svg" alt="WiFi" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> WiFi</span>
                                <span><img src="images/icon-ac.svg" alt="A/C" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> A/C</span>
                                <span><img src="images/icon-tv.svg" alt="Projector" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Projector</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <span class="room-avail"><img src="images/icon-dot.svg" alt="Available" style="width:6px;height:6px;margin-right:6px;vertical-align:middle;opacity:0.85;"/> Available</span>
                            <button class="btn-borrow" style="width:auto; padding:9px 24px;"
                                data-action="open-room-form" data-room="Computer Laboratory 301">
                                <img src="images/icon-calendar-plus.svg" alt="Reserve" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Reserve
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room 2 -->
                <div class="room-card">
                    <div class="room-img"><img src="images/icon-flask.svg" alt="Lab" style="width:48px;height:48px;"/></div>
                    <div class="room-info">
                        <div>
                            <div class="room-header">
                                <div>
                                    <h3>Science Laboratory</h3>
                                    <p>2nd Floor, Science Wing</p>
                                </div>
                                <span class="capacity-badge"><img src="images/icon-users.svg" alt="Seats" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/>30 seats</span>
                            </div>
                            <div class="amenities" style="margin-top:10px;">
                                <span><img src="images/icon-ac.svg" alt="A/C" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> A/C</span>
                                <span><img src="images/icon-faucet.svg" alt="Water" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Running Water</span>
                                <span><img src="images/icon-fire.svg" alt="Safety" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Safety Kit</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <span class="room-occupied"><img src="images/icon-dot.svg" alt="Occupied" style="width:6px;height:6px;margin-right:6px;vertical-align:middle;opacity:0.85;"/> Occupied until 3 PM</span>
                            <button class="btn-borrow" style="width:auto; padding:9px 24px;"
                                data-action="open-room-form" data-room="Science Laboratory">
                                <img src="images/icon-calendar-plus.svg" alt="Reserve" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Reserve
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room 3 -->
                <div class="room-card">
                    <div class="room-img"><img src="images/icon-chalkboard.svg" alt="Hall" style="width:48px;height:48px;"/></div>
                    <div class="room-info">
                        <div>
                            <div class="room-header">
                                <div>
                                    <h3>Lecture Hall A</h3>
                                    <p>Ground Floor, Academic Building</p>
                                </div>
                                <span class="capacity-badge"><img src="images/icon-users.svg" alt="Seats" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/>80 seats</span>
                            </div>
                            <div class="amenities" style="margin-top:10px;">
                                <span><img src="images/icon-wifi.svg" alt="WiFi" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> WiFi</span>
                                <span><img src="images/icon-ac.svg" alt="A/C" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> A/C</span>
                                <span><img src="images/icon-microphone.svg" alt="PA" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> PA System</span>
                                <span><img src="images/icon-tv.svg" alt="Projector" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Projector</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <span class="room-avail"><img src="images/icon-dot.svg" alt="Available" style="width:6px;height:6px;margin-right:6px;vertical-align:middle;opacity:0.85;"/> Available</span>
                            <button class="btn-borrow" style="width:auto; padding:9px 24px;"
                                data-action="open-room-form" data-room="Lecture Hall A">
                                <img src="images/icon-calendar-plus.svg" alt="Reserve" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Reserve
                            </button>
                        </div>
                    </div>
                </div>
            </div><!-- /roomList -->

            <!-- Room Reservation Form (hidden until Reserve clicked) -->
            <div id="room-form-section" class="hidden" style="margin-top:2rem;">
                <div class="eq-card room-form-card">
                    <div class="form-card-header">
                        <h2><img src="images/icon-rooms.svg" class="icon-img" style="color:var(--accent-maroon); margin-right:8px;" alt="Room Form"/>Room Reservation Form</h2>
                        <button class="btn-close-custom" data-action="close-room-form" title="Close">
                            <img src="images/icon-xmark.svg" alt="Close" style="width:16px;height:16px;" />
                        </button>
                    </div>
                    <div class="form-card-body">
                        <div class="selected-item-banner" id="selectedRoomBanner">
                            <img src="images/icon-rooms.svg" alt="Room" style="width:18px;height:18px;" />
                            <span id="selectedRoomLabel">No room selected</span>
                        </div>

                        <div class="coming-soon-banner" style="margin-bottom:1.5rem;">
                            <img src="images/icon-info.svg" class="icon-img" alt="Info" style="width:18px;height:18px;" />
                            <h3>Preview Mode</h3>
                            <p>Reservations are not yet processed. This form shows the planned layout.</p>
                        </div>

                        <div class="form-group">
                            <label>Purpose / Event Name</label>
                            <input type="text" class="form-control-custom" placeholder="e.g. BSIT Capstone Defense">
                        </div>
                        <div class="form-group">
                            <label>Instructor / Adviser</label>
                            <input type="text" class="form-control-custom" placeholder="e.g. Sir. Migs">
                        </div>
                        <div class="form-group">
                            <label>Number of Attendees</label>
                            <input type="number" class="form-control-custom" placeholder="e.g. 25" min="1">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Reservation Date</label>
                                <input type="date" class="form-control-custom">
                            </div>
                            <div class="form-group">
                                <label>Time Slot</label>
                                <select class="form-control-custom">
                                    <option value="">Select time slot</option>
                                    <option>7:00 AM – 9:00 AM</option>
                                    <option>9:00 AM – 11:00 AM</option>
                                    <option>11:00 AM – 1:00 PM</option>
                                    <option>1:00 PM – 3:00 PM</option>
                                    <option>3:00 PM – 5:00 PM</option>
                                    <option>Full Day (7 AM – 5 PM)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Additional Notes</label>
                            <textarea class="form-control-custom" rows="3" placeholder="Any special requirements or notes..."></textarea>
                        </div>
                        <button type="button" class="btn-submit-form" data-action="room-reserve-preview">
                            <img src="images/icon-paper-plane.svg" alt="Submit" style="width:16px;height:16px;margin-right:8px;"/> Submit Reservation (Preview)
                        </button>
                    </div>
                </div>
            </div>
        </div><!-- /panel-rooms -->

    </main>

    <!-- ================================================================
     OVERLAY: ACCOUNT PAGE
================================================================ -->
    <div class="overlay-page" id="accountOverlay">
        <div class="account-layout">
            <div class="account-sidebar">
                <button class="overlay-back-btn" data-action="close-overlay" data-target="accountOverlay">
                    <img src="images/icon-arrow-left.svg" class="icon-img" alt="Back" style="width:14px;height:14px;margin-right:8px;"/> Back to Dashboard
                </button>
                <div class="divider-line"></div>
                <span class="account-sidebar-label">My Account</span>
                <button class="acc-nav-btn active" data-acc-tab="acc-overview">
                    <img src="images/icon-id-card.svg" class="icon-img" alt="Overview"/> Overview
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-academic">
                    <img src="images/icon-graduation.svg" class="icon-img" alt="Academic"/> Academic Info
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-contact">
                    <img src="images/icon-address-book.svg" class="icon-img" alt="Contact"/> Contact Details
                </button>
                <span class="account-sidebar-label" style="margin-top:0.5rem;">Emergency</span>
                <button class="acc-nav-btn" data-acc-tab="acc-emergency">
                    <img src="images/icon-heart-pulse.svg" class="icon-img" alt="Emergency"/> Emergency Contact
                </button>
            </div>

            <div class="account-content">
                <!-- Overview -->
                <div id="acc-overview" class="overlay-sub-panel active">
                    <div class="account-hero-card">
                        <div class="acc-avatar-large">
                            <?php echo htmlspecialchars($initials); ?>
                            <div class="cam-btn" title="Change photo"><img src="images/icon-camera.svg" alt="Camera" style="width:14px;height:14px;"/></div>
                        </div>
                        <div class="acc-hero-info">
                            <h2><?php echo htmlspecialchars($fullname); ?></h2>
                            <p>ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
                            <span class="acc-badge">
                                <img src="images/icon-dot-green.svg" alt="Active" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/>
                                Active Student
                            </span>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">
                                Edit Profile
                            </button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;" data-action="profile-save">
                                <img src="images/icon-check.svg" alt="Save" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"/> Save
                            </button>
                            <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;" data-action="profile-cancel">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Personal Information</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val" data-field="fullname"><?php echo htmlspecialchars($fullname); ?></span>
                            <input class="info-input-f" data-input="fullname" value="<?php echo htmlspecialchars($fullname); ?>" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Student ID</span>
                            <span class="info-val"><?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Date of Birth</span>
                            <span class="info-val empty" data-field="dob">— Not provided</span>
                            <input class="info-input-f" type="date" data-input="dob" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Gender</span>
                            <span class="info-val empty" data-field="gender">— Not provided</span>
                            <select class="info-input-f" data-input="gender" disabled style="display:none;">
                                <option value="">Select...</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Prefer not to say</option>
                            </select>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Nationality</span>
                            <span class="info-val empty" data-field="nationality">— Not provided</span>
                            <input class="info-input-f" data-input="nationality" placeholder="e.g. Filipino" disabled style="display:none;">
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Login & Security</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Email</span>
                            <span class="info-val empty" data-field="email">— Not provided</span>
                            <input class="info-input-f" data-input="email" type="email" placeholder="your@school.edu.ph" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Password</span>
                            <span class="info-val">••••••••••</span>
                            <button class="btn-borrow" style="width:auto; padding:6px 16px; font-size:0.75rem; margin-left:auto;"
                                data-action="toast" data-msg="Change password feature coming soon!">
                                Change
                            </button>
                        </div>
                    </div>
                </div><!-- /acc-overview -->

                <!-- Academic -->
                <div id="acc-academic" class="overlay-sub-panel">
                    <h2 style="font-size:1.4rem; font-weight:700; color:var(--text-dark); margin-bottom:3px;">Academic Information</h2>
                    <p style="color:var(--text-light); font-size:0.875rem; margin-bottom:1.6rem;">Your enrollment and program details.</p>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Enrollment</h3>
                        </div>
                        <div class="info-row"><span class="info-lbl">Student ID</span><span class="info-val"><?php echo htmlspecialchars($_SESSION['user_id']); ?></span></div>
                        <div class="info-row"><span class="info-lbl">Full Name</span><span class="info-val"><?php echo htmlspecialchars($fullname); ?></span></div>
                        <div class="info-row"><span class="info-lbl">Program</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Year Level</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Section</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Status</span><span class="info-val"><span class="stock-badge stock-avail">Active / Regular</span></span></div>
                    </div>
                </div>

                <!-- Contact -->
                <div id="acc-contact" class="overlay-sub-panel">
                    <h2 style="font-size:1.4rem; font-weight:700; color:var(--text-dark); margin-bottom:3px;">Contact Details</h2>
                    <p style="color:var(--text-light); font-size:0.875rem; margin-bottom:1.6rem;">How we can reach you.</p>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Address</h3>
                        </div>
                        <div class="info-row"><span class="info-lbl">Present Address</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Permanent Address</span><span class="info-val empty">— Not provided</span></div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Phone</h3>
                        </div>
                        <div class="info-row"><span class="info-lbl">Mobile Number</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Landline</span><span class="info-val empty">— Not provided</span></div>
                    </div>
                </div>

                <!-- Emergency -->
                <div id="acc-emergency" class="overlay-sub-panel">
                    <h2 style="font-size:1.4rem; font-weight:700; color:var(--text-dark); margin-bottom:3px;">Emergency Contact</h2>
                    <p style="color:var(--text-light); font-size:0.875rem; margin-bottom:1.6rem;">Person to contact in an emergency.</p>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Primary Contact</h3>
                        </div>
                        <div class="info-row"><span class="info-lbl">Name</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Relationship</span><span class="info-val empty">— Not provided</span></div>
                        <div class="info-row"><span class="info-lbl">Mobile Number</span><span class="info-val empty">— Not provided</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /accountOverlay -->

    <!-- ================================================================
     OVERLAY: SETTINGS PAGE
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">
        <div class="settings-layout">
            <div class="settings-sidebar">
                <button class="overlay-back-btn" data-action="close-overlay" data-target="settingsOverlay">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <div class="divider-line"></div>
                <span class="s-cat-label">Appearance</span>
                <button class="s-nav-item active" data-sett-tab="st-appearance"><i class="fa-solid fa-palette"></i> Appearance</button>
                <button class="s-nav-item" data-sett-tab="st-accessibility"><i class="fa-solid fa-universal-access"></i> Accessibility</button>
                <span class="s-cat-label">Account</span>
                <button class="s-nav-item" data-sett-tab="st-privacy"><i class="fa-solid fa-shield-halved"></i> Privacy & Security</button>
                <button class="s-nav-item" data-sett-tab="st-notif-prefs"><i class="fa-solid fa-bell"></i> Notifications</button>
                <button class="s-nav-item" data-sett-tab="st-language"><i class="fa-solid fa-language"></i> Language & Region</button>
                <div class="s-divider"></div>
                <span class="s-cat-label">System</span>
                <button class="s-nav-item" data-sett-tab="st-advanced"><i class="fa-solid fa-sliders"></i> Advanced</button>
            </div>

            <div class="settings-content">

                <!-- Appearance -->
                <div id="st-appearance" class="overlay-sub-panel active">
                    <h2 class="settings-title">Appearance</h2>
                    <p class="settings-desc">Customize how the portal looks and feels.</p>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Theme</h3>
                            <p>Choose between light, dark, or high-contrast mode.</p>
                        </div>
                        <div class="theme-grid">
                            <div class="theme-opt selected" id="tp-light" data-action="apply-theme" data-theme="light">
                                <div class="theme-prev tp-light">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">Light <i class="fa-solid fa-check" id="tc-light" style="color:var(--accent-maroon);font-size:0.7rem;"></i></div>
                            </div>
                            <div class="theme-opt" id="tp-dark" data-action="apply-theme" data-theme="dark">
                                <div class="theme-prev tp-dark">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">Dark <i class="fa-solid fa-check" id="tc-dark" style="color:var(--accent-maroon);font-size:0.7rem;display:none;"></i></div>
                            </div>
                            <div class="theme-opt" id="tp-hc" data-action="apply-theme" data-theme="high-contrast">
                                <div class="theme-prev tp-hc">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">High Contrast <i class="fa-solid fa-check" id="tc-hc" style="color:var(--accent-maroon);font-size:0.7rem;display:none;"></i></div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Accent Color</h3>
                            <p>Pick a highlight color for buttons and active elements.</p>
                        </div>
                        <div class="color-dots">
                            <div class="c-dot selected" style="background:#6d1b23;" data-action="apply-accent" data-color="#6d1b23" data-light="#f3e5e6" title="Maroon (Default)"></div>
                            <div class="c-dot" style="background:#1a5276;" data-action="apply-accent" data-color="#1a5276" data-light="#d6eaf8" title="Navy Blue"></div>
                            <div class="c-dot" style="background:#1e8449;" data-action="apply-accent" data-color="#1e8449" data-light="#d5f5e3" title="Forest Green"></div>
                            <div class="c-dot" style="background:#7d3c98;" data-action="apply-accent" data-color="#7d3c98" data-light="#f0e6fa" title="Purple"></div>
                            <div class="c-dot" style="background:#d35400;" data-action="apply-accent" data-color="#d35400" data-light="#fde8d8" title="Burnt Orange"></div>
                            <div class="c-dot" style="background:#2e86c1;" data-action="apply-accent" data-color="#2e86c1" data-light="#d6eaf8" title="Sky Blue"></div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Compact Mode</h3>
                            <p>Reduce spacing for a denser layout.</p>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Enable Compact Mode</h4>
                                <p>Makes cards and list items smaller.</p>
                            </div>
                            <label class="toggle-sw">
                                <input type="checkbox" id="compactToggle" data-action="apply-compact">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Accessibility -->
                <div id="st-accessibility" class="overlay-sub-panel">
                    <h2 class="settings-title">Accessibility</h2>
                    <p class="settings-desc">Make the portal easier to use.</p>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Text Size</h3>
                        </div>
                        <div class="range-wrap">
                            <h4>Font Size <span id="fontSizeLbl" style="color:var(--accent-maroon);">100%</span></h4>
                            <div class="range-labels"><span>Small</span><span>Default</span><span>Large</span></div>
                            <input type="range" min="80" max="130" value="100" step="5" id="fontSizeRange" data-action="apply-fontsize">
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Motion & Animations</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Reduce Motion</h4>
                                <p>Disables fade-in and slide animations.</p>
                            </div>
                            <label class="toggle-sw">
                                <input type="checkbox" id="reduceMotionToggle" data-action="apply-reduce-motion">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Focus Indicators</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Enhanced Focus Ring</h4>
                                <p>Makes keyboard focus outlines more visible.</p>
                            </div>
                            <label class="toggle-sw">
                                <input type="checkbox" id="focusRingToggle" data-action="apply-focus-ring">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Privacy -->
                <div id="st-privacy" class="overlay-sub-panel">
                    <h2 class="settings-title">Privacy & Security</h2>
                    <p class="settings-desc">Control your data and account security.</p>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Login Sessions</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Remember Me</h4>
                                <p>Stay logged in for 30 days.</p>
                            </div>
                            <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Two-Factor Authentication</h4>
                                <p>Add an extra layer of security.</p>
                            </div>
                            <button class="btn-borrow" style="width:auto;padding:7px 16px;font-size:0.78rem;"
                                data-action="toast" data-msg="2FA setup coming soon!">Enable 2FA</button>
                        </div>
                    </div>
                    <div class="settings-card danger-card">
                        <div class="settings-card-head">
                            <h3 style="color:var(--danger);">Danger Zone</h3>
                            <p>Irreversible actions.</p>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Clear All Activity Data</h4>
                                <p>Permanently wipes your history.</p>
                            </div>
                            <button class="btn-danger-sm" data-action="toast" data-msg="This action is admin-restricted.">Clear Data</button>
                        </div>
                    </div>
                </div>

                <!-- Notification Prefs -->
                <div id="st-notif-prefs" class="overlay-sub-panel">
                    <h2 class="settings-title">Notification Preferences</h2>
                    <p class="settings-desc">Control which notifications you receive.</p>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Borrow & Return Alerts</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Borrow Approved</h4>
                                <p>Notify when my request is approved.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Due Date Reminder</h4>
                                <p>Remind me 1 day before equipment is due.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Overdue Warning</h4>
                                <p>Alert when I have an overdue item.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                    </div>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>General</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>School Announcements</h4>
                                <p>Upcoming events.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>System Updates</h4>
                                <p>Maintenance alerts.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                    </div>
                </div>

                <!-- Language -->
                <div id="st-language" class="overlay-sub-panel">
                    <h2 class="settings-title">Language & Region</h2>
                    <p class="settings-desc">Set your preferred language and date/time format.</p>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Language</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Display Language</h4>
                            </div>
                            <select class="s-select">
                                <option selected>English (Philippines)</option>
                                <option>Filipino</option>
                                <option>English (US)</option>
                            </select>
                        </div>
                    </div>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Date & Time</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Time Zone</h4>
                            </div><select class="s-select">
                                <option selected>Asia/Manila (UTC+8)</option>
                                <option>UTC</option>
                            </select>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Date Format</h4>
                            </div><select class="s-select">
                                <option>MM/DD/YYYY</option>
                                <option selected>DD/MM/YYYY</option>
                                <option>YYYY-MM-DD</option>
                            </select>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Time Format</h4>
                            </div><select class="s-select">
                                <option selected>12-hour (AM/PM)</option>
                                <option>24-hour</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Advanced -->
                <div id="st-advanced" class="overlay-sub-panel">
                    <h2 class="settings-title">Advanced</h2>
                    <p class="settings-desc">Power user settings. Be careful.</p>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Display</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Show Asset IDs</h4>
                                <p>Display equipment asset IDs.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Verbose Error Messages</h4>
                                <p>Show detailed error info.</p>
                            </div><label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
                        </div>
                    </div>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Reset</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Reset All Settings</h4>
                                <p>Restore defaults.</p>
                            </div>
                            <button class="btn-danger-sm" data-action="reset-settings">Reset</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div><!-- /settingsOverlay -->

    <!-- ================================================================
     OVERLAY: NOTIFICATIONS
================================================================ -->
    <div class="overlay-page" id="notifOverlay" style="flex-direction:column; overflow-y:auto;">
        <div class="notif-wrapper">
            <button class="overlay-back-btn" data-action="close-overlay" data-target="notifOverlay">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </button>
            <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.2rem; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="font-size:1.45rem; font-weight:700; color:var(--text-dark); margin-bottom:3px;">Notifications</h2>
                    <p style="color:var(--text-light); font-size:0.875rem;">You have <strong style="color:var(--accent-maroon);" id="unreadCount">3 unread</strong> notifications.</p>
                </div>
                <button class="mark-read-btn" data-action="mark-all-read">Mark all as read</button>
            </div>

            <div class="notif-filter-tabs">
                <button class="notif-tab active" data-notif-filter="all">All</button>
                <button class="notif-tab" data-notif-filter="unread">Unread</button>
                <button class="notif-tab" data-notif-filter="borrow">Borrow</button>
                <button class="notif-tab" data-notif-filter="system">System</button>
            </div>

            <div class="notif-group">Today</div>
            <div class="notif-item unread" data-cat="borrow">
                <div class="notif-icon ni-success"><i class="fa-solid fa-check"></i></div>
                <div class="notif-body-wrap">
                    <h4>Borrow Request Approved</h4>
                    <p>Your latest borrow request has been approved. Please pick up the item at the Admin Office before 5:00 PM.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">9:42 AM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-item unread" data-cat="system">
                <div class="notif-icon ni-alert"><i class="fa-solid fa-gear"></i></div>
                <div class="notif-body-wrap">
                    <h4>System Maintenance Tonight</h4>
                    <p>EQUIPLEND will undergo scheduled maintenance from 11:00 PM to 1:00 AM.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">8:00 AM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>

            <div class="notif-group">Yesterday</div>
            <div class="notif-item unread" data-cat="borrow">
                <div class="notif-icon ni-warn"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="notif-body-wrap">
                    <h4>Return Reminder</h4>
                    <p>You have a borrowed item due in 1 day. Please return it on time to avoid penalties.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">Yesterday, 4:15 PM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-item" data-cat="borrow">
                <div class="notif-icon ni-success"><i class="fa-solid fa-box"></i></div>
                <div class="notif-body-wrap">
                    <h4>Request Submitted</h4>
                    <p>Your borrow request for Lab Equipment was successfully submitted and is under review.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">Yesterday, 2:00 PM</span></div>
            </div>
        </div>
    </div><!-- /notifOverlay -->

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top:1rem; font-weight:600; color:var(--text-dark); font-size:0.9rem;">Processing your request...</p>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>


    <!-- ================================================================
     JAVASCRIPT — Single event-delegation model. No inline onclick.
     All overlays toggle `.nav-disabled` on the nav bar.
     applyReduceMotion only touches animation, never transition —
     touching transition: none on * was the root cause of the freeze.
================================================================ -->
    <script>
        (function() {
            'use strict';

            const todayStr = new Date().toISOString().split('T')[0];

            /* ── Toast ─────────────────────────────────────────────────────────── */
            let toastTimer;

            function showToast(msg) {
                const t = document.getElementById('app-toast');
                if (!t) return;
                t.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + msg;
                t.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
            }

            /* ── Nav tab enable / disable ──────────────────────────────────────── */
            function disableNavTabs() {
                const nav = document.querySelector('.nav-tabs-wrap');
                if (nav) nav.classList.add('nav-disabled');
            }

            function enableNavTabs() {
                // Only re-enable if NO overlay is still open
                if (!document.querySelector('.overlay-page.active')) {
                    const nav = document.querySelector('.nav-tabs-wrap');
                    if (nav) nav.classList.remove('nav-disabled');
                }
            }

            /* ── Profile Dropdown ──────────────────────────────────────────────── */
            function openDropdown() {
                document.getElementById('profileDropdown').classList.add('open');
                document.getElementById('avatarBtn').setAttribute('aria-expanded', 'true');
            }

            function closeDropdown() {
                document.getElementById('profileDropdown').classList.remove('open');
                document.getElementById('avatarBtn').setAttribute('aria-expanded', 'false');
            }

            function toggleDropdown() {
                document.getElementById('profileDropdown').classList.contains('open') ? closeDropdown() : openDropdown();
            }

            /* ── Overlays ──────────────────────────────────────────────────────── */
            const overlayLabels = {
                accountOverlay: 'My Account',
                settingsOverlay: 'Settings',
                notifOverlay: 'Notifications'
            };

            function openOverlay(id) {
                closeDropdown();
                const el = document.getElementById(id);
                if (el) {
                    el.classList.add('active');
                    disableNavTabs();
                    const strip = document.getElementById('overlayContextStrip');
                    const lbl = document.getElementById('overlayContextLabel');
                    if (strip && lbl) {
                        lbl.textContent = overlayLabels[id] || 'Sub-page';
                        strip.classList.add('visible');
                    }
                }
            }

            function closeOverlay(id) {
                const el = document.getElementById(id);
                if (el) el.classList.remove('active');
                enableNavTabs();
                if (!document.querySelector('.overlay-page.active')) {
                    const strip = document.getElementById('overlayContextStrip');
                    if (strip) strip.classList.remove('visible');
                }
            }

            /* ── Main Tab Switcher ─────────────────────────────────────────────── */
            function switchTab(tabName) {
                document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
                const btn = document.querySelector('.nav-tab[data-tab="' + tabName + '"]');
                if (btn) btn.classList.add('active');
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                const panel = document.getElementById('panel-' + tabName);
                if (panel) panel.classList.add('active');
            }

            /* ── Lending Sub-Sections ──────────────────────────────────────────── */
            function switchLendingSub(subName) {
                document.querySelectorAll('.lending-nav-btn').forEach(b => b.classList.remove('active'));
                const btn = document.querySelector('.lending-nav-btn[data-lending-nav="' + subName + '"]');
                if (btn) btn.classList.add('active');
                document.querySelectorAll('.lending-sub').forEach(s => s.classList.remove('active'));
                const sub = document.getElementById('lending-' + subName);
                if (sub) sub.classList.add('active');
            }

            /* ── Account Sub-Tabs ──────────────────────────────────────────────── */
            function switchAccTab(panelId) {
                document.querySelectorAll('.acc-nav-btn').forEach(b => b.classList.remove('active'));
                const btn = document.querySelector('.acc-nav-btn[data-acc-tab="' + panelId + '"]');
                if (btn) btn.classList.add('active');
                document.querySelectorAll('#accountOverlay .overlay-sub-panel').forEach(p => p.classList.remove('active'));
                const panel = document.getElementById(panelId);
                if (panel) panel.classList.add('active');
            }

            /* ── Settings Sub-Tabs ─────────────────────────────────────────────── */
            function switchSettTab(panelId) {
                document.querySelectorAll('.s-nav-item').forEach(b => b.classList.remove('active'));
                const btn = document.querySelector('.s-nav-item[data-sett-tab="' + panelId + '"]');
                if (btn) btn.classList.add('active');
                document.querySelectorAll('#settingsOverlay .overlay-sub-panel').forEach(p => p.classList.remove('active'));
                const panel = document.getElementById(panelId);
                if (panel) panel.classList.add('active');
            }

            /* ── Equipment Search/Filter ───────────────────────────────────────── */
            function filterEquipment() {
                const search = (document.getElementById('equipmentSearch').value || '').toLowerCase();
                const category = (document.getElementById('categoryFilter').value || '').toLowerCase();
                document.querySelectorAll('.item-node').forEach(item => {
                    const nameMatch = item.dataset.name.includes(search);
                    const catMatch = !category || item.dataset.category === category;
                    item.style.display = (nameMatch && catMatch) ? '' : 'none';
                });
            }

            /* ── Borrow Form ───────────────────────────────────────────────────── */
            function openBorrowForm(itemName) {
                document.getElementById('selectedItem').value = itemName;
                document.getElementById('selectedItemLabel').textContent = itemName;
                switchTab('lending');
                switchLendingSub('form');
            }

            /* ── Room Form ─────────────────────────────────────────────────────── */
            function openRoomForm(roomName) {
                document.getElementById('selectedRoomLabel').textContent = roomName;
                const sec = document.getElementById('room-form-section');
                if (sec) {
                    sec.classList.remove('hidden');
                    sec.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }

            function closeRoomForm() {
                const sec = document.getElementById('room-form-section');
                if (sec) sec.classList.add('hidden');
            }

            /* ── Notifications ─────────────────────────────────────────────────── */
            function filterNotifs(cat) {
                document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
                const btn = document.querySelector('.notif-tab[data-notif-filter="' + cat + '"]');
                if (btn) btn.classList.add('active');
                document.querySelectorAll('.notif-item').forEach(item => {
                    if (cat === 'all') item.style.display = '';
                    else if (cat === 'unread') item.style.display = item.classList.contains('unread') ? '' : 'none';
                    else item.style.display = item.dataset.cat === cat ? '' : 'none';
                });
            }

            function markAllRead() {
                document.querySelectorAll('.notif-item').forEach(item => {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.style.display = 'none';
                });
                const uc = document.getElementById('unreadCount');
                if (uc) uc.textContent = '0 unread';
                document.querySelectorAll('.notif-badge').forEach(b => b.style.display = 'none');
                showToast('All notifications marked as read.');
            }

            /* ── Settings: Theme ───────────────────────────────────────────────── */
            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                const tMap = {
                    'light': 'light',
                    'dark': 'dark',
                    'high-contrast': 'hc'
                };
                ['light', 'dark', 'hc'].forEach(k => {
                    const el = document.getElementById('tp-' + k);
                    const ch = document.getElementById('tc-' + k);
                    if (el) el.classList.remove('selected');
                    if (ch) ch.style.display = 'none';
                });
                const key = tMap[theme] || theme;
                const el = document.getElementById('tp-' + key);
                const ch = document.getElementById('tc-' + key);
                if (el) el.classList.add('selected');
                if (ch) ch.style.display = '';
                showToast('Theme: ' + theme.charAt(0).toUpperCase() + theme.slice(1));
            }

            /* ── Settings: Accent Color ────────────────────────────────────────── */
            function applyAccent(color, light) {
                document.querySelectorAll('.c-dot').forEach(d => d.classList.remove('selected'));
                const dot = document.querySelector('.c-dot[data-color="' + color + '"]');
                if (dot) dot.classList.add('selected');
                document.documentElement.style.setProperty('--accent-maroon', color);
                document.documentElement.style.setProperty('--accent-light', light);
                showToast('Accent color updated!');
            }

            /* ── Settings: Compact ─────────────────────────────────────────────── */
            function applyCompact(on) {
                document.documentElement.style.setProperty('--radius', on ? '9px' : '16px');
                showToast(on ? 'Compact mode enabled' : 'Compact mode disabled');
            }

            /* ── Settings: Font Size ───────────────────────────────────────────── */
            function applyFontSize(val) {
                const lbl = document.getElementById('fontSizeLbl');
                if (lbl) lbl.textContent = val + '%';
                document.documentElement.style.fontSize = (val / 100) + 'rem';
            }

            /* ── Settings: Reduce Motion ───────────────────────────────────────── */
            /* IMPORTANT: We only kill animation-duration here, NEVER touch
               `transition` on `*` — doing so was the root cause of the freeze bug
               because it would also null-out pointer-event related repaint cycles. */
            function applyReduceMotion(on) {
                let s = document.getElementById('reduceMotionStyle');
                if (!s) {
                    s = document.createElement('style');
                    s.id = 'reduceMotionStyle';
                    document.head.appendChild(s);
                }
                s.textContent = on ?
                    '*, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; }' :
                    '';
                showToast(on ? 'Animations disabled' : 'Animations re-enabled');
            }

            /* ── Settings: Focus Ring ──────────────────────────────────────────── */
            function applyFocusRing(on) {
                let s = document.getElementById('focusRingStyle');
                if (!s) {
                    s = document.createElement('style');
                    s.id = 'focusRingStyle';
                    document.head.appendChild(s);
                }
                s.textContent = on ?
                    '*:focus { outline: 3px solid var(--accent-maroon) !important; outline-offset: 3px !important; }' :
                    '';
                showToast(on ? 'Focus rings enhanced' : 'Focus rings reset');
            }

            /* ── Settings: Reset All ───────────────────────────────────────────── */
            function resetAllSettings() {
                applyTheme('light');
                const ct = document.getElementById('compactToggle');
                if (ct) {
                    ct.checked = false;
                    applyCompact(false);
                }
                const fr = document.getElementById('fontSizeRange');
                if (fr) {
                    fr.value = 100;
                    applyFontSize(100);
                }
                const rmt = document.getElementById('reduceMotionToggle');
                if (rmt) {
                    rmt.checked = false;
                    applyReduceMotion(false);
                }
                const frt = document.getElementById('focusRingToggle');
                if (frt) {
                    frt.checked = false;
                    applyFocusRing(false);
                }
                applyAccent('#6d1b23', '#f3e5e6');
                showToast('All settings reset to defaults.');
            }

            /* ── Profile Edit ──────────────────────────────────────────────────── */
            function toggleProfileEdit() {
                const editBtn = document.getElementById('editProfileBtn');
                const saveBtn = document.getElementById('saveProfileBtn');
                const cancelBtn = document.getElementById('cancelProfileBtn');
                if (editBtn) editBtn.style.display = 'none';
                if (saveBtn) saveBtn.style.display = 'flex';
                if (cancelBtn) cancelBtn.style.display = 'flex';
                document.querySelectorAll('[data-field]').forEach(span => {
                    const key = span.dataset.field;
                    const input = document.querySelector('[data-input="' + key + '"]');
                    if (!input) return;
                    span.style.display = 'none';
                    input.style.display = '';
                    input.disabled = false;
                    if (span.classList.contains('empty')) input.value = '';
                });
            }

            function cancelProfileEdit() {
                const editBtn = document.getElementById('editProfileBtn');
                const saveBtn = document.getElementById('saveProfileBtn');
                const cancelBtn = document.getElementById('cancelProfileBtn');
                if (editBtn) editBtn.style.display = 'flex';
                if (saveBtn) saveBtn.style.display = 'none';
                if (cancelBtn) cancelBtn.style.display = 'none';
                document.querySelectorAll('[data-input]').forEach(input => {
                    const key = input.dataset.input;
                    const span = document.querySelector('[data-field="' + key + '"]');
                    if (!span) return;
                    span.style.display = '';
                    input.style.display = 'none';
                    input.disabled = true;
                });
            }

            function saveProfileEdit() {
                document.querySelectorAll('[data-input]').forEach(input => {
                    const key = input.dataset.input;
                    const span = document.querySelector('[data-field="' + key + '"]');
                    if (!span) return;
                    const val = input.tagName === 'SELECT' ?
                        input.options[input.selectedIndex].text :
                        input.value.trim();
                    if (val) {
                        span.textContent = val;
                        span.classList.remove('empty');
                    } else {
                        span.textContent = '— Not provided';
                        span.classList.add('empty');
                    }
                });
                cancelProfileEdit();
                showToast('Profile updated successfully!');
            }

            /* ── Borrow Form Init ──────────────────────────────────────────────── */
            function initBorrowForm() {
                const form = document.getElementById('borrowForm');
                const borrowInp = document.getElementById('borrow_date');
                const returnInp = document.getElementById('return_date');
                const instrInp = document.getElementById('instructorField');
                if (!form || !borrowInp || !returnInp) return;

                borrowInp.min = todayStr;
                returnInp.min = todayStr;

                borrowInp.addEventListener('change', function() {
                    returnInp.min = this.value;
                    if (returnInp.value && returnInp.value < this.value) returnInp.value = this.value;
                });

                if (instrInp) {
                    instrInp.addEventListener('input', function() {
                        this.value = this.value.replace(/[^a-zA-Z\s.']/g, '');
                    });
                }

                form.addEventListener('submit', function(e) {
                    const bv = borrowInp.value;
                    const rv = returnInp.value;
                    if (bv < todayStr) {
                        e.preventDefault();
                        alert('The borrow date cannot be in the past.');
                        return;
                    }
                    if (rv < bv) {
                        e.preventDefault();
                        alert('The return date cannot be earlier than the borrow date.');
                        return;
                    }
                    e.preventDefault();
                    document.getElementById('loading-overlay').classList.add('active');
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'borrow_submit';
                    hidden.value = '1';
                    this.appendChild(hidden);
                    setTimeout(() => this.submit(), 2000);
                });
            }

            /* ════════════════════════════════════════════════════════════════════
               MASTER EVENT DELEGATION
               All click-based interactions route through here. Each case is
               wrapped in a try-catch so one failing action can NEVER freeze
               the rest of the UI — this was the secondary cause of the freeze.
            ════════════════════════════════════════════════════════════════════ */
            document.addEventListener('click', function(e) {
                const el = e.target.closest('[data-action]');
                if (!el) return;
                const action = el.dataset.action;
                try {
                    switch (action) {
                        case 'open-overlay':
                            openOverlay(el.dataset.target);
                            break;
                        case 'close-overlay':
                            closeOverlay(el.dataset.target);
                            break;
                        case 'dismiss-alert': {
                            const t = document.getElementById(el.dataset.target);
                            if (t) t.style.display = 'none';
                            break;
                        }
                        case 'go-tab':
                            switchTab(el.dataset.tab);
                            if (el.dataset.lending) switchLendingSub(el.dataset.lending);
                            break;
                        case 'open-borrow-form':
                            openBorrowForm(el.dataset.item);
                            break;
                        case 'lending-back':
                            switchLendingSub('browse');
                            break;
                        case 'open-room-form':
                            openRoomForm(el.dataset.room);
                            break;
                        case 'close-room-form':
                            closeRoomForm();
                            break;
                        case 'room-reserve-preview':
                            showToast('Room Reservation feature coming soon!');
                            break;
                        case 'apply-theme':
                            applyTheme(el.dataset.theme);
                            break;
                        case 'apply-accent':
                            applyAccent(el.dataset.color, el.dataset.light);
                            break;
                        case 'reset-settings':
                            resetAllSettings();
                            break;
                        case 'profile-edit':
                            toggleProfileEdit();
                            break;
                        case 'profile-save':
                            saveProfileEdit();
                            break;
                        case 'profile-cancel':
                            cancelProfileEdit();
                            break;
                        case 'mark-all-read':
                            markAllRead();
                            break;
                        case 'toast':
                            showToast(el.dataset.msg || '');
                            break;
                        case 'logout':
                            closeDropdown();
                            if (confirm('Confirm Logout?')) window.location.href = 'logout.php';
                            break;
                    }
                } catch (err) {
                    console.warn('Action "' + action + '" failed:', err);
                }
            });

            /* ── Avatar button ────────────────────────────────────────────────── */
            const avatarBtn = document.getElementById('avatarBtn');
            if (avatarBtn) {
                avatarBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleDropdown();
                });
            }

            /* ── Close dropdown on outside click ─────────────────────────────── */
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.header-right')) closeDropdown();
            });

            /* ── Nav tabs ─────────────────────────────────────────────────────── */
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(this.dataset.tab);
                });
            });

            /* ── Lending sub-nav ──────────────────────────────────────────────── */
            document.querySelectorAll('.lending-nav-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchLendingSub(this.dataset.lendingNav);
                });
            });

            /* ── Account sub-nav ──────────────────────────────────────────────── */
            document.querySelectorAll('.acc-nav-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchAccTab(this.dataset.accTab);
                });
            });

            /* ── Settings sub-nav ─────────────────────────────────────────────── */
            document.querySelectorAll('.s-nav-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchSettTab(this.dataset.settTab);
                });
            });

            /* ── Notification filter tabs ─────────────────────────────────────── */
            document.querySelectorAll('.notif-tab').forEach(btn => {
                btn.addEventListener('click', function() {
                    filterNotifs(this.dataset.notifFilter);
                });
            });

            /* ── Equipment search/filter ──────────────────────────────────────── */
            const eqSearch = document.getElementById('equipmentSearch');
            const eqCat = document.getElementById('categoryFilter');
            if (eqSearch) eqSearch.addEventListener('input', filterEquipment);
            if (eqCat) eqCat.addEventListener('change', filterEquipment);

            /* ── Settings toggles — use 'change' event (reliable, no delegation conflict) */
            const compactToggle = document.getElementById('compactToggle');
            if (compactToggle) compactToggle.addEventListener('change', function() {
                applyCompact(this.checked);
            });

            const fontSizeRange = document.getElementById('fontSizeRange');
            if (fontSizeRange) fontSizeRange.addEventListener('input', function() {
                applyFontSize(this.value);
            });

            const reduceMotionToggle = document.getElementById('reduceMotionToggle');
            if (reduceMotionToggle) reduceMotionToggle.addEventListener('change', function() {
                applyReduceMotion(this.checked);
            });

            const focusRingToggle = document.getElementById('focusRingToggle');
            if (focusRingToggle) focusRingToggle.addEventListener('change', function() {
                applyFocusRing(this.checked);
            });

            /* ── Page Init ────────────────────────────────────────────────────── */
            function initPage() {
                // URL slug
                const userSlug = '<?php echo $user_slug; ?>';
                if (!window.location.search.includes(userSlug)) {
                    const newUrl = window.location.protocol + '//' + window.location.host +
                        window.location.pathname + '?u=' + userSlug;
                    window.history.replaceState({
                        path: newUrl
                    }, '', newUrl);
                }
                // Auto-hide success alert + clean URL param
                const sa = document.getElementById('success-alert');
                if (sa) {
                    const url = new URL(window.location);
                    url.searchParams.delete('success');
                    window.history.replaceState({}, document.title, url.pathname + (url.search || ''));
                    setTimeout(() => {
                        if (sa) sa.style.display = 'none';
                    }, 5000);
                }
                initBorrowForm();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPage);
            } else {
                initPage();
            }

        })();
    </script>

</body>

</html>