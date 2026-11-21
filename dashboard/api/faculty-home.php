<?php

/**
 * dashboard/api/faculty-home.php
 * Live refresh for the faculty Home tab (key figures, activity chart, "Needs your attention",
 * today's schedule, recent activity).
 *
 * It does not duplicate any of that logic: it loads the same data the page loads on first paint
 * and renders the very same view (dashboard/views/faculty-screen.php), so what you see after a
 * refresh is exactly what a full page reload would show. Read-only: nothing is written except
 * the housekeeping the page itself already runs on every load.
 *
 * Response: { ok: true, html: "<panel markup>", notif_count: n }
 * Depth: 2  ->  config prefix: __DIR__ . '/../../config/...'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// Same housekeeping the page and the My Activity refresh run (auto-decline, overdue, no-shows)
require_once __DIR__ . '/../../activity/core/faculty-data.php';   // also loads BookingSchedule, notifications, quotas
BookingSchedule::ensureSchema($conn);
fact_eq_housekeeping($conn);

$uid_safe = mysqli_real_escape_string($conn, (string)$_SESSION['faculty_id']);

// Loans (same query as dashboard/core/faculty-data.php)
$requests_js  = [];
$requests_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY request_date DESC");
while ($requests_raw && ($row = mysqli_fetch_assoc($requests_raw))) {
    $requests_js[] = $row;
}

// Counts the view reads (same exact-status counts as the page)
$stat_waiting = $stat_approved = $stat_on_loan = $stat_overdue = 0;
foreach ($requests_js as $r) {
    switch ($r['status'] ?? '') {
        case 'Waiting':  $stat_waiting++;  break;
        case 'Approved': $stat_approved++; break;
        case 'On Loan':  $stat_on_loan++;  break;
        case 'Overdue':  $stat_overdue++;  break;
    }
}

// Room reservations (same file the page uses; reads $conn and $uid_safe)
require __DIR__ . '/../../activity/core/faculty-reservations-data.php';

// Unread notifications (the "Needs your attention" list counts them)
$notifications = fnotif_build_list($conn, (string)$_SESSION['faculty_id']);
$notif_count   = fnotif_count_unread($notifications);

// Only read by the header part of the view, which the live refresh never swaps in
$firstname       = explode(' ', trim((string)($_SESSION['faculty_name'] ?? '')))[0];
$profile_pic_url = '';
$initials        = strtoupper(mb_substr($firstname, 0, 1));
$fac_boot_tab    = 'home';

ob_start();
include __DIR__ . '/../views/faculty-screen.php';
$html = ob_get_clean();

echo json_encode(
    ['ok' => true, 'html' => $html, 'notif_count' => (int)$notif_count],
    JSON_INVALID_UTF8_SUBSTITUTE
);
$conn->close();
