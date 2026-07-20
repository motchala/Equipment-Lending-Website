<?php
/**
 * room-reservation/api/check-room-availability.php
 *
 * Read-only endpoint: checks whether a requested room time slot overlaps
 * with any existing Approved reservation in tbl_room_reservations.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 *
 * GET parameters:
 *   room_id          — positive integer
 *   reservation_date — string, YYYY-MM-DD
 *   start_time       — string, HH:MM (00:00–23:59)
 *   end_time         — string, HH:MM (00:00–23:59), must be > start_time
 *
 * Responses:
 *   200  {"available": true,  "conflict": false}   — no overlap found
 *   200  {"available": false, "conflict": true}    — overlap found
 *   400  {"error": "…"}                            — bad / missing parameter
 *   401  {"error": "Unauthorized"}                 — no faculty session
 *   500  {"error": "An error occurred. Please try again."} — DB error
 *
 * This file contains NO INSERT, UPDATE, or DELETE statements.
 */

// ── 1.1 Scaffold: correct depth-2 include order ───────────────────────────────
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

if (empty($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';

// ── 1.2 GET parameter validation ─────────────────────────────────────────────

// room_id — must be a positive integer
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($room_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id must be a positive integer.']);
    exit;
}

// reservation_date — must match YYYY-MM-DD and be a real calendar date
$res_date = isset($_GET['reservation_date']) ? trim($_GET['reservation_date']) : '';
if ($res_date === '') {
    http_response_code(400);
    echo json_encode(['error' => 'reservation_date is required.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $res_date)) {
    http_response_code(400);
    echo json_encode(['error' => 'reservation_date must be in YYYY-MM-DD format.']);
    exit;
}
$date_obj = DateTime::createFromFormat('Y-m-d', $res_date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $res_date) {
    http_response_code(400);
    echo json_encode(['error' => 'reservation_date is not a valid calendar date.']);
    exit;
}

// start_time — must match HH:MM and be within 00:00–23:59
$start_time = isset($_GET['start_time']) ? trim($_GET['start_time']) : '';
if ($start_time === '') {
    http_response_code(400);
    echo json_encode(['error' => 'start_time is required.']);
    exit;
}
if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
    http_response_code(400);
    echo json_encode(['error' => 'start_time must be in HH:MM format.']);
    exit;
}
[$st_h, $st_m] = explode(':', $start_time);
if ((int)$st_h > 23 || (int)$st_m > 59) {
    http_response_code(400);
    echo json_encode(['error' => 'start_time is out of range (00:00–23:59).']);
    exit;
}

// end_time — must match HH:MM and be within 00:00–23:59
$end_time = isset($_GET['end_time']) ? trim($_GET['end_time']) : '';
if ($end_time === '') {
    http_response_code(400);
    echo json_encode(['error' => 'end_time is required.']);
    exit;
}
if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    http_response_code(400);
    echo json_encode(['error' => 'end_time must be in HH:MM format.']);
    exit;
}
[$et_h, $et_m] = explode(':', $end_time);
if ((int)$et_h > 23 || (int)$et_m > 59) {
    http_response_code(400);
    echo json_encode(['error' => 'end_time is out of range (00:00–23:59).']);
    exit;
}

// start_time must be strictly before end_time
if ($start_time >= $end_time) {
    http_response_code(400);
    echo json_encode(['error' => 'start_time must be before end_time.']);
    exit;
}

// ── 1.3 Overlap query and JSON response ──────────────────────────────────────

$conn = getDB();

// Strict-overlap predicate: existing.start_time < req.end_time
//                       AND existing.end_time   > req.start_time
// bind_param order: room_id (i), reservation_date (s),
//                  end_time (s) — bound to "start_time < ?" placeholder,
//                  start_time (s) — bound to "end_time > ?" placeholder
$sql = "SELECT COUNT(*) FROM tbl_room_reservations
        WHERE room_id          = ?
          AND reservation_date = ?
          AND status           = 'Approved'
          AND start_time       < ?
          AND end_time         > ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('check-room-availability.php: prepare() failed — ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
    exit;
}

// NOTE: end_time is passed first (matches "start_time < ?" in SQL),
//       start_time is passed second (matches "end_time > ?" in SQL).
$stmt->bind_param('isss', $room_id, $res_date, $end_time, $start_time);

if ($stmt->execute() === false) {
    error_log('check-room-availability.php: execute() failed — ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
    exit;
}

$result = $stmt->get_result();
$count  = (int)$result->fetch_row()[0];

$stmt->close();
$conn->close();

if ($count === 0) {
    echo json_encode(['available' => true,  'conflict' => false]);
} else {
    echo json_encode(['available' => false, 'conflict' => true]);
}
