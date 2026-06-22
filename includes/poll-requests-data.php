<?php
require_once __DIR__ . '/security-headers.php';
// ajax/poll-requests-data.php
// Returns current request data as JSON for live re-rendering.
// No page reload needed — the JS reads this and updates the DOM directly.

require_once __DIR__ . '/session-config.php';
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
$conn = getDB();

$today = date('Y-m-d');

// ── Auto-decline expired waiting requests ──────────────────────────────────
$conn->prepare("
    UPDATE tbl_requests
    SET status = 'Declined', reason = 'Request expired – borrow date has already passed'
    WHERE status = 'Waiting' AND borrow_date < ?
")->bind_param('s', $today) && true; // silent

// ── Auto-mark overdue ──────────────────────────────────────────────────────
$conn->query("
    UPDATE tbl_requests
    SET status = 'Overdue'
    WHERE status = 'Approved' AND return_date < '$today'
");

// ── Stats ──────────────────────────────────────────────────────────────────
$stats = [];
foreach (
    [
        'waiting'   => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Waiting'",
        'approved'  => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Approved'",
        'declined'  => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Declined'",
        'overdue'   => "SELECT COUNT(*) c FROM tbl_requests WHERE status='Overdue'",
        'inv_total' => "SELECT COUNT(*) c FROM tbl_inventory WHERE is_archived=0",
        'inv_low'   => "SELECT COUNT(*) c FROM tbl_inventory WHERE quantity<=2 AND is_archived=0",
        'total_req' => "SELECT COUNT(*) c FROM tbl_requests",
    ] as $key => $sql
) {
    $stats[$key] = (int) mysqli_fetch_assoc(mysqli_query($conn, $sql))['c'];
}

// ── Waiting requests ───────────────────────────────────────────────────────
$waiting = [];
$r = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status='Waiting' ORDER BY request_date DESC");
while ($row = mysqli_fetch_assoc($r)) $waiting[] = $row;

// ── Approved/Overdue (for return-body and approved-list) ───────────────────
$approved = [];
$r = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status IN ('Approved','Overdue') ORDER BY request_date DESC");
while ($row = mysqli_fetch_assoc($r)) $approved[] = $row;

// ── Declined ───────────────────────────────────────────────────────────────
$declined = [];
$r = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE status='Declined' ORDER BY request_date DESC");
while ($row = mysqli_fetch_assoc($r)) $declined[] = $row;

// ── Recent activity (dashboard feed) ──────────────────────────────────────
$activity = [];
$r = mysqli_query($conn, "SELECT faculty_name, equipment_name, status, request_date FROM tbl_requests ORDER BY request_date DESC LIMIT 6");
while ($row = mysqli_fetch_assoc($r)) $activity[] = $row;

// ── High-water mark for change detection ──────────────────────────────────
$hw = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT MAX(returned_at) AS last_return FROM tbl_requests"
))['last_return'] ?? null;

$conn->close();

echo json_encode([
    'today'    => $today,
    'stats'    => $stats,
    'waiting'  => $waiting,
    'approved' => $approved,
    'declined' => $declined,
    'activity' => $activity,
    'last_return' => $hw,
]);
