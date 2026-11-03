<?php
// Live-refresh request from admin-dashboard.js: return just the dashboard body and stop.
// (admin-functions.php above has already enforced the admin session.)
if (($_GET['fragment'] ?? '') === 'dashboard') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    dash_render(dash_collect($conn));
    exit;
}
