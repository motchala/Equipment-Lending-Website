<?php
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

// JSON normally; multipart when the student attached a request letter (later-date borrow).
$is_multipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
$body = $is_multipart ? $_POST : json_decode(file_get_contents('php://input'), true);

$code_db_id    = intval($body['code_db_id']    ?? 0);
$faculty_id    = trim($body['faculty_id']      ?? '');
$faculty_name  = trim($body['faculty_name']    ?? '');
$student_name  = trim($body['student_name']    ?? '');
$student_id    = trim($body['student_id']      ?? '');
$equipment     = trim($body['equipment_name']  ?? '');
$quantity      = max(1, (int)($body['quantity'] ?? 1));
$room_id       = intval($body['room_id']       ?? 0);
$booking_mode  = trim($body['booking_mode']    ?? '');
$borrow_date   = trim($body['borrow_date']     ?? '');
$start_time    = trim($body['start_time']      ?? '');
$duration      = (int)($body['duration']       ?? 0);

if (
    !$code_db_id || !$faculty_id || !$student_name ||
    !$equipment || $room_id <= 0 || !$booking_mode || !$borrow_date || !$start_time || !$duration
) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../shared/core/student-room-options.php';
require_once __DIR__ . '/../core/booking-schedule.php';

$conn = getDB();
$conn->set_charset('utf8mb4');
BookingSchedule::ensureSchema($conn);
try {
    $selected_room = student_room_find($conn, $room_id);
} catch (RuntimeException $e) {
    error_log('[submit-student-borrow] ' . $e->getMessage());
    $conn->close();
    http_response_code(500);
    echo json_encode(['error' => 'Could not verify the selected room. Please try again.']);
    exit();
}
if (!$selected_room) {
    $conn->close();
    echo json_encode(['error' => 'That room is no longer available. Please choose another room.']);
    exit();
}
$room = $selected_room['room_name'];

// ── Re-verify code is still unused (race-condition guard) ─────────────────
$chk = $conn->prepare("SELECT id FROM tbl_faculty_codes WHERE id = ? AND is_used = 0 LIMIT 1");
$chk->bind_param('i', $code_db_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close();
    $conn->close();
    echo json_encode(['error' => 'This code was just used by someone else. Ask your faculty for a new code.']);
    exit();
}
$chk->close();

// ── Validate the selected borrowing window and inventory ────────────────
$schedule = BookingSchedule::parse($conn, [
    'booking_mode' => $booking_mode,
    'borrow_date' => $borrow_date,
    'start_time' => $start_time,
    'duration' => $duration,
], $equipment, $quantity);
if (!$schedule['ok']) {
    $conn->close();
    echo json_encode(['error' => $schedule['error']]);
    exit();
}
$booking = $schedule['data'];
if (BookingSchedule::freeUnits($conn, $equipment, $booking['start_ts'], $booking['end_ts']) < $quantity) {
    $conn->close();
    echo json_encode(['error' => 'That equipment is already booked for the selected time. Please choose another time.']);
    exit();
}
$roomConflict = BookingSchedule::roomConflictMessage($conn, $room_id, $booking['start_ts'], $booking['end_ts'], $faculty_id);
if ($roomConflict) {
    $conn->close();
    echo json_encode(['error' => $roomConflict]);
    exit();
}
$borrow_date = $booking['borrow_date'];
$return_date = $booking['return_date'];
$borrow_time = $booking['borrow_time'];
$return_time = $booking['return_time'];
$booking_mode = $booking['mode'];

// borrow_qty must exist: stock is decremented by $quantity below, and a return restores
// stock from borrow_qty — so it has to hold the real quantity (same as submit-item-borrow.php).
require_once __DIR__ . '/../core/booking-schedule.php';
BookingSchedule::ensureSchema($conn);

// ── Optional request letter: recommended for a LATER borrow date, ignored for a same-day one ──
require_once __DIR__ . '/../../shared/core/document-helpers.php';
$letter_path = null;
if ($booking_mode === 'future' && $is_multipart && isset($_FILES['document'])) {
    $letter_err  = null;
    $letter_path = ps_store_request_letter($_FILES['document'], 'uploads/request_letters', $student_id, $letter_err);
    if ($letter_path === null && $letter_err !== null) {
        $conn->close();
        echo json_encode(['error' => $letter_err]);
        exit();
    }
}

