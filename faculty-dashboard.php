<?php

// application error disclosure vulnerability prevention 
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// csp vulnerability fix: generate a nonce for inline scripts and styles, and include it in the CSP header
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

require_once __DIR__ . '/includes/session-config.php';
require_once __DIR__ . '/includes/csrf.php';
if (!isset($_SESSION['faculty_id'])) {
    header("Location: ../Equipment-Lending-Website/landing-page.php");
    exit();
}
$fullname = $_SESSION['faculty_name'];
$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

require_once __DIR__ . '/includes/db.php';
$conn = getDB();

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

require_once __DIR__ . '/includes/arbitration-engine.php';

function maskEmail($email)
{
    if (!$email) return null;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return htmlspecialchars($email);
    $visible = htmlspecialchars(mb_substr($parts[0], 0, 4));
    return $visible . '***@' . htmlspecialchars($parts[1]);
}

// ── Auto-decline expired & mark overdue ───────────────────────────────────
$today = date('Y-m-d');
$reason_expired = "Request expired – borrow date has already passed";
$stmt_expired = $conn->prepare("UPDATE tbl_requests SET status='Declined', reason=? WHERE status='Waiting' AND borrow_date < ?");
$stmt_expired->bind_param("ss", $reason_expired, $today);
$stmt_expired->execute();
mysqli_query($conn, "UPDATE tbl_requests SET status='Overdue' WHERE status='Approved' AND return_date < '$today'");

// ── Handle Borrow Request ──────────────────────────────────────────────────
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name'])) {
    if (!isset($_SESSION['faculty_id'])) die("Unauthorized access");
    csrf_verify();
    $user_id = $_SESSION['faculty_id'];
    $user_query = mysqli_query($conn, "SELECT fullname, faculty_id FROM tbl_users WHERE faculty_id='" . mysqli_real_escape_string($conn, $user_id) . "'");
    $user = mysqli_fetch_assoc($user_query);
    if (!$user) die("User profile not found.");
    $faculty_name   = $user['fullname'];
    $faculty_id     = $user['faculty_id'];
    $borrow_date    = mysqli_real_escape_string($conn, $_POST['borrow_date']);
    $return_date    = mysqli_real_escape_string($conn, $_POST['return_date']);
    $equipment_name = mysqli_real_escape_string($conn, trim($_POST['equipment_name']));
    $room           = mysqli_real_escape_string($conn, $_POST['room']);
    $instructor     = mysqli_real_escape_string($conn, $faculty_name); // auto-filled from account name
    $current_date   = date('Y-m-d');
    if ($borrow_date < $current_date) die("Error: You cannot select a borrow date in the past.");
    if ($return_date < $borrow_date)  die("Error: Return date cannot be before the borrow date.");
    // ── Document upload validation ─────────────────────────────────────────────
    $document_path = null;
    if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['request_document']['error'] !== UPLOAD_ERR_OK) {
            die("File upload error. Please try again.");
        }
        if ($_FILES['request_document']['size'] > 5 * 1024 * 1024) {
            die("File too large. Maximum size is 5 MB.");
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['request_document']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed_mimes, true)) {
            die("Unsupported file type. Please upload a PDF, JPG, PNG, or WEBP file.");
        }
        // $document_path will be set in the next step after successful INSERT
    }
    $insert = "INSERT INTO tbl_requests (faculty_name,faculty_id,equipment_name,instructor,room,borrow_date,return_date,status,request_date)
               VALUES ('$faculty_name','$faculty_id','$equipment_name','$instructor','$room','$borrow_date','$return_date','Waiting',NOW())";
    if (mysqli_query($conn, $insert)) {
        $new_request_id = mysqli_insert_id($conn);

        // ── Move uploaded document and update document_path ────────────────────
        if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] === UPLOAD_ERR_OK) {
            $orig_name   = basename($_FILES['request_document']['name']);
            $safe_name   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
            $dest_name   = time() . '_' . $faculty_id . '_' . $safe_name;
            $dest_dir    = __DIR__ . '/uploads/request_letters/';
            $dest_path   = $dest_dir . $dest_name;
            $rel_path    = 'uploads/request_letters/' . $dest_name;

            if (move_uploaded_file($_FILES['request_document']['tmp_name'], $dest_path)) {
                $stmt_doc = $conn->prepare('UPDATE tbl_requests SET document_path = ? WHERE id = ?');
                if ($stmt_doc) {
                    $stmt_doc->bind_param('si', $rel_path, $new_request_id);
                    $stmt_doc->execute();
                    $stmt_doc->close();
                }
            }
        }

        ArbitrationEngine::process($conn, $new_request_id);

        // ── Generate return token if engine approved the request ──────────────
        $check_approved = $conn->prepare(
            "SELECT id FROM tbl_requests WHERE id = ? AND status = 'Approved' AND return_token IS NULL LIMIT 1"
        );
        if ($check_approved) {
            $check_approved->bind_param('i', $new_request_id);
            $check_approved->execute();
            $check_approved->get_result(); // consume result
            if ($check_approved->affected_rows > 0 || $conn->affected_rows > 0) {
                // Simpler: just always try to stamp it, the NULL guard prevents double-write
                $auto_token = bin2hex(random_bytes(32));
                $tok = $conn->prepare(
                    "UPDATE tbl_requests SET return_token = ? WHERE id = ? AND status = 'Approved' AND return_token IS NULL"
                );
                if ($tok) {
                    $tok->bind_param('si', $auto_token, $new_request_id);
                    $tok->execute();
                    $tok->close();
                }
            }
            $check_approved->close();
        }

        header("Location: faculty-dashboard.php?success=1");
        exit();
    } else {
        error_log('[PUPSync] faculty-dashboard borrow insert failed: ' . mysqli_error($conn));
        die("Error processing request. Please try again later.");
    }
}

// ── Inventory & Requests ───────────────────────────────────────────────────
$category_result  = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory WHERE is_archived = 0 ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY item_name ASC");
$uid_safe = mysqli_real_escape_string($conn, $_SESSION['faculty_id']);

// ── Stats ──────────────────────────────────────────────────────────────────
$stat_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe'"))['c'];
$stat_waiting  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Waiting'"))['c'];
$stat_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Approved'"))['c'];
$stat_declined = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Declined'"))['c'];
$stat_overdue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Overdue'"))['c'];

$has_overdue_block = $stat_overdue > 0;
// ── Requests JSON for JS ───────────────────────────────────────────────────
$requests_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY request_date DESC");
$requests_js = [];
while ($row = mysqli_fetch_assoc($requests_raw)) $requests_js[] = $row;
$requests_json = json_encode($requests_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// ── Overdue for notifications ──────────────────────────────────────────────
$overdue_items_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Overdue' ORDER BY return_date ASC");
$overdue_notifs = [];
while ($row = mysqli_fetch_assoc($overdue_items_raw)) $overdue_notifs[] = $row;
$notif_count = count($overdue_notifs);

// ── Avatar initials ────────────────────────────────────────────────────────
$name_parts = explode(' ', trim($fullname));
$firstname  = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

// ── Profile ────────────────────────────────────────────────────────────────
$profile_row = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT email, backup_email, profile_picture, dob, gender, nationality, 
     department, faculty_rank, phone, present_address, permanent_address, landline,
     emergency_name, emergency_relationship, emergency_phone 
     FROM tbl_users WHERE faculty_id='$uid_safe' LIMIT 1"
)) ?: [];
$db_email             = $profile_row['email']             ?? '';
$db_backup_email      = $profile_row['backup_email']      ?? '';
$db_profile_pic       = $profile_row['profile_picture']   ?? '';
$db_dob               = $profile_row['dob']               ?? '';
$db_gender            = $profile_row['gender']            ?? '';
$db_nationality       = $profile_row['nationality']       ?? '';
$db_department        = $profile_row['department']        ?? '';
$db_faculty_rank      = $profile_row['faculty_rank']      ?? '';
$db_phone             = $profile_row['phone']             ?? '';
$db_present_address   = $profile_row['present_address']   ?? '';
$db_permanent_address = $profile_row['permanent_address'] ?? '';
$db_landline          = $profile_row['landline']          ?? '';
$db_emergency_name    = $profile_row['emergency_name']        ?? '';
$db_emergency_rel     = $profile_row['emergency_relationship'] ?? '';
$db_emergency_phone   = $profile_row['emergency_phone']       ?? '';

