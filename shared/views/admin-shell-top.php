<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Sync | Admin Portal</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
    <link rel="icon" href="shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="shared/assets/images/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">
    <link rel="stylesheet" href="dashboard/assets/css/admin-dashboard.css?v=<?php echo filemtime('dashboard/assets/css/admin-dashboard.css'); ?>">
    <!-- Mobile/tablet adaptations; must load after admin-dashboard.css -->
    <link rel="stylesheet" href="dashboard/assets/css/admin-dashboard-responsive.css?v=<?php echo @filemtime('dashboard/assets/css/admin-dashboard-responsive.css'); ?>">
    <link rel="stylesheet" href="settings/assets/css/admin-imports.css?v=<?php echo @filemtime('settings/assets/css/admin-imports.css'); ?>">
    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="shared/assets/css/logout-modal.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/logout-modal.css'); ?>">
    <?php role_splash_head(); ?>
    <script>
        /* Keep the default dashboard from appearing while the restored tab loads. */
        (function() {
            var params = new URLSearchParams(window.location.search);
            var tab = null;

            if (params.has('edit_item')) {
                tab = 'inventory';
            } else {
                var view = params.get('view');
                if (['inventory', 'faculty', 'activity', 'admins'].indexOf(view) !== -1) {
                    tab = view;
                } else if (params.get('tab') === 'rooms') {
                    tab = 'rooms';
                } else {
                    var hash = window.location.hash;
                    if (hash.indexOf('#tab-') === 0) tab = hash.slice(5);
                }
            }

            if (['rooms', 'faculty', 'admins', 'activity', 'inventory', 'settings'].indexOf(tab) === -1) return;
            try {
                if (sessionStorage.getItem('adminHardRefresh') === '1') return;
            } catch (_) {}
            window._preJumpTab = tab;
            document.documentElement.classList.add('admin-tab-pending');
        })();
    </script>
    <link rel="stylesheet" href="shared/assets/css/admin-shell.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/admin-shell.css'); ?>">
</head>

<body>

    <?php render_role_splash(($_SESSION['admin_role'] ?? 'Admin') === 'Super Admin' ? 'Super Admin' : 'Admin'); ?>

    <!-- ================================================================
     MOBILE MENU BUTTON
     The top bar is gone — the sidebar is the only app chrome. On phones the
     sidebar is an off-canvas drawer, and this floating button opens it.
     (Hidden on tablet/desktop; see .sidebar-toggle-btn in the CSS.)
================================================================ -->
    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Open menu"
        aria-expanded="false" aria-controls="adminSidebar">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <!-- ================================================================
     APP BODY
================================================================ -->
    <div class="app-body">

        <!-- Backdrop behind the off-canvas sidebar on mobile — tapping it closes the menu -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- ================================================================
     SIDEBAR
================================================================ -->
        <nav class="sidebar" id="adminSidebar">

            <!-- Brand row: [logo] [PUPSYNC / role portal] ........ [collapse toggle].
                 On phones the same toggle closes the drawer (see admin-dashboard.js). -->
            <div class="sidebar-brand">
                <div class="sidebar-logo" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"
                        aria-hidden="true">
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                </div>
                <div class="sidebar-brand-text">
                    <span class="sidebar-title"><strong>PUP</strong><span class="snt-light">SYNC</span></span>
                    <span class="sidebar-sub"><?php echo htmlspecialchars((string)($_SESSION['admin_role'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?> Portal</span>
                </div>
                <button type="button" class="sidebar-toggle" id="sidebarCollapseBtn"
                    title="Collapse sidebar" aria-label="Collapse sidebar" aria-expanded="true"
                    aria-controls="adminSidebar">
                    <svg class="sn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="4" width="18" height="16" rx="3.5" />
                        <path d="M9.5 4v16" />
                        <rect class="sn-fill" x="4.6" y="5.6" width="3.3" height="12.8" rx="1.4"
                            fill="currentColor" stroke="none" />
                    </svg>
                </button>
            </div>

            <!-- Tabs are grouped by purpose: Overview (what's happening) · Operations (day-to-day work) ·
                 Accounts (Super Admin only). Each group title shares a row with its thin separator line. -->
            <div class="sidebar-links">
                <div class="nav-group" role="group" aria-labelledby="navg-overview">
                    <span class="nav-group-title" id="navg-overview">Overview</span>
                    <a class="nav-item active" data-tab="dashboard" href="#">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span class="nav-label">Dashboard</span>
                    </a>
                    <a class="nav-item" data-tab="activity" id="snav-activity" href="#">
                        <span class="material-symbols-outlined">history</span>
                        <span class="nav-label">Activity</span>
                    </a>
                </div>
                <div class="nav-group" role="group" aria-labelledby="navg-operations">
                    <span class="nav-group-title" id="navg-operations">Operations</span>
                    <a class="nav-item" data-tab="inventory" id="snav-inventory" href="#">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <span class="nav-label">Equipment</span>
                    </a>
                    <a class="nav-item" data-tab="rooms" href="#">
                        <span class="material-symbols-outlined">meeting_room</span>
                        <span class="nav-label">Facilities</span>
                    </a>
                </div>
                <?php if ($is_super_admin): ?>
                    <div class="nav-group" role="group" aria-labelledby="navg-accounts">
                        <span class="nav-group-title" id="navg-accounts">Accounts</span>
                        <a class="nav-item" data-tab="faculty" href="#">
                            <span class="material-symbols-outlined">group</span>
                            <span class="nav-label">Faculty</span>
                        </a>
                        <a class="nav-item" data-tab="admins" id="snav-admins" href="#">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                            <span class="nav-label">Admins</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="sidebar-footer">
                <!-- Signed-in admin: the name only. Not a button, no avatar. Notifications and Settings
                     live in the Dashboard's top-right bell / avatar; Scan Return is a Quick Action card
                     on the Dashboard. (.nav-account-name stays on the span so the live name update in
                     admin-dashboard.js keeps working.) -->
                <div class="nav-user" id="navAccountGroup" hidden aria-hidden="true" style="display:none;" title="<?php echo htmlspecialchars($admin_role); ?>">
                    <span class="nav-label nav-account-name"><?php echo htmlspecialchars($admin_name); ?></span>
                </div>
                <!-- Settings: just under the divider line, directly above Log Out. data-tab="settings" is picked
                     up by the generic .nav-item[data-tab] handler in admin-dashboard.js (same tab the Dashboard
                     avatar opens). -->
                <a class="nav-item" data-tab="settings" id="snav-settings" href="#">
                    <span class="material-symbols-outlined">settings</span>
                    <span class="nav-label">Settings</span>
                </a>
                <!-- Log Out — pinned to the very bottom of the sidebar -->
                <a class="nav-item nav-signout" id="snav-logout" data-action="logout" href="#">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="nav-label">Log Out</span>
                </a>
            </div>

        </nav><!-- /sidebar -->

        <!-- ================================================================
     MAIN
================================================================ -->
