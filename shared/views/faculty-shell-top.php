<?php
/* First-paint bridge for a page refresh (same idea as the admin interface).
   F5 / the reload button sends "Cache-Control: max-age=0" (a hard refresh sends no-cache, a normal visit neither),
   and faculty-dashboard.js keeps the screen you are on in the cookie ps_fac_tab. When both say "refresh of screen X",
   the server prints screen X as the active one right in the HTML (see $fac_boot_tab in the panels and the sidebar),
   so the Dashboard is never painted, not even for one frame. It is only a guess: the head script below checks it
   and, if it disagrees, hides the page and switches screens before anything shows. */
$fac_boot_tab = 'home';
$fac_cache_ctl = strtolower((string)($_SERVER['HTTP_CACHE_CONTROL'] ?? ''));
if (strpos($fac_cache_ctl, 'max-age=0') !== false && strpos($fac_cache_ctl, 'no-cache') === false) {
    $fac_cookie_tab = (string)($_COOKIE['ps_fac_tab'] ?? '');
    if (in_array($fac_cookie_tab, ['activity', 'lending', 'rooms', 'settings'], true)) {
        $fac_boot_tab = $fac_cookie_tab;
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light"<?php
    // fac-restored: the screen appears in place, no slide-in (same class the reveal script adds)
    if ($fac_boot_tab !== 'home') echo ' class="fac-restored" data-fac-tab="' . $fac_boot_tab . '"';
?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#800000">
    <!-- Phone detection. Runs BEFORE first paint so a phone never flashes the responsive layout.
         Sets class "rx-mobile" on <html> when the device is a phone (phone-sized touch screen) or the window
         is <= 600px wide. Tablets/desktops do not get it and keep the responsive layout.
         External file on purpose (not inline): it judges the real screen size, not only window.innerWidth,
         which real phones can report wider than the screen. Add ?rxdebug=1 to the URL to see what it reads. -->
    <script src="dashboard/assets/js/faculty-phone-detect.js?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/js/faculty-phone-detect.js'); ?>"></script>
    <!-- Refresh behaviour (faculty interface). Same rules as the admin interface:
         - Soft refresh (F5 / reload button): stay on the screen you are on.
         - Hard refresh (Ctrl+Shift+R / Ctrl+F5): back to the Dashboard.
         The server normally paints the right screen already (see the top of this file). If it guessed wrong, the page
         stays hidden (html.fac-tab-pending) until faculty-shell-bottom.php has switched screens, so the Dashboard
         and its sidebar highlight never show in between. -->
    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            var TABS = ['activity', 'lending', 'rooms', 'settings'];
            var root = document.documentElement;
            // the screen the server painted ('home' when it did not guess)
            var served = root.getAttribute('data-fac-tab') || 'home';

            var nav = (window.performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation')[0] : null;
            var isReload = nav ? nav.type === 'reload' : !!(performance.navigation && performance.navigation.type === 1);

            // Remember a hard-refresh shortcut so the page that loads next knows about it.
            window.addEventListener('keydown', function(e) {
                var k = (e.key || '').toLowerCase();
                if (((e.ctrlKey || e.metaKey) && ((e.shiftKey && k === 'r') || k === 'f5')) || (e.shiftKey && k === 'f5')) {
                    try { sessionStorage.setItem('facultyHardRefresh', String(Date.now())); } catch (_) {}
                }
            }, true);

            var hard = false;
            try {
                var stamp = parseInt(sessionStorage.getItem('facultyHardRefresh'), 10);
                hard = isReload && stamp > 0 && (Date.now() - stamp) < 15000;
                sessionStorage.removeItem('facultyHardRefresh');
            } catch (_) {}

            // The screen you were on is kept in sessionStorage as a backup for the #tab- part of the URL.
            // Only a refresh may use it; opening the page any other way starts on the Dashboard.
            var stored = null;
            try {
                stored = sessionStorage.getItem('facultyCurrentTab');
                if (!isReload || hard) sessionStorage.removeItem('facultyCurrentTab');
            } catch (_) {}

            var tab = null;
            if (isReload && !hard) {
                var hash = window.location.hash;
                var hashTab = null;
                if (hash.indexOf('#tab-') === 0) hashTab = hash.slice(5).split('-')[0];
                else if (hash.indexOf('#overlay-settingsOverlay') === 0) hashTab = 'settings';
                tab = hashTab || stored;
            }
            if (TABS.indexOf(tab) === -1) tab = 'home';   // nothing to restore: the Dashboard is right

            // Tell the server which screen this page starts on, for the next refresh.
            try {
                document.cookie = 'ps_fac_tab=' + tab + '; path=/; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
            } catch (_) {}

            window._facRestoreTab = tab;   // 'home' = nothing to restore; faculty-dashboard.js finishes the job
            if (tab === served) return;    // the server already painted this very screen: nothing to hide

            // The server guessed wrong (or did not guess): hide the page until the right screen is in place.
            root.classList.add('fac-tab-pending');
            // Safety net: never leave the page hidden if the reveal in faculty-shell-bottom.php fails to run.
            // It waits for the page to finish loading (a slow connection can take longer than any fixed
            // timeout, and lifting early would flash the Dashboard), then lifts after a short grace.
            var lift = function() { root.classList.remove('fac-tab-pending'); };
            window.addEventListener('load', function() { setTimeout(lift, 1500); });
            setTimeout(lift, 20000);
        })();
    </script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>PUPSync | Faculty Dashboard</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
    <link rel="icon" href="shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="shared/assets/images/apple-touch-icon.png">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="dashboard/assets/css/faculty-dashboard.css?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/css/faculty-dashboard.css'); ?>">
    <link rel="stylesheet" href="equipment-booking/assets/css/faculty-borrow-flow.css?v=<?php echo @filemtime(PS_ROOT . '/equipment-booking/assets/css/faculty-borrow-flow.css'); ?>">
    <link rel="stylesheet" href="equipment-booking/assets/css/faculty-add-button.css?v=<?php echo @filemtime(PS_ROOT . '/equipment-booking/assets/css/faculty-add-button.css'); ?>">

    <!-- NOTE: faculty-code-card.css intentionally not loaded — it was a stale
         snapshot of this same stylesheet (predating several redesigns) that
         was silently overriding current styles because it loaded last. -->

    <!-- Dashboard Redesign v3 — Google Font -->

    <!-- facilities tab portal -->
    <link rel="stylesheet" href="room-reservation/assets/css/fcty-facilities.css?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/css/fcty-facilities.css'); ?>">
    <!-- same-day room request: review-buffer countdown chip -->
    <link rel="stylesheet" href="room-reservation/assets/css/room-buffer.css?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/css/room-buffer.css'); ?>">
    <!-- same-day equipment request: approval-buffer countdown chip -->
    <link rel="stylesheet" href="equipment-booking/assets/css/equipment-buffer.css?v=<?php echo @filemtime(PS_ROOT . '/equipment-booking/assets/css/equipment-buffer.css'); ?>">

    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="shared/assets/css/logout-modal.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/logout-modal.css'); ?>">
    <link rel="stylesheet" href="shared/assets/css/semester-tag.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/semester-tag.css'); ?>">