// ── Insert as Waiting — the auto-approve tick will approve after buffer expires ──
// Tokens and inventory decrement happen at approval time (auto-approve-tick.php
// or admin-override.php), not at insert time.
$ins = $conn->prepare(
    "INSERT INTO tbl_requests
        (faculty_name, faculty_id, equipment_name, instructor, room, room_id,
            borrow_date, return_date, borrow_time, return_time, borrow_qty, booking_mode,
            stock_applied, status, request_date,
            submitted_by_name, submitted_by_id, submitted_as)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), ?, ?, 'student')"
);
$ins->bind_param(
    'sssssissssisss',
    $faculty_name,
    $faculty_id,
    $equipment,
    $faculty_name,
    $room,
    $room_id,
    $borrow_date,
    $return_date,
    $borrow_time,
    $return_time,
    $quantity,
    $booking_mode,
    $student_name,
    $student_id
);

if (!$ins->execute()) {
    $ins->close();
    $conn->close();
    if ($letter_path !== null) {
        @unlink(dirname(__DIR__, 2) . '/' . $letter_path);
    }
    echo json_encode(['error' => 'Failed to save request. Please try again.']);
    exit();
}
$request_id = $conn->insert_id;
$ins->close();

if ($letter_path !== null) {
    $doc = $conn->prepare('UPDATE tbl_requests SET document_path = ? WHERE id = ?');
    if ($doc) {
        $doc->bind_param('si', $letter_path, $request_id);
        $doc->execute();
        $doc->close();
    }
}

// ── Mark code as used — only on the last item of a multi-item submission ──────
// JS sends is_last_item=true on the final request. For intermediate items the
// code stays available so the next item in the sequence can still be verified.
$is_last_item = filter_var($body['is_last_item'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($is_last_item) {
    $upd = $conn->prepare(
        "UPDATE tbl_faculty_codes
            SET is_used = 1, used_by_name = ?, used_by_id = ?, used_at = NOW()
          WHERE id = ?"
    );
    $upd->bind_param('ssi', $student_name, $student_id, $code_db_id);
    $upd->execute();
    $upd->close();
}


// ── Email 2: Notify faculty that borrowing was successfully completed ──────
$fac_mail_stmt2 = $conn->prepare("SELECT email, fullname FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac_mail_stmt2->bind_param('s', $faculty_id);
$fac_mail_stmt2->execute();
$fac_mail_row2 = $fac_mail_stmt2->get_result()->fetch_assoc();
$fac_mail_stmt2->close();

if ($fac_mail_row2 && !empty($fac_mail_row2['email'])) {
    require_once __DIR__ . '/../../shared/core/mailer.php';
    $email_subject2 = 'PUPSync: Borrowing Confirmed — ' . $equipment;
    $email_body2 = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
        <div style="background:#800000;padding:24px 28px;">
            <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync · Borrowing Confirmed</h2>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;color:#333;font-size:.95rem;">
                Hello <strong>' . htmlspecialchars($fac_mail_row2['fullname']) . '</strong>,
            </p>
            <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
                The equipment borrowing request you authorized has been successfully submitted and approved.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;width:40%;">Request ID</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">#' . $request_id . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Student</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($student_name) . ($student_id !== '' ? ' &nbsp;·&nbsp; ' . htmlspecialchars($student_id) : '') . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Equipment</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($equipment) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Room</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($room) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Borrow Date</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($borrow_date) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;color:#666;">Return By</td>
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($return_date) . '</td>
                </tr>
                <tr style="background:#f9f5f5;">
                    <td style="padding:10px 14px;color:#666;">Status</td>
                    <td style="padding:10px 14px;color:#2e7d32;font-weight:700;">✓ Approved</td>
                </tr>
            </table>
            <p style="margin:20px 0 0;font-size:.8rem;color:#999;">
                The student should present their QR receipt to the admin office to physically claim the equipment.
            </p>
        </div>
        <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
            PUPSync · Polytechnic University of the Philippines – Biñan Campus
        </div>
    </div>';
    sendPupSyncEmail($fac_mail_row2['email'], $fac_mail_row2['fullname'], $email_subject2, $email_body2);
}
// ── End Email 2 ───────────────────────────────────────────────────────────

$conn->close();

echo json_encode([
    'success'      => true,
    'request_id'   => $request_id,
    'return_token' => null,
    'claim_token'  => null,
]);
