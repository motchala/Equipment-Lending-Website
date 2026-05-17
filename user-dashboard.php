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

// ── Email masking helper ────────────────────────────────────────────────────
function maskEmail($email)
{
    if (!$email) return null;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return htmlspecialchars($email);
    $local  = $parts[0];
    $domain = $parts[1];
    // Always show first 4 chars + fixed "***" + @ + full domain
    $visible = htmlspecialchars(mb_substr($local, 0, 4));
    return $visible . '***@' . htmlspecialchars($domain);
}

// ── Handle Return Item (AJAX) ──────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'return_item') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
        exit;
    }
    $req_id = intval($_POST['request_id'] ?? 0);
    $uid_r  = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    // Fetch the request (verify ownership)
    $rq = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tbl_requests WHERE id=$req_id AND student_id='$uid_r' LIMIT 1"));
    if (!$rq) {
        echo json_encode(['success' => false, 'msg' => 'Request not found']);
        exit;
    }
    if (!in_array($rq['status'], ['Approved', 'Overdue'])) {
        echo json_encode(['success' => false, 'msg' => 'Cannot return this item']);
        exit;
    }
    // Mark as Returned
    mysqli_query($conn, "UPDATE tbl_requests SET status='Returned' WHERE id=$req_id");
    // Increment inventory quantity
    $eq_name = mysqli_real_escape_string($conn, $rq['equipment_name']);
    mysqli_query($conn, "UPDATE tbl_inventory SET quantity = quantity + 1 WHERE item_name='$eq_name' LIMIT 1");
    echo json_encode(['success' => true, 'msg' => 'Item returned successfully!']);
    exit;
}

// ── Auto-decline expired requests ──────────────────────────────────────────
$today = date('Y-m-d');
$reason_expired = "Request expired – borrow date has already passed";
$stmt_expired = $conn->prepare("UPDATE tbl_requests SET status='Declined', reason=? WHERE status='Waiting' AND borrow_date < ?");
$stmt_expired->bind_param("ss", $reason_expired, $today);
$stmt_expired->execute();

// ── Auto-mark overdue approved requests ────────────────────────────────────
mysqli_query($conn, "UPDATE tbl_requests SET status='Overdue' WHERE status='Approved' AND return_date < '$today'");

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
// only show non-archived rows to regular users so they cannot borrow retired items
$category_result  = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory WHERE is_archived = 0 ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY item_name ASC");
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
$stat_overdue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE student_id='$uid_safe' AND status='Overdue'"))['c'];

