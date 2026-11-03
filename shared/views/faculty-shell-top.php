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
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="dashboard/assets/css/faculty-dashboard.css?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/css/faculty-dashboard.css'); ?>">

    <!-- NOTE: faculty-code-card.css intentionally not loaded — it was a stale
         snapshot of this same stylesheet (predating several redesigns) that
         was silently overriding current styles because it loaded last. -->

    <!-- Dashboard Redesign v3 — Google Font -->

    <!-- facilities tab portal -->
    <link rel="stylesheet" href="room-reservation/assets/css/fcty-facilities.css?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/css/fcty-facilities.css'); ?>">

    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="shared/assets/css/logout-modal.css?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/css/logout-modal.css'); ?>">