<?php require_once __DIR__ . '/equipment-booking/core/admin-functions.php'; ?>
<?php require_once __DIR__ . '/room-reservation/core/admin-rooms-functions.php'; ?>
<?php
// ── CONFIRM RETURN ─────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'return_confirm' && isset($_GET['id'])) {
    $req_id = intval($_GET['id']);
    $res = $conn->query("SELECT equipment_name FROM tbl_requests WHERE id = $req_id LIMIT 1");
    if ($res && $row_rc = $res->fetch_assoc()) {
        $conn->query("UPDATE tbl_requests SET status = 'Returned', return_token = NULL, returned_at = NOW() WHERE id = $req_id");
        $eq = $conn->real_escape_string($row_rc['equipment_name']);
        $conn->query("UPDATE tbl_inventory SET quantity = quantity + 1 WHERE item_name = '$eq'");
    }
    header("Location: admin-dashboard.php?view=return-confirmation");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Sync | Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">
    <link rel="stylesheet" href="equipment-booking/assets/css/admin-dashboard.css?v=<?php echo filemtime('equipment-booking/assets/css/admin-dashboard.css'); ?>">
</head>

<body>

    <!-- ================================================================
     HEADER
================================================================ -->
    <header class="app-header">

        <!-- Logo block — sits flush above the sidebar -->
        <div class="header-logo">
            <div class="logo-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div class="logo-text">
                <span style="white-space:nowrap;line-height:1.2;">
                    <strong>PUP</strong><span style="font-weight:500;">SYNC</span>
                    <span class="logo-badge">Admin</span>
                </span>
                <span class="logo-subtitle">Admin Portal</span>
            </div>
        </div>

        <!-- Center: Search -->
        <div class="header-search">
            <span class="material-symbols-outlined search-icon">search</span>
            <input type="text" class="header-search-input" placeholder="Search requests, equipment, faculty...">
        </div>

        <!-- Right: Notification + User + Avatar + Dropdown (unchanged) -->
        <div class="header-right">
            <!-- Scan Return QR — opens #qrScannerModal (admin-dashboard.js) -->
            <button id="openQrScannerBtn" class="qr-scan-btn" title="Scan a faculty member's return QR code">
                <span class="material-symbols-outlined">qr_code_scanner</span>
                <span class="qr-scan-btn-label">Scan Return</span>
            </button>

            <!-- Notification Bell -->
            <button class="notif-btn" data-action="open-notif-modal" title="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <?php if ($stat_waiting > 0 || $stat_overdue > 0): ?>
                    <span class="notif-btn-badge">
                        <?php echo $stat_waiting + $stat_overdue; ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="header-user-info">
                <span class="u-name"><?php echo htmlspecialchars($admin_name); ?></span>
                <span class="u-role">Administrator</span>
            </div>

            <div class="avatar-btn" id="avatarBtn" role="button" aria-haspopup="true" aria-expanded="false"
                title="Account menu">
                <?php echo htmlspecialchars($initials); ?>
            </div>

            <!-- Profile Dropdown (unchanged) -->
            <div class="profile-dropdown" id="profileDropdown" role="menu">
                <div class="dd-header">
                    <div class="dd-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div>
                        <span class="dd-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="dd-sub">Administrator</span>
                        <span class="dd-sub" style="margin-top:2px;">Full Access</span>
                    </div>
                </div>
                <div class="dd-menu">
                    <button class="dd-item" id="dd-account-btn">
                        <div class="dd-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>My Account
                    </button>
                    <button class="dd-item" data-action="open-notif-modal">
                        <div class="dd-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                        </div>Notifications
                        <?php if ($stat_waiting + $stat_overdue > 0): ?>
                            <span class="notif-badge"><?php echo $stat_waiting + $stat_overdue; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="dd-item" id="dd-settings-btn">
                        <div class="dd-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16">
                                <circle cx="12" cy="12" r="3" />
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                            </svg>
                        </div>Settings
                    </button>
                    <div class="dd-divider"></div>
                    <button class="dd-item dd-logout" data-action="logout">
                        <div class="dd-icon" style="background:#ffeaea;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16" style="color:var(--danger)">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <polyline points="16 17 21 12 16 7" />
                                <line x1="21" y1="12" x2="9" y2="12" />
                            </svg>
                        </div>Logout
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- ================================================================
     APP BODY
================================================================ -->
    <div class="app-body">

        <!-- ================================================================
     SIDEBAR
================================================================ -->
        <nav class="sidebar" id="adminSidebar">

            <div class="nav-group-label">Main</div>

            <a class="nav-item active" data-tab="dashboard" href="#">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="nav-item" data-tab="requests" id="snav-requests" href="#">
                <span class="material-symbols-outlined">assignment</span>
                <span>Requests</span>
                <?php if ($stat_waiting > 0): ?>
                    <span class="nav-badge"><?php echo $stat_waiting; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-item" data-tab="inventory" id="snav-inventory" href="#">
                <span class="material-symbols-outlined">inventory_2</span>
                <span>Equipment</span>
            </a>
            <a class="nav-item" data-tab="rooms" href="#">
                <span class="material-symbols-outlined">meeting_room</span>
                <span>Rooms</span>
            </a>
            <a class="nav-item" data-tab="faculty" href="#">
                <span class="material-symbols-outlined">group</span>
                <span>Faculty</span>
            </a>

            <hr class="nav-divider">

            <div class="sidebar-bottom">
                <a class="nav-item" data-tab="settings" id="snav-settings" href="#">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Settings</span>
                </a>
            </div>

        </nav><!-- /sidebar -->

        <!-- ================================================================
     MAIN
