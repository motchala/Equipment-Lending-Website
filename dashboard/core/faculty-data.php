<?php
// ── Inventory & Requests ───────────────────────────────────────────────────
$category_result  = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory WHERE is_archived = 0 ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY item_name ASC");
$uid_safe = mysqli_real_escape_string($conn, $_SESSION['faculty_id']);

// ── Stats ──────────────────────────────────────────────────────────────────
$stat_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe'"))['c'];
$stat_waiting  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Waiting'"))['c'];
$stat_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Approved'"))['c'];
$stat_on_loan  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='On Loan'"))['c'];
$stat_declined = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Declined'"))['c'];
$stat_overdue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Overdue'"))['c'];

$has_overdue_block = $stat_overdue > 0;

// The "Borrowing Blocked" notice shows as a toast once per login session —
// not on every page load/refresh. It reappears after logout+login, or once
// the PHP session itself has expired (e.g. from being away too long).
$show_overdue_toast = $has_overdue_block && empty($_SESSION['overdue_toast_shown']);
if ($has_overdue_block) {
    $_SESSION['overdue_toast_shown'] = true;
} else {
    // No longer blocked (e.g. the item was returned) — clear the flag so
    // the toast shows fresh again if they end up blocked in the future.
    unset($_SESSION['overdue_toast_shown']);
}
// ── Requests JSON for JS ───────────────────────────────────────────────────
$requests_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY request_date DESC");
$requests_js = [];
while ($row = mysqli_fetch_assoc($requests_raw)) $requests_js[] = $row;
$requests_json = json_encode($requests_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