$masked_email       = maskEmail($db_email);
$masked_backup      = maskEmail($db_backup_email);
$dob_display        = $db_dob ? date('F j, Y', strtotime($db_dob)) : '';
$dob_locked         = !empty($db_dob);
$gender_locked      = !empty($db_gender);
$nationality_locked = !empty($db_nationality);
$backup_locked      = !empty($db_backup_email);
$department_locked  = !empty($db_department);
$profile_pic_url    = !empty($db_profile_pic) ? 'uploads/profile_pictures/' . $db_profile_pic : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSync | Faculty Dashboard</title>
    <!-- Google Fonts: Hanken Grotesk + Inter (matches new design system) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Material Symbols -->
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet">
    <!-- Font Awesome (kept for existing icon references in JS) -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="css/faculty-dashboard.css">

    <!-- Faculty Code Card -->
    <link rel="stylesheet" href="css/faculty-code-card.css">

    <!-- Responsive System -->
    <link rel="stylesheet" href="css/faculty-dashboard-responsive.css">

    <!-- Dashboard Redesign v3 — Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- facilities tab portal -->
    <link rel="stylesheet" href="css/fcty-facilities.css">

    <style nonce="<?php echo $csp_nonce; ?>">
        /* fix for csp vulnerability. inline styles */
        /* ================================================================
       DASHBOARD REDESIGN v3 — panel-home overrides only
       All JS-referenced classes are preserved; only visual/layout
       styles are added or overridden here.
    ================================================================ */

        /* ── Hero Header ──────────────────────────────────────────────── */
        .dash-hero {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--color-primary-container) 0%, #5a0000 100%);
            border-radius: var(--radius-xl);
            padding: 28px 32px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .dash-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .dash-hero-text {
            position: relative;
            z-index: 1;
        }

        .dash-hero-eyebrow {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 6px;
        }

        .dash-hero-title {
            font-family: 'Syne', var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .dash-hero-name {
            color: #ffcece;
        }

        .dash-hero-sub {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 400;
        }

        .dash-hero-ornament {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 110px;
            height: 110px;
            color: #fff;
            opacity: 0.25;
            pointer-events: none;
        }

        [data-theme="dark"] .dash-hero {
            background: linear-gradient(135deg, #4a0000 0%, #2a0000 100%);
        }

        /* ── Stats Row layout override ────────────────────────────────── */
        /* .dashboard-stats-col already exists; we change it to a row */
        .dash-stats-row-layout {
            flex-direction: row !important;
            gap: 14px !important;
            margin-bottom: 20px;
        }

        .dash-stats-row-layout .stat-card {
            flex: 1;
            flex-direction: column;
            align-items: flex-start;
            padding: 18px 20px 16px;
            border-left: 3px solid var(--color-primary);
            border-radius: var(--radius-lg);
            gap: 2px;
        }

        .dash-stats-row-layout .stat-card-icon {
            position: static;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            background: color-mix(in srgb, var(--color-primary) 12%, transparent);
            margin-bottom: 10px;
            color: var(--color-primary);
        }

        .dash-stats-row-layout .stat-card-value {
            font-family: 'Syne', var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            margin-bottom: 2px;
        }

        .dash-stats-row-layout .stat-card-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--color-secondary);
        }

        .dash-stats-row-layout .stat-card-overdue {
            border-left-color: var(--color-error);
        }

        .dash-stats-row-layout .stat-card-overdue .stat-card-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ── Main Body Layout override ────────────────────────────────── */
        /* .dashboard-grid changed from 256px 1fr to 1fr 300px */
        .dash-body-layout {
            grid-template-columns: 1fr 296px !important;
            gap: 20px !important;
            margin-bottom: 0 !important;
            align-items: start;
        }

        .dash-bento-col {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .dash-sidebar-col {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Bento cards: taller, more vivid ─────────────────────────── */
        #panel-home .bento-grid {
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        #panel-home .bento-item {
            min-height: 160px;
            border-radius: var(--radius-xl);
            padding: 22px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative corner arc */
        #panel-home .bento-item::after {
            content: '';
            position: absolute;
            bottom: -28px;
            right: -28px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 18px solid currentColor;
            opacity: 0.07;
            pointer-events: none;
        }

        #panel-home .bento-item:nth-child(1) {
            color: var(--color-primary);
        }

        #panel-home .bento-item:nth-child(2) {
            color: #4a6cf7;
        }

        #panel-home .bento-item:nth-child(3) {
            color: #0d7a5f;
        }

        [data-theme="dark"] #panel-home .bento-item:nth-child(2) {
            color: #7c9fff;
        }

        [data-theme="dark"] #panel-home .bento-item:nth-child(3) {
            color: #34d99a;
        }

        #panel-home .bento-icon .material-symbols-outlined {
            font-size: 32px;
            color: inherit;
            opacity: 0.8;
        }

        #panel-home .bento-title {
            font-family: 'Syne', var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-top: 14px;
            margin-bottom: 3px;
            color: var(--color-on-surface);
        }

        /* ── Audit card inline (under bento) ─────────────────────────── */
        .dash-audit-inline {
            border-radius: var(--radius-lg);
            padding: 16px 18px;
        }

        /* ── Stat card clickable hover for row layout ─────────────────── */
        .dash-stats-row-layout .stat-card-clickable:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ── Section label spacing ────────────────────────────────────── */
        #panel-home .section-label {
            margin-top: 24px;
            margin-bottom: 12px;
        }

        /* ── Active card redesign: cleaner ────────────────────────────── */
        #panel-home .active-card {
            border-radius: var(--radius-xl);
            padding: 18px 20px;
            border-top: 3px solid var(--color-outline-variant);
            transition: transform var(--transition), box-shadow var(--transition), border-top-color var(--transition);
        }

        #panel-home .active-card:hover {
            border-top-color: var(--color-primary);
            transform: translateY(-2px);
        }

        #panel-home .active-card-overdue {
            border-top-color: var(--color-error) !important;
        }

        #panel-home .active-card-thumb {
            background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface-container));
            border-radius: var(--radius-md);
        }

        #panel-home .active-card-title {
            font-family: 'Syne', var(--font-display);
            font-weight: 700;
        }

        /* ── Quick actions pill style ─────────────────────────────────── */
        #panel-home .qa-btn {
            border-radius: var(--radius-full);
            padding: 9px 16px;
            font-size: 0.8rem;
            transition: background var(--transition), color var(--transition), box-shadow var(--transition);
        }

        #panel-home .qa-btn:hover {
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.12);
        }

        /* ── Faculty code card: matches sidebar ───────────────────────── */
        #panel-home .faculty-code-card {
            border-radius: var(--radius-lg);
        }

        /* ── Page header override (hide old one gracefully) ───────────── */
        #panel-home .page-header-block {
            display: none;
        }

        /* ── Dark theme hero ──────────────────────────────────────────── */
        [data-theme="dark"] .dash-hero-name {
            color: #ffb3b3;
        }

        /* ================================================================
           EQUIPMENT PANEL REDESIGN v4
           Scoped 100% to #panel-lending — zero impact on other tabs.
        ================================================================ */

        /* ── Sub-nav ──────────────────────────────────────────────── */
        #panel-lending .lending-subnav {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        #panel-lending .lending-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 8px;
            border: 1.5px solid #e5e7eb;
            background: #ffffff;
            color: #374151;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-sans);
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            transition: all .2s cubic-bezier(.4, 0, .2, 1);
        }

        #panel-lending .lending-nav-btn .material-symbols-outlined {
            font-size: 16px;
        }

        #panel-lending .lending-nav-btn.active {
            background: #570000;
            color: #fff;
            border-color: #570000;
            box-shadow: 0 3px 12px rgba(87, 0, 0, .28);
        }

        #panel-lending .lending-nav-btn:not(.active):hover {
            background: #fdf1f1;
            border-color: #c0a0a0;
            color: #570000;
        }

        /* ── Page Header ──────────────────────────────────────────── */
        #panel-lending .page-header-block {
            display: block !important;
            margin-bottom: 24px;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
        }

        #panel-lending .page-title-sm {
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1.875rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            letter-spacing: -0.4px;
            margin-bottom: 4px;
            line-height: 1.15;
        }

        #panel-lending .page-subtitle {
            font-size: 0.875rem !important;
            color: #6b7280 !important;
            font-weight: 400 !important;
        }

        /* ── Featured Banner ──────────────────────────────────────── */
        #panel-lending .featured-section {
            margin-bottom: 32px;
        }

        #panel-lending .featured-label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
            letter-spacing: -0.1px;
        }

        #panel-lending .featured-label .material-symbols-outlined {
            font-size: 18px;
            color: #f59e0b;
            font-variation-settings: 'FILL' 1;
        }

        #panel-lending .featured-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            align-items: stretch;
        }

        /* Hero featured card — spans 2 cols */
        #panel-lending .feat-hero {
            grid-column: span 2;
            background: #ffffff;
            border: 1px solid #f0e0e0;
            border-left: 4px solid #570000;
            border-radius: 14px;
            display: flex;
            flex-direction: row;
            overflow: hidden;
            box-shadow: 0 4px 20px -4px rgba(87, 0, 0, .10);
            transition: box-shadow .25s, transform .25s;
            min-height: 200px;
        }

        #panel-lending .feat-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px -6px rgba(87, 0, 0, .14);
        }

        #panel-lending .feat-hero-body {
            flex: 1;
            padding: 28px 28px 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        #panel-lending .feat-hero-img {
            width: 46%;
            background: linear-gradient(145deg, #f7f0f0, #ede4e4);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        #panel-lending .feat-hero-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .45s cubic-bezier(.4, 0, .2, 1);
        }

        #panel-lending .feat-hero-img-placeholder {
            width: 46%;
            background: linear-gradient(145deg, #f7f0f0, #ede4e4);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #panel-lending .feat-hero-img-placeholder .material-symbols-outlined {
            font-size: 64px;
            color: #570000;
            opacity: .12;
        }

        #panel-lending .feat-hero:hover .feat-hero-img img {
            transform: scale(1.07);
        }

        #panel-lending .feat-hero-title {
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1.45rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.3px;
            margin-bottom: 6px;
            line-height: 1.25;
        }

        #panel-lending .feat-hero-desc {
            font-size: 0.835rem;
            color: #6b7280;
            line-height: 1.55;
            margin-bottom: 0;
            flex: 1;
        }

        #panel-lending .feat-hero-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        #panel-lending .feat-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Secondary featured card */
        #panel-lending .feat-secondary {
            background: #ffffff;
            border: 1px solid #f0e0e0;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px -4px rgba(87, 0, 0, .08);
            transition: box-shadow .25s, transform .25s;
        }

        #panel-lending .feat-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -6px rgba(87, 0, 0, .13);
        }

        #panel-lending .feat-secondary-img {
            height: 130px;
            background: linear-gradient(145deg, #f7f0f0, #ede4e4);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        #panel-lending .feat-secondary-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s cubic-bezier(.4, 0, .2, 1);
        }

        #panel-lending .feat-secondary-img .material-symbols-outlined {
            font-size: 52px;
            color: #570000;
            opacity: .12;
        }

        #panel-lending .feat-secondary:hover .feat-secondary-img img {
            transform: scale(1.08);
        }

        #panel-lending .feat-secondary-body {
            padding: 16px 18px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        #panel-lending .feat-secondary-title {
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.15px;
            margin-bottom: 3px;
        }

        #panel-lending .feat-secondary-cat {
            font-size: 0.72rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 12px;
        }

        #panel-lending .feat-secondary-cat .material-symbols-outlined {
            font-size: 11px;
            color: rgba(87, 0, 0, .4);
        }

        /* ── Catalog section ──────────────────────────────────────── */
        #panel-lending .catalog-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        #panel-lending .catalog-section-title {
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.1px;
        }

        #panel-lending .catalog-count-chip {
            font-size: 0.72rem;
            font-weight: 600;
            color: #6b7280;
            background: #f3f4f6;
            border-radius: 20px;
            padding: 3px 10px;
        }

        /* Catalog glass card */
        #panel-lending .catalog-card {
            background: rgba(255, 255, 255, .82) !important;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(240, 224, 224, .7) !important;
            border-radius: 16px !important;
            padding: 24px !important;
            box-shadow: 0 4px 24px rgba(87, 0, 0, .05) !important;
        }

        /* Filter bar */
        #panel-lending .catalog-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        #panel-lending .catalog-search-wrap {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f9fafb !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            padding: 10px 16px !important;
            transition: border-color .18s, box-shadow .18s;
        }

        #panel-lending .catalog-search-wrap:focus-within {
            border-color: #570000 !important;
            box-shadow: 0 0 0 3px rgba(87, 0, 0, .08) !important;
            background: #fff !important;
        }

        #panel-lending .catalog-search-wrap .material-symbols-outlined {
            font-size: 18px;
            color: #9ca3af;
            flex-shrink: 0;
        }

        #panel-lending .catalog-search-wrap input {
            border: none;
            background: transparent;
            outline: none;
            font-family: var(--font-sans);
            font-size: 0.875rem;
            color: #111827;
            width: 100%;
        }

        #panel-lending .catalog-search-wrap input::placeholder {
            color: #9ca3af;
        }

        #panel-lending .catalog-filter-select {
            padding: 10px 14px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #f9fafb !important;
            color: #374151 !important;
            font-family: var(--font-sans);
            font-size: 0.875rem !important;
            font-weight: 500;
            outline: none;
            cursor: pointer;
            min-width: 175px;
            transition: border-color .18s;
        }

        #panel-lending .catalog-filter-select:focus {
            border-color: #570000 !important;
            box-shadow: 0 0 0 3px rgba(87, 0, 0, .08) !important;
        }

        /* ── Equipment grid ───────────────────────────────────────── */
        #panel-lending .eq-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important;
            gap: 16px !important;
        }

        /* ── Equipment card ───────────────────────────────────────── */
        #panel-lending .eq-item-card,
        #panel-lending .item-node {
            background: #ffffff !important;
            border: 1.5px solid #f0e0e0 !important;
            border-radius: 14px !important;
            overflow: hidden;
            box-shadow: 0 2px 12px -2px rgba(87, 0, 0, .07) !important;
            transition: transform .28s cubic-bezier(.4, 0, .2, 1), box-shadow .28s, border-color .28s !important;
            position: relative;
            display: flex !important;
            flex-direction: column !important;
        }

        #panel-lending .eq-item-card::after,
        #panel-lending .item-node::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #570000, #a00000);
            opacity: 0;
            transition: opacity .25s;
            border-radius: 14px 14px 0 0;
            pointer-events: none;
            z-index: 2;
        }

        #panel-lending .eq-item-card:hover,
        #panel-lending .item-node:hover {
            transform: translateY(-4px) !important;
            border-color: rgba(87, 0, 0, .22) !important;
            box-shadow: 0 12px 28px -5px rgba(87, 0, 0, .13), 0 4px 10px -4px rgba(87, 0, 0, .07) !important;
        }

        #panel-lending .eq-item-card:hover::after,
        #panel-lending .item-node:hover::after {
            opacity: 1;
        }

        /* Image zone */
        #panel-lending .eq-item-img-wrap {
            position: relative;
            overflow: hidden;
            background: linear-gradient(160deg, #fdf1f1 0%, #f5e8e8 100%);
            height: 160px;
        }

        #panel-lending .eq-item-img {
            width: 100% !important;
            height: 160px !important;
            object-fit: cover !important;
            display: block;
            transition: transform .45s cubic-bezier(.4, 0, .2, 1) !important;
        }

        #panel-lending .eq-item-card:hover .eq-item-img,
        #panel-lending .item-node:hover .eq-item-img {
            transform: scale(1.07) !important;
        }

        #panel-lending .eq-item-img-placeholder {
            width: 100% !important;
            height: 160px !important;
            background: linear-gradient(160deg, #fdf1f1 0%, #f0e4e4 100%) !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #panel-lending .eq-item-img-placeholder .material-symbols-outlined {
            font-size: 48px !important;
            color: #570000 !important;
            opacity: .12 !important;
        }

        /* Stock badge overlay on image */
        #panel-lending .eq-stock-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
        }

        /* Card body */
        #panel-lending .eq-item-body {
            padding: 14px 16px 16px !important;
            display: flex !important;
            flex-direction: column !important;
            flex: 1;
            background: #fff !important;
            border-top: 1px solid #f8eded !important;
        }

        #panel-lending .eq-item-name {
            font-family: 'Hanken Grotesk', var(--font-display) !important;
            font-weight: 700 !important;
            font-size: 0.975rem !important;
            color: #111827 !important;
            margin-bottom: 4px !important;
            letter-spacing: -0.1px;
            line-height: 1.3;
            transition: color .18s;
        }

        #panel-lending .eq-item-card:hover .eq-item-name,
        #panel-lending .item-node:hover .eq-item-name {
            color: #570000 !important;
        }

        #panel-lending .eq-item-cat {
            font-size: 0.69rem !important;
            font-weight: 600 !important;
            color: #9ca3af !important;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 10px !important;
        }

        #panel-lending .eq-item-cat .material-symbols-outlined {
            font-size: 11px !important;
            color: rgba(87, 0, 0, .4) !important;
        }

        /* Stock badges */
        #panel-lending .stock-badge {
            display: inline-flex !important;
            align-items: center;
            gap: 4px !important;
            padding: 3px 9px !important;
            border-radius: 20px !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            margin-bottom: 12px !important;
            width: fit-content;
            letter-spacing: 0.01em;
        }

        #panel-lending .stock-avail {
            background: #d1fae5 !important;
            color: #065f46 !important;
            border: 1px solid rgba(16, 185, 129, .18) !important;
        }

        #panel-lending .stock-avail .material-symbols-outlined {
            font-size: 12px !important;
            color: #059669 !important;
        }

        #panel-lending .stock-unavail {
            background: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid rgba(239, 68, 68, .15) !important;
        }

        #panel-lending .stock-unavail .material-symbols-outlined {
            font-size: 12px !important;
            color: #dc2626 !important;
        }

        /* Borrow button */
        #panel-lending .btn-borrow {
            width: 100% !important;
            padding: 10px !important;
            background: #570000 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 9px !important;
            cursor: pointer;
            font-weight: 600 !important;
            font-size: 0.855rem !important;
            font-family: var(--font-sans);
            margin-top: auto;
            box-shadow: 0 2px 8px rgba(87, 0, 0, .22) !important;
            transition: background .2s, transform .2s, box-shadow .2s !important;
            letter-spacing: 0.01em;
        }

        #panel-lending .btn-borrow:hover:not(:disabled) {
            background: #3d0000 !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 16px rgba(87, 0, 0, .32) !important;
        }

        #panel-lending .btn-borrow:disabled,
        #panel-lending .btn-borrow[disabled] {
            background: #f3f4f6 !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            border: 1px solid #e5e7eb !important;
        }

        #panel-lending .btn-borrow-blocked {
            background: transparent !important;
            color: #9ca3af !important;
            border: 1px solid #e5e7eb !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
        }

        /* Featured borrow button (outline ghost style) */
        #panel-lending .btn-borrow-ghost {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: 1.5px solid #e5e7eb;
            border-radius: 9px;
            background: transparent;
            color: #374151;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: var(--font-sans);
            cursor: pointer;
            transition: border-color .18s, background .18s, color .18s;
        }

        #panel-lending .btn-borrow-ghost:hover {
            border-color: #570000;
            color: #570000;
            background: #fdf1f1;
        }

        #panel-lending .btn-borrow-ghost .material-symbols-outlined {
            font-size: 15px;
        }

        /* Featured primary borrow button */
        #panel-lending .btn-borrow-primary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 22px;
            background: #570000;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: var(--font-sans);
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(87, 0, 0, .28);
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        #panel-lending .btn-borrow-primary:hover:not(:disabled) {
            background: #3d0000;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(87, 0, 0, .32);
        }

        #panel-lending .btn-borrow-primary:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            box-shadow: none;
            cursor: not-allowed;
        }

        #panel-lending .btn-borrow-primary .material-symbols-outlined {
            font-size: 16px;
        }

        /* Empty state */
        #panel-lending .eq-empty {
            grid-column: 1/-1;
            text-align: center;
            padding: 5rem 1rem;
        }

        #panel-lending .eq-empty .material-symbols-outlined {
            font-size: 52px !important;
            display: block;
            margin-bottom: 14px;
            opacity: .18 !important;
            color: #570000 !important;
        }

        #panel-lending .eq-empty p {
            font-size: 0.9rem;
            font-weight: 500;
            color: #9ca3af !important;
        }

        /* ── Dark theme ───────────────────────────────────────────── */
        [data-theme="dark"] #panel-lending .eq-item-card,
        [data-theme="dark"] #panel-lending .item-node {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-body {
            background: var(--color-surface-low) !important;
            border-top-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-name {
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-card:hover .eq-item-name,
        [data-theme="dark"] #panel-lending .item-node:hover .eq-item-name {
            color: #f4a0a0 !important;
        }

        [data-theme="dark"] #panel-lending .catalog-card {
            background: rgba(35, 21, 21, .85) !important;
            border-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .catalog-search-wrap,
        [data-theme="dark"] #panel-lending .catalog-filter-select {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .lending-nav-btn {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
            color: var(--color-on-surface-variant) !important;
        }

        [data-theme="dark"] #panel-lending .lending-nav-btn.active {
            background: var(--color-primary-container) !important;
            border-color: var(--color-primary-container) !important;
            color: #fff !important;
        }

        [data-theme="dark"] #panel-lending .page-title-sm {
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .btn-borrow:not(:disabled),
        [data-theme="dark"] #panel-lending .btn-borrow-primary:not(:disabled) {
            background: var(--color-primary-container) !important;
        }

        [data-theme="dark"] #panel-lending .feat-hero,
        [data-theme="dark"] #panel-lending .feat-secondary {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .feat-hero-title,
        [data-theme="dark"] #panel-lending .feat-secondary-title,
        [data-theme="dark"] #panel-lending .featured-label,
        [data-theme="dark"] #panel-lending .catalog-section-title {
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-img-placeholder,
        [data-theme="dark"] #panel-lending .feat-hero-img-placeholder,
        [data-theme="dark"] #panel-lending .feat-secondary-img {
            background: var(--color-surface-high) !important;
        }

        [data-theme="dark"] #panel-lending .catalog-count-chip {
            background: var(--color-surface-high) !important;
            color: var(--color-on-surface-variant) !important;
        }

        [data-theme="dark"] #panel-lending .btn-borrow-ghost {
            border-color: var(--color-outline-variant) !important;
            color: var(--color-on-surface-variant) !important;
        }
    </style>
