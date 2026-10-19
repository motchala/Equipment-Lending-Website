<?php
/**
 * room-reservation/api/check-slot-availability.php
 *
 * Returns all bookable rooms for a campus that have NO conflicting
 * Approved reservation in the requested time slot.
 *
 * Used by the AI chatbot mini-form to populate the room dropdown
 * after campus + date + time are selected.
 *
 * POST only — GET returns 405.
 * Requires a valid faculty session — returns 401 if not logged in.
 * Requires CSRF token in the JSON body.
 *
 * Request body (JSON):
 *   {
 *     csrf_token  : string   — required
 *     campus      : string   — 'main' or 'cite'
 *     date        : string   — YYYY-MM-DD
 *     start_time  : string   — HH:MM  (07:00–20:00)
 *     end_time    : string   — HH:MM  (07:00–20:00), must be > start_time
 *   }
 *
 * Success response (200):
 *   {
 *     rooms: [
 *       { room_id, room_name, building_name, floor_label }
 *     ]
 *   }
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
date_default_timezone_set('Asia/Manila');

ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

function slot_respond(int $code, array $payload): never
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// ── Method guard: POST only ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    slot_respond(405, ['error' => 'Method not allowed.']);
}

// ── Session guard ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['faculty_id'])) {
    slot_respond(401, ['error' => 'Unauthorized.']);
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    slot_respond(400, ['error' => 'Invalid request body.']);
}

// ── CSRF guard ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/csrf.php';
$submitted_token = trim((string)($body['csrf_token'] ?? ''));
$session_token   = (string)($_SESSION['csrf_token'] ?? '');
if ($session_token === '' || $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)) {
    slot_respond(403, ['error' => 'Invalid session token. Please refresh the page.']);
}

// ── Validate campus ───────────────────────────────────────────────────────────
$campus = trim(strtolower((string)($body['campus'] ?? '')));
if (!in_array($campus, ['main', 'cite'], true)) {
    slot_respond(400, ['error' => 'campus must be "main" or "cite".']);
}
$campus_id = ($campus === 'cite') ? 2 : 1;

// ── Validate date ─────────────────────────────────────────────────────────────
$res_date = trim((string)($body['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $res_date)) {
    slot_respond(400, ['error' => 'date must be in YYYY-MM-DD format.']);
}
$date_obj = DateTimeImmutable::createFromFormat('Y-m-d', $res_date,
    new DateTimeZone('Asia/Manila'));
if (!$date_obj || $date_obj->format('Y-m-d') !== $res_date) {
    slot_respond(400, ['error' => 'date is not a valid calendar date.']);
}
// No Sundays
if ($date_obj->format('N') === '7') {
    slot_respond(400, ['error' => 'Reservations are not available on Sundays.']);
}
// Not in the past
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
if ($date_obj < $today) {
    slot_respond(400, ['error' => 'date cannot be in the past.']);
}
// Max 21 days ahead
$max_date = $today->modify('+21 days');
if ($date_obj > $max_date) {
    slot_respond(400, ['error' => 'date cannot be more than 21 days in the future.']);
}

// ── Validate start_time ───────────────────────────────────────────────────────
$start_time = trim((string)($body['start_time'] ?? ''));
if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
    slot_respond(400, ['error' => 'start_time must be in HH:MM format.']);
}
if ($start_time < '07:00' || $start_time >= '20:00') {
    slot_respond(400, ['error' => 'start_time must be between 07:00 and 20:00.']);
}

// ── Validate end_time ─────────────────────────────────────────────────────────
$end_time = trim((string)($body['end_time'] ?? ''));
if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    slot_respond(400, ['error' => 'end_time must be in HH:MM format.']);
}
if ($end_time <= $start_time) {
    slot_respond(400, ['error' => 'end_time must be after start_time.']);
}
if ($end_time > '20:00') {
    slot_respond(400, ['error' => 'end_time cannot be after 20:00.']);
}

// ── Database connection ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

// ── Get all bookable rooms for this campus ────────────────────────────────────
$rooms_stmt = $conn->prepare(
    "SELECT r.room_id, r.room_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
            b.name AS building_name
       FROM tbl_rooms r
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE b.campus_id   = ?
        AND r.status      = 'Available'
        AND r.is_archived = 0
      ORDER BY b.sort_order ASC, r.floor_number ASC, r.sort_order ASC"
);
if ($rooms_stmt === false) {
    error_log('[check-slot-availability] rooms prepare failed: ' . $conn->error);
    slot_respond(500, ['error' => 'Database error. Please try again.']);
}
$rooms_stmt->bind_param('i', $campus_id);
$rooms_stmt->execute();
$all_rooms = $rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rooms_stmt->close();

if (empty($all_rooms)) {
    slot_respond(200, ['rooms' => []]);
}

// ── Find room IDs that have a conflicting booking on this slot ────────────────
// Overlap condition (same as check-room-availability.php):
//   existing.start_time < requested.end_time
//   AND existing.end_time > requested.start_time
$room_ids     = array_column($all_rooms, 'room_id');
$placeholders = implode(',', array_fill(0, count($room_ids), '?'));
$types        = str_repeat('i', count($room_ids));

$conflict_stmt = $conn->prepare(
    "SELECT DISTINCT room_id
       FROM tbl_room_reservations
      WHERE room_id IN ({$placeholders})
        AND reservation_date = ?
        AND status           = 'Approved'
        AND start_time       < ?
        AND end_time         > ?"
);
if ($conflict_stmt === false) {
    error_log('[check-slot-availability] conflict prepare failed: ' . $conn->error);
    slot_respond(500, ['error' => 'Database error. Please try again.']);
}

// bind_param: all room IDs (iii…) + date (s) + end_time (s) + start_time (s)
$bind_args = array_merge([$types . 'sss'], $room_ids, [$res_date, $end_time, $start_time]);
$refs = [];
foreach ($bind_args as $k => $v) { $refs[$k] = &$bind_args[$k]; }
call_user_func_array([$conflict_stmt, 'bind_param'], $refs);
$conflict_stmt->execute();
$conflict_rows = $conflict_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conflict_stmt->close();

$conflicted_ids = array_column($conflict_rows, 'room_id');
$conflicted_set = array_flip($conflicted_ids); // O(1) lookup

// ── Filter out conflicted rooms ───────────────────────────────────────────────
$available_rooms = array_values(array_filter($all_rooms, function ($r) use ($conflicted_set) {
    return !isset($conflicted_set[$r['room_id']]);
}));

// ── Return available rooms ────────────────────────────────────────────────────
$rooms_out = array_map(function ($r) {
    return [
        'room_id'       => (int)$r['room_id'],
        'room_name'     => $r['room_name'],
        'building_name' => $r['building_name'],
        'floor_label'   => $r['floor_label'],
    ];
}, $available_rooms);

slot_respond(200, ['rooms' => $rooms_out]);
