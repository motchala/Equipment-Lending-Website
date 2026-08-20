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
    <link rel="stylesheet" href="equipment-booking/assets/css/admin-dashboard.css">
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
                <span>Admin Portal</span>
            </div>
        </div>

        <!-- Center: Search -->
        <div class="header-search">
            <span class="material-symbols-outlined search-icon">search</span>
            <input type="text" class="header-search-input" placeholder="Search requests, equipment, faculty...">
        </div>

        <!-- Right: Notification + User + Avatar + Dropdown (unchanged) -->
        <div class="header-right">
            <!-- Notification Bell -->
            <button class="notif-btn" data-action="open-overlay" data-target="notifOverlay" title="Notifications">
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
                    <button class="dd-item" data-action="open-overlay" data-target="accountOverlay">
                        <div class="dd-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>Account
                    </button>
                    <button class="dd-item" data-action="open-overlay" data-target="notifOverlay">
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
                    <button class="dd-item" data-action="open-overlay" data-target="settingsOverlay">
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
        <a class="nav-item" data-tab="lending" id="snav-requests" href="#">
            <span class="material-symbols-outlined">assignment</span>
            <span>Requests</span>
            <?php if ($stat_waiting > 0): ?>
                <span class="nav-badge"><?php echo $stat_waiting; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-item" data-tab="lending" id="snav-inventory" href="#">
            <span class="material-symbols-outlined">inventory_2</span>
            <span>Inventory</span>
        </a>
        <a class="nav-item" data-tab="rooms" href="#">
            <span class="material-symbols-outlined">meeting_room</span>
            <span>Rooms</span>
        </a>
        <a class="nav-item" data-tab="faculty" href="#">
            <span class="material-symbols-outlined">group</span>
            <span>Faculty</span>
        </a>
        <a class="nav-item" data-tab="lending" id="snav-arbitration" href="#">
            <span class="material-symbols-outlined">balance</span>
            <span>Arbitration</span>
        </a>

        <hr class="nav-divider">

        <div class="sidebar-bottom">
            <a class="nav-item" data-action="open-overlay" data-target="settingsOverlay" href="#">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            <a class="nav-item" data-action="open-overlay" data-target="accountOverlay" href="#">
                <span class="material-symbols-outlined">help</span>
                <span>Help Center</span>
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
                    ?>, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>.</h1>
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
                <div class="ps-qa-card ps-qa-card--light" data-action="go-faculty" style="cursor:pointer">
                    <span class="material-symbols-outlined">person_add</span>
                    <strong>Register Faculty</strong>
                    <small>Create new faculty account</small>
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
                                        $badge = match($rr['status']) {
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
                                <?php endwhile; else: ?>
                                <tr><td colspan="3" style="text-align:center;color:var(--text-light);padding:1.5rem">No requests yet.</td></tr>
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
                        <?php endwhile; else: ?>
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
                        $rooms_total  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_rooms WHERE is_archived=0"))['c'] ?? 0;
                        $rooms_issues = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_room_issues WHERE status='Open'"))['c'] ?? 0;
                        $rooms_today  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_room_reservations WHERE DATE(created_at)=CURDATE()"))['c'] ?? 0;
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
                        $fac_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_accounts WHERE role='faculty'"))['c'] ?? 0;
                        $fac_active  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_accounts WHERE role='faculty' AND status='Active'"))['c'] ?? 0;
                        $fac_org     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_accounts WHERE role='faculty' AND is_org_adviser=1"))['c'] ?? 0;
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
         TAB: LENDING
    ============================================================ -->
        <div class="tab-panel" id="panel-lending">

            <!-- Lending Sub-Nav -->
            <div class="lending-nav">
                <button class="lending-nav-btn active" data-lending-nav="waiting">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                        <line x1="9" y1="12" x2="15" y2="12" />
                        <line x1="12" y1="9" x2="12" y2="15" />
                    </svg>
                    Borrow Requests <span class="lnb-badge">
                        <?php echo $stat_waiting; ?>
                    </span>
                </button>
                <button class="lending-nav-btn" data-lending-nav="history">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="9" y1="13" x2="15" y2="13" />
                        <line x1="9" y1="17" x2="15" y2="17" />
                        <polyline points="9 9 10 9 12 9" />
                    </svg>
                    Borrow History <span class="lnb-badge">
                        <?php echo $stat_approved + $stat_declined; ?>
                    </span>
                </button>
                <button class="lending-nav-btn" data-lending-nav="inventory">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path
                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    </svg>
                    Equipment Registry
                </button>
                <button class="lending-nav-btn" data-lending-nav="raw">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <line x1="8" y1="6" x2="21" y2="6" />
                        <line x1="8" y1="12" x2="21" y2="12" />
                        <line x1="8" y1="18" x2="21" y2="18" />
                        <line x1="3" y1="6" x2="3.01" y2="6" />
                        <line x1="3" y1="12" x2="3.01" y2="12" />
                        <line x1="3" y1="18" x2="3.01" y2="18" />
                    </svg>
                    Raw Data
                </button>
                <button class="lending-nav-btn" data-lending-nav="arb-log">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="9" y1="13" x2="15" y2="13" />
                        <line x1="9" y1="17" x2="15" y2="17" />
                    </svg>
                    Arbitration Log
                </button>
                <button class="lending-nav-btn" data-lending-nav="arb-config">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <circle cx="12" cy="12" r="3" />
                        <path
                            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                    </svg>
                    Config Panel
                </button>
            </div>

            <!-- ── BORROW REQUESTS ────────────────────────────────────── -->
            <div class="lending-sub active" id="lending-waiting">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                            <line x1="9" y1="12" x2="15" y2="12" />
                            <line x1="12" y1="9" x2="12" y2="15" />
                        </svg>Borrow Requests</h2>
                    <p>Review pending borrow requests and confirm equipment returns from students.</p>
                </div>

                <!-- Borrow Requests sub-tabs toggle -->
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                    <div class="history-toggle-wrap" style="margin-bottom:0;">
                        <button class="history-toggle-btn active" data-history-tab="pending-loans">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="14" height="14">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            Pending Approval
                            <span class="history-toggle-count">
                                <?php echo $stat_waiting; ?>
                            </span>
                        </button>
                        <button class="history-toggle-btn" data-history-tab="return-confirmation">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="14" height="14">
                                <polyline points="1 4 1 10 7 10" />
                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                            </svg>
                            Return Confirmation
                            <span class="history-toggle-count">
                                <?php echo $stat_approved; ?>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Pending Approval sub-panel -->
                <div class="history-panel active" id="history-pending-loans">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="16" height="16">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="waitingSearch" placeholder="Search by ID, name, or equipment...">
                            </div>
                        </div>
                        <div class="tbl-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Equipment</th>
                                        <th>Borrow Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                        <th>Override</th>
                                    </tr>
                                </thead>
                                <tbody id="waiting-body">
                                    <?php if (mysqli_num_rows($waiting_result) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted" style="text-align:center;padding:3rem;">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" width="40" height="40"
                                                    style="display:block;margin:0 auto 10px;opacity:0.3;">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <polyline points="12 6 12 12 16 14" />
                                                </svg>
                                                No pending requests.
                                            </td>
                                        </tr>
                                        <?php else: while ($r = mysqli_fetch_assoc($waiting_result)):
                                            $isPast = strtotime($r['borrow_date']) < strtotime($today);
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($r['faculty_id']); ?>
                                                </td>
                                                <td class="fw-bold">
                                                    <?php echo htmlspecialchars($r['faculty_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($r['equipment_name']); ?>
                                                </td>
                                                <td style="<?php echo $isPast ? 'color:var(--danger);font-weight:600;' : '' ?>">
                                                    <?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>
                                                    <?php if ($isPast): ?><br><small style="font-size:0.68rem;">(Date
                                                            Passed)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($r['return_date'])); ?>
                                                </td>
                                                <td><span class="status-pill pill-waiting">Pending</span></td>
                                                <td>
                                                    <button class="btn-action btn-override-req" data-action="open-override"
                                                        data-request-id="<?php echo $r['id']; ?>" data-request-status="Waiting"
                                                        data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                        data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                        title="Override this request">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round" width="14" height="14">
                                                            <path
                                                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endwhile;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /history-pending-loans -->

                <!-- Return Confirmation sub-panel -->
                <div class="history-panel" id="history-return-confirmation">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="16" height="16">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="returnSearch" placeholder="Search by ID, name, or equipment...">
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
                            <button id="openQrScannerBtn" class="btn-submit-form" style="width:auto;padding:8px 18px;margin:0;display:flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                    <rect x="3" y="3" width="5" height="5" />
                                    <rect x="16" y="3" width="5" height="5" />
                                    <rect x="3" y="16" width="5" height="5" />
                                    <line x1="21" y1="16" x2="21" y2="21" />
                                    <line x1="16" y1="21" x2="21" y2="21" />
                                    <line x1="16" y1="16" x2="16" y2="16" />
                                </svg>
                                Scan Return QR
                            </button>
                        </div>
                        <div class="tbl-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Equipment</th>
                                        <th>Borrow Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="return-body">
                                    <?php
                                    mysqli_data_seek($approved_result, 0);
                                    if (mysqli_num_rows($approved_result) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted" style="text-align:center;padding:3rem;">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" width="40" height="40"
                                                    style="display:block;margin:0 auto 10px;opacity:0.3;">
                                                    <polyline points="1 4 1 10 7 10" />
                                                    <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                </svg>
                                                No items awaiting return confirmation.
                                            </td>
                                        </tr>
                                        <?php else: while ($r = mysqli_fetch_assoc($approved_result)):
                                            $isOverdue = strtotime($r['return_date']) < strtotime($today);
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($r['faculty_id']); ?>
                                                </td>
                                                <td class="fw-bold">
                                                    <?php echo htmlspecialchars($r['faculty_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($r['equipment_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>
                                                </td>
                                                <td
                                                    style="<?php echo $isOverdue ? 'color:var(--danger);font-weight:600;' : '' ?>">
                                                    <?php echo date('M d, Y', strtotime($r['return_date'])); ?>
                                                    <?php if ($isOverdue): ?><br><small
                                                            style="font-size:0.68rem;">(Overdue)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span
                                                        class="status-pill <?php echo $isOverdue ? 'pill-overdue' : 'pill-approved'; ?>">
                                                        <?php echo $isOverdue ? 'Overdue' : 'Out on Loan'; ?>
                                                    </span></td>
                                                <td class="action-cell">
                                                    <div class="action-btns">
                                                        <a href="admin-dashboard.php?action=return_confirm&id=<?php echo $r['id']; ?>"
                                                            class="btn-return-confirm" title="Confirm item has been returned"
                                                            onclick="return confirm('Confirm that <?php echo htmlspecialchars(addslashes($r['faculty_name'])); ?> has returned the <?php echo htmlspecialchars(addslashes($r['equipment_name'])); ?>?')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                fill="none" stroke="currentColor" stroke-width="2.5"
                                                                stroke-linecap="round" stroke-linejoin="round" width="13"
                                                                height="13">
                                                                <polyline points="1 4 1 10 7 10" />
                                                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                            </svg>
                                                            Returned
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
                </div><!-- /history-return-confirmation -->

            </div><!-- /lending-waiting -->

            <!-- ── BORROW HISTORY ─────────────────────────────────── -->
            <div class="lending-sub" id="lending-history">
                <div class="page-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="9" y1="13" x2="15" y2="13" />
                            <line x1="9" y1="17" x2="15" y2="17" />
                        </svg>Borrow History
                    </h2>
                    <p>View all resolved borrow requests — approved and declined.</p>
                </div>

                <!-- Status toggle -->
                <div class="history-toggle-wrap">
                    <button class="history-toggle-btn active" data-history-tab="approved">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                        Approved
                        <span class="history-toggle-count">
                            <?php echo $stat_approved; ?>
                        </span>
                    </button>
                    <button class="history-toggle-btn" data-history-tab="declined">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        Declined
                        <span class="history-toggle-count">
                            <?php echo $stat_declined; ?>
                        </span>
                    </button>
                </div>

                <!-- Approved sub-panel -->
                <div class="history-panel active" id="history-approved">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="16" height="16">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="approvedSearch" placeholder="Search approved records...">
                            </div>
                        </div>
                        <div class="tbl-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Equipment</th>
                                        <th>Borrow Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                        <th>Override</th>
                                    </tr>
                                </thead>
                                <tbody id="approved-list">
                                    <?php mysqli_data_seek($approved_result, 0);
                                    if (mysqli_num_rows($approved_result) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted" style="text-align:center;padding:2.5rem;">No
                                                approved requests.</td>
                                        </tr>
                                        <?php else: while ($r = mysqli_fetch_assoc($approved_result)): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($r['faculty_id']); ?>
                                                </td>
                                                <td class="fw-bold">
                                                    <?php echo htmlspecialchars($r['faculty_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($r['equipment_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($r['return_date'])); ?>
                                                </td>
                                                <td><span class="status-pill pill-approved">Approved</span></td>
                                                <td>
                                                    <button class="btn-action btn-override-req" data-action="open-override"
                                                        data-request-id="<?php echo $r['id']; ?>" data-request-status="Approved"
                                                        data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                        data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                        title="Override this request">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round" width="14" height="14">
                                                            <path
                                                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endwhile;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /history-approved -->

                <!-- Declined sub-panel -->
                <div class="history-panel" id="history-declined">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="16" height="16">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="declinedSearch" placeholder="Search declined records...">
                            </div>
                        </div>
                        <div class="tbl-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Equipment</th>
                                        <th>Borrow Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <th>Override</th>
                                    </tr>
                                </thead>
                                <tbody id="declined-list">
                                    <?php mysqli_data_seek($declined_result, 0);
                                    if (mysqli_num_rows($declined_result) === 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-muted" style="text-align:center;padding:2.5rem;">No
                                                declined requests.</td>
                                        </tr>
                                        <?php else: while ($r = mysqli_fetch_assoc($declined_result)): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($r['faculty_id']); ?>
                                                </td>
                                                <td class="fw-bold">
                                                    <?php echo htmlspecialchars($r['faculty_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($r['equipment_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($r['return_date'])); ?>
                                                </td>
                                                <td><span class="status-pill pill-declined">Declined</span></td>
                                                <td class="text-muted" style="font-size:0.78rem;">
                                                    <?php echo htmlspecialchars($r['reason'] ?? '—'); ?>
                                                </td>
                                                <td>
                                                    <button class="btn-action btn-override-req" data-action="open-override"
                                                        data-request-id="<?php echo $r['id']; ?>" data-request-status="Declined"
                                                        data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                        data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                        title="Override this request">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round" width="14" height="14">
                                                            <path
                                                                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endwhile;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /history-declined -->

            </div><!-- /lending-history -->

            <!-- ── EQUIPMENT REGISTRY ────────────────────────────────── -->
            <div class="lending-sub" id="lending-inventory">
                <div class="page-header inv-page-header">
                    <div>
                        <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="important-icon">
                                <path
                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            </svg>Equipment Registry</h2>
                        <p>Manage active equipment and archived items in one place.</p>
                    </div>
                    <button class="btn-add-item" data-action="show-add-form">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Add Item
                    </button>
                </div>

                <!-- Add / Edit Form (hidden by default) -->
                <div id="item-form-wrap" class="<?php echo $edit_item ? '' : 'hidden'; ?>"
                    style="margin-bottom:1.5rem;">
                    <div class="eq-card form-card">
                        <div class="form-card-header">
                            <h2>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="18" height="18">
                                    <path
                                        d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                </svg>
                                <span id="form-title">
                                    <?php echo $edit_item ? 'Edit Item' : 'Add New Item'; ?>
                                </span>
                            </h2>
                            <button type="button" class="btn-close-custom" data-action="hide-item-form">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="16" height="16">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                        <div class="form-card-body">
                            <form method="POST" enctype="multipart/form-data" id="itemForm">
                                <?= csrf_field() ?>
                                <?php if ($edit_item): ?>
                                    <input type="hidden" name="item_id" value="<?php echo $edit_item['item_id']; ?>">
                                    <input type="hidden" name="old_image"
                                        value="<?php echo htmlspecialchars($edit_item['image_path']); ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label>Item Name</label>
                                    <input type="text" name="item_name" class="form-control-custom"
                                        value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_name']) : ''; ?>"
                                        placeholder="e.g. HDMI Cable" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <select name="category" class="form-control-custom" required>
                                            <option value="">Select...</option>
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
                                        <label>Quantity</label>
                                        <input type="number" name="quantity" class="form-control-custom" min="0"
                                            value="<?php echo $edit_item ? $edit_item['quantity'] : '1'; ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Item Image</label>
                                    <div class="drop-zone" id="dropZone">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" width="32" height="32"
                                            style="color:var(--text-light)">
                                            <rect x="3" y="3" width="18" height="18" rx="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <polyline points="21 15 16 10 5 21" />
                                        </svg>
                                        <p>Click to upload, drag & drop, or paste an image</p>
                                        <input type="file" name="item_image" id="itemImageInput" accept="image/*"
                                            style="display:none;">
                                        <?php if ($edit_item && $edit_item['image_path'] !== 'uploads/default.png'): ?>
                                            <img src="<?php echo $root_url . htmlspecialchars($edit_item['image_path']); ?>"
                                                class="drop-zone-preview" id="imagePreview" style="display:block;">
                                        <?php else: ?>
                                            <img id="imagePreview" class="drop-zone-preview" style="display:none;">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="removeImageBtn"
                                        class="<?php echo ($edit_item && $edit_item['image_path'] !== 'uploads/default.png') ? '' : 'hidden'; ?>"
                                        style="margin-top:6px;font-size:0.75rem;color:var(--danger);background:none;border:none;cursor:pointer;">✕
                                        Remove image</button>
                                </div>

                                <button type="submit" name="<?php echo $edit_item ? 'update_item' : 'add_item'; ?>"
                                    class="btn-submit-form">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" width="16" height="16">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <?php echo $edit_item ? 'Update Item' : 'Add to Inventory'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Equipment Registry toggle -->
                <div class="history-toggle-wrap" id="registry-toggle-wrap">
                    <button class="history-toggle-btn active" data-history-tab="reg-active">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        </svg>
                        Active Equipment
                    </button>
                    <button class="history-toggle-btn" data-history-tab="reg-archived">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <polyline points="21 8 21 21 3 21 3 8" />
                            <rect x="1" y="3" width="22" height="5" />
                            <line x1="10" y1="12" x2="14" y2="12" />
                        </svg>
                        Archived Items
                    </button>
                </div>

                <!-- Active Equipment sub-panel -->
                <div class="history-panel active" id="history-reg-active">
                    <!-- Inventory Table -->
                    <div class="eq-card" id="inv-table-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="16" height="16">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="inventorySearch" placeholder="Search inventory...">
                            </div>
                        </div>
                        <div class="tbl-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Qty</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-body">
                                    <?php mysqli_data_seek($inventory_result, 0);
                                    if (mysqli_num_rows($inventory_result) === 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-muted" style="text-align:center;padding:3rem;">
                                                Inventory is empty.</td>
                                        </tr>
                                        <?php else: while ($item = mysqli_fetch_assoc($inventory_result)): ?>
                                            <tr>
                                                <td><img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                        class="item-img" onerror="this.src='../uploads/default.png'"></td>
                                                <td class="fw-bold">
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($item['category']); ?>
                                                </td>
                                                <td><span class="status-pill pill-info">
                                                        <?php echo $item['quantity']; ?> units
                                                    </span></td>
                                                <td>
                                                    <?php if ($item['quantity'] > 2): ?>
                                                        <span class="stock-badge stock-avail">Available</span>
                                                    <?php elseif ($item['quantity'] > 0): ?>
                                                        <span class="stock-badge stock-low">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="stock-badge stock-unavail">No Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="action-cell">
                                                    <div class="action-btns">
                                                        <a href="admin-dashboard.php?edit_item=<?php echo $item['item_id']; ?>"
                                                            class="btn-action btn-edit-item" title="Edit">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                fill="none" stroke="currentColor" stroke-width="2"
                                                                stroke-linecap="round" stroke-linejoin="round" width="14"
                                                                height="14">
                                                                <path
                                                                    d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                                <path
                                                                    d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                            </svg>
                                                        </a>
                                                        <a href="admin-dashboard.php?delete_item=<?php echo $item['item_id']; ?>"
                                                            class="btn-action btn-delete-item" title="Archive"
                                                            onclick="return confirm('Archive this item?')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                fill="none" stroke="currentColor" stroke-width="2"
                                                                stroke-linecap="round" stroke-linejoin="round" width="14"
                                                                height="14">
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
                    </div><!-- /inv-table-card -->
                </div><!-- /history-reg-active -->

                <!-- Archived Items sub-panel -->
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
                                            <td colspan="4" class="text-muted" style="text-align:center;padding:2.5rem;">No
                                                archived items.</td>
                                        </tr>
                                        <?php else: while ($item = mysqli_fetch_assoc($archive_result)): ?>
                                            <tr>
                                                <td><img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                        class="item-img" onerror="this.src='../uploads/default.png'"></td>
                                                <td class="fw-bold">
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($item['category']); ?>
                                                </td>
                                                <td class="action-cell">
                                                    <div class="action-btns">
                                                        <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>"
                                                            class="btn-action btn-restore" title="Restore to active">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                fill="none" stroke="currentColor" stroke-width="2"
                                                                stroke-linecap="round" stroke-linejoin="round" width="14"
                                                                height="14">
                                                                <polyline points="1 4 1 10 7 10" />
                                                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                            </svg>
                                                        </a>
                                                        <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>"
                                                            class="btn-action btn-force-del" title="Delete permanently"
                                                            onclick="return confirm('Permanently delete this item? This cannot be undone.')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                fill="none" stroke="currentColor" stroke-width="2"
                                                                stroke-linecap="round" stroke-linejoin="round" width="14"
                                                                height="14">
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

            </div><!-- /lending-inventory -->

            <!-- ── RAW DATA ───────────────────────────────────────────── -->
            <div class="lending-sub" id="lending-raw">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <line x1="8" y1="6" x2="21" y2="6" />
                            <line x1="8" y1="12" x2="21" y2="12" />
                            <line x1="8" y1="18" x2="21" y2="18" />
                            <line x1="3" y1="6" x2="3.01" y2="6" />
                            <line x1="3" y1="12" x2="3.01" y2="12" />
                            <line x1="3" y1="18" x2="3.01" y2="18" />
                        </svg>Raw Data</h2>
                    <p>Full unfiltered view of all borrow request records.</p>
                </div>
                <div class="eq-card">
                    <div class="search-row">
                        <div class="search-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" id="rawSearch" placeholder="Search all records...">
                        </div>
                    </div>
                    <div class="tbl-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Equipment</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                    <th>Borrow Date</th>
                                    <th>Return Date</th>
                                    <th>Requested</th>
                                </tr>
                            </thead>
                            <tbody id="raw-data-body">
                                <?php if (mysqli_num_rows($raw_data_result) === 0): ?>
                                    <tr>
                                        <td colspan="8" class="text-muted" style="text-align:center;padding:2.5rem;">No
                                            records found.</td>
                                    </tr>
                                    <?php else: while ($r = mysqli_fetch_assoc($raw_data_result)): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($r['faculty_id']); ?>
                                            </td>
                                            <td class="fw-bold">
                                                <?php echo htmlspecialchars($r['faculty_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['equipment_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['instructor']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['room']); ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($r['return_date'])); ?>
                                            </td>
                                            <td class="text-muted" style="font-size:0.78rem;">
                                                <?php echo date('M d, Y g:i A', strtotime($r['request_date'])); ?>
                                            </td>
                                        </tr>
                                <?php endwhile;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /lending-raw -->

            <!-- ── ARBITRATION LOG ───────────────────────────────────── -->
            <div class="lending-sub" id="lending-arb-log">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="9" y1="13" x2="15" y2="13" />
                            <line x1="9" y1="17" x2="15" y2="17" />
                        </svg>Arbitration Log</h2>
                    <p>Audit trail of all automated decisions made by the Arbitration Engine.</p>
                </div>
                <div class="eq-card">
                    <div class="search-row">
                        <div class="search-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                width="16" height="16">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" id="arbLogSearch"
                                placeholder="Search by borrower name, ID, or equipment...">
                        </div>
                    </div>
                    <div class="tbl-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Borrower Name</th>
                                    <th>Borrower ID</th>
                                    <th>Equipment</th>
                                    <th>Decision</th>
                                    <th>Rule Applied</th>
                                    <th>Reason</th>
                                    <th>Timestamp</th>
                                    <th>Override</th>
                                </tr>
                            </thead>
                            <tbody id="arb-log-body">
                                <?php if (!$arb_log_result || mysqli_num_rows($arb_log_result) === 0): ?>
                                    <tr>
                                        <td colspan="9" class="text-muted" style="text-align:center;padding:2.5rem;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" width="40" height="40"
                                                style="display:block;margin:0 auto 10px;opacity:0.3;">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <polyline points="14 2 14 8 20 8" />
                                                <line x1="9" y1="13" x2="15" y2="13" />
                                                <line x1="9" y1="17" x2="15" y2="17" />
                                            </svg>
                                            No arbitration log entries yet.
                                        </td>
                                    </tr>
                                    <?php else: while ($r = mysqli_fetch_assoc($arb_log_result)): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($r['request_id']); ?>
                                            </td>
                                            <td class="fw-bold">
                                                <?php echo htmlspecialchars($r['borrower_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['borrower_id']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['equipment_name']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $dec = $r['decision'];
                                                if ($dec === 'Approved') {
                                                    echo '<span class="status-pill pill-approved">Approved</span>';
                                                } elseif ($dec === 'Declined') {
                                                    echo '<span class="status-pill pill-declined">Declined</span>';
                                                } else {
                                                    echo '<span class="status-pill pill-waiting">' . htmlspecialchars($dec) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-muted" style="font-size:0.78rem;">
                                                <?php echo htmlspecialchars($r['rule_applied'] ?? '—'); ?>
                                            </td>
                                            <td class="text-muted" style="font-size:0.78rem;">
                                                <?php echo htmlspecialchars($r['reason'] ?? '—'); ?>
                                            </td>
                                            <td class="text-muted" style="font-size:0.78rem;">
                                                <?php echo date('M d, Y g:i A', strtotime($r['created_at'])); ?>
                                            </td>
                                            <td>
                                                <button class="btn-action btn-override-req" data-action="open-override"
                                                    data-request-id="<?php echo $r['request_id']; ?>"
                                                    data-request-status="<?php echo htmlspecialchars($r['current_request_status'] ?? $r['decision']); ?>"
                                                    data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                    data-borrower="<?php echo htmlspecialchars($r['borrower_name']); ?>"
                                                    title="Override this decision">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" width="14" height="14">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                <?php endwhile;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /lending-arb-log -->

            <!-- ── ARBITRATION CONFIG PANEL ──────────────────────────── -->
            <div class="lending-sub" id="lending-arb-config">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>Config Panel</h2>
                    <p>Configure arbitration rules and role priorities.</p>
                </div>

                <div class="eq-card form-card">
                    <div class="form-card-body">
                        <form id="arbConfigForm">
                            <?= csrf_field() ?>

                            <!-- Role Priority -->
                            <h3
                                style="font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-light);margin-bottom:1rem;">
                                Role Priority</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Director</label>
                                    <input type="number" name="config[role_priority_director]"
                                        class="form-control-custom" min="1" max="10"
                                        value="<?php echo htmlspecialchars($arb_config['role_priority_director'] ?? 4); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Adviser</label>
                                    <input type="number" name="config[role_priority_adviser]"
                                        class="form-control-custom" min="1" max="10"
                                        value="<?php echo htmlspecialchars($arb_config['role_priority_adviser'] ?? 3); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Regular Faculty</label>
                                    <input type="number" name="config[role_priority_faculty]"
                                        class="form-control-custom" min="1" max="10"
                                        value="<?php echo htmlspecialchars($arb_config['role_priority_faculty'] ?? 2); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Student Representative</label>
                                    <input type="number" name="config[role_priority_student]"
                                        class="form-control-custom" min="1" max="10"
                                        value="<?php echo htmlspecialchars($arb_config['role_priority_student'] ?? 1); ?>">
                                </div>
                            </div>

                            <!-- Tie-Break Window -->
                            <h3
                                style="font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-light);margin-bottom:1rem;margin-top:1.5rem;">
                                Tie-Break Window</h3>
                            <div class="form-group" style="max-width:260px;">
                                <label>Window (seconds)</label>
                                <input type="number" name="config[tie_break_window_seconds]" class="form-control-custom"
                                    min="1"
                                    value="<?php echo htmlspecialchars($arb_config['tie_break_window_seconds'] ?? 5); ?>">
                            </div>

                            <!-- Rule Toggles -->
                            <h3
                                style="font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-light);margin-bottom:1rem;margin-top:1.5rem;">
                                Auto-Decline Rules</h3>
                            <div class="s-row"
                                style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--border);">
                                <div>
                                    <h4 style="font-size:0.88rem;font-weight:600;margin:0 0 2px;">Overdue Block</h4>
                                    <p style="font-size:0.78rem;color:var(--text-light);margin:0;">Decline requests from
                                        borrowers with overdue items.</p>
                                </div>
                                <label class="toggle-sw">
                                    <input type="checkbox" name="config[rule_overdue_block_enabled]" value="1" <?php
                                                                                                                echo (($arb_config['rule_overdue_block_enabled'] ?? '1') === '1') ? 'checked'
                                                                                                                    : ''; ?>>
                                    <span class="toggle-track"></span>
                                </label>
                            </div>
                            <div class="s-row"
                                style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--border);">
                                <div>
                                    <h4 style="font-size:0.88rem;font-weight:600;margin:0 0 2px;">Duplicate Block</h4>
                                    <p style="font-size:0.78rem;color:var(--text-light);margin:0;">Decline duplicate
                                        active requests for the same equipment.</p>
                                </div>
                                <label class="toggle-sw">
                                    <input type="checkbox" name="config[rule_duplicate_block_enabled]" value="1" <?php
                                                                                                                    echo (($arb_config['rule_duplicate_block_enabled'] ?? '1') === '1') ? 'checked'
                                                                                                                        : ''; ?>>
                                    <span class="toggle-track"></span>
                                </label>
                            </div>
                            <div class="s-row"
                                style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 0;">
                                <div>
                                    <h4 style="font-size:0.88rem;font-weight:600;margin:0 0 2px;">Missing Document Block
                                    </h4>
                                    <p style="font-size:0.78rem;color:var(--text-light);margin:0;">Hold requests for
                                        organization borrowing (Adviser role) without a signed letter.</p>
                                </div>
                                <label class="toggle-sw">
                                    <input type="checkbox" name="config[rule_missing_doc_block_enabled]" value="1" <?php
                                                                                                                    echo (($arb_config['rule_missing_doc_block_enabled'] ?? '1') === '1')
                                                                                                                        ? 'checked' : ''; ?>>
                                    <span class="toggle-track"></span>
                                </label>
                            </div>

                            <!-- Submit -->
                            <div style="margin-top:1.5rem;">
                                <button type="button" id="saveArbConfig" class="btn-submit-form">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" width="16" height="16">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Save Arbitration Settings
                                </button>
                                <div id="arbConfigMsg" style="display:none;margin-top:0.75rem;"
                                    class="alert-banner alert-success">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="icon-img">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                        <polyline points="22 4 12 14.01 9 11.01" />
                                    </svg>
                                    Arbitration settings saved.
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div><!-- /lending-arb-config -->

        </div><!-- /panel-lending -->


        <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
        <div class="tab-panel" id="panel-rooms">

            <!-- ── Page header + Add Room button ──────────────────── -->
            <div class="page-header inv-page-header">
                <div>
                    <h2 style="display:flex;align-items:center;gap:8px;margin:0 0 4px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                        </svg>
                        Room Registry
                    </h2>
                    <p>Manage rooms across all campuses and buildings. <?php echo $stat_rooms_total; ?> active room<?php echo $stat_rooms_total !== 1 ? 's' : ''; ?> — <?php echo $stat_rooms_available; ?> Available, <?php echo $stat_rooms_maintenance; ?> Maintenance, <?php echo $stat_rooms_notbookable; ?> Not Bookable.</p>
                </div>
                <button class="btn-add-item" data-action="show-room-form" id="addRoomBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Add Room
                </button>
            </div>

            <!-- ── Add / Edit Room form (hidden by default) ────────── -->
            <div id="room-form-wrap" class="<?php echo $edit_room ? '' : 'hidden'; ?>" style="margin-bottom:1.5rem;">
                <div class="eq-card form-card">

                    <!-- Header — matches equipment form exactly -->
                    <div class="form-card-header">
                        <h2>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <line x1="9" y1="3" x2="9" y2="21" />
                            </svg>
                            <span id="room-form-title"><?php echo $edit_room ? 'Edit Room' : 'Add New Room'; ?></span>
                        </h2>
                        <button type="button" class="btn-close-custom" data-action="hide-room-form" aria-label="Close form">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body wrapper — matches equipment form padding -->
                    <div class="form-card-body">
                    <form method="POST" id="roomForm">
                        <?= csrf_field() ?>
                        <?php if ($edit_room): ?>
                            <input type="hidden" name="room_id" value="<?php echo (int)$edit_room['room_id']; ?>">
                        <?php endif; ?>

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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                        <span class="field-tip-box" role="tooltip">Available = open for booking. Maintenance = temporarily closed. Not Bookable = this room cannot be reserved.</span>
                                    </span>
                                </label>
                                <select name="status" class="form-control-custom">
                                    <?php foreach (['Available','Maintenance','Not Bookable'] as $s): ?>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
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
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    <span class="field-tip-box" role="tooltip">Tick everything this room has. These details appear on the room information screen.</span>
                                </span>
                            </label>
                            <?php
                            $preset_amenities = ['WiFi','A/C','Projector','PA System','Running Water','Safety Kit','Whiteboard','Smart TV'];
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
                        <button type="submit" name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                            class="btn-submit-form">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <?php echo $edit_room ? 'Update Room' : 'Add Room'; ?>
                        </button>
                    </form>
                    </div><!-- /.form-card-body -->

                </div>
            </div><!-- /#room-form-wrap -->

            <!-- ── Active / Archived / Reservations toggle ─────────────── -->
            <div class="history-toggle-wrap" id="rooms-toggle-wrap">
                <button class="history-toggle-btn active" data-rooms-tab="rooms-active">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <line x1="9" y1="3" x2="9" y2="21" />
                    </svg>
                    Active Rooms
                </button>
                <button class="history-toggle-btn" data-rooms-tab="rooms-archived">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                        <polyline points="21 8 21 21 3 21 3 8" />
                        <rect x="1" y="3" width="22" height="5" />
                        <line x1="10" y1="12" x2="14" y2="12" />
                    </svg>
                    Archived Rooms
                    <?php if (!empty($rooms_archived)): ?>
                        <span class="lnb-badge"><?php echo count($rooms_archived); ?></span>
                    <?php endif; ?>
                </button>
                <button class="history-toggle-btn" data-rooms-tab="rooms-reservations">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Reservations
                    <?php if (!empty($admin_room_reservations)): ?>
                        <span class="lnb-badge"><?php echo count($admin_room_reservations); ?></span>
                    <?php endif; ?>
                </button>
                <button class="history-toggle-btn" data-rooms-tab="rooms-issues">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Issues
                    <?php if (!empty($admin_room_issues_open)): ?>
                        <span class="lnb-badge"><?php echo (int)$admin_room_issues_open; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- ── Active rooms sub-panel ──────────────────────────────── -->
            <div class="rooms-sub-panel active" id="rooms-active-panel">

            <!-- ── Live room list ───────────────────────────────────── -->
            <div class="room-list">
<?php if (empty($rooms_list)): ?>
                <p style="color:var(--text-light);font-size:0.87rem;padding:1.5rem 0;text-align:center;">
                    No rooms in the registry yet. Click <strong>Add Room</strong> to get started.
                </p>
<?php else:
    $prev_building_id = null;
    foreach ($rooms_list as $room):
        // Group header when building changes
        if ($room['building_id'] !== $prev_building_id):
            if ($prev_building_id !== null) echo '</div><!-- /.room-building-group -->';
            $prev_building_id = $room['building_id'];
?>
                <div class="room-building-group" style="margin-bottom:1.5rem;">
                    <p style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:var(--text-light);margin:0 0 0.6rem;">
                        <?php echo htmlspecialchars($room['campus_name'] . ' — ' . $room['building_name']); ?>
                    </p>
<?php   endif; ?>
                <!-- Room card -->
                <div class="room-card">
                    <div class="room-img">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                        </svg>
                    </div>
                    <div class="room-info">
                        <div class="room-header">
                            <div>
                                <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                <p><?php
                                    $fl = !empty($room['floor_label']) ? $room['floor_label'] : $room['floor_number'] . 'F';
                                    echo htmlspecialchars($fl . ', ' . $room['building_name']);
                                ?></p>
                            </div>
                            <?php if ($room['seating_capacity'] !== null): ?>
                            <span class="capacity-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="13" height="13"
                                    style="margin-right:5px;vertical-align:middle;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <?php echo (int)$room['seating_capacity']; ?> seats
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php
                        $amenities_arr = [];
                        if (!empty($room['amenities'])) {
                            $dec = json_decode($room['amenities'], true);
                            if (is_array($dec)) $amenities_arr = $dec;
                        }
                        if (!empty($amenities_arr)): ?>
                        <div class="amenities" style="margin-top:8px;">
                            <?php foreach ($amenities_arr as $am): ?>
                                <span><?php echo htmlspecialchars($am); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <?php
                            $status_class = 'room-avail';
                            if ($room['status'] === 'Maintenance')  $status_class = 'room-occupied';
                            if ($room['status'] === 'Not Bookable') $status_class = 'room-occupied';
                            ?>
                            <span class="<?php echo $status_class; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="7" height="7"
                                    style="vertical-align:middle;margin-right:5px;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg>
                                <?php echo htmlspecialchars($room['status']); ?>
                            </span>
                            <div style="display:flex;gap:6px;">
                                <a href="admin-dashboard.php?tab=rooms&edit_room=<?php echo (int)$room['room_id']; ?>"
                                    class="room-btn" style="text-decoration:none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" width="14" height="14">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                    </svg>
                                    Edit
                                </a>
                                <a href="admin-dashboard.php?archive_room=<?php echo (int)$room['room_id']; ?>"
                                    class="room-btn"
                                    style="text-decoration:none;color:var(--danger);"
                                    onclick="return confirm('Archive this room? It will be hidden from the registry.')">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" width="14" height="14">
                                        <polyline points="3 6 5 6 21 6" />
                                        <path d="M19 6l-1 14H6L5 6" />
                                        <path d="M10 11v6" /><path d="M14 11v6" />
                                        <path d="M9 6V4h6v2" />
                                    </svg>
                                    Archive
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
<?php endforeach;
    if ($prev_building_id !== null) echo '</div><!-- /.room-building-group -->';
endif; ?>
            </div><!-- /.room-list -->

            </div><!-- /#rooms-active-panel -->

            <!-- ── Archived rooms sub-panel ────────────────────────────── -->
            <div class="rooms-sub-panel" id="rooms-archived-panel">
                <div class="eq-card">
                    <div class="tbl-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Room Name</th>
                                    <th>Location</th>
                                    <th>Status (at archive)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rooms_archived)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted"
                                            style="text-align:center;padding:2.5rem;">
                                            No archived rooms.
                                        </td>
                                    </tr>
                                <?php else: foreach ($rooms_archived as $ar):
                                    $ar_floor = !empty($ar['floor_label']) ? $ar['floor_label'] : $ar['floor_number'] . 'F';
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($ar['room_name']); ?></td>
                                        <td><?php echo htmlspecialchars($ar_floor . ', ' . $ar['building_name'] . ' — ' . $ar['campus_name']); ?></td>
                                        <td>
                                            <span class="stock-badge <?php echo $ar['status'] === 'Available' ? 'stock-avail' : 'stock-low'; ?>">
                                                <?php echo htmlspecialchars($ar['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-cell">
                                            <div class="action-btns">
                                                <a href="admin-dashboard.php?restore_room=<?php echo (int)$ar['room_id']; ?>"
                                                    class="btn-action btn-restore" title="Restore to active">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        width="14" height="14">
                                                        <polyline points="1 4 1 10 7 10" />
                                                        <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /#rooms-archived-panel -->

            <!-- ── All Reservations sub-panel ──────────────────────────── -->
            <div class="rooms-sub-panel" id="rooms-reservations-panel">
                <div class="eq-card">
                    <div class="page-header-block" style="padding:1rem 1.2rem .5rem;">
                        <p class="page-subtitle">All room reservations across all faculty and rooms. Admin can cancel any Approved reservation.</p>
                    </div>
                    <div class="tbl-wrap">
                        <table class="admin-table">
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
                                        <td colspan="11" class="text-muted" style="text-align:center;padding:2.5rem;">No reservations yet.</td>
                                    </tr>
                                <?php else: foreach ($admin_room_reservations as $ar):
                                    $ar_pill = 'pill-approved';
                                    if ($ar['status'] === 'Declined')  $ar_pill = 'pill-declined';
                                    if ($ar['status'] === 'Cancelled') $ar_pill = 'pill-cancelled';

                                    $ar_submitted = match($ar['submitted_as']) {
                                        'adviser' => 'Adviser',
                                        'student' => 'Student (via code)',
                                        default   => 'Personal',
                                    };
                                    $ar_who = $ar['submitted_as'] === 'student' && !empty($ar['submitted_by_name'])
                                        ? htmlspecialchars($ar['submitted_by_name']) . '<br><small style="color:var(--text-light);">via ' . htmlspecialchars($ar['faculty_name']) . '</small>'
                                        : htmlspecialchars($ar['faculty_name']);

                                    // Cancel eligibility: Approved rows only — cutoff enforced server-side
                                    // The PHP display of the cancel button is always shown for Approved;
                                    // the 1-hour guard is enforced by cancel-reservation.php (RoomCancellation::cancel).
                                ?>
                                    <tr>
                                        <td class="text-muted" style="font-size:.78rem;">#<?php echo (int)$ar['id']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($ar['room_name']); ?></td>
                                        <td><?php echo htmlspecialchars($ar['floor_label'] . ', ' . $ar['building_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?></td>
                                        <td style="white-space:nowrap;"><?php echo htmlspecialchars($ar['start_fmt'] . ' – ' . $ar['end_fmt']); ?></td>
                                        <td><?php echo $ar_who; ?></td>
                                        <td><?php echo htmlspecialchars($ar_submitted); ?></td>
                                        <td><?php echo htmlspecialchars($ar['purpose']); ?></td>
                                        <td><span class="status-pill <?php echo $ar_pill; ?>"><?php echo htmlspecialchars($ar['status']); ?></span></td>
                                        <td style="color:var(--text-light);font-size:.78rem;"><?php echo $ar['reason'] ? htmlspecialchars($ar['reason']) : '—'; ?></td>
                                        <td>
                                            <?php if ($ar['status'] === 'Approved'): ?>
                                                <button class="btn-action btn-override-req btn-cancel-rr-admin"
                                                    data-action="admin-cancel-reservation"
                                                    data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                    data-room-name="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                    data-faculty-name="<?php echo htmlspecialchars($ar['faculty_name']); ?>"
                                                    title="Cancel this reservation">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" width="14" height="14">
                                                        <circle cx="12" cy="12" r="10"/>
                                                        <line x1="15" y1="9" x2="9" y2="15"/>
                                                        <line x1="9" y1="9" x2="15" y2="15"/>
                                                    </svg>
                                                    Cancel
                                                </button>
                                            <?php else: ?>
                                                <span style="color:var(--text-light);font-size:.78rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /#rooms-reservations-panel -->

            <!-- ── Room Issues sub-panel ───────────────────────────────── -->
            <div class="rooms-sub-panel" id="rooms-issues-panel">
                <div class="eq-card">
                    <div class="page-header-block" style="padding:1rem 1.2rem .5rem;">
                        <p class="page-subtitle">Room issue reports submitted by faculty. Review and resolve each report — rooms are not automatically set to Maintenance.</p>
                    </div>
                    <div class="tbl-wrap">
                        <table class="admin-table">
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
                                        <td colspan="8" class="text-muted" style="text-align:center;padding:2.5rem;">No issue reports yet.</td>
                                    </tr>
                                <?php else: foreach ($admin_room_issues as $issue):
                                    $issue_pill = 'pill-waiting';
                                    if ($issue['status'] === 'Resolved')  $issue_pill = 'pill-approved';
                                    if ($issue['status'] === 'Dismissed') $issue_pill = 'pill-declined';
                                ?>
                                    <tr>
                                        <td class="text-muted" style="font-size:.78rem;">#<?php echo (int)$issue['id']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($issue['room_name']); ?></td>
                                        <td><?php echo htmlspecialchars($issue['floor_label'] . ', ' . $issue['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($issue['reported_by_name']); ?></td>
                                        <td style="max-width:240px;font-size:.82rem;"><?php echo htmlspecialchars($issue['description']); ?></td>
                                        <td><span class="status-pill <?php echo $issue_pill; ?>"><?php echo htmlspecialchars($issue['status']); ?></span></td>
                                        <td style="font-size:.78rem;white-space:nowrap;"><?php echo date('M d, Y g:i A', strtotime($issue['created_at'])); ?></td>
                                        <td>
                                            <?php if ($issue['status'] === 'Open'): ?>
                                                <button class="btn-action btn-override-req"
                                                    data-action="open-issue-review"
                                                    data-issue-id="<?php echo (int)$issue['id']; ?>"
                                                    data-room-name="<?php echo htmlspecialchars($issue['room_name']); ?>"
                                                    data-reporter="<?php echo htmlspecialchars($issue['reported_by_name']); ?>"
                                                    data-description="<?php echo htmlspecialchars($issue['description']); ?>"
                                                    title="Review this issue report">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" width="14" height="14">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                    </svg>
                                                    Review
                                                </button>
                                            <?php else: ?>
                                                <span style="color:var(--text-light);font-size:.78rem;">
                                                    <?php echo $issue['status']; ?>
                                                    <?php if ($issue['admin_notes']): ?>
                                                        <br><small><?php echo htmlspecialchars(mb_substr($issue['admin_notes'], 0, 60)); ?><?php echo strlen($issue['admin_notes']) > 60 ? '…' : ''; ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /#rooms-issues-panel -->

        </div><!-- /panel-rooms -->


        <!-- ============================================================
         TAB: FACULTY
    ============================================================ -->
        <div class="tab-panel" id="panel-faculty">

            <!-- Section header -->
            <div class="section-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="8.5" cy="7" r="4" />
                        <line x1="20" y1="8" x2="20" y2="14" />
                        <line x1="23" y1="11" x2="17" y2="11" />
                    </svg>
                    Faculty Management
                </h2>
                <p>Create and manage faculty accounts.</p>
            </div>

            <!-- Two-column layout: form left, list right -->
            <div class="faculty-layout">

                <!-- ── CREATE FORM ──────────────────────────────────── -->
                <div class="eq-card faculty-form-card">
                    <div class="eq-card-header">
                        <h2>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" width="18" height="18"
                                 style="margin-right:8px;vertical-align:middle;">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                            Create Faculty Account
                        </h2>
                    </div>
                    <div class="eq-card-body">

                    <!-- CSRF token -->
                    <?= csrf_field() ?>

                    <!-- PUPSync Email -->
                    <div class="form-group">
                        <label for="fac-email">PUPSync Email <span class="req-star">*</span></label>
                        <input type="email" id="fac-email" name="pupsync_email"
                               class="form-control-custom" maxlength="254" required
                               placeholder="e.g. juan.delacruz@pup.edu.ph">
                    </div>

                    <!-- Backup Email -->
                    <div class="form-group">
                        <label for="fac-backup">Google Account / Backup Email</label>
                        <input type="email" id="fac-backup" name="backup_email"
                               class="form-control-custom" maxlength="254"
                               placeholder="Optional">
                    </div>

                    <!-- First Name + Middle Name (side-by-side) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fac-first">First Name <span class="req-star">*</span></label>
                            <input type="text" id="fac-first" name="first_name"
                                   class="form-control-custom" maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label for="fac-middle">Middle Name</label>
                            <input type="text" id="fac-middle" name="middle_name"
                                   class="form-control-custom" maxlength="100"
                                   placeholder="Optional">
                        </div>
                    </div>

                    <!-- Last Name -->
                    <div class="form-group">
                        <label for="fac-last">Last Name <span class="req-star">*</span></label>
                        <input type="text" id="fac-last" name="last_name"
                               class="form-control-custom" maxlength="100" required>
                    </div>

                    <!-- Password -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fac-password">Password <span class="req-star">*</span></label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="fac-password" name="password"
                                       class="form-control-custom" maxlength="128" required
                                       placeholder="Min. 8 characters">
                                <button type="button" class="fac-pw-toggle" data-target="fac-password" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" width="16" height="16">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="fac-confirm">Confirm Password <span class="req-star">*</span></label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="fac-confirm" name="confirm_password"
                                       class="form-control-custom" maxlength="128" required
                                       placeholder="Re-enter password">
                                <button type="button" class="fac-pw-toggle" data-target="fac-confirm" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" width="16" height="16">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Adviser toggle -->
                    <div class="form-group faculty-adviser-toggle-wrap">
                        <label class="faculty-toggle-label">
                            <input type="checkbox" id="fac-adviser" name="is_org_adviser"
                                   value="1" class="faculty-toggle-input">
                            <span class="faculty-toggle-track"></span>
                            Is this faculty an organization adviser?
                        </label>
                    </div>

                    <!-- Organization dropdown (hidden by default) -->
                    <div class="form-group" id="fac-org-group" style="display:none;">
                        <label for="fac-org">Organization <span class="req-star">*</span></label>
                        <?php
                        $org_opts_res = $conn->query(
                            "SELECT id, name FROM tbl_organizations ORDER BY name ASC"
                        );
                        if ($org_opts_res && $org_opts_res->num_rows > 0): ?>
                            <select id="fac-org" name="organization_id"
                                    class="form-control-custom">
                                <option value="">— Select Organization —</option>
                                <?php while ($org_row = $org_opts_res->fetch_assoc()): ?>
                                    <option value="<?= (int)$org_row['id'] ?>">
                                        <?= htmlspecialchars($org_row['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        <?php else: ?>
                            <select id="fac-org" name="organization_id"
                                    class="form-control-custom" disabled>
                                <option value="">— Organizations unavailable —</option>
                            </select>
                            <small class="faculty-field-error">
                                Could not load organizations. Please refresh after applying the DB migration.
                            </small>
                        <?php endif; ?>
                    </div>

                    <!-- Inline feedback area -->
                    <div id="fac-form-alert" class="alert-banner hidden" role="alert"></div>

                    <!-- Submit -->
                    <button type="button" id="fac-submit-btn"
                            class="btn-submit-form">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" width="16" height="16"
                             style="vertical-align:middle;margin-right:6px;">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="16" />
                            <line x1="8" y1="12" x2="16" y2="12" />
                        </svg>
                        Create Account
                    </button>

                    </div><!-- /eq-card-body -->
                </div><!-- /faculty-form-card -->


                <!-- ── FACULTY LIST ─────────────────────────────────── -->
                <div class="eq-card">
                    <div class="eq-card-header">
                        <h2>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" width="18" height="18"
                                 style="margin-right:8px;vertical-align:middle;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            Faculty Accounts
                        </h2>
                        <span class="status-pill pill-info" style="font-size:0.72rem;">
                            <?php
                            $fac_count = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_users");
                            echo ($fac_count) ? (int)$fac_count->fetch_assoc()['cnt'] : 0;
                            ?> total
                        </span>
                    </div>
                    <div class="tbl-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>PUPSync Email</th>
                                    <th>Role</th>
                                    <th>Organization</th>
                                    <th>Allow Org Borrowing</th>
                                </tr>
                            </thead>
                            <tbody id="faculty-list-tbody">
                            <?php
                            // Guard: check whether allow_org_borrowing column exists before querying it.
                            // If the dual-borrowing-mode migration hasn't been run yet the column is absent
                            // and the query would throw a fatal error. Fall back to 0 for all rows.
                            $_aob_col = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'allow_org_borrowing'");
                            $_has_aob_col = $_aob_col && $_aob_col->num_rows > 0;
                            $fac_res = $conn->query(
                                "SELECT u.fullname, u.email, u.role,
                                        u.faculty_id,"
                                . ($_has_aob_col ? " u.allow_org_borrowing," : " 0 AS allow_org_borrowing,")
                                . "     o.name AS org_name
                                   FROM tbl_users u
                                   LEFT JOIN tbl_organizations o
                                     ON u.organization_id = o.id
                                   ORDER BY u.fullname ASC"
                            );
                            if ($fac_res && $fac_res->num_rows > 0):
                                while ($frow = $fac_res->fetch_assoc()):
                                    $roleClass = ($frow['role'] === 'Organization Adviser') ? 'pill-approved' : 'pill-info';
                            ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($frow['fullname']) ?></td>
                                    <td><?= htmlspecialchars($frow['email']) ?></td>
                                    <td>
                                        <span class="status-pill <?= $roleClass ?>">
                                            <?= htmlspecialchars($frow['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($frow['org_name']): ?>
                                            <span class="status-pill pill-info"><?= htmlspecialchars($frow['org_name']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-light);">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <label class="faculty-toggle-label">
                                            <input type="checkbox"
                                                   class="faculty-toggle-input org-borrowing-toggle"
                                                   data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                                                   <?= $frow['allow_org_borrowing'] == 1 ? 'checked' : '' ?>>
                                            <span class="faculty-toggle-track"></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endwhile;
                            else: ?>
                                <tr id="fac-empty-row">
                                    <td colspan="5" class="text-muted" style="text-align:center;padding:3rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                             stroke-linejoin="round" width="40" height="40"
                                             style="display:block;margin:0 auto 10px;opacity:0.3;">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                        </svg>
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

    </main><!-- /app-main -->

    </div><!-- /app-body -->


    <!-- ================================================================
     OVERLAY: ACCOUNT
================================================================ -->
    <div class="overlay-page" id="accountOverlay">
        <div class="overlay-topbar">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="accountOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg>
                Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">My Account</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <span>PUPSYNC</span>
            </div>
        </div>

        <div class="account-layout">
            <div class="account-sidebar">
                <span class="account-sidebar-label">Admin Account</span>
                <button class="acc-nav-btn active" data-acc-tab="acc-overview">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <circle cx="8" cy="12" r="2" />
                        <path d="M14 9h4" />
                        <path d="M14 12h4" />
                        <path d="M14 15h2" />
                    </svg>
                    Overview
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-security">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    Security
                </button>
            </div>
            <div class="account-content">
                <!-- Overview -->
                <div id="acc-overview" class="overlay-sub-panel active">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account › Overview</span>
                        <h2>Admin Profile</h2>
                        <p>Your administrator account details.</p>
                    </div>
                    <div class="account-hero-card">
                        <div class="acc-avatar-large">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div class="acc-hero-info">
                            <h2>
                                <?php echo htmlspecialchars($admin_name); ?>
                            </h2>
                            <p>System Administrator</p>
                            <span class="acc-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12"
                                    style="vertical-align:middle;margin-right:4px;">
                                    <circle cx="12" cy="12" r="7" fill="#22c55e" stroke="none" />
                                </svg>
                                Active Admin
                            </span>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">Edit
                                Profile</button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;"
                                data-action="profile-save">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" width="14" height="14">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Save
                            </button>
                            <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;"
                                data-action="profile-cancel">Cancel</button>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Admin Information</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Display Name</span>
                            <span class="info-val <?php echo empty($admin_name) ? 'empty' : ''; ?>"
                                data-field="admin_name">
                                <?php
                                echo htmlspecialchars($admin_name);
                                ?>
                            </span>
                            <input class="info-input-f" data-input="admin_name"
                                value="<?php echo htmlspecialchars($admin_name); ?>" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Role</span>
                            <span class="info-val">Administrator</span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Email</span>
                            <span class="info-val 
                                    <?php echo empty($admin_email) ? 'empty' : ''; ?>" data-field="admin_email">
                                <?php
                                echo !empty($admin_email) ? htmlspecialchars($admin_email) : '— Not provided';
                                ?>
                            </span>
                            <input class="info-input-f" data-input="admin_email" type="email"
                                value="<?php echo htmlspecialchars($admin_email ?? ''); ?>"
                                placeholder="admin@pup.edu.ph" disabled style="display:none;">
                        </div>
                    </div>
                </div>

                <!-- Security -->
                <div id="acc-security" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account › Security</span>
                        <h2>Security</h2>
                        <p>Manage your admin password and session security.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Login & Security</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Password</span>
                            <span class="info-val">••••••••••</span>
                            <button class="btn-borrow"
                                style="width:auto;padding:6px 16px;font-size:0.75rem;margin-left:auto;"
                                data-action="open-change-pass">
                                Change
                            </button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Last Login</span>
                            <span class="info-val">
                                <?php
                                $last_login_display = '— Not available';
                                if (!empty($_SESSION['admin_last_login'])) {
                                    $ts = strtotime($_SESSION['admin_last_login']);
                                    if ($ts !== false) $last_login_display = date('M d, Y g:i A', $ts);
                                }
                                echo htmlspecialchars($last_login_display);
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Session</span>
                            <span class="info-val"><span class="stock-badge stock-avail">Active</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /accountOverlay -->


    <!-- ================================================================
     OVERLAY: NOTIFICATIONS
================================================================ -->
    <div class="overlay-page" id="notifOverlay" style="flex-direction:column;overflow-y:auto;">
        <div class="overlay-topbar" style="flex-shrink:0;">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="notifOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg>
                Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">Notifications</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <span>PUPSYNC</span>
            </div>
        </div>

        <div class="notif-wrapper">
            <div
                style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:10px;">
                <div class="overlay-section-header" style="flex:1;margin-bottom:0;">
                    <span class="section-eyebrow">Admin Inbox</span>
                    <h2>Notifications</h2>
                    <p>You have <strong style="color:var(--accent-maroon);" id="unreadCount">
                            <?php echo $stat_waiting + $stat_overdue + 2; ?> unread
                        </strong> notifications.</p>
                </div>
                <button class="mark-read-btn" data-action="mark-all-read" style="margin-top:0.5rem;">Mark all as
                    read</button>
            </div>

            <div class="notif-filter-tabs">
                <button class="notif-tab active" data-notif-filter="all">All</button>
                <button class="notif-tab" data-notif-filter="unread">Unread</button>
                <button class="notif-tab" data-notif-filter="request">Requests</button>
                <button class="notif-tab" data-notif-filter="overdue">Overdue</button>
                <button class="notif-tab" data-notif-filter="system">System</button>
            </div>

            <?php if ($stat_overdue > 0): ?>
                <div class="notif-group" style="color:#e65100;">⚠️ Overdue — Immediate Action Needed</div>
                <?php
                $ov_notif = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status='Overdue' ORDER BY return_date ASC LIMIT 5");
                while ($on = mysqli_fetch_assoc($ov_notif)):
                    $days_late = floor((time() - strtotime($on['return_date'])) / 86400);
                ?>
                    <div class="notif-item notif-card unread notif-urgent" data-cat="overdue">
                        <div class="notif-card-main" role="button" tabindex="0">
                            <div class="notif-icon ni-urgent">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                    <line x1="12" y1="9" x2="12" y2="13" />
                                    <line x1="12" y1="17" x2="12.01" y2="17" />
                                </svg>
                            </div>
                            <div class="notif-body-wrap">
                                <h4>Overdue:
                                    <?php echo htmlspecialchars($on['equipment_name']); ?>
                                </h4>
                                <p><strong>
                                        <?php echo htmlspecialchars($on['faculty_name']); ?>
                                    </strong> has not returned this item.
                                    <?php echo $days_late; ?> day
                                    <?php echo $days_late != 1 ? 's' : ''; ?> overdue.
                                </p>
                            </div>
                            <div class="notif-meta">
                                <span class="notif-time">Due
                                    <?php echo date('M d', strtotime($on['return_date'])); ?>
                                </span>
                                <div class="unread-dot"></div>
                                <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                        </div>
                        <div class="notif-card-detail">
                            <div class="notif-detail-grid">
                                <div class="notif-detail-row"><span class="ndl">Student</span><span class="ndv">
                                        <?php echo htmlspecialchars($on['faculty_name']); ?> (
                                        <?php echo htmlspecialchars($on['faculty_id']); ?>)
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Equipment</span><span class="ndv">
                                        <?php echo htmlspecialchars($on['equipment_name']); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Due Date</span><span class="ndv"
                                        style="color:#e65100;font-weight:600;">
                                        <?php echo date('M d, Y', strtotime($on['return_date'])); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Days Overdue</span><span class="ndv"
                                        style="color:#e65100;font-weight:700;">
                                        <?php echo $days_late; ?> day
                                        <?php echo $days_late != 1 ? 's' : ''; ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Borrow Date</span><span class="ndv">
                                        <?php echo date('M d, Y', strtotime($on['borrow_date'])); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Room / Instructor</span><span class="ndv">
                                        <?php echo htmlspecialchars($on['room'] ?? '—'); ?> /
                                        <?php echo htmlspecialchars($on['instructor'] ?? '—'); ?>
                                    </span></div>
                            </div>
                            <div class="notif-card-actions">
                                <a href="admin-dashboard.php?view=overdue" class="notif-action-btn notif-action-primary"
                                    data-action="close-overlay" data-target="notifOverlay">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    View in Overdue
                                </a>
                                <button class="notif-action-btn notif-action-dismiss" data-notif-dismiss>Got it</button>
                            </div>
                        </div>
                    </div>
            <?php endwhile;
            endif; ?>

            <?php if ($stat_waiting > 0): ?>
                <div class="notif-group">Pending Requests</div>
                <?php
                $wt_notif = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status='Waiting' ORDER BY request_date DESC LIMIT 5");
                while ($wn = mysqli_fetch_assoc($wt_notif)):
                ?>
                    <div class="notif-item notif-card unread" data-cat="request">
                        <div class="notif-card-main" role="button" tabindex="0">
                            <div class="notif-icon ni-warn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                            </div>
                            <div class="notif-body-wrap">
                                <h4>New Borrow Request</h4>
                                <p><strong>
                                        <?php echo htmlspecialchars($wn['faculty_name']); ?>
                                    </strong> requested <strong>
                                        <?php echo htmlspecialchars($wn['equipment_name']); ?>
                                    </strong> — awaiting approval.</p>
                            </div>
                            <div class="notif-meta">
                                <span class="notif-time">
                                    <?php echo date('M d', strtotime($wn['request_date'])); ?>
                                </span>
                                <div class="unread-dot"></div>
                                <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                        </div>
                        <div class="notif-card-detail">
                            <div class="notif-detail-grid">
                                <div class="notif-detail-row"><span class="ndl">Student</span><span class="ndv">
                                        <?php echo htmlspecialchars($wn['faculty_name']); ?> (
                                        <?php echo htmlspecialchars($wn['faculty_id']); ?>)
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Equipment</span><span class="ndv">
                                        <?php echo htmlspecialchars($wn['equipment_name']); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Borrow Date</span><span class="ndv">
                                        <?php echo date('M d, Y', strtotime($wn['borrow_date'])); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Return Date</span><span class="ndv">
                                        <?php echo date('M d, Y', strtotime($wn['return_date'])); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Requested On</span><span class="ndv">
                                        <?php echo date('M d, Y g:i A', strtotime($wn['request_date'])); ?>
                                    </span></div>
                                <div class="notif-detail-row"><span class="ndl">Room / Instructor</span><span class="ndv">
                                        <?php echo htmlspecialchars($wn['room'] ?? '—'); ?> /
                                        <?php echo htmlspecialchars($wn['instructor'] ?? '—'); ?>
                                    </span></div>
                            </div>
                            <div class="notif-card-actions">
                                <button class="notif-action-btn notif-action-dismiss" data-notif-dismiss>Got it</button>
                            </div>
                        </div>
                    </div>
            <?php endwhile;
            endif; ?>

            <div class="notif-group">System</div>
            <div class="notif-item notif-card unread" data-cat="system">
                <div class="notif-card-main" role="button" tabindex="0">
                    <div class="notif-icon ni-info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                    </div>
                    <div class="notif-body-wrap">
                        <h4>Scheduled Maintenance Tonight</h4>
                        <p>PUPSYNC will undergo maintenance from 11:00 PM to 1:00 AM. Please inform users.</p>
                    </div>
                    <div class="notif-meta">
                        <span class="notif-time">8:00 AM</span>
                        <div class="unread-dot"></div>
                        <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                </div>
                <div class="notif-card-detail">
                    <div class="notif-detail-grid">
                        <div class="notif-detail-row"><span class="ndl">Window</span><span class="ndv">11:00 PM – 1:00
                                AM tonight</span></div>
                        <div class="notif-detail-row"><span class="ndl">Affected</span><span class="ndv">All PUPSYNC
                                services (lending, inventory, login)</span></div>
                        <div class="notif-detail-row"><span class="ndl">Action Required</span><span class="ndv">Notify
                                active users before 10:30 PM</span></div>
                    </div>
                    <div class="notif-card-actions">
                        <button class="notif-action-btn notif-action-dismiss" data-notif-dismiss>Got it</button>
                    </div>
                </div>
            </div>

            <div class="notif-item notif-card" data-cat="system">
                <div class="notif-card-main" role="button" tabindex="0">
                    <div class="notif-icon ni-success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <div class="notif-body-wrap">
                        <h4>Database Backup Completed</h4>
                        <p>Automatic daily backup of <code>lending_db</code> completed successfully.</p>
                    </div>
                    <div class="notif-meta">
                        <span class="notif-time">Yesterday, 2:00 AM</span>
                        <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                </div>
                <div class="notif-card-detail">
                    <div class="notif-detail-grid">
                        <div class="notif-detail-row"><span class="ndl">Database</span><span
                                class="ndv">lending_db</span></div>
                        <div class="notif-detail-row"><span class="ndl">Completed At</span><span class="ndv">Yesterday
                                at 2:00 AM</span></div>
                        <div class="notif-detail-row"><span class="ndl">Status</span><span class="ndv"><span
                                    class="stock-badge stock-avail">Success</span></span></div>
                    </div>
                    <div class="notif-card-actions">
                        <button class="notif-action-btn notif-action-dismiss" data-notif-dismiss>Got it</button>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /notifOverlay -->


    <!-- ================================================================
     OVERLAY: SETTINGS
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">
        <div class="overlay-topbar">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="settingsOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg>
                Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">Settings</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                <button class="s-nav-item active" data-sett-tab="st-appearance">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        style="vertical-align:middle;margin-right:6px;">
                        <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                        <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                        <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                        <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                        <path
                            d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                    </svg>
                    Appearance
                </button>
                <button class="s-nav-item" data-sett-tab="st-accessibility">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        style="vertical-align:middle;margin-right:6px;">
                        <circle cx="12" cy="6" r="2" />
                        <path d="m4 14 8-2 8 2" />
                        <path d="M8 12v1.5l-3 5" />
                        <path d="M16 12v1.5l3 5" />
                        <path d="m9 22 3-6 3 6" />
                    </svg>
                    Accessibility
                </button>
                <span class="s-cat-label">Admin</span>
                <button class="s-nav-item" data-sett-tab="st-notifications">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        style="vertical-align:middle;margin-right:6px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    Notifications
                </button>
                <div class="s-divider"></div>
                <span class="s-cat-label">System</span>
                <button class="s-nav-item" data-sett-tab="st-advanced">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        style="vertical-align:middle;margin-right:6px;">
                        <line x1="4" y1="21" x2="4" y2="14" />
                        <line x1="4" y1="10" x2="4" y2="3" />
                        <line x1="12" y1="21" x2="12" y2="12" />
                        <line x1="12" y1="8" x2="12" y2="3" />
                        <line x1="20" y1="21" x2="20" y2="16" />
                        <line x1="20" y1="12" x2="20" y2="3" />
                        <line x1="1" y1="14" x2="7" y2="14" />
                        <line x1="9" y1="8" x2="15" y2="8" />
                        <line x1="17" y1="16" x2="23" y2="16" />
                    </svg>
                    Advanced
                </button>
            </div>

            <div class="settings-content">
                <!-- Appearance -->
                <div id="st-appearance" class="overlay-sub-panel active">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Appearance</span>
                        <h2>Appearance</h2>
                        <p>Customize how the admin portal looks and feels.</p>
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
                                <div class="theme-lbl">Light <svg id="tc-light" xmlns="http://www.w3.org/2000/svg"
                                        width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="color:var(--accent-maroon);vertical-align:middle;">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg></div>
                            </div>
                            <div class="theme-opt" id="tp-dark" data-action="apply-theme" data-theme="dark">
                                <div class="theme-prev tp-dark">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">Dark <svg id="tc-dark" xmlns="http://www.w3.org/2000/svg"
                                        width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="color:var(--accent-maroon);vertical-align:middle;display:none;">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg></div>
                            </div>
                            <div class="theme-opt" id="tp-hc" data-action="apply-theme" data-theme="high-contrast">
                                <div class="theme-prev tp-hc">
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                    <div class="theme-prev-bar"></div>
                                </div>
                                <div class="theme-lbl">High Contrast <svg id="tc-hc" xmlns="http://www.w3.org/2000/svg"
                                        width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
                                        style="color:var(--accent-maroon);vertical-align:middle;display:none;">
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
                            <div class="c-dot selected" style="background:#600302;" data-action="apply-accent"
                                data-color="#600302" data-light="#f3e5e6" title="Maroon (Default)"></div>
                            <div class="c-dot" style="background:#1a5276;" data-action="apply-accent"
                                data-color="#1a5276" data-light="#d6eaf8" title="Navy Blue"></div>
                            <div class="c-dot" style="background:#1e8449;" data-action="apply-accent"
                                data-color="#1e8449" data-light="#d5f5e3" title="Forest Green"></div>
                            <div class="c-dot" style="background:#7d3c98;" data-action="apply-accent"
                                data-color="#7d3c98" data-light="#f0e6fa" title="Purple"></div>
                            <div class="c-dot" style="background:#d35400;" data-action="apply-accent"
                                data-color="#d35400" data-light="#fde8d8" title="Burnt Orange"></div>
                            <div class="c-dot" style="background:#2e86c1;" data-action="apply-accent"
                                data-color="#2e86c1" data-light="#d6eaf8" title="Sky Blue"></div>
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
                                <p>Makes cards and table rows more compact.</p>
                            </div>
                            <label class="toggle-sw"><input type="checkbox" id="compactToggle"
                                    data-action="apply-compact"><span class="toggle-track"></span></label>
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
                            <input type="range" min="80" max="130" value="100" step="5" id="fontSizeRange"
                                data-action="apply-fontsize">
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
                            <label class="toggle-sw"><input type="checkbox" id="reduceMotionToggle"
                                    data-action="apply-reduce-motion"><span class="toggle-track"></span></label>
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
                            <label class="toggle-sw"><input type="checkbox" id="focusRingToggle"
                                    data-action="apply-focus-ring"><span class="toggle-track"></span></label>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div id="st-notifications" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">Settings › Notifications</span>
                        <h2>Notification Preferences</h2>
                        <p>Control which admin notifications you receive.</p>
                    </div>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>Request Alerts</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>New Borrow Requests</h4>
                                <p>Alert when a student submits a new request.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span
                                    class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Overdue Items</h4>
                                <p>Alert when a borrowed item passes its return date.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span
                                    class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Low Stock Warning</h4>
                                <p>Alert when any item drops to 2 or fewer units.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span
                                    class="toggle-track"></span></label>
                        </div>
                    </div>
                    <div class="settings-card">
                        <div class="settings-card-head">
                            <h3>System</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>System Maintenance</h4>
                                <p>Scheduled downtime notifications.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span
                                    class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Database Backup</h4>
                                <p>Daily backup completion status.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span
                                    class="toggle-track"></span></label>
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
                                <p>Display equipment item IDs in tables.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span
                                    class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Verbose Error Messages</h4>
                                <p>Show detailed database error information.</p>
                            </div><label class="toggle-sw"><input type="checkbox"><span
                                    class="toggle-track"></span></label>
                        </div>
                    </div>
                    <div class="settings-card danger-card">
                        <div class="settings-card-head">
                            <h3 style="color:var(--danger);">Reset</h3>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Reset All Settings</h4>
                                <p>Restore all appearance and accessibility defaults.</p>
                            </div>
                            <button class="btn-danger-sm" data-action="reset-settings">Reset</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /settingsOverlay -->

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top:1rem;font-weight:600;color:var(--text-dark);font-size:0.9rem;">Processing...</p>
    </div>

    <!-- QR Scanner Modal -->
    <div id="qrScannerModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.75);align-items:center;justify-content:center;">
        <div style="background:var(--surface,#fff);border-radius:20px;padding:2rem;max-width:420px;width:90%;text-align:center;position:relative;">
            <button id="closeQrScanner" style="position:absolute;top:12px;right:12px;background:none;border:none;cursor:pointer;font-size:22px;color:#555;">✕</button>
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;color:var(--text-dark,#1a1a1a);">Scan Return QR Code</h3>
            <p style="font-size:0.8rem;color:#888;margin-bottom:16px;">Point the camera at the faculty member's QR code.</p>
            <div style="position:relative;width:100%;border-radius:12px;overflow:hidden;background:#000;">
                <video id="qrVideo" style="width:100%;display:block;" playsinline autoplay></video>
                <!-- Scan guide overlay -->
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="width:200px;height:200px;border:3px solid rgba(255,255,255,0.8);border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,0.35);"></div>
                </div>
            </div>
            <p id="qrScanStatus" style="margin-top:14px;font-size:0.85rem;color:#888;">
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
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control-custom" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control-custom" minlength="4" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control-custom" minlength="4"
                            required>
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
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    Cancel Reservation
                </h2>
                <button type="button" class="btn-close-custom" id="closeAdminCancelRrModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
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
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Review Issue Report
                </h2>
                <button type="button" class="btn-close-custom" id="closeIssueReviewModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
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

</body>

</html>