</head>

<body>

    <!-- ================================================================
     SIDE NAVIGATION
================================================================ -->
    <nav class="side-nav" id="sideNav">
        <div class="side-nav-brand">
            <div class="side-nav-logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div>
                <div class="side-nav-title"><strong>PUP</strong>SYNC</div>
                <div class="side-nav-sub">Faculty Platform</div>
            </div>
        </div>

        <div class="side-nav-links">
            <a class="side-nav-item active" id="nav-home" data-tab="home" href="#">
                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="side-nav-item" id="nav-lending" data-tab="lending" href="#">
                <span class="material-symbols-outlined">inventory_2</span>
                <span>Equipment</span>
            </a>
            <a class="side-nav-item" id="nav-rooms" data-tab="rooms" href="#">
                <span class="material-symbols-outlined">apartment</span>
                <span>Facilities</span>
            </a>
            <a class="side-nav-item" id="nav-activity" data-tab="activity" href="#">
                <span class="material-symbols-outlined">history_edu</span>
                <span>My Activity</span>
            </a>
        </div>

        <div class="side-nav-footer">
            <a class="side-nav-item" id="nav-settings" data-action="open-overlay" data-target="settingsOverlay"
                href="#">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            <a class="side-nav-item" href="#" data-action="open-overlay" data-target="helpOverlay">
                <span class="material-symbols-outlined">help</span>
                <span>Help Center</span>
            </a>
        </div>
    </nav>

    <!-- ================================================================
     MAIN WRAPPER (right of sidebar)
================================================================ -->
    <div class="main-wrapper">

        <!-- ================================================================
     TOP APP BAR
================================================================ -->
        <header class="top-bar" id="topBar">
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="top-bar-search" style="position:relative;">
                <span class="material-symbols-outlined">search</span>
                <input type="search" id="globalSearch" placeholder="Search equipment, requests, facilities…"
                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="searchbox">
                <div class="live-search-dropdown" id="liveSearchDropdown" style="display:none;"></div>
            </div>
            <div class="top-bar-actions">
                <!-- Notification Bell -->
                <div class="top-bar-notif-wrap" id="notifWrap">
                    <button class="top-bar-icon-btn" id="notifBtn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if ($notif_count > 0): ?>
                            <span class="top-bar-badge" id="notifBadge"><?php echo $notif_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <!-- Notification Popover -->
                    <div class="notif-popover" id="notifPopover" role="menu">
                        <div class="notif-popover-head">
                            <span>Notifications</span>
                            <button class="notif-mark-read-btn" data-action="mark-all-read">Mark all read</button>
                        </div>
                        <div class="notif-popover-list">
                            <?php if (!empty($overdue_notifs)): foreach ($overdue_notifs as $on): ?>
                                    <div class="notif-pop-item unread" data-cat="overdue">
                                        <div class="notif-pop-dot notif-dot-error"></div>
                                        <div class="notif-pop-body">
                                            <div class="notif-pop-title">Overdue: <?php echo htmlspecialchars($on['equipment_name']); ?></div>
                                            <div class="notif-pop-sub">Due <?php echo htmlspecialchars($on['return_date']); ?> — return immediately</div>
                                        </div>
                                    </div>
                            <?php endforeach;
                            endif; ?>
                            <div class="notif-pop-item unread" data-cat="borrow">
                                <div class="notif-pop-dot notif-dot-primary"></div>
                                <div class="notif-pop-body">
                                    <div class="notif-pop-title">Borrow Request Approved</div>
                                    <div class="notif-pop-sub">Pick up at Admin Office before 5:00 PM</div>
                                </div>
                            </div>
                            <div class="notif-pop-item unread" data-cat="system">
                                <div class="notif-pop-dot notif-dot-secondary"></div>
                                <div class="notif-pop-body">
                                    <div class="notif-pop-title">System Maintenance Tonight</div>
                                    <div class="notif-pop-sub">PUPSYNC offline 11 PM – 1 AM</div>
                                </div>
                            </div>
                        </div>
                        <button class="notif-popover-footer" data-action="open-overlay" data-target="notifOverlay">View all notifications</button>
                    </div>
                </div>
                <!-- Avatar -->
                <div class="top-bar-profile-wrap" id="avatarWrap" data-name="<?php echo htmlspecialchars($fullname); ?>">
                    <button class="top-bar-avatar" id="avatarBtn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
                        <?php if ($profile_pic_url): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                        <?php endif; ?>
                    </button>
                    <!-- Simple Avatar Dropdown -->
                    <div class="profile-dropdown" id="profileDropdown" role="menu">
                        <div class="dd-header">
                            <div class="dd-avatar">
                                <?php if ($profile_pic_url): ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                                <?php else: ?>
                                    <?php echo htmlspecialchars($initials); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="dd-name"><?php echo htmlspecialchars($fullname); ?></span>
                                <span class="dd-sub">Faculty &mdash; ID: <?php echo htmlspecialchars($_SESSION['faculty_id']); ?></span>
                            </div>
                        </div>
                        <div class="dd-menu">
                            <button class="dd-item dd-logout" data-action="logout">
                                <span class="material-symbols-outlined dd-item-icon">logout</span> Sign Out
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </header>

        <!-- ================================================================
     MAIN CANVAS
