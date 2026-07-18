<?php
/**
 * room-reservation/api/verify-room-code.php
 * Validates a faculty code for the room reservation student flow.
 * Mirrors equipment-booking/api/verify-faculty-code.php exactly.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 *
 * POST body (JSON):
 *   code         — faculty-generated one-time code
 *   student_name — student's full name
 *   student_id   — student's ID number
 *
 * Success response:
 *   { valid: true, faculty_id, faculty_name, code_db_id, rooms: [...] }
 *   rooms[] = { campus_key, campus_name, building_name, room_id, room_name,
 *               floor_label, seating_capacity, status }
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$body         = json_decode(file_get_contents('php://input'), true);
$code         = trim($body['code']         ?? '');
$student_name = trim($body['student_name'] ?? '');
$student_id   = trim($body['student_id']   ?? '');

if (!$code || !$student_name || !$student_id) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ── Look up the code ───────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id, faculty_id, faculty_name, is_used
       FROM tbl_faculty_codes
      WHERE code = ?
      LIMIT 1"
);
$stmt->bind_param('s', $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Invalid faculty code. Please check the code and try again.']);
    exit();
}
if ($row['is_used']) {
    echo json_encode(['error' => 'This code has already been used. Ask your faculty for a new code.']);
    exit();
}

// ── Overdue block (equipment overdue blocks room reservations too) ─────────
$overdue = $conn->prepare(
    "SELECT id FROM tbl_requests
      WHERE faculty_id = ? AND status = 'Overdue'
      LIMIT 1"
);
$overdue->bind_param('s', $row['faculty_id']);
$overdue->execute();
$overdue->store_result();
$has_overdue = $overdue->num_rows > 0;
$overdue->close();

if ($has_overdue) {
    echo json_encode([
        'error' => 'Your faculty advisor currently has an overdue equipment item. Room reservations are not allowed until it is returned. Please coordinate with ' . htmlspecialchars($row['faculty_name']) . '.',
    ]);
    exit();
}

// ── Email 1: Notify faculty that student is about to reserve a room ────────
$fac_stmt = $conn->prepare("SELECT email, fullname FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac_stmt->bind_param('s', $row['faculty_id']);
$fac_stmt->execute();
$fac_row = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();

if ($fac_row && !empty($fac_row['email'])) {
    require_once __DIR__ . '/../../equipment-booking/core/mailer.php';
    $subject = 'PUPSync: ' . $student_name . ' is about to reserve a room';
    $body_html = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
        <div style="background:#800000;padding:24px 28px;">
            <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync &middot; Room Reservation Alert</h2>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;color:#333;font-size:.95rem;">
                Hello <strong>' . htmlspecialchars($fac_row['fullname']) . '</strong>,
            </p>
            <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
                A student you authorized is currently filling out a room reservation form using your code.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;width:40%;">Student Name</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($student_name) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Student ID</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($student_id) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Status</td>
                    <td style="padding:10px 14px;color:#e65100;font-weight:700;">Form opened &mdash; not yet submitted</td>
                </tr>
            </table>
            <p style="margin:20px 0 0;font-size:.8rem;color:#999;">
                If you did not authorize this student, your code may have been shared without your knowledge.
                Contact the admin office immediately.
            </p>
        </div>
        <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
            PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus
        </div>
    </div>';
    sendPupSyncEmail($fac_row['email'], $fac_row['fullname'], $subject, $body_html);
}

// ── Return available bookable rooms ───────────────────────────────────────
$rooms_stmt = $conn->prepare(
    "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label,
            r.seating_capacity, r.status,
            b.name AS building_name, b.building_key,
            c.campus_key, c.campus_name
       FROM tbl_rooms r
       JOIN tbl_buildings b ON b.building_id = r.building_id
       JOIN tbl_campuses  c ON c.campus_id  = b.campus_id
      WHERE r.is_archived = 0
        AND r.status = 'Available'
      ORDER BY c.campus_name ASC, b.name ASC, r.floor_number ASC, r.sort_order ASC"
);
$rooms_stmt->execute();
$rooms_result = $rooms_stmt->get_result();
$rooms = [];
while ($r = $rooms_result->fetch_assoc()) {
    $fl = !empty($r['floor_label']) ? $r['floor_label'] : _ordinal_floor_rr((int)$r['floor_number']);
    $rooms[] = [
        'room_id'          => (int)$r['room_id'],
        'room_name'        => $r['room_name'],
        'floor_label'      => $fl,
        'building_name'    => $r['building_name'],
        'campus_key'       => $r['campus_key'],
        'campus_name'      => $r['campus_name'],
        'seating_capacity' => $r['seating_capacity'] !== null ? (int)$r['seating_capacity'] : null,
        'status'           => $r['status'],
    ];
}
$rooms_stmt->close();

function _ordinal_floor_rr(int $n): string {
    if ($n === 1) return '1st Floor';
    if ($n === 2) return '2nd Floor';
    if ($n === 3) return '3rd Floor';
    return $n . 'th Floor';
}

echo json_encode([
    'valid'        => true,
    'faculty_id'   => $row['faculty_id'],
    'faculty_name' => $row['faculty_name'],
    'code_db_id'   => (int)$row['id'],
    'rooms'        => $rooms,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