// Build requests array for JS
$requests_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid_safe' ORDER BY request_date DESC");
$requests_js = [];
while ($row = mysqli_fetch_assoc($requests_raw)) {
    $requests_js[] = $row;
}
$requests_json = json_encode($requests_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Overdue items for notifications
$overdue_items_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id='$uid_safe' AND status='Overdue' ORDER BY return_date ASC");
$overdue_notifs = [];
while ($row = mysqli_fetch_assoc($overdue_items_raw)) {
    $overdue_notifs[] = $row;
}

// ── Fetch extended user profile ─────────────────────────────────────────────
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
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Sync | User Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="stylesheet" href="CSS/user-dashboard.css">

</head>

<body>

    <!-- ================================================================
     HEADER
================================================================ -->
    <header class="app-header">
        <div class="header-left">
            <div class="app-logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon" style="color: var(--accent-maroon)" aria-label="PUPSYNC" aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <div class="app-logo-text" style="display: flex; flex-direction: column;">
                    <span style="white-space: nowrap; line-height: 1.1;">
                        <strong style="font-size: 25px;">PUP</strong><span style="font-weight: 500; letter-spacing: -0.3px; font-size: 21px; vertical-align: baseline; margin-left: 1px;">SYNC</span>
                    </span>
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
                <?php if ($profile_pic_url): ?>
                    <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img">
                <?php else: ?>
                    <?php echo htmlspecialchars($initials); ?>
                <?php endif; ?>
            </div>

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
                        <span class="notif-badge" id="notifBadge"><?php echo (3 + count($overdue_notifs)); ?></span>
                    </button>
                    <button class="dd-item" data-action="open-overlay" data-target="settingsOverlay">
                        Settings
                    </button>
                    <div class="dd-divider"></div>
                    <button class="dd-item dd-logout" data-action="logout">
                        <div class="dd-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;" aria-label="Logout" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <polyline points="16 17 21 12 16 7" />
                                <line x1="21" y1="12" x2="9" y2="12" />
                            </svg></div> Logout
                    </button>
                </div>
            </div>
        </div>
    </header>


    <!-- ================================================================
     MAIN CONTENT
================================================================ -->
    <main id="app-main">

        <!-- Success Alert -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert-banner alert-success" id="success-alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Success" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <strong>Success!</strong> Your borrow request has been submitted for approval.
                <button class="alert-close" data-action="dismiss-alert" data-target="success-alert">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;" aria-label="Close" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Overdue Alert -->
        <div class="alert-banner alert-danger hidden" id="overdue-alert">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Alert" aria-hidden="true">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
            <strong>Overdue Alert:</strong> You have overdue equipment — please return it immediately!
            <button class="alert-close" data-action="dismiss-alert" data-target="overdue-alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;" aria-label="Close" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>

        <!-- ============================================================
         TAB: HOME
    ============================================================ -->
        <div class="tab-panel active" id="panel-home">
            <div class="section-header">
                <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon" aria-label="Home" aria-hidden="true">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>Welcome back, <?php echo htmlspecialchars($firstname); ?>! 👋</h2>
                <p><?php echo date('l, F j, Y'); ?> &mdash; Here's a summary of your activity.</p>
            </div>

            <!-- Hero Card -->
            <div class="hero-card">
                <h1>Equipment Lending Portal</h1>
                <p>Browse available equipment, submit borrow requests, and track your approvals — all in one place.</p>
            </div>

            <!-- Stats -->
            <p style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1.2px; color:var(--text-light); margin-bottom:0.8rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon" aria-label="Activity" aria-hidden="true">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                    <line x1="2" y1="20" x2="22" y2="20" />
                </svg>Your Activity
            </p>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;" aria-label="Total" aria-hidden="true">
                            <polygon points="12 2 2 7 12 12 22 7 12 2" />
                            <polyline points="2 17 12 22 22 17" />
                            <polyline points="2 12 12 17 22 12" />
                        </svg></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value"><?php echo $stat_total; ?></div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Waiting" title="View Pending requests">
                    <div class="stat-icon" style="background:#fff8e1; color:#c67c00;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;" aria-label="Pending" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg></div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:var(--warning);"><?php echo $stat_waiting; ?></div>
                    <div class="stat-sub stat-sub-link">Awaiting approval →</div>
                </div>
                <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Approved" title="View Approved requests">
                    <div class="stat-icon" style="background:#e3fcef; color:#00875a;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;" aria-label="Approved" aria-hidden="true">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg></div>
                    <div class="stat-label">Approved</div>
                    <div class="stat-value" style="color:var(--success);"><?php echo $stat_approved; ?></div>
                    <div class="stat-sub stat-sub-link">Ready to pick up →</div>
                </div>
                <div class="stat-card stat-card-clickable" data-action="filter-requests" data-status="Declined" title="View Declined requests">
                    <div class="stat-icon" style="background:#ffeaea; color:var(--danger);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;" aria-label="Declined" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg></div>
                    <div class="stat-label">Declined</div>
                    <div class="stat-value" style="color:var(--danger);"><?php echo $stat_declined; ?></div>
                    <div class="stat-sub stat-sub-link">Review reasons →</div>
                </div>
                <div class="stat-card stat-card-clickable<?php echo $stat_overdue > 0 ? ' stat-card-overdue' : ''; ?>" data-action="filter-requests" data-status="Overdue" title="View Overdue items">
                    <div class="stat-icon" style="background:#fff3e0; color:#e65100;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;" aria-label="Overdue" aria-hidden="true">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg></div>
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value" style="color:#e65100;" id="statOverdueVal"><?php echo $stat_overdue; ?></div>
                    <div class="stat-sub stat-sub-link">Items past due →</div>
                </div>
            </div>

            <div class="home-grid">
                <!-- Events -->
                <div class="event-container">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" style="color:var(--accent-maroon); margin-right:8px" aria-label="Calendar" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>Upcoming Events</h3>
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
        </div><!-- /panel-home -->


        <!-- ============================================================
         TAB: LENDING
    ============================================================ -->
        <div class="tab-panel" id="panel-lending">

            <!-- Lending Sub-Nav -->
            <div class="lending-nav">
                <button class="lending-nav-btn active" data-lending-nav="browse">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Browse" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg> Browse Equipment
                </button>
                <button class="lending-nav-btn" data-lending-nav="requests">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Requests" aria-hidden="true">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                    </svg> My Requests
                </button>
            </div>

            <!-- ── Sub: Browse Equipment ─────────────────────────── -->
            <div class="lending-sub active" id="lending-browse">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon" aria-label="Browse equipment" aria-hidden="true">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>Browse Equipment</h2>
                    <p>Search and request available school equipment for academic use.</p>
                </div>

                <div class="eq-card">
                    <div class="eq-card-body">
                        <div class="filter-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon" aria-label="Search" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
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
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="width:48px;height:48px;color:var(--khaki-border);display:block;margin-bottom:0.8rem;opacity:0.7;" aria-label="No items" aria-hidden="true">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                        <line x1="12" y1="22.08" x2="12" y2="12" />
                                    </svg>
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
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36" style="width:36px;height:36px;" aria-label="Item" aria-hidden="true">
                                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                                    <line x1="12" y1="22.08" x2="12" y2="12" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>

                                        <div class="eq-item-body">
                                            <div class="eq-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="eq-item-meta">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;" aria-label="Category" aria-hidden="true">
                                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                                                    <line x1="7" y1="7" x2="7.01" y2="7" />
                                                </svg>
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <div style="margin-bottom:6px;">
                                                <?php if ($item['quantity'] > 0): ?>
                                                    <span class="stock-badge stock-avail">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12" style="width:12px;height:12px;" aria-label="Available" aria-hidden="true">
                                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                                            <polyline points="22 4 12 14.01 9 11.01" />
                                                        </svg>
                                                        <?php echo (int)$item['quantity']; ?> available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-badge stock-unavail">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12" style="width:12px;height:12px;" aria-label="Unavailable" aria-hidden="true">
                                                            <line x1="18" y1="6" x2="6" y2="18" />
                                                            <line x1="6" y1="6" x2="18" y2="18" />
                                                        </svg>
                                                        Out of stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn-borrow"
                                                <?php if ($item['quantity'] <= 0) echo 'disabled'; ?>
                                                data-action="open-borrow-form"
                                                data-item="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;" aria-label="Borrow" aria-hidden="true">
                                                    <path d="M18 11V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2" />
                                                    <path d="M14 10V4a2 2 0 0 0-2-2 2 2 0 0 0-2 2v2" />
                                                    <path d="M10 10.5V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2v8" />
                                                    <path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15" />
                                                </svg>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;" aria-label="Close" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <div class="form-card-body">
                        <div class="selected-item-banner" id="selectedItemBanner">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="width:18px;height:18px;" aria-label="Selected" aria-hidden="true">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                <line x1="12" y1="22.08" x2="12" y2="12" />
                            </svg>
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
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;" aria-label="Send" aria-hidden="true">
                                    <line x1="22" y1="2" x2="11" y2="13" />
                                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                </svg> Submit Borrow Request
                            </button>
                        </form>
                    </div>
                </div>
            </div><!-- /lending-form -->

            <!-- ── Sub: My Requests ──────────────────────────────── -->
            <div class="lending-sub" id="lending-requests">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon" aria-label="Requests" aria-hidden="true">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                        </svg>My Borrow Requests</h2>
                    <p>Track the status of all your submitted borrow requests.</p>
                </div>

                <div class="eq-card">
                    <div class="eq-card-header" style="flex-wrap:wrap; gap:10px;">
                        <h2>Request History</h2>
                        <div class="requests-toolbar">
                            <!-- Status Filter -->
                            <div class="req-filter-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;color:var(--text-light);" aria-hidden="true">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                                </svg>
                                <select id="reqStatusFilter" class="req-filter-select" data-action="filter-requests-dd">
                                    <option value="All">All Statuses</option>
                                    <option value="Waiting">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Declined">Declined</option>
                                    <option value="Overdue">Overdue</option>
                                    <option value="Returned">Returned</option>
                                </select>
                            </div>
                            <!-- Sort Order -->
                            <button class="req-sort-btn" id="reqSortBtn" data-action="toggle-sort" title="Toggle sort order">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;" aria-hidden="true">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <polyline points="5 12 12 5 19 12" />
                                </svg>
                                <span id="reqSortLabel">Latest First</span>
                            </button>
                        </div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="requests-table" id="requestsTable">
                            <thead>
                                <tr>
                                    <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-hidden="true">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                            <line x1="12" y1="22.08" x2="12" y2="12" />
                                        </svg>Equipment</th>
                                    <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-hidden="true">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                            <circle cx="12" cy="7" r="4" />
                                        </svg>Instructor</th>
                                    <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                            <line x1="9" y1="3" x2="9" y2="21" />
                                            <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                                        </svg>Room</th>
                                    <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-hidden="true">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>Borrow Date</th>
                                    <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-hidden="true">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>Return Date</th>
                                    <th>Status</th>
                                    <th>Reason / Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="requestsTbody">
                                <!-- Populated by JS from PHP JSON -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /lending-requests -->

            <!-- Embed requests data for JS -->
            <script>
                window.REQUESTS_DATA = <?php echo $requests_json; ?>;
            </script>

        </div><!-- /panel-lending -->


        <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
        <div class="tab-panel" id="panel-rooms">
            <div class="section-header">
                <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon" aria-label="Rooms" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                        <line x1="9" y1="3" x2="9" y2="21" />
                        <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                    </svg>Room Reservation</h2>
                <p>Browse available rooms and make a reservation for your class or event.</p>
            </div>

            <!-- Coming Soon Banner -->
            <div class="coming-soon-banner">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Coming soon" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                <h3>Room Reservation — Coming Soon</h3>
                <p>This feature is under development. You can preview available rooms below and fill a reservation form when it launches.</p>
            </div>

            <!-- Room Cards (Pseudo Design) -->
            <div class="room-list" id="roomList">
                <!-- Room 1 -->
                <div class="room-card">
                    <div class="room-img"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="width:48px;height:48px;" aria-label="Lab" aria-hidden="true">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                            <line x1="8" y1="21" x2="16" y2="21" />
                            <line x1="12" y1="17" x2="12" y2="21" />
                        </svg></div>
                    <div class="room-info">
                        <div>
                            <div class="room-header">
                                <div>
                                    <h3>Computer Laboratory 301</h3>
                                    <p>3rd Floor, Main Building</p>
                                </div>
                                <span class="capacity-badge"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Seats" aria-hidden="true">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                    </svg>40 seats</span>
                            </div>
                            <div class="amenities" style="margin-top:10px;">
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="WiFi" aria-hidden="true">
                                        <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                                        <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                                        <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                                        <line x1="12" y1="20" x2="12.01" y2="20" />
                                    </svg> WiFi</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="A/C" aria-hidden="true">
                                        <path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2" />
                                        <path d="M9.6 4.6A2 2 0 1 1 11 8H2" />
                                        <path d="M12.6 19.4A2 2 0 1 0 14 16H2" />
                                    </svg> A/C</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Projector" aria-hidden="true">
                                        <rect x="2" y="7" width="20" height="15" rx="2" ry="2" />
                                        <polyline points="17 2 12 7 7 2" />
                                    </svg> Projector</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <span class="room-avail"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="6" height="6" aria-hidden="true" style="vertical-align:middle;margin-right:6px;opacity:0.85;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg> Available</span>
                            <button class="btn-borrow" style="width:auto; padding:9px 24px;"
                                data-action="open-room-form" data-room="Computer Laboratory 301">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Reserve" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                    <line x1="12" y1="15" x2="12" y2="19" />
                                    <line x1="10" y1="17" x2="14" y2="17" />
                                </svg> Reserve
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room 2 -->
                <div class="room-card">
                    <div class="room-img"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="width:48px;height:48px;" aria-label="Lab" aria-hidden="true">
                            <path d="M9 3h6" />
                            <path d="M10 3v7l-5 11h14L14 10V3" />
                        </svg></div>
                    <div class="room-info">
                        <div>
                            <div class="room-header">
                                <div>
                                    <h3>Science Laboratory</h3>
                                    <p>2nd Floor, Science Wing</p>
                                </div>
                                <span class="capacity-badge"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Seats" aria-hidden="true">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                    </svg>30 seats</span>
                            </div>
                            <div class="amenities" style="margin-top:10px;">
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="A/C" aria-hidden="true">
                                        <path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2" />
                                        <path d="M9.6 4.6A2 2 0 1 1 11 8H2" />
                                        <path d="M12.6 19.4A2 2 0 1 0 14 16H2" />
                                    </svg> A/C</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Water" aria-hidden="true">
                                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                                    </svg> Running Water</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Safety" aria-hidden="true">
                                        <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" />
                                    </svg> Safety Kit</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <span class="room-occupied"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="6" height="6" aria-hidden="true" style="vertical-align:middle;margin-right:6px;opacity:0.85;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg> Occupied until 3 PM</span>
                            <button class="btn-borrow" style="width:auto; padding:9px 24px;"
                                data-action="open-room-form" data-room="Science Laboratory">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Reserve" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                    <line x1="12" y1="15" x2="12" y2="19" />
                                    <line x1="10" y1="17" x2="14" y2="17" />
                                </svg> Reserve
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room 3 -->
                <div class="room-card">
                    <div class="room-img"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="width:48px;height:48px;" aria-label="Hall" aria-hidden="true">
                            <rect x="2" y="3" width="20" height="14" rx="2" />
                            <line x1="8" y1="21" x2="16" y2="21" />
                            <line x1="12" y1="17" x2="12" y2="21" />
                            <path d="M9 10l2 2 4-4" />
                        </svg></div>
                    <div class="room-info">
                        <div>
                            <div class="room-header">
                                <div>
                                    <h3>Lecture Hall A</h3>
                                    <p>Ground Floor, Academic Building</p>
                                </div>
                                <span class="capacity-badge"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Seats" aria-hidden="true">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                    </svg>80 seats</span>
                            </div>
                            <div class="amenities" style="margin-top:10px;">
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="WiFi" aria-hidden="true">
                                        <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                                        <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                                        <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                                        <line x1="12" y1="20" x2="12.01" y2="20" />
                                    </svg> WiFi</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="A/C" aria-hidden="true">
                                        <path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2" />
                                        <path d="M9.6 4.6A2 2 0 1 1 11 8H2" />
                                        <path d="M12.6 19.4A2 2 0 1 0 14 16H2" />
                                    </svg> A/C</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="PA" aria-hidden="true">
                                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                                        <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                                        <line x1="12" y1="19" x2="12" y2="23" />
                                        <line x1="8" y1="23" x2="16" y2="23" />
                                    </svg> PA System</span>
                                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Projector" aria-hidden="true">
                                        <rect x="2" y="7" width="20" height="15" rx="2" ry="2" />
                                        <polyline points="17 2 12 7 7 2" />
                                    </svg> Projector</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <span class="room-avail"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="6" height="6" aria-hidden="true" style="vertical-align:middle;margin-right:6px;opacity:0.85;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg> Available</span>
                            <button class="btn-borrow" style="width:auto; padding:9px 24px;"
                                data-action="open-room-form" data-room="Lecture Hall A">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Reserve" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                    <line x1="12" y1="15" x2="12" y2="19" />
                                    <line x1="10" y1="17" x2="14" y2="17" />
                                </svg> Reserve
                            </button>
                        </div>
                    </div>
                </div>
            </div><!-- /roomList -->

            <!-- Room Reservation Form (hidden until Reserve clicked) -->
            <div id="room-form-section" class="hidden" style="margin-top:2rem;">
                <div class="eq-card room-form-card">
                    <div class="form-card-header">
                        <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" style="color:var(--accent-maroon); margin-right:8px" aria-label="Room Form" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                <line x1="9" y1="3" x2="9" y2="21" />
                                <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                            </svg>Room Reservation Form</h2>
                        <button class="btn-close-custom" data-action="close-room-form" title="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;" aria-label="Close" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <div class="form-card-body">
                        <div class="selected-item-banner" id="selectedRoomBanner">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="width:18px;height:18px;" aria-label="Room" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                <line x1="9" y1="3" x2="9" y2="21" />
                                <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                            </svg>
                            <span id="selectedRoomLabel">No room selected</span>
                        </div>

                        <div class="coming-soon-banner" style="margin-bottom:1.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Info" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="width:16px;height:16px;margin-right:8px;" aria-label="Submit" aria-hidden="true">
                                <line x1="22" y1="2" x2="11" y2="13" />
                                <polygon points="22 2 15 22 11 13 2 9 22 2" />
                            </svg> Submit Reservation (Preview)
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

        <!-- Own top bar — replaces the hidden app header while overlay is open -->
        <div class="overlay-topbar">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="accountOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg> Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">My Account</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="PUPSYNC" aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <span>PUPSYNC</span>
            </div>
        </div>

        <div class="account-layout">
            <div class="account-sidebar">
                <span class="account-sidebar-label">My Account</span>
                <button class="acc-nav-btn active" data-acc-tab="acc-overview">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Overview" aria-hidden="true">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <circle cx="8" cy="12" r="2" />
                        <path d="M14 9h4" />
                        <path d="M14 12h4" />
                        <path d="M14 15h2" />
                    </svg> Overview
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-academic">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Academic" aria-hidden="true">
                        <path d="M22 10v6" />
                        <path d="M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg> Academic Info
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-contact">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Contact" aria-hidden="true">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                    </svg> Contact Details
                </button>
                <span class="account-sidebar-label" style="margin-top:0.5rem;">Emergency</span>
                <button class="acc-nav-btn" data-acc-tab="acc-emergency">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" aria-label="Emergency" aria-hidden="true">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg> Emergency Contact
                </button>
            </div>

            <div class="account-content">
                <!-- Overview -->
                <div id="acc-overview" class="overlay-sub-panel active">
                    <div class="overlay-section-header" style="margin-bottom:1.4rem;">
                        <span class="section-eyebrow">My Account › Overview</span>
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
                            <div style="position: relative;">
                                <button class="btn-change-profile" id="changeProfileBtn">
                                    Change Profile
                                </button>
                                <!-- Picture menu -->
                                <div class="picture-menu" id="pictureMenu" style="display:none;">
                                    <button class="pic-menu-item" data-action="upload-picture">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="margin-right:8px;">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="17 8 12 3 7 8" />
                                            <line x1="12" y1="3" x2="12" y2="15" />
                                        </svg>
                                        Upload Photo
                                    </button>
                                    <?php if ($profile_pic_url): ?>
                                        <button class="pic-menu-item pic-menu-danger" data-action="remove-picture">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="margin-right:8px;">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                            </svg>
                                            Remove Photo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Hidden file input -->
                            <input type="file" id="profilePicInput" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none;">
                        </div>
                        <div class="acc-hero-info">
                            <h2><?php echo htmlspecialchars($fullname); ?></h2>
                            <p>ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
                            <span class="acc-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" style="vertical-align:middle;margin-right:6px;">
                                    <circle cx="12" cy="12" r="7" fill="#22c55e" stroke="none" />
                                </svg>
                                Active Student
                            </span>

                            <!-- Subtle Progress Bar (hidden when 100%) -->
                            <div class="account-progress-inline" id="inlineProgressContainer">
                                <div class="progress-bar-wrapper">
                                    <div class="progress-bar-small">
                                        <div class="progress-bar-fill-small" id="completionBar" style="width: 0%;"></div>
                                    </div>
                                    <span class="progress-percentage-small" id="completionPercentage">0%</span>
                                </div>
                                <div class="progress-info-icon" id="progressInfoIcon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="16" x2="12" y2="12" />
                                        <line x1="12" y1="8" x2="12.01" y2="8" />
                                    </svg>
                                    <div class="progress-tooltip" id="progressTooltip">
                                        <strong>Account Completion</strong>
                                        <p id="tooltipHint">Complete your profile to access all features</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">
                                Edit Profile
                            </button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;" data-action="profile-save">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" aria-label="Save" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg> Save
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
                            <span class="info-val <?php echo $dob_locked ? '' : 'empty'; ?>" data-field="dob"><?php echo $dob_locked ? htmlspecialchars($dob_display) : '— Not provided'; ?></span>
                            <?php if (!$dob_locked): ?>
                                <input class="info-input-f" type="date" data-input="dob" disabled style="display:none;" max="<?php echo date('Y-m-d'); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Gender</span>
                            <span class="info-val <?php echo $gender_locked ? '' : 'empty'; ?>" data-field="gender"><?php echo $gender_locked ? htmlspecialchars($db_gender) : '— Not provided'; ?></span>
                            <?php if (!$gender_locked): ?>
                                <select class="info-input-f" data-input="gender" disabled style="display:none;">
                                    <option value="">Select...</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Prefer not to say</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Nationality</span>
                            <span class="info-val <?php echo $nationality_locked ? '' : 'empty'; ?>" data-field="nationality"><?php echo $nationality_locked ? htmlspecialchars($db_nationality) : '— Not provided'; ?></span>
                            <?php if (!$nationality_locked): ?>
                                <input class="info-input-f" data-input="nationality" placeholder="e.g. Filipino" disabled style="display:none;">
                            <?php endif; ?>
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
                            <span class="info-val <?php echo $masked_backup ? '' : 'empty'; ?>" data-field="backup_email">
                                <?php echo $masked_backup ?: '— Not provided'; ?>
                            </span>
                            <?php if (!$backup_locked): ?>
                                <button class="btn-borrow" style="width:auto; padding:6px 16px; font-size:0.75rem; margin-left:auto;"
                                    data-action="open-backup-email-modal">
                                    Add
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Password</span>
                            <span class="info-val">••••••••••</span>
                            <button class="btn-borrow" style="width:auto; padding:6px 16px; font-size:0.75rem; margin-left:auto;"
                                data-action="open-email-verify-modal">
                                Change
                            </button>
                        </div>
                    </div>
                </div><!-- /acc-overview -->

                <!-- Academic -->
                <div id="acc-academic" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account › Academic</span>
                        <h2>Academic Information</h2>
                        <p>Your enrollment and program details.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Enrollment</h3>
                            <button class="btn-edit-acc" id="editAcademicBtn" data-action="academic-edit" style="display:inline-flex;">
                                Edit
                            </button>
                            <button class="btn-save-acc" id="saveAcademicBtn" style="display:none;" data-action="academic-save">
                                Save Changes
                            </button>
                            <button class="btn-cancel-acc" id="cancelAcademicBtn" style="display:none;" data-action="academic-cancel">
                                Cancel
                            </button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Student ID</span>
                            <span class="info-val"><?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val"><?php echo htmlspecialchars($fullname); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Program</span>
                            <span class="info-val <?php echo $program_locked ? '' : 'empty'; ?>" data-field="program">
                                <?php echo $program_locked ? htmlspecialchars($db_program) : '— Not provided'; ?>
                            </span>
                            <?php if (!$program_locked): ?>
                                <select class="info-input-f" data-input="program" disabled style="display:none;">
                                    <option value="">Select Program...</option>
                                    <option value="BEED">BEED</option>
                                    <option value="BSBA-HRM">BSBA-HRM</option>
                                    <option value="BSCpE">BSCpE</option>
                                    <option value="BSED">BSED</option>
                                    <option value="BSIE">BSIE</option>
                                    <option value="BSIT">BSIT</option>
                                    <option value="BSPSY">BSPSY</option>
                                    <option value="DCET">DCET</option>
                                    <option value="DIT">DIT</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Year Level</span>
                            <span class="info-val <?php echo $db_year_level ? '' : 'empty'; ?>" data-field="year_level">
                                <?php echo $db_year_level ? htmlspecialchars($db_year_level) : '— Not provided'; ?>
                            </span>
                            <select class="info-input-f" data-input="year_level" disabled style="display:none;">
                                <option value="">Select Year Level...</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                                <option value="Ladderized">Ladderized</option>
                            </select>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Status</span>
                            <span class="info-val"><span class="stock-badge stock-avail">Active / Regular</span></span>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div id="acc-contact" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account › Contact</span>
                        <h2>Contact Details</h2>
                        <p>How we can reach you.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Address</h3>
                            <button class="btn-edit-acc" id="editContactBtn" data-action="contact-edit" style="display:inline-flex;">
                                Edit
                            </button>
                            <button class="btn-save-acc" id="saveContactBtn" style="display:none;" data-action="contact-save">
                                Save Changes
                            </button>
                            <button class="btn-cancel-acc" id="cancelContactBtn" style="display:none;" data-action="contact-cancel">
                                Cancel
                            </button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Present Address</span>
                            <span class="info-val <?php echo $db_present_address ? '' : 'empty'; ?>" data-field="present_address">
                                <?php echo $db_present_address ? htmlspecialchars($db_present_address) : '— Not provided'; ?>
                            </span>
                            <textarea class="info-input-f" data-input="present_address" placeholder="Enter your current address" disabled style="display:none; min-height:60px;"></textarea>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Permanent Address</span>
                            <span class="info-val <?php echo $db_permanent_address ? '' : 'empty'; ?>" data-field="permanent_address">
                                <?php echo $db_permanent_address ? htmlspecialchars($db_permanent_address) : '— Not provided'; ?>
                            </span>
                            <textarea class="info-input-f" data-input="permanent_address" placeholder="Enter your permanent address" disabled style="display:none; min-height:60px;"></textarea>
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
                            <input class="info-input-f" data-input="phone" placeholder="e.g. +63 912 345 6789" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Landline</span>
                            <span class="info-val <?php echo $db_landline ? '' : 'empty'; ?>" data-field="landline">
                                <?php echo $db_landline ? htmlspecialchars($db_landline) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="landline" placeholder="e.g. (02) 1234-5678" disabled style="display:none;">
                        </div>
                    </div>
                </div>

                <!-- Emergency -->
                <div id="acc-emergency" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account › Emergency</span>
                        <h2>Emergency Contact</h2>
                        <p>Person to contact in an emergency.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Primary Contact</h3>
                            <button class="btn-edit-acc" id="editEmergencyBtn" data-action="emergency-edit" style="display:inline-flex;">
                                Edit
                            </button>
                            <button class="btn-save-acc" id="saveEmergencyBtn" style="display:none;" data-action="emergency-save">
                                Save Changes
                            </button>
                            <button class="btn-cancel-acc" id="cancelEmergencyBtn" style="display:none;" data-action="emergency-cancel">
                                Cancel
                            </button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Name</span>
                            <span class="info-val <?php echo $db_emergency_name ? '' : 'empty'; ?>" data-field="emergency_name">
                                <?php echo $db_emergency_name ? htmlspecialchars($db_emergency_name) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="emergency_name" placeholder="Full name" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Relationship</span>
                            <span class="info-val <?php echo $db_emergency_rel ? '' : 'empty'; ?>" data-field="emergency_relationship">
                                <?php echo $db_emergency_rel ? htmlspecialchars($db_emergency_rel) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="emergency_relationship" placeholder="e.g. Mother, Father, Guardian" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Mobile Number</span>
                            <span class="info-val <?php echo $db_emergency_phone ? '' : 'empty'; ?>" data-field="emergency_phone">
                                <?php echo $db_emergency_phone ? htmlspecialchars($db_emergency_phone) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="emergency_phone" placeholder="e.g. +63 912 345 6789" disabled style="display:none;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /accountOverlay -->

    <!-- ================================================================
     OVERLAY: SETTINGS PAGE
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">

        <!-- Own top bar -->
        <div class="overlay-topbar">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="settingsOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg> Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">Settings</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="PUPSYNC" aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <span>PUPSYNC</span>
            </div>
        </div>

        <div class="settings-layout">
            <div class="settings-sidebar">
                <span class="s-cat-label">Appearance</span>
                <button class="s-nav-item active" data-sett-tab="st-appearance"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <circle cx="13.5" cy="6.5" r="0.5" fill="currentColor" />
                        <circle cx="17.5" cy="10.5" r="0.5" fill="currentColor" />
                        <circle cx="8.5" cy="7.5" r="0.5" fill="currentColor" />
                        <circle cx="6.5" cy="12.5" r="0.5" fill="currentColor" />
                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                    </svg> Appearance</button>
                <button class="s-nav-item" data-sett-tab="st-accessibility"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <circle cx="12" cy="6" r="2" />
                        <path d="m4 14 8-2 8 2" />
                        <path d="M8 12v1.5l-3 5" />
                        <path d="M16 12v1.5l3 5" />
                        <path d="m9 22 3-6 3 6" />
                    </svg> Accessibility</button>
                <span class="s-cat-label">Account</span>
                <button class="s-nav-item" data-sett-tab="st-privacy"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        <polyline points="9 12 11 14 15 10" />
                    </svg> Privacy &amp; Security</button>
                <button class="s-nav-item" data-sett-tab="st-notif-prefs"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg> Notifications</button>
                <!-- <button class="s-nav-item" data-sett-tab="st-language"><i class="fa-solid fa-language"></i> Language & Region</button> -->
                <div class="s-divider"></div>
                <span class="s-cat-label">System</span>
                <button class="s-nav-item" data-sett-tab="st-advanced"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <line x1="4" y1="21" x2="4" y2="14" />
                        <line x1="4" y1="10" x2="4" y2="3" />
                        <line x1="12" y1="21" x2="12" y2="12" />
                        <line x1="12" y1="8" x2="12" y2="3" />
                        <line x1="20" y1="21" x2="20" y2="16" />
                        <line x1="20" y1="12" x2="20" y2="3" />
                        <line x1="1" y1="14" x2="7" y2="14" />
                        <line x1="9" y1="8" x2="15" y2="8" />
                        <line x1="17" y1="16" x2="23" y2="16" />
                    </svg> Advanced</button>
            </div>

            <div class="settings-content">

                <!-- Appearance -->
                <div id="st-appearance" class="overlay-sub-panel active">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Appearance</span>
                        <h2>Appearance</h2>
                        <p>Customize how the portal looks and feels.</p>
                    </div>

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
                                <div class="theme-lbl">Light <svg id="tc-light" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-maroon);vertical-align:middle;">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg></div>
                            </div>
                            <div class="theme-opt" id="tp-dark" data-action="apply-theme" data-theme="dark">
                                <div class="theme-prev tp-dark">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">Dark <svg id="tc-dark" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-maroon);vertical-align:middle;display:none;">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg></div>
                            </div>
                            <div class="theme-opt" id="tp-hc" data-action="apply-theme" data-theme="high-contrast">
                                <div class="theme-prev tp-hc">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">High Contrast <svg id="tc-hc" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-maroon);vertical-align:middle;display:none;">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg></div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Accent Color</h3>
                            <p>Pick a highlight color for buttons and active elements.</p>
                        </div>
                        <div class="color-dots">
                            <div class="c-dot selected" style="background:#600302;" data-action="apply-accent" data-color="#600302" data-light="#f3e5e6" title="Maroon (Default)"></div>
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
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Accessibility</span>
                        <h2>Accessibility</h2>
                        <p>Make the portal easier to use.</p>
                    </div>

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
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Privacy</span>
                        <h2>Privacy &amp; Security</h2>
                        <p>Control your data and account security.</p>
                    </div>
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
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Notifications</span>
                        <h2>Notification Preferences</h2>
                        <p>Control which notifications you receive.</p>
                    </div>
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

                <!-- Advanced -->
                <div id="st-advanced" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Advanced</span>
                        <h2>Advanced</h2>
                        <p>Power user settings. Be careful.</p>
                    </div>
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
    <div class="overlay-page" id="notifOverlay" style="display:flex; flex-direction:column; overflow-y:auto;">

        <!-- Own top bar -->
        <div class="overlay-topbar" style="flex-shrink:0;">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="notifOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg> Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">Notifications</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="PUPSYNC" aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <span>PUPSYNC</span>
            </div>
        </div>

        <div class="notif-wrapper">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.2rem; flex-wrap:wrap; gap:10px;">
                <div class="overlay-section-header" style="flex:1; margin-bottom:0;">
                    <span class="section-eyebrow">Inbox › All Notifications</span>
                    <h2>Notifications</h2>
                    <p>You have <strong style="color:var(--accent-maroon);" id="unreadCount"><?php echo (3 + count($overdue_notifs)); ?> unread</strong> notifications.</p>
                </div>
                <button class="mark-read-btn" data-action="mark-all-read" style="margin-top:0.5rem;">Mark all as read</button>
            </div>

            <div class="notif-filter-tabs">
                <button class="notif-tab active" data-notif-filter="all">All</button>
                <button class="notif-tab" data-notif-filter="unread">Unread</button>
                <button class="notif-tab" data-notif-filter="borrow">Borrow</button>
                <button class="notif-tab" data-notif-filter="overdue">Overdue</button>
                <button class="notif-tab" data-notif-filter="system">System</button>
            </div>

            <?php if (!empty($overdue_notifs)): ?>
                <div class="notif-group overdue-notif-group">⚠️ Overdue — Action Required</div>
                <?php foreach ($overdue_notifs as $on): ?>
                    <div class="notif-item unread notif-overdue" data-cat="overdue">
                        <div class="notif-icon ni-overdue"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                <line x1="12" y1="9" x2="12" y2="13" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg></div>
                        <div class="notif-body-wrap">
                            <h4>Overdue Item: <?php echo htmlspecialchars($on['equipment_name']); ?></h4>
                            <p>This item was due on <strong><?php echo htmlspecialchars($on['return_date']); ?></strong>. Please return it to the admin immediately to avoid penalties.</p>
                        </div>
                        <div class="notif-meta"><span class="notif-time">Overdue since <?php echo htmlspecialchars($on['return_date']); ?></span>
                            <div class="unread-dot"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="notif-group">Today</div>
            <div class="notif-item unread" data-cat="borrow">
                <div class="notif-icon ni-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg></div>
                <div class="notif-body-wrap">
                    <h4>Borrow Request Approved</h4>
                    <p>Your latest borrow request has been approved. Please pick up the item at the Admin Office before 5:00 PM.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">9:42 AM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-item unread" data-cat="system">
                <div class="notif-icon ni-alert"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                    </svg></div>
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
                <div class="notif-icon ni-warn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg></div>
                <div class="notif-body-wrap">
                    <h4>Return Reminder</h4>
                    <p>You have a borrowed item due in 1 day. Please return it on time to avoid penalties.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">Yesterday, 4:15 PM</span>
                    <div class="unread-dot"></div>
                </div>
            </div>
            <div class="notif-item" data-cat="borrow">
                <div class="notif-icon ni-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                        <line x1="12" y1="22.08" x2="12" y2="12" />
                    </svg></div>
                <div class="notif-body-wrap">
                    <h4>Request Submitted</h4>
                    <p>Your borrow request for Lab Equipment was successfully submitted and is under review.</p>
                </div>
                <div class="notif-meta"><span class="notif-time">Yesterday, 2:00 PM</span></div>
            </div>
        </div>
    </div><!-- /notifOverlay -->

    <!-- ================================================================
     CONFIRMATION MODAL (shows changes before saving)