================================================================ -->
        <main class="app-main" id="appMain">

            <!-- Success Alert -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-banner alert-success" id="success-alert">
                    <span class="material-symbols-outlined">check_circle</span>
                    <strong>Success!</strong> Your borrow request has been submitted for approval.
                    <button class="alert-close" data-action="dismiss-alert" data-target="success-alert" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Overdue Alert -->
            <div class="alert-banner alert-danger <?php echo $has_overdue_block ? '' : 'hidden'; ?>" id="overdue-alert">
                <span class="material-symbols-outlined">warning</span>
                <strong>Borrowing Blocked:</strong> You have overdue equipment. Return it to the Admin Office before you can borrow again.
                <button class="alert-close" data-action="dismiss-alert" data-target="overdue-alert" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- ============================================================
         TAB: DASHBOARD (HOME)  — Redesigned v3
    ============================================================ -->
            <div class="tab-panel active" id="panel-home">

                <!-- ── Flat Header ──────────────────────────────────── -->
                <div class="dash-flat-header">
                    <h1 class="dash-flat-title">Good <?php
                                                        $h = (int)date('H');
                                                        echo $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening');
                                                        ?>, <?php echo htmlspecialchars($firstname); ?>.</h1>
                    <p class="dash-flat-sub"><?php echo date('l, F j, Y'); ?> — Here is an overview of your active equipment and requests.</p>
                </div>

                <!-- ── Top Two-Column: [Stats+Bentos] | [Code Card] ── -->
                <div class="dash-top-grid">

                    <!-- LEFT col: stats bar + bento buttons -->
                    <div class="dash-top-left">

                        <!-- Stats Bar -->
                        <div class="dashboard-stats-col dash-stats-row-layout">
                            <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Approved">
                                <div class="stat-card-icon"><span class="material-symbols-outlined">devices</span></div>
                                <div class="stat-card-label">Active Borrowings:</div>
                                <div class="stat-card-value"><?php echo $stat_approved; ?></div>
                            </div>
                            <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Waiting">
                                <div class="stat-card-icon"><span class="material-symbols-outlined">pending</span></div>
                                <div class="stat-card-label">Pending Requests:</div>
                                <div class="stat-card-value"><?php echo $stat_waiting; ?></div>
                            </div>
                            <?php if ($stat_overdue > 0): ?>
                                <div class="stat-card stat-card-overdue stat-card-clickable" data-action="filter-requests" data-status="Overdue">
                                    <div class="stat-card-icon"><span class="material-symbols-outlined">alarm</span></div>
                                    <div class="stat-card-label">Overdue:</div>
                                    <div class="stat-card-value" id="statOverdueVal" style="background:#fee2e2;color:#dc2626;"><?php echo $stat_overdue; ?></div>
                                    <div class="stat-card-action-tag">Action Required</div>
                                </div>
                            <?php else: ?>
                                <div class="stat-card">
                                    <div class="stat-card-icon"><span class="material-symbols-outlined">receipt_long</span></div>
                                    <div class="stat-card-label">Total Requests:</div>
                                    <div class="stat-card-value"><?php echo $stat_total; ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Bento Action Buttons -->
                        <div class="dash-bento-row">
                            <div class="dash-bento-btn" data-action="go-tab" data-tab="lending" data-lending="browse">
                                <div class="dash-bento-icon">
                                    <span class="material-symbols-outlined">add_shopping_cart</span>
                                </div>
                                <div class="dash-bento-label">Borrow Equipment</div>
                            </div>
                            <div class="dash-bento-btn" data-action="go-tab" data-tab="rooms">
                                <div class="dash-bento-icon">
                                    <span class="material-symbols-outlined">meeting_room</span>
                                </div>
                                <div class="dash-bento-label">Reserve Room</div>
                            </div>
                        </div>

                    </div><!-- /dash-top-left -->

                    <!-- RIGHT col: Student Access Code card -->
                    <div class="dash-top-right">
                        <div class="dash-code-card" id="facultyCodePanel">
                            <div class="dash-code-card-header">
                                <span class="dash-code-card-title">Student Access Code</span>
                                <span class="dash-code-active-badge" id="fccBadge">ACTIVE</span>
                            </div>
                            <div class="dash-code-body" id="fccBody">
                                <div class="fcc-loading" style="padding:20px 0;">
                                    <span class="material-symbols-outlined" style="font-size:1.2rem;opacity:.4;animation:spin 1s linear infinite;">sync</span>
                                </div>
                            </div>
                            <button class="dash-code-generate-btn" id="btnGenerateCode">
                                Generate New Code
                            </button>
                        </div>
                    </div><!-- /dash-top-right -->

                </div><!-- /dash-top-grid -->

                <!-- ── Activity Overview ─────────────────────────────── -->
                <?php
                $timeline_raw = mysqli_query($conn, "SELECT equipment_name, status, request_date FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY request_date DESC LIMIT 3");
                $timeline_items = [];
                if ($timeline_raw) while ($tr = mysqli_fetch_assoc($timeline_raw)) $timeline_items[] = $tr;

                $monthly_counts = [];
                $month_labels = [];
                for ($i = 9; $i >= 0; $i--) {
                    $ts = strtotime("-$i months");
                    $month_labels[] = date('M', $ts);
                    $y = date('Y', $ts);
                    $m = date('m', $ts);
                    $cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND YEAR(request_date)=$y AND MONTH(request_date)=$m"))['c'] ?? 0;
                    $monthly_counts[] = (int)$cnt;
                }
                $max_count = max(1, max($monthly_counts));
                $highlight_idx = array_search(max($monthly_counts), $monthly_counts);
                ?>

                <div class="dash-section-title">Activity Overview</div>
                <div class="dash-activity-overview-grid">

                    <!-- Recent Activity -->
                    <div class="dash-activity-card">
                        <div class="dash-activity-card-head">
                            <span class="dash-activity-card-title">Recent Activity</span>
                            <button class="dash-activity-card-btn">Timeline <span style="font-size:10px;">▾</span></button>
                        </div>
                        <div class="dash-timeline">
                            <?php if (!empty($timeline_items)):
                                foreach ($timeline_items as $idx => $ti): ?>
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-dot <?php echo $idx === 0 ? 'dot-active' : ''; ?>"></div>
                                        <div class="dash-timeline-time"><?php echo date('g:i A', strtotime($ti['request_date'])); ?></div>
                                        <div class="dash-timeline-body">
                                            <div class="dash-timeline-title"><?php echo htmlspecialchars($ti['equipment_name']); ?></div>
                                            <div class="dash-timeline-date"><?php echo date('F j, Y', strtotime($ti['request_date'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach;
                            else: ?>
                                <div class="dash-timeline-item">
                                    <div class="dash-timeline-dot"></div>
                                    <div class="dash-timeline-time">—</div>
                                    <div class="dash-timeline-body">
                                        <div class="dash-timeline-title">No recent activity</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- My Activity Bar Chart -->
                    <div class="dash-activity-card">
                        <div class="dash-activity-card-head">
                            <span class="dash-activity-card-title">My Activity</span>
                            <button class="dash-activity-card-btn">Charts <span style="font-size:10px;">▾</span></button>
                        </div>
                        <div class="dash-bar-chart">
                            <div class="dash-bar-chart-y">
                                <span><?php echo ceil($max_count * 1.25); ?></span>
                                <span><?php echo ceil($max_count * 1.0); ?></span>
                                <span><?php echo ceil($max_count * 0.5); ?></span>
                                <span>0</span>
                            </div>
                            <div class="dash-bar-chart-grid">
                                <div class="dash-bar-chart-grid-line"></div>
                                <div class="dash-bar-chart-grid-line"></div>
                                <div class="dash-bar-chart-grid-line"></div>
                                <div class="dash-bar-chart-grid-line"></div>
                            </div>
                            <div class="dash-bar-chart-area">
                                <?php foreach ($monthly_counts as $idx => $cnt):
                                    $h = $cnt > 0 ? round(($cnt / ($max_count * 1.25)) * 100) : 4;
                                    $isHL = ($idx == $highlight_idx && $cnt > 0);
                                ?>
                                    <div class="dash-bar <?php echo $isHL ? 'highlight' : ''; ?>" style="height:<?php echo $h; ?>%;"></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="dash-bar-chart-x">
                                <?php foreach ($month_labels as $ml): ?>
                                    <span><?php echo $ml; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /dash-activity-overview-grid -->

                <!-- ── Active Now ─────────────────────────────────────── -->
                <?php
                $active_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' AND status IN ('Approved','Overdue') ORDER BY return_date ASC LIMIT 4");
                $active_items = [];
                if ($active_raw) while ($ar = mysqli_fetch_assoc($active_raw)) $active_items[] = $ar;
                ?>
                <?php if (!empty($active_items)): ?>
                    <div class="dash-section-title" style="margin-top:32px;">Active Now</div>
                    <div class="active-cards-grid">
                        <?php foreach ($active_items as $ai):
                            $isOverdue = $ai['status'] === 'Overdue';
                            $chipClass = $isOverdue ? 'chip-error' : 'chip-success';
                            $chipLabel = $isOverdue ? 'OVERDUE' : 'ACTIVE';
                            $borrowTs = strtotime($ai['borrow_date']);
                            $returnTs = strtotime($ai['return_date']);
                            $nowTs = time();
                            $totalDays = max(1, $returnTs - $borrowTs);
                            $usedDays = max(0, min($nowTs - $borrowTs, $totalDays));
                            $progress = round(($usedDays / $totalDays) * 100);
                        ?>
                            <div class="active-card <?php echo $isOverdue ? 'active-card-overdue' : ''; ?>">
                                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;">
                                    <div class="active-card-thumb">
                                        <span class="material-symbols-outlined">inventory_2</span>
                                    </div>
                                    <span class="status-chip <?php echo $chipClass; ?>" style="font-size:0.65rem;padding:2px 8px;border-radius:4px;letter-spacing:0.5px;">
                                        <span class="chip-dot"></span><?php echo $chipLabel; ?>
                                    </span>
                                </div>
                                <div class="active-card-body">
                                    <div class="active-card-meta">Equipment</div>
                                    <div class="active-card-title"><?php echo htmlspecialchars($ai['equipment_name']); ?></div>
                                    <div class="active-card-sub"><?php echo htmlspecialchars($ai['equipment_name']); ?></div>
                                </div>
                                <div class="active-card-footer">
                                    <span class="active-card-due">Due dated: <?php echo date('F j, Y', strtotime($ai['return_date'])); ?></span>
                                    <div class="active-card-progress">
                                        <div class="active-card-progress-fill" style="width:<?php echo $progress; ?>%;<?php echo $isOverdue ? 'background:#dc2626;' : ''; ?>"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($active_items) < 3): ?>
                            <div class="dash-empty-active-slot">No other active items</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div><!-- /panel-home -->

            <!-- ============================================================
         TAB: EQUIPMENT (LENDING)
    ============================================================ -->
            <div class="tab-panel" id="panel-lending">

                <!-- Lending Sub-Nav -->
                <div class="lending-subnav">
                    <button class="lending-nav-btn active" data-lending-nav="browse">
                        <span class="material-symbols-outlined">search</span> Browse Equipment
                    </button>
                    <button class="lending-nav-btn" data-lending-nav="requests">
                        <span class="material-symbols-outlined">receipt_long</span> My Requests
                    </button>
                </div>

                <!-- ── Sub: Browse ─────────────────────────────────────── -->
                <div class="lending-sub active" id="lending-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Browse Equipment</h2>
                        <p class="page-subtitle">Search and select equipment to submit a borrow request.</p>
                    </div>

                    <!-- ── Featured Section ────────────────────────────────── -->
                    <?php
                    // Grab up to 2 featured (highest quantity available) items for the featured banner
                    mysqli_data_seek($inventory_result, 0);
                    $all_items = [];
                    while ($row = mysqli_fetch_assoc($inventory_result)) $all_items[] = $row;
                    $avail_items = array_filter($all_items, fn($r) => $r['quantity'] > 0);
                    usort($avail_items, fn($a, $b) => $b['quantity'] - $a['quantity']);
                    $featured_hero = $avail_items[0] ?? null;
                    $featured_sec  = $avail_items[1] ?? null;
                    if ($featured_hero || $featured_sec):
                    ?>
                        <div class="featured-section">
                            <div class="featured-label">
                                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">star</span>
                                Featured
                            </div>
                            <div class="featured-grid">
                                <!-- Hero Featured -->
                                <?php if ($featured_hero): ?>
                                    <div class="feat-hero">
                                        <div class="feat-hero-body">
                                            <div>
                                                <div class="feat-hero-meta">
                                                    <span class="stock-badge stock-avail" style="margin-bottom:0;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                        <?php echo (int)$featured_hero['quantity']; ?> available
                                                    </span>
                                                </div>
                                                <div class="feat-hero-title" style="margin-top:12px;"><?php echo htmlspecialchars($featured_hero['item_name']); ?></div>
                                                <div class="feat-hero-desc" style="margin-top:6px;"><?php echo !empty($featured_hero['description']) ? htmlspecialchars(mb_substr($featured_hero['description'], 0, 110)) . (mb_strlen($featured_hero['description']) > 110 ? '…' : '') : 'Available for borrowing. Submit a request to reserve this item for your class or event.'; ?></div>
                                            </div>
                                            <div class="feat-hero-actions" style="margin-top:20px;">
                                                <?php if ($has_overdue_block): ?>
                                                    <button class="btn-borrow-primary" disabled>
                                                        <span class="material-symbols-outlined">block</span> Overdue Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-borrow-primary"
                                                        data-action="open-borrow-form"
                                                        data-item="<?php echo htmlspecialchars($featured_hero['item_name'], ENT_QUOTES); ?>">
                                                        <span class="material-symbols-outlined">add_circle</span> Borrow Now
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($featured_hero['image_path'])): ?>
                                            <div class="feat-hero-img">
                                                <img src="<?php echo $base_url . htmlspecialchars($featured_hero['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured_hero['item_name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="feat-hero-img-placeholder">
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Secondary Featured -->
                                <?php if ($featured_sec): ?>
                                    <div class="feat-secondary">
                                        <div class="feat-secondary-img" style="position:relative;">
                                            <?php if (!empty($featured_sec['image_path'])): ?>
                                                <img src="<?php echo $base_url . htmlspecialchars($featured_sec['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured_sec['item_name']); ?>">
                                            <?php else: ?>
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            <?php endif; ?>
                                            <span class="stock-badge stock-avail"
                                                style="position:absolute;top:10px;left:10px;margin-bottom:0;z-index:2;background:rgba(209,250,229,.95);backdrop-filter:blur(4px);">
                                                <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                <?php echo (int)$featured_sec['quantity']; ?> available
                                            </span>
                                        </div>
                                        <div class="feat-secondary-body">
                                            <div class="feat-secondary-title"><?php echo htmlspecialchars($featured_sec['item_name']); ?></div>
                                            <div class="feat-secondary-cat">
                                                <span class="material-symbols-outlined">label</span>
                                                <?php echo htmlspecialchars($featured_sec['category']); ?>
                                            </div>
                                            <div style="margin-top:auto;">
                                                <?php if ($has_overdue_block): ?>
                                                    <button class="btn-borrow-primary" style="width:100%;" disabled>Overdue Block</button>
                                                <?php else: ?>
                                                    <button class="btn-borrow-primary" style="width:100%;justify-content:center;"
                                                        data-action="open-borrow-form"
                                                        data-item="<?php echo htmlspecialchars($featured_sec['item_name'], ENT_QUOTES); ?>">
                                                        Borrow
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ── All Equipment Catalog ───────────────────────────── -->
                    <div class="catalog-card">
                        <div class="catalog-section-header">
                            <span class="catalog-section-title">All Equipment</span>
                            <span class="catalog-count-chip" id="equipCountChip">
                                <?php echo count($all_items); ?> items
                            </span>
                        </div>
                        <div class="catalog-filters">
                            <div class="catalog-search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="equipmentSearch" placeholder="Search by equipment name…">
                            </div>
                            <select id="categoryFilter" class="catalog-filter-select">
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
                            <?php if (empty($all_items)): ?>
                                <div class="eq-empty">
                                    <span class="material-symbols-outlined">inventory_2</span>
                                    <p>No equipment available at the moment.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($all_items as $item): ?>
                                    <div class="eq-item-card item-node"
                                        data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>"
                                        data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>"
                                        data-item-id="<?php echo (int)$item['item_id']; ?>">
                                        <div class="eq-item-img-wrap">
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img class="eq-item-img"
                                                    src="<?php echo $base_url . htmlspecialchars($item['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <?php else: ?>
                                                <div class="eq-item-img-placeholder">
                                                    <span class="material-symbols-outlined">inventory_2</span>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Stock badge overlay on image -->
                                            <div class="eq-stock-overlay">
                                                <?php if ($item['quantity'] > 0): ?>
                                                    <span class="stock-badge stock-avail" style="margin-bottom:0;backdrop-filter:blur(4px);background:rgba(209,250,229,.9) !important;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                        <?php echo (int)$item['quantity']; ?> available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-badge stock-unavail" style="margin-bottom:0;backdrop-filter:blur(4px);background:rgba(254,226,226,.9) !important;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">cancel</span>
                                                        Out of stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="eq-item-body">
                                            <div class="eq-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="eq-item-cat">
                                                <span class="material-symbols-outlined">label</span>
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <?php if ($has_overdue_block): ?>
                                                <button class="btn-borrow btn-borrow-blocked" disabled
                                                    title="You have an overdue item. Return it before borrowing again.">
                                                    Overdue Block
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-borrow" <?php if ($item['quantity'] <= 0) echo 'disabled'; ?>
                                                    data-action="open-borrow-form"
                                                    data-item="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                    <?php echo ($item['quantity'] > 0) ? 'Borrow' : 'Unavailable'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- /lending-browse -->

                <!-- ── Sub: Borrow Form (now a modal — this sub-panel is kept as an
                     empty shell so lending-nav references don't break) ── -->
                <div class="lending-sub" id="lending-form"></div><!-- /lending-form -->

                <!-- ── Sub: My Requests ────────────────────────────────── -->
                <div class="lending-sub" id="lending-requests">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">My Requests</h2>
                        <p class="page-subtitle">Track and manage all submitted borrow requests.</p>
                    </div>
                    <div class="table-surface">
                        <div class="table-toolbar">
                            <h3 class="table-toolbar-title">Request History</h3>
                            <div class="table-toolbar-actions">
                                <div class="req-filter-wrap">
                                    <span class="material-symbols-outlined"
                                        style="font-size:16px;color:var(--color-on-surface-variant)">filter_list</span>
                                    <select id="reqStatusFilter" class="req-filter-select"
                                        data-action="filter-requests-dd">
                                        <option value="All">All Statuses</option>
                                        <option value="Waiting">Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Declined">Declined</option>
                                        <option value="Overdue">Overdue</option>
                                        <option value="Returned">Returned</option>
                                    </select>
                                </div>
                                <button class="req-sort-btn" id="reqSortBtn" data-action="toggle-sort">
                                    <span class="material-symbols-outlined" style="font-size:16px">sort</span>
                                    <span id="reqSortLabel">Latest First</span>
                                </button>
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="requests-table" id="requestsTable">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Requested By</th>
                                        <th>Room</th>
                                        <th>Borrow Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="requestsTbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /lending-requests -->

            </div><!-- /panel-lending -->

            <!-- ============================================================
         TAB: FACILITIES (ROOMS)
    ============================================================ -->
            <?php include 'fcty-facilities.php'; ?>

            <!-- ============================================================
         TAB: MY ACTIVITY (Timeline)
    ============================================================ -->
            <div class="tab-panel" id="panel-activity">

                <?php
                /* ── Activity panel stats & board queries ─────────────────── */
                $act_total   = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT COUNT(*) as c FROM tbl_requests
                     WHERE faculty_id='$uid_safe' AND status != 'Waiting'"
                ))['c'] ?? 0;

                $act_pending = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT COUNT(*) as c FROM tbl_requests
                     WHERE faculty_id='$uid_safe' AND status='Approved'
                     AND return_date >= '$today'"
                ))['c'] ?? 0;

                $act_due     = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT COUNT(*) as c FROM tbl_requests
                     WHERE faculty_id='$uid_safe'
                     AND (status='Overdue'
                          OR (status='Approved' AND return_date = '$today'))"
                ))['c'] ?? 0;

                $act_upcoming = mysqli_query(
                    $conn,
                    "SELECT * FROM tbl_requests
                     WHERE faculty_id='$uid_safe'
                     AND status IN ('Waiting','Approved')
                     AND borrow_date >= '$today'
                     ORDER BY borrow_date ASC LIMIT 10"
                );

                $act_ongoing  = mysqli_query(
                    $conn,
                    "SELECT * FROM tbl_requests
                     WHERE faculty_id='$uid_safe'
                     AND status='Approved'
                     AND borrow_date <= '$today' AND return_date >= '$today'
                     ORDER BY return_date ASC LIMIT 10"
                );

                $act_history  = mysqli_query(
                    $conn,
                    "SELECT * FROM tbl_requests
                     WHERE faculty_id='$uid_safe'
                     AND (status='Declined' OR status='Overdue'
                          OR (status='Approved' AND return_date < '$today'))
                     ORDER BY request_date DESC LIMIT 10"
                );

                /* ── Equipment icon helper ─────────────────────────────────── */
                function actEquipIcon(string $name): string
                {
                    $n = strtolower($name);
                    if (str_contains($n, 'projector'))                    return 'videocam';
                    if (str_contains($n, 'remote') || str_contains($n, ' ac ') || $n === 'ac') return 'settings_remote';
                    if (str_contains($n, 'cord') || str_contains($n, 'extension')) return 'power';
                    if (str_contains($n, 'laptop') || str_contains($n, 'computer')) return 'laptop';
                    if (str_contains($n, 'camera'))                       return 'photo_camera';
                    if (str_contains($n, 'speaker') || str_contains($n, 'audio'))   return 'speaker';
                    if (str_contains($n, 'mic'))                          return 'mic';
                    if (str_contains($n, 'monitor') || str_contains($n, 'screen'))  return 'monitor';
                    if (str_contains($n, 'tablet') || str_contains($n, 'ipad'))     return 'tablet';
                    if (str_contains($n, 'printer'))                      return 'print';
                    return 'inventory_2';
                }

                /* ── Progress ring helper ──────────────────────────────────── */
                function actProgressRing(string $borrowDate, string $returnDate, string $today): array
                {
                    $bd    = strtotime($borrowDate);
                    $rd    = strtotime($returnDate);
                    $td    = strtotime($today);
                    $total = max(1, ($rd - $bd) / 86400);
                    $elapsed = max(0, ($td - $bd) / 86400);
                    $pct   = (int) min(100, round($elapsed / $total * 100));
                    $left  = max(0, (int) ceil(($rd - $td) / 86400));
                    $label = $left > 1 ? $left . 'd left' : ($left === 1 ? '1d left' : 'Due today');
                    return ['pct' => $pct, 'label' => $label, 'days_left' => $left];
                }
                ?>

                <!-- ── Page Header ──────────────────────────────────────── -->
                <div class="page-header-block"
                    style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
                    <div>
                        <h2 class="page-title-sm">My Activity Tracker</h2>
                        <p class="page-subtitle">Track your current requests, upcoming borrowings, and facility access.</p>
                    </div>
                    <button class="btn-download-report" onclick="window.print()">
                        <span class="material-symbols-outlined">download</span> Download Report
                    </button>
                </div>

                <!-- ── Stats Bar ────────────────────────────────────────── -->
                <div class="act-stats-bar">
                    <div class="act-stat">
                        <div class="act-stat-icon">
                            <span class="material-symbols-outlined">layers</span>
                        </div>
                        <div>
                            <p class="act-stat-label">Total Borrowed</p>
                            <p class="act-stat-value"><?php echo (int)$act_total; ?></p>
                        </div>
                    </div>
                    <div class="act-stat">
                        <div class="act-stat-icon">
                            <span class="material-symbols-outlined">history</span>
                        </div>
                        <div>
                            <p class="act-stat-label">Pending Returns</p>
                            <p class="act-stat-value"><?php echo (int)$act_pending; ?></p>
                        </div>
                    </div>
                    <div class="act-stat">
                        <div class="act-stat-icon">
                            <span class="material-symbols-outlined">calendar_today</span>
                        </div>
                        <div>
                            <p class="act-stat-label">Items Due Soon</p>
                            <p class="act-stat-value <?php echo $act_due > 0 ? 'act-stat-value-warn' : ''; ?>">
                                <?php echo (int)$act_due; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── Kanban Board ──────────────────────────────────────── -->
                <div class="act-board">

                    <!-- ── Column 1: Upcoming ──────────────────────────── -->
                    <div class="act-col">
                        <div class="act-col-header">
                            <h3 class="act-col-title">Upcoming</h3>
                            <button class="act-col-menu" title="Options">
                                <span class="material-symbols-outlined" style="font-size:18px;">more_horiz</span>
                            </button>
                        </div>
                        <div class="act-col-body">
                            <?php
                            $has_upcoming = false;
                            if ($act_upcoming):
                                while ($r = mysqli_fetch_assoc($act_upcoming)):
                                    $has_upcoming = true;
                                    $bd       = strtotime($r['borrow_date']);
                                    $td_ts    = strtotime($today);
                                    $daysAway = max(0, (int)ceil(($bd - $td_ts) / 86400));
                                    $awayStr  = $daysAway > 1 ? 'In ' . $daysAway . ' days'
                                        : ($daysAway === 1 ? 'Tomorrow' : 'Starts today');
                                    $icon     = actEquipIcon($r['equipment_name']);
                                    $isPending = $r['status'] === 'Waiting';
                            ?>
                                    <div class="act-card">
                                        <div class="act-card-icon">
                                            <span class="material-symbols-outlined"
                                                style="font-variation-settings:'FILL' 0;">
                                                <?php echo $icon; ?>
                                            </span>
                                        </div>
                                        <h4 class="act-card-title">
                                            <?php echo htmlspecialchars($r['equipment_name']); ?>
                                        </h4>
                                        <div class="act-card-meta">
                                            <span class="material-symbols-outlined">calendar_today</span>
                                            <?php echo htmlspecialchars($r['borrow_date']); ?> &rarr;
                                            <?php echo htmlspecialchars($r['return_date']); ?>
                                        </div>
                                        <div class="act-card-meta">
                                            <span class="material-symbols-outlined">location_on</span>
                                            Room: <?php echo htmlspecialchars($r['room']); ?>
                                        </div>
                                        <div class="act-card-progress">
                                            <div class="act-progress-ring">
                                                <svg viewBox="0 0 36 36" class="act-ring-svg">
                                                    <path class="act-ring-bg"
                                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                        fill="none" stroke-width="3" />
                                                    <path class="act-ring-fg"
                                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                        fill="none" stroke-width="3"
                                                        stroke-dasharray="0, 100" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="act-progress-label">Starts in</p>
                                                <p class="act-progress-value"><?php echo $awayStr; ?></p>
                                            </div>
                                        </div>
                                        <?php if ($isPending): ?>
                                            <span class="act-status-chip act-chip-warning">
                                                <span class="chip-dot"></span>Awaiting Approval
                                            </span>
                                        <?php else: ?>
                                            <button class="act-card-action"
                                                data-action="go-tab" data-tab="lending" data-lending="browse">
                                                Extend Borrowing
                                            </button>
                                        <?php endif; ?>
                                    </div>
                            <?php endwhile;
                            endif; ?>
                            <?php if (!$has_upcoming): ?>
                                <div class="act-col-empty">
                                    <span class="material-symbols-outlined">event_upcoming</span>
                                    <p>No upcoming requests</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Column 2: Ongoing ───────────────────────────── -->
                    <div class="act-col">
                        <div class="act-col-header">
                            <h3 class="act-col-title">Ongoing</h3>
                            <button class="act-col-menu" title="Options">
                                <span class="material-symbols-outlined" style="font-size:18px;">more_horiz</span>
                            </button>
                        </div>
                        <div class="act-col-body">
                            <?php
                            $has_ongoing = false;
                            if ($act_ongoing):
                                while ($r = mysqli_fetch_assoc($act_ongoing)):
                                    $has_ongoing = true;
                                    $ring  = actProgressRing($r['borrow_date'], $r['return_date'], $today);
                                    $icon  = actEquipIcon($r['equipment_name']);
                            ?>
                                    <div class="act-card">
                                        <div class="act-card-icon">
                                            <span class="material-symbols-outlined"
                                                style="font-variation-settings:'FILL' 0;">
                                                <?php echo $icon; ?>
                                            </span>
                                        </div>
                                        <h4 class="act-card-title">
                                            <?php echo htmlspecialchars($r['equipment_name']); ?>
                                        </h4>
                                        <div class="act-card-meta">
                                            <span class="material-symbols-outlined">calendar_today</span>
                                            <?php echo htmlspecialchars($r['borrow_date']); ?> &rarr;
                                            <?php echo htmlspecialchars($r['return_date']); ?>
                                        </div>
                                        <div class="act-card-meta">
                                            <span class="material-symbols-outlined">location_on</span>
                                            Room: <?php echo htmlspecialchars($r['room']); ?>
                                        </div>
                                        <div class="act-card-progress">
                                            <div class="act-progress-ring">
                                                <svg viewBox="0 0 36 36" class="act-ring-svg">
                                                    <path class="act-ring-bg"
                                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                        fill="none" stroke-width="3" />
                                                    <path class="act-ring-fg"
                                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                        fill="none" stroke-width="3"
                                                        stroke-dasharray="<?php echo $ring['pct']; ?>, 100" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="act-progress-label">Time left</p>
                                                <p class="act-progress-value"><?php echo $ring['label']; ?></p>
                                            </div>
                                        </div>
                                        <button class="act-card-action-outline">
                                            <span class="material-symbols-outlined"
                                                style="font-size:15px;vertical-align:middle;margin-right:4px;">report</span>
                                            Report Issue
                                        </button>
                                    </div>
                            <?php endwhile;
                            endif; ?>
                            <?php if (!$has_ongoing): ?>
                                <div class="act-col-empty">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <p>Nothing currently borrowed</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Column 3: History ───────────────────────────── -->
                    <div class="act-col">
                        <div class="act-col-header">
                            <h3 class="act-col-title">History</h3>
                            <button class="act-col-menu" title="Options">
                                <span class="material-symbols-outlined" style="font-size:18px;">more_horiz</span>
                            </button>
                        </div>
                        <div class="act-col-body">
                            <?php
                            $has_history = false;
                            if ($act_history):
                                while ($r = mysqli_fetch_assoc($act_history)):
                                    $has_history = true;
                                    $icon = actEquipIcon($r['equipment_name']);
                                    $isOverdue = $r['status'] === 'Overdue';
                                    $isDeclined = $r['status'] === 'Declined';
                            ?>
                                    <div class="act-card act-card-history">
                                        <div class="act-card-icon">
                                            <span class="material-symbols-outlined"
                                                style="font-variation-settings:'FILL' 0;">
                                                <?php echo $icon; ?>
                                            </span>
                                        </div>
                                        <h4 class="act-card-title">
                                            <?php echo htmlspecialchars($r['equipment_name']); ?>
                                        </h4>
                                        <div class="act-card-meta">
                                            <span class="material-symbols-outlined">calendar_today</span>
                                            <?php echo htmlspecialchars($r['borrow_date']); ?> &rarr;
                                            <?php echo htmlspecialchars($r['return_date']); ?>
                                        </div>
                                        <div class="act-history-row act-card-meta">
                                            <span>
                                                <span class="material-symbols-outlined">location_on</span>
                                                Room: <?php echo htmlspecialchars($r['room']); ?>
                                            </span>
                                            <?php if ($isDeclined): ?>
                                                <span class="act-status-chip act-chip-error" style="margin-left:auto;">
                                                    <span class="chip-dot"></span>Declined
                                                </span>
                                            <?php elseif ($isOverdue): ?>
                                                <span class="act-status-chip act-chip-error" style="margin-left:auto;">
                                                    <span class="chip-dot"></span>Overdue
                                                </span>
                                            <?php else: ?>
                                                <span class="act-card-check">
                                                    <span class="material-symbols-outlined"
                                                        style="font-size:11px;font-variation-settings:'FILL' 1;">check</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isDeclined && !empty($r['reason'])): ?>
                                            <p style="font-size:.75rem;color:var(--color-error);margin-top:6px;">
                                                <?php echo htmlspecialchars($r['reason']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                            <?php endwhile;
                            endif; ?>
                            <?php if (!$has_history): ?>
                                <div class="act-col-empty">
                                    <span class="material-symbols-outlined">history</span>
                                    <p>No completed requests yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /.act-board -->

            </div><!-- /panel-activity -->

            <!-- ── AI Support Hub ─────────────────────────────────────────── -->
            <!-- Chat window -->
            <div class="act-ai-chat" id="actAiChat">
                <div class="act-ai-chat-head">
                    <div class="act-ai-chat-head-info">
                        <div class="act-ai-chat-avatar">
                            <span class="material-symbols-outlined">smart_toy</span>
                        </div>
                        <div>
                            <div class="act-ai-chat-name">PUPSync AI Support</div>
                            <div class="act-ai-chat-status">Online</div>
                        </div>
                    </div>
                    <button class="act-ai-chat-close" onclick="document.getElementById('actAiChat').classList.remove('open')" title="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="act-ai-chat-body" id="actAiChatBody">
                    <div class="act-chat-msg">
                        <span class="act-chat-msg-time">AI Assistant</span>
                        <div class="act-chat-bubble act-chat-bubble-ai">
                            Hello <?php echo htmlspecialchars($fullname); ?>! I'm your PUPSync AI Assistant.
                            How can I help with your activity tracking today?
                        </div>
                    </div>
                </div>
                <div class="act-ai-chat-input">
                    <input type="text" id="actAiInput" placeholder="Type a message…"
                        onkeydown="if(event.key==='Enter') actAiSend()">
                    <button class="act-ai-chat-send" onclick="actAiSend()">
                        <span class="material-symbols-outlined">send</span>
                    </button>
                </div>
                <div class="act-ai-chat-footer">
                    <a href="#">
                        <span class="material-symbols-outlined">error_outline</span>
                        Manual Form: Report Damaged / Lost Item
                    </a>
                </div>
            </div>

            <!-- FAB -->
            <button class="act-ai-fab" id="actAiFab"
                onclick="document.getElementById('actAiChat').classList.toggle('open')"
                title="Chat with AI Support">
                <span class="material-symbols-outlined">smart_toy</span>
                <span class="act-ai-fab-tooltip">Chat with AI Support</span>
            </button>

            <script nonce="<?php echo $csp_nonce; ?>">
                // nonce for inline script — fix for csp vulnerability
                /* AI chat send stub — wire to real endpoint later */
                function actAiSend() {
                    const input = document.getElementById('actAiInput');
                    const msg = (input.value || '').trim();
                    if (!msg) return;
                    const body = document.getElementById('actAiChatBody');

                    /* User bubble */
                    const uDiv = document.createElement('div');
                    uDiv.className = 'act-chat-msg act-chat-msg-user';
                    uDiv.innerHTML = `<span class="act-chat-msg-time">You</span>
                    <div class="act-chat-bubble act-chat-bubble-user">${msg.replace(/</g,'&lt;')}</div>`;
                    body.appendChild(uDiv);
                    input.value = '';
                    body.scrollTop = body.scrollHeight;

                    /* Stub AI reply */
                    setTimeout(() => {
                        const aDiv = document.createElement('div');
                        aDiv.className = 'act-chat-msg';
                        aDiv.innerHTML = `<span class="act-chat-msg-time">AI Assistant</span>
                        <div class="act-chat-bubble act-chat-bubble-ai">
                            Thanks for your message! AI response support coming soon.
                        </div>`;
                        body.appendChild(aDiv);
                        body.scrollTop = body.scrollHeight;
                    }, 600);
                }
            </script>

        </main><!-- /app-main -->
    </div><!-- /main-wrapper -->

    <!-- ================================================================
     OVERLAY: ACCOUNT — merged into settingsOverlay below
     (kept as hidden anchor for legacy data-target references)
================================================================ -->
    <div class="overlay-page" id="accountOverlay">
        <div class="overlay-topbar">
            <button class="overlay-back-btn" data-action="close-overlay" data-target="accountOverlay">
                <span class="material-symbols-outlined">arrow_back</span> Back
            </button>
            <span class="overlay-topbar-title">My Account</span>
            <div class="overlay-topbar-brand"><strong>PUP</strong>SYNC</div>
        </div>
        <div class="account-layout">
            <div class="account-sidebar">
                <span class="account-sidebar-label">My Account</span>
                <button class="acc-nav-btn active" data-acc-tab="acc-overview">
                    <span class="material-symbols-outlined">badge</span> Overview
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-academic">
                    <span class="material-symbols-outlined">school</span> Department Info
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-contact">
                    <span class="material-symbols-outlined">contacts</span> Contact Details
                </button>
            </div>
            <div class="account-content">

                <!-- Overview -->
                <div id="acc-overview" class="overlay-sub-panel active">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ║ Overview</span>
                        <h2>Profile &amp; Identity</h2>
                        <p>Your personal details and login information.</p>
                    </div>
                    <div class="account-hero-card">
                        <div class="acc-avatar-section">
                            <div class="acc-avatar-large" id="accOverlayAvatar">
                                <?php if ($profile_pic_url): ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile"
                                        class="avatar-img">
                                <?php else: ?>
                                    <?php echo htmlspecialchars($initials); ?>
                                <?php endif; ?>
                            </div>
                            <div style="position:relative;">
                                <button class="btn-change-profile" id="accOverlayChangePhotoBtn"
                                    data-action="open-picture-menu">Change Photo</button>
                                <div class="picture-menu" id="accOverlayPictureMenu" style="display:none;">
                                    <button class="pic-menu-item" data-action="upload-picture">
                                        <span class="material-symbols-outlined"
                                            style="font-size:15px;margin-right:8px;">upload</span>Upload Photo
                                    </button>
                                    <?php if ($profile_pic_url): ?>
                                        <button class="pic-menu-item pic-menu-danger" data-action="remove-picture">
                                            <span class="material-symbols-outlined"
                                                style="font-size:15px;margin-right:8px;">delete</span>Remove Photo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="file" id="accOverlayPicInput" accept="image/jpeg,image/png,image/jpg,image/webp"
                                style="display:none;">
                        </div>
                        <div class="acc-hero-info">
                            <h2>
                                <?php echo htmlspecialchars($fullname); ?>
                            </h2>
                            <p>ID:
                                <?php echo htmlspecialchars($_SESSION['faculty_id']); ?>
                            </p>
                            <span class="acc-badge">
                                <span
                                    style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px;vertical-align:middle;"></span>
                                Active Faculty
                            </span>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="accOverlayEditBtn" data-action="profile-edit">Edit
                                Profile</button>
                            <button class="btn-save-acc" id="accOverlaySaveBtn" style="display:none;"
                                data-action="profile-save">
                                <span class="material-symbols-outlined"
                                    style="font-size:14px;margin-right:4px;">check</span>Save
                            </button>
                            <button class="btn-cancel-acc" id="accOverlayCancelBtn" style="display:none;"
                                data-action="profile-cancel">Cancel</button>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Personal Information</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val" data-field="fullname">
                                <?php echo htmlspecialchars($fullname); ?>
                            </span>
                            <input class="info-input-f" data-input="fullname"
                                value="<?php echo htmlspecialchars($fullname); ?>" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Faculty ID</span>
                            <span class="info-val">
                                <?php echo htmlspecialchars($_SESSION['faculty_id']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Login &amp; Security</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Primary Email</span>
                            <span class="info-val <?php echo $masked_email ? '' : 'empty'; ?>">
                                <?php echo $masked_email ?: '— Not provided'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Backup Email</span>
                            <span class="info-val <?php echo $masked_backup ? '' : 'empty'; ?>"
                                data-field="backup_email">
                                <?php echo $masked_backup ?: '— Not provided'; ?>
                            </span>
                            <?php if (!$backup_locked): ?>
                                <button class="btn-inline-action" data-action="open-backup-email-modal">Add</button>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Password</span>
                            <span class="info-val">••••••••••</span>
                            <button class="btn-inline-action" data-action="open-email-verify-modal">Change</button>
                        </div>
                    </div>
                </div><!-- /acc-overview -->

                <!-- Department -->
                <div id="acc-academic" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ║ Department</span>
                        <h2>Department Information</h2>
                        <p>Your faculty department and assignment details.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Department Assignment</h3>
                            <button class="btn-edit-acc" id="editAcademicBtn" data-action="academic-edit"
                                style="display:inline-flex;">Edit</button>
                            <button class="btn-save-acc" id="saveAcademicBtn" style="display:none;"
                                data-action="academic-save">Save Changes</button>
                            <button class="btn-cancel-acc" id="cancelAcademicBtn" style="display:none;"
                                data-action="academic-cancel">Cancel</button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Faculty ID</span>
                            <span class="info-val">
                                <?php echo htmlspecialchars($_SESSION['faculty_id']); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val">
                                <?php echo htmlspecialchars($fullname); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Department</span>
                            <span class="info-val <?php echo $department_locked ? '' : 'empty'; ?>" data-field="department">
                                <?php echo $department_locked ? htmlspecialchars($db_department) : '— Not provided'; ?>
                            </span>
                            <?php if (!$department_locked): ?>
                                <select class="info-input-f" data-input="department" disabled style="display:none;">
                                    <option value="">Select Department...</option>
                                    <?php foreach (['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'] as $p): ?>
                                        <option value="<?php echo $p; ?>">
                                            <?php echo $p; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Position / Rank</span>
                            <span class="info-val <?php echo $db_faculty_rank ? '' : 'empty'; ?>" data-field="faculty_rank">
                                <?php echo $db_faculty_rank ? htmlspecialchars($db_faculty_rank) : '— Not provided'; ?>
                            </span>
                            <select class="info-input-f" data-input="faculty_rank" disabled style="display:none;">
                                <option value="">Select Position...</option>
                                <?php foreach (['Instructor I', 'Instructor II', 'Instructor III', 'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Associate Professor I', 'Associate Professor II', 'Professor I', 'Professor II', 'Part-time Faculty'] as $rank): ?>
                                    <option value="<?php echo $rank; ?>">
                                        <?php echo $rank; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Status</span>
                            <span class="info-val"><span class="stock-badge stock-avail">Active / Regular</span></span>
                        </div>
                    </div>
                </div><!-- /acc-academic -->

                <!-- Contact -->
                <div id="acc-contact" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ║ Contact</span>
                        <h2>Contact Details</h2>
                        <p>How we can reach you.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Address</h3>
                            <button class="btn-edit-acc" id="editContactBtn" data-action="contact-edit"
                                style="display:inline-flex;">Edit</button>
                            <button class="btn-save-acc" id="saveContactBtn" style="display:none;"
                                data-action="contact-save">Save Changes</button>
                            <button class="btn-cancel-acc" id="cancelContactBtn" style="display:none;"
                                data-action="contact-cancel">Cancel</button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Present Address</span>
                            <span class="info-val <?php echo $db_present_address ? '' : 'empty'; ?>"
                                data-field="present_address">
                                <?php echo $db_present_address ? htmlspecialchars($db_present_address) : '— Not provided'; ?>
                            </span>
                            <textarea class="info-input-f" data-input="present_address"
                                placeholder="Enter your current address" disabled
                                style="display:none;min-height:60px;"></textarea>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Permanent Address</span>
                            <span class="info-val <?php echo $db_permanent_address ? '' : 'empty'; ?>"
                                data-field="permanent_address">
                                <?php echo $db_permanent_address ? htmlspecialchars($db_permanent_address) : '— Not provided'; ?>
                            </span>
                            <textarea class="info-input-f" data-input="permanent_address"
                                placeholder="Enter your permanent address" disabled
                                style="display:none;min-height:60px;"></textarea>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Phone</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Mobile Number</span>
                            <span class="info-val <?php echo $db_phone ? '' : 'empty'; ?>" data-field="phone">
                                <?php echo $db_phone ? htmlspecialchars($db_phone) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="phone" placeholder="e.g. +63 912 345 6789" disabled
                                style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Landline</span>
                            <span class="info-val <?php echo $db_landline ? '' : 'empty'; ?>" data-field="landline">
                                <?php echo $db_landline ? htmlspecialchars($db_landline) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="landline" placeholder="e.g. (02) 1234-5678" disabled
                                style="display:none;">
                        </div>
                    </div>
                </div><!-- /acc-contact -->

            </div>
        </div>
    </div><!-- /accountOverlay -->

    <!-- ================================================================
     OVERLAY: SETTINGS (Redesigned — Sidebar Tab Layout)
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">

        <!-- Sticky top-bar (back + title) -->
        <div class="sov-topbar">
            <button class="sov-back-btn" data-action="close-overlay" data-target="settingsOverlay">
                <span class="material-symbols-outlined">arrow_back</span>
            </button>
            <div class="sov-topbar-brand"><strong>PUP</strong>SYNC</div>
        </div>

        <!-- Two-column shell -->
        <div class="sov-shell">

            <!-- ── Profile Banner ───────────────────────────── -->
            <section class="sov-banner">
                <div class="sov-banner-inner">
                    <h1 class="sov-banner-title">Profile Summary</h1>
                    <div class="sov-profile-card">
                        <!-- Join date -->
                        <div class="sov-pc-col sov-pc-meta">
                            <span class="material-symbols-outlined sov-meta-icon">calendar_today</span>
                            <span class="sov-meta-lbl">Joined: Oct 2023</span>
                        </div>
                        <!-- Avatar + name -->
                        <div class="sov-pc-col sov-pc-main">
                            <div class="sov-pc-avatar-wrap">
                                <div class="sov-pc-avatar">
                                    <?php if ($profile_pic_url): ?>
                                        <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="sov-avatar-img">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($initials); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h2 class="sov-pc-name"><?php echo htmlspecialchars($fullname); ?></h2>
                            <p class="sov-pc-id"><?php echo htmlspecialchars($_SESSION['faculty_id']); ?></p>
                            <span class="sov-pc-badge">Active Faculty</span>
                            <p class="sov-pc-verified">Last Verified: Oct 25, 2023</p>
                        </div>
                        <!-- Clearance + actions -->
                        <div class="sov-pc-col sov-pc-actions">
                            <div class="sov-clearance-pill">
                                <span>Clearance Status:</span>
                                <span class="sov-clearance-ok">Cleared</span>
                                <span class="material-symbols-outlined sov-clearance-chk">check_circle</span>
                            </div>
                            <button class="sov-action-btn" data-action="open-overlay" data-target="accountOverlay">View My Permissions</button>
                            <button class="sov-action-btn" data-action="open-email-verify-modal">Generate Pickup QR</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Settings Body (sidebar + content) ────────── -->
            <div class="sov-body">

                <!-- Left sidebar nav -->
                <nav class="sov-sidenav" id="sovSidenav">
                    <a class="sov-nav-item active" data-sov-tab="sov-tab-profile" href="#">
                        Profile
                    </a>
                    <a class="sov-nav-item" data-sov-tab="sov-tab-appearance" href="#">
                        Appearance
                    </a>
                    <a class="sov-nav-item" data-sov-tab="sov-tab-security" href="#">
                        Security
                    </a>
                    <a class="sov-nav-item" data-sov-tab="sov-tab-privacy" href="#">
                        Privacy
                    </a>
                </nav>

                <!-- Right content area -->
                <div class="sov-content">

                    <!-- ══ TAB: Profile ══════════════════════════════════ -->
                    <div class="sov-tab-panel active" id="sov-tab-profile">
                        <div class="sov-form-card">
                            <h3 class="sov-form-title">Profile</h3>
                            <div class="sov-form-grid">
                                <div class="sov-form-group">
                                    <label class="sov-label" for="sovFullName">Full Name</label>
                                    <input class="sov-input" id="sovFullName" type="text"
                                        value="<?php echo htmlspecialchars($fullname); ?>"
                                        data-field="fullname">
                                </div>
                                <div class="sov-form-group">
                                    <label class="sov-label" for="sovFacultyId">Faculty ID</label>
                                    <input class="sov-input sov-input-readonly" id="sovFacultyId" type="text"
                                        value="<?php echo htmlspecialchars($_SESSION['faculty_id']); ?>"
                                        readonly>
                                </div>
                                <div class="sov-form-group">
                                    <label class="sov-label" for="sovEmail">Email Address</label>
                                    <input class="sov-input" id="sovEmail" type="email"
                                        value="<?php echo htmlspecialchars($db_email); ?>"
                                        data-field="email">
                                </div>
                                <div class="sov-form-group">
                                    <label class="sov-label" for="sovDepartment">Department</label>
                                    <?php if ($department_locked): ?>
                                        <input class="sov-input sov-input-readonly" id="sovDepartment" type="text"
                                            value="<?php echo htmlspecialchars($db_department); ?>" readonly>
                                    <?php else: ?>
                                        <select class="sov-input" id="sovDepartment" data-field="department">
                                            <option value="">Select Department…</option>
                                            <?php foreach (['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'] as $p): ?>
                                                <option value="<?php echo $p; ?>" <?php echo $db_department === $p ? 'selected' : ''; ?>>
                                                    <?php echo $p; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="sov-form-group">
                                    <label class="sov-label" for="sovRank">Position / Rank</label>
                                    <select class="sov-input" id="sovRank" data-field="faculty_rank">
                                        <option value="">Select Position…</option>
                                        <?php foreach (['Instructor I', 'Instructor II', 'Instructor III', 'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Associate Professor I', 'Associate Professor II', 'Professor I', 'Professor II', 'Part-time Faculty'] as $rank): ?>
                                            <option value="<?php echo $rank; ?>" <?php echo $db_faculty_rank === $rank ? 'selected' : ''; ?>>
                                                <?php echo $rank; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sov-form-group">
                                    <label class="sov-label" for="sovPhone">Mobile Number</label>
                                    <input class="sov-input" id="sovPhone" type="tel"
                                        value="<?php echo htmlspecialchars($db_phone); ?>"
                                        placeholder="+63 912 345 6789"
                                        data-field="phone">
                                </div>
                            </div>
                            <div class="sov-form-footer">
                                <button class="sov-save-btn" data-action="contact-save">Save Changes</button>
                            </div>
                        </div>
                    </div><!-- /sov-tab-profile -->

                    <!-- ══ TAB: Appearance ═══════════════════════════════ -->
                    <div class="sov-tab-panel" id="sov-tab-appearance">
                        <div class="sov-form-card">
                            <h3 class="sov-form-title">Appearance</h3>

                            <!-- Theme -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Theme</p>
                                <div class="sov-theme-row">
                                    <div class="sov-theme-option" data-action="apply-theme" data-theme="light">
                                        <div class="sov-theme-swatch sov-swatch-light">
                                            <div class="sov-swatch-topbar"></div>
                                            <div class="sov-swatch-body">
                                                <div class="sov-swatch-sidebar"></div>
                                                <div class="sov-swatch-content"></div>
                                            </div>
                                        </div>
                                        <div class="sov-theme-check" id="tc-light">
                                            <span class="material-symbols-outlined">check</span>
                                        </div>
                                        <span class="sov-theme-name">Light</span>
                                    </div>
                                    <div class="sov-theme-option" data-action="apply-theme" data-theme="dark">
                                        <div class="sov-theme-swatch sov-swatch-dark">
                                            <div class="sov-swatch-topbar"></div>
                                            <div class="sov-swatch-body">
                                                <div class="sov-swatch-sidebar"></div>
                                                <div class="sov-swatch-content"></div>
                                            </div>
                                        </div>
                                        <div class="sov-theme-check" id="tc-dark">
                                            <span class="material-symbols-outlined">check</span>
                                        </div>
                                        <span class="sov-theme-name">Dark</span>
                                    </div>
                                    <div class="sov-theme-option" data-action="apply-theme" data-theme="high-contrast">
                                        <div class="sov-theme-swatch sov-swatch-hc">
                                            <div class="sov-swatch-topbar"></div>
                                            <div class="sov-swatch-body">
                                                <div class="sov-swatch-sidebar"></div>
                                                <div class="sov-swatch-content"></div>
                                            </div>
                                        </div>
                                        <div class="sov-theme-check" id="tc-hc">
                                            <span class="material-symbols-outlined">check</span>
                                        </div>
                                        <span class="sov-theme-name">High Contrast</span>
                                    </div>
                                </div>
                                <!-- Hidden legacy elements kept for JS compatibility -->
                                <select id="themeSelectUnified" style="display:none;">
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="high-contrast">High Contrast</option>
                                </select>
                                <div style="display:none;">
                                    <div id="tp-light"></div>
                                    <div id="tp-dark"></div>
                                    <div id="tp-hc"></div>
                                </div>
                                <p class="sov-desc-small">Current theme: <strong id="currentThemeLabel">Light</strong></p>
                            </div>

                            <!-- Font Size -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Text Size</p>
                                <div class="sov-font-row">
                                    <button class="sov-font-btn sov-font-sm" data-scale="80">A</button>
                                    <button class="sov-font-btn sov-font-md font-scale-active" data-scale="100">A</button>
                                    <button class="sov-font-btn sov-font-lg" data-scale="120">A</button>
                                </div>
                                <p class="sov-desc-small">Current size: <strong><span id="fontSizeLbl">100%</span></strong></p>
                                <input type="range" min="80" max="130" value="100" step="5" id="fontSizeRange" style="display:none;">
                                <!-- Legacy font-scale-btn kept for JS compatibility (hidden) -->
                                <div style="display:none;">
                                    <button class="font-scale-btn" data-scale="80">A</button>
                                    <button class="font-scale-btn font-scale-active" data-scale="100">A</button>
                                    <button class="font-scale-btn" data-scale="120">A</button>
                                </div>
                            </div>
                        </div>
                    </div><!-- /sov-tab-appearance -->

                    <!-- ══ TAB: Security ═════════════════════════════════ -->
                    <div class="sov-tab-panel" id="sov-tab-security">
                        <div class="sov-form-card">
                            <h3 class="sov-form-title">Security</h3>

                            <!-- Password -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Password</p>
                                <div class="sov-security-row">
                                    <div class="sov-security-info">
                                        <span class="material-symbols-outlined sov-sec-icon">lock</span>
                                        <div>
                                            <p class="sov-sec-title">Change Password</p>
                                            <p class="sov-sec-sub">Update your account password regularly to keep it secure.</p>
                                        </div>
                                    </div>
                                    <button class="sov-outline-btn" data-action="open-pw-modal">Change</button>
                                </div>
                            </div>

                            <!-- Backup Email -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Recovery</p>
                                <div class="sov-security-row">
                                    <div class="sov-security-info">
                                        <span class="material-symbols-outlined sov-sec-icon">alternate_email</span>
                                        <div>
                                            <p class="sov-sec-title">Backup Email</p>
                                            <p class="sov-sec-sub">
                                                <?php echo $masked_backup ? htmlspecialchars($masked_backup) : 'No backup email set.'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <button class="sov-outline-btn" data-action="open-backup-email-modal">
                                        <?php echo $backup_locked ? 'Edit' : 'Add'; ?>
                                    </button>
                                </div>
                            </div>

                            <!-- 2FA -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Two-Factor Authentication</p>
                                <div class="sov-security-row">
                                    <div class="sov-security-info">
                                        <span class="material-symbols-outlined sov-sec-icon">verified_user</span>
                                        <div>
                                            <p class="sov-sec-title">Authenticator App</p>
                                            <p class="sov-sec-sub">Add an extra layer of protection to your account.</p>
                                        </div>
                                    </div>
                                    <button class="sov-outline-btn" data-action="open-email-verify-modal">Manage</button>
                                </div>
                            </div>

                            <!-- Active Sessions -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Active Sessions</p>
                                <div class="sov-session-card">
                                    <div class="sov-session-info">
                                        <span class="material-symbols-outlined sov-sec-icon">devices</span>
                                        <div>
                                            <p class="sov-sec-title">Current Session</p>
                                            <p class="sov-sec-sub">This device — <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ? substr($_SERVER['HTTP_USER_AGENT'], 0, 40) . '…' : 'Unknown'); ?></p>
                                        </div>
                                    </div>
                                    <span class="sov-session-badge">Active</span>
                                </div>
                            </div>
                        </div>
                    </div><!-- /sov-tab-security -->

                    <!-- ══ TAB: Privacy ══════════════════════════════════ -->
                    <div class="sov-tab-panel" id="sov-tab-privacy">
                        <div class="sov-form-card">
                            <h3 class="sov-form-title">Privacy</h3>

                            <!-- Notification preferences -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Notification Preferences</p>
                                <div class="sov-toggle-list">
                                    <div class="sov-toggle-row">
                                        <div class="sov-toggle-info">
                                            <p class="sov-toggle-title">Email Alerts</p>
                                            <p class="sov-toggle-sub">Receive overdue reminders via email</p>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                                    </div>
                                    <div class="sov-toggle-row">
                                        <div class="sov-toggle-info">
                                            <p class="sov-toggle-title">Reservation Reminders</p>
                                            <p class="sov-toggle-sub">Get notified 24 hours before a booking</p>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                                    </div>
                                    <div class="sov-toggle-row">
                                        <div class="sov-toggle-info">
                                            <p class="sov-toggle-title">Account Activity Alerts</p>
                                            <p class="sov-toggle-sub">Receive security and login notifications</p>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                            </div>

                            <!-- Visibility -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Profile Visibility</p>
                                <div class="sov-toggle-list">
                                    <div class="sov-toggle-row">
                                        <div class="sov-toggle-info">
                                            <p class="sov-toggle-title">Show Profile to Other Faculty</p>
                                            <p class="sov-toggle-sub">Allow other faculty members to view your basic profile</p>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                                    </div>
                                    <div class="sov-toggle-row">
                                        <div class="sov-toggle-info">
                                            <p class="sov-toggle-title">Show Activity Status</p>
                                            <p class="sov-toggle-sub">Let others see when you were last active</p>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                            </div>

                            <!-- Data management -->
                            <div class="sov-section-block">
                                <p class="sov-section-label">Data Management</p>
                                <div class="sov-privacy-action-row">
                                    <div class="sov-security-info">
                                        <span class="material-symbols-outlined sov-sec-icon">download</span>
                                        <div>
                                            <p class="sov-sec-title">Export My Data</p>
                                            <p class="sov-sec-sub">Download a copy of all your activity and profile data.</p>
                                        </div>
                                    </div>
                                    <button class="sov-outline-btn" data-action="toast" data-msg="Data export coming soon!">Export</button>
                                </div>
                                <div class="sov-privacy-action-row sov-danger-row">
                                    <div class="sov-security-info">
                                        <span class="material-symbols-outlined sov-sec-icon sov-icon-danger">delete_forever</span>
                                        <div>
                                            <p class="sov-sec-title sov-text-danger">Delete Account</p>
                                            <p class="sov-sec-sub">Permanently remove your account and all associated data.</p>
                                        </div>
                                    </div>
                                    <button class="sov-danger-btn" data-action="toast" data-msg="Please contact your administrator to delete your account.">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div><!-- /sov-tab-privacy -->

                </div><!-- /sov-content -->
            </div><!-- /sov-body -->
        </div><!-- /sov-shell -->
    </div><!-- /settingsOverlay -->

    <!-- ================================================================
     OVERLAY: NOTIFICATIONS
================================================================ -->
    <div class="overlay-page" id="notifOverlay">
        <div class="overlay-topbar">
            <button class="overlay-back-btn" data-action="close-overlay" data-target="notifOverlay">
                <span class="material-symbols-outlined">arrow_back</span> Back
            </button>
            <span class="overlay-topbar-title">Notifications</span>
            <div class="overlay-topbar-brand"><strong>PUP</strong>SYNC</div>
        </div>
        <div class="notif-overlay-wrap">

            <!-- Header row -->
            <div class="notif-overlay-header">
                <div>
                    <h1 class="page-title">Notifications</h1>
                    <p class="page-subtitle">You have <strong id="unreadCount"><?php echo $notif_count; ?> unread</strong> notification<?php echo $notif_count !== 1 ? 's' : ''; ?>.</p>
                </div>
                <button class="mark-read-btn" data-action="mark-all-read">Mark all as read</button>
            </div>

            <!-- Filter tabs -->
            <div class="notif-filter-tabs">
                <button class="notif-tab active" data-notif-filter="all">All</button>
                <button class="notif-tab" data-notif-filter="unread">Unread</button>
                <button class="notif-tab" data-notif-filter="overdue">Overdue</button>
                <button class="notif-tab" data-notif-filter="borrow">Borrow</button>
                <button class="notif-tab" data-notif-filter="system">System</button>
            </div>

            <!-- Notification cards -->
            <div class="notif-card-list">

                <?php if (!empty($overdue_notifs)): ?>
                    <div class="notif-section-label notif-section-overdue">
                        <span class="material-symbols-outlined" style="font-size:14px;">alarm</span>
                        Overdue — Action Required
                    </div>
                    <?php foreach ($overdue_notifs as $on): ?>
                        <div class="notif-card unread notif-card-overdue" data-cat="overdue">
                            <div class="notif-card-icon ni-overdue">
                                <span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1">alarm</span>
                            </div>
                            <div class="notif-card-body">
                                <div class="notif-card-title">Overdue: <?php echo htmlspecialchars($on['equipment_name']); ?></div>
                                <div class="notif-card-sub">Due on <strong><?php echo htmlspecialchars($on['return_date']); ?></strong> — return immediately to avoid penalties.</div>
                            </div>
                            <div class="notif-card-meta">
                                <span class="status-chip chip-error"><span class="chip-dot"></span>Overdue</span>
                                <div class="unread-dot"></div>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>

                <div class="notif-section-label">Today</div>

                <div class="notif-card unread" data-cat="borrow">
                    <div class="notif-card-icon ni-success">
                        <span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1">check_circle</span>
                    </div>
                    <div class="notif-card-body">
                        <div class="notif-card-title">Borrow Request Approved</div>
                        <div class="notif-card-sub">Your latest borrow request has been approved. Pick up at the Admin Office before 5:00 PM.</div>
                    </div>
                    <div class="notif-card-meta">
                        <span class="notif-time">9:42 AM</span>
                        <div class="unread-dot"></div>
                    </div>
                </div>

                <div class="notif-card unread" data-cat="system">
                    <div class="notif-card-icon ni-alert">
                        <span class="material-symbols-outlined" style="font-size:18px;">settings</span>
                    </div>
                    <div class="notif-card-body">
                        <div class="notif-card-title">System Maintenance Tonight</div>
                        <div class="notif-card-sub">PUPSYNC will undergo scheduled maintenance from 11:00 PM to 1:00 AM.</div>
                    </div>
                    <div class="notif-card-meta">
                        <span class="notif-time">8:00 AM</span>
                        <div class="unread-dot"></div>
                    </div>
                </div>

                <div class="notif-section-label">Yesterday</div>

                <div class="notif-card unread" data-cat="borrow">
                    <div class="notif-card-icon ni-warn">
                        <span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1">warning</span>
                    </div>
                    <div class="notif-card-body">
                        <div class="notif-card-title">Return Reminder</div>
                        <div class="notif-card-sub">You have a borrowed item due in 1 day. Please return it on time to avoid penalties.</div>
                    </div>
                    <div class="notif-card-meta">
                        <span class="notif-time">4:15 PM</span>
                        <div class="unread-dot"></div>
                    </div>
                </div>

                <div class="notif-card" data-cat="borrow">
                    <div class="notif-card-icon ni-success">
                        <span class="material-symbols-outlined" style="font-size:18px;">inventory_2</span>
                    </div>
                    <div class="notif-card-body">
                        <div class="notif-card-title">Request Submitted</div>
                        <div class="notif-card-sub">Your borrow request was successfully submitted and is under review.</div>
                    </div>
                    <div class="notif-card-meta">
                        <span class="notif-time">2:00 PM</span>
                    </div>
                </div>

            </div><!-- /notif-card-list -->
        </div><!-- /notif-overlay-wrap -->
    </div><!-- /notifOverlay -->

    <!-- ================================================================
     OVERLAY: HELP CENTER
================================================================ -->
    <div class="overlay-page" id="helpOverlay">
        <div class="unified-settings-wrap">
            <div class="unified-settings-header">
                <h1>Help Center</h1>
                <p>Browse common topics or contact the system administrator for further assistance.</p>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I borrow equipment?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Go to the <strong>Equipment</strong> tab on the sidebar, browse the catalog, and click
                        <em>Borrow</em> on any available item. Fill in the borrow date, return date, room, and
                        instructor, then submit. Your request will be reviewed by the admin.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I return a borrowed item?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Physically return the borrowed item to the Admin Office. The administrator will then confirm and
                        mark your item as returned in the system. You can track the status update in <strong>Equipment
                            &mdash; My Requests</strong> or the <strong>My Activity</strong> tab.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        Why is my request showing as Overdue?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Your item's return date has passed without it being marked as returned. Please return the item
                        to the Admin Office immediately. Contact the system administrator if you believe this is an
                        error.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I reserve a facility or room?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Go to the <strong>Facilities</strong> tab on the sidebar. Browse available rooms, check their
                        availability, and submit a reservation request. Approvals are handled by the facilities
                        coordinator.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I update my profile or change my password?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Open <strong>Settings</strong> from the sidebar. From there you can update your profile
                        picture, name, department, and change your password securely.
                    </div>
                </details>

            </div>

            <div class="help-contact-card" style="margin-top:28px;">
                <span class="material-symbols-outlined"
                    style="font-size:32px;color:var(--color-primary);margin-bottom:8px;">support_agent</span>
                <h4>Still need help?</h4>
                <p>Contact the PUPSync system administrator for technical issues or escalations.</p>
                <a href="mailto:admin@pupsync.edu.ph" class="btn-urgent-primary"
                    style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;margin-top:12px;">
                    <span class="material-symbols-outlined" style="font-size:16px">mail</span>
                    Email Administrator
                </a>
            </div>
        </div>
    </div><!-- /helpOverlay -->


    <!-- ================================================================
     MODALS
================================================================ -->

    <!-- ── Borrow Request Modal ───────────────────────────────────────── -->
    <div class="modal-backdrop" id="borrowModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
        <div class="modal-box borrow-modal-box">
            <div class="modal-header">
                <h3 id="borrowModalTitle">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:8px;">inventory_2</span>
                    Borrow Request
                </h3>
                <button class="modal-close-btn" data-action="close-borrow-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="selected-item-banner" id="selectedItemBanner" style="margin-bottom:18px;">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <span id="selectedItemLabel">No item selected</span>
                </div>
                <form id="borrowForm" method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="equipment_name" id="selectedItem">
                    <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                    <div class="form-group">
                        <label class="form-label">Room / Laboratory</label>
                        <input type="text" name="room" class="form-input" placeholder="e.g. Lab 301" required>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Borrow Date</label>
                            <input type="date"
                                name="borrow_date"
                                id="borrow_date"
                                class="form-input"
                                min="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Return Date</label>
                            <input type="date"
                                name="return_date"
                                id="return_date"
                                class="form-input"
                                min="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="request_document">Request Letter
                            <span style="font-size:0.8em;color:var(--color-on-surface-variant);">(Optional — PDF, JPG, PNG, WEBP; max 5 MB)</span>
                        </label>
                        <input type="file" id="request_document" name="request_document"
                            accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control-custom">
                        <small style="color:var(--color-on-surface-variant);font-size:0.75rem;">Required for high-value equipment or organization borrowing.</small>
                    </div>
                    <button type="submit" class="btn-submit-form" style="width:100%;justify-content:center;margin-top:8px;">
                        <span class="material-symbols-outlined">send</span> Submit Borrow Request
                    </button>
                </form>
            </div>
        </div>
    </div><!-- /borrowModal -->

    <!-- Confirmation Modal -->
    <div class="modal-backdrop" id="confirmationModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">task_alt</span>Confirm Changes
                </h3>
                <button class="modal-close-btn" data-action="close-confirmation-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Please review the
                    changes you're about to make:</p>
                <div class="changes-summary" id="changesSummary"></div>
                <div class="warning-box">
                    <span class="material-symbols-outlined" style="color:#856404;flex-shrink:0;">warning</span>
                    <div><strong style="color:#856404;display:block;margin-bottom:4px;">Important Notice</strong>
                        <p style="color:#856404;margin:0;font-size:0.875rem;" id="warningMessage">Some changes cannot be
                            reversed once saved.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-confirmation-modal">Cancel</button>
                <button class="btn-save-acc" id="confirmChangesBtn" data-action="confirm-changes">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Confirm
                    &amp; Save
                </button>
            </div>
        </div>
    </div>

    <!-- Email Verify Modal -->
    <div class="modal-backdrop" id="emailVerifyModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">mail</span>Verify Your Email</h3>
                <button class="modal-close-btn" data-action="close-email-verify-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">For security,
                    verify your email address before changing your password.</p>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="verifyEmailInput" class="form-input"
                        placeholder="Enter your registered email" autocomplete="email">
                </div>
                <p class="modal-error" id="emailVerifyError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-email-verify-modal">Cancel</button>
                <button class="btn-save-acc" id="emailVerifyBtn" data-action="submit-email-verify">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Verify
                    &amp; Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Backup Email Modal -->
    <div class="modal-backdrop" id="backupEmailModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">alternate_email</span>Backup
                    Email</h3>
                <button class="modal-close-btn" data-action="close-backup-email-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Add a backup email
                    for account recovery and important notifications.</p>
                <div class="form-group">
                    <label class="form-label">Backup Email Address</label>
                    <input type="email" id="backupEmailInput" class="form-input" placeholder="Enter backup email"
                        autocomplete="email" value="<?php echo htmlspecialchars($db_backup_email); ?>">
                </div>
                <p class="modal-error" id="backupEmailError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-backup-email-modal">Cancel</button>
                <button class="btn-save-acc" id="backupEmailSaveBtn" data-action="save-backup-email">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Save
                    Backup Email
                </button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal-backdrop" id="pwModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">lock</span>Change Password</h3>
                <button class="modal-close-btn" data-action="close-pw-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwCurrent" class="form-input"
                            placeholder="Enter your current password" autocomplete="current-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwCurrent"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwNew" class="form-input" placeholder="At least 6 characters"
                            autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwNew"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                    <div class="pw-strength-bar" id="pwStrengthBar" style="display:none;">
                        <div class="pw-strength-fill" id="pwStrengthFill"></div>
                    </div>
                    <span class="pw-strength-label" id="pwStrengthLabel"></span>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwConfirm" class="form-input" placeholder="Repeat your new password"
                            autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwConfirm"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <p class="modal-error" id="pwModalError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-pw-modal">Cancel</button>
                <button class="btn-save-acc" id="pwSubmitBtn" data-action="submit-pw-change">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Update
                    Password
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Config for JS -->
    <script nonce="<?php echo $csp_nonce; ?>">
        window.USER_PROFILE_LOCKS = {
            dob: <?php echo $dob_locked ? 'true' : 'false'; ?>,
            gender: <?php echo $gender_locked ? 'true' : 'false'; ?>,
            nationality: <?php echo $nationality_locked ? 'true' : 'false'; ?>
        };
    </script>

    <!-- Loading Overlay -->
    <div id="loading-overlay"
        style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;">
        <div
            style="background:var(--color-surface);border-radius:16px;padding:2rem 2.5rem;display:flex;flex-direction:column;align-items:center;gap:12px;">
            <div class="spinner"></div>
            <p style="font-weight:600;color:var(--color-on-surface);font-size:0.9rem;">Processing your request…</p>
        </div>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>

    <script nonce="<?php echo $csp_nonce; ?>">
        window.REQUESTS_DATA = <?php echo $requests_json; ?>;
        window.USER_SLUG = '<?php echo $user_slug; ?>';
        window.OVERDUE_COUNT = <?php echo (int)$stat_overdue; ?>;
        window.SERVER_BASE_URL = '<?php
                                    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";

                                    // Get the real network IP of the laptop (not loopback 127.0.0.1)
                                    $server_addr = $_SERVER["SERVER_ADDR"] ?? "127.0.0.1";
                                    if ($server_addr === "127.0.0.1" || $server_addr === "::1") {
                                        $server_addr = gethostbyname(gethostname());
                                    }

                                    $port    = $_SERVER["SERVER_PORT"] ?? "80";
                                    $portStr = ($port == "80" || $port == "443") ? "" : ":" . $port;
                                    $path    = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
                                    echo $scheme . "://" . $server_addr . $portStr . $path . "/";
                                    ?>';
    </script>
    <!-- Mobile Nav Backdrop -->
    <div class="nav-backdrop" id="navBackdrop"></div>

    <script src="JS/faculty-dashboard.js"></script>
    <script src="JS/fcty-facilities.js"></script>
</body>

</html>