<?php
/**
 * room-reservation/api/submit-faculty-reserve.php
 * Faculty submits their own room reservation (personal or adviser mode).
 * Called via fetch() from faculty-dashboard.php.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 *
 * POST body (JSON):
 *   room_id, reservation_date, start_time, end_time,
 *   purpose, attendees, notes (optional),
 *   submitted_as ('personal' | 'adviser'),
 *   csrf_token
 *
 * For submitted_as='adviser': document upload handled separately via
 * multipart POST — use FormData, not JSON.
 * This endpoint handles the JSON (personal) path only.
 * The multipart adviser path is handled in faculty-dashboard.php directly
 * (same pattern as equipment adviser borrowing).
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

$body         = json_decode(file_get_contents('php://input'), true);

// CSRF verification
$token = $body['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token. Please refresh and try again.']);
    exit();
}

$faculty_id   = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];
$room_id      = intval($body['room_id']          ?? 0);
$res_date     = trim($body['reservation_date']   ?? '');
$start_time   = trim($body['start_time']         ?? '');
$end_time     = trim($body['end_time']           ?? '');
$purpose      = trim($body['purpose']            ?? '');
$attendees    = max(1, intval($body['attendees'] ?? 1));
$notes        = trim($body['notes']              ?? '');
$submitted_as = trim($body['submitted_as']       ?? 'personal');

$allowed_modes = ['personal', 'adviser'];
if (!in_array($submitted_as, $allowed_modes, true)) {
    $submitted_as = 'personal';
}

// ── Input validation ──────────────────────────────────────────────────────
if (!$room_id || !$res_date || !$start_time || !$end_time || !$purpose) {
    echo json_encode(['error' => 'All required fields must be filled in.']);
    exit();
}
// Past datetime check — compare full date+time in Asia/Manila timezone
$now_manila   = new DateTime('now', new DateTimeZone('Asia/Manila'));
$req_datetime = DateTime::createFromFormat('Y-m-d H:i', $res_date . ' ' . $start_time,
                    new DateTimeZone('Asia/Manila'));
if ($req_datetime === false || $req_datetime <= $now_manila) {
    echo json_encode(['error' => 'Reservation date and time cannot be in the past.']);
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

$conn = getDB();

// ── Adviser gate — re-read allow_org_borrowing server-side ───────────────
if ($submitted_as === 'adviser') {
    $gate = $conn->prepare(
        "SELECT allow_org_borrowing FROM tbl_users WHERE faculty_id = ? LIMIT 1"
    );
    $gate->bind_param('s', $faculty_id);
    $gate->execute();
    $gate_row = $gate->get_result()->fetch_assoc();
    $gate->close();
    if ((int)($gate_row['allow_org_borrowing'] ?? 0) !== 1) {
        echo json_encode(['error' => 'Organisation reservations are not permitted for your account.']);
        exit();
    }
}

// ── Verify room exists and is available ──────────────────────────────────
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

// ── Insert reservation ────────────────────────────────────────────────────
$notes_val = $notes !== '' ? $notes : null;
$ins = $conn->prepare(
    "INSERT INTO tbl_room_reservations
        (room_id, faculty_id, faculty_name, submitted_as,
         purpose, attendees, reservation_date, start_time, end_time,
         notes, status, request_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW())"
);
$ins->bind_param(
    'issssissss',
    $room_id, $faculty_id, $faculty_name, $submitted_as,
    $purpose, $attendees, $res_date, $start_time, $end_time,
    $notes_val
);

if (!$ins->execute()) {
    error_log('[PUPSync] submit-faculty-reserve insert failed: ' . $conn->error);
    $ins->close();
    echo json_encode(['error' => 'Failed to save reservation. Please try again.']);
    exit();
}
$reservation_id = $conn->insert_id;
$ins->close();

// ── Run Arbitration Engine ────────────────────────────────────────────────
require_once __DIR__ . '/../../equipment-booking/core/arbitration-engine.php';
ArbitrationEngine::processRoomReservation($conn, $reservation_id);

// ── Read final status ─────────────────────────────────────────────────────
$status_stmt = $conn->prepare(
    "SELECT status, reason FROM tbl_room_reservations WHERE id = ? LIMIT 1"
);
$status_stmt->bind_param('i', $reservation_id);
$status_stmt->execute();
$status_row = $status_stmt->get_result()->fetch_assoc();
$status_stmt->close();
$final_status = $status_row['status'] ?? 'Approved';
$final_reason = $status_row['reason'] ?? '';

// ── Email: Notify faculty of their own reservation result ─────────────────
$fac_stmt = $conn->prepare("SELECT email FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac_stmt->bind_param('s', $faculty_id);
$fac_stmt->execute();
$fac_email_row = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();

if ($fac_email_row && !empty($fac_email_row['email'])) {
    require_once __DIR__ . '/../../equipment-booking/core/mailer.php';
    $status_color = $final_status === 'Approved' ? '#2e7d32' : '#c62828';
    $status_icon  = $final_status === 'Approved' ? '&#10003; Approved' : '&#10007; Declined';
    $subject      = 'PUPSync: Room Reservation ' . $final_status . ' — ' . $room_name;
    $body_html    = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
        <div style="background:#800000;padding:24px 28px;">
            <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync &middot; Room Reservation ' . htmlspecialchars($final_status) . '</h2>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;color:#333;font-size:.95rem;">
                Hello <strong>' . htmlspecialchars($faculty_name) . '</strong>,
            </p>
            <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
                Your room reservation request has been automatically processed.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;width:40%;">Reservation ID</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">#' . $reservation_id . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Room</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($room_name) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Date</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($res_date) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Time</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($start_time) . ' &ndash; ' . htmlspecialchars($end_time) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Purpose</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($purpose) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Status</td>
                    <td style="padding:10px 14px;color:' . $status_color . ';font-weight:700;">' . $status_icon . '</td>
                </tr>
                ' . ($final_reason ? '<tr style="background:#f9f5f5;"><td style="padding:10px 14px;color:#666;">Reason</td><td style="padding:10px 14px;color:#666;">' . htmlspecialchars($final_reason) . '</td></tr>' : '') . '
            </table>
        </div>
        <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
            PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus
        </div>
    </div>';
    sendPupSyncEmail($fac_email_row['email'], $faculty_name, $subject, $body_html);
}

echo json_encode([
    'success'        => true,
    'reservation_id' => $reservation_id,
    'status'         => $final_status,
    'reason'         => $final_reason,
    'room_name'      => $room_name,
]);