================================================================ -->
    <div class="pw-modal-backdrop" id="confirmationModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="confirmationTitle">
        <div class="pw-modal-box" style="max-width: 600px;">
            <div class="pw-modal-header">
                <h3 id="confirmationTitle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                    </svg>Confirm Changes
                </h3>
                <button class="pw-modal-close" data-action="close-confirmation-modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="pw-modal-body">
                <p style="margin-bottom:1rem; color:var(--text-secondary); font-size:0.9rem;">Please review the changes you're about to make:</p>

                <div class="changes-summary" id="changesSummary" style="background: var(--bg-secondary); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem; max-height: 300px; overflow-y: auto;">
                    <!-- Changes will be populated here -->
                </div>

                <div class="warning-box" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: var(--radius); padding: 0.875rem; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#856404" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                        <div>
                            <strong style="color: #856404; display: block; margin-bottom: 0.25rem;">Important Notice</strong>
                            <p style="color: #856404; margin: 0; font-size: 0.875rem;" id="warningMessage">
                                Some changes cannot be reversed once saved. Please verify all information before confirming.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pw-modal-footer">
                <button class="btn-cancel-acc" data-action="close-confirmation-modal">Cancel</button>
                <button class="btn-save-acc" id="confirmChangesBtn" data-action="confirm-changes">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Confirm & Save
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================
     EMAIL VERIFICATION MODAL (before password change)
