<?php
/* First-paint bridge for a page refresh.
   Hiding the page until a script has restored your screen depends on that script running early, and on a slower host
   the Dashboard can show first. So the server says which screen to paint, in the HTML itself:
   - F5 / the reload button sends "Cache-Control: max-age=0" (a hard refresh sends no-cache, a normal visit neither);
   - admin-dashboard.js keeps your last screen and open sub-tab in cookies (ps_admin_tab, ps_sub_rooms, ps_sub_settings).
   When both say "refresh of screen X", <html> gets data-ps-tab="X" (and data-ps-sub), and admin-shell.css paints that screen
   from the very first frame, no script involved. It is only a guess: the head script below drops it at once if it
   disagrees, and admin-dashboard.js drops it when its own start-up is done. */
$ps_bridge_tab = '';
$ps_bridge_sub = '';
$ps_cache_ctl  = strtolower((string)($_SERVER['HTTP_CACHE_CONTROL'] ?? ''));
if (
    strpos($ps_cache_ctl, 'max-age=0') !== false && strpos($ps_cache_ctl, 'no-cache') === false
    && !isset($_GET['edit_item']) && !isset($_GET['edit_room']) && !isset($_GET['view']) && !isset($_GET['tab'])
) {
    $ps_cookie_tab = (string)($_COOKIE['ps_admin_tab'] ?? '');
    if (in_array($ps_cookie_tab, ['rooms', 'faculty', 'admins', 'activity', 'inventory', 'settings', 'imports'], true)) {
        $ps_bridge_tab = $ps_cookie_tab;
        if ($ps_cookie_tab === 'rooms') {
            $ps_cookie_sub = (string)($_COOKIE['ps_sub_rooms'] ?? '');
            if (in_array($ps_cookie_sub, ['rooms-active', 'rooms-reservations', 'rooms-archived'], true)) {
                $ps_bridge_sub = $ps_cookie_sub;
            }
        } elseif ($ps_cookie_tab === 'settings') {
            $ps_cookie_sub = (string)($_COOKIE['ps_sub_settings'] ?? '');
            if (in_array($ps_cookie_sub, ['sett-account', 'sett-prefs', 'sett-arbitration', 'sett-data', 'sett-ratings', 'sett-help'], true)) {
                $ps_bridge_sub = $ps_cookie_sub;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" <?php
                                    if ($ps_bridge_tab !== '') {
                                        // ps-restored: the screen appears in place, no slide-in (same class the reveal script adds)
                                        echo ' class="ps-restored" data-ps-tab="' . $ps_bridge_tab . '"' . ($ps_bridge_sub !== '' ? ' data-ps-sub="' . $ps_bridge_sub . '"' : '');
                                    }
                                    ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#600302">
    <!-- Phone detection (same rule as the Faculty portal). Runs BEFORE first paint so a phone never flashes the desktop
         layout. Sets class "rx-mobile" on <html> when the device is a phone (also in landscape), or the window is
         600px wide or less. Tablets and desktops do not get it and keep the layout they have today.
         Everything phone-specific lives in admin-dashboard-mobile.css / .js and is scoped to html.rx-mobile. -->
    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            var d = document.documentElement;

            function phoneUA() {
                var u = navigator.userAgentData;
                if (u && typeof u.mobile === "boolean") return u.mobile;
                return /iPhone|iPod|Android.+Mobile|Windows Phone|IEMobile|BlackBerry|Opera Mini|Mobile.+Firefox/i.test(navigator.userAgent || "");
            }

            function apply() {
                var narrow = (window.innerWidth || 0) <= 600;
                var shortSide = Math.min(screen.width || 9999, screen.height || 9999);
                var phone = phoneUA() && shortSide <= 540; // phones stay in phone mode in landscape too
                d.classList.toggle("rx-mobile", narrow || phone);
            }
            apply();
            window.addEventListener("resize", apply);
            window.addEventListener("orientationchange", apply);
        })();
    </script>
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
    <link rel="stylesheet" href="settings/assets/css/admin-arbitration.css?v=<?php echo @filemtime('settings/assets/css/admin-arbitration.css'); ?>">
    <link rel="stylesheet" href="settings/assets/css/admin-clear-history.css?v=<?php echo @filemtime('settings/assets/css/admin-clear-history.css'); ?>">
    <link rel="stylesheet" href="reports/assets/css/reports.css?v=<?php echo @filemtime(PS_ROOT . '/reports/assets/css/reports.css'); ?>">
    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="shared/assets/css/push-permission-modal.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/push-permission-modal.css'); ?>">
    <link rel="stylesheet" href="shared/assets/css/logout-modal.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/logout-modal.css'); ?>">
    <link rel="stylesheet" href="shared/assets/css/semester-tag.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/semester-tag.css'); ?>">
    <?php role_splash_head(); ?>
    <script nonce="<?php echo $csp_nonce; ?>">
        /* Refresh behaviour (admin interface)
           - Soft refresh (F5 / reload button): stay on the screen you are on; an open edit form is dropped.
           - Hard refresh (Ctrl+Shift+R / Ctrl+F5): back to the Dashboard (first screen).
           Also keeps the default dashboard from appearing while the restored tab loads. */
        (function() {
            var TABS = ['rooms', 'faculty', 'admins', 'activity', 'inventory', 'settings', 'imports', 'reports'];
            var loc = window.location;

            var nav = (window.performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation')[0] : null;
            var isReload = nav ? nav.type === 'reload' : !!(performance.navigation && performance.navigation.type === 1);
            window._adminIsReload = isReload;

            // First-paint bridge (see the top of this file): the server may already have painted the screen it expects.
            // Drop it as soon as this script decides otherwise; admin-dashboard.js drops it once its start-up is done.
            var root = document.documentElement;
            var bridged = root.getAttribute('data-ps-tab');
            var dropBridge = function() {
                root.removeAttribute('data-ps-tab');
                root.removeAttribute('data-ps-sub');
            };

            // Remember a hard-refresh shortcut so the page that loads next knows about it.
            window.addEventListener('keydown', function(e) {
                var k = (e.key || '').toLowerCase();
                if (((e.ctrlKey || e.metaKey) && ((e.shiftKey && k === 'r') || k === 'f5')) || (e.shiftKey && k === 'f5')) {
                    try {
                        sessionStorage.setItem('adminHardRefresh', String(Date.now()));
                    } catch (_) {}
                }
            }, true);

            var hard = false;
            try {
                var stamp = parseInt(sessionStorage.getItem('adminHardRefresh'), 10);
                hard = isReload && stamp > 0 && (Date.now() - stamp) < 15000;
                sessionStorage.removeItem('adminHardRefresh');
            } catch (_) {}

            // The screen you were on is also kept in sessionStorage, as a backup for the #tab- part of the URL.
            // Only a refresh may use it; opening the page any other way starts on the Dashboard.
            var stored = null;
            try {
                stored = sessionStorage.getItem('adminCurrentTab');
                if (!isReload || hard) sessionStorage.removeItem('adminCurrentTab');
            } catch (_) {}
            window._adminStoredTab = (isReload && !hard) ? stored : null;

            // Hard refresh: forget the screen and any leftover URL state, land on the Dashboard.
            if (hard) {
                dropBridge();
                try {
                    sessionStorage.removeItem('adminSubView');
                } catch (_) {}
                try {
                    ['ps_admin_tab', 'ps_sub_rooms', 'ps_sub_settings'].forEach(function(c) {
                        document.cookie = c + '=; path=/; Max-Age=0';
                    });
                } catch (_) {}
                if (loc.search || loc.hash) {
                    window._adminLeaving = true;
                    loc.replace(loc.pathname);
                }
                return;
            }

            var params = new URLSearchParams(loc.search);
            var hash = loc.hash;
            var hashTab = hash.indexOf('#tab-') === 0 ? hash.slice(5) : null;

            // The screen to come back to after a soft refresh: the #tab- in the URL, else the remembered one.
            var restore = isReload ? (hashTab || window._adminStoredTab) : null;

            // Soft refresh while an edit form is open: reload once more without it (same screen, form closed).
            if (isReload && (params.has('edit_item') || params.has('edit_room'))) {
                var wasEquipment = params.has('edit_item');
                params.delete('edit_item');
                params.delete('edit_room');
                if (restore) {
                    params.delete('view');
                    params.delete('tab');
                    if (!hashTab) {
                        hash = '#tab-' + restore;
                    }
                } else if (wasEquipment) {
                    hash = '#tab-inventory';
                }
                var qs = params.toString();
                window._adminLeaving = true;
                // Keep the Dashboard (and its sidebar highlight) from showing while the page reloads once more.
                dropBridge();
                document.documentElement.classList.add('admin-tab-pending');
                setTimeout(function() {
                    document.documentElement.classList.remove('admin-tab-pending');
                }, 5000);
                loc.replace(loc.pathname + (qs ? '?' + qs : '') + hash);
                return;
            }

            var tab = null;
            if (restore) {
                tab = restore; // the screen you were on beats leftover URL params
            } else if (params.has('edit_item')) {
                tab = 'inventory';
            } else {
                var view = params.get('view');
                if (['inventory', 'faculty', 'activity', 'admins'].indexOf(view) !== -1) {
                    tab = view;
                } else if (params.get('tab') === 'rooms') {
                    tab = 'rooms';
                } else {
                    tab = hashTab;
                }
            }

            // Phone view: Faculty, Admins, Imports and Reports are desktop-only (see admin-dashboard-mobile.js),
            // so a refresh, an old link or a leftover cookie must not land there. Also forget a desktop-only Settings
            // sub-tab (Arbitration / Data / Service Ratings) so the First-paint bridge does not paint it.
            if (root.classList.contains('rx-mobile')) {
                if (['faculty', 'admins', 'imports', 'reports'].indexOf(tab) !== -1) {
                    tab = null;
                    window._adminStoredTab = null;
                    try { sessionStorage.removeItem('adminCurrentTab'); } catch (_) {}
                    if (hashTab) { try { history.replaceState(history.state, '', loc.pathname + loc.search); } catch (_) {} }
                }
                var bridgedSub = root.getAttribute('data-ps-sub');
                if (bridgedSub === 'sett-arbitration' || bridgedSub === 'sett-data' || bridgedSub === 'sett-ratings') {
                    root.removeAttribute('data-ps-sub');
                }
                try {
                    var sv = JSON.parse(sessionStorage.getItem('adminSubView') || '{}');
                    if (sv.settings === 'sett-arbitration' || sv.settings === 'sett-data' || sv.settings === 'sett-ratings') {
                        delete sv.settings;
                        sessionStorage.setItem('adminSubView', JSON.stringify(sv));
                    }
                } catch (_) {}
            }

            if (TABS.indexOf(tab) === -1) {
                // Nothing to restore, so the Dashboard is right: drop a wrong guess by the server and forget the stale screen.
                dropBridge();
                try {
                    document.cookie = 'ps_admin_tab=dashboard; path=/; SameSite=Lax';
                } catch (_) {}
                return;
            }
            window._preJumpTab = tab;
            if (bridged === tab) {
                // The server already painted this very screen, so there is nothing to hide: the page shows it from the
                // first frame. The reveal script at the end of the page still puts the real classes in place.
                return;
            }
            dropBridge();
            document.documentElement.classList.add('admin-tab-pending');
            // Safety net: never leave the page hidden if the reveal script below fails to run.
            // It waits for the page to finish loading (a slow connection can take longer than any fixed
            // timeout, and lifting early would flash the Dashboard), then lifts after a short grace.
            var lift = function() {
                document.documentElement.classList.remove('admin-tab-pending');
            };
            window.addEventListener('load', function() {
                setTimeout(lift, 1500);
            });
            setTimeout(lift, 20000);
        })();
    </script>
    <link rel="stylesheet" href="shared/assets/css/admin-shell.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/admin-shell.css'); ?>">
    <!-- Phone app layout (only active when <html class="rx-mobile">). Loaded LAST on purpose. -->
    <link rel="stylesheet" href="dashboard/assets/css/admin-dashboard-mobile.css?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/css/admin-dashboard-mobile.css'); ?>">
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
                 Accounts (Super Admin only) · Data (Imports for Super Admin only, Reports for every admin).
                 Each group title shares a row with its thin separator line. -->
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
                <!-- Data: Imports (Super Admin only) sits next to Reports (Admin and Super Admin) -->
                <div class="nav-group" role="group" aria-labelledby="navg-data">
                    <span class="nav-group-title" id="navg-data">Data</span>
                    <?php if ($is_super_admin): ?>
                        <a class="nav-item" data-tab="imports" id="snav-imports" href="#">
                            <span class="material-symbols-outlined">upload_file</span>
                            <span class="nav-label">Imports</span>
                        </a>
                    <?php endif; ?>
                    <a class="nav-item" data-tab="reports" id="snav-reports" href="#">
                        <span class="material-symbols-outlined">summarize</span>
                        <span class="nav-label">Reports</span>
                    </a>
                </div>
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