<?php require_once __DIR__ . '/includes/admin-dashboard-functions.php'; ?>
<?php
// ── CONFIRM RETURN ─────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'return_confirm' && isset($_GET['id'])) {
    $req_id = intval($_GET['id']);
    $res = $conn->query("SELECT equipment_name FROM tbl_requests WHERE id = $req_id LIMIT 1");
    if ($res && $row_rc = $res->fetch_assoc()) {
        $conn->query("UPDATE tbl_requests SET status = 'Returned' WHERE id = $req_id");
        $eq = $conn->real_escape_string($row_rc['equipment_name']);
        $conn->query("UPDATE tbl_inventory SET quantity = quantity + 1 WHERE item_name = '$eq'");
    }
    header("Location: admin-dashboard.php?view=waiting");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Sync | Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-dashboard.css">
</head>

<body>

    <!-- ================================================================
     HEADER
================================================================ -->
    <header class="app-header">
        <div class="header-left">
            <div class="app-logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon" style="color:var(--accent-maroon)">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <div class="app-logo-text" style="display:flex;flex-direction:column;">
                    <span style="white-space:nowrap;line-height:1.1;">
                        <strong style="font-size:25px;">PUP</strong><span style="font-weight:500;font-size:21px;margin-left:1px;">SYNC</span>
                        <span class="admin-badge">Admin</span>
                    </span>
                    <small>Admin Portal</small>
                </div>
            </div>
        </div>

        <!-- Center: Top Navigation Tabs -->
        <nav class="nav-tabs-wrap" role="navigation" aria-label="Main Navigation">
            <button class="nav-tab active" data-tab="dashboard">Dashboard</button>
            <button class="nav-tab" data-tab="lending">Lending</button>
            <button class="nav-tab" data-tab="rooms">Rooms</button>
        </nav>

        <div class="header-right">
            <!-- Notification Bell -->
            <button class="notif-btn" data-action="open-overlay" data-target="notifOverlay" title="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <?php if ($stat_waiting > 0 || $stat_overdue > 0): ?>
                    <span class="notif-btn-badge"><?php echo $stat_waiting + $stat_overdue; ?></span>
                <?php endif; ?>
            </button>

            <div class="header-user-info">
                <span class="u-name"><?php echo htmlspecialchars($admin_name); ?></span>
                <span class="u-role">Administrator</span>
            </div>

            <div class="avatar-btn" id="avatarBtn" role="button" aria-haspopup="true" aria-expanded="false" title="Account menu">
                <?php echo htmlspecialchars($initials); ?>
            </div>

            <!-- Profile Dropdown -->
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>Account
                    </button>
                    <button class="dd-item" data-action="open-overlay" data-target="notifOverlay">
                        <div class="dd-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                <circle cx="12" cy="12" r="3" />
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                            </svg>
                        </div>Settings
                    </button>
                    <div class="dd-divider"></div>
                    <button class="dd-item dd-logout" data-action="logout">
                        <div class="dd-icon" style="background:#ffeaea;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="color:var(--danger)">
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
     MAIN