================================================================ -->
    <div class="pw-modal-backdrop" id="emailVerifyModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="emailVerifyTitle">
        <div class="pw-modal-box">
            <div class="pw-modal-header">
                <h3 id="emailVerifyTitle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>Verify Your Email
                </h3>
                <button class="pw-modal-close" data-action="close-email-verify-modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="pw-modal-body">
                <p style="margin-bottom:1rem; color:var(--text-secondary); font-size:0.9rem;">For security, please verify your email address before changing your password.</p>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="verifyEmailInput" class="form-control-custom" placeholder="Enter your registered email" autocomplete="email">
                </div>
                <p class="pw-modal-error" id="emailVerifyError" style="display:none;"></p>
            </div>
            <div class="pw-modal-footer">
                <button class="btn-cancel-acc" data-action="close-email-verify-modal">Cancel</button>
                <button class="btn-save-acc" id="emailVerifyBtn" data-action="submit-email-verify">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Verify & Continue
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================
     BACKUP EMAIL MODAL
================================================================ -->
    <div class="pw-modal-backdrop" id="backupEmailModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="backupEmailTitle">
        <div class="pw-modal-box">
            <div class="pw-modal-header">
                <h3 id="backupEmailTitle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>Backup Email
                </h3>
                <button class="pw-modal-close" data-action="close-backup-email-modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="pw-modal-body">
                <p style="margin-bottom:1rem; color:var(--text-secondary); font-size:0.9rem;">Add a backup email for account recovery and important notifications.</p>
                <div class="form-group">
                    <label>Backup Email Address</label>
                    <input type="email" id="backupEmailInput" class="form-control-custom" placeholder="Enter backup email" autocomplete="email" value="<?php echo htmlspecialchars($db_backup_email); ?>">
                </div>
                <p class="pw-modal-error" id="backupEmailError" style="display:none;"></p>
            </div>
            <div class="pw-modal-footer">
                <button class="btn-cancel-acc" data-action="close-backup-email-modal">Cancel</button>
                <button class="btn-save-acc" id="backupEmailSaveBtn" data-action="save-backup-email">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Save Backup Email
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================
     CHANGE PASSWORD MODAL
