<?php
require_once __DIR__ . '/../../config/security-headers.php';
// ajax/poll-requests-data.php
// Returns current request data as JSON for live re-rendering.
// No page reload needed — the JS reads this and updates the DOM directly.

require_once __DIR__ . '/../../config/session.php';
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ── Deadline tick: decide missed same-day requests, decline undecided ones past their deadline ──────────────────────────────────────────
require_once __DIR__ . '/../core/auto-approve-tick.php';
equipment_auto_approve_tick($conn);

// The admin poll also runs the room side of the scheduled work (same-day room buffers), see shared/core/pseudo-cron.php.
require_once __DIR__ . '/../../shared/core/pseudo-cron.php';
ps_pseudo_cron_tick($conn);

$today = date('Y-m-d');

// -- Status housekeeping (one shared routine, see core/request-decision.php) ----
// The tick above already declined undecided requests past their deadline (12:01 AM on the
// borrow date). This marks loans that are out and past their return time as Overdue, closes
// bookings nobody collected, and applies stock for bookings that have started.
BookingSchedule::tick($conn);

// ── Stats ──────────────────────────────────────────────────────────────────
$stats = [];
foreach (
    [
        'waiting'   => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Waiting'",
        'approved'  => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Approved'",
        'declined'  => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Declined' AND COALESCE(arbitration_rule,'')='override'",
        'overdue'   => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Overdue'",
        'inv_total' => "SELECT COUNT(*) c FROM tbl_inventory WHERE is_archived=0",
        'inv_low'   => "SELECT COUNT(*) c FROM tbl_inventory WHERE quantity<=2 AND is_archived=0",
        'total_req' => "SELECT COUNT(*) c FROM tbl_requests WHERE NOT (status='Declined' AND COALESCE(arbitration_rule,'')<>'override') AND status <> 'Cancelled'",
    ] as $key => $sql
) {
    $stats[$key] = (int) mysqli_fetch_assoc(mysqli_query($conn, $sql))['c'];
}

// ── Waiting requests ───────────────────────────────────────────────────────
// Guard: check if dual-borrowing-mode migration columns exist.
$_sa_col = $conn->query("SHOW COLUMNS FROM tbl_requests LIKE 'submitted_as'");
$_has_sa = $_sa_col && $_sa_col->num_rows > 0;
$_bid_col = $conn->query("SHOW COLUMNS FROM tbl_requests LIKE 'batch_id'");
$_has_bid = $_bid_col && $_bid_col->num_rows > 0;

// Build column lists as arrays for safe concatenation
$base_cols = ['id', 'faculty_name', 'faculty_id', 'equipment_name', 'instructor', 'room',
              'borrow_date', 'return_date', 'return_token', 'returned_at', 'status',
              'request_date', 'reason', 'document_path', 'arbitration_rule',
              'submitted_by_name'];
$extra_cols = [];
if ($_has_sa)  $extra_cols[] = 'submitted_as';
if ($_has_bid) $extra_cols[] = 'batch_id';
$all_cols = array_merge($base_cols, $extra_cols);
$col_list = implode(', ', $all_cols);

$waiting = [];
$r = mysqli_query($conn, "SELECT {$col_list} FROM tbl_requests WHERE status='Waiting' ORDER BY request_date DESC");
while ($row = mysqli_fetch_assoc($r)) {
    if (!$_has_sa) $row['submitted_as'] = null;
    if (!$_has_bid) $row['batch_id'] = null;
    $waiting[] = $row;
}

// ── Approved/Overdue (for return-body and approved-list) ───────────────────
$approved = [];
// Extension info (only once the columns exist, i.e. after the first extension anywhere)
$_ext_col = mysqli_query($conn, "SHOW COLUMNS FROM tbl_requests LIKE 'extension_count'");
$_has_ext = $_ext_col && $_ext_col->num_rows > 0;
$ext_cols = $_has_ext ? ['extension_count', 'original_return_date', 'original_return_time'] : [];
$all_cols_ext = array_merge($all_cols, $ext_cols);
$col_list_ext = implode(', ', $all_cols_ext);
$r = mysqli_query($conn, "SELECT {$col_list_ext} FROM tbl_requests WHERE status IN ('Approved','Overdue') ORDER BY request_date DESC");
while ($row = mysqli_fetch_assoc($r)) {
    if (!$_has_sa) $row['submitted_as'] = null;
    if (!$_has_bid) $row['batch_id'] = null;
    $approved[] = $row;
}

// ── Declined ───────────────────────────────────────────────────────────────
$declined = [];
$r = mysqli_query($conn, "SELECT {$col_list} FROM tbl_requests WHERE status='Declined' AND COALESCE(arbitration_rule,'')='override' ORDER BY request_date DESC");
while ($row = mysqli_fetch_assoc($r)) {
    if (!$_has_sa) $row['submitted_as'] = null;
    if (!$_has_bid) $row['batch_id'] = null;
    $declined[] = $row;
}

// ── Recent activity (dashboard feed) ──────────────────────────────────────
$activity = [];
$activity_cols = ['faculty_name', 'equipment_name', 'status', 'request_date'];
if ($_has_sa) $activity_cols[] = 'submitted_as';
$activity_col_list = implode(', ', $activity_cols);
$r = mysqli_query($conn, "SELECT {$activity_col_list} FROM tbl_requests WHERE NOT (status='Declined' AND COALESCE(arbitration_rule,'')<>'override') ORDER BY request_date DESC LIMIT 6");
while ($row = mysqli_fetch_assoc($r)) {
    if (!$_has_sa) $row['submitted_as'] = null;
    $activity[] = $row;
}

// ── High-water mark for change detection ──────────────────────────────────
$hw = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT MAX(returned_at) AS last_return FROM tbl_requests"
))['last_return'] ?? null;

// Fingerprint of every request (how many, the newest id, and each one's status / return date / return time).
// It changes whenever ANY request is added, decided, cancelled, extended or returned, so the Loans table
// is refreshed even when two changes cancel each other out in the plain counts above.
$loans_sig = '';
$sig_row = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n, COALESCE(MAX(id),0) AS max_id,
            COALESCE(SUM(CRC32(CONCAT(status,'|',COALESCE(return_date,''),'|',COALESCE(returned_at,'')))),0) AS chk
       FROM tbl_requests"
));
if ($sig_row) {
    $loans_sig = $sig_row['n'] . '-' . $sig_row['max_id'] . '-' . $sig_row['chk'];
}

$conn->close();

echo json_encode([
    'loans_sig' => $loans_sig,
    'today'    => $today,
    'stats'    => $stats,
    'waiting'  => $waiting,
    'approved' => $approved,
    'declined' => $declined,
    'activity' => $activity,
    'last_return' => $hw,
]);
