<?php
session_start();
if (!isset($_SESSION['faculty_id'])) {
    header("Location: landing-page.php");
    exit();
}
$fullname = $_SESSION['faculty_name'];
$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

require_once __DIR__ . '/includes/auto-approve-functions.php';

function maskEmail($email)
{
    if (!$email) return null;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return htmlspecialchars($email);
    $visible = htmlspecialchars(mb_substr($parts[0], 0, 4));
    return $visible . '***@' . htmlspecialchars($parts[1]);
}

// ΓöÇΓöÇ Auto-decline expired & mark overdue ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$today = date('Y-m-d');
$reason_expired = "Request expired ΓÇô borrow date has already passed";
$stmt_expired = $conn->prepare("UPDATE tbl_requests SET status='Declined', reason=? WHERE status='Waiting' AND borrow_date < ?");
$stmt_expired->bind_param("ss", $reason_expired, $today);
$stmt_expired->execute();
mysqli_query($conn, "UPDATE tbl_requests SET status='Overdue' WHERE status='Approved' AND return_date < '$today'");

// ΓöÇΓöÇ Handle Borrow Request ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name'])) {
    if (!isset($_SESSION['faculty_id'])) die("Unauthorized access");
    $user_id = $_SESSION['faculty_id'];
    $user_query = mysqli_query($conn, "SELECT fullname, student_id FROM tbl_users WHERE student_id='" . mysqli_real_escape_string($conn, $user_id) . "'");
    $user = mysqli_fetch_assoc($user_query);
    if (!$user) die("User profile not found.");
    $student_name   = $user['fullname'];
    $student_id     = $user['student_id'];
    $borrow_date    = mysqli_real_escape_string($conn, $_POST['borrow_date']);
    $return_date    = mysqli_real_escape_string($conn, $_POST['return_date']);
    $equipment_name = mysqli_real_escape_string($conn, $_POST['equipment_name']);
    $room           = mysqli_real_escape_string($conn, $_POST['room']);
    $instructor     = mysqli_real_escape_string($conn, $student_name); // auto-filled from account name
    $current_date   = date('Y-m-d');
    if ($borrow_date < $current_date) die("Error: You cannot select a borrow date in the past.");
    if ($return_date < $borrow_date)  die("Error: Return date cannot be before the borrow date.");
    $insert = "INSERT INTO tbl_requests (student_name,student_id,equipment_name,instructor,room,borrow_date,return_date,status,request_date)
               VALUES ('$student_name','$student_id','$equipment_name','$instructor','$room','$borrow_date','$return_date','Waiting',NOW())";
    if (mysqli_query($conn, $insert)) {
        $new_request_id = mysqli_insert_id($conn);
        processAutoApprove($conn, $new_request_id);
        header("Location: faculty-dashboard.php?success=1");
        exit();
    } else die("Error processing request: " . mysqli_error($conn));
}

// ΓöÇΓöÇ Inventory & Requests ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$category_result  = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory WHERE is_archived = 0 ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY item_name ASC");
$uid_safe = mysqli_real_escape_string($conn, $_SESSION['faculty_id']);

// ΓöÇΓöÇ Stats ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$stat_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe'"))['c'];
$stat_waiting  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Waiting'"))['c'];
$stat_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Approved'"))['c'];
$stat_declined = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Declined'"))['c'];
$stat_overdue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Overdue'"))['c'];

// ΓöÇΓöÇ Requests JSON for JS ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$requests_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid_safe' ORDER BY request_date DESC");
$requests_js = [];
while ($row = mysqli_fetch_assoc($requests_raw)) $requests_js[] = $row;
$requests_json = json_encode($requests_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// ΓöÇΓöÇ Overdue for notifications ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$overdue_items_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid_safe' AND status='Overdue' ORDER BY return_date ASC");
$overdue_notifs = [];
while ($row = mysqli_fetch_assoc($overdue_items_raw)) $overdue_notifs[] = $row;

// ΓöÇΓöÇ Avatar initials ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$name_parts = explode(' ', trim($fullname));
$firstname  = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

// ΓöÇΓöÇ Profile ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
$profile_row = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT email, backup_email, profile_picture, dob, gender, nationality, 
     program, year_level, phone, present_address, permanent_address, landline,
     emergency_name, emergency_relationship, emergency_phone 
     FROM tbl_users WHERE student_id='$uid_safe' LIMIT 1"
)) ?: [];
$db_email         = $profile_row['email']         ?? '';
$db_backup_email  = $profile_row['backup_email']  ?? '';
$db_profile_pic   = $profile_row['profile_picture'] ?? '';
$db_dob           = $profile_row['dob']           ?? '';
$db_gender        = $profile_row['gender']        ?? '';
$db_nationality   = $profile_row['nationality']   ?? '';
// Academic
$db_program       = $profile_row['program']       ?? '';
$db_year_level    = $profile_row['year_level']    ?? '';
// Contact
$db_phone            = $profile_row['phone']            ?? '';
$db_present_address  = $profile_row['present_address']  ?? '';
$db_email             = $profile_row['email']             ?? '';
$db_backup_email      = $profile_row['backup_email']      ?? '';
$db_profile_pic       = $profile_row['profile_picture']   ?? '';
$db_program           = $profile_row['program']           ?? '';
$db_year_level        = $profile_row['year_level']        ?? '';
$db_phone             = $profile_row['phone']             ?? '';
$db_present_address   = $profile_row['present_address']   ?? '';
$db_permanent_address = $profile_row['permanent_address'] ?? '';
$db_landline         = $profile_row['landline']         ?? '';
// Emergency
$db_emergency_name   = $profile_row['emergency_name']        ?? '';
$db_emergency_rel    = $profile_row['emergency_relationship'] ?? '';
$db_emergency_phone  = $profile_row['emergency_phone']       ?? '';

