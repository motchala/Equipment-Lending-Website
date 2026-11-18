<!DOCTYPE html>
<html lang="en" data-theme="light">

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
         While the screen is restored the page stays hidden (html.fac-tab-pending), so the Dashboard
         and its sidebar highlight never show in between. faculty-dashboard.js reveals the page. -->
<script src="dashboard/assets/js/faculty-tab-restore.js?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/js/faculty-tab-restore.js'); ?>"></script>

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