================================================================ -->
        <main id="app-main">

            <!-- ============================================================
         TAB: DASHBOARD
    ============================================================ -->
            <div class="tab-panel active" id="panel-dashboard">

                <!-- Overdue alert banner -->
                <?php if ($stat_overdue > 0): ?>
                    <div class="ps-alert ps-alert--danger" id="overdue-alert">
                        <span class="material-symbols-outlined">warning</span>
                        <span><strong>Overdue Alert:</strong> <?php echo $stat_overdue; ?> item(s) are currently overdue and need immediate attention.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="overdue-alert">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- URL-param flash alerts -->
                <?php if (isset($_GET['added'])): ?>
                    <div class="ps-alert ps-alert--success" id="added-alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span><strong>Success!</strong> Item added to inventory.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="added-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="ps-alert ps-alert--success" id="updated-alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span><strong>Updated!</strong> Item has been updated successfully.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="updated-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['room_added'])): ?>
                    <div class="ps-alert ps-alert--success" id="room-added-alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span><strong>Room added!</strong> The room has been added to the registry.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="room-added-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['room_updated'])): ?>
                    <div class="ps-alert ps-alert--success" id="room-updated-alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span><strong>Room updated!</strong> Changes saved successfully.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="room-updated-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['room_archived'])): ?>
                    <div class="ps-alert ps-alert--success" id="room-archived-alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span><strong>Room archived.</strong> It has been removed from the active registry.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="room-archived-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['room_restored'])): ?>
                    <div class="ps-alert ps-alert--success" id="room-restored-alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span><strong>Room restored.</strong> It is now back in the active registry.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="room-restored-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['room_error'])): ?>
                    <div class="ps-alert ps-alert--danger" id="room-error-alert">
                        <span class="material-symbols-outlined">warning</span>
                        <span><strong>Error:</strong> Could not save room. Please check required fields and try again.</span>
                        <button class="ps-alert__close" data-action="dismiss-alert" data-target="room-error-alert"><span class="material-symbols-outlined">close</span></button>
                    </div>
                <?php endif; ?>

                <!-- Page header row -->
                <div class="ps-page-header-row">
                    <div class="ps-page-header">
                        <h1>Good <?php
                                    $hour = (int)date('H');
                                    echo $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
                                    ?>, <span id="greetName"><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></span>.</h1>
                        <p><?php echo date('l, F j, Y'); ?> &mdash; Overview of all lending activity and inventory.</p>
                    </div>
                    <a href="?export=1" class="ps-btn ps-btn--outline">
                        <span class="material-symbols-outlined">download</span> Export Report
                    </a>
                </div>

                <!-- Stat cards -->
                <div class="ps-stats-row">
                    <div class="ps-stat-card">
                        <div class="ps-stat-icon ps-stat-icon--maroon">
                            <span class="material-symbols-outlined">assignment</span>
                        </div>
                        <div class="ps-stat-body">
                            <div class="ps-stat-val"><?php echo $stat_waiting; ?></div>
                            <div class="ps-stat-lbl">Waiting Requests</div>
                            <?php if ($stat_waiting > 0): ?>
                                <div class="ps-stat-sub ps-stat-sub--warn">Needs action</div>
                            <?php else: ?>
                                <div class="ps-stat-sub">All clear</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ps-stat-card">
                        <div class="ps-stat-icon ps-stat-icon--green">
                            <span class="material-symbols-outlined">check_circle</span>
                        </div>
                        <div class="ps-stat-body">
                            <div class="ps-stat-val"><?php echo $stat_approved; ?></div>
                            <div class="ps-stat-lbl">Active Borrowings</div>
                            <div class="ps-stat-sub">Currently out</div>
                        </div>
                    </div>
                    <div class="ps-stat-card">
                        <div class="ps-stat-icon ps-stat-icon--red">
                            <span class="material-symbols-outlined">schedule</span>
                        </div>
                        <div class="ps-stat-body">
                            <div class="ps-stat-val"><?php echo $stat_overdue; ?></div>
                            <div class="ps-stat-lbl">Overdue Items</div>
                            <?php if ($stat_overdue > 0): ?>
                                <div class="ps-stat-sub ps-stat-sub--warn">Immediate attention</div>
                            <?php else: ?>
                                <div class="ps-stat-sub">None overdue</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ps-stat-card">
                        <div class="ps-stat-icon ps-stat-icon--orange">
                            <span class="material-symbols-outlined">inventory_2</span>
                        </div>
                        <div class="ps-stat-body">
                            <div class="ps-stat-val"><?php echo $stat_inv_total; ?></div>
                            <div class="ps-stat-lbl">Total Inventory</div>
                            <?php if ($stat_inv_low > 0): ?>
                                <div class="ps-stat-sub ps-stat-sub--warn"><?php echo $stat_inv_low; ?> low stock</div>
                            <?php else: ?>
                                <div class="ps-stat-sub">Stock OK</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions label -->
                <div class="ps-section-label">
                    <span class="material-symbols-outlined">bolt</span> Quick Actions
                </div>

                <!-- Quick Action cards -->
                <div class="ps-quick-actions">
                    <div class="ps-qa-card ps-qa-card--dark" data-action="go-lending" data-lending="waiting" style="cursor:pointer">
                        <span class="material-symbols-outlined">assignment_turned_in</span>
                        <strong>Review Requests</strong>
                        <small><?php echo $stat_waiting; ?> waiting for approval</small>
                    </div>
                    <div class="ps-qa-card ps-qa-card--dark" data-action="go-lending" data-lending="inventory" style="cursor:pointer">
                        <span class="material-symbols-outlined">add_box</span>
                        <strong>Add Equipment</strong>
                        <small>Update inventory catalog</small>
                    </div>
                </div>

                <!-- Bottom two-col: Recent Requests + Recent Activity -->
                <div class="ps-two-col" style="margin-bottom:1.25rem;">

                    <!-- Recent Requests -->
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">assignment</span> Recent Requests</h3>
                            <button class="ps-btn ps-btn--ghost ps-btn--sm" data-action="go-lending" data-lending="waiting">View All</button>
                        </div>
                        <div class="ps-card-body" style="padding:0">
                            <table class="ps-table">
                                <thead>
                                    <tr>
                                        <th>Requester</th>
                                        <th>Equipment</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_req = mysqli_query($conn, "SELECT faculty_name, faculty_id, equipment_name, status FROM tbl_requests ORDER BY request_date DESC LIMIT 5");
                                    if ($recent_req && mysqli_num_rows($recent_req) > 0):
                                        while ($rr = mysqli_fetch_assoc($recent_req)):
                                            $badge = match ($rr['status']) {
                                                'Waiting'  => 'ps-badge--waiting',
                                                'Approved' => 'ps-badge--active',
                                                'Overdue'  => 'ps-badge--overdue',
                                                'Returned' => 'ps-badge--returned',
                                                default    => 'ps-badge--returned',
                                            };
                                    ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600"><?php echo htmlspecialchars($rr['faculty_name']); ?></div>
                                                    <div style="font-size:11px;color:var(--text-light)"><?php echo htmlspecialchars($rr['faculty_id']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($rr['equipment_name']); ?></td>
                                                <td><span class="ps-badge ps-badge--dot <?php echo $badge; ?>"><?php echo htmlspecialchars($rr['status']); ?></span></td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align:center;color:var(--text-light);padding:1.5rem">No requests yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">history</span> Recent Activity</h3>
                        </div>
                        <div class="ps-card-body">
                            <?php
                            $recent_act = mysqli_query($conn, "SELECT faculty_name, equipment_name, status, request_date FROM tbl_requests ORDER BY request_date DESC LIMIT 6");
                            if ($recent_act && mysqli_num_rows($recent_act) > 0):
                                while ($ra = mysqli_fetch_assoc($recent_act)):
                                    $dot_active = in_array($ra['status'], ['Approved', 'Returned']) ? '' : 'ps-feed-dot--gray';
                            ?>
                                    <div class="ps-feed-item">
                                        <div class="ps-feed-dot <?php echo $dot_active; ?>"></div>
                                        <div class="ps-feed-body">
                                            <div class="ps-feed-title"><?php echo htmlspecialchars($ra['equipment_name']); ?> — <?php echo htmlspecialchars($ra['status']); ?></div>
                                            <div class="ps-feed-meta"><?php echo htmlspecialchars($ra['faculty_name']); ?></div>
                                        </div>
                                        <div class="ps-feed-time"><?php echo date('M d', strtotime($ra['request_date'])); ?></div>
                                    </div>
                                <?php endwhile;
                            else: ?>
                                <p style="color:var(--text-light);font-size:0.83rem;text-align:center;padding:1rem">No activity yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bottom two-col: Room Status + Faculty Overview -->
                <div class="ps-two-col">
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">meeting_room</span> Room Status</h3>
                            <button class="ps-btn ps-btn--ghost ps-btn--sm" data-action="go-rooms">Manage</button>
                        </div>
                        <div class="ps-card-body">
                            <?php
                            $q = mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_rooms WHERE is_archived=0");
                            $rooms_total  = $q ? (mysqli_fetch_assoc($q)['c'] ?? 0) : 0;
                            $q = mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_room_issues WHERE status='Open'");
                            $rooms_issues = $q ? (mysqli_fetch_assoc($q)['c'] ?? 0) : 0;
                            $q = mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_room_reservations WHERE DATE(reservation_date)=CURDATE()");
                            $rooms_today  = $q ? (mysqli_fetch_assoc($q)['c'] ?? 0) : 0;
                            ?>
                            <div class="ps-mini-stats">
                                <div class="ps-mini-stat">
                                    <div class="ps-mini-val"><?php echo $rooms_total; ?></div>
                                    <div class="ps-mini-lbl">Active Rooms</div>
                                </div>
                                <div class="ps-mini-stat">
                                    <div class="ps-mini-val" style="color:var(--warning)"><?php echo $rooms_issues; ?></div>
                                    <div class="ps-mini-lbl">Reported Issues</div>
                                </div>
                                <div class="ps-mini-stat">
                                    <div class="ps-mini-val" style="color:var(--success)"><?php echo $rooms_today; ?></div>
                                    <div class="ps-mini-lbl">Reservations Today</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">group</span> Faculty Overview</h3>
                            <button class="ps-btn ps-btn--ghost ps-btn--sm" data-action="go-faculty">Manage</button>
                        </div>
                        <div class="ps-card-body">
                            <?php
                            $q = mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_users");
                            $fac_total  = $q ? (mysqli_fetch_assoc($q)['c'] ?? 0) : 0;
                            $q = mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_users WHERE role != 'Organization Adviser'");
                            $fac_active = $q ? (mysqli_fetch_assoc($q)['c'] ?? 0) : 0;
                            $q = mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_users WHERE role = 'Organization Adviser'");
                            $fac_org    = $q ? (mysqli_fetch_assoc($q)['c'] ?? 0) : 0;
                            ?>
                            <div class="ps-mini-stats">
                                <div class="ps-mini-stat">
                                    <div class="ps-mini-val"><?php echo $fac_total; ?></div>
                                    <div class="ps-mini-lbl">Total Faculty</div>
                                </div>
                                <div class="ps-mini-stat">
                                    <div class="ps-mini-val" style="color:var(--success)"><?php echo $fac_active; ?></div>
                                    <div class="ps-mini-lbl">Active</div>
                                </div>
                                <div class="ps-mini-stat">
                                    <div class="ps-mini-val" style="color:var(--accent-maroon)"><?php echo $fac_org; ?></div>
                                    <div class="ps-mini-lbl">Org Advisers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /panel-dashboard -->


            <!-- ============================================================
         TAB: REQUESTS  (Phase 3 redesign)
    ============================================================ -->
            <div class="tab-panel" id="panel-requests">

                <!-- Page header -->
                <div class="ps-page-header-row">
                    <div class="ps-page-header">
                        <h1>Equipment Requests</h1>
                        <p>Review, approve, or decline borrow requests submitted by faculty and students.</p>
                    </div>
                    <button class="ps-btn ps-btn--outline" data-action="ps-open-modal" data-modal="ps-export-modal">
                        <span class="material-symbols-outlined">download</span> Export
                    </button>
                </div>

                <!-- Sub-tabs -->
                <div class="rq-filter-chips" id="rqTabs">
                    <button class="rq-filter-chip active" data-rq-panel="rq-waiting">
                        Waiting
                        <?php if ($stat_waiting > 0): ?>
                            <span class="rq-chip-count"><?php echo $stat_waiting; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="rq-filter-chip" data-rq-panel="rq-active">
                        Active
                        <?php if ($stat_approved > 0): ?>
                            <span class="rq-chip-count rq-chip-count--ok"><?php echo $stat_approved; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="rq-filter-chip" data-rq-panel="rq-overdue">
                        Overdue
                        <?php if ($stat_overdue > 0): ?>
                            <span class="rq-chip-count"><?php echo $stat_overdue; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="rq-filter-chip" data-rq-panel="rq-all">Returned</button>
                </div>

                <!-- ── WAITING ───────────────────────────────────────────── -->
                <div class="rq-sub-panel active" id="rq-waiting">
                    <div class="ps-help-note">
                        <span class="material-symbols-outlined">info</span>
                        These requests are pending your approval. Approve to issue the item, or decline with an optional reason.
                    </div>
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">pending</span> Waiting for Approval</h3>
                            <div style="display:flex;gap:6px">
                                <input class="ps-form-control" style="width:200px" placeholder="Search requester..." id="rq-waiting-search">
                            </div>
                        </div>
                        <div class="ps-table-wrap">
                            <table class="ps-table" id="rq-waiting-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Requester</th>
                                        <th>Student/Faculty ID</th>
                                        <th>Equipment</th>
                                        <th>Date Requested</th>
                                        <th>Date Needed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    mysqli_data_seek($waiting_result, 0);
                                    if (mysqli_num_rows($waiting_result) > 0):
                                        while ($r = mysqli_fetch_assoc($waiting_result)):
                                    ?>
                                            <tr class="ps-req-row"
                                                data-id="<?php echo (int)$r['id']; ?>"
                                                data-status="Waiting"
                                                data-condition="<?php echo htmlspecialchars($r['item_condition'] ?? 'Good'); ?>"
                                                data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                data-id-number="<?php echo htmlspecialchars($r['faculty_id']); ?>"
                                                data-req-type="Faculty"
                                                data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                data-instructor="<?php echo htmlspecialchars($r['instructor']); ?>"
                                                data-room="<?php echo htmlspecialchars($r['room']); ?>"
                                                data-submitted="<?php echo date('M d, Y g:i A', strtotime($r['request_date'])); ?>"
                                                data-date-needed="<?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>"
                                                data-return-date="<?php echo htmlspecialchars($r['return_date']); ?>"
                                                data-return-date-display="<?php echo date('M d, Y', strtotime($r['return_date'])); ?>"
                                                data-arb-rule="<?php echo htmlspecialchars($r['arbitration_rule'] ?? ''); ?>">
                                                <td>
                                                    <strong style="color:var(--accent-maroon);cursor:pointer"
                                                        data-action="ps-open-modal" data-modal="ps-req-detail-modal">
                                                        #<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600"><?php echo htmlspecialchars($r['faculty_name']); ?></div>
                                                    <div style="font-size:11px;color:var(--text-light)">Faculty</div>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['faculty_id']); ?></td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td><?php echo date('M d, g:i A', strtotime($r['request_date'])); ?></td>
                                                <td><?php echo date('M d, g:i A', strtotime($r['borrow_date'])); ?></td>
                                                <td>
                                                    <div style="display:flex;gap:6px">
                                                        <button class="ps-btn ps-btn--success ps-btn--sm"
                                                            data-action="ps-open-modal" data-modal="ps-approve-modal">
                                                            <span class="material-symbols-outlined">check</span> Approve
                                                        </button>
                                                        <button class="ps-btn ps-btn--danger ps-btn--sm"
                                                            data-action="ps-open-modal" data-modal="ps-decline-modal">
                                                            <span class="material-symbols-outlined">close</span> Decline
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="ps-empty-state">
                                                    <span class="material-symbols-outlined">assignment_turned_in</span>
                                                    <p>No pending requests. All caught up!</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /rq-waiting -->

                <!-- ── ACTIVE BORROWINGS ─────────────────────────────────── -->
                <div class="rq-sub-panel" id="rq-active">
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">check_circle</span>
                                Active Borrowings (<?php echo $stat_approved; ?>)</h3>
                        </div>
                        <div class="ps-table-wrap">
                            <table class="ps-table">
                                <thead>
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Equipment</th>
                                        <th>Issued</th>
                                        <th>Due</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    mysqli_data_seek($approved_result, 0);
                                    if (mysqli_num_rows($approved_result) > 0):
                                        while ($r = mysqli_fetch_assoc($approved_result)):
                                            $isOD = strtotime($r['return_date']) < strtotime($today);
                                    ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600"><?php echo htmlspecialchars($r['faculty_name']); ?></div>
                                                    <div style="font-size:11px;color:var(--text-light)"><?php echo htmlspecialchars($r['faculty_id']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td><?php echo date('M d', strtotime($r['borrow_date'])); ?></td>
                                                <td style="<?php echo $isOD ? 'color:var(--danger);font-weight:600' : ''; ?>">
                                                    <?php echo date('M d', strtotime($r['return_date'])); ?>
                                                </td>
                                                <td>
                                                    <button class="ps-btn ps-btn--ghost ps-btn--sm"
                                                        data-action="ps-open-modal" data-modal="ps-return-modal">Confirm Return</button>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="ps-empty-state">
                                                    <span class="material-symbols-outlined">inventory_2</span>
                                                    <p>No active borrowings at the moment.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /rq-active -->

                <!-- ── OVERDUE ────────────────────────────────────────────── -->
                <div class="rq-sub-panel" id="rq-overdue">
                    <?php if ($stat_overdue > 0): ?>
                        <div class="ps-alert ps-alert--danger" id="rq-overdue-alert">
                            <span class="material-symbols-outlined">warning</span>
                            <?php echo $stat_overdue; ?> item(s) are overdue. Contact the borrowers immediately.
                            <button class="ps-alert__close" data-action="dismiss-alert" data-target="rq-overdue-alert"><span class="material-symbols-outlined">close</span></button>
                        </div>
                    <?php endif; ?>
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">schedule</span>
                                Overdue Items (<?php echo $stat_overdue; ?>)</h3>
                        </div>
                        <div class="ps-table-wrap">
                            <table class="ps-table">
                                <thead>
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Equipment</th>
                                        <th>Due Date</th>
                                        <th>Days Overdue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $overdue_q = mysqli_query($conn, "SELECT tbl_requests.*, tbl_inventory.`condition` AS item_condition
                                        FROM tbl_requests
                                        LEFT JOIN tbl_inventory ON tbl_inventory.item_name = tbl_requests.equipment_name
                                        WHERE tbl_requests.status='Overdue' OR (tbl_requests.status='Approved' AND tbl_requests.return_date < CURDATE())
                                        ORDER BY tbl_requests.return_date ASC");
                                    if ($overdue_q && mysqli_num_rows($overdue_q) > 0):
                                        while ($r = mysqli_fetch_assoc($overdue_q)):
                                            $days_od = max(0, (int)floor((strtotime($today) - strtotime($r['return_date'])) / 86400));
                                    ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600"><?php echo htmlspecialchars($r['faculty_name']); ?></div>
                                                    <div style="font-size:11px;color:var(--text-light)"><?php echo htmlspecialchars($r['faculty_id']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td style="color:var(--danger);font-weight:600"><?php echo date('M d', strtotime($r['return_date'])); ?></td>
                                                <td><span class="ps-badge ps-badge--overdue"><?php echo $days_od; ?> days</span></td>
                                                <td>
                                                    <div style="display:flex;gap:6px">
                                                        <button class="ps-btn ps-btn--ghost ps-btn--sm"
                                                            data-action="ps-open-modal" data-modal="ps-return-modal">Confirm Return</button>
                                                        <button class="ps-btn ps-btn--outline ps-btn--sm"
                                                            data-action="ps-open-modal" data-modal="ps-notice-modal">Send Notice</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="ps-empty-state">
                                                    <span class="material-symbols-outlined">check_circle</span>
                                                    <p>No overdue items. Great job!</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /rq-overdue -->

                <!-- ── RETURNED (merged: was "History" + "All Requests") ──── -->
                <div class="rq-sub-panel" id="rq-all">
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><span class="material-symbols-outlined">history</span> Returned Requests</h3>
                            <div style="display:flex;gap:6px">
                                <input class="ps-form-control" style="width:220px" placeholder="Search..." id="rq-all-search">
                                <select class="ps-form-control" style="width:140px" id="rq-all-range">
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month" selected>This Month</option>
                                    <option value="year">This Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="ps-table-wrap">
                            <table class="ps-table" id="rq-all-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Borrower</th>
                                        <th>Equipment</th>
                                        <th>Returned</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $all_q = mysqli_query($conn, "SELECT tbl_requests.*, tbl_inventory.`condition` AS item_condition
                                        FROM tbl_requests
                                        LEFT JOIN tbl_inventory ON tbl_inventory.item_name = tbl_requests.equipment_name
                                        WHERE tbl_requests.status='Returned'
                                        ORDER BY tbl_requests.return_date DESC LIMIT 100");
                                    if ($all_q && mysqli_num_rows($all_q) > 0):
                                        while ($r = mysqli_fetch_assoc($all_q)):
                                    ?>
                                            <tr class="ps-req-row"
                                                data-id="<?php echo (int)$r['id']; ?>"
                                                data-status="<?php echo htmlspecialchars($r['status']); ?>"
                                                data-condition="<?php echo htmlspecialchars($r['item_condition'] ?? 'Good'); ?>"
                                                data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                data-id-number="<?php echo htmlspecialchars($r['faculty_id']); ?>"
                                                data-req-type="Faculty"
                                                data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                data-instructor="<?php echo htmlspecialchars($r['instructor']); ?>"
                                                data-room="<?php echo htmlspecialchars($r['room']); ?>"
                                                data-submitted="<?php echo date('M d, Y g:i A', strtotime($r['request_date'])); ?>"
                                                data-date-needed="<?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>"
                                                data-return-date="<?php echo htmlspecialchars($r['return_date']); ?>"
                                                data-return-date-display="<?php echo date('M d, Y', strtotime($r['return_date'])); ?>"
                                                data-arb-rule="<?php echo htmlspecialchars($r['arbitration_rule'] ?? ''); ?>">
                                                <td style="cursor:pointer;color:var(--accent-maroon);font-weight:600"
                                                    data-action="ps-open-modal" data-modal="ps-req-detail-modal">
                                                    #<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['faculty_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($r['return_date'])); ?></td>
                                                <td>
                                                    <button class="ps-btn ps-btn--ghost ps-btn--sm" data-action="ps-open-modal" data-modal="ps-req-detail-modal">View</button>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="ps-empty-state">
                                                    <span class="material-symbols-outlined">list_alt</span>
                                                    <p>No requests found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /rq-all -->

            </div><!-- /panel-requests -->






            <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
            <div class="tab-panel" id="panel-rooms">

                <!-- ── Page header + Add Room button ──────────────────── -->
                <div class="pr-page-header-row">
                    <div>
                        <h1 class="pr-page-title">Room Management</h1>
                        <p class="pr-page-subtitle">Manage campus rooms, reservations, and reported issues. <?php echo $stat_rooms_total; ?> active room<?php echo $stat_rooms_total !== 1 ? 's' : ''; ?> — <?php echo $stat_rooms_available; ?> Available, <?php echo $stat_rooms_maintenance; ?> Maintenance, <?php echo $stat_rooms_notbookable; ?> Not Bookable.</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-shrink:0;">
                        <button class="pr-btn pr-btn-ghost pr-btn-sm" data-rooms-tab="rooms-archived" title="View archived rooms">
                            <span class="material-symbols-outlined" style="font-size:15px;">archive</span>
                            Archived
                            <?php if (!empty($rooms_archived)): ?>
                                <span class="pr-tab-badge" style="background:var(--text-light);"><?php echo count($rooms_archived); ?></span>
                            <?php endif; ?>
                        </button>
                        <button class="pr-btn pr-btn-primary" data-action="show-room-form" id="addRoomBtn">
                            <span class="material-symbols-outlined">add</span>
                            Add Room
                        </button>
                    </div>
                </div>

                <!-- ── Add / Edit Room form (hidden by default) ────────── -->
                <div id="room-form-wrap" class="<?php echo $edit_room ? '' : 'hidden'; ?>">
                    <div class="eq-card form-card">

                        <!-- Modal header -->
                        <div class="form-card-header">
                            <div class="rmod-head-icon">
                                <span class="material-symbols-outlined"><?php echo $edit_room ? 'edit' : 'add_home_work'; ?></span>
                            </div>
                            <h2 id="room-form-title">
                                <?php echo $edit_room
                                    ? 'Edit Room &mdash; ' . htmlspecialchars($edit_room['room_name'])
                                    : 'Add New Room'; ?>
                            </h2>
                            <button type="button" class="rmod-close-btn" data-action="hide-room-form" aria-label="Close">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <!-- Body wrapper — matches equipment form padding -->
                        <div class="form-card-body">
                            <form method="POST" id="roomForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="room_id" id="room-form-id"
                                    value="<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>">

                                <!-- Row 1: Building | Room Name -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Building <span class="req-star" aria-label="required">*</span></label>
                                        <select name="building_id" class="form-control-custom" required>
                                            <option value="">— Select building —</option>
                                            <?php foreach ($rooms_buildings as $bid => $b): ?>
                                                <option value="<?php echo (int)$bid; ?>"
                                                    <?php echo ($edit_room && (int)$edit_room['building_id'] === $bid) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($b['campus_name'] . ' › ' . $b['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Room Name <span class="req-star" aria-label="required">*</span></label>
                                        <input type="text" name="room_name" class="form-control-custom"
                                            value="<?php echo $edit_room ? htmlspecialchars($edit_room['room_name']) : ''; ?>"
                                            placeholder="e.g. Computer Laboratory 1" required>
                                    </div>
                                </div>

                                <!-- Row 2: Floor Number | Display Name for Floor -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>
                                            Floor Number <span class="req-star" aria-label="required">*</span>
                                            <span class="field-tip" data-tip="The floor this room is on (e.g. enter 2 for 2nd Floor)." aria-label="More information" tabindex="0">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="16" x2="12" y2="12" />
                                                    <line x1="12" y1="8" x2="12.01" y2="8" />
                                                </svg>
                                                <span class="field-tip-box" role="tooltip">The floor this room is on (e.g. enter 2 for 2nd Floor).</span>
                                            </span>
                                        </label>
                                        <input type="number" name="floor_number" class="form-control-custom" min="1" max="20"
                                            value="<?php echo $edit_room ? (int)$edit_room['floor_number'] : 1; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            Display Name for Floor <small class="field-optional">(optional)</small>
                                            <span class="field-tip" data-tip="Only needed if this floor has a special name, like &#39;Ground Floor&#39; or &#39;Mezzanine&#39;. Leave blank to use the floor number automatically." aria-label="More information" tabindex="0">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="16" x2="12" y2="12" />
                                                    <line x1="12" y1="8" x2="12.01" y2="8" />
                                                </svg>
                                                <span class="field-tip-box" role="tooltip">Only needed if this floor has a special name, like &lsquo;Ground Floor&rsquo; or &lsquo;Mezzanine&rsquo;. Leave blank to use the floor number automatically.</span>
                                            </span>
                                        </label>
                                        <input type="text" name="floor_label" class="form-control-custom"
                                            value="<?php echo $edit_room ? htmlspecialchars($edit_room['floor_label'] ?? '') : ''; ?>"
                                            placeholder="e.g. Ground Floor">
                                    </div>
                                </div>

                                <!-- Row 3: Seating Capacity | Room Status -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>
                                            Seating Capacity <small class="field-optional">(optional)</small>
                                            <span class="field-tip" data-tip="How many people this room can hold. Leave blank if unknown." aria-label="More information" tabindex="0">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="16" x2="12" y2="12" />
                                                    <line x1="12" y1="8" x2="12.01" y2="8" />
                                                </svg>
                                                <span class="field-tip-box" role="tooltip">How many people this room can hold. Leave blank if unknown.</span>
                                            </span>
                                        </label>
                                        <input type="number" name="seating_capacity" class="form-control-custom" min="1"
                                            value="<?php echo ($edit_room && $edit_room['seating_capacity'] !== null) ? (int)$edit_room['seating_capacity'] : ''; ?>"
                                            placeholder="e.g. 40">
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            Room Status <span class="req-star" aria-label="required">*</span>
                                            <span class="field-tip" data-tip="Available = open for booking. Maintenance = temporarily closed. Not Bookable = this room cannot be reserved." aria-label="More information" tabindex="0">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="16" x2="12" y2="12" />
                                                    <line x1="12" y1="8" x2="12.01" y2="8" />
                                                </svg>
                                                <span class="field-tip-box" role="tooltip">Available = open for booking. Maintenance = temporarily closed. Not Bookable = this room cannot be reserved.</span>
                                            </span>
                                        </label>
                                        <select name="status" class="form-control-custom">
                                            <?php foreach (['Available', 'Maintenance', 'Not Bookable'] as $s): ?>
                                                <option value="<?php echo $s; ?>"
                                                    <?php echo ($edit_room && $edit_room['status'] === $s) ? 'selected' : (!$edit_room && $s === 'Available' ? 'selected' : ''); ?>>
                                                    <?php echo htmlspecialchars($s); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Row 4: Display Position (left only, right empty — mirrors Quantity row in equipment form) -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>
                                            Display Position <small class="field-optional">(optional)</small>
                                            <span class="field-tip" data-tip="Controls which room appears first when browsing this floor. Leave as 0 if you don&#39;t have a preference — rooms will sort by the order they were added." aria-label="More information" tabindex="0">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="12" y1="16" x2="12" y2="12" />
                                                    <line x1="12" y1="8" x2="12.01" y2="8" />
                                                </svg>
                                                <span class="field-tip-box" role="tooltip">Controls which room appears first when browsing this floor. Leave as 0 if you don&rsquo;t have a preference &mdash; rooms will sort by the order they were added.</span>
                                            </span>
                                        </label>
                                        <input type="number" name="sort_order" class="form-control-custom" min="0"
                                            value="<?php echo $edit_room ? (int)$edit_room['sort_order'] : 0; ?>">
                                    </div>
                                    <div class="form-group"><!-- empty right column --></div>
                                </div>

                                <!-- Row 5: Room Features (full width) -->
                                <div class="form-group">
                                    <label>
                                        Room Features <small class="field-optional">(optional)</small>
                                        <span class="field-tip" data-tip="Tick everything this room has. These details appear on the room information screen." aria-label="More information" tabindex="0">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                                                <circle cx="12" cy="12" r="10" />
                                                <line x1="12" y1="16" x2="12" y2="12" />
                                                <line x1="12" y1="8" x2="12.01" y2="8" />
                                            </svg>
                                            <span class="field-tip-box" role="tooltip">Tick everything this room has. These details appear on the room information screen.</span>
                                        </span>
                                    </label>
                                    <?php
                                    $preset_amenities = ['WiFi', 'A/C', 'Projector', 'PA System', 'Running Water', 'Safety Kit', 'Whiteboard', 'Smart TV'];
                                    $checked_amenities = [];
                                    if ($edit_room && !empty($edit_room['amenities'])) {
                                        $decoded = json_decode($edit_room['amenities'], true);
                                        $checked_amenities = is_array($decoded) ? $decoded : [];
                                    }
                                    ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
                                        <?php foreach ($preset_amenities as $am): ?>
                                            <label style="display:flex;align-items:center;gap:4px;font-weight:400;cursor:pointer;font-size:0.85rem;background:var(--surface-alt);padding:4px 10px;border-radius:20px;border:1px solid var(--border);">
                                                <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($am); ?>"
                                                    <?php echo in_array($am, $checked_amenities, true) ? 'checked' : ''; ?>>
                                                <?php echo htmlspecialchars($am); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Row 6: Submit button -->
                                <button type="submit" id="room-form-submit-inner"
                                    name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                    class="btn-submit-form">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span id="room-submit-inner-label"><?php echo $edit_room ? 'Update Room' : 'Add Room'; ?></span>
                                </button>
                            </form>

                            <!-- Archive zone: always in DOM, shown/hidden by JS -->
                            <div class="rmod-archive-zone<?php echo $edit_room ? '' : ' hidden'; ?>" id="room-archive-zone">
                                <a href="admin-dashboard.php?archive_room=<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>"
                                    id="room-archive-link"
                                    class="pr-btn pr-btn-danger pr-btn-sm">
                                    <span class="material-symbols-outlined" style="font-size:15px;">archive</span>
                                    Archive Room
                                </a>
                            </div>

                        </div><!-- /.form-card-body -->

                        <!-- Modal footer -->
                        <div class="rmod-footer">
                            <button type="button" class="pr-btn pr-btn-ghost" data-action="hide-room-form">Cancel</button>
                            <button type="submit" form="roomForm" id="room-form-submit-foot"
                                name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                class="pr-btn pr-btn-primary">
                                <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                                <span id="room-submit-foot-label"><?php echo $edit_room ? 'Save Changes' : 'Add Room'; ?></span>
                            </button>
                        </div>

                    </div><!-- /.eq-card.form-card -->
                </div><!-- /#room-form-wrap -->

                <!-- ── Tab navigation (streamlined: 2 tabs — Rooms + Issues) ── -->
                <div class="pr-sub-tabs" id="rooms-toggle-wrap">
                    <button class="pr-sub-tab active" data-rooms-tab="rooms-active">Rooms</button>
                    <button class="pr-sub-tab" data-rooms-tab="rooms-issues">
                        Issues
                        <?php if (!empty($admin_room_issues_open)): ?>
                            <span class="pr-tab-badge"><?php echo (int)$admin_room_issues_open; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- ════════════════════════════════════════════════════
                     SUB-PANEL: ACTIVE ROOMS
                     ════════════════════════════════════════════════════ -->
                <div class="rooms-sub-panel active" id="rooms-active-panel">

                    <div class="pr-card">
                        <div class="pr-card-header pr-card-header-maroon">
                            <h3>
                                <span class="material-symbols-outlined">calendar_month</span>
                                Reservations
                            </h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Room</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Faculty</th>
                                        <th>Submitted As</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($admin_room_reservations)): ?>
                                        <tr>
                                            <td colspan="11" style="text-align:center;padding:2.5rem;color:var(--text-light);">No reservations yet.</td>
                                        </tr>
                                        <?php else: foreach ($admin_room_reservations as $ar):
                                            $ar_pill = 'pr-pill-approved';
                                            if ($ar['status'] === 'Declined')  $ar_pill = 'pr-pill-declined';
                                            if ($ar['status'] === 'Cancelled') $ar_pill = 'pr-pill-cancelled';

                                            $ar_submitted = match ($ar['submitted_as']) {
                                                'adviser' => 'Adviser',
                                                'student' => 'Student (via code)',
                                                default   => 'Personal',
                                            };
                                            $ar_who = $ar['submitted_as'] === 'student' && !empty($ar['submitted_by_name'])
                                                ? htmlspecialchars($ar['submitted_by_name']) . '<br><small style="color:var(--text-light);">via ' . htmlspecialchars($ar['faculty_name']) . '</small>'
                                                : htmlspecialchars($ar['faculty_name']);
                                        ?>
                                            <tr>
                                                <td class="td-sm">#<?php echo (int)$ar['id']; ?></td>
                                                <td class="td-fw"><?php echo htmlspecialchars($ar['room_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ar['floor_label'] . ', ' . $ar['building_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?></td>
                                                <td style="white-space:nowrap;"><?php echo htmlspecialchars($ar['start_fmt'] . ' – ' . $ar['end_fmt']); ?></td>
                                                <td><?php echo $ar_who; ?></td>
                                                <td><?php echo htmlspecialchars($ar_submitted); ?></td>
                                                <td><?php echo htmlspecialchars($ar['purpose']); ?></td>
                                                <td><span class="pr-pill <?php echo $ar_pill; ?>"><?php echo htmlspecialchars($ar['status']); ?></span></td>
                                                <td class="td-sm"><?php echo $ar['reason'] ? htmlspecialchars($ar['reason']) : '—'; ?></td>
                                                <td>
                                                    <?php if ($ar['status'] === 'Approved'): ?>
                                                        <button class="pr-tbl-btn cancel-r btn-cancel-rr-admin"
                                                            data-action="admin-cancel-reservation"
                                                            data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                            data-room-name="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                            data-faculty-name="<?php echo htmlspecialchars($ar['faculty_name']); ?>"
                                                            title="Cancel this reservation">
                                                            <span class="material-symbols-outlined" style="font-size:14px;">cancel</span>
                                                            Cancel
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="td-sm">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php
                    // ── Group active rooms by building → floor, and assign each
                    //    building a display accent color. Shared with the AJAX
                    //    responses in admin-rooms-functions.php via
                    //    ps_prepare_rooms_registry_view() so both paths compute
                    //    this identically. ─────────────────────────────────────
                    $rv = ps_prepare_rooms_registry_view($rooms_buildings, $rooms_list);
                    $rooms_grouped         = $rv['grouped'];
                    $floors_by_building_js = $rv['floors_by_building'];
                    $first_building_id     = $rv['first_building_id'];
                    $building_accent       = $rv['accent'];
                    ?>

                    <?php if (empty($rooms_buildings)): ?>
                        <div class="pr-card" style="margin-top:1.75rem;">
                            <div class="pr-card-header pr-card-header-maroon">
                                <h3>
                                    <span class="material-symbols-outlined">meeting_room</span>
                                    All Rooms
                                </h3>
                            </div>
                            <div class="pr-empty">
                                No rooms in the registry yet. Click <strong>Add Room</strong> to get started.
                            </div>
                        </div>
                    <?php else: ?>

                        <div class="pr-card" style="margin-top:1.75rem;">
                            <div class="pr-card-header pr-card-header-maroon">
                                <h3>
                                    <span class="material-symbols-outlined">meeting_room</span>
                                    All Rooms
                                </h3>
                            </div>

                            <!-- ── Toolbar: search + Building / Floor / Status filters ── -->
                            <div class="pr-rooms-toolbar">
                                <div class="pr-search-wrap">
                                    <span class="material-symbols-outlined">search</span>
                                    <input type="text" id="roomSearchInput" placeholder="Search rooms…" autocomplete="off">
                                </div>
                                <select id="roomBuildingSelect" class="pr-filter-select">
                                    <option value="all">All Buildings</option>
                                    <?php foreach ($rooms_buildings as $bid => $b): ?>
                                        <option value="<?php echo (int)$bid; ?>" <?php echo $bid === $first_building_id ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['campus_name'] . ' · ' . $b['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="roomFloorSelect" class="pr-filter-select">
                                    <option value="all">All Floors</option>
                                    <?php foreach (($floors_by_building_js[$first_building_id] ?? []) as $f): ?>
                                        <option value="<?php echo (int)$f['num']; ?>"><?php echo htmlspecialchars($f['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="roomStatusSelect" class="pr-filter-select">
                                    <option value="all">All Statuses</option>
                                    <option value="Available">Available</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Not Bookable">Not Bookable</option>
                                </select>
                            </div>

                            <!-- ── One flat, floor-grouped table for every building ────── -->
                            <div class="pr-tbl-wrap">
                                <table class="pr-table pr-rooms-table" id="roomsRegistryTable">
                                    <colgroup>
                                        <col>
                                        <col class="pr-col-capacity" style="width:110px;">
                                        <col style="width:150px;">
                                        <col style="width:132px;">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Capacity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php echo ps_render_rooms_tbody($rooms_buildings, $rooms_grouped, $building_accent); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.pr-card (All Rooms) -->

                        <script nonce="<?php echo $csp_nonce; ?>">
                            /* ── PUPSync Room Registry filter module ─────────────────
                               Search + Building/Floor/Status dropdowns. Depends on
                               PHP-emitted floor data, so it lives inline (same pattern
                               as the Room Schedule module below). */
                            (function() {
                                'use strict';
                                let FLOORS_BY_BUILDING = <?php echo json_encode($floors_by_building_js); ?>;
                                const DEFAULT_BUILDING = '<?php echo (int)$first_building_id; ?>';

                                const table = document.getElementById('roomsRegistryTable');
                                const bldSel = document.getElementById('roomBuildingSelect');
                                const flrSel = document.getElementById('roomFloorSelect');
                                const stSel = document.getElementById('roomStatusSelect');
                                const search = document.getElementById('roomSearchInput');
                                if (!table || !bldSel || !flrSel || !stSel || !search) return;

                                function populateFloorSelect(buildingId) {
                                    flrSel.innerHTML = '';
                                    const allOpt = document.createElement('option');
                                    allOpt.value = 'all';
                                    allOpt.textContent = 'All Floors';
                                    flrSel.appendChild(allOpt);

                                    if (buildingId === 'all') {
                                        flrSel.disabled = true;
                                        return;
                                    }
                                    flrSel.disabled = false;
                                    (FLOORS_BY_BUILDING[buildingId] || []).forEach(function(f) {
                                        const opt = document.createElement('option');
                                        opt.value = String(f.num);
                                        opt.textContent = f.label;
                                        flrSel.appendChild(opt);
                                    });
                                }

                                function applyFilters() {
                                    const bId = bldSel.value;
                                    const fNum = flrSel.value;
                                    const status = stSel.value;
                                    const query = (search.value || '').trim().toLowerCase();
                                    const searching = query.length > 0;

                                    let anyVisible = false;
                                    let currentDivider = null;
                                    let dividerHasVisible = false;

                                    table.querySelectorAll('tbody tr').forEach(function(row) {
                                        if (row.classList.contains('pr-floor-divider')) {
                                            if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';
                                            const dB = row.dataset.buildingId;
                                            const dF = row.dataset.floorNum;
                                            const inScope = searching || ((bId === 'all' || dB === bId) && (fNum === 'all' || dF === fNum));
                                            currentDivider = inScope ? row : null;
                                            if (!inScope) row.style.display = 'none';
                                            dividerHasVisible = false;
                                            return;
                                        }
                                        if (row.classList.contains('pr-no-match-row')) return;
                                        if (!row.classList.contains('pr-room-row')) return;

                                        const rB = row.dataset.roomBuildingId;
                                        const rF = row.dataset.roomFloorNum;
                                        const inScope = searching || ((bId === 'all' || rB === bId) && (fNum === 'all' || rF === fNum));
                                        const matchesStatus = status === 'all' || row.dataset.roomStatus === status;
                                        const matchesQuery = !searching || (row.dataset.roomName || '').toLowerCase().indexOf(query) !== -1;
                                        const visible = inScope && matchesStatus && matchesQuery;

                                        row.style.display = visible ? '' : 'none';
                                        if (visible) {
                                            dividerHasVisible = true;
                                            anyVisible = true;
                                        }
                                    });
                                    if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';

                                    const noMatch = table.querySelector('.pr-no-match-row');
                                    if (noMatch) noMatch.style.display = anyVisible ? 'none' : '';
                                }

                                bldSel.addEventListener('change', function() {
                                    populateFloorSelect(this.value);
                                    applyFilters();
                                });
                                flrSel.addEventListener('change', applyFilters);
                                stSel.addEventListener('change', applyFilters);
                                search.addEventListener('input', applyFilters);

                                populateFloorSelect(DEFAULT_BUILDING);
                                applyFilters();

                                /* ── Exposed for the AJAX module below: after an add/
                                   update/archive succeeds, drop the fresh <tbody> HTML
                                   in, refresh the Floor dropdown's options for whichever
                                   Building is currently selected (a new floor number may
                                   now exist), and re-apply the current search/filter
                                   state so the admin's view doesn't reset. ──────────── */
                                window.psRefreshRoomsRegistry = function(html, freshFloorsByBuilding) {
                                    if (freshFloorsByBuilding) {
                                        FLOORS_BY_BUILDING = freshFloorsByBuilding;
                                    }
                                    if (typeof html === 'string') {
                                        const tbody = table.querySelector('tbody');
                                        if (tbody) tbody.innerHTML = html;
                                    }
                                    if (bldSel.value !== 'all') {
                                        const keepFloor = flrSel.value;
                                        populateFloorSelect(bldSel.value);
                                        if ([...flrSel.options].some(function(o) {
                                                return o.value === keepFloor;
                                            })) {
                                            flrSel.value = keepFloor;
                                        }
                                    }
                                    applyFilters();
                                };
                            })();
                        </script>

                        <script nonce="<?php echo $csp_nonce; ?>">
                            /* ── PUPSync Room Registry: AJAX add/update/archive ──────
                               Progressive enhancement — the <form> and the Archive
                               <a> links still have their real method="POST"/href, so
                               they keep working with JS disabled. When JS runs, this
                               intercepts both, sends an extra ajax=1 flag, and swaps
                               in the fresh table HTML the server sends back instead
                               of letting the browser navigate at all. */
                            (function() {
                                'use strict';
                                const roomForm = document.getElementById('roomForm');
                                if (!roomForm) return;

                                function closeRoomModal() {
                                    const rfw = document.getElementById('room-form-wrap');
                                    const addRoomBtn = document.getElementById('addRoomBtn');
                                    if (rfw) rfw.classList.add('hidden');
                                    if (addRoomBtn) addRoomBtn.style.display = '';
                                    // If the modal was opened via ?edit_room=ID, drop that
                                    // param now — otherwise a later refresh would silently
                                    // reopen the modal on a room that's already saved.
                                    const params = new URLSearchParams(window.location.search);
                                    if (params.get('edit_room')) {
                                        params.delete('edit_room');
                                        const qs = params.toString();
                                        window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
                                    }
                                }

                                function afterMutation(json) {
                                    if (json && json.status === 'success') {
                                        if (window.psRefreshRoomsRegistry) {
                                            window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                        }
                                        closeRoomModal();
                                        if (typeof showToast === 'function') showToast(json.message || 'Saved.');
                                    } else {
                                        if (typeof showToast === 'function') {
                                            showToast((json && json.message) || 'Something went wrong. Please try again.');
                                        }
                                    }
                                }

                                function onNetworkError() {
                                    if (typeof showToast === 'function') showToast('Network error — please try again.');
                                }

                                const submitBtns = ['room-form-submit-inner', 'room-form-submit-foot']
                                    .map(function(id) {
                                        return document.getElementById(id);
                                    })
                                    .filter(Boolean);

                                roomForm.addEventListener('submit', function(e) {
                                    e.preventDefault();

                                    const fd = new FormData(roomForm);
                                    // A submit event's .submitter is the exact button that
                                    // was clicked — needed here because FormData(form) alone
                                    // does not include a submit button's name/value (the
                                    // browser only adds that for a real, non-JS submit).
                                    // Both buttons carry name="add_room" or "update_room"
                                    // depending on the modal's current mode, so this is how
                                    // the server knows which action to run.
                                    const clicked = e.submitter || submitBtns[0];
                                    if (clicked && clicked.name) fd.set(clicked.name, clicked.value || '1');
                                    fd.set('ajax', '1');

                                    submitBtns.forEach(function(b) {
                                        b.disabled = true;
                                    });

                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            submitBtns.forEach(function(b) {
                                                b.disabled = false;
                                            });
                                            afterMutation(json);
                                        })
                                        .catch(function() {
                                            submitBtns.forEach(function(b) {
                                                b.disabled = false;
                                            });
                                            onNetworkError();
                                        });
                                });

                                /* Archive: both the per-row icon (in the table) and the
                                   modal's own Archive Room link share the same href
                                   pattern, so one delegated listener catches both. */
                                document.addEventListener('click', function(e) {
                                    const link = e.target.closest('a[href*="archive_room="]');
                                    if (!link) return;
                                    e.preventDefault();
                                    if (!confirm('Archive this room? It will be hidden from the registry and can be restored later.')) return;

                                    const href = link.getAttribute('href');
                                    const url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';

                                    fetch(url, {
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(afterMutation)
                                        .catch(onNetworkError);
                                });
                            })();
                        </script>

                    <?php endif; ?>


                </div><!-- /#rooms-active-panel -->

                <!-- ════════════════════════════════════════════════════
                     SUB-PANEL: ARCHIVED ROOMS
                     ════════════════════════════════════════════════════ -->
                <div class="rooms-sub-panel" id="rooms-archived-panel">
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3>
                                <span class="material-symbols-outlined">archive</span>
                                Archived Rooms
                            </h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table">
                                <thead>
                                    <tr>
                                        <th>Room Name</th>
                                        <th>Location</th>
                                        <th>Status at Archive</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rooms_archived)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;padding:2.5rem;color:var(--text-light);">No archived rooms.</td>
                                        </tr>
                                        <?php else: foreach ($rooms_archived as $ar):
                                            $ar_floor = !empty($ar['floor_label']) ? $ar['floor_label'] : $ar['floor_number'] . 'F';
                                            $ar_pill_cls = $ar['status'] === 'Available' ? 'pr-pill-avail' : 'pr-pill-maint';
                                        ?>
                                            <tr>
                                                <td class="td-fw"><?php echo htmlspecialchars($ar['room_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ar_floor . ', ' . $ar['building_name'] . ' — ' . $ar['campus_name']); ?></td>
                                                <td><span class="pr-pill <?php echo $ar_pill_cls; ?>"><?php echo htmlspecialchars($ar['status']); ?></span></td>
                                                <td>
                                                    <a href="admin-dashboard.php?restore_room=<?php echo (int)$ar['room_id']; ?>"
                                                        class="pr-tbl-btn restore">
                                                        <span class="material-symbols-outlined" style="font-size:14px;">unarchive</span>
                                                        Restore
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-archived-panel -->

                <!-- ════════════════════════════════════════════════════
                     SUB-PANEL: ROOM ISSUES
                     ════════════════════════════════════════════════════ -->
                <div class="rooms-sub-panel" id="rooms-issues-panel">
                    <div class="pr-card">
                        <div class="pr-card-header pr-card-header-maroon">
                            <h3>
                                <span class="material-symbols-outlined">report_problem</span>
                                Room Issues
                                <?php if (!empty($admin_room_issues_open)): ?>
                                    <span class="pr-tab-badge" style="background:var(--warning);"><?php echo (int)$admin_room_issues_open; ?> open</span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Room</th>
                                        <th>Location</th>
                                        <th>Reported By</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Reported At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($admin_room_issues)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-light);">No issue reports yet.</td>
                                        </tr>
                                        <?php else: foreach ($admin_room_issues as $issue):
                                            $iss_pill = 'pr-pill-open';
                                            if ($issue['status'] === 'Resolved')  $iss_pill = 'pr-pill-resolved';
                                            if ($issue['status'] === 'Dismissed') $iss_pill = 'pr-pill-dismissed';
                                        ?>
                                            <tr>
                                                <td class="td-sm">#<?php echo (int)$issue['id']; ?></td>
                                                <td class="td-fw"><?php echo htmlspecialchars($issue['room_name']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['floor_label'] . ', ' . $issue['building_name']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['reported_by_name']); ?></td>
                                                <td style="max-width:220px;font-size:.82rem;"><?php echo htmlspecialchars($issue['description']); ?></td>
                                                <td><span class="pr-pill <?php echo $iss_pill; ?>"><?php echo htmlspecialchars($issue['status']); ?></span></td>
                                                <td class="td-sm" style="white-space:nowrap;"><?php echo date('M d, Y g:i A', strtotime($issue['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($issue['status'] === 'Open'): ?>
                                                        <button class="pr-tbl-btn review btn-action btn-override-req"
                                                            data-action="open-issue-review"
                                                            data-issue-id="<?php echo (int)$issue['id']; ?>"
                                                            data-room-name="<?php echo htmlspecialchars($issue['room_name']); ?>"
                                                            data-reporter="<?php echo htmlspecialchars($issue['reported_by_name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($issue['description']); ?>"
                                                            title="Review this issue report">
                                                            <span class="material-symbols-outlined" style="font-size:14px;">rate_review</span>
                                                            Review
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="td-sm">
                                                            <?php echo $issue['status']; ?>
                                                            <?php if ($issue['admin_notes']): ?>
                                                                <br><small><?php echo htmlspecialchars(mb_substr($issue['admin_notes'], 0, 60)); ?><?php echo strlen($issue['admin_notes']) > 60 ? '…' : ''; ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-issues-panel -->




                <!-- ── Rooms: JSON data + schedule JS ──────────────────── -->
                <?php
                // Encode rooms list for schedule JS
                $ps_rooms_json = array_map(function ($r) {
                    return [
                        'room_id'    => (int)$r['room_id'],
                        'room_name'  => $r['room_name'],
                        'campus'     => $r['campus_name'],
                        'building'   => $r['building_name'],
                        'floor'      => !empty($r['floor_label']) ? $r['floor_label'] : ($r['floor_number'] . 'F'),
                        'capacity'   => $r['seating_capacity'],
                    ];
                }, $rooms_list ?? []);

                // Approved reservations only (these appear on the schedule grid)
                $ps_rsvp_json = array_values(array_filter(
                    array_map(function ($ar) {
                        return [
                            'id'           => (int)$ar['id'],
                            'room_id'      => isset($ar['room_id']) ? (int)$ar['room_id'] : null,
                            'room_name'    => $ar['room_name'],
                            'date'         => $ar['reservation_date'],
                            'start_fmt'    => $ar['start_fmt'],
                            'end_fmt'      => $ar['end_fmt'],
                            'label'        => !empty($ar['purpose']) ? $ar['purpose'] : $ar['faculty_name'],
                            'faculty'      => $ar['faculty_name'],
                            'status'       => $ar['status'],
                        ];
                    }, $admin_room_reservations ?? []),
                    function ($ar) {
                        return $ar['status'] === 'Approved';
                    }
                ));
                ?>
                <script nonce="<?php echo $csp_nonce; ?>">
                    /* ── PUPSync Room Schedule module ──────────────────────── */
                    (function() {
                        'use strict';

                        /* — Data injected from PHP — */
                        const PS_ROOMS = <?php echo json_encode(array_values($ps_rooms_json)); ?>;
                        const PS_RSVP = <?php echo json_encode($ps_rsvp_json); ?>;

                        /* — Time slots shown in the grid — */
                        const SLOTS = [{
                                label: '7–8 AM',
                                h: 7
                            },
                            {
                                label: '8–9 AM',
                                h: 8
                            },
                            {
                                label: '9–10 AM',
                                h: 9
                            },
                            {
                                label: '10–11 AM',
                                h: 10
                            },
                            {
                                label: '11 AM–12 PM',
                                h: 11
                            },
                            {
                                label: '12–1 PM',
                                h: 12
                            },
                            {
                                label: '1–2 PM',
                                h: 13
                            },
                            {
                                label: '2–3 PM',
                                h: 14
                            },
                            {
                                label: '3–4 PM',
                                h: 15
                            },
                            {
                                label: '4–5 PM',
                                h: 16
                            },
                        ];
                        const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                        ];

                        /* — State — */
                        let _roomId = null;
                        let _roomName = '';
                        let _weekOff = 0; // 0 = current week

                        /* — Helpers — */
                        function parseHour(fmt) {
                            if (!fmt) return 0;
                            const [timePart, period] = fmt.trim().split(/\s+/);
                            let [h] = (timePart || '0:00').split(':').map(Number);
                            if ((period || '').toUpperCase() === 'PM' && h !== 12) h += 12;
                            if ((period || '').toUpperCase() === 'AM' && h === 12) h = 0;
                            return h;
                        }

                        function getMondayDate(offset) {
                            const today = new Date();
                            const d = today.getDay(); // 0=Sun
                            const diff = d === 0 ? -6 : (1 - d); // to Monday
                            const mon = new Date(today);
                            mon.setDate(today.getDate() + diff + offset * 7);
                            mon.setHours(0, 0, 0, 0);
                            return mon;
                        }

                        function getWeekDates(offset) {
                            const mon = getMondayDate(offset);
                            return Array.from({
                                length: 5
                            }, (_, i) => {
                                const d = new Date(mon);
                                d.setDate(mon.getDate() + i);
                                return d;
                            });
                        }

                        function toISO(d) {
                            const y = d.getFullYear();
                            const m = String(d.getMonth() + 1).padStart(2, '0');
                            const day = String(d.getDate()).padStart(2, '0');
                            return `${y}-${m}-${day}`;
                        }

                        function weekLabel(offset) {
                            const dates = getWeekDates(offset);
                            const s = dates[0],
                                e = dates[4];
                            if (s.getMonth() === e.getMonth())
                                return `${MONTHS[s.getMonth()]} ${s.getDate()}–${e.getDate()}, ${s.getFullYear()}`;
                            return `${MONTHS[s.getMonth()]} ${s.getDate()} – ${MONTHS[e.getMonth()]} ${e.getDate()}, ${e.getFullYear()}`;
                        }

                        /* — Render the weekly grid — */
                        function renderGrid() {
                            const dates = getWeekDates(_weekOff);
                            const dateISOs = dates.map(toISO);

                            // Update week label
                            const lbl = document.getElementById('rsm-week-lbl');
                            if (lbl) lbl.textContent = weekLabel(_weekOff);

                            // Filter reservations for this room & week
                            const rsvps = PS_RSVP.filter(r => {
                                const sameRoom = _roomId ?
                                    (r.room_id === _roomId) :
                                    (r.room_name === _roomName);
                                return sameRoom && dateISOs.includes(r.date);
                            });

                            // Build lookup: dateISO → [ reservations ]
                            const lookup = {};
                            rsvps.forEach(r => {
                                if (!lookup[r.date]) lookup[r.date] = [];
                                lookup[r.date].push(r);
                            });

                            // Build rows
                            const gridBody = document.getElementById('rsm-grid-body');
                            if (!gridBody) return;
                            gridBody.innerHTML = '';

                            SLOTS.forEach(slot => {
                                const row = document.createElement('div');
                                row.className = 'rsm-grid-row';

                                // Time label cell
                                const tc = document.createElement('div');
                                tc.className = 'rsm-grid-time';
                                tc.textContent = slot.label;
                                row.appendChild(tc);

                                // Day cells
                                dates.forEach((date, di) => {
                                    const cell = document.createElement('div');
                                    cell.className = 'rsm-grid-cell';

                                    const dayRsvps = lookup[dateISOs[di]] || [];
                                    const hit = dayRsvps.find(r => {
                                        const sh = parseHour(r.start_fmt);
                                        const eh = parseHour(r.end_fmt);
                                        return slot.h >= sh && slot.h < eh;
                                    });

                                    if (hit) {
                                        const blk = document.createElement('span');
                                        blk.className = 'rsm-block';
                                        const txt = (hit.label || hit.faculty || 'Reserved').substring(0, 20);
                                        blk.textContent = txt;
                                        blk.title = `${hit.faculty} — ${hit.label || ''}`.replace(/^—\s*/, '');
                                        cell.appendChild(blk);
                                    }
                                    row.appendChild(cell);
                                });

                                gridBody.appendChild(row);
                            });
                        }

                        /* — Public API — */
                        window.psOpenSchedule = function(btn) {
                            const card = btn.closest('.pr-room-row');
                            if (!card) return;
                            _roomId = parseInt(card.dataset.roomId) || null;
                            _roomName = card.dataset.roomName || '';
                            _weekOff = 0;
                            const title = document.getElementById('rsm-title');
                            if (title) title.textContent = 'Room Schedule – ' + _roomName;
                            const meta = document.getElementById('rsm-meta');
                            if (meta) {
                                const campus = card.dataset.roomCampus || '';
                                const floor = card.dataset.roomFloor || '';
                                const capacity = card.dataset.roomCapacity || '';
                                let parts = [campus, floor].filter(Boolean).join(' – ');
                                if (capacity) parts += ' – Capacity: ' + capacity;
                                meta.textContent = parts;
                            }
                            const modal = document.getElementById('roomScheduleModal');
                            if (modal) modal.classList.remove('hidden');
                            try {
                                renderGrid();
                            } catch (e) {
                                console.warn('renderGrid error:', e);
                            }
                        };

                        window.psCloseSchedule = function() {
                            const modal = document.getElementById('roomScheduleModal');
                            if (modal) modal.classList.add('hidden');
                        };

                        window.psScheduleNav = function(dir) {
                            _weekOff += dir;
                            renderGrid();
                        };

                        /* ── edit-room-inline: populate & show form without page reload ── */
                        document.addEventListener('click', function(e) {
                            var btn = e.target.closest('[data-action="edit-room-inline"]');
                            if (!btn) return;
                            var card = btn.closest('.pr-room-row');
                            if (!card) return;
                            var d = card.dataset;
                            var form = document.getElementById('roomForm');
                            if (!form) return;

                            /* Populate all fields */
                            var el;
                            el = document.getElementById('room-form-id');
                            if (el) el.value = d.roomId || '';

                            el = form.querySelector('select[name="building_id"]');
                            if (el) el.value = d.roomBuildingId || '';

                            el = form.querySelector('input[name="room_name"]');
                            if (el) el.value = d.roomName || '';

                            el = form.querySelector('input[name="floor_number"]');
                            if (el) el.value = d.roomFloorNum || '1';

                            el = form.querySelector('input[name="floor_label"]');
                            if (el) el.value = d.roomFloorLabel || '';

                            el = form.querySelector('input[name="seating_capacity"]');
                            if (el) el.value = d.roomCapacity || '';

                            el = form.querySelector('select[name="status"]');
                            if (el) el.value = d.roomStatus || 'Available';

                            el = form.querySelector('input[name="sort_order"]');
                            if (el) el.value = d.roomSort || '0';

                            var amenities = [];
                            try {
                                amenities = JSON.parse(d.roomAmenities || '[]');
                            } catch (x) {}
                            form.querySelectorAll('input[name="amenities[]"]').forEach(function(cb) {
                                cb.checked = amenities.indexOf(cb.value) !== -1;
                            });

                            /* Update title + icon */
                            el = document.getElementById('room-form-title');
                            if (el) el.textContent = 'Edit Room — ' + (d.roomName || '');
                            el = document.querySelector('#room-form-wrap .rmod-head-icon .material-symbols-outlined');
                            if (el) el.textContent = 'edit';

                            /* Switch submit buttons to update_room */
                            ['room-form-submit-inner', 'room-form-submit-foot'].forEach(function(id) {
                                var b = document.getElementById(id);
                                if (b) b.name = 'update_room';
                            });
                            el = document.getElementById('room-submit-inner-label');
                            if (el) el.textContent = 'Update Room';
                            el = document.getElementById('room-submit-foot-label');
                            if (el) el.textContent = 'Save Changes';

                            /* Archive zone */
                            var az = document.getElementById('room-archive-zone');
                            if (az) az.classList.remove('hidden');
                            var al = document.getElementById('room-archive-link');
                            if (al) al.href = 'admin-dashboard.php?archive_room=' + encodeURIComponent(d.roomId || '');

                            /* Show form and scroll */
                            var rfw = document.getElementById('room-form-wrap');
                            if (rfw) {
                                rfw.classList.remove('hidden');
                                setTimeout(function() {
                                    rfw.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start'
                                    });
                                }, 30);
                            }
                        });

                        /* Reset form to Add-mode when Cancel/Close is clicked */
                        document.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="hide-room-form"]')) return;
                            var el;
                            el = document.getElementById('room-form-title');
                            if (el) el.textContent = 'Add New Room';
                            el = document.querySelector('#room-form-wrap .rmod-head-icon .material-symbols-outlined');
                            if (el) el.textContent = 'add_home_work';
                            ['room-form-submit-inner', 'room-form-submit-foot'].forEach(function(id) {
                                var b = document.getElementById(id);
                                if (b) b.name = 'add_room';
                            });
                            el = document.getElementById('room-submit-inner-label');
                            if (el) el.textContent = 'Add Room';
                            el = document.getElementById('room-submit-foot-label');
                            if (el) el.textContent = 'Add Room';
                            var az = document.getElementById('room-archive-zone');
                            if (az) az.classList.add('hidden');
                            el = document.getElementById('room-form-id');
                            if (el) el.value = '';
                        });

                        document.addEventListener('click', function(e) {
                            const modal = document.getElementById('roomScheduleModal');
                            if (e.target.closest('[data-action="open-room-schedule"]')) {
                                window.psOpenSchedule(e.target.closest('[data-action="open-room-schedule"]'));
                                return;
                            }
                            if (e.target.closest('[data-action="close-room-schedule"]')) {
                                window.psCloseSchedule();
                                return;
                            }
                            const navBtn = e.target.closest('[data-action="room-schedule-nav"]');
                            if (navBtn) {
                                window.psScheduleNav(parseInt(navBtn.dataset.dir));
                                return;
                            }
                            if (modal && !modal.classList.contains('hidden') && e.target === modal)
                                window.psCloseSchedule();
                            const rfw = document.getElementById('room-form-wrap');
                            if (rfw && !rfw.classList.contains('hidden') && e.target === rfw)
                                rfw.classList.add('hidden');
                        });
                    })();
                </script>

            </div><!-- /panel-rooms -->


            <!-- ============================================================
         TAB: FACULTY
    ============================================================ -->
            <div class="tab-panel" id="panel-faculty">

                <div style="margin-bottom:1.5rem">
                    <h2 style="font-size:1.3rem;font-weight:700;color:var(--text-dark)">Faculty Management</h2>
                    <p style="color:var(--text-light);font-size:12.5px;margin-top:2px">Create and manage faculty accounts. Enable or disable org borrowing privileges.</p>
                </div>

                <div class="faculty-layout">

                    <!-- CREATE FORM -->
                    <div class="eq-card faculty-form-card">
                        <div class="eq-card-header">
                            <h2>
                                <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">person_add</span>
                                Create Faculty Account
                            </h2>
                        </div>
                        <div class="eq-card-body">
                            <?= csrf_field() ?>

                            <div class="form-group">
                                <label for="fac-email">PUPSync Email <span class="req-star">*</span></label>
                                <input type="email" id="fac-email" name="pupsync_email"
                                    class="form-control-custom" maxlength="254" required
                                    placeholder="faculty@example.com">
                            </div>

                            <div class="form-group">
                                <label for="fac-backup">Google / Backup Email</label>
                                <input type="email" id="fac-backup" name="backup_email"
                                    class="form-control-custom" maxlength="254"
                                    placeholder="backup@gmail.com">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fac-first">First Name <span class="req-star">*</span></label>
                                    <input type="text" id="fac-first" name="first_name"
                                        class="form-control-custom" maxlength="100" required placeholder="First">
                                </div>
                                <div class="form-group">
                                    <label for="fac-middle">Middle Name</label>
                                    <input type="text" id="fac-middle" name="middle_name"
                                        class="form-control-custom" maxlength="100" placeholder="Middle">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="fac-last">Last Name <span class="req-star">*</span></label>
                                <input type="text" id="fac-last" name="last_name"
                                    class="form-control-custom" maxlength="100" required placeholder="Last">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fac-password">Password <span class="req-star">*</span></label>
                                    <div class="fac-pw-wrap">
                                        <input type="password" id="fac-password" name="password"
                                            class="form-control-custom" maxlength="128" required
                                            placeholder="Min. 8 characters">
                                        <button type="button" class="fac-pw-toggle"
                                            data-target="fac-password" aria-label="Toggle password">
                                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="fac-confirm">Confirm Password <span class="req-star">*</span></label>
                                    <div class="fac-pw-wrap">
                                        <input type="password" id="fac-confirm" name="confirm_password"
                                            class="form-control-custom" maxlength="128" required
                                            placeholder="Re-enter password">
                                        <button type="button" class="fac-pw-toggle"
                                            data-target="fac-confirm" aria-label="Toggle password">
                                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div style="background:var(--secondary-cream);border-radius:10px;padding:0.85rem;border:1px solid var(--khaki-border);margin-bottom:1rem">
                                <div class="form-group faculty-adviser-toggle-wrap" style="margin-bottom:0.65rem">
                                    <label class="faculty-toggle-label">
                                        <input type="checkbox" id="fac-adviser" name="is_org_adviser"
                                            value="1" class="faculty-toggle-input">
                                        <span class="faculty-toggle-track"></span>
                                        Organization adviser
                                    </label>
                                </div>
                                <div id="fac-org-group" style="display:none;">
                                    <label style="font-size:12px;font-weight:600;margin-bottom:4px;display:block">Organization</label>
                                    <?php
                                    $org_opts_res = $conn->query(
                                        "SELECT id, name FROM tbl_organizations ORDER BY name ASC"
                                    );
                                    if ($org_opts_res && $org_opts_res->num_rows > 0): ?>
                                        <select id="fac-org" name="organization_id"
                                            class="form-control-custom">
                                            <option value="">&#8212; Select Organization &#8212;</option>
                                            <?php while ($org_row = $org_opts_res->fetch_assoc()): ?>
                                                <option value="<?= (int)$org_row['id'] ?>">
                                                    <?= htmlspecialchars($org_row['name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    <?php else: ?>
                                        <select id="fac-org" name="organization_id"
                                            class="form-control-custom" disabled>
                                            <option value="">&#8212; Organizations unavailable &#8212;</option>
                                        </select>
                                        <small class="faculty-field-error">
                                            Could not load organizations.
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div id="fac-form-alert" class="alert-banner hidden" role="alert"></div>

                            <button type="button" id="fac-submit-btn"
                                class="ps-btn ps-btn--primary" style="width:100%">
                                <span class="material-symbols-outlined">person_add</span>
                                Create Account
                            </button>
                        </div><!-- /eq-card-body -->
                    </div><!-- /faculty-form-card -->


                    <!-- FACULTY LIST -->
                    <div class="eq-card">
                        <div class="eq-card-header" style="flex-wrap:wrap;gap:0.75rem">
                            <h2>
                                <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">group</span>
                                Faculty List
                                <span class="fac-count-badge">(<?php
                                                                $fac_count = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_users");
                                                                echo ($fac_count) ? (int)$fac_count->fetch_assoc()['cnt'] : 0;
                                                                ?>)</span>
                            </h2>
                            <div style="display:flex;gap:6px;align-items:center;margin-left:auto">
                                <input type="text" id="fac-search-input"
                                    class="form-control-custom"
                                    style="width:180px;font-size:12px"
                                    placeholder="Search faculty...">
                                <button class="ps-btn ps-btn--ghost ps-btn--sm" id="fac-gen-code-btn">
                                    <span class="material-symbols-outlined">key</span> Gen Code
                                </button>
                            </div>
                        </div>
                        <div class="tbl-wrap">
                            <table class="admin-table" id="fac-list-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Faculty ID</th>
                                        <th>Email</th>
                                        <th>Org Borrowing</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="faculty-list-tbody">
                                    <?php
                                    $_aob_col = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'allow_org_borrowing'");
                                    $_has_aob_col = $_aob_col && $_aob_col->num_rows > 0;
                                    $fac_res = $conn->query(
                                        "SELECT u.fullname, u.email, u.role,"
                                            . " u.faculty_id,"
                                            . ($_has_aob_col ? " u.allow_org_borrowing," : " 0 AS allow_org_borrowing,")
                                            . "     o.name AS org_name"
                                            . " FROM tbl_users u"
                                            . " LEFT JOIN tbl_organizations o ON u.organization_id = o.id"
                                            . " ORDER BY u.fullname ASC"
                                    );
                                    if ($fac_res && $fac_res->num_rows > 0):
                                        while ($frow = $fac_res->fetch_assoc()):
                                            $isAdviser = ($frow['role'] === 'Organization Adviser');
                                            $subLabel  = $isAdviser && !empty($frow['org_name'])
                                                ? 'Org Adviser &middot; ' . htmlspecialchars($frow['org_name'])
                                                : 'Active Faculty';
                                            $initFac   = strtoupper(substr($frow['fullname'] ?? 'F', 0, 1));
                                    ?>
                                            <tr
                                                data-fullname="<?= htmlspecialchars($frow['fullname']) ?>"
                                                data-email="<?= htmlspecialchars($frow['email']) ?>"
                                                data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                                                data-role="<?= htmlspecialchars($frow['role']) ?>"
                                                data-org="<?= htmlspecialchars($frow['org_name'] ?? '') ?>"
                                                data-aob="<?= $frow['allow_org_borrowing'] ? '1' : '0' ?>"
                                                data-init="<?= $initFac ?>">
                                                <td>
                                                    <div style="font-weight:600"><?= htmlspecialchars($frow['fullname']) ?></div>
                                                    <div style="font-size:11px;color:var(--text-light)"><?= $subLabel ?></div>
                                                </td>
                                                <td style="font-size:12px;color:var(--text-light)"><?= htmlspecialchars($frow['faculty_id']) ?></td>
                                                <td style="font-size:12px"><?= htmlspecialchars($frow['email']) ?></td>
                                                <td>
                                                    <label class="faculty-toggle-label">
                                                        <input type="checkbox"
                                                            class="faculty-toggle-input org-borrowing-toggle"
                                                            data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                                                            <?= $frow['allow_org_borrowing'] == 1 ? 'checked' : '' ?>>
                                                        <span class="faculty-toggle-track"></span>
                                                    </label>
                                                </td>
                                                <td>
                                                    <button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn">
                                                        <span class="material-symbols-outlined">edit</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr id="fac-empty-row">
                                            <td colspan="5"
                                                style="text-align:center;padding:3rem;color:var(--text-light)">
                                                <span class="material-symbols-outlined"
                                                    style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>
                                                No faculty accounts yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div><!-- /faculty-list-card -->

                </div><!-- /faculty-layout -->
            </div><!-- /panel-faculty -->



            <!-- ============================================================
         TAB: INVENTORY
    ============================================================ -->
            <div class="tab-panel" id="panel-inventory">

                <!-- ── INVENTORY SCREEN (REDESIGNED) ─────────────────── -->
                <div id="lending-inventory">

                    <!-- Page Header -->
                    <div class="inv-redesign-header">
                        <div>
                            <h1 class="inv-page-title">Equipment Inventory</h1>
                            <p class="inv-page-sub">Manage the equipment catalog, stock quantities, and item details.</p>
                        </div>
                    </div>

                    <!-- Split Layout -->
                    <div class="inv-split-layout">

                        <!-- LEFT: Equipment List -->
                        <div class="inv-list-col">
                            <div class="eq-card inv-list-card" id="inv-table-card">
                                <div class="inv-list-header">
                                    <h3 class="inv-list-title">
                                        <span class="material-symbols-outlined">inventory_2</span>
                                        All Equipment (<?php echo mysqli_num_rows($inventory_result); ?>)
                                    </h3>
                                    <input type="text" id="inventorySearch" class="inv-search-ctrl"
                                        placeholder="Search...">
                                </div>
                                <div class="inv-list-body" id="inventory-body">
                                    <?php
                                    mysqli_data_seek($inventory_result, 0);
                                    if (mysqli_num_rows($inventory_result) === 0): ?>
                                        <div class="inv-empty-state">
                                            <span class="material-symbols-outlined">inventory_2</span>
                                            <p>Inventory is empty.</p>
                                        </div>
                                        <?php else: while ($item = mysqli_fetch_assoc($inventory_result)): ?>
                                            <div class="inv-row-item"
                                                data-item-id="<?php echo (int)$item['item_id']; ?>"
                                                data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                                                data-item-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                                                data-item-quantity="<?php echo (int)$item['quantity']; ?>"
                                                data-item-condition="<?php echo htmlspecialchars($item['condition'] ?? 'Good', ENT_QUOTES); ?>"
                                                data-item-description="<?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES); ?>"
                                                data-item-image="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES); ?>"
                                                data-item-image-full="<?php echo htmlspecialchars($root_url . $item['image_path'], ENT_QUOTES); ?>">
                                                <div class="inv-row-thumb">
                                                    <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                        alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                        onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                                                    <div class="inv-thumb-ph">
                                                        <span class="material-symbols-outlined">inventory_2</span>
                                                    </div>
                                                </div>
                                                <div class="inv-row-info">
                                                    <div class="i-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                    <div class="i-cat"><?php echo htmlspecialchars($item['category']); ?></div>
                                                </div>
                                                <div class="inv-row-qty">
                                                    <div class="q-val<?php
                                                                        if ($item['quantity'] == 0)     echo ' q-none';
                                                                        elseif ($item['quantity'] <= 2) echo ' q-low';
                                                                        ?>">
                                                        <?php echo $item['quantity']; ?>
                                                    </div>
                                                    <div class="q-lbl"><?php
                                                                        if ($item['quantity'] > 2)     echo 'in stock';
                                                                        elseif ($item['quantity'] > 0) echo 'low stock';
                                                                        else                           echo 'no stock';
                                                                        ?></div>
                                                </div>
                                                <div class="inv-row-actions">
                                                    <button type="button" class="btn-inv-edit" title="Edit item"
                                                        data-action="eq-open-edit">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            width="14" height="14">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                    <?php endwhile;
                                    endif; ?>
                                </div>
                            </div>
                        </div><!-- /inv-list-col -->

                        <!-- RIGHT: Add / Edit Form -->
                        <div class="inv-form-col">
                            <div class="eq-card inv-form-card" id="item-form-wrap">
                                <div class="inv-form-header">
                                    <h3>
                                        <span class="material-symbols-outlined">add_box</span>
                                        <span id="form-title">
                                            <?php echo $edit_item ? 'Edit Equipment' : 'Add / Edit Equipment'; ?>
                                        </span>
                                    </h3>
                                </div>
                                <div class="inv-form-body">
                                    <form method="POST" enctype="multipart/form-data" id="itemForm">
                                        <?= csrf_field() ?>
                                        <?php if ($edit_item): ?>
                                            <input type="hidden" name="item_id"
                                                value="<?php echo $edit_item['item_id']; ?>">
                                            <input type="hidden" name="old_image"
                                                value="<?php echo htmlspecialchars($edit_item['image_path']); ?>">
                                        <?php endif; ?>

                                        <div class="form-group">
                                            <label>Item Name <span class="inv-req">*</span></label>
                                            <input type="text" name="item_name" class="form-control-custom"
                                                value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_name']) : ''; ?>"
                                                placeholder="e.g. Extension Cord" required>
                                        </div>

                                        <div class="form-group">
                                            <label>Category <span class="inv-req">*</span></label>
                                            <select name="category" class="form-control-custom" required>
                                                <option value="">Select category...</option>
                                                <?php
                                                $cats = ['Audio/Visual', 'Cables & Connectors', 'Computing', 'Lab Equipment', 'Networking', 'Power', 'Tools', 'Others'];
                                                foreach ($cats as $c) {
                                                    $sel = ($edit_item && $edit_item['category'] === $c) ? 'selected' : '';
                                                    echo "<option value=\"$c\" $sel>$c</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control-custom" rows="3"
                                                placeholder="Short description of the item..."></textarea>
                                        </div>

                                        <div class="inv-form-row">
                                            <div class="form-group">
                                                <label>Quantity <span class="inv-req">*</span></label>
                                                <input type="number" name="quantity" class="form-control-custom"
                                                    min="0"
                                                    value="<?php echo $edit_item ? $edit_item['quantity'] : '1'; ?>"
                                                    required>
                                            </div>
                                            <div class="form-group">
                                                <label>Condition</label>
                                                <select name="condition" class="form-control-custom">
                                                    <?php
                                                    $cur_condition = $edit_item['condition'] ?? 'Good';
                                                    foreach (['Good', 'Fair', 'For Repair'] as $condOpt) {
                                                        $sel = ($cur_condition === $condOpt) ? 'selected' : '';
                                                        echo "<option value=\"$condOpt\" $sel>$condOpt</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Item Image</label>
                                            <div class="drop-zone" id="dropZone">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    width="32" height="32" style="color:var(--text-light)">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                                    <polyline points="21 15 16 10 5 21" />
                                                </svg>
                                                <p>Click to upload, drag &amp; drop, or paste an image</p>
                                                <input type="file" name="item_image" id="itemImageInput"
                                                    accept="image/jpeg,image/png" style="display:none;">
                                                <?php if ($edit_item && $edit_item['image_path'] !== 'uploads/default.png'): ?>
                                                    <img src="<?php echo $root_url . htmlspecialchars($edit_item['image_path']); ?>"
                                                        class="drop-zone-preview" id="imagePreview" style="display:block;">
                                                <?php else: ?>
                                                    <img id="imagePreview" class="drop-zone-preview" style="display:none;">
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" id="removeImageBtn"
                                                class="<?php echo ($edit_item && $edit_item['image_path'] !== 'uploads/default.png') ? '' : 'hidden'; ?>"
                                                style="margin-top:6px;font-size:0.75rem;color:var(--danger);background:none;border:none;cursor:pointer;">
                                                &#x2715; Remove image
                                            </button>
                                        </div>

                                        <div class="inv-form-actions">
                                            <button type="submit"
                                                name="<?php echo $edit_item ? 'update_item' : 'add_item'; ?>"
                                                class="btn-inv-save">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    width="15" height="15">
                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                                    <polyline points="17 21 17 13 7 13 7 21" />
                                                    <polyline points="7 3 7 8 15 8" />
                                                </svg>
                                                <?php echo $edit_item ? 'Update Item' : 'Save Equipment'; ?>
                                            </button>
                                            <?php if ($edit_item): ?>
                                                <a href="admin-dashboard.php?delete_item=<?php echo $edit_item['item_id']; ?>"
                                                    class="btn-inv-delete" title="Archive item"
                                                    onclick="return confirm('Archive this item? It will no longer be available for lending, but borrow history is preserved.');">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        width="15" height="15">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14H6L5 6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                        <path d="M9 6V4h6v2" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div><!-- /inv-form-col -->

                    </div><!-- /inv-split-layout -->

                    <!-- Archived Items -->
                    <div class="inv-archived-wrap">
                        <div class="history-toggle-wrap inv-arch-toggle" id="registry-toggle-wrap">
                            <button class="history-toggle-btn" data-history-tab="reg-archived">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="14" height="14">
                                    <polyline points="21 8 21 21 3 21 3 8" />
                                    <rect x="1" y="3" width="22" height="5" />
                                    <line x1="10" y1="12" x2="14" y2="12" />
                                </svg>
                                Archived Items
                            </button>
                        </div>
                        <div class="history-panel" id="history-reg-archived">
                            <div class="eq-card">
                                <div class="tbl-wrap">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Item Name</th>
                                                <th>Category</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($archive_result) === 0): ?>
                                                <tr>
                                                    <td colspan="4" class="text-muted"
                                                        style="text-align:center;padding:2.5rem;">
                                                        No archived items.
                                                    </td>
                                                </tr>
                                                <?php else: while ($item = mysqli_fetch_assoc($archive_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                                class="item-img"
                                                                onerror="this.src='../uploads/default.png'">
                                                        </td>
                                                        <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                        <td class="action-cell">
                                                            <div class="action-btns">
                                                                <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>"
                                                                    class="btn-action btn-restore" title="Restore">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        width="14" height="14">
                                                                        <polyline points="1 4 1 10 7 10" />
                                                                        <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                                    </svg>
                                                                </a>
                                                                <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>"
                                                                    class="btn-action btn-force-del" title="Delete permanently"
                                                                    onclick="return confirm('Permanently delete? This cannot be undone.')">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        width="14" height="14">
                                                                        <polyline points="3 6 5 6 21 6" />
                                                                        <path d="M19 6l-1 14H6L5 6" />
                                                                        <path d="M10 11v6" />
                                                                        <path d="M14 11v6" />
                                                                        <path d="M9 6V4h6v2" />
                                                                    </svg>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                            <?php endwhile;
                                            endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div><!-- /history-reg-archived -->
                    </div><!-- /inv-archived-wrap -->

                </div><!-- /lending-inventory -->

            </div><!-- /panel-inventory -->

            <!-- ============================================================
    <!-- ============================================================
         TAB: SETTINGS  (streamlined — consolidates the old Settings tab,
         Account overlay, Arbitration tab, and Help Center overlay into
         one screen with four sub-tabs)
    ============================================================ -->
            <div class="tab-panel" id="panel-settings">

                <!-- Page Header -->
                <div class="sett-page-header">
                    <h1 class="sett-page-title">Settings</h1>
                    <p class="sett-page-sub">Manage your account, preferences, borrowing rules, and get help.</p>
                </div>

                <!-- Settings mega sub-tabs -->
                <div class="rq-sub-tabs" id="settMainTabs">
                    <button class="rq-sub-tab active" data-sett-panel="sett-account">My Account</button>
                    <button class="rq-sub-tab" data-sett-panel="sett-prefs">Preferences</button>
                    <button class="rq-sub-tab" data-sett-panel="sett-rules">Borrowing Rules</button>
                    <button class="rq-sub-tab" data-sett-panel="sett-help">Help &amp; FAQ</button>
                </div>

                <!-- ── MY ACCOUNT ─────────────────────────────────────── -->
                <div class="rq-sub-panel active" id="sett-account">

                    <!-- Profile Hero -->
                    <div class="ov-profile-hero" id="acctHero">
                        <div class="ov-av-lg">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div class="ov-hero-body">
                            <div class="ov-hero-name"><?php echo htmlspecialchars($admin_name); ?></div>
                            <div class="ov-hero-role">Administrator &middot; PUPSync Biñan Campus</div>
                            <div class="ov-hero-badge">
                                <span class="material-symbols-outlined">verified</span>
                                Active &middot; Full Access
                            </div>
                        </div>
                        <div class="ov-hero-actions">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">
                                <span class="material-symbols-outlined">edit</span>
                                Edit Profile
                            </button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;"
                                data-action="profile-save">
                                <span class="material-symbols-outlined">save</span>
                                Save Changes
                            </button>
                            <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;"
                                data-action="profile-cancel">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <div class="ps-two-col">
                        <div>
                            <!-- Personal Information -->
                            <div class="info-card" id="profileInfoCard">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">person</span>
                                        Personal Information
                                    </h3>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Display Name</span>
                                    <span class="info-val <?php echo empty($admin_name) ? 'empty' : ''; ?>"
                                        data-field="admin_name">
                                        <?php echo !empty($admin_name) ? htmlspecialchars($admin_name) : '— Not provided'; ?>
                                    </span>
                                    <input class="info-input-f" data-input="admin_name"
                                        value="<?php echo htmlspecialchars($admin_name ?? ''); ?>"
                                        placeholder="Display Name" disabled style="display:none;">
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Role</span>
                                    <span class="info-val">Administrator</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Email Address</span>
                                    <span class="info-val <?php echo empty($admin_email) ? 'empty' : ''; ?>"
                                        data-field="admin_email">
                                        <?php echo !empty($admin_email) ? htmlspecialchars($admin_email) : '— Not provided'; ?>
                                    </span>
                                    <input class="info-input-f" data-input="admin_email" type="email"
                                        value="<?php echo htmlspecialchars($admin_email ?? ''); ?>"
                                        placeholder="admin@pup.edu.ph" disabled style="display:none;">
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Campus</span>
                                    <span class="info-val">PUPSync Biñan Campus</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Access Level</span>
                                    <span class="info-val">
                                        <span class="ov-access-badge">Full Access</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Activity Summary -->
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">bar_chart</span>
                                        Activity Summary
                                    </h3>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Requests Processed</span>
                                    <span class="info-val ov-stat-val">
                                        <?php echo $stat_total_req ?? '0'; ?> total
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Faculty Accounts Created</span>
                                    <span class="info-val">
                                        <?php
                                        $fac_count = mysqli_fetch_assoc(
                                            mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_users WHERE role='faculty'")
                                        )['c'] ?? 0;
                                        echo $fac_count;
                                        ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Last Login</span>
                                    <span class="info-val">
                                        <?php
                                        $ll = '— Not available';
                                        if (!empty($_SESSION['admin_last_login'])) {
                                            $ts = strtotime($_SESSION['admin_last_login']);
                                            if ($ts !== false) $ll = date('M d, Y · g:i A', $ts);
                                        }
                                        echo htmlspecialchars($ll);
                                        ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Active Equipment</span>
                                    <span class="info-val"><?php echo $stat_inv_total ?? '0'; ?> items</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <!-- Password & Security -->
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">lock</span>
                                        Password &amp; Security
                                    </h3>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Current Password</span>
                                    <span class="info-val">&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;</span>
                                    <button class="btn-inline-sm" data-action="open-change-pass">Change</button>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Last Changed</span>
                                    <span class="info-val" id="pwLastChangedVal" style="color:var(--text-light)">
                                        <?php
                                        $pwc = '— Not tracked';
                                        if (!empty($admin_last_pw_change)) {
                                            $ts_pwc = strtotime($admin_last_pw_change);
                                            if ($ts_pwc !== false) $pwc = date('M d, Y · g:i A', $ts_pwc);
                                        }
                                        echo htmlspecialchars($pwc);
                                        ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Session Status</span>
                                    <span class="info-val">
                                        <span class="ov-status-dot">Active</span>
                                    </span>
                                    <button class="btn-inline-danger" data-action="logout">Log Out All</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /sett-account -->

                <!-- ── PREFERENCES ────────────────────────────────────── -->
                <div class="rq-sub-panel" id="sett-prefs">
                    <div class="sett-grid">
                        <div class="sett-card">
                            <div class="sett-card-head">
                                <span class="material-symbols-outlined">palette</span>
                                <h3>Appearance</h3>
                            </div>
                            <div class="sett-card-body">
                                <div class="sett-field-lbl">Theme</div>
                                <div class="sett-theme-row">
                                    <div class="sett-theme-opt sett-theme-sel" id="tp-light" data-action="apply-theme" data-theme="light">
                                        &#9728;&#65039; Light
                                    </div>
                                    <div class="sett-theme-opt" id="tp-dark" data-action="apply-theme" data-theme="dark">
                                        &#127769; Dark
                                    </div>
                                    <div class="sett-theme-opt" id="tp-hc" data-action="apply-theme" data-theme="high-contrast">
                                        &#9889; High Contrast
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sett-card">
                            <div class="sett-card-head">
                                <span class="material-symbols-outlined">notifications</span>
                                <h3>Notification Preferences</h3>
                            </div>
                            <div class="sett-card-body sett-card-body--notifs">
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="notifPrefRequestsToggle" checked>
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">New Requests</div>
                                        <div class="sett-notif-sub">Alert when faculty submits a request</div>
                                    </div>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="notifPrefOverdueToggle" checked>
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Overdue Alerts</div>
                                        <div class="sett-notif-sub">Alert when an item becomes overdue</div>
                                    </div>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="notifPrefRoomToggle">
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Room Issues</div>
                                        <div class="sett-notif-sub">Alert when a room issue is reported</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sett-card">
                            <div class="sett-card-head">
                                <span class="material-symbols-outlined">accessibility</span>
                                <h3>Accessibility</h3>
                            </div>
                            <div class="sett-card-body">
                                <div class="form-group">
                                    <label class="sett-field-lbl">Text Size</label>
                                    <select class="form-control-custom" id="textSizeSelect">
                                        <option value="Normal">Normal</option>
                                        <option value="Large">Large</option>
                                        <option value="X-Large">X-Large</option>
                                    </select>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="reduceMotionToggle" data-action="apply-reduce-motion">
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Reduce animations</div>
                                    </div>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="focusRingToggle" data-action="apply-focus-ring">
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Enhanced Focus Ring</div>
                                        <div class="sett-notif-sub">Makes keyboard focus outlines more visible</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /sett-grid -->

                    <div class="sett-danger-wrap">
                        <div class="sett-danger-wrap">
                            <div class="sett-card sett-danger-bdr">
                                <div class="sett-card-head">
                                    <span class="material-symbols-outlined" style="color:var(--danger)">warning</span>
                                    <h3 style="color:var(--danger)">Advanced</h3>
                                </div>
                                <div class="sett-card-body">
                                    <div class="sett-notif-row">
                                        <label class="toggle-sw"><input type="checkbox" id="showAssetIdsToggle" checked><span class="toggle-track"></span></label>
                                        <div>
                                            <div class="sett-notif-title">Show Asset IDs</div>
                                            <div class="sett-notif-sub">Display equipment item IDs in tables</div>
                                        </div>
                                    </div>
                                    <div class="sett-notif-row">
                                        <label class="toggle-sw"><input type="checkbox" id="verboseErrorsToggle"><span class="toggle-track"></span></label>
                                        <div>
                                            <div class="sett-notif-title">Verbose Error Messages</div>
                                            <div class="sett-notif-sub">Show detailed database error info (not recommended in production)</div>
                                        </div>
                                    </div>
                                    <div class="sett-adv-reset-row">
                                        <div>
                                            <div class="sett-notif-title" style="color:var(--danger)">Reset All Settings</div>
                                            <div class="sett-notif-sub">Restore all appearance and accessibility defaults</div>
                                        </div>
                                        <button class="sett-reset-btn" data-action="reset-settings">
                                            <span class="material-symbols-outlined">restart_alt</span> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div><!-- /sett-prefs -->

                <!-- ── BORROWING RULES (was the standalone Arbitration tab) ── -->
                <div class="rq-sub-panel" id="sett-rules">
                    <!-- Sub-tabs -->
                    <div class="rq-sub-tabs" id="arbSubTabs">
                        <button class="rq-sub-tab active" data-arb-panel="arb-sub-config">Configuration</button>
                        <button class="rq-sub-tab" data-arb-panel="arb-sub-log">Decision Log</button>
                    </div>

                    <!-- ── CONFIGURATION ─────────────────────────────────────── -->
                    <div class="arb-sub-panel active" id="arb-sub-config">
                        <div class="ps-two-col">
                            <!-- Left: Rules -->
                            <div class="ps-card">
                                <div class="ps-card-header">
                                    <h3><span class="material-symbols-outlined">rule</span> Auto-Approval Rules</h3>
                                </div>
                                <div class="ps-card-body">
                                    <div class="arb-rule">
                                        <h4>
                                            <span class="material-symbols-outlined">check_circle</span>
                                            Auto-approve Faculty Requests
                                            <label class="ps-toggle" style="margin-left:auto">
                                                <input type="checkbox" checked>
                                                <span class="ps-toggle-track"></span>
                                            </label>
                                        </h4>
                                        <p>Automatically approve equipment requests from verified faculty with cleared status.</p>
                                    </div>
                                    <div class="arb-rule">
                                        <h4>
                                            <span class="material-symbols-outlined">block</span>
                                            Block Overdue Borrowers
                                            <label class="ps-toggle" style="margin-left:auto">
                                                <input type="checkbox"
                                                    <?php echo (($arb_config['rule_overdue_block_enabled'] ?? '1') == '1') ? 'checked' : ''; ?>>
                                                <span class="ps-toggle-track"></span>
                                            </label>
                                        </h4>
                                        <p>Automatically decline new requests from users with overdue items.</p>
                                    </div>
                                    <div class="arb-rule">
                                        <h4>
                                            <span class="material-symbols-outlined">inventory</span>
                                            Stock-Based Rejection
                                            <label class="ps-toggle" style="margin-left:auto">
                                                <input type="checkbox"
                                                    <?php echo (($arb_config['rule_duplicate_block_enabled'] ?? '0') == '1') ? 'checked' : ''; ?>>
                                                <span class="ps-toggle-track"></span>
                                            </label>
                                        </h4>
                                        <p>Automatically decline if available stock falls below minimum threshold.</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Right: Thresholds -->
                            <div class="ps-card">
                                <div class="ps-card-header">
                                    <h3><span class="material-symbols-outlined">tune</span> Thresholds &amp; Limits</h3>
                                </div>
                                <div class="ps-card-body">
                                    <div class="ps-form-group">
                                        <label class="ps-form-label">Maximum Borrow Days</label>
                                        <input class="ps-form-control" type="number" min="1" max="60"
                                            value="<?php echo htmlspecialchars($arb_config['max_borrow_days'] ?? 7); ?>">
                                        <div class="ps-form-hint">Items must be returned within this many days.</div>
                                    </div>
                                    <div class="ps-form-group">
                                        <label class="ps-form-label">Max Items Per Borrower</label>
                                        <input class="ps-form-control" type="number" min="1" max="20"
                                            value="<?php echo htmlspecialchars($arb_config['max_items_per_borrower'] ?? 3); ?>">
                                        <div class="ps-form-hint">Maximum number of items a user can have at once.</div>
                                    </div>
                                    <div class="ps-form-group">
                                        <label class="ps-form-label">Low Stock Threshold</label>
                                        <input class="ps-form-control" type="number" min="0" max="10"
                                            value="<?php echo htmlspecialchars($arb_config['low_stock_threshold'] ?? 2); ?>">
                                        <div class="ps-form-hint">Trigger low-stock alert when quantity drops below this.</div>
                                    </div>
                                    <button type="button" class="ps-btn ps-btn--primary"
                                        style="width:100%;justify-content:center;margin-top:0.25rem">
                                        <span class="material-symbols-outlined">save</span> Save Configuration
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div><!-- /arb-sub-config -->

                    <!-- ── DECISION LOG ───────────────────────────────────────── -->
                    <div class="arb-sub-panel" id="arb-sub-log">
                        <div class="ps-card">
                            <div class="ps-card-header">
                                <h3><span class="material-symbols-outlined">history</span> Decision Log</h3>
                                <select class="ps-form-control" style="width:160px" id="arb-log-filter">
                                    <option value="">All decisions</option>
                                    <option value="Approved">Auto-approved</option>
                                    <option value="Declined">Auto-declined</option>
                                </select>
                            </div>
                            <div class="ps-table-wrap">
                                <table class="ps-table" id="arb-log-new-table">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Decision</th>
                                            <th>Rule Triggered</th>
                                            <th>Borrower</th>
                                            <th>Equipment</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!$arb_log_result || mysqli_num_rows($arb_log_result) === 0):
                                        ?>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="ps-empty-state">
                                                        <span class="material-symbols-outlined">history</span>
                                                        <p>No arbitration log entries yet.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php else:
                                            mysqli_data_seek($arb_log_result, 0);
                                            while ($r = mysqli_fetch_assoc($arb_log_result)):
                                                $dec    = $r['decision'];
                                                $dbadge = ($dec === 'Approved') ? 'ps-badge--active' : 'ps-badge--overdue';
                                            ?>
                                                <tr data-decision="<?php echo htmlspecialchars($dec); ?>">
                                                    <td style="font-weight:600;color:var(--accent-maroon)">
                                                        <?php echo htmlspecialchars($r['request_id']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="ps-badge ps-badge--dot <?php echo $dbadge; ?>">
                                                            <?php echo htmlspecialchars($dec); ?>
                                                        </span>
                                                    </td>
                                                    <td style="font-size:12px;color:var(--text-light)">
                                                        <?php echo htmlspecialchars($r['rule_applied'] ?? '—'); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($r['borrower_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                    <td style="font-size:12px;color:var(--text-light)">
                                                        <?php echo date('M d, g:i A', strtotime($r['created_at'])); ?>
                                                    </td>
                                                </tr>
                                        <?php endwhile;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- /arb-sub-log -->

                </div><!-- /sett-rules -->

                <!-- ── HELP & FAQ (was the standalone Help Center overlay) ─── -->
                <div class="rq-sub-panel" id="sett-help">

                    <div class="ps-two-col">
                        <div>
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">quiz</span>
                                        Common Questions
                                    </h3>
                                </div>
                                <div class="info-card-body" style="padding:0.5rem 1.25rem 1.25rem;">
                                    <div class="hc-faq-list">

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I approve or decline a borrow request?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Requests</strong> in the sidebar. Pending requests appear at the top with an orange badge. Click <strong>Approve</strong> to confirm the loan or <strong>Decline</strong> to reject it — you can add a reason when declining. The faculty member will see the updated status on their portal immediately.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I add new equipment to the inventory?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Inventory</strong> and click <strong>+ Add Equipment</strong>. Fill in the item name, description, quantity, condition, and category. Items become available for borrowing immediately after saving. You can also set an item to "Not Available" if it's under repair.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">What happens when I archive a room?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Archiving a room hides it from faculty when they submit new reservations. Existing reservation history is preserved. You can restore an archived room at any time from <strong>Rooms → Archived</strong> tab by clicking <strong>Restore</strong>.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do faculty members submit room reservations?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Faculty log in to their portal and go to <strong>Room Reservations</strong>. They select the campus, building, room, date, time slot, and purpose. The request appears in your <strong>Rooms → Reservations</strong> panel. The room slot is not blocked until you confirm it.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I manage or create faculty accounts?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Faculty</strong> in the sidebar. You can view all active faculty accounts, review their request history, and reset passwords. Click <strong>+ Add Faculty</strong> to create a new account. New accounts receive a default password that must be changed on first login.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">What is the Arbitration panel used for?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Arbitration handles conflicts when two or more faculty members compete for the same resource — a room or equipment — at the same time. You review both claims, see who has stronger grounds, and make a final ruling. The ruling overrides the normal approval flow and is logged.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I handle a reported room issue?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                In <strong>Rooms → Issues</strong>, you'll see all open reports submitted by faculty. Click <strong>Review</strong> on any open issue. You can mark it as <em>Resolved</em> (problem fixed, room stays active) or <em>Dismissed</em> (not a valid concern). Note: rooms are <strong>not</strong> automatically set to Maintenance — you must edit the room status separately if needed.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">Can I restore an archived room or equipment item?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Yes. For rooms, go to <strong>Rooms → Archived</strong> and click <strong>Restore</strong> next to the room. For equipment, go to <strong>Inventory → Archived</strong> and click <strong>Restore</strong>. The item or room returns to the active list immediately and is available again.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I cancel an approved room reservation?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Rooms → Reservations</strong>. Find the reservation with an <em>Approved</em> status and click <strong>Cancel</strong>. You can add a reason for the cancellation. Please note: you cannot cancel a reservation within 1 hour of its scheduled start time — this restriction is enforced to protect faculty planning.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">What do the different request statuses mean?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                <div class="hc-status-table">
                                                    <div class="hc-st-row"><span class="hc-st-pill pending">Pending</span><span>Awaiting admin review and action.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill approved">Approved</span><span>Admin confirmed — item loaned or room reserved.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill declined">Declined</span><span>Admin rejected the request.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill cancelled">Cancelled</span><span>Cancelled by the admin or the faculty member.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill returned">Returned</span><span>Equipment was returned and processed (lending only).</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill overdue">Overdue</span><span>Item not returned by the agreed return date.</span></div>
                                                </div>
                                            </div>
                                        </details>

                                    </div><!-- /.hc-faq-list -->
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">contact_support</span>
                                        Contact Support
                                    </h3>
                                </div>
                                <div class="ps-empty-state" style="padding:2rem 1.25rem;">
                                    <span class="material-symbols-outlined">mail</span>
                                    <p>For system issues, contact the PUPSync development team at<br>
                                        <strong>pupsync.support@pup.edu.ph</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /sett-help -->

            </div><!-- /panel-settings -->

        </main><!-- /app-main -->

    </div><!-- /app-body -->

    <!-- ================================================================
         REQUESTS PANEL MODALS — outside app-body for correct fixed positioning
    ================================================================ -->

    <!-- ================================================================
             MODALS — REQUESTS PANEL  (UI only — functions wired in Phase 4)
        ================================================================ -->

    <!-- MODAL: APPROVE REQUEST -->
    <div class="ps-modal-backdrop" id="ps-approve-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--success">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <h3>Approve Request</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-approve-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="approve-request-id" value="">
                <div id="ps-approve-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-approve-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="approve-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="approve-equipment">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Date Needed</span><span class="ps-rsv" id="approve-date-needed">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Return Due Date</label>
                    <input class="ps-form-control" type="date" id="approve-due-date" readonly disabled>
                    <div class="ps-form-hint">Set by the borrower when they submitted the request.</div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason / Note <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters — kept in the arbitration log)</span></label>
                    <textarea class="ps-form-control" id="approve-reason" rows="2" placeholder="e.g. Item available, request meets policy..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-approve-modal">Cancel</button>
                <button class="ps-btn ps-btn--success" id="ps-approve-confirm-btn">
                    <span class="material-symbols-outlined">check</span> Approve Request
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: DECLINE REQUEST -->
    <div class="ps-modal-backdrop" id="ps-decline-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">cancel</span>
                </div>
                <h3>Decline Request</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-decline-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="decline-request-id" value="">
                <div id="ps-decline-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-decline-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="decline-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="decline-equipment">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason for Declining <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters)</span></label>
                    <textarea class="ps-form-control" id="decline-reason" rows="3" placeholder="e.g. Item is currently in use by another borrower..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-decline-modal">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="ps-decline-confirm-btn">
                    <span class="material-symbols-outlined">close</span> Decline Request
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: CONFIRM RETURN -->
    <div class="ps-modal-backdrop" id="ps-return-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment_return</span>
                </div>
                <h3>Confirm Return</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-return-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" style="font-weight:600">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Student ID</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Due Date</span><span class="ps-rsv" style="color:var(--danger);font-weight:600">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Return Condition</label>
                    <select class="ps-form-control">
                        <option>Good — No visible damage</option>
                        <option>Fair — Minor wear</option>
                        <option>Damaged — Needs repair</option>
                        <option>Missing — Item not returned</option>
                    </select>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Admin Notes <span style="font-size:11px;color:var(--text-light)">(optional)</span></label>
                    <textarea class="ps-form-control" rows="2" placeholder="Any notes about the return..."></textarea>
                </div>
                <div class="ps-alert ps-alert--info" style="margin:0">
                    <span class="material-symbols-outlined">info</span>
                    Confirming will update the stock count and close this request.
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-return-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" data-action="ps-close-modal" data-modal="ps-return-modal">
                    <span class="material-symbols-outlined">check</span> Confirm Return
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: SEND OVERDUE NOTICE -->
    <div class="ps-modal-backdrop" id="ps-notice-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--warning">
                    <span class="material-symbols-outlined">mail</span>
                </div>
                <h3>Send Overdue Notice</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-notice-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <div class="ps-req-summary" style="margin-bottom:1rem">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" style="font-weight:600">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Student ID</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Days Overdue</span><span class="ps-rsv" style="color:var(--danger);font-weight:600">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Message Preview</label>
                    <div class="ps-notice-preview">Dear [Borrower Name],

                        This is a reminder that the following item borrowed from the PUPSync Equipment Lending System is now overdue:

                        • Item: [Equipment Name]
                        • Due Date: [Due Date]
                        • Days Overdue: [N] days

                        Please return the item as soon as possible to avoid further penalties.

                        Thank you,
                        PUPSync Admin — Biñan Campus</div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Delivery Method</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin-top:6px">
                        <input type="checkbox" checked> Send via Email
                    </label>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-notice-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" data-action="ps-close-modal" data-modal="ps-notice-modal">
                    <span class="material-symbols-outlined">send</span> Send Notice
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: REQUEST DETAIL -->
    <div class="ps-modal-backdrop" id="ps-req-detail-modal">
        <div class="ps-modal ps-modal--lg">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment</span>
                </div>
                <h3>Request Details</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-req-detail-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="detail-request-id" value="">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.25rem">
                    <span class="ps-badge ps-badge--waiting ps-badge--dot" id="detail-status-badge" style="font-size:12px;padding:4px 12px">Waiting for Approval</span>
                    <span style="font-size:11.5px;color:var(--text-light)">Submitted: <span id="detail-submitted">—</span></span>
                </div>
                <div class="ps-detail-grid" style="margin-bottom:1.25rem">
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Requester</div>
                        <div class="ps-detail-value" id="detail-requester" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Student / Faculty ID</div>
                        <div class="ps-detail-value" id="detail-id-number">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Requester Type</div>
                        <div class="ps-detail-value" id="detail-req-type">Faculty</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Room</div>
                        <div class="ps-detail-value" id="detail-room" style="font-size:12px">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Equipment</div>
                        <div class="ps-detail-value" id="detail-equipment" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Quantity</div>
                        <div class="ps-detail-value">1</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Date Needed</div>
                        <div class="ps-detail-value" id="detail-date-needed" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Return By</div>
                        <div class="ps-detail-value" id="detail-return-by">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Condition</div>
                        <div class="ps-detail-value"><span class="ps-badge ps-badge--dot ps-badge--active" id="detail-condition-badge">Good</span></div>
                    </div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Instructor / Assigned To</label>
                    <div id="detail-instructor" style="background:var(--secondary-cream);border-radius:8px;padding:0.75rem;font-size:12.5px;color:var(--text-dark);border:1px solid var(--khaki-border)">—</div>
                </div>
                <div style="background:var(--secondary-cream);border-radius:10px;padding:0.85rem;font-size:12px;color:var(--text-light);border:1px solid var(--khaki-border)">
                    <strong style="color:var(--text-dark)">Arbitration Status:</strong> <span id="detail-arb-status">Pending review.</span>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-req-detail-modal">Close</button>
                <button class="ps-btn ps-btn--danger" id="ps-detail-decline-btn" data-action="ps-switch-modal" data-close="ps-req-detail-modal" data-open="ps-decline-modal">
                    <span class="material-symbols-outlined">close</span> Decline
                </button>
                <button class="ps-btn ps-btn--success" id="ps-detail-approve-btn" data-action="ps-switch-modal" data-close="ps-req-detail-modal" data-open="ps-approve-modal">
                    <span class="material-symbols-outlined">check</span> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: EXPORT REQUESTS -->
    <div class="ps-modal-backdrop" id="ps-export-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--info">
                    <span class="material-symbols-outlined">download</span>
                </div>
                <h3>Export Requests</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-export-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <div class="ps-form-group">
                    <label class="ps-form-label">Date Range</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <div style="font-size:11px;color:var(--text-light);margin-bottom:3px">From</div>
                            <input class="ps-form-control" type="date" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-light);margin-bottom:3px">To</div>
                            <input class="ps-form-control" type="date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Filter by Status</label>
                    <select class="ps-form-control">
                        <option value="">All Statuses</option>
                        <option value="Waiting">Waiting</option>
                        <option value="Approved">Active Borrowings</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Returned">Returned</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Export Format</label>
                    <div style="display:flex;gap:10px;margin-top:4px">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="export-fmt" value="csv" checked> CSV
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="export-fmt" value="xlsx"> Excel (.xlsx)
                        </label>
                    </div>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-export-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" data-action="ps-close-modal" data-modal="ps-export-modal">
                    <span class="material-symbols-outlined">download</span> Export
                </button>
            </div>
        </div>
    </div>




    <!-- ============================================================
         MODAL: NOTIFICATIONS  (redesigned to match the rest of the
         admin — ps-modal shell, rq-filter-chip pills, ps-detail-grid
         detail rows, instead of the old standalone overlay-page.)
    ============================================================ -->
    <div class="ps-modal-backdrop" id="notifOverlay">
        <div class="ps-modal ps-modal--lg notif-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">notifications</span>
                </div>
                <h3>Notifications</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="notifOverlay" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="notif-toolbar">
                <p class="notif-unread-line">You have
                    <strong id="unreadCount"><?php echo $stat_waiting + $stat_overdue + 2; ?> unread</strong>
                    notifications.
                </p>
                <button class="ps-btn ps-btn--ghost ps-btn--sm" data-action="mark-all-read">Mark all as read</button>
            </div>

            <div class="rq-filter-chips notif-filter-chips">
                <button class="rq-filter-chip active" data-notif-filter="all">All</button>
                <button class="rq-filter-chip" data-notif-filter="unread">Unread</button>
                <button class="rq-filter-chip" data-notif-filter="request">Requests</button>
                <button class="rq-filter-chip" data-notif-filter="overdue">Overdue</button>
                <button class="rq-filter-chip" data-notif-filter="system">System</button>
            </div>

            <div class="ps-modal-body notif-modal-body">

                <?php if ($stat_overdue > 0): ?>
                    <div class="notif-group-label notif-group-label--danger">
                        <span class="material-symbols-outlined">warning</span>
                        Overdue &mdash; Immediate Action Needed
                    </div>
                    <?php
                    $ov_notif = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status='Overdue' ORDER BY return_date ASC LIMIT 5");
                    while ($on = mysqli_fetch_assoc($ov_notif)):
                        $days_late = floor((time() - strtotime($on['return_date'])) / 86400);
                    ?>
                        <div class="notif-item notif-card unread notif-urgent" data-cat="overdue">
                            <div class="notif-card-main" role="button" tabindex="0">
                                <div class="notif-icon notif-icon--danger">
                                    <span class="material-symbols-outlined">schedule</span>
                                </div>
                                <div class="notif-body-wrap">
                                    <h4>Overdue: <?php echo htmlspecialchars($on['equipment_name']); ?></h4>
                                    <p><strong><?php echo htmlspecialchars($on['faculty_name']); ?></strong> has not returned this item.
                                        <?php echo $days_late; ?> day<?php echo $days_late != 1 ? 's' : ''; ?> overdue.</p>
                                </div>
                                <div class="notif-meta">
                                    <span class="notif-time">Due <?php echo date('M d', strtotime($on['return_date'])); ?></span>
                                    <div class="unread-dot"></div>
                                    <span class="material-symbols-outlined notif-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="notif-card-detail">
                                <div class="ps-detail-grid">
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Borrower</div>
                                        <div class="ps-detail-value"><?php echo htmlspecialchars($on['faculty_name']); ?> (<?php echo htmlspecialchars($on['faculty_id']); ?>)</div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Equipment</div>
                                        <div class="ps-detail-value"><?php echo htmlspecialchars($on['equipment_name']); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Due Date</div>
                                        <div class="ps-detail-value" style="color:var(--danger);font-weight:600;"><?php echo date('M d, Y', strtotime($on['return_date'])); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Days Overdue</div>
                                        <div class="ps-detail-value" style="color:var(--danger);font-weight:700;"><?php echo $days_late; ?> day<?php echo $days_late != 1 ? 's' : ''; ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Borrow Date</div>
                                        <div class="ps-detail-value"><?php echo date('M d, Y', strtotime($on['borrow_date'])); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Room / Instructor</div>
                                        <div class="ps-detail-value"><?php echo htmlspecialchars($on['room'] ?? 'â'); ?> / <?php echo htmlspecialchars($on['instructor'] ?? 'â'); ?></div>
                                    </div>
                                </div>
                                <div class="notif-card-actions">
                                    <a href="admin-dashboard.php?view=overdue" class="ps-btn ps-btn--primary ps-btn--sm"
                                        data-action="ps-close-modal" data-modal="notifOverlay">
                                        <span class="material-symbols-outlined">visibility</span>
                                        View in Overdue
                                    </a>
                                    <button class="ps-btn ps-btn--ghost ps-btn--sm" data-notif-dismiss>Got it</button>
                                </div>
                            </div>
                        </div>
                <?php endwhile;
                endif; ?>

                <?php if ($stat_waiting > 0): ?>
                    <div class="notif-group-label">
                        <span class="material-symbols-outlined">assignment</span>
                        Pending Requests
                    </div>
                    <?php
                    $wt_notif = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status='Waiting' ORDER BY request_date DESC LIMIT 5");
                    while ($wn = mysqli_fetch_assoc($wt_notif)):
                    ?>
                        <div class="notif-item notif-card unread" data-cat="request">
                            <div class="notif-card-main" role="button" tabindex="0">
                                <div class="notif-icon notif-icon--maroon">
                                    <span class="material-symbols-outlined">pending_actions</span>
                                </div>
                                <div class="notif-body-wrap">
                                    <h4>New Borrow Request</h4>
                                    <p><strong><?php echo htmlspecialchars($wn['faculty_name']); ?></strong> requested
                                        <strong><?php echo htmlspecialchars($wn['equipment_name']); ?></strong> &mdash; awaiting approval.
                                    </p>
                                </div>
                                <div class="notif-meta">
                                    <span class="notif-time"><?php echo date('M d', strtotime($wn['request_date'])); ?></span>
                                    <div class="unread-dot"></div>
                                    <span class="material-symbols-outlined notif-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="notif-card-detail">
                                <div class="ps-detail-grid">
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Borrower</div>
                                        <div class="ps-detail-value"><?php echo htmlspecialchars($wn['faculty_name']); ?> (<?php echo htmlspecialchars($wn['faculty_id']); ?>)</div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Equipment</div>
                                        <div class="ps-detail-value"><?php echo htmlspecialchars($wn['equipment_name']); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Borrow Date</div>
                                        <div class="ps-detail-value"><?php echo date('M d, Y', strtotime($wn['borrow_date'])); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Return Date</div>
                                        <div class="ps-detail-value"><?php echo date('M d, Y', strtotime($wn['return_date'])); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Requested On</div>
                                        <div class="ps-detail-value"><?php echo date('M d, Y g:i A', strtotime($wn['request_date'])); ?></div>
                                    </div>
                                    <div class="ps-detail-item">
                                        <div class="ps-detail-label">Room / Instructor</div>
                                        <div class="ps-detail-value"><?php echo htmlspecialchars($wn['room'] ?? 'â'); ?> / <?php echo htmlspecialchars($wn['instructor'] ?? 'â'); ?></div>
                                    </div>
                                </div>
                                <div class="notif-card-actions">
                                    <a href="admin-dashboard.php?view=requests" class="ps-btn ps-btn--primary ps-btn--sm"
                                        data-action="ps-close-modal" data-modal="notifOverlay">
                                        <span class="material-symbols-outlined">visibility</span>
                                        View in Requests
                                    </a>
                                    <button class="ps-btn ps-btn--ghost ps-btn--sm" data-notif-dismiss>Got it</button>
                                </div>
                            </div>
                        </div>
                <?php endwhile;
                endif; ?>

                <div class="notif-group-label">
                    <span class="material-symbols-outlined">info</span>
                    System
                </div>

                <div class="notif-item notif-card unread" data-cat="system">
                    <div class="notif-card-main" role="button" tabindex="0">
                        <div class="notif-icon notif-icon--info">
                            <span class="material-symbols-outlined">info</span>
                        </div>
                        <div class="notif-body-wrap">
                            <h4>Scheduled Maintenance Tonight</h4>
                            <p>PUPSync will undergo maintenance from 11:00 PM to 1:00 AM. Please inform users.</p>
                        </div>
                        <div class="notif-meta">
                            <span class="notif-time">8:00 AM</span>
                            <div class="unread-dot"></div>
                            <span class="material-symbols-outlined notif-chevron">expand_more</span>
                        </div>
                    </div>
                    <div class="notif-card-detail">
                        <div class="ps-detail-grid">
                            <div class="ps-detail-item">
                                <div class="ps-detail-label">Window</div>
                                <div class="ps-detail-value">11:00 PM &ndash; 1:00 AM tonight</div>
                            </div>
                            <div class="ps-detail-item">
                                <div class="ps-detail-label">Affected</div>
                                <div class="ps-detail-value">All PUPSync services (lending, inventory, login)</div>
                            </div>
                            <div class="ps-detail-item">
                                <div class="ps-detail-label">Action Required</div>
                                <div class="ps-detail-value">Notify active users before 10:30 PM</div>
                            </div>
                        </div>
                        <div class="notif-card-actions">
                            <button class="ps-btn ps-btn--ghost ps-btn--sm" data-notif-dismiss>Got it</button>
                        </div>
                    </div>
                </div>

                <div class="notif-item notif-card" data-cat="system">
                    <div class="notif-card-main" role="button" tabindex="0">
                        <div class="notif-icon notif-icon--success">
                            <span class="material-symbols-outlined">check_circle</span>
                        </div>
                        <div class="notif-body-wrap">
                            <h4>Database Backup Completed</h4>
                            <p>Automatic daily backup of <code>lending_db</code> completed successfully.</p>
                        </div>
                        <div class="notif-meta">
                            <span class="notif-time">Yesterday, 2:00 AM</span>
                            <span class="material-symbols-outlined notif-chevron">expand_more</span>
                        </div>
                    </div>
                    <div class="notif-card-detail">
                        <div class="ps-detail-grid">
                            <div class="ps-detail-item">
                                <div class="ps-detail-label">Database</div>
                                <div class="ps-detail-value">lending_db</div>
                            </div>
                            <div class="ps-detail-item">
                                <div class="ps-detail-label">Completed At</div>
                                <div class="ps-detail-value">Yesterday at 2:00 AM</div>
                            </div>
                            <div class="ps-detail-item">
                                <div class="ps-detail-label">Status</div>
                                <div class="ps-detail-value"><span class="ps-badge ps-badge--dot ps-badge--active">Success</span></div>
                            </div>
                        </div>
                        <div class="notif-card-actions">
                            <button class="ps-btn ps-btn--ghost ps-btn--sm" data-notif-dismiss>Got it</button>
                        </div>
                    </div>
                </div>

            </div><!-- /notif-modal-body -->
        </div>
    </div><!-- /notifOverlay -->


    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top:1rem;font-weight:600;color:var(--text-dark);font-size:0.9rem;">Processing...</p>
    </div>

    <!-- QR Scanner Modal -->
    <div id="qrScannerModal" class="qr-scanner-backdrop">
        <div class="qr-scanner-card">
            <button id="closeQrScanner" class="qr-scanner-close" title="Close scanner">✕</button>
            <h3>Scan Return QR Code</h3>
            <p>Point the camera at the faculty member's QR code.</p>
            <div class="qr-video-wrap">
                <video id="qrVideo" playsinline autoplay></video>
                <!-- Scan guide overlay -->
                <div class="qr-scan-guide">
                    <div class="qr-scan-guide-box"></div>
                </div>
            </div>
            <p id="qrScanStatus">
                Initializing camera...
            </p>
        </div>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>


    <div class="modal-overlay" id="changePassModal" style="display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.6); 
            z-index: 99999; 
            align-items: center; 
            justify-content: center;">
        <div class="modal-backdrop" data-action="close-change-pass" style="position: absolute; inset: 0;"></div>
        <div class="eq-card form-card"
            style="position: relative; width: 100%; max-width: 400px; margin: 20px; z-index: 100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    Change Password
                </h2>
                <button type="button" class="btn-close-custom" data-action="close-change-pass">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="cp-alert"
                    style="display:none; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 500;">
                </div>

                <form id="changePasswordForm">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="cp-current-password">Current Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-current-password" name="current_password"
                                class="form-control-custom" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-current-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cp-new-password">New Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-new-password" name="new_password"
                                class="form-control-custom" minlength="4" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-new-password,cp-confirm-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cp-confirm-password">Confirm New Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-confirm-password" name="confirm_password"
                                class="form-control-custom" minlength="4" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-new-password,cp-confirm-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn-cancel-acc" data-action="close-change-pass"
                            style="padding: 8px 16px; width: auto;">Cancel</button>
                        <button type="submit" class="btn-submit-form"
                            style="margin-top: 0; width: auto; padding: 8px 16px;">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Cancel Reservation Modal -->
    <div class="modal-overlay" id="adminCancelRrModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="adminCancelRrBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:420px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                    Cancel Reservation
                </h2>
                <button type="button" class="btn-close-custom" id="closeAdminCancelRrModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="admin-cancel-rr-alert" style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:.85rem;font-weight:500;"></div>
                <p id="adminCancelRrDesc" style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <p style="font-size:.83rem;color:var(--text-light);margin-bottom:1rem;">
                    The faculty member will be notified by email. All faculty on the waitlist for this slot will also be notified that the slot is now available.
                </p>
                <input type="hidden" id="adminCancelRrId">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelAdminCancelRrBtn" style="padding:8px 16px;width:auto;">Go Back</button>
                    <button type="button" class="btn-submit-form" id="submitAdminCancelRrBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;background:var(--danger);">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Issue Review Modal -->
    <div class="modal-overlay" id="issueReviewModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="issueReviewModalBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:460px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Review Issue Report
                </h2>
                <button type="button" class="btn-close-custom" id="closeIssueReviewModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="issue-review-alert" style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:.85rem;font-weight:500;"></div>
                <p id="issueReviewDesc" style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <div class="form-group">
                    <label>Admin Notes <span style="font-size:.75rem;font-weight:400;">(optional)</span></label>
                    <textarea id="issueAdminNotes" class="form-control-custom" rows="3"
                        placeholder="Internal notes about this report or action taken…"
                        style="resize:vertical;"></textarea>
                </div>
                <div class="form-group" style="margin-top:.75rem;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
                        <input type="checkbox" id="issueSetMaintenance" style="width:16px;height:16px;">
                        Set room to <strong>Maintenance</strong> mode
                    </label>
                    <small style="color:var(--text-light);font-size:.75rem;margin-top:4px;display:block;">
                        Only applies when resolving (not dismissing). You can change the room status back any time from the Rooms registry.
                    </small>
                </div>
                <input type="hidden" id="issueReviewId">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1.25rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelIssueReviewBtn" style="padding:8px 16px;width:auto;">Cancel</button>
                    <button type="button" class="btn-submit-form" id="dismissIssueBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;background:#6b7280;">Dismiss</button>
                    <button type="button" class="btn-submit-form" id="resolveIssueBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;">Mark Resolved</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Override Modal -->
    <div class="modal-overlay" id="overrideModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="overrideModalBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:440px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Override Request
                </h2>
                <button type="button" class="btn-close-custom" id="closeOverrideModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="override-alert"
                    style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:0.85rem;font-weight:500;">
                </div>
                <p id="overrideDesc" style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <!-- Context info shown when direction is fixed (Approved→Declined or Declined→Approved) -->
                <div id="overrideContextInfo"
                    style="display:none;padding:10px 12px;border-radius:6px;margin-bottom:1rem;font-size:0.83rem;font-weight:500;">
                </div>
                <div class="form-group" id="overrideStatusGroup" style="display:none;">
                    <label>New Status</label>
                    <select id="overrideNewStatus" class="form-control-custom">
                        <option value="Approved">Approved</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Override Reason <span style="color:var(--danger);">*</span></label>
                    <textarea id="overrideReason" class="form-control-custom" rows="3"
                        placeholder="Enter mandatory reason for this override (min. 5 characters)..."
                        style="resize:vertical;"></textarea>
                    <small id="overrideReasonHint" style="color:var(--text-light);font-size:0.75rem;">Minimum 5
                        characters required.</small>
                </div>
                <input type="hidden" id="overrideRequestId">
                <input type="hidden" id="overrideCurrentStatus">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelOverrideBtn"
                        style="padding:8px 16px;width:auto;">Cancel</button>
                    <button type="button" class="btn-submit-form" id="submitOverrideBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;" disabled>Apply Override</button>
                </div>
            </div>
        </div>
    </div>

    <script src="equipment-booking/assets/js/admin-dashboard.js"></script>
    <script src="equipment-booking/assets/js/admin-live-render.js"></script>

    <!-- Admin poll toast -->
    <div id="admin-poll-toast">
    </div>

    <!-- Modal + sub-tab functions (inline for guaranteed global scope) -->
    <script nonce="<?php echo $csp_nonce; ?>">
        /* ── Modal open / close ─────────────────────────────────────── */
        function psOpenModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('ps-modal-open');
        }

        function psCloseModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('ps-modal-open');
        }

        /* Close on backdrop click */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ps-modal-backdrop').forEach(function(bd) {
                bd.addEventListener('click', function(e) {
                    if (e.target === bd) bd.classList.remove('ps-modal-open');
                });
            });

            /* Requests filter-chip switching */
            var rqTabs = document.getElementById('rqTabs');
            if (rqTabs) {
                rqTabs.querySelectorAll('.rq-filter-chip').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        rqTabs.querySelectorAll('.rq-filter-chip').forEach(function(t) {
                            t.classList.remove('active');
                        });
                        document.querySelectorAll('#panel-requests .rq-sub-panel').forEach(function(p) {
                            p.classList.remove('active');
                        });
                        tab.classList.add('active');
                        var panel = document.getElementById(tab.dataset.rqPanel);
                        if (panel) panel.classList.add('active');
                    });
                });
            }

            /* Live search — Waiting table */
            var waitSearch = document.getElementById('rq-waiting-search');
            var waitTable = document.getElementById('rq-waiting-table');
            if (waitSearch && waitTable) {
                waitSearch.addEventListener('input', function() {
                    var q = waitSearch.value.toLowerCase();
                    waitTable.querySelectorAll('tbody tr').forEach(function(row) {
                        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                    });
                });
            }

            /* Live search + return-date range filter — Returned Requests */
            var allSearch = document.getElementById('rq-all-search');
            var allRange = document.getElementById('rq-all-range');
            var allTable = document.getElementById('rq-all-table');

            function inReturnRange(dateStr, range) {
                if (!dateStr) return true; // rows with no date (e.g. empty-state) always show
                var d = new Date(dateStr + 'T00:00:00');
                if (isNaN(d.getTime())) return true;
                var now = new Date();
                var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                if (range === 'today') {
                    return d.getTime() === todayStart.getTime();
                }
                if (range === 'week') {
                    var weekStart = new Date(todayStart);
                    weekStart.setDate(todayStart.getDate() - todayStart.getDay());
                    return d.getTime() >= weekStart.getTime() && d.getTime() <= todayStart.getTime();
                }
                if (range === 'month') {
                    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
                }
                if (range === 'year') {
                    return d.getFullYear() === now.getFullYear();
                }
                return true;
            }

            function filterAll() {
                var q = allSearch ? allSearch.value.toLowerCase() : '';
                var range = allRange ? allRange.value : 'month';
                if (!allTable) return;
                allTable.querySelectorAll('tbody tr').forEach(function(row) {
                    var matchQ = !q || row.textContent.toLowerCase().includes(q);
                    var matchRange = inReturnRange(row.dataset.returnDate, range);
                    row.style.display = (matchQ && matchRange) ? '' : 'none';
                });
            }
            if (allSearch) allSearch.addEventListener('input', filterAll);
            if (allRange) allRange.addEventListener('change', filterAll);
            filterAll(); // apply the default "This Month" range immediately
        });
    </script>


    <!-- ── MODAL: EDIT EQUIPMENT ────────────────────────────────── -->
    <div class="ps-modal-backdrop" id="eq-edit-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                <h3>Edit Equipment</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="eq-edit-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form method="POST" enctype="multipart/form-data" id="eqEditForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" id="eqm-item-id">
                    <input type="hidden" name="old_image" id="eqm-old-image">
                    <input type="hidden" name="remove_image" id="eqm-remove-image-flag" value="0">

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Item Name <span class="inv-req">*</span></label>
                        <input type="text" name="item_name" id="eqm-item-name" class="form-control-custom"
                            placeholder="e.g. Extension Cord" required>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Category <span class="inv-req">*</span></label>
                        <select name="category" id="eqm-category" class="form-control-custom" required>
                            <option value="">Select category...</option>
                            <?php
                            foreach (['Audio/Visual', 'Cables & Connectors', 'Computing', 'Lab Equipment', 'Networking', 'Power', 'Tools', 'Others'] as $c) {
                                echo "<option value=\"$c\">$c</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Quantity <span class="inv-req">*</span></label>
                            <input type="number" name="quantity" id="eqm-quantity" class="form-control-custom" min="0" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Condition</label>
                            <select name="condition" id="eqm-condition" class="form-control-custom">
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="For Repair">For Repair</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Description</label>
                        <textarea name="description" id="eqm-description" class="form-control-custom" rows="3"
                            placeholder="Short description of the item..."></textarea>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Item Image</label>
                        <div class="drop-zone" id="eqm-dropZone">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"
                                style="color:var(--text-light)">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <p>Click to upload, drag &amp; drop, or paste an image</p>
                            <input type="file" name="item_image" id="eqm-itemImageInput" accept="image/jpeg,image/png" style="display:none;">
                            <img id="eqm-imagePreview" class="drop-zone-preview" style="display:none;">
                        </div>
                        <button type="button" id="eqm-removeImageBtn" class="hidden"
                            style="margin-top:6px;font-size:0.75rem;color:var(--danger);background:none;border:none;cursor:pointer;">
                            &#x2715; Remove image
                        </button>
                    </div>

                    <div style="border-top:1px solid var(--khaki-border);margin-top:1rem;padding-top:1rem">
                        <a href="#" id="eqm-archive-link" class="ps-btn ps-btn--danger" style="text-decoration:none;display:inline-flex;">
                            <span class="material-symbols-outlined">archive</span> Archive Item
                        </a>
                    </div>
                </form>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" type="button" data-action="ps-close-modal" data-modal="eq-edit-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" type="submit" form="eqEditForm" name="update_item">
                    <span class="material-symbols-outlined">save</span> Update Item
                </button>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            /* Dropdown "My Account" / "Settings" buttons are bound in
               admin-dashboard.js (routes to the correct Settings sub-tab). */

            /* Override initView() for ?edit_item= and ?view=inventory.
               admin-dashboard.js calls _switchTabDOM('lending') for those,
               but inventory is now its own #panel-inventory tab.

               IMPORTANT: admin-dashboard.js's own init() runs as soon as its
               <script> tag is reached — if document.readyState is no longer
               'loading' by then (typical, since that script sits near the
               end of the page), init() runs synchronously immediately,
               BEFORE any 'DOMContentLoaded' listener registered here would
               ever fire (the event has already passed). That's why this
               used to silently fail to land on the Equipment tab after a
               save. Matching admin-dashboard.js's own readyState check
               instead of blindly waiting for DOMContentLoaded fixes it. */
            function fixInventoryTab() {
                var params = new URLSearchParams(window.location.search);
                if (params.get('edit_item') || params.get('view') === 'inventory') {
                    document.querySelectorAll('.tab-panel').forEach(function(p) {
                        p.classList.remove('active');
                    });
                    var pInv = document.getElementById('panel-inventory');
                    if (pInv) pInv.classList.add('active');
                    document.querySelectorAll('.nav-item').forEach(function(n) {
                        n.classList.remove('active');
                    });
                    var snavInv = document.getElementById('snav-inventory');
                    if (snavInv) snavInv.classList.add('active');
                }

                /* Show a toast for the add/update/error redirects from
                   admin-functions.php, then strip the param so refreshing
                   or navigating away doesn't re-show it. */
                var msg = null;
                if (params.get('added') === '1') msg = 'Equipment added successfully.';
                else if (params.get('updated') === '1') msg = 'Equipment updated successfully.';
                else if (params.get('error') === 'filetype') msg = 'Only JPG and PNG images are allowed.';
                else if (params.get('error') === 'filesize') msg = 'Image too large. Maximum size is 2MB.';
                else if (params.get('error') === 'dberror') msg = 'Error saving to database. Please try again.';

                if (msg && typeof showToast === 'function') {
                    showToast(msg);
                    params.delete('added');
                    params.delete('updated');
                    params.delete('error');
                    var qs = params.toString();
                    var newUrl = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
                    window.history.replaceState({}, '', newUrl);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(fixInventoryTab, 0);
                });
            } else {
                setTimeout(fixInventoryTab, 0);
            }
        })();

        /* ── Edit Equipment modal: populate from the clicked row's
           data-* attributes, then open it. (ps-open-modal/close-modal
           are already handled by the main delegation in
           admin-dashboard.js — no need to duplicate that here.) ── */
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="eq-open-edit"]');
            if (!btn) return;
            var row = btn.closest('.inv-row-item');
            if (!row) return;
            var d = row.dataset;

            document.getElementById('eqm-item-id').value = d.itemId;
            document.getElementById('eqm-old-image').value = d.itemImage;
            document.getElementById('eqm-remove-image-flag').value = '0';
            document.getElementById('eqm-item-name').value = d.itemName;
            document.getElementById('eqm-category').value = d.itemCategory;
            document.getElementById('eqm-quantity').value = d.itemQuantity;
            document.getElementById('eqm-condition').value = d.itemCondition || 'Good';
            document.getElementById('eqm-description').value = d.itemDescription || '';

            var preview = document.getElementById('eqm-imagePreview');
            var removeBtn = document.getElementById('eqm-removeImageBtn');
            var fileInput = document.getElementById('eqm-itemImageInput');
            if (fileInput) fileInput.value = '';
            if (d.itemImage && d.itemImage.indexOf('default.png') === -1) {
                preview.src = d.itemImageFull;
                preview.style.display = 'block';
                removeBtn.classList.remove('hidden');
            } else {
                preview.src = '';
                preview.style.display = 'none';
                removeBtn.classList.add('hidden');
            }

            var archiveLink = document.getElementById('eqm-archive-link');
            archiveLink.href = 'admin-dashboard.php?delete_item=' + d.itemId;
            archiveLink.onclick = function() {
                return confirm('Archive "' + d.itemName + '"? It will no longer be available for lending, but borrow history is preserved.');
            };

            psOpenModal('eq-edit-modal');
        });
    </script>

    <!-- ══════════════════════════════════════════════════════
         ROOM SCHEDULE MODAL
         ══════════════════════════════════════════════════════ -->
    <div id="roomScheduleModal" class="hidden">
        <div class="rsm-card">
            <div class="rsm-head">
                <div class="rsm-head-icon">
                    <span class="material-symbols-outlined">calendar_month</span>
                </div>
                <h3 id="rsm-title">Room Schedule</h3>
                <button class="rsm-close-btn" data-action="close-room-schedule" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="rsm-info-row">
                <span class="rsm-room-meta" id="rsm-meta"></span>
                <div class="rsm-week-ctrl">
                    <button class="rsm-week-nav-btn" data-action="room-schedule-nav" data-dir="-1" title="Previous week">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <span class="rsm-week-lbl" id="rsm-week-lbl"></span>
                    <button class="rsm-week-nav-btn" data-action="room-schedule-nav" data-dir="1" title="Next week">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
            <div class="rsm-body">
                <div class="rsm-grid">
                    <div class="rsm-grid-head">
                        <div class="rsm-grid-th">TIME</div>
                        <div class="rsm-grid-th">MON</div>
                        <div class="rsm-grid-th">TUE</div>
                        <div class="rsm-grid-th">WED</div>
                        <div class="rsm-grid-th">THU</div>
                        <div class="rsm-grid-th">FRI</div>
                    </div>
                    <div id="rsm-grid-body"></div>
                </div>
            </div>
            <div class="rsm-legend">
                <div class="rsm-legend-item">
                    <div class="rsm-legend-dot reserved"></div> Reserved
                </div>
                <div class="rsm-legend-item">
                    <div class="rsm-legend-dot avail"></div> Available
                </div>
            </div>
            <div class="rsm-foot">
                <button type="button" class="btn-cancel-acc" data-action="close-room-schedule">Close</button>
            </div>
        </div><!-- /.rsm-card -->
    </div><!-- /#roomScheduleModal -->



    <!-- ================================================================
     MODAL: EDIT FACULTY ACCOUNT
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-edit-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">manage_accounts</span>
                </div>
                <h3>Edit Faculty Account</h3>
                <button class="ps-modal-close" id="fac-edit-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <!-- Faculty identity banner -->
                <div style="display:flex;align-items:center;gap:12px;background:var(--secondary-cream);border-radius:12px;padding:0.9rem;border:1px solid var(--khaki-border);margin-bottom:1.25rem">
                    <div id="fac-edit-avatar"
                        style="width:44px;height:44px;background:var(--accent-maroon);border-radius:50%;display:grid;place-items:center;color:white;font-weight:700;font-size:16px;flex-shrink:0">F</div>
                    <div>
                        <div id="fac-edit-name-display" style="font-weight:600;font-size:13.5px">&#8212;</div>
                        <div id="fac-edit-meta-display" style="font-size:11.5px;color:var(--text-light)">&#8212;</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">First Name</label>
                        <input type="text" id="fac-edit-first" class="form-control-custom" placeholder="First">
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Last Name</label>
                        <input type="text" id="fac-edit-last" class="form-control-custom" placeholder="Last">
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">PUPSync Email</label>
                    <input type="email" id="fac-edit-email" class="form-control-custom" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Backup Email</label>
                    <input type="email" id="fac-edit-backup" class="form-control-custom" placeholder="backup@gmail.com">
                </div>

                <div style="background:var(--secondary-cream);border-radius:10px;padding:0.85rem;border:1px solid var(--khaki-border);margin-bottom:1rem">
                    <label class="faculty-toggle-label" style="font-size:13px;font-weight:600;margin-bottom:8px;display:flex;cursor:pointer">
                        <input type="checkbox" id="fac-edit-adviser" class="faculty-toggle-input">
                        <span class="faculty-toggle-track"></span>
                        Organization Adviser
                    </label>
                    <div class="form-group" style="margin-bottom:0;margin-top:0.65rem">
                        <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px">Organization Name</label>
                        <input type="text" id="fac-edit-org" class="form-control-custom" placeholder="e.g. JPIA">
                    </div>
                </div>

                <label class="faculty-toggle-label" style="font-size:13px;cursor:pointer;display:flex;align-items:center">
                    <input type="checkbox" id="fac-edit-aob" class="faculty-toggle-input">
                    <span class="faculty-toggle-track"></span>
                    Org Borrowing Enabled
                </label>

                <div style="border-top:1px solid var(--khaki-border);margin-top:1rem;padding-top:1rem">
                    <button class="ps-btn ps-btn--danger" id="fac-edit-delete-btn" type="button" disabled>
                        <span class="material-symbols-outlined">person_remove</span> Delete Account
                    </button>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-edit-cancel">Cancel</button>
                <button class="ps-btn ps-btn--primary" id="fac-edit-save">
                    <span class="material-symbols-outlined">save</span> Save Changes
                </button>
            </div>
        </div>
    </div>


    <!-- ================================================================
     MODAL: DELETE FACULTY CONFIRM
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-delete-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">person_remove</span>
                </div>
                <h3>Delete Faculty Account</h3>
                <button class="ps-modal-close" id="fac-delete-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p style="font-size:13px;margin-bottom:0.75rem">Are you sure you want to permanently delete the account of <strong id="fac-delete-name">this faculty member</strong>?</p>
                <div class="ps-alert ps-alert--danger" style="margin:0">
                    <span class="material-symbols-outlined">warning</span>
                    This action cannot be undone. All associated borrowing history will be preserved.
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-delete-cancel">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="fac-delete-confirm">
                    <span class="material-symbols-outlined">delete</span> Delete Account
                </button>
            </div>
        </div>
    </div>


    <!-- ================================================================
     MODAL: GENERATE FACULTY CODE
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-code-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">key</span>
                </div>
                <h3>Generate Faculty Code</h3>
                <button class="ps-modal-close" id="fac-code-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <div class="form-group" style="margin-bottom:1.25rem">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Faculty Member</label>
                    <select id="fac-code-select" class="form-control-custom">
                        <?php
                        $fac_ddl = $conn->query("SELECT faculty_id, fullname FROM tbl_users ORDER BY fullname ASC");
                        if ($fac_ddl && $fac_ddl->num_rows > 0):
                            while ($fdrow = $fac_ddl->fetch_assoc()):
                        ?>
                                <option value="<?= htmlspecialchars($fdrow['faculty_id']) ?>">
                                    <?= htmlspecialchars($fdrow['fullname']) ?>
                                </option>
                            <?php endwhile;
                        else: ?>
                            <option value="">&#8212; No faculty accounts yet &#8212;</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="fac-code-display">
                    <div class="fac-code-label">ORGANIZATION BORROWING CODE</div>
                    <div class="fac-code-val" id="fac-code-value">PUP&#8211;&#8211;&#8211;&#8211;</div>
                    <div class="fac-code-sub">Valid for one-time org borrowing activation</div>
                    <button class="fac-code-copy-btn" id="fac-code-copy-btn">
                        <span class="material-symbols-outlined">content_copy</span> Copy Code
                    </button>
                </div>
                <p style="font-size:12px;color:var(--text-light);text-align:center;margin-top:0.75rem">
                    Share this code with the faculty member. It expires in 48 hours.
                </p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-code-close-btn">Close</button>
                <button class="ps-btn ps-btn--outline" id="fac-code-regen-btn">
                    <span class="material-symbols-outlined">refresh</span> Regenerate
                </button>
            </div>
        </div>
    </div>


    <!-- Faculty Panel JS -->
    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            function openFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.add('ps-modal-open');
            }

            function closeFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.remove('ps-modal-open');
            }

            /* Search filter */
            var facSearch = document.getElementById('fac-search-input');
            if (facSearch) {
                facSearch.addEventListener('input', function() {
                    var q = facSearch.value.toLowerCase();
                    var table = document.getElementById('fac-list-table');
                    if (!table) return;
                    table.querySelectorAll('tbody tr').forEach(function(row) {
                        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                    });
                });
            }

            /* Edit modal: open on row edit-button click */
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.fac-edit-btn');
                if (!btn) return;
                var row = btn.closest('tr');
                if (!row) return;

                var fullname = row.dataset.fullname || '';
                var email = row.dataset.email || '';
                var facId = row.dataset.facultyId || '';
                var role = row.dataset.role || '';
                var org = row.dataset.org || '';
                var aob = row.dataset.aob === '1';
                var init = row.dataset.init || (fullname.charAt(0).toUpperCase()) || 'F';
                var isAdviser = role === 'Organization Adviser';
                var subLine = facId + ' \u00B7 ' + (isAdviser && org ? 'Org Adviser' : 'Active Faculty');

                var parts = fullname.trim().split(' ');
                var lastName = parts.length > 1 ? parts[parts.length - 1] : '';
                var firstName = parts.length > 1 ? parts.slice(0, -1).join(' ') : parts[0];

                document.getElementById('fac-edit-avatar').textContent = init;
                document.getElementById('fac-edit-name-display').textContent = fullname;
                document.getElementById('fac-edit-meta-display').textContent = subLine;
                document.getElementById('fac-edit-first').value = firstName;
                document.getElementById('fac-edit-last').value = lastName;
                document.getElementById('fac-edit-email').value = email;
                document.getElementById('fac-edit-backup').value = '';
                document.getElementById('fac-edit-adviser').checked = isAdviser;
                document.getElementById('fac-edit-org').value = org;
                document.getElementById('fac-edit-aob').checked = aob;
                document.getElementById('fac-delete-name').textContent = fullname;

                openFacModal('fac-edit-modal');
            });

            /* Edit modal: close */
            ['fac-edit-close', 'fac-edit-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-edit-modal');
                });
            });

            /* Delete Account: non-functional until later dev stage */

            /* Save Changes: non-functional until later dev stage */

            /* Delete modal: close */
            ['fac-delete-close', 'fac-delete-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-delete-modal');
                });
            });

            /* Delete modal: confirm (placeholder) */
            var facDelConf = document.getElementById('fac-delete-confirm');
            if (facDelConf) {
                facDelConf.addEventListener('click', function() {
                    closeFacModal('fac-delete-modal');
                    if (typeof showToast === 'function') showToast('Faculty account deleted.', 't-danger');
                });
            }

            /* Generate Code modal: open */
            function _genCode() {
                var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                var c = '';
                for (var i = 0; i < 4; i++) c += chars[Math.floor(Math.random() * chars.length)];
                return 'PUP-' + c;
            }

            var facGenBtn = document.getElementById('fac-gen-code-btn');
            if (facGenBtn) {
                facGenBtn.addEventListener('click', function() {
                    document.getElementById('fac-code-value').textContent = _genCode();
                    openFacModal('fac-code-modal');
                });
            }

            /* Gen Code modal: close */
            ['fac-code-close', 'fac-code-close-btn'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-code-modal');
                });
            });

            /* Gen Code modal: regenerate */
            var facRegen = document.getElementById('fac-code-regen-btn');
            if (facRegen) {
                facRegen.addEventListener('click', function() {
                    document.getElementById('fac-code-value').textContent = _genCode();
                });
            }

            /* Gen Code modal: copy */
            var facCopy = document.getElementById('fac-code-copy-btn');
            if (facCopy) {
                facCopy.addEventListener('click', function() {
                    var val = document.getElementById('fac-code-value').textContent;
                    navigator.clipboard.writeText(val).then(function() {
                        if (typeof showToast === 'function') showToast('Code copied to clipboard.', 't-success');
                    }).catch(function() {
                        var ta = document.createElement('textarea');
                        ta.value = val;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        if (typeof showToast === 'function') showToast('Code copied.', 't-success');
                    });
                });
            }

            /* Backdrop click to close */
            ['fac-edit-modal', 'fac-delete-modal', 'fac-code-modal'].forEach(function(id) {
                var bd = document.getElementById(id);
                if (bd) bd.addEventListener('click', function(e) {
                    if (e.target === bd) closeFacModal(id);
                });
            });

        }());
    </script>
</body>

</html>