$masked_email     = maskEmail($db_email);
$masked_backup    = maskEmail($db_backup_email);
$dob_display      = $db_dob ? date('F j, Y', strtotime($db_dob)) : '';
// Locked = value already exists in DB (one-time fields)
$dob_locked         = !empty($db_dob);
$gender_locked      = !empty($db_gender);
$nationality_locked = !empty($db_nationality);
$backup_locked      = !empty($db_backup_email);
$program_locked     = !empty($db_program);
// Profile picture path
$profile_pic_url = !empty($db_profile_pic) ? 'uploads/profile_pictures/' . $db_profile_pic : '';
$db_landline          = $profile_row['landline']          ?? '';
$masked_email         = maskEmail($db_email);
$masked_backup        = maskEmail($db_backup_email);
$backup_locked        = !empty($db_backup_email);
$program_locked       = !empty($db_program);
$profile_pic_url      = !empty($db_profile_pic) ? 'uploads/profile_pictures/' . $db_profile_pic : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Sync | User Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>PUPSync | Faculty Dashboard</title>
    <!-- Google Fonts: Hanken Grotesk + Inter (matches new design system) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <!-- Font Awesome (kept for existing icon references in JS) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/user-dashboard.css">
</head>

<body>

    <!-- ================================================================
     SIDE NAVIGATION
