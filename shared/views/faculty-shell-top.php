<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#800000">
    <!-- Phone detection. Runs BEFORE first paint so a phone never flashes the desktop layout.
         Sets class "rx-mobile" on <html> when the device is a phone (or the window is <= 600px wide).
         Tablets/desktops do not get it and keep the responsive layout. -->
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
    <!-- Refresh behaviour (faculty interface). Same rules as the admin interface:
         - Soft refresh (F5 / reload button): stay on the screen you are on.
         - Hard refresh (Ctrl+Shift+R / Ctrl+F5): back to the Dashboard.
         While the screen is restored the page stays hidden (html.fac-tab-pending), so the Dashboard
         and its sidebar highlight never show in between. faculty-dashboard.js reveals the page. -->
    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            var TABS = ['activity', 'lending', 'rooms'];
            var root = document.documentElement;

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
            if (hard) return;

            var hash = window.location.hash;
            var hashTab = hash.indexOf('#tab-') === 0 ? hash.slice(5).split('-')[0] : null;
            var tab = isReload ? (hashTab || stored) : null;
            if (TABS.indexOf(tab) === -1) return;

            window._preJumpTab = tab;
            root.classList.add('fac-tab-pending');
            // Safety net: never leave the page hidden if the reveal in faculty-dashboard.js fails to run.
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

    <!-- NOTE: faculty-code-card.css intentionally not loaded — it was a stale
         snapshot of this same stylesheet (predating several redesigns) that
         was silently overriding current styles because it loaded last. -->

    <!-- Dashboard Redesign v3 — Google Font -->

    <!-- facilities tab portal -->
    <link rel="stylesheet" href="room-reservation/assets/css/fcty-facilities.css?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/css/fcty-facilities.css'); ?>">

    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="shared/assets/css/logout-modal.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/logout-modal.css'); ?>">
    <link rel="stylesheet" href="shared/assets/css/semester-tag.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/semester-tag.css'); ?>">