================================================================ -->
    <div class="pw-modal-backdrop" id="pwModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="pwModalTitle">
        <div class="pw-modal-box">
            <div class="pw-modal-header">
                <h3 id="pwModalTitle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>Change Password
                </h3>
                <button class="pw-modal-close" data-action="close-pw-modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="pw-modal-body">
                <div class="form-group">
                    <label>Current Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwCurrent" class="form-control-custom" placeholder="Enter your current password" autocomplete="current-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwCurrent" tabindex="-1" aria-label="Toggle visibility">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwNew" class="form-control-custom" placeholder="At least 6 characters" autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwNew" tabindex="-1" aria-label="Toggle visibility">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="pw-strength-bar" id="pwStrengthBar" style="display:none;">
                        <div class="pw-strength-fill" id="pwStrengthFill"></div>
                    </div>
                    <span class="pw-strength-label" id="pwStrengthLabel"></span>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwConfirm" class="form-control-custom" placeholder="Repeat your new password" autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwConfirm" tabindex="-1" aria-label="Toggle visibility">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="pw-modal-error" id="pwModalError" style="display:none;"></p>
            </div>
            <div class="pw-modal-footer">
                <button class="btn-cancel-acc" data-action="close-pw-modal">Cancel</button>
                <button class="btn-save-acc" id="pwSubmitBtn" data-action="submit-pw-change">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Update Password
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
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top:1rem; font-weight:600; color:var(--text-dark); font-size:0.9rem;">Processing your request...</p>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>


    <!-- Scripts -->
    <script src="js/user-dashboard.js"></script>


</body>

</html>