================================================================ -->
    <main id="app-main">

        <!-- Alerts -->
        <?php if (isset($_GET['added'])): ?>
            <div class="alert-banner alert-success" id="added-alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <strong>Success!</strong> Item added to inventory.
                <button class="alert-close" data-action="dismiss-alert" data-target="added-alert">✕</button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert-banner alert-success" id="updated-alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <strong>Updated!</strong> Item has been updated successfully.
                <button class="alert-close" data-action="dismiss-alert" data-target="updated-alert">✕</button>
            </div>
        <?php endif; ?>
        <?php if ($stat_overdue > 0): ?>
            <div class="alert-banner alert-danger" id="overdue-alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                <strong>Overdue Alert:</strong> <?php echo $stat_overdue; ?> item(s) are currently overdue and need immediate attention.
                <button class="alert-close" data-action="dismiss-alert" data-target="overdue-alert">✕</button>
            </div>
        <?php endif; ?>

        <!-- ============================================================
         TAB: DASHBOARD
    ============================================================ -->
        <div class="tab-panel active" id="panel-dashboard">
            <div class="section-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    Admin Dashboard
                </h2>
                <p><?php echo date('l, F j, Y'); ?> &mdash; Overview of all lending activity and inventory.</p>
            </div>

            <!-- Hero Card -->
            <div class="hero-card">
                <h1>Equipment Lending Management</h1>
                <p>Manage borrow requests, track inventory, approve or decline student requests — all from one place.</p>
            </div>

            <!-- Stats Grid -->
            <p style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:var(--text-light);margin-bottom:0.8rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                    <line x1="2" y1="20" x2="22" y2="20" />
                </svg>
                System Overview
            </p>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                        </svg></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value"><?php echo $stat_total_req; ?></div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-card stat-card-clickable" data-action="go-lending" data-lending="waiting" title="View pending requests">
                    <div class="stat-icon" style="background:#fff8e1;color:#c67c00;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg></div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:#c67c00;"><?php echo $stat_waiting; ?></div>
                    <div class="stat-sub stat-sub-link">Needs action →</div>
                </div>
                <div class="stat-card stat-card-clickable" data-action="go-lending" data-lending="approved" title="View approved requests">
                    <div class="stat-icon" style="background:#e3fcef;color:#00875a;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg></div>
                    <div class="stat-label">Approved</div>
                    <div class="stat-value" style="color:#00875a;"><?php echo $stat_approved; ?></div>
                    <div class="stat-sub stat-sub-link">Currently out →</div>
                </div>
                <div class="stat-card stat-card-clickable<?php echo $stat_overdue > 0 ? ' stat-card-alert' : ''; ?>" data-action="go-lending" data-lending="waiting" title="Overdue items need attention">
                    <div class="stat-icon" style="background:#fff3e0;color:#e65100;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg></div>
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value" style="color:#e65100;"><?php echo $stat_overdue; ?></div>
                    <div class="stat-sub stat-sub-link" style="color:#e65100;">Immediate action →</div>
                </div>
                <div class="stat-card stat-card-clickable" data-action="go-lending" data-lending="inventory">
                    <div class="stat-icon" style="background:#e3f2fd;color:#1565c0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                            <line x1="12" y1="22.08" x2="12" y2="12" />
                        </svg></div>
                    <div class="stat-label">Inventory Items</div>
                    <div class="stat-value" style="color:#1565c0;"><?php echo $stat_inv_total; ?></div>
                    <div class="stat-sub stat-sub-link">Manage stock →</div>
                </div>
                <?php if ($stat_inv_low > 0): ?>
                    <div class="stat-card stat-card-alert">
                        <div class="stat-icon" style="background:#fff8e1;color:#c67c00;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg></div>
                        <div class="stat-label">Low Stock</div>
                        <div class="stat-value" style="color:#c67c00;"><?php echo $stat_inv_low; ?></div>
                        <div class="stat-sub">Items with ≤2 units</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Home Grid -->
            <div class="home-grid">
                <!-- Recent Activity -->
                <div class="activity-container">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" style="color:var(--accent-maroon)">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                        Recent Activity
                    </h3>
                    <?php
                    $recent = mysqli_query($conn, "SELECT * FROM tbl_requests ORDER BY request_date DESC LIMIT 6");
                    if (mysqli_num_rows($recent) === 0): ?>
                        <p style="color:var(--text-light);font-size:0.83rem;text-align:center;padding:1rem;">No activity yet.</p>
                        <?php else:
                        while ($r = mysqli_fetch_assoc($recent)):
                            $dotClass = 'dot-waiting';
                            if ($r['status'] === 'Approved') $dotClass = 'dot-approved';
                            if ($r['status'] === 'Declined') $dotClass = 'dot-declined';
                            if ($r['status'] === 'Overdue')  $dotClass = 'dot-overdue';
                        ?>
                            <div class="activity-item">
                                <div class="activity-dot <?php echo $dotClass; ?>"></div>
                                <div class="activity-info">
                                    <h4><?php echo htmlspecialchars($r['student_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($r['equipment_name']); ?> &mdash; <?php echo htmlspecialchars($r['status']); ?></p>
                                </div>
                                <span class="activity-time"><?php echo date('M d', strtotime($r['request_date'])); ?></span>
                            </div>
                    <?php endwhile;
                    endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img" style="color:var(--accent-maroon)">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                        </svg>
                        Quick Actions
                    </h3>
                    <button class="qa-btn" data-action="go-lending" data-lending="waiting">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                        Pending Requests <span style="margin-left:auto;background:#fff8e1;color:#c67c00;font-size:0.72rem;font-weight:700;padding:2px 9px;border-radius:20px;"><?php echo $stat_waiting; ?></span>
                    </button>
                    <button class="qa-btn" data-action="go-lending" data-lending="inventory">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        </svg>
                        Manage Inventory
                    </button>
                    <button class="qa-btn" data-action="go-lending" data-lending="approved">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                        Approved Requests
                    </button>
                    <button class="qa-btn" data-action="go-lending" data-lending="raw">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                            <line x1="8" y1="6" x2="21" y2="6" />
                            <line x1="8" y1="12" x2="21" y2="12" />
                            <line x1="8" y1="18" x2="21" y2="18" />
                            <line x1="3" y1="6" x2="3.01" y2="6" />
                            <line x1="3" y1="12" x2="3.01" y2="12" />
                            <line x1="3" y1="18" x2="3.01" y2="18" />
                        </svg>
                        Raw Data
                    </button>
                    <button class="qa-btn" data-action="open-overlay" data-target="notifOverlay">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                        Notifications
                        <?php if ($stat_waiting + $stat_overdue > 0): ?>
                            <span class="notif-badge" style="font-size:0.7rem;padding:1px 7px;"><?php echo $stat_waiting + $stat_overdue; ?></span>
                        <?php endif; ?>
                    </button>
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                        <line x1="9" y1="12" x2="15" y2="12" />
                        <line x1="12" y1="9" x2="12" y2="15" />
                    </svg>
                    Borrow Requests <span class="lnb-badge"><?php echo $stat_waiting; ?></span>
                </button>
                <button class="lending-nav-btn" data-lending-nav="history">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="9" y1="13" x2="15" y2="13" />
                        <line x1="9" y1="17" x2="15" y2="17" />
                        <polyline points="9 9 10 9 12 9" />
                    </svg>
                    Borrow History <span class="lnb-badge"><?php echo $stat_approved + $stat_declined; ?></span>
                </button>
                <button class="lending-nav-btn" data-lending-nav="inventory">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    </svg>
                    Equipment Registry
                </button>
                <button class="lending-nav-btn" data-lending-nav="raw">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <line x1="8" y1="6" x2="21" y2="6" />
                        <line x1="8" y1="12" x2="21" y2="12" />
                        <line x1="8" y1="18" x2="21" y2="18" />
                        <line x1="3" y1="6" x2="3.01" y2="6" />
                        <line x1="3" y1="12" x2="3.01" y2="12" />
                        <line x1="3" y1="18" x2="3.01" y2="18" />
                    </svg>
                    Raw Data
                </button>
            </div>

            <!-- ── BORROW REQUESTS ────────────────────────────────────── -->
            <div class="lending-sub active" id="lending-waiting">
                <div class="page-header">
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                            <line x1="9" y1="12" x2="15" y2="12" />
                            <line x1="12" y1="9" x2="12" y2="15" />
                        </svg>Borrow Requests</h2>
                    <p>Review pending borrow requests and confirm equipment returns from students.</p>
                </div>

                <!-- Borrow Requests sub-tabs toggle -->
                <div class="history-toggle-wrap">
                    <button class="history-toggle-btn active" data-history-tab="pending-loans">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                        Pending Approval
                        <span class="history-toggle-count"><?php echo $stat_waiting; ?></span>
                    </button>
                    <button class="history-toggle-btn" data-history-tab="return-confirmation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <polyline points="1 4 1 10 7 10" />
                            <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                        </svg>
                        Return Confirmation
                        <span class="history-toggle-count"><?php echo $stat_approved; ?></span>
                    </button>
                </div>

                <!-- Pending Approval sub-panel -->
                <div class="history-panel active" id="history-pending-loans">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="waiting-body">
                                    <?php if (mysqli_num_rows($waiting_result) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted" style="text-align:center;padding:3rem;">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="40" height="40" style="display:block;margin:0 auto 10px;opacity:0.3;">
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
                                                <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($r['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td style="<?php echo $isPast ? 'color:var(--danger);font-weight:600;' : '' ?>">
                                                    <?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>
                                                    <?php if ($isPast): ?><br><small style="font-size:0.68rem;">(Date Passed)</small><?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($r['return_date'])); ?></td>
                                                <td><span class="status-pill pill-waiting">Pending</span></td>
                                                <td class="action-cell">
                                                    <div class="action-btns">
                                                        <a href="admin-dashboard.php?action=approve&id=<?php echo $r['id']; ?>" class="btn-action btn-approve" title="Approve">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                                                <polyline points="20 6 9 17 4 12" />
                                                            </svg>
                                                        </a>
                                                        <a href="admin-dashboard.php?action=decline&id=<?php echo $r['id']; ?>" class="btn-action btn-decline" title="Decline" onclick="return confirm('Decline this request?')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                                                <line x1="18" y1="6" x2="6" y2="18" />
                                                                <line x1="6" y1="6" x2="18" y2="18" />
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
                </div><!-- /history-pending-loans -->

                <!-- Return Confirmation sub-panel -->
                <div class="history-panel" id="history-return-confirmation">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="returnSearch" placeholder="Search by ID, name, or equipment...">
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
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="40" height="40" style="display:block;margin:0 auto 10px;opacity:0.3;">
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
                                                <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($r['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($r['borrow_date'])); ?></td>
                                                <td style="<?php echo $isOverdue ? 'color:var(--danger);font-weight:600;' : '' ?>">
                                                    <?php echo date('M d, Y', strtotime($r['return_date'])); ?>
                                                    <?php if ($isOverdue): ?><br><small style="font-size:0.68rem;">(Overdue)</small><?php endif; ?>
                                                </td>
                                                <td><span class="status-pill pill-approved">Out on Loan</span></td>
                                                <td class="action-cell">
                                                    <div class="action-btns">
                                                        <a href="admin-dashboard.php?action=return_confirm&id=<?php echo $r['id']; ?>"
                                                            class="btn-return-confirm"
                                                            title="Confirm item has been returned"
                                                            onclick="return confirm('Confirm that <?php echo htmlspecialchars(addslashes($r['student_name'])); ?> has returned the <?php echo htmlspecialchars(addslashes($r['equipment_name'])); ?>?')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
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
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
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
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                        Approved
                        <span class="history-toggle-count"><?php echo $stat_approved; ?></span>
                    </button>
                    <button class="history-toggle-btn" data-history-tab="declined">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        Declined
                        <span class="history-toggle-count"><?php echo $stat_declined; ?></span>
                    </button>
                </div>

                <!-- Approved sub-panel -->
                <div class="history-panel active" id="history-approved">
                    <div class="eq-card">
                        <div class="search-row">
                            <div class="search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                                    </tr>
                                </thead>
                                <tbody id="approved-list">
                                    <?php mysqli_data_seek($approved_result, 0);
                                    if (mysqli_num_rows($approved_result) === 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-muted" style="text-align:center;padding:2.5rem;">No approved requests.</td>
                                        </tr>
                                        <?php else: while ($r = mysqli_fetch_assoc($approved_result)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($r['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($r['borrow_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($r['return_date'])); ?></td>
                                                <td><span class="status-pill pill-approved">Approved</span></td>
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
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                                    </tr>
                                </thead>
                                <tbody id="declined-list">
                                    <?php mysqli_data_seek($declined_result, 0);
                                    if (mysqli_num_rows($declined_result) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted" style="text-align:center;padding:2.5rem;">No declined requests.</td>
                                        </tr>
                                        <?php else: while ($r = mysqli_fetch_assoc($declined_result)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($r['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($r['borrow_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($r['return_date'])); ?></td>
                                                <td><span class="status-pill pill-declined">Declined</span></td>
                                                <td class="text-muted" style="font-size:0.78rem;"><?php echo htmlspecialchars($r['reason'] ?? '—'); ?></td>
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
                <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            </svg>Equipment Registry</h2>
                        <p>Manage active equipment and archived items in one place.</p>
                    </div>
                    <button class="btn-add-item" data-action="show-add-form">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Add Item
                    </button>
                </div>

                <!-- Add / Edit Form (hidden by default) -->
                <div id="item-form-wrap" class="<?php echo $edit_item ? '' : 'hidden'; ?>" style="margin-bottom:1.5rem;">
                    <div class="eq-card form-card">
                        <div class="form-card-header">
                            <h2>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                </svg>
                                <span id="form-title"><?php echo $edit_item ? 'Edit Item' : 'Add New Item'; ?></span>
                            </h2>
                            <button type="button" class="btn-close-custom" data-action="hide-item-form">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                        <div class="form-card-body">
                            <form method="POST" enctype="multipart/form-data" id="itemForm">
                                <?php if ($edit_item): ?>
                                    <input type="hidden" name="item_id" value="<?php echo $edit_item['item_id']; ?>">
                                    <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($edit_item['image_path']); ?>">
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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32" style="color:var(--text-light)">
                                            <rect x="3" y="3" width="18" height="18" rx="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <polyline points="21 15 16 10 5 21" />
                                        </svg>
                                        <p>Click to upload, drag & drop, or paste an image</p>
                                        <input type="file" name="item_image" id="itemImageInput" accept="image/*" style="display:none;">
                                        <?php if ($edit_item && $edit_item['image_path'] !== 'uploads/default.png'): ?>
                                            <img src="<?php echo htmlspecialchars($edit_item['image_path']); ?>" class="drop-zone-preview" id="imagePreview" style="display:block;">
                                        <?php else: ?>
                                            <img id="imagePreview" class="drop-zone-preview" style="display:none;">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="removeImageBtn" class="<?php echo ($edit_item && $edit_item['image_path'] !== 'uploads/default.png') ? '' : 'hidden'; ?>"
                                        style="margin-top:6px;font-size:0.75rem;color:var(--danger);background:none;border:none;cursor:pointer;">✕ Remove image</button>
                                </div>

                                <button type="submit" name="<?php echo $edit_item ? 'update_item' : 'add_item'; ?>" class="btn-submit-form">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        </svg>
                        Active Equipment
                    </button>
                    <button class="history-toggle-btn" data-history-tab="reg-archived">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
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
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                                            <td colspan="6" class="text-muted" style="text-align:center;padding:3rem;">Inventory is empty.</td>
                                        </tr>
                                        <?php else: while ($item = mysqli_fetch_assoc($inventory_result)): ?>
                                            <tr>
                                                <td><img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="item-img" onerror="this.src='uploads/default.png'"></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td><span class="status-pill pill-info"><?php echo $item['quantity']; ?> units</span></td>
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
                                                        <a href="admin-dashboard.php?edit_item=<?php echo $item['item_id']; ?>" class="btn-action btn-edit-item" title="Edit">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                            </svg>
                                                        </a>
                                                        <a href="admin-dashboard.php?delete_item=<?php echo $item['item_id']; ?>" class="btn-action btn-delete-item" title="Archive" onclick="return confirm('Archive this item?')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
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
                                            <td colspan="4" class="text-muted" style="text-align:center;padding:2.5rem;">No archived items.</td>
                                        </tr>
                                        <?php else: while ($item = mysqli_fetch_assoc($archive_result)): ?>
                                            <tr>
                                                <td><img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="item-img" onerror="this.src='uploads/default.png'"></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td class="action-cell">
                                                    <div class="action-btns">
                                                        <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>" class="btn-action btn-restore" title="Restore to active">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                                                <polyline points="1 4 1 10 7 10" />
                                                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                            </svg>
                                                        </a>
                                                        <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>" class="btn-action btn-force-del" title="Delete permanently" onclick="return confirm('Permanently delete this item? This cannot be undone.')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
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
                    <h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
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
                                        <td colspan="8" class="text-muted" style="text-align:center;padding:2.5rem;">No records found.</td>
                                    </tr>
                                    <?php else: while ($r = mysqli_fetch_assoc($raw_data_result)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($r['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['instructor']); ?></td>
                                            <td><?php echo htmlspecialchars($r['room']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($r['borrow_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($r['return_date'])); ?></td>
                                            <td class="text-muted" style="font-size:0.78rem;"><?php echo date('M d, Y g:i A', strtotime($r['request_date'])); ?></td>
                                        </tr>
                                <?php endwhile;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /lending-raw -->

        </div><!-- /panel-lending -->


        <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
        <div class="tab-panel" id="panel-rooms">
            <div class="section-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="important-icon">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                        <line x1="9" y1="3" x2="9" y2="21" />
                        <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
                    </svg>
                    Room Management
                </h2>
                <p>Oversee room reservations and availability across the campus.</p>
            </div>

            <div class="coming-soon-banner">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="40" height="40" style="color:var(--accent-maroon);margin-bottom:0.5rem;">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                <h3>Room Reservation Management — Coming Soon</h3>
                <p>Admin controls for room scheduling, conflict detection, and approval workflows are under development.</p>
            </div>

            <!-- Room Preview Cards -->
            <div class="room-list">
                <!-- Room 1 -->
                <div class="room-card">
                    <div class="room-img">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                            <line x1="8" y1="21" x2="16" y2="21" />
                            <line x1="12" y1="17" x2="12" y2="21" />
                        </svg>
                    </div>
                    <div class="room-info">
                        <div class="room-header">
                            <div>
                                <h3>Computer Laboratory 301</h3>
                                <p>3rd Floor, Main Building</p>
                            </div>
                            <span class="capacity-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" style="margin-right:5px;vertical-align:middle;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                40 seats
                            </span>
                        </div>
                        <div class="amenities" style="margin-top:8px;">
                            <span>WiFi</span><span>A/C</span><span>Projector</span>
                        </div>
                        <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <span class="room-avail">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="7" height="7" style="vertical-align:middle;margin-right:5px;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg>
                                Available
                            </span>
                            <button class="room-btn" data-action="toast" data-msg="Room management coming soon!">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                Manage Schedule
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room 2 -->
                <div class="room-card">
                    <div class="room-img">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36">
                            <path d="M9 3h6" />
                            <path d="M10 3v7l-5 11h14L14 10V3" />
                        </svg>
                    </div>
                    <div class="room-info">
                        <div class="room-header">
                            <div>
                                <h3>Science Laboratory</h3>
                                <p>2nd Floor, Science Wing</p>
                            </div>
                            <span class="capacity-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" style="margin-right:5px;vertical-align:middle;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                30 seats
                            </span>
                        </div>
                        <div class="amenities" style="margin-top:8px;">
                            <span>A/C</span><span>Running Water</span><span>Safety Kit</span>
                        </div>
                        <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <span class="room-occupied">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="7" height="7" style="vertical-align:middle;margin-right:5px;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg>
                                Occupied until 3 PM
                            </span>
                            <button class="room-btn" data-action="toast" data-msg="Room management coming soon!">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                Manage Schedule
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room 3 -->
                <div class="room-card">
                    <div class="room-img">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36">
                            <rect x="2" y="3" width="20" height="14" rx="2" />
                            <line x1="8" y1="21" x2="16" y2="21" />
                            <line x1="12" y1="17" x2="12" y2="21" />
                            <path d="M9 10l2 2 4-4" />
                        </svg>
                    </div>
                    <div class="room-info">
                        <div class="room-header">
                            <div>
                                <h3>Lecture Hall A</h3>
                                <p>Ground Floor, Academic Building</p>
                            </div>
                            <span class="capacity-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" style="margin-right:5px;vertical-align:middle;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                80 seats
                            </span>
                        </div>
                        <div class="amenities" style="margin-top:8px;">
                            <span>WiFi</span><span>A/C</span><span>PA System</span><span>Projector</span>
                        </div>
                        <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <span class="room-avail">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8" width="7" height="7" style="vertical-align:middle;margin-right:5px;">
                                    <circle cx="4" cy="4" r="4" fill="currentColor" />
                                </svg>
                                Available
                            </span>
                            <button class="room-btn" data-action="toast" data-msg="Room management coming soon!">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                Manage Schedule
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /panel-rooms -->

    </main><!-- /app-main -->


    <!-- ================================================================
     OVERLAY: ACCOUNT
================================================================ -->
    <div class="overlay-page" id="accountOverlay">
        <div class="overlay-topbar">
            <button class="overlay-topbar-back" data-action="close-overlay" data-target="accountOverlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg>
                Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">My Account</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <circle cx="8" cy="12" r="2" />
                        <path d="M14 9h4" />
                        <path d="M14 12h4" />
                        <path d="M14 15h2" />
                    </svg>
                    Overview
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-security">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-img">
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
                        <div class="acc-avatar-large"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="acc-hero-info">
                            <h2><?php echo htmlspecialchars($admin_name); ?></h2>
                            <p>System Administrator</p>
                            <span class="acc-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" style="vertical-align:middle;margin-right:4px;">
                                    <circle cx="12" cy="12" r="7" fill="#22c55e" stroke="none" />
                                </svg>
                                Active Admin
                            </span>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">Edit Profile</button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;" data-action="profile-save">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Save
                            </button>
                            <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;" data-action="profile-cancel">Cancel</button>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Admin Information</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Display Name</span>
                                <span class="info-val <?php echo empty($admin_name) ? 'empty' : '';?>" 
                                    data-field="admin_name">
                                    <?php 
                                    echo htmlspecialchars($admin_name); 
                                    ?>
                                </span>
                            <input class="info-input-f" data-input="admin_name" value="<?php echo htmlspecialchars($admin_name); ?>" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Role</span>
                            <span class="info-val">Administrator</span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Email</span>
                                <span class="info-val 
                                    <?php echo empty($admin_email) ? 'empty' : ''; ?>" 
                                    data-field="admin_email">
                                    <?php 
                                        echo !empty($admin_email) ? htmlspecialchars($admin_email) : '— Not provided'; 
                                    ?>
                                </span>
                            <input class="info-input-f" data-input="admin_email" type="email" value="<?php echo htmlspecialchars($admin_email ?? ''); ?>" placeholder="admin@pup.edu.ph" disabled style="display:none;">
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
                            <span class="info-val"><?php echo date('M d, Y g:i A'); ?></span>
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
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg>
                Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">Notifications</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
                <span>PUPSYNC</span>
            </div>
        </div>

        <div class="notif-wrapper">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:10px;">
                <div class="overlay-section-header" style="flex:1;margin-bottom:0;">
                    <span class="section-eyebrow">Admin Inbox</span>
                    <h2>Notifications</h2>
                    <p>You have <strong style="color:var(--accent-maroon);" id="unreadCount"><?php echo $stat_waiting + $stat_overdue + 2; ?> unread</strong> notifications.</p>
                </div>
                <button class="mark-read-btn" data-action="mark-all-read" style="margin-top:0.5rem;">Mark all as read</button>
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                    <line x1="12" y1="9" x2="12" y2="13" />
                                    <line x1="12" y1="17" x2="12.01" y2="17" />
                                </svg>
                            </div>
                            <div class="notif-body-wrap">
                                <h4>Overdue: <?php echo htmlspecialchars($on['equipment_name']); ?></h4>
                                <p><strong><?php echo htmlspecialchars($on['student_name']); ?></strong> has not returned this item. <?php echo $days_late; ?> day<?php echo $days_late != 1 ? 's' : ''; ?> overdue.</p>
                            </div>
                            <div class="notif-meta">
                                <span class="notif-time">Due <?php echo date('M d', strtotime($on['return_date'])); ?></span>
                                <div class="unread-dot"></div>
                                <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                        </div>
                        <div class="notif-card-detail">
                            <div class="notif-detail-grid">
                                <div class="notif-detail-row"><span class="ndl">Student</span><span class="ndv"><?php echo htmlspecialchars($on['student_name']); ?> (<?php echo htmlspecialchars($on['student_id']); ?>)</span></div>
                                <div class="notif-detail-row"><span class="ndl">Equipment</span><span class="ndv"><?php echo htmlspecialchars($on['equipment_name']); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Due Date</span><span class="ndv" style="color:#e65100;font-weight:600;"><?php echo date('M d, Y', strtotime($on['return_date'])); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Days Overdue</span><span class="ndv" style="color:#e65100;font-weight:700;"><?php echo $days_late; ?> day<?php echo $days_late != 1 ? 's' : ''; ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Borrow Date</span><span class="ndv"><?php echo date('M d, Y', strtotime($on['borrow_date'])); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Room / Instructor</span><span class="ndv"><?php echo htmlspecialchars($on['room'] ?? '—'); ?> / <?php echo htmlspecialchars($on['instructor'] ?? '—'); ?></span></div>
                            </div>
                            <div class="notif-card-actions">
                                <a href="admin-dashboard.php?view=overdue" class="notif-action-btn notif-action-primary" data-action="close-overlay" data-target="notifOverlay">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                            </div>
                            <div class="notif-body-wrap">
                                <h4>New Borrow Request</h4>
                                <p><strong><?php echo htmlspecialchars($wn['student_name']); ?></strong> requested <strong><?php echo htmlspecialchars($wn['equipment_name']); ?></strong> — awaiting approval.</p>
                            </div>
                            <div class="notif-meta">
                                <span class="notif-time"><?php echo date('M d', strtotime($wn['request_date'])); ?></span>
                                <div class="unread-dot"></div>
                                <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                        </div>
                        <div class="notif-card-detail">
                            <div class="notif-detail-grid">
                                <div class="notif-detail-row"><span class="ndl">Student</span><span class="ndv"><?php echo htmlspecialchars($wn['student_name']); ?> (<?php echo htmlspecialchars($wn['student_id']); ?>)</span></div>
                                <div class="notif-detail-row"><span class="ndl">Equipment</span><span class="ndv"><?php echo htmlspecialchars($wn['equipment_name']); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Borrow Date</span><span class="ndv"><?php echo date('M d, Y', strtotime($wn['borrow_date'])); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Return Date</span><span class="ndv"><?php echo date('M d, Y', strtotime($wn['return_date'])); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Requested On</span><span class="ndv"><?php echo date('M d, Y g:i A', strtotime($wn['request_date'])); ?></span></div>
                                <div class="notif-detail-row"><span class="ndl">Room / Instructor</span><span class="ndv"><?php echo htmlspecialchars($wn['room'] ?? '—'); ?> / <?php echo htmlspecialchars($wn['instructor'] ?? '—'); ?></span></div>
                            </div>
                            <div class="notif-card-actions">
                                <a href="admin-dashboard.php?action=approve&id=<?php echo $wn['id']; ?>" class="notif-action-btn notif-action-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Approve
                                </a>
                                <a href="admin-dashboard.php?action=decline&id=<?php echo $wn['id']; ?>" class="notif-action-btn notif-action-danger" onclick="return confirm('Decline this request?')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                    Decline
                                </a>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                </div>
                <div class="notif-card-detail">
                    <div class="notif-detail-grid">
                        <div class="notif-detail-row"><span class="ndl">Window</span><span class="ndv">11:00 PM – 1:00 AM tonight</span></div>
                        <div class="notif-detail-row"><span class="ndl">Affected</span><span class="ndv">All PUPSYNC services (lending, inventory, login)</span></div>
                        <div class="notif-detail-row"><span class="ndl">Action Required</span><span class="ndv">Notify active users before 10:30 PM</span></div>
                    </div>
                    <div class="notif-card-actions">
                        <button class="notif-action-btn notif-action-dismiss" data-notif-dismiss>Got it</button>
                    </div>
                </div>
            </div>

            <div class="notif-item notif-card" data-cat="system">
                <div class="notif-card-main" role="button" tabindex="0">
                    <div class="notif-icon ni-success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <div class="notif-body-wrap">
                        <h4>Database Backup Completed</h4>
                        <p>Automatic daily backup of <code>lending_db</code> completed successfully.</p>
                    </div>
                    <div class="notif-meta">
                        <span class="notif-time">Yesterday, 2:00 AM</span>
                        <svg class="notif-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                </div>
                <div class="notif-card-detail">
                    <div class="notif-detail-grid">
                        <div class="notif-detail-row"><span class="ndl">Database</span><span class="ndv">lending_db</span></div>
                        <div class="notif-detail-row"><span class="ndl">Completed At</span><span class="ndv">Yesterday at 2:00 AM</span></div>
                        <div class="notif-detail-row"><span class="ndl">Status</span><span class="ndv"><span class="stock-badge stock-avail">Success</span></span></div>
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
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 5 5 12 12 19" />
                </svg>
                Back to Dashboard
            </button>
            <div class="overlay-topbar-sep"></div>
            <span class="overlay-topbar-title">Settings</span>
            <div class="overlay-topbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                        <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                        <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                        <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                    </svg>
                    Appearance
                </button>
                <button class="s-nav-item" data-sett-tab="st-accessibility">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    Notifications
                </button>
                <div class="s-divider"></div>
                <span class="s-cat-label">System</span>
                <button class="s-nav-item" data-sett-tab="st-advanced">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
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
                                <p>Makes cards and table rows more compact.</p>
                            </div>
                            <label class="toggle-sw"><input type="checkbox" id="compactToggle" data-action="apply-compact"><span class="toggle-track"></span></label>
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
                            <label class="toggle-sw"><input type="checkbox" id="reduceMotionToggle" data-action="apply-reduce-motion"><span class="toggle-track"></span></label>
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
                            <label class="toggle-sw"><input type="checkbox" id="focusRingToggle" data-action="apply-focus-ring"><span class="toggle-track"></span></label>
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
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Overdue Items</h4>
                                <p>Alert when a borrowed item passes its return date.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Low Stock Warning</h4>
                                <p>Alert when any item drops to 2 or fewer units.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
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
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Database Backup</h4>
                                <p>Daily backup completion status.</p>
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
                                <p>Display equipment item IDs in tables.</p>
                            </div><label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="s-row">
                            <div class="s-row-label">
                                <h4>Verbose Error Messages</h4>
                                <p>Show detailed database error information.</p>
                            </div><label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
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

    <!-- Toast -->
    <div id="app-toast"></div>


    <div class="modal-overlay" id="changePassModal" 
    style="display: none; 
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
    <div class="eq-card form-card" style="position: relative; width: 100%; max-width: 400px; margin: 20px; z-index: 100000;">
        <div class="form-card-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                Change Password
            </h2>
            <button type="button" class="btn-close-custom" data-action="close-change-pass">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="form-card-body">
            <div id="cp-alert" style="display:none; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 500;"></div>
            
            <form id="changePasswordForm">
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
                    <input type="password" name="confirm_password" class="form-control-custom" minlength="4" required>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-cancel-acc" data-action="close-change-pass" style="padding: 8px 16px; width: auto;">Cancel</button>
                    <button type="submit" class="btn-submit-form" style="margin-top: 0; width: auto; padding: 8px 16px;">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="js/admin-dashboard.js"></script>

</body>

</html>