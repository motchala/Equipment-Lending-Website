<?php
declare(strict_types=1);
/**
 * submit-cart-borrow.php
 *
 * Multi-item personal borrow for the faculty equipment cart.
 * Accepts JSON, processes each item through BookingSchedule + ArbitrationEngine,
 * returns JSON — no page redirect, enabling live UI updates.
 *
 * POST JSON fields:
 *   csrf_token  string
 *   room_id     string   (from RoomPicker, may be empty for "Outside")
 *   items       array of { name: string, qty: int }
 *   borrow_mode string   "today" (only today supported for now)
 *   time_slot   string   "HH:MM" start time (today mode only)
 *   duration    int      hours (today mode only)
 *
 * Returns:
 *   { ok: true,  submitted: [{name, request_id, status}], failed: [{name, error}] }
 *   { ok: false, error: string }
 *
 * Depth 2 → config prefix: __DIR__ . '/../../config/…'
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request body.']);
    exit;
}

// CSRF
$submitted_token = trim((string)($body['csrf_token'] ?? ''));
$session_token   = (string)($_SESSION['csrf_token'] ?? '');
if ($session_token === '' || $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid session token. Please refresh the page.']);
    exit;
}

$conn = getDB();
$conn->set_charset('utf8mb4');
BookingSchedule::ensureSchema($conn);

// Load faculty info
$stmt_usr = $conn->prepare("SELECT fullname, faculty_id, role FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$stmt_usr->bind_param('s', $_SESSION['faculty_id']);
$stmt_usr->execute();
$user = $stmt_usr->get_result()->fetch_assoc();
$stmt_usr->close();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'User profile not found.']);
    exit;
}
$faculty_name = (string)$user['fullname'];
$faculty_id   = (string)$user['faculty_id'];
$is_adviser   = BookingSchedule::isAdviserRole((string)($user['role'] ?? ''));

// Room: the cart uses a plain text input (not the room picker).
// Store as a text label with room_id = null — same as "Outside" in the existing system.
$room_label = trim((string)($body['room'] ?? ''));
if ($room_label === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter a room or location.']);
    exit;
}
$room    = $room_label;
$room_id = null;

// Collector info — who will physically pick up the equipment
$collector_type = (string)($body['collector_type'] ?? 'faculty');
$collector_name = trim((string)($body['collector_name'] ?? ''));
// Server-side validation: if student selected, name is required
if ($collector_type === 'student' && $collector_name === '') {
    echo json_encode(['ok' => false, 'error' => 'Student name is required when a student will collect.']);
    exit;
}
// Normalize: if faculty collecting, clear the student field
if ($collector_type !== 'student') {
    $collector_name = '';
}
$collector_id = ''; // ID field removed from this form

// Validate items array
$raw_items = $body['items'] ?? [];
if (!is_array($raw_items) || empty($raw_items)) {
    echo json_encode(['ok' => false, 'error' => 'No items provided.']);
    exit;
}

// Build a pseudo-$_POST array that BookingSchedule::parse() expects
// The cart always submits today borrowing, so mode=today with time+duration
// BookingSchedule::parse() reads: booking_mode, start_time, duration, borrow_date
$pseudo_post = [
    'booking_mode' => 'today',
    'start_time'   => (string)($body['time_slot'] ?? date('H:i')),
    'duration'     => (int)($body['duration'] ?? 1),
    'borrow_date'  => date('Y-m-d'),
];

$submitted = [];
$failed    = [];

foreach ($raw_items as $raw) {
    $name = trim((string)($raw['name'] ?? ''));
    $qty  = max(1, (int)($raw['qty'] ?? 1));
    if ($name === '') continue;

    $chk = BookingSchedule::parse($conn, $pseudo_post, $is_adviser, $name, $qty);
    if (!$chk['ok']) {
        $failed[] = ['name' => $name, 'error' => $chk['error']];
        continue;
    }
    $bk = $chk['data'];

    if (BookingSchedule::freeUnits($conn, $name, $bk['start_ts'], $bk['end_ts']) < $bk['qty']) {
        $failed[] = ['name' => $name, 'error' => '"' . $name . '" is already booked for that time.'];
        continue;
    }

    if ($room_id !== null) {
        $room_msg = BookingSchedule::roomConflictMessage($conn, $room_id, $bk['start_ts'], $bk['end_ts'], $faculty_id);
        if ($room_msg) {
            $failed[] = ['name' => $name, 'error' => $room_msg];
            continue;
        }
    }

    $stmt_ins = $conn->prepare(
        "INSERT INTO tbl_requests
         (faculty_name, faculty_id, equipment_name, instructor, room,
          borrow_date, return_date, borrow_time, return_time, borrow_qty, room_id,
          booking_mode, stock_applied, status, request_date, submitted_as,
          submitted_by_name, submitted_by_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'personal', ?, ?)"
    );
    if (!$stmt_ins) {
        $failed[] = ['name' => $name, 'error' => 'Database error. Please try again.'];
        continue;
    }
    $stmt_ins->bind_param(
        'ssssssssssiiss',
        $faculty_name,
        $faculty_id,
        $name,
        $faculty_name,
        $room,
        $bk['borrow_date'],
        $bk['return_date'],
        $bk['borrow_time'],
        $bk['return_time'],
        $bk['qty'],
        $room_id,
        $bk['mode'],
        $collector_name,
        $collector_id
    );
    if (!$stmt_ins->execute()) {
        $stmt_ins->close();
        $failed[] = ['name' => $name, 'error' => 'Could not save request for ' . $name . '.'];
        continue;
    }
    $request_id = (int)$conn->insert_id;
    $stmt_ins->close();

    // Request stays Waiting — admin/super admin reviews and approves.
    $submitted[] = ['name' => $name, 'request_id' => $request_id, 'status' => 'Waiting'];
}

echo json_encode([
    'ok'        => true,
    'submitted' => $submitted,
    'failed'    => $failed,
]);