================================================================ -->
    <nav class="side-nav" id="sideNav">
        <div class="side-nav-brand">
            <div class="side-nav-logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
            <a class="side-nav-item" id="nav-settings" data-action="open-overlay" data-target="settingsOverlay" href="#">
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
            <div class="top-bar-search" style="position:relative;">
                <span class="material-symbols-outlined">search</span>
                <input type="text" id="globalSearch" placeholder="Search equipment, requests, facilitiesΓÇª" autocomplete="off">
                <div class="live-search-dropdown" id="liveSearchDropdown" style="display:none;"></div>
            </div>
            <div class="top-bar-actions">
                <!-- Notifications -->
                <div class="top-bar-notif-wrap">
                    <button class="top-bar-icon-btn" id="notifBellBtn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if ((3 + count($overdue_notifs)) > 0): ?>
                            <span class="top-bar-badge" id="notifBadgeTop"><?php echo (3 + count($overdue_notifs)); ?></span>
                        <?php endif; ?>
                    </button>
                    <!-- Notification Popover -->
                    <div class="notif-popover" id="notifPopover" role="dialog" aria-label="Notifications">
                        <div class="notif-popover-head">
                            <span>Notifications</span>
                            <button class="notif-mark-read-btn" data-action="mark-all-read">Mark all as read</button>
                        </div>
                        <div class="notif-popover-list">
                            <?php if (!empty($overdue_notifs)): foreach ($overdue_notifs as $on): ?>
                                    <div class="notif-pop-item unread" data-cat="overdue">
                                        <div class="notif-pop-dot notif-dot-error"></div>
                                        <div class="notif-pop-body">
                                            <div class="notif-pop-title">Overdue: <?php echo htmlspecialchars($on['equipment_name']); ?></div>
                                            <div class="notif-pop-sub">Due <?php echo htmlspecialchars($on['return_date']); ?> ΓÇö return immediately</div>
                                            <div class="notif-pop-time">Overdue</div>
                                        </div>
                                    </div>
                            <?php endforeach;
                            endif; ?>
                            <div class="notif-pop-item unread" data-cat="borrow">
                                <div class="notif-pop-dot notif-dot-primary"></div>
                                <div class="notif-pop-body">
                                    <div class="notif-pop-title">Borrow Request Approved</div>
                                    <div class="notif-pop-sub">Pick up at Admin Office before 5:00 PM</div>
                                    <div class="notif-pop-time">9:42 AM</div>
                                </div>
                            </div>
                            <div class="notif-pop-item unread" data-cat="system">
                                <div class="notif-pop-dot notif-dot-secondary"></div>
                                <div class="notif-pop-body">
                                    <div class="notif-pop-title">System Maintenance Tonight</div>
                                    <div class="notif-pop-sub">PUPSYNC offline 11 PM ΓÇô 1 AM</div>
                                    <div class="notif-pop-time">8:00 AM</div>
                                </div>
                            </div>
                        </div>
                        <button class="notif-popover-footer" data-action="open-overlay" data-target="notifOverlay">View all notifications</button>
                    </div>
                </div>

                <div class="top-bar-divider"></div>

                <!-- Profile -->
                <div class="top-bar-profile-wrap">
                    <button class="top-bar-avatar" id="avatarBtn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
                        <?php if ($profile_pic_url): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                        <?php endif; ?>
                    </button>
                    <!-- Profile Dropdown -->
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
                                <span class="dd-sub">Faculty</span>
                                <span class="dd-sub">ID: <?php echo htmlspecialchars($_SESSION['faculty_id']); ?></span>
                            </div>
                        </div>
                        <div class="dd-menu">
                            <button class="dd-item" data-action="open-overlay" data-target="notifOverlay">
                                <span class="material-symbols-outlined dd-item-icon">notifications</span> Notifications
                                <span class="notif-badge" id="notifBadge"><?php echo (3 + count($overdue_notifs)); ?></span>
                            </button>
                            <div class="dd-divider"></div>
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
            <div class="alert-banner alert-danger hidden" id="overdue-alert">
                <span class="material-symbols-outlined">warning</span>
                <strong>Overdue Alert:</strong> You have overdue equipment ΓÇö please return it immediately!
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
                    <h1 class="page-title">Good <?php
                                                $h = (int)date('H');
                                                echo $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening');
                                                ?>, <?php echo htmlspecialchars($firstname); ?>.</h1>
                    <p class="page-subtitle"><?php echo date('l, F j, Y'); ?> &mdash; Here is an overview of your active equipment and requests.</p>
                </div>

                <!-- Overdue Urgent Card (shown only when overdue > 0) -->
                <?php if ($stat_overdue > 0): ?>
                    <div class="urgent-card">
                        <div class="urgent-card-icon">
                            <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">warning</span>
                        </div>
                        <div class="urgent-card-body">
                            <h3>Overdue Equipment Return</h3>
                            <p>You have <strong><?php echo $stat_overdue; ?></strong> overdue item<?php echo $stat_overdue > 1 ? 's' : ''; ?>. Please return <?php echo $stat_overdue > 1 ? 'them' : 'it'; ?> to the Admin Office as soon as possible to avoid late penalties.</p>
                            <div class="urgent-card-actions">
                                <button class="btn-urgent-primary" data-action="filter-requests" data-status="Overdue">View Overdue Items</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats + Quick Access Grid -->
                <div class="dashboard-grid">

                    <!-- Left: Stats Column -->
                    <div class="dashboard-stats-col">
                        <div class="stat-card">
                            <div class="stat-card-label">Active Borrowings</div>
                            <div class="stat-card-value"><?php echo $stat_approved; ?></div>
                            <div class="stat-card-icon"><span class="material-symbols-outlined">devices</span></div>
                        </div>
                        <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Waiting">
                            <div class="stat-card-label">Pending Requests</div>
                            <div class="stat-card-value" style="color:var(--color-warning)"><?php echo $stat_waiting; ?></div>
                            <div class="stat-card-icon"><span class="material-symbols-outlined">pending</span></div>
                        </div>
                        <?php if ($stat_overdue > 0): ?>
                            <div class="stat-card stat-card-overdue stat-card-clickable" data-action="filter-requests" data-status="Overdue">
                                <div class="stat-card-label">Overdue</div>
                                <div class="stat-card-value" style="color:var(--color-error)" id="statOverdueVal"><?php echo $stat_overdue; ?></div>
                                <div class="stat-card-icon"><span class="material-symbols-outlined">alarm</span></div>
                            </div>
                        <?php else: ?>
                            <div class="stat-card">
                                <div class="stat-card-label">Total Requests</div>
                                <div class="stat-card-value"><?php echo $stat_total; ?></div>
                                <div class="stat-card-icon"><span class="material-symbols-outlined">receipt_long</span></div>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Audit Log -->
                        <div class="audit-card">
                            <div class="audit-card-head">
                                <span class="material-symbols-outlined">history</span>
                                <span>Recent Activity</span>
                            </div>
                            <?php
                            $recent_raw = mysqli_query($conn, "SELECT equipment_name, status, request_date FROM tbl_requests WHERE student_id='$uid_safe' ORDER BY request_date DESC LIMIT 3");
                            if ($recent_raw && mysqli_num_rows($recent_raw) > 0):
                                while ($rr = mysqli_fetch_assoc($recent_raw)):
                            ?>
                                    <div class="audit-row">
                                        <span class="audit-row-label"><?php echo htmlspecialchars($rr['equipment_name']); ?></span>
                                        <span class="audit-row-time"><?php echo date('M j', strtotime($rr['request_date'])); ?></span>
                                    </div>
                                <?php endwhile;
                            else: ?>
                                <div class="audit-row"><span class="audit-row-label" style="color:var(--color-on-surface-variant)">No recent activity</span></div>
                            <?php endif; ?>
                            <a class="audit-view-all" data-tab="activity" href="#">View Full Activity Log</a>
                        </div>
                        <!-- Quick Actions -->
                        <div class="quick-actions">
                            <h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" style="color:var(--accent-maroon); margin-right:8px" aria-label="Quick" aria-hidden="true">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                </svg>Quick Actions</h3>
                            <button class="qa-btn" data-action="go-tab" data-tab="lending" data-lending="browse">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Search" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg> Browse Equipment
                            </button>
                            <button class="qa-btn" data-action="go-tab" data-tab="lending" data-lending="requests">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Requests" aria-hidden="true">
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                                </svg> My Requests
                            </button>
                            <button class="qa-btn" data-action="go-tab" data-tab="rooms">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Rooms" aria-hidden="true">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                    <line x1="9" y1="3" x2="9" y2="21" />
                                    <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                                </svg> Reserve a Room
                            </button>
                            <button class="qa-btn" data-action="open-overlay" data-target="notifOverlay">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Notifications" aria-hidden="true">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                </svg> Notifications <span class="notif-badge" style="font-size:0.7rem; padding: 1px 6px;"><?php echo (3 + count($overdue_notifs)); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Right: Quick Access Bento -->
                    <div class="bento-grid">
                        <div class="bento-item" data-action="go-tab" data-tab="lending" data-lending="browse">
                            <div class="bento-icon"><span class="material-symbols-outlined">add_shopping_cart</span></div>
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
                $active_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid_safe' AND status IN ('Approved','Overdue') ORDER BY return_date ASC LIMIT 4");
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
                                    <div class="active-card-title"><?php echo htmlspecialchars($ai['equipment_name']); ?></div>
                                    <div class="active-card-sub">Room: <?php echo htmlspecialchars($ai['room']); ?></div>
                                    <div class="active-card-footer">
                                        <span class="active-card-due">Due: <?php echo htmlspecialchars($ai['return_date']); ?></span>
                                        <span class="status-chip <?php echo $ai['status'] === 'Overdue' ? 'chip-error' : 'chip-success'; ?>">
                                            <span class="chip-dot"></span><?php echo $ai['status']; ?>
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

                <!-- ΓöÇΓöÇ Sub: Browse ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
                <div class="lending-sub active" id="lending-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Browse Equipment</h2>
                        <p class="page-subtitle">Search and select equipment to submit a borrow request.</p>
                    </div>
                    <div class="catalog-card">
                        <div class="catalog-filters">
                            <div class="catalog-search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="equipmentSearch" placeholder="Search by equipment nameΓÇª">
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
                                        data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img class="eq-item-img" src="/Equipment-Lending-Website/<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                        <?php else: ?>
                                            <div class="eq-item-img-placeholder">
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="eq-item-body">
                                            <div class="eq-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="eq-item-cat">
                                                <span class="material-symbols-outlined" style="font-size:13px;">label</span>
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <?php if ($item['quantity'] > 0): ?>
                                                <span class="stock-badge stock-avail">
                                                    <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                    <?php echo (int)$item['quantity']; ?> available
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-badge stock-unavail">
                                                    <span class="material-symbols-outlined" style="font-size:12px;">cancel</span>
                                                    Out of stock
                                                </span>
                                            <?php endif; ?>
                                            <button class="btn-borrow"
                                                <?php if ($item['quantity'] <= 0) echo 'disabled'; ?>
                                                data-action="open-borrow-form"
                                                data-item="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                <?php echo ($item['quantity'] > 0) ? 'Borrow' : 'Unavailable'; ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- /lending-browse -->

                <!-- ΓöÇΓöÇ Sub: Borrow Form ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
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
                        <form id="borrowForm" method="POST" action="">
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
                            <button type="submit" class="btn-submit-form">
                                <span class="material-symbols-outlined">send</span> Submit Borrow Request
                            </button>
                        </form>
                    </div>
                </div><!-- /lending-form -->

                <!-- ΓöÇΓöÇ Sub: My Requests ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
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
                                    <span class="material-symbols-outlined" style="font-size:16px;color:var(--color-on-surface-variant)">filter_list</span>
                                    <select id="reqStatusFilter" class="req-filter-select" data-action="filter-requests-dd">
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
                    <script>
                        window.REQUESTS_DATA = <?php echo $requests_json; ?>;
                    </script>
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
                        <h3>Room Reservation ΓÇö Coming Soon</h3>
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
                                <span><span class="material-symbols-outlined" style="font-size:14px">wifi</span> WiFi</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">ac_unit</span> A/C</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">videocam</span> Projector</span>
                            </div>
                            <div class="room-card-footer">
                                <span class="room-avail"><span class="room-avail-dot"></span> Available</span>
                                <button class="btn-borrow" style="width:auto;padding:8px 20px;" data-action="room-reserve-preview" data-room="Computer Laboratory 301">Reserve</button>
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
                                <span><span class="material-symbols-outlined" style="font-size:14px">wifi</span> WiFi</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">ac_unit</span> A/C</span>
                            </div>
                            <div class="room-card-footer">
                                <span class="room-avail"><span class="room-avail-dot"></span> Available</span>
                                <button class="btn-borrow" style="width:auto;padding:8px 20px;" data-action="room-reserve-preview" data-room="Science Laboratory">Reserve</button>
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
                                <span><span class="material-symbols-outlined" style="font-size:14px">wifi</span> WiFi</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">ac_unit</span> A/C</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">videocam</span> Projector</span>
                                <span><span class="material-symbols-outlined" style="font-size:14px">mic</span> Sound System</span>
                            </div>
                            <div class="room-card-footer">
                                <span class="room-occupied"><span class="room-occupied-dot"></span> In Use</span>
                                <button class="btn-borrow" style="width:auto;padding:8px 20px;" disabled>Unavailable</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /panel-rooms -->

            <!-- ============================================================
         TAB: MY ACTIVITY (Timeline)
    ============================================================ -->
            <div class="tab-panel" id="panel-activity">
                <div class="page-header-block" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h2 class="page-title-sm">My Activity Tracker</h2>
                        <p class="page-subtitle">Track your current requests, upcoming borrowings, and facility access.</p>
                    </div>
                    <button class="btn-download-report" onclick="window.print()">
                        <span class="material-symbols-outlined">download</span> Download Report
                    </button>
                </div>

                <div class="timeline-container" id="activityTimeline">
                    <?php
                    // Group requests by date proximity
                    $all_req = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid_safe' ORDER BY borrow_date DESC, request_date DESC LIMIT 20");
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
                            <button class="btn-borrow" style="width:auto;padding:10px 24px;margin-top:12px;" data-action="go-tab" data-tab="lending" data-lending="browse">Browse Equipment</button>
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
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                    <span class="timeline-date-chip"><?php echo date('M j', $d); ?></span>
                                </div>
                                <div class="timeline-items">
                                    <?php foreach ($items as $ti):
                                        $isActive = in_array($ti['status'], ['Approved', 'Overdue']);
                                        $isOverdue = $ti['status'] === 'Overdue';
                                        $isPending = $ti['status'] === 'Waiting';
                                    ?>
                                        <div class="timeline-card <?php echo $isOverdue ? 'timeline-card-overdue' : ($isActive ? 'timeline-card-active' : ''); ?>">
                                            <div class="timeline-indicator <?php echo $isOverdue ? 'ti-error' : ($isActive ? 'ti-primary' : ($isPending ? 'ti-warning' : 'ti-muted')); ?>">
                                                <span class="material-symbols-outlined" style="font-size:16px;font-variation-settings:'FILL' 1">
                                                    <?php echo $isOverdue ? 'alarm' : ($isActive ? 'inventory_2' : ($isPending ? 'hourglass_empty' : 'check_circle')); ?>
                                                </span>
                                            </div>
                                            <div class="timeline-card-content">
                                                <div class="timeline-card-top">
                                                    <div>
                                                        <h3 class="timeline-card-title"><?php echo htmlspecialchars($ti['equipment_name']); ?></h3>
                                                        <p class="timeline-card-sub">
                                                            <span class="material-symbols-outlined" style="font-size:14px">schedule</span>
                                                            <?php echo htmlspecialchars($ti['borrow_date']); ?> &rarr; <?php echo htmlspecialchars($ti['return_date']); ?>
                                                            <?php if ($isActive): ?>
                                                                <span class="timeline-time-left" id="timeleft-<?php echo $ti['id']; ?>"></span>
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
                                                        <span class="chip-dot"></span><?php echo htmlspecialchars($ti['status']); ?>
                                                    </span>
                                                </div>
                                                <p class="timeline-card-detail">Room: <?php echo htmlspecialchars($ti['room']); ?></p>
                                                <?php if ($ti['status'] === 'Declined' && !empty($ti['reason'])): ?>
                                                    <p class="timeline-card-reason">Reason: <?php echo htmlspecialchars($ti['reason']); ?></p>
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
     OVERLAY: ACCOUNT ΓÇö merged into settingsOverlay below
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
                        <span class="section-eyebrow">My Account ΓÇ║ Overview</span>
                        <h2>Profile &amp; Identity</h2>
                        <p>Your personal details and login information.</p>
                    </div>
                    <div class="account-hero-card">
                        <div class="acc-avatar-section">
                            <div class="acc-avatar-large" id="profileAvatarLarge">
                                <?php if ($profile_pic_url): ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                                <?php else: ?>
                                    <?php echo htmlspecialchars($initials); ?>
                                <?php endif; ?>
                            </div>
                            <div style="position:relative;">
                                <button class="btn-change-profile" id="changeProfileBtn" data-action="open-picture-menu">Change Photo</button>
                                <div class="picture-menu" id="pictureMenu" style="display:none;">
                                    <button class="pic-menu-item" data-action="upload-picture">
                                        <span class="material-symbols-outlined" style="font-size:15px;margin-right:8px;">upload</span>Upload Photo
                                    </button>
                                    <?php if ($profile_pic_url): ?>
                                        <button class="pic-menu-item pic-menu-danger" data-action="remove-picture">
                                            <span class="material-symbols-outlined" style="font-size:15px;margin-right:8px;">delete</span>Remove Photo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="file" id="profilePicInput" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none;">
                        </div>
                        <div class="acc-hero-info">
                            <h2><?php echo htmlspecialchars($fullname); ?></h2>
                            <p>ID: <?php echo htmlspecialchars($_SESSION['faculty_id']); ?></p>
                            <span class="acc-badge">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px;vertical-align:middle;"></span>
                                Active Faculty
                            </span>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">Edit Profile</button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;" data-action="profile-save">
                                <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Save
                            </button>
                            <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;" data-action="profile-cancel">Cancel</button>
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
                            <span class="info-lbl">Faculty ID</span>
                            <span class="info-val"><?php echo htmlspecialchars($_SESSION['faculty_id']); ?></span>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Login &amp; Security</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Primary Email</span>
                            <span class="info-val <?php echo $masked_email ? '' : 'empty'; ?>"><?php echo $masked_email ?: 'ΓÇö Not provided'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Backup Email</span>
                            <span class="info-val <?php echo $masked_backup ? '' : 'empty'; ?>" data-field="backup_email"><?php echo $masked_backup ?: 'ΓÇö Not provided'; ?></span>
                            <?php if (!$backup_locked): ?>
                                <button class="btn-inline-action" data-action="open-backup-email-modal">Add</button>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Password</span>
                            <span class="info-val">ΓÇóΓÇóΓÇóΓÇóΓÇóΓÇóΓÇóΓÇóΓÇóΓÇó</span>
                            <button class="btn-inline-action" data-action="open-email-verify-modal">Change</button>
                        </div>
                    </div>
                </div><!-- /acc-overview -->

                <!-- Department -->
                <div id="acc-academic" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ΓÇ║ Department</span>
                        <h2>Department Information</h2>
                        <p>Your faculty department and assignment details.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Department Assignment</h3>
                            <button class="btn-edit-acc" id="editAcademicBtn" data-action="academic-edit" style="display:inline-flex;">Edit</button>
                            <button class="btn-save-acc" id="saveAcademicBtn" style="display:none;" data-action="academic-save">Save Changes</button>
                            <button class="btn-cancel-acc" id="cancelAcademicBtn" style="display:none;" data-action="academic-cancel">Cancel</button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Faculty ID</span>
                            <span class="info-val"><?php echo htmlspecialchars($_SESSION['faculty_id']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val"><?php echo htmlspecialchars($fullname); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Department</span>
                            <span class="info-val <?php echo $program_locked ? '' : 'empty'; ?>" data-field="program"><?php echo $program_locked ? htmlspecialchars($db_program) : 'ΓÇö Not provided'; ?></span>
                            <?php if (!$program_locked): ?>
                                <select class="info-input-f" data-input="program" disabled style="display:none;">
                                    <option value="">Select Program...</option>
                                    <?php foreach (['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'] as $p): ?>
                                        <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Position / Rank</span>
                            <span class="info-val <?php echo $db_year_level ? '' : 'empty'; ?>" data-field="year_level"><?php echo $db_year_level ? htmlspecialchars($db_year_level) : 'ΓÇö Not provided'; ?></span>
                            <select class="info-input-f" data-input="year_level" disabled style="display:none;">
                                <option value="">Select Position...</option>
                                <?php foreach (['Instructor I', 'Instructor II', 'Instructor III', 'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Associate Professor I', 'Associate Professor II', 'Professor I', 'Professor II', 'Part-time Faculty'] as $rank): ?>
                                    <option value="<?php echo $rank; ?>"><?php echo $rank; ?></option>
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
                        <span class="section-eyebrow">My Account ΓÇ║ Contact</span>
                        <h2>Contact Details</h2>
                        <p>How we can reach you.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Address</h3>
                            <button class="btn-edit-acc" id="editContactBtn" data-action="contact-edit" style="display:inline-flex;">Edit</button>
                            <button class="btn-save-acc" id="saveContactBtn" style="display:none;" data-action="contact-save">Save Changes</button>
                            <button class="btn-cancel-acc" id="cancelContactBtn" style="display:none;" data-action="contact-cancel">Cancel</button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Present Address</span>
                            <span class="info-val <?php echo $db_present_address ? '' : 'empty'; ?>" data-field="present_address"><?php echo $db_present_address ? htmlspecialchars($db_present_address) : 'ΓÇö Not provided'; ?></span>
                            <textarea class="info-input-f" data-input="present_address" placeholder="Enter your current address" disabled style="display:none;min-height:60px;"></textarea>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Permanent Address</span>
                            <span class="info-val <?php echo $db_permanent_address ? '' : 'empty'; ?>" data-field="permanent_address"><?php echo $db_permanent_address ? htmlspecialchars($db_permanent_address) : 'ΓÇö Not provided'; ?></span>
                            <textarea class="info-input-f" data-input="permanent_address" placeholder="Enter your permanent address" disabled style="display:none;min-height:60px;"></textarea>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Phone</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Mobile Number</span>
                            <span class="info-val <?php echo $db_phone ? '' : 'empty'; ?>" data-field="phone"><?php echo $db_phone ? htmlspecialchars($db_phone) : 'ΓÇö Not provided'; ?></span>
                            <input class="info-input-f" data-input="phone" placeholder="e.g. +63 912 345 6789" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Landline</span>
                            <span class="info-val <?php echo $db_landline ? '' : 'empty'; ?>" data-field="landline"><?php echo $db_landline ? htmlspecialchars($db_landline) : 'ΓÇö Not provided'; ?></span>
                            <input class="info-input-f" data-input="landline" placeholder="e.g. (02) 1234-5678" disabled style="display:none;">
                        </div>
                    </div>
                </div><!-- /acc-contact -->

            </div>
        </div>
    </div><!-- /accountOverlay -->

    <!-- ================================================================
     OVERLAY: SETTINGS (unified ΓÇö Account + System Settings)
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">
        <div class="overlay-topbar">
            <span class="overlay-topbar-title">Account &amp; System Settings</span>
        </div>

        <div class="unified-settings-wrap">

            <!-- Page Header -->
            <div class="unified-settings-header">
                <h1>Account &amp; System Settings</h1>
                <p>Manage your profile, preferences, and security settings.</p>
            </div>

            <div class="unified-settings-grid">

                <!-- ΓöÇΓöÇ Profile Information Card (full-width) ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
                <div class="u-card u-card-full">
                    <div class="u-card-head">
                        <h2>Profile Information</h2>
                    </div>

                    <!-- Profile photo row -->
                    <div class="u-profile-photo-row">
                        <div class="acc-avatar-large" id="profileAvatarLarge" style="width:80px;height:80px;font-size:1.6rem;">
                            <?php if ($profile_pic_url): ?>
                                <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                            <?php else: ?>
                                <?php echo htmlspecialchars($initials); ?>
                            <?php endif; ?>
                        </div>
                        <div style="position:relative;">
                            <button class="btn-change-profile" id="changeProfileBtn" data-action="open-picture-menu">Change Photo</button>
                            <div class="picture-menu" id="pictureMenu" style="display:none;">
                                <button class="pic-menu-item" data-action="upload-picture">
                                    <span class="material-symbols-outlined" style="font-size:15px;margin-right:8px;">upload</span>Upload Photo
                                </button>
                                <?php if ($profile_pic_url): ?>
                                    <button class="pic-menu-item pic-menu-danger" data-action="remove-picture">
                                        <span class="material-symbols-outlined" style="font-size:15px;margin-right:8px;">delete</span>Remove Photo
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="file" id="profilePicInput" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none;">
                    </div>

                    <div class="u-form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <span class="info-val" data-field="fullname" id="profileNameDisplay"><?php echo htmlspecialchars($fullname); ?></span>
                            <input class="form-input info-input-f" data-input="fullname" value="<?php echo htmlspecialchars($fullname); ?>" disabled style="display:none;" placeholder="Your full name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Faculty ID</label>
                            <input class="form-input" type="text" value="<?php echo htmlspecialchars($_SESSION['faculty_id']); ?>" readonly style="background:var(--color-surface-container);color:var(--color-secondary);cursor:not-allowed;">
                        </div>
                        <div class="form-group u-form-col-span">
                            <label class="form-label">Department / College</label>
                            <?php if ($program_locked): ?>
                                <input class="form-input" type="text" value="<?php echo htmlspecialchars($db_program); ?>" readonly style="background:var(--color-surface-container);color:var(--color-secondary);cursor:not-allowed;">
                            <?php else: ?>
                                <span class="info-val <?php echo $program_locked ? '' : 'empty'; ?>" data-field="program"><?php echo $program_locked ? htmlspecialchars($db_program) : 'ΓÇö Not provided'; ?></span>
                                <select class="form-input info-input-f" data-input="program" disabled style="display:none;">
                                    <option value="">Select Department...</option>
                                    <?php foreach (['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'] as $p): ?>
                                        <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="u-card-actions">
                        <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">Edit Profile</button>
                        <button class="btn-save-acc" id="saveProfileBtn" style="display:none;" data-action="profile-save">
                            <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Update Profile
                        </button>
                        <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;" data-action="profile-cancel">Cancel</button>
                    </div>
                </div>

                <!-- ΓöÇΓöÇ Appearance & Accessibility Card ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
                <div class="u-card">
                    <div class="u-card-head">
                        <h2>Appearance &amp; Accessibility</h2>
                    </div>

                    <!-- Theme -->
                    <div class="u-field-group">
                        <div class="u-field-label">
                            <h4>Theme</h4>
                            <p>Choose how PUPSync looks to you.</p>
                        </div>
                        <select class="form-input" id="themeSelectUnified" style="appearance:none;background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%235e5e67%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E');background-repeat:no-repeat;background-position:right 0.75rem center;background-size:1.2em;padding-right:2.5rem;">
                            <option value="light" id="themeOptLight">Light (Default)</option>
                            <option value="dark" id="themeOptDark">Dark</option>
                            <option value="high-contrast" id="themeOptHC">High Contrast</option>
                        </select>
                        <!-- Hidden theme opt divs kept for JS compatibility -->
                        <div style="display:none;">
                            <div id="tp-light" data-action="apply-theme" data-theme="light"></div>
                            <div id="tp-dark" data-action="apply-theme" data-theme="dark"></div>
                            <div id="tp-hc" data-action="apply-theme" data-theme="high-contrast"></div>
                            <svg id="tc-light" style="display:none;">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <svg id="tc-dark" style="display:none;">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <svg id="tc-hc" style="display:none;">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </div>
                    </div>

                    <!-- Font Scaling (3-button toggle) -->
                    <div class="u-field-group" style="margin-top:20px;">
                        <div class="u-field-label">
                            <h4>Font Scaling</h4>
                            <p>Adjust the text size for readability. <span id="fontSizeLbl" style="color:var(--color-primary);font-weight:600;"></span></p>
                        </div>
                        <div class="u-font-toggle" id="fontScaleToggle">
                            <button class="u-font-btn" data-scale="80">Small</button>
                            <button class="u-font-btn u-font-btn-active" data-scale="100">Standard</button>
                            <button class="u-font-btn" data-scale="120">Large</button>
                        </div>
                        <!-- Hidden range kept for JS compatibility -->
                        <input type="range" min="80" max="130" value="100" step="5" id="fontSizeRange" style="display:none;">
                    </div>

                    <!-- Hidden toggles kept for JS restore compatibility -->
                    <!-- <label class="toggle-sw"><input type="checkbox" id="compactToggle"><span class="toggle-track"></span></label> -->
                    <!-- <label class="toggle-sw"><input type="checkbox" id="reduceMotionToggle"><span class="toggle-track"></span></label> -->
                    <!-- <label class="toggle-sw"><input type="checkbox" id="focusRingToggle"><span class="toggle-track"></span></label> -->
                </div>

                <!-- ΓöÇΓöÇ Notification Preferences Card ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
                <div class="u-card">
                    <div class="u-card-head">
                        <h2>Notification Preferences</h2>
                    </div>

                    <div class="u-notif-row u-notif-row-bordered">
                        <div class="u-notif-label">
                            <h4>Email Alerts for Overdue Items</h4>
                            <p>Receive daily summaries of late equipment returns.</p>
                        </div>
                        <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                    </div>
                    <div class="u-notif-row u-notif-row-bordered">
                        <div class="u-notif-label">
                            <h4>Upcoming Reservation Reminders</h4>
                            <p>Get notified 24h before a facility booking.</p>
                        </div>
                        <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                    </div>
                    <div class="u-notif-row">
                        <div class="u-notif-label">
                            <h4>Account Activity Alerts</h4>
                            <p>Get notified about new logins and security-related changes.</p>
                        </div>
                        <label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
                    </div>
                </div>

                <!-- ΓöÇΓöÇ Privacy & Security Card ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ -->
                <div class="u-card">
                    <div class="u-card-head">
                        <h2>Privacy &amp; Security</h2>
                    </div>

                    <!-- Change Password -->
                    <button class="u-security-row" data-action="open-email-verify-modal">
                        <div class="u-security-row-text">
                            <span class="u-security-row-title">Change Password</span>
                            <span class="u-security-row-sub">Verify your email to get started</span>
                        </div>
                        <span class="material-symbols-outlined" style="color:var(--color-secondary);font-size:20px;">chevron_right</span>
                    </button>

                    <!-- Backup Email -->
                    <div class="u-security-row" style="cursor:default;">
                        <div class="u-security-row-text">
                            <span class="u-security-row-title">Backup Email</span>
                            <span class="u-security-row-sub"><?php echo $masked_backup ?: 'ΓÇö Not provided'; ?></span>
                        </div>
                        <?php if (!$backup_locked): ?>
                            <button class="btn-inline-action" data-action="open-backup-email-modal">Add</button>
                        <?php endif; ?>
                    </div>

                    <!-- 2FA -->
                    <div class="u-security-row" style="cursor:default;">
                        <div class="u-security-row-text">
                            <span class="u-security-row-title">
                                Two-Factor Authentication
                                <span style="display:inline-block;margin-left:8px;padding:2px 8px;background:var(--color-primary);color:var(--color-on-primary);border-radius:4px;font-size:10px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;vertical-align:middle;">Enabled</span>
                            </span>
                            <span class="u-security-row-sub">Secured via Authenticator App</span>
                        </div>
                        <button class="btn-inline-action" data-action="toast" data-msg="2FA management coming soon!">Manage</button>
                    </div>

                    <!-- Primary Email (read-only info) -->
                    <div class="u-security-row" style="cursor:default;">
                        <div class="u-security-row-text">
                            <span class="u-security-row-title">Primary Email</span>
                            <span class="u-security-row-sub <?php echo $masked_email ? '' : 'empty'; ?>"><?php echo $masked_email ?: 'ΓÇö Not provided'; ?></span>
                        </div>
                    </div>

                    <!-- Login & Sessions info -->
                    <!-- Remember Me / session management not applicable in current design ΓÇö commented out -->
                    <!-- <div class="s-row"><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label></div> -->
                </div>

            </div><!-- /unified-settings-grid -->
        </div><!-- /unified-settings-wrap -->
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
        <div class="notif-wrapper">
            <div class="notif-header-row">
                <div class="overlay-section-header" style="margin-bottom:0;flex:1;">
                    <span class="section-eyebrow">Inbox ΓÇ║ All Notifications</span>
                    <h2>Notifications</h2>
                    <p>You have <strong id="unreadCount"><?php echo (3 + count($overdue_notifs)); ?> unread</strong> notifications.</p>
                </div>
                <button class="mark-read-btn" data-action="mark-all-read">Mark all as read</button>
            </div>
            <div class="notif-filter-tabs">
                <button class="notif-tab active" data-notif-filter="all">All</button>
                <button class="notif-tab" data-notif-filter="unread">Unread</button>
                <button class="notif-tab" data-notif-filter="borrow">Borrow</button>
                <button class="notif-tab" data-notif-filter="overdue">Overdue</button>
                <button class="notif-tab" data-notif-filter="system">System</button>
            </div>
            <?php if (!empty($overdue_notifs)): ?>
                <div class="notif-group overdue-notif-group">ΓÜá∩╕Å Overdue ΓÇö Action Required</div>
                <?php foreach ($overdue_notifs as $on): ?>
                    <div class="notif-item unread notif-overdue" data-cat="overdue">
                        <div class="notif-icon ni-overdue"><span class="material-symbols-outlined" style="font-size:16px">alarm</span></div>
                        <div class="notif-body-wrap">
                            <h4>Overdue Item: <?php echo htmlspecialchars($on['equipment_name']); ?></h4>
                            <p>Due on <strong><?php echo htmlspecialchars($on['return_date']); ?></strong>. Return immediately to avoid penalties.</p>
                        </div>
                        <div class="notif-meta"><span class="notif-time">Overdue since <?php echo htmlspecialchars($on['return_date']); ?></span>
                            <div class="unread-dot"></div>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
            <div class="notif-group">Today</div>
            <div class="notif-item unread" data-cat="borrow">
                <div class="notif-icon ni-success"><span class="material-symbols-outlined" style="font-size:16px">check_circle</span></div>
                <div class="notif-body-wrap">
                    <h4>Borrow Request Approved</h4>
                    <p>Your latest borrow request has been approved. Pick up at the Admin Office before 5:00 PM.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">9:42 AM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-item unread" data-cat="system">
                <div class="notif-icon ni-alert"><span class="material-symbols-outlined" style="font-size:16px">settings</span></div>
                <div class="notif-body-wrap">
                    <h4>System Maintenance Tonight</h4>
                    <p>PUPSYNC will undergo scheduled maintenance from 11:00 PM to 1:00 AM.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">8:00 AM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-group">Yesterday</div>
            <div class="notif-item unread" data-cat="borrow">
                <div class="notif-icon ni-warn"><span class="material-symbols-outlined" style="font-size:16px">warning</span></div>
                <div class="notif-body-wrap">
                    <h4>Return Reminder</h4>
                    <p>You have a borrowed item due in 1 day. Please return it on time to avoid penalties.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">Yesterday, 4:15 PM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-item" data-cat="borrow">
                <div class="notif-icon ni-success"><span class="material-symbols-outlined" style="font-size:16px">inventory_2</span></div>
                <div class="notif-body-wrap">
                    <h4>Request Submitted</h4>
                    <p>Your borrow request was successfully submitted and is under review.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">Yesterday, 2:00 PM</span></div>
            </div>
        </div>
    </div><!-- /notifOverlay -->

    <!-- ================================================================
     OVERLAY: HELP CENTER
================================================================ -->
    <div class="overlay-page" id="helpOverlay">
        <div class="overlay-topbar">
            <button class="overlay-back-btn" data-action="close-overlay" data-target="helpOverlay">
                <span class="material-symbols-outlined">arrow_back</span> Back
            </button>
            <span class="overlay-topbar-title">Help Center</span>
            <div class="overlay-topbar-brand"><strong>PUP</strong>SYNC</div>
        </div>
        <div class="notif-wrapper">
            <div class="overlay-section-header">
                <span class="section-eyebrow">Support ΓÇ║ Help Center</span>
                <h2>How can we help?</h2>
                <p>Browse common topics or contact the system administrator for further assistance.</p>
            </div>

            <!-- FAQ Items -->
            <div style="display:flex;flex-direction:column;gap:12px;margin-top:8px;">

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I borrow equipment?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Go to the <strong>Equipment</strong> tab on the sidebar, browse the catalog, and click <em>Borrow</em> on any available item. Fill in the borrow date, return date, room, and instructor, then submit. Your request will be reviewed by the admin.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I return a borrowed item?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Physically return the borrowed item to the Admin Office. The administrator will then confirm and mark your item as returned in the system. You can track the status update in <strong>Equipment &mdash; My Requests</strong> or the <strong>My Activity</strong> tab.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        Why is my request showing as Overdue?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Your item's return date has passed without it being marked as returned. Please return the item to the Admin Office immediately. Contact the system administrator if you believe this is an error.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I reserve a facility or room?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Go to the <strong>Facilities</strong> tab on the sidebar. Browse available rooms, check their availability, and submit a reservation request. Approvals are handled by the facilities coordinator.
                    </div>
                </details>

                <details class="help-item">
                    <summary class="help-item-q">
                        <span class="material-symbols-outlined">help_outline</span>
                        How do I update my profile or change my password?
                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                    </summary>
                    <div class="help-item-a">
                        Click your profile avatar in the top-right corner and select <em>View Profile</em>. From there you can update your contact details, profile picture, and change your password securely.
                    </div>
                </details>

            </div>

            <!-- Contact Block -->
            <div class="help-contact-card" style="margin-top:28px;">
                <span class="material-symbols-outlined" style="font-size:32px;color:var(--color-primary);margin-bottom:8px;">support_agent</span>
                <h4>Still need help?</h4>
                <p>Contact the PUPSync system administrator for technical issues or escalations.</p>
                <a href="mailto:admin@pupsync.edu.ph" class="btn-urgent-primary" style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;margin-top:12px;">
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
                <h3><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:8px;">task_alt</span>Confirm Changes</h3>
                <button class="modal-close-btn" data-action="close-confirmation-modal" aria-label="Close"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Please review the changes you're about to make:</p>
                <div class="changes-summary" id="changesSummary"></div>
                <div class="warning-box">
                    <span class="material-symbols-outlined" style="color:#856404;flex-shrink:0;">warning</span>
                    <div><strong style="color:#856404;display:block;margin-bottom:4px;">Important Notice</strong>
                        <p style="color:#856404;margin:0;font-size:0.875rem;" id="warningMessage">Some changes cannot be reversed once saved.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-confirmation-modal">Cancel</button>
                <button class="btn-save-acc" id="confirmChangesBtn" data-action="confirm-changes">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Confirm &amp; Save
                </button>
            </div>
        </div>
    </div>

    <!-- Email Verify Modal -->
    <div class="modal-backdrop" id="emailVerifyModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:8px;">mail</span>Verify Your Email</h3>
                <button class="modal-close-btn" data-action="close-email-verify-modal" aria-label="Close"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">For security, verify your email address before changing your password.</p>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="verifyEmailInput" class="form-input" placeholder="Enter your registered email" autocomplete="email">
                </div>
                <p class="modal-error" id="emailVerifyError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-email-verify-modal">Cancel</button>
                <button class="btn-save-acc" id="emailVerifyBtn" data-action="submit-email-verify">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Verify &amp; Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Backup Email Modal -->
    <div class="modal-backdrop" id="backupEmailModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:8px;">alternate_email</span>Backup Email</h3>
                <button class="modal-close-btn" data-action="close-backup-email-modal" aria-label="Close"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Add a backup email for account recovery and important notifications.</p>
                <div class="form-group">
                    <label class="form-label">Backup Email Address</label>
                    <input type="email" id="backupEmailInput" class="form-input" placeholder="Enter backup email" autocomplete="email" value="<?php echo htmlspecialchars($db_backup_email); ?>">
                </div>
                <p class="modal-error" id="backupEmailError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-backup-email-modal">Cancel</button>
                <button class="btn-save-acc" id="backupEmailSaveBtn" data-action="save-backup-email">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Save Backup Email
                </button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal-backdrop" id="pwModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:8px;">lock</span>Change Password</h3>
                <button class="modal-close-btn" data-action="close-pw-modal" aria-label="Close"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwCurrent" class="form-input" placeholder="Enter your current password" autocomplete="current-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwCurrent" aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwNew" class="form-input" placeholder="At least 6 characters" autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwNew" aria-label="Toggle visibility">
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
                        <input type="password" id="pwConfirm" class="form-input" placeholder="Repeat your new password" autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwConfirm" aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <p class="modal-error" id="pwModalError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-pw-modal">Cancel</button>
                <button class="btn-save-acc" id="pwSubmitBtn" data-action="submit-pw-change">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Update Password
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Config for JS -->
    <script>
        window.USER_PROFILE_LOCKS = {
            dob: <?php echo $dob_locked         ? 'true' : 'false'; ?>,
            gender: <?php echo $gender_locked      ? 'true' : 'false'; ?>,
            nationality: <?php echo $nationality_locked ? 'true' : 'false'; ?>
        };
    </script>

    <!-- Loading Overlay -->
    <div id="loading-overlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;">
        <div style="background:var(--color-surface);border-radius:16px;padding:2rem 2.5rem;display:flex;flex-direction:column;align-items:center;gap:12px;">
            <div class="spinner"></div>
            <p style="font-weight:600;color:var(--color-on-surface);font-size:0.9rem;">Processing your requestΓÇª</p>
        </div>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>

    <script>
        window.REQUESTS_DATA = <?php echo $requests_json; ?>;
        window.USER_SLUG = '<?php echo $user_slug; ?>';
        window.OVERDUE_COUNT = <?php echo (int)$stat_overdue; ?>;
    </script>
    <script src="JS/user-dashboard.js"></script>
</body>

</html>