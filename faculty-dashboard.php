﻿<?php
session_start();
if (!isset($_SESSION['faculty_id'])) {
    header("Location: ../Equipment-Lending-Website/landing-page.php");
    exit();
}
$fullname = $_SESSION['faculty_name'];
$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

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
    } else die("Error processing request: " . mysqli_error($conn));
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/faculty-dashboard.css">
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
                <div class="top-bar-profile-wrap" id="avatarWrap">
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
         TAB: DASHBOARD (HOME)
    ============================================================ -->
            <div class="tab-panel active" id="panel-home">

                <!-- Page Header -->
                <div class="page-header-block">
                    <h1 class="page-title">Good
                        <?php
                        $h = (int)date('H');
                        echo $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening');
                        ?>,
                        <span style="color:var(--color-primary);"><?php echo htmlspecialchars($firstname); ?></span>.
                    </h1>
                    <p class="page-subtitle">
                        <?php echo date('l, F j, Y'); ?> &mdash; Here is an overview of your active equipment and
                        requests.
                    </p>
                </div>

                <!-- Overdue Urgent Card removed — emphasis moved to stat card below -->
                <!-- Stats + Quick Access Grid -->
                <div class="dashboard-grid">

                    <!-- Left: Stats Column -->
                    <div class="dashboard-stats-col">
                        <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Approved">
                            <div class="stat-card-label">Active Borrowings</div>
                            <div class="stat-card-value">
                                <?php echo $stat_approved; ?>
                            </div>
                            <div class="stat-card-icon"><span class="material-symbols-outlined">devices</span></div>
                        </div>
                        <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Waiting">
                            <div class="stat-card-label">Pending Requests</div>
                            <div class="stat-card-value" style="color:var(--color-warning)">
                                <?php echo $stat_waiting; ?>
                            </div>
                            <div class="stat-card-icon"><span class="material-symbols-outlined">pending</span></div>
                        </div>
                        <?php if ($stat_overdue > 0): ?>
                            <div class="stat-card stat-card-overdue stat-card-clickable" data-action="filter-requests"
                                data-status="Overdue">
                                <div class="stat-card-label">Overdue</div>
                                <div class="stat-card-value" id="statOverdueVal">
                                    <?php echo $stat_overdue; ?>
                                </div>
                                <div class="stat-card-action-tag">Action Required</div>
                                <div class="stat-card-icon"><span class="material-symbols-outlined">alarm</span></div>
                            </div>
                        <?php else: ?>
                            <div class="stat-card">
                                <div class="stat-card-label">Total Requests</div>
                                <div class="stat-card-value">
                                    <?php echo $stat_total; ?>
                                </div>
                                <div class="stat-card-icon"><span class="material-symbols-outlined">receipt_long</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Audit Log -->
                        <div class="audit-card">
                            <div class="audit-card-head">
                                <span class="material-symbols-outlined">history</span>
                                <span>Recent Activity</span>
                            </div>
                            <?php
                            $recent_raw = mysqli_query($conn, "SELECT equipment_name, status, request_date FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY request_date DESC LIMIT 3");
                            if ($recent_raw && mysqli_num_rows($recent_raw) > 0):
                                while ($rr = mysqli_fetch_assoc($recent_raw)):
                            ?>
                                    <div class="audit-row">
                                        <span class="audit-row-label">
                                            <?php echo htmlspecialchars($rr['equipment_name']); ?>
                                        </span>
                                        <span class="audit-row-time">
                                            <?php echo date('M j', strtotime($rr['request_date'])); ?>
                                        </span>
                                    </div>
                                <?php endwhile;
                            else: ?>
                                <div class="audit-row"><span class="audit-row-label"
                                        style="color:var(--color-on-surface-variant)">No recent activity</span></div>
                            <?php endif; ?>
                            <a class="audit-view-all" data-tab="activity" href="#">View Full Activity Log</a>
                        </div>
                        <!-- Quick Actions -->
                        <div class="quick-actions">
                            <h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon-img"
                                    style="color:var(--accent-maroon); margin-right:8px" aria-label="Quick"
                                    aria-hidden="true">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                </svg>Quick Actions</h3>
                            <button class="qa-btn" data-action="go-tab" data-tab="lending" data-lending="browse">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon-img" aria-label="Search" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg> Browse Equipment
                            </button>
                            <button class="qa-btn" data-action="go-tab" data-tab="lending" data-lending="requests">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon-img" aria-label="Requests" aria-hidden="true">
                                    <path
                                        d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                                </svg> My Requests
                            </button>
                            <button class="qa-btn" data-action="go-tab" data-tab="rooms">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon-img" aria-label="Rooms" aria-hidden="true">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                    <line x1="9" y1="3" x2="9" y2="21" />
                                    <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                                </svg> Reserve a Room
                            </button>
                            <button class="qa-btn" data-action="open-overlay" data-target="notifOverlay">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon-img" aria-label="Notifications"
                                    aria-hidden="true">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                </svg> Notifications
                                <?php if ($notif_count > 0): ?>
                                    <span class="notif-badge" style="font-size:0.7rem; padding: 1px 6px;">
                                        <?php echo $notif_count; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>

                    <!-- Right: Quick Access Bento -->
                    <div class="bento-grid">
                        <div class="bento-item" data-action="go-tab" data-tab="lending" data-lending="browse">
                            <div class="bento-icon"><span class="material-symbols-outlined">add_shopping_cart</span>
                            </div>
                            <div class="bento-title">Borrow Equipment</div>
                            <div class="bento-sub">Browse the equipment catalog</div>
                        </div>
                        <div class="bento-item" data-action="go-tab" data-tab="rooms">
                            <div class="bento-icon"><span class="material-symbols-outlined">meeting_room</span></div>
                            <div class="bento-title">Reserve Room</div>
                            <div class="bento-sub">Book lecture halls and labs</div>
                        </div>
                        <div class="bento-item bento-item-wide" data-action="go-tab" data-tab="activity">
                            <div class="bento-icon"><span class="material-symbols-outlined">history_edu</span></div>
                            <div class="bento-title">My Activity</div>
                            <div class="bento-sub">Track all your requests and reservations in one place</div>
                        </div>
                    </div>
                </div>


                <!-- Active Now Section -->
                <?php
                $active_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' AND status IN ('Approved','Overdue') ORDER BY return_date ASC LIMIT 4");
                $active_items = [];
                if ($active_raw) while ($ar = mysqli_fetch_assoc($active_raw)) $active_items[] = $ar;
                ?>
                <?php if (!empty($active_items)): ?>
                    <div class="section-label">Active Now</div>
                    <div class="active-cards-grid">
                        <?php foreach ($active_items as $ai): ?>
                            <div class="active-card <?php echo $ai['status'] === 'Overdue' ? 'active-card-overdue' : ''; ?>">
                                <div class="active-card-thumb">
                                    <span class="material-symbols-outlined">inventory_2</span>
                                </div>
                                <div class="active-card-body">
                                    <div class="active-card-meta">Equipment</div>
                                    <div class="active-card-title">
                                        <?php echo htmlspecialchars($ai['equipment_name']); ?>
                                    </div>
                                    <div class="active-card-sub">Room:
                                        <?php echo htmlspecialchars($ai['room']); ?>
                                    </div>
                                    <div class="active-card-footer">
                                        <span class="active-card-due">Due:
                                            <?php echo htmlspecialchars($ai['return_date']); ?>
                                        </span>
                                        <span
                                            class="status-chip <?php echo $ai['status'] === 'Overdue' ? 'chip-error' : 'chip-success'; ?>">
                                            <span class="chip-dot"></span>
                                            <?php echo $ai['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    <div class="catalog-card">
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
                            <?php if (mysqli_num_rows($inventory_result) == 0): ?>
                                <div class="eq-empty">
                                    <span class="material-symbols-outlined">inventory_2</span>
                                    <p>No equipment available at the moment.</p>
                                </div>
                            <?php else: ?>
                                <?php while ($item = mysqli_fetch_assoc($inventory_result)): ?>
                                    <div class="eq-item-card item-node"
                                        data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>"
                                        data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>"
                                        data-item-id="<?php echo (int)$item['item_id']; ?>">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img class="eq-item-img"
                                                src="/Equipment-Lending-Website/<?php echo htmlspecialchars($item['image_path']); ?>"
                                                alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                        <?php else: ?>
                                            <div class="eq-item-img-placeholder">
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="eq-item-body">
                                            <div class="eq-item-name">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                            </div>
                                            <div class="eq-item-cat">
                                                <span class="material-symbols-outlined" style="font-size:13px;">label</span>
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <?php if ($item['quantity'] > 0): ?>
                                                <span class="stock-badge stock-avail">
                                                    <span class="material-symbols-outlined"
                                                        style="font-size:12px;">check_circle</span>
                                                    <?php echo (int)$item['quantity']; ?> available
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-badge stock-unavail">
                                                    <span class="material-symbols-outlined" style="font-size:12px;">cancel</span>
                                                    Out of stock
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($has_overdue_block): ?>
                                                <button class="btn-borrow btn-borrow-blocked" disabled
                                                    title="You have an overdue item. Return it before borrowing again.">
                                                    Overdue Block
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-borrow" <?php if ($item['quantity'] <= 0) echo 'disabled'; ?>
                                                    data-action="open-borrow-form"
                                                    data-item="
                                            <?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                    <?php echo ($item['quantity'] > 0) ? 'Borrow' : 'Unavailable'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- /lending-browse -->

                <!-- ── Sub: Borrow Form ────────────────────────────────── -->
                <div class="lending-sub" id="lending-form">
                    <div class="page-header-block" style="display:flex;align-items:center;gap:12px;">
                        <button class="btn-back" data-action="lending-back" aria-label="Back">
                            <span class="material-symbols-outlined">arrow_back</span>
                        </button>
                        <div>
                            <h2 class="page-title-sm">Borrow Request</h2>
                            <p class="page-subtitle">Fill in the details to submit your request.</p>
                        </div>
                    </div>
                    <div class="form-surface">
                        <div class="selected-item-banner" id="selectedItemBanner">
                            <span class="material-symbols-outlined">inventory_2</span>
                            <span id="selectedItemLabel">No item selected</span>
                        </div>
                        <form id="borrowForm" method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="equipment_name" id="selectedItem">
                            <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                            <div class="form-group">
                                <label class="form-label">Room / Laboratory</label>
                                <input type="text" name="room" class="form-input" placeholder="e.g. Lab 301" required>
                            </div>
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label class="form-label">Borrow Date</label>
                                    <input type="date" name="borrow_date" id="borrow_date" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Return Date</label>
                                    <input type="date" name="return_date" id="return_date" class="form-input" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="request_document">Request Letter <span
                                        style="font-size:0.8em;color:var(--text-light);">(Optional — PDF, JPG, PNG,
                                        WEBP; max 5 MB)</span></label>
                                <input type="file" id="request_document" name="request_document"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control-custom">
                                <small style="color:var(--text-light);font-size:0.75rem;">Required for high-value
                                    equipment or organization borrowing.</small>
                            </div>
                            <button type="submit" class="btn-submit-form">
                                <span class="material-symbols-outlined">send</span> Submit Borrow Request
                            </button>
                        </form>
                    </div>
                </div><!-- /lending-form -->

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
            <div class="tab-panel" id="panel-rooms">
                <div class="page-header-block">
                    <h2 class="page-title-sm">Facilities</h2>
                    <p class="page-subtitle">Browse available rooms and make a reservation for your class or event.</p>
                </div>
                <div class="coming-soon-banner">
                    <span class="material-symbols-outlined">schedule</span>
                    <div>
                        <h3>Room Reservation — Coming Soon</h3>
                        <p>This feature is under development. Preview available rooms below.</p>
                    </div>
                </div>
                <div class="room-list" id="roomList">
                    <!-- Room 1 -->
                    <div class="room-card">
                        <div class="room-card-thumb">
                            <span class="material-symbols-outlined">computer</span>
                        </div>
                        <div class="room-card-body">
                            <div class="room-card-header">
                                <div>
                                    <h3 class="room-card-title">Computer Laboratory 301</h3>
                                    <p class="room-card-loc">3rd Floor, Main Building</p>
                                </div>
                                <span class="capacity-badge">40 seats</span>
                            </div>
                            <div class="room-amenities">
                                <span><span class="material-symbols-outlined" style="font-size:14px">wifi</span>
                                    WiFi</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">ac_unit</span>
                                    A/C</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">videocam</span>
                                    Projector</span>
                            </div>
                            <div class="room-card-footer">
                                <span class="room-avail"><span class="room-avail-dot"></span> Available</span>
                                <button class="btn-borrow" style="width:auto;padding:8px 20px;"
                                    data-action="room-reserve-preview"
                                    data-room="Computer Laboratory 301">Reserve</button>
                            </div>
                        </div>
                    </div>
                    <!-- Room 2 -->
                    <div class="room-card">
                        <div class="room-card-thumb">
                            <span class="material-symbols-outlined">science</span>
                        </div>
                        <div class="room-card-body">
                            <div class="room-card-header">
                                <div>
                                    <h3 class="room-card-title">Science Laboratory</h3>
                                    <p class="room-card-loc">2nd Floor, Science Wing</p>
                                </div>
                                <span class="capacity-badge">30 seats</span>
                            </div>
                            <div class="room-amenities">
                                <span><span class="material-symbols-outlined" style="font-size:14px">wifi</span>
                                    WiFi</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">ac_unit</span>
                                    A/C</span>
                            </div>
                            <div class="room-card-footer">
                                <span class="room-avail"><span class="room-avail-dot"></span> Available</span>
                                <button class="btn-borrow" style="width:auto;padding:8px 20px;"
                                    data-action="room-reserve-preview" data-room="Science Laboratory">Reserve</button>
                            </div>
                        </div>
                    </div>
                    <!-- Room 3 -->
                    <div class="room-card">
                        <div class="room-card-thumb">
                            <span class="material-symbols-outlined">meeting_room</span>
                        </div>
                        <div class="room-card-body">
                            <div class="room-card-header">
                                <div>
                                    <h3 class="room-card-title">Lecture Hall A</h3>
                                    <p class="room-card-loc">Ground Floor, Academic Building</p>
                                </div>
                                <span class="capacity-badge">80 seats</span>
                            </div>
                            <div class="room-amenities">
                                <span><span class="material-symbols-outlined" style="font-size:14px">wifi</span>
                                    WiFi</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">ac_unit</span>
                                    A/C</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">videocam</span>
                                    Projector</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">mic</span> Sound
                                    System</span>
                            </div>
                            <div class="room-card-footer">
                                <span class="room-occupied"><span class="room-occupied-dot"></span> In Use</span>
                                <button class="btn-borrow" style="width:auto;padding:8px 20px;"
                                    disabled>Unavailable</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /panel-rooms -->

            <!-- ============================================================
         TAB: MY ACTIVITY (Timeline)
    ============================================================ -->
            <div class="tab-panel" id="panel-activity">
                <div class="page-header-block"
                    style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h2 class="page-title-sm">My Activity Tracker</h2>
                        <p class="page-subtitle">Track your current requests, upcoming borrowings, and facility access.
                        </p>
                    </div>
                    <button class="btn-download-report" onclick="window.print()">
                        <span class="material-symbols-outlined">download</span> Download Report
                    </button>
                </div>

                <div class="timeline-container" id="activityTimeline">
                    <?php
                    // Group requests by date proximity
                    $all_req = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY borrow_date DESC, request_date DESC LIMIT 20");
                    $grouped = [];
                    if ($all_req) {
                        while ($r = mysqli_fetch_assoc($all_req)) {
                            $bd = $r['borrow_date'];
                            $grouped[$bd][] = $r;
                        }
                    }
                    if (empty($grouped)):
                    ?>
                        <div class="timeline-empty">
                            <span class="material-symbols-outlined">history_edu</span>
                            <p>No activity yet. Start by borrowing equipment or reserving a room.</p>
                            <button class="btn-borrow" style="width:auto;padding:10px 24px;margin-top:12px;"
                                data-action="go-tab" data-tab="lending" data-lending="browse">Browse Equipment</button>
                        </div>
                        <?php else: foreach ($grouped as $date => $items):
                            $label = '';
                            $d = strtotime($date);
                            $todayTs = strtotime($today);
                            $diff = (int)(($d - $todayTs) / 86400);
                            if ($diff === 0) $label = 'Today';
                            elseif ($diff === 1) $label = 'Tomorrow';
                            elseif ($diff === -1) $label = 'Yesterday';
                            else $label = date('M j, Y', $d);
                        ?>
                            <div class="timeline-group">
                                <div class="timeline-group-label">
                                    <span>
                                        <?php echo htmlspecialchars($label); ?>
                                    </span>
                                    <span class="timeline-date-chip">
                                        <?php echo date('M j', $d); ?>
                                    </span>
                                </div>
                                <div class="timeline-items">
                                    <?php foreach ($items as $ti):
                                        $isActive = in_array($ti['status'], ['Approved', 'Overdue']);
                                        $isOverdue = $ti['status'] === 'Overdue';
                                        $isPending = $ti['status'] === 'Waiting';
                                    ?>
                                        <div
                                            class="timeline-card <?php echo $isOverdue ? 'timeline-card-overdue' : ($isActive ? 'timeline-card-active' : ''); ?>">
                                            <div
                                                class="timeline-indicator <?php echo $isOverdue ? 'ti-error' : ($isActive ? 'ti-primary' : ($isPending ? 'ti-warning' : 'ti-muted')); ?>">
                                                <span class="material-symbols-outlined"
                                                    style="font-size:16px;font-variation-settings:'FILL' 1">
                                                    <?php echo $isOverdue ? 'alarm' : ($isActive ? 'inventory_2' : ($isPending ? 'hourglass_empty' : 'check_circle')); ?>
                                                </span>
                                            </div>
                                            <div class="timeline-card-content">
                                                <div class="timeline-card-top">
                                                    <div>
                                                        <h3 class="timeline-card-title">
                                                            <?php echo htmlspecialchars($ti['equipment_name']); ?>
                                                        </h3>
                                                        <p class="timeline-card-sub">
                                                            <span class="material-symbols-outlined"
                                                                style="font-size:14px">schedule</span>
                                                            <?php echo htmlspecialchars($ti['borrow_date']); ?> &rarr;
                                                            <?php echo htmlspecialchars($ti['return_date']); ?>
                                                            <?php if ($isActive): ?>
                                                                <span class="timeline-time-left"
                                                                    id="timeleft-<?php echo $ti['id']; ?>"></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <span class="status-chip <?php
                                                                                $chipClass = 'chip-muted';
                                                                                if ($ti['status'] === 'Approved') $chipClass = 'chip-success';
                                                                                elseif ($ti['status'] === 'Overdue')  $chipClass = 'chip-error';
                                                                                elseif ($ti['status'] === 'Waiting')  $chipClass = 'chip-warning';
                                                                                echo $chipClass;
                                                                                ?>">
                                                        <span class="chip-dot"></span>
                                                        <?php echo htmlspecialchars($ti['status']); ?>
                                                    </span>
                                                </div>
                                                <p class="timeline-card-detail">Room:
                                                    <?php echo htmlspecialchars($ti['room']); ?>
                                                </p>
                                                <?php if ($ti['status'] === 'Declined' && !empty($ti['reason'])): ?>
                                                    <p class="timeline-card-reason">Reason:
                                                        <?php echo htmlspecialchars($ti['reason']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                    <div class="timeline-end">
                        <div class="timeline-end-line"></div>
                        <span>End of Activity Log</span>
                        <div class="timeline-end-line"></div>
                    </div>
                </div>
            </div><!-- /panel-activity -->

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
     OVERLAY: SETTINGS (Redesigned Bento Style)
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">
        <div class="settings-bento-wrap">
            <!-- Header -->
            <div class="settings-bento-header">
                <button class="settings-back-btn" data-action="close-overlay" data-target="settingsOverlay">
                    <span class="material-symbols-outlined">arrow_back</span>
                </button>
                <div>
                    <h1>Settings</h1>
                    <p>Customize your experience and manage your account</p>
                </div>
            </div>

            <!-- Bento Grid -->
            <div class="settings-bento-grid">

                <!-- Profile Card (Large) -->
                <div class="bento-card bento-card-profile">
                    <div class="bento-card-header">
                        <span class="material-symbols-outlined bento-icon">account_circle</span>
                        <h3>Profile</h3>
                    </div>
                    <div class="bento-profile-content">
                        <div class="bento-avatar">
                            <?php if ($profile_pic_url): ?>
                                <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                            <?php else: ?>
                                <?php echo htmlspecialchars($initials); ?>
                            <?php endif; ?>
                        </div>
                        <div class="bento-profile-info">
                            <h4><?php echo htmlspecialchars($fullname); ?></h4>
                            <p><?php echo htmlspecialchars($_SESSION['faculty_id']); ?></p>
                            <span class="bento-badge">Active Faculty</span>
                        </div>
                    </div>
                    <button class="bento-btn" data-action="open-overlay" data-target="accountOverlay">
                        <span>Edit Profile</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>

                <!-- Appearance Card -->
                <div class="bento-card bento-card-appearance">
                    <div class="bento-card-header">
                        <span class="material-symbols-outlined bento-icon">palette</span>
                        <h3>Appearance</h3>
                    </div>
                    <div class="bento-theme-preview">
                        <div class="theme-circle theme-light" data-action="apply-theme" data-theme="light" title="Light"></div>
                        <div class="theme-circle theme-dark" data-action="apply-theme" data-theme="dark" title="Dark"></div>
                        <div class="theme-circle theme-hc" data-action="apply-theme" data-theme="high-contrast" title="High Contrast"></div>
                    </div>
                    <p class="bento-desc">Current: <strong id="currentThemeLabel">Light</strong></p>
                    <select id="themeSelectUnified" style="display:none;">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                        <option value="high-contrast">High Contrast</option>
                    </select>
                    <div style="display:none;">
                        <div id="tp-light"></div>
                        <div id="tp-dark"></div>
                        <div id="tp-hc"></div>
                        <svg id="tc-light">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <svg id="tc-dark">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <svg id="tc-hc">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                </div>

                <!-- Font Size Card -->
                <div class="bento-card bento-card-font">
                    <div class="bento-card-header">
                        <span class="material-symbols-outlined bento-icon">text_fields</span>
                        <h3>Font Size</h3>
                    </div>
                    <div class="bento-font-scale">
                        <button class="font-scale-btn" data-scale="80">A</button>
                        <button class="font-scale-btn font-scale-active" data-scale="100">A</button>
                        <button class="font-scale-btn" data-scale="120">A</button>
                    </div>
                    <p class="bento-desc"><span id="fontSizeLbl">100%</span></p>
                    <input type="range" min="80" max="130" value="100" step="5" id="fontSizeRange" style="display:none;">
                </div>

                <!-- Security Card -->
                <div class="bento-card bento-card-security">
                    <div class="bento-card-header">
                        <span class="material-symbols-outlined bento-icon">shield</span>
                        <h3>Security</h3>
                    </div>
                    <div class="bento-security-list">
                        <div class="security-item">
                            <span class="material-symbols-outlined">lock</span>
                            <span>Password</span>
                        </div>
                        <div class="security-item">
                            <span class="material-symbols-outlined">verified_user</span>
                            <span>2FA Enabled</span>
                        </div>
                    </div>
                    <button class="bento-btn" data-action="open-email-verify-modal">
                        <span>Manage Security</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>

                <!-- Notifications Card -->
                <div class="bento-card bento-card-notif">
                    <div class="bento-card-header">
                        <span class="material-symbols-outlined bento-icon">notifications</span>
                        <h3>Notifications</h3>
                    </div>
                    <div class="bento-notif-toggles">
                        <div class="notif-toggle-row">
                            <div>
                                <h4>Email Alerts</h4>
                                <p>Overdue reminders</p>
                            </div>
                            <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="notif-toggle-row">
                            <div>
                                <h4>Reservation Reminders</h4>
                                <p>24h before booking</p>
                            </div>
                            <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="notif-toggle-row">
                            <div>
                                <h4>Account Activity</h4>
                                <p>Login & security alerts</p>
                            </div>
                            <label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
                        </div>
                    </div>
                </div>

                <!-- Data & Privacy Card -->
                <div class="bento-card bento-card-privacy">
                    <div class="bento-card-header">
                        <span class="material-symbols-outlined bento-icon">privacy_tip</span>
                        <h3>Data & Privacy</h3>
                    </div>
                    <div class="bento-privacy-list">
                        <div class="privacy-item">
                            <span class="material-symbols-outlined">download</span>
                            <span>Export Data</span>
                        </div>
                        <div class="privacy-item">
                            <span class="material-symbols-outlined">delete</span>
                            <span>Delete Account</span>
                        </div>
                    </div>
                    <button class="bento-btn" data-action="toast" data-msg="Data management coming soon!">
                        <span>Manage Data</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>
            </div><!-- /settings-bento-grid -->
        </div><!-- /settings-bento-wrap -->
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
    <script>
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

    <script>
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
</body>

</html>