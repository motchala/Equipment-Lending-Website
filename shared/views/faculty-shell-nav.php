<!-- Responsive System. Loaded LAST on purpose: the inline <style> block above uses !important
         and sets the desktop layout, so these mobile/tablet overrides only win if they come after it. -->
<link rel="stylesheet" href="dashboard/assets/css/faculty-dashboard-responsive.css?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/css/faculty-dashboard-responsive.css'); ?>">
<!-- Phone app layout (only active when <html class="rx-mobile">) -->
<link rel="stylesheet" href="dashboard/assets/css/faculty-dashboard-mobile.css?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/css/faculty-dashboard-mobile.css'); ?>">
<?php role_splash_head(); ?>
</head>

<body>

    <?php render_role_splash('Faculty'); ?>

    <!-- ================================================================
     SIDE NAVIGATION
================================================================ -->
    <nav class="side-nav" id="sideNav">
        <div class="side-nav-brand">
            <!-- Logo is a plain mark now (no longer the collapse button) -->
            <div class="side-nav-logo" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"
                    aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div class="side-nav-brand-text">
                <span class="side-nav-title"><strong>PUP</strong><span class="snt-light">SYNC</span></span>
                <span class="side-nav-sub">Faculty Portal</span>
            </div>
            <!-- Sidebar toggle: desktop = collapse / expand, phones = close the drawer -->
            <button type="button" class="side-nav-toggle" id="sidebarCollapseBtn"
                title="Collapse sidebar" aria-label="Collapse sidebar" aria-expanded="true"
                aria-controls="sideNav">
                <svg class="sn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <rect x="3" y="4" width="18" height="16" rx="3.5" />
                    <path d="M9.5 4v16" />
                    <rect class="sn-fill" x="4.6" y="5.6" width="3.3" height="12.8" rx="1.4" fill="currentColor" stroke="none" />
                </svg>
            </button>
        </div>

        <div class="side-nav-links">
            <div class="side-nav-section" role="group" aria-labelledby="fnavg-overview">
                <span class="side-nav-section-title" id="fnavg-overview">Overview</span>
                <a class="side-nav-item active" id="nav-home" data-tab="home" href="#">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a class="side-nav-item" id="nav-activity" data-tab="activity" href="#">
                    <span class="material-symbols-outlined">history</span>
                    <span>My Activity</span>
                </a>
            </div>
            <div class="side-nav-section" role="group" aria-labelledby="fnavg-operations">
                <span class="side-nav-section-title" id="fnavg-operations">Operations</span>
                <a class="side-nav-item" id="nav-lending" data-tab="lending" href="#">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <span>Equipment</span>
                </a>
                <a class="side-nav-item" id="nav-rooms" data-tab="rooms" href="#">
                    <span class="material-symbols-outlined">meeting_room</span>
                    <span>Facilities</span>
                </a>
            </div>
        </div>

        <div class="side-nav-footer">
            <!-- Signed-in faculty: the name only. Not a button, no avatar. Notifications and Settings
                 live in the Dashboard's top-right bell / avatar. (.side-nav-user-name stays on the span:
                 faculty-dashboard.js reads and updates it.) -->
            <div class="side-nav-user" id="navAccountGroup" hidden aria-hidden="true" style="display:none;">
                <span class="side-nav-user-name"><?php echo htmlspecialchars($fullname); ?></span>
            </div>
            <!-- Settings: just under the divider line, directly above Log Out (opens the Settings overlay,
                 same as the Dashboard avatar). #nav-settings is what _openOverlayDOM() highlights. -->
            <a class="side-nav-item" id="nav-settings" href="#" data-action="open-overlay" data-target="settingsOverlay">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            <!-- Log Out — pinned to the very bottom of the sidebar -->
            <a class="side-nav-item side-nav-signout" id="nav-signout" href="#" data-action="logout">
                <span class="material-symbols-outlined">logout</span>
                <span>Log Out</span>
            </a>
        </div>
    </nav>

    <!-- ================================================================
     MAIN WRAPPER (right of sidebar)
================================================================ -->
    <div class="main-wrapper">

        <!-- Top bar removed. Only the mobile menu button remains (shown at <=768px)
             so the sidebar drawer can still be opened on phones. -->
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation">
            <svg class="sn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <rect x="3" y="4" width="18" height="16" rx="3.5" />
                <path d="M9.5 4v16" />
                <rect class="sn-fill" x="4.6" y="5.6" width="3.3" height="12.8" rx="1.4" fill="currentColor" stroke="none" />
            </svg>
        </button>

        <?php
        /* ── GLOBAL SEARCH — DORMANT ──────────────────────────────────────
           Switched off on purpose for now and not rendered. Markup kept so
           it can be restored: put it back in a container and set
           GLOBAL_SEARCH_ENABLED = true in faculty-dashboard.js.

           <div class="top-bar-global-search" id="globalSearchWrap">
               <button type="button" class="top-bar-global-search-toggle" id="globalSearchToggle"
                   aria-label="Open dashboard search" aria-expanded="false">
                   <span class="material-symbols-outlined" aria-hidden="true">search</span>
               </button>
               <input type="search" id="globalSearch" placeholder="Search dashboard" autocomplete="off"
                   aria-label="Search dashboard">
           </div>
        ─────────────────────────────────────────────────────────────────── */
        ?>

        <!-- ================================================================
     MAIN CANVAS
================================================================ -->