<?php
/**
 * room-reservation/api/submit-student-reserve.php
 * Student submits a room reservation via faculty code.
 * Mirrors equipment-booking/api/submit-student-borrow.php exactly.
 *
 * POST body (JSON):
 *   code_db_id, faculty_id, faculty_name,
 *   student_name, student_id,
 *   room_id, reservation_date, start_time, end_time,
 *   purpose, attendees, notes (optional)
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
$code_db_id   = intval($body['code_db_id']       ?? 0);
$faculty_id   = trim($body['faculty_id']          ?? '');
$faculty_name = trim($body['faculty_name']        ?? '');
$student_name = trim($body['student_name']        ?? '');
$student_id   = trim($body['student_id']          ?? '');
$room_id      = intval($body['room_id']           ?? 0);
$res_date     = trim($body['reservation_date']    ?? '');
$start_time   = trim($body['start_time']          ?? '');
$end_time     = trim($body['end_time']            ?? '');
$purpose      = trim($body['purpose']             ?? '');
$attendees    = max(1, intval($body['attendees']  ?? 1));
$notes        = trim($body['notes']               ?? '');

// ── Input validation ──────────────────────────────────────────────────────
if (!$code_db_id || !$faculty_id || !$student_name || !$student_id ||
    !$room_id || !$res_date || !$start_time || !$end_time || !$purpose) {
    echo json_encode(['error' => 'All required fields must be filled in.']);
    exit();
}

$today = date('Y-m-d');
if ($res_date < $today) {
    echo json_encode(['error' => 'Reservation date cannot be in the past.']);
    exit();
}
if ($end_time <= $start_time) {
    echo json_encode(['error' => 'End time must be after start time.']);
    exit();
}
// ── Operating hours enforcement (07:00–20:00) ─────────────────────────────
if ($start_time < '07:00' || $end_time > '20:00') {
    echo json_encode(['error' => 'Reservations must be within operating hours: 7:00 AM to 8:00 PM.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ── Re-verify code still unused (race-condition guard) ────────────────────
$chk = $conn->prepare("SELECT id FROM tbl_faculty_codes WHERE id = ? AND is_used = 0 LIMIT 1");
$chk->bind_param('i', $code_db_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close();
    echo json_encode(['error' => 'This code was just used by someone else. Ask your faculty for a new code.']);
    exit();
}
$chk->close();

// ── Verify room is still available and not archived ───────────────────────
$room_chk = $conn->prepare(
    "SELECT room_name FROM tbl_rooms
      WHERE room_id = ? AND is_archived = 0 AND status = 'Available'
      LIMIT 1"
);
$room_chk->bind_param('i', $room_id);
$room_chk->execute();
$room_row = $room_chk->get_result()->fetch_assoc();
$room_chk->close();

if (!$room_row) {
    echo json_encode(['error' => 'This room is no longer available for booking.']);
    exit();
}
$room_name = $room_row['room_name'];

// ── Insert reservation (defaults to Approved; engine may flip to Declined) ─
$notes_val = $notes !== '' ? $notes : null;
$ins = $conn->prepare(
    "INSERT INTO tbl_room_reservations
        (room_id, faculty_id, faculty_name,
         submitted_as, submitted_by_name, submitted_by_id,
         purpose, attendees, reservation_date, start_time, end_time,
         notes, status, request_date)
     VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW())"
);
$ins->bind_param(
    'isssssissss',
    $room_id, $faculty_id, $faculty_name,
    $student_name, $student_id,
    $purpose, $attendees, $res_date, $start_time, $end_time,
    $notes_val
);

if (!$ins->execute()) {
    error_log('[PUPSync] submit-student-reserve insert failed: ' . $conn->error);
    $ins->close();
    echo json_encode(['error' => 'Failed to save reservation. Please try again.']);
    exit();
}
$reservation_id = $conn->insert_id;
$ins->close();

// ── Run Arbitration Engine ────────────────────────────────────────────────
require_once __DIR__ . '/../../equipment-booking/core/arbitration-engine.php';
ArbitrationEngine::processRoomReservation($conn, $reservation_id);

// ── Read final status written by engine ───────────────────────────────────
$status_stmt = $conn->prepare(
    "SELECT status, reason FROM tbl_room_reservations WHERE id = ? LIMIT 1"
);
$status_stmt->bind_param('i', $reservation_id);
$status_stmt->execute();
$status_row = $status_stmt->get_result()->fetch_assoc();
$status_stmt->close();
$final_status = $status_row['status'] ?? 'Approved';
$final_reason = $status_row['reason'] ?? '';

// ── Mark code as used ─────────────────────────────────────────────────────
$upd = $conn->prepare(
    "UPDATE tbl_faculty_codes
        SET is_used = 1, used_by_name = ?, used_by_id = ?, used_at = NOW()
      WHERE id = ?"
);
$upd->bind_param('ssi', $student_name, $student_id, $code_db_id);
$upd->execute();
$upd->close();

// ── Email 2: Notify faculty of successful room reservation ────────────────
$fac_stmt = $conn->prepare("SELECT email, fullname FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac_stmt->bind_param('s', $faculty_id);
$fac_stmt->execute();
$fac_row = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();

if ($fac_row && !empty($fac_row['email'])) {
    require_once __DIR__ . '/../../equipment-booking/core/mailer.php';
    $status_color = $final_status === 'Approved' ? '#2e7d32' : '#c62828';
    $status_icon  = $final_status === 'Approved' ? '&#10003; Approved' : '&#10007; Declined';
    $subject = 'PUPSync: Room Reservation ' . $final_status . ' — ' . $room_name;
    $body_html = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
        <div style="background:#800000;padding:24px 28px;">
            <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync &middot; Room Reservation Confirmed</h2>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;color:#333;font-size:.95rem;">
                Hello <strong>' . htmlspecialchars($fac_row['fullname']) . '</strong>,
            </p>
            <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
                The room reservation you authorized has been processed by the system.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;width:40%;">Reservation ID</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">#' . $reservation_id . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Student</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($student_name) . ' &nbsp;&middot;&nbsp; ' . htmlspecialchars($student_id) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Room</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($room_name) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Date</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($res_date) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Time</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($start_time) . ' &ndash; ' . htmlspecialchars($end_time) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Purpose</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($purpose) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Status</td>
                    <td style="padding:10px 14px;color:' . $status_color . ';font-weight:700;">' . $status_icon . '</td>
                </tr>
                ' . ($final_reason ? '<tr><td style="padding:10px 14px;color:#666;">Reason</td><td style="padding:10px 14px;color:#666;">' . htmlspecialchars($final_reason) . '</td></tr>' : '') . '
            </table>
        </div>
        <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
            PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus
        </div>
    </div>';
    sendPupSyncEmail($fac_row['email'], $fac_row['fullname'], $subject, $body_html);
}

echo json_encode([
    'success'        => true,
    'reservation_id' => $reservation_id,
    'status'         => $final_status,
    'reason'         => $final_reason,
    'room_name'      => $room_name,
]);
