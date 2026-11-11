<?php
declare(strict_types=1);
/**
 * submit-cart-borrow.php
 *
 * The one endpoint behind the faculty Equipment tab's borrow flow (pick one or many items,
 * then fill in the Borrow modal). Checks each item against BookingSchedule (time window,
 * availability, room conflicts) and files it as 'Waiting'. Same-day requests are then decided
 * by the auto-approve tick; later-date requests wait for an admin.
 * Returns JSON — no page redirect, enabling live UI updates.
 *
 * Accepts either:
 *   - application/json                      (no request letter), or
 *   - multipart/form-data with a `payload` field holding the same JSON, plus an optional
 *     `request_document` file (the request letter; only kept for a later-date booking).
 *
 * Payload fields:
 *   csrf_token   string
 *   room_id      string   room id from the room picker, or "outside"
 *   items        array of { name: string, qty: int }
 *   booking_mode string   "today" or "future"
 *   borrow_date  string   "YYYY-MM-DD" (future mode)
 *   time_slot    string   "HH:MM" start time
 *   duration     int      hours (1, 2, 3, or 6)
 *   collector_type "faculty" | "student", collector_name string (student only)
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

$is_multipart = stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data') !== false;
$raw_body     = $is_multipart ? (string)($_POST['payload'] ?? '') : (string)file_get_contents('php://input');
$body         = json_decode($raw_body, true);
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
$stmt_usr = $conn->prepare("SELECT fullname, faculty_id FROM tbl_users WHERE faculty_id = ? LIMIT 1");
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

// Where: a live room record (same tables as Facilities), or "outside" (no room).
$rr = BookingSchedule::resolveRoom($conn, (string)($body['room_id'] ?? ''));
if (!$rr['ok']) {
    echo json_encode(['ok' => false, 'error' => $rr['error']]);
    exit;
}
$room    = $rr['label'];
$room_id = $rr['id'];   // null = "Outside"

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

// Build a pseudo-$_POST array that BookingSchedule::parse() expects.
$pseudo_post = [
    'booking_mode' => (string)($body['booking_mode'] ?? ''),
    'start_time'   => (string)($body['time_slot'] ?? $body['start_time'] ?? ''),
    'duration'     => (int)($body['duration'] ?? 1),
    'borrow_date'  => (string)($body['borrow_date'] ?? ''),
];
$submitted = [];
$failed    = [];
$cart_names = [];
foreach ($raw_items as $raw) {
    if (is_array($raw)) {
        $name = trim((string)($raw['name'] ?? ''));
        if ($name !== '') {
            $cart_names[strtolower($name)] = true;
        }
    }
}
// ── Optional request letter: recommended for a LATER date, ignored for a same-day request ──
require_once __DIR__ . '/../../shared/core/document-helpers.php';
$letter_path = null;
if ($pseudo_post['booking_mode'] === 'future' && $is_multipart && isset($_FILES['request_document'])) {
    $letter_err  = null;
    $letter_path = ps_store_request_letter($_FILES['request_document'], 'uploads/request_letters', $faculty_id, $letter_err);
    if ($letter_path === null && $letter_err !== null) {
        echo json_encode(['ok' => false, 'error' => $letter_err]);
        exit;
    }
}

$batch_id = null;
if (count($cart_names) > 1) {
    $uuid = bin2hex(random_bytes(16));
    $batch_id = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-4' . substr($uuid, 13, 3)
        . '-a' . substr($uuid, 17, 3) . '-' . substr($uuid, 20, 12);
}

foreach ($raw_items as $raw) {
    $name = trim((string)($raw['name'] ?? ''));
    $qty  = max(1, (int)($raw['qty'] ?? 1));
    if ($name === '') continue;

    $chk = BookingSchedule::parse($conn, $pseudo_post, $name, $qty);
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
          submitted_by_name, submitted_by_id, batch_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'personal', ?, ?, ?)"
    );
    if (!$stmt_ins) {
        $failed[] = ['name' => $name, 'error' => 'Database error. Please try again.'];
        continue;
    }
    $stmt_ins->bind_param(
        'sssssssssiissss',   // borrow_qty + room_id are ints; booking_mode is TEXT ('today'/'future')
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
        $collector_id,
        $batch_id
    );
    if (!$stmt_ins->execute()) {
        $stmt_ins->close();
        $failed[] = ['name' => $name, 'error' => 'Could not save request for ' . $name . '.'];
        continue;
    }
    $request_id = (int)$conn->insert_id;
    $stmt_ins->close();

    if ($letter_path !== null) {
        $stmt_doc = $conn->prepare('UPDATE tbl_requests SET document_path = ? WHERE id = ?');
        if ($stmt_doc) {
            $stmt_doc->bind_param('si', $letter_path, $request_id);
            $stmt_doc->execute();
            $stmt_doc->close();
        }
    }

    // Request stays Waiting — admin/super admin reviews and approves.
    $submitted[] = ['name' => $name, 'request_id' => $request_id, 'status' => 'Waiting'];
}

// Nothing was filed: do not leave an orphaned letter file behind.
if ($letter_path !== null && !$submitted) {
    @unlink(dirname(__DIR__, 2) . '/' . $letter_path);
}

echo json_encode([
    'ok'        => true,
    'submitted' => $submitted,
    'failed'    => $failed,
]);
