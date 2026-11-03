<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// csp vulnerability fix: generate a nonce for inline scripts/styles (future-proofing,
// matches the pattern used on landing-page.php / faculty-dashboard.php / student-dashboard.php)
// and send the Content-Security-Policy header. admin-dashboard.php currently has no inline
// <script> or <style> blocks (only style="" attributes), so 'unsafe-inline' is included for
// style-src to avoid breaking the 150+ inline style attributes already in use.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

// admin-dashboard-functions.php
require_once __DIR__ . '/../../config/session.php';
// Ensure server uses local timezone for displaying login timestamps
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    // Relative to admin-dashboard.php (same folder as landing-page.php). The old
    // "../../landing-page.php" climbed out of the project folder and landed on
    // whatever page sat above it (e.g. an old landing page in the web root).
    header("Location: landing-page.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../activity/core/admin-data.php'; // also loads admin-activity.php
$conn = getDB();

// Root URL: SCRIPT_NAME for admin-dashboard.php is now /Equipment-Lending-Website/admin-dashboard.php
// dirname() once gives /Equipment-Lending-Website — the project root.
// Used so upload image paths (stored as 'uploads/filename.jpg' in DB) resolve correctly.
$root_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
require_once __DIR__ . '/../../config/csrf.php';
