<?php
declare(strict_types=1);
/**
 * submit-item-borrow.php
 *
 * Student submits the item-code borrow form.
 * - Item is pre-locked to the code's item
 * - Uses a validated today / future borrowing window
 * - Marks code as used, auto-regenerates new code for next student
 * - Inserts into faculty notification feed
 *
 * POST fields (JSON): code_db_id, faculty_id, faculty_name,
 *                     student_name, optional student_id, item_name, item_id,
 *                     quantity, room_id, booking_mode, borrow_date,
 *                     start_time (HH:MM), duration (hours)
 * Returns: { success, request_id, return_token } or { error }
 */
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

$body = json_decode((string)file_get_contents('php://input'), true);

$code_db_id   = (int)($body['code_db_id']   ?? 0);
$faculty_id   = trim((string)($body['faculty_id']   ?? ''));
$faculty_name = trim((string)($body['faculty_name'] ?? ''));
$student_name = trim((string)($body['student_name'] ?? ''));
$student_id   = trim((string)($body['student_id']   ?? ''));
$item_name    = trim((string)($body['item_name']    ?? ''));
$item_id      = (int)($body['item_id']    ?? 0);
$quantity     = (int)($body['quantity']   ?? 1);
$room_id      = (int)($body['room_id']      ?? 0);
$booking_mode = trim((string)($body['booking_mode'] ?? ''));
$borrow_date  = trim((string)($body['borrow_date']  ?? ''));
$start_time   = trim((string)($body['start_time']   ?? ''));
$duration     = (int)($body['duration'] ?? 0);

if (!$code_db_id || !$faculty_id || !$student_name ||
    !$item_name || $item_id <= 0 || $room_id <= 0 ||
    !$booking_mode || !$borrow_date || !$start_time || !$duration) {
    echo json_encode(['error' => 'All fields are required.']);
    exit;
}

// Validate quantity
if ($quantity < 1) {
    echo json_encode(['error' => 'Quantity must be at least 1.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../shared/core/faculty-id.php';
require_once __DIR__ . '/../../notifications/core/faculty-data.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../../shared/core/student-room-options.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

// Ensure borrow_qty, return_time and other scheduling columns exist on tbl_requests
BookingSchedule::ensureSchema($conn);

try {
    $conn->begin_transaction();

    // Re-verify code is still unused and belongs to this faculty + item (race-condition guard)
    $chk = $conn->prepare(
        "SELECT id, faculty_id, item_name, item_id
           FROM tbl_faculty_item_codes
          WHERE id = ? AND faculty_id = ? AND item_id = ? AND is_used = 0
          LIMIT 1 FOR UPDATE"
    );
    if (!$chk) throw new RuntimeException('Could not verify the code.');
    $chk->bind_param('isi', $code_db_id, $faculty_id, $item_id);
    $chk->execute();
    $code_row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$code_row) {
        $conn->rollback();
        echo json_encode(['error' => 'This code was just used by someone else. Ask your faculty for a new code.']);
        exit;
    }

    $selected_room = student_room_find($conn, $room_id);
    if (!$selected_room) {
        $conn->rollback();
        echo json_encode(['error' => 'That room is no longer available. Please choose another room.']);
        exit;
    }
    $room = $selected_room['room_name'];

    $schedule = BookingSchedule::parse($conn, [
        'booking_mode' => $booking_mode,
        'borrow_date' => $borrow_date,
        'start_time' => $start_time,
        'duration' => $duration,
    ], $item_name, $quantity);
    if (!$schedule['ok']) {
        $conn->rollback();
        echo json_encode(['error' => $schedule['error']]);
        exit;
    }
    $booking = $schedule['data'];
    if (BookingSchedule::freeUnits($conn, $item_name, $booking['start_ts'], $booking['end_ts']) < $quantity) {
        $conn->rollback();
        echo json_encode(['error' => 'That equipment is already booked for the selected time. Please choose another time.']);
        exit;
    }
    $roomConflict = BookingSchedule::roomConflictMessage($conn, $room_id, $booking['start_ts'], $booking['end_ts'], $faculty_id);
    if ($roomConflict) {
        $conn->rollback();
        echo json_encode(['error' => $roomConflict]);
        exit;
    }
    $borrow_date = $booking['borrow_date'];
    $return_date = $booking['return_date'];
    $borrow_time = $booking['borrow_time'];
    $return_time = $booking['return_time'];
    $booking_mode = $booking['mode'];

    // Insert as Waiting — tokens and inventory decrement happen at approval time
    // (auto-approve-tick.php or admin-override.php), not at insert time.
    $ins = $conn->prepare(
        "INSERT INTO tbl_requests
            (faculty_name, faculty_id, equipment_name, instructor, room, room_id,
             borrow_date, return_date, borrow_time, return_time, borrow_qty, booking_mode,
             stock_applied,
             status, request_date,
             submitted_by_name, submitted_by_id, submitted_as)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), ?, ?, 'student')"
    );
    if (!$ins) throw new RuntimeException('Could not save request.');
    $ins->bind_param(
        'sssssissssisss',
        $faculty_name, $faculty_id, $item_name, $faculty_name, $room, $room_id,
        $borrow_date, $return_date, $borrow_time, $return_time, $quantity, $booking_mode,
        $student_name, $student_id
    );
    if (!$ins->execute()) throw new RuntimeException('Insert failed: ' . $ins->error);
    $request_id = (int)$conn->insert_id;
    $ins->close();

    // Mark code as used
    $mark = $conn->prepare(
        "UPDATE tbl_faculty_item_codes
            SET is_used = 1, used_by_name = ?, used_by_id = ?, used_at = NOW()
          WHERE id = ?"
    );
    if (!$mark) throw new RuntimeException('Could not mark code.');
    $mark->bind_param('ssi', $student_name, $student_id, $code_db_id);
    $mark->execute();
    $mark->close();

    // Auto-regenerate new code for this faculty + item
    $new_code = sib_generate_unique_code($conn);
    $regen = $conn->prepare(
        "UPDATE tbl_faculty_item_codes
            SET code = ?, is_used = 0, used_by_name = NULL,
                used_by_id = NULL, used_at = NULL, created_at = NOW()
          WHERE id = ?"
    );
    if ($regen) {
        $regen->bind_param('si', $new_code, $code_db_id);
        $regen->execute();
        $regen->close();
    }

    $conn->commit();

    // Notify faculty via their notification feed
    fnotif_ensure_admin_notice_table($conn);
    $title   = 'Item Code Used: ' . $item_name;
    $qty_str = $quantity . ' unit' . ($quantity === 1 ? '' : 's');
    $student_label = $student_name . ($student_id !== '' ? ' (' . $student_id . ')' : '');
    $message = $student_label . ' requested ' . $qty_str
        . ' of ' . $item_name . ' using your item code for ' . $borrow_date . ' from '
        . substr($borrow_time, 0, 5) . ' to ' . substr($return_time, 0, 5)
        . '. Room/Location: ' . $room . '.';
    $admin_name = 'PUPSync System';
    $admin_id   = 0;
    $notice = $conn->prepare(
        "INSERT INTO tbl_faculty_admin_notice
            (faculty_id, request_id, admin_id, admin_name, title, message)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if ($notice) {
        $notice->bind_param('siisss', $faculty_id, $request_id, $admin_id, $admin_name, $title, $message);
        $notice->execute();
        $notice->close();
    }

    echo json_encode([
        'success'      => true,
        'request_id'   => $request_id,
        'borrow_date'  => $borrow_date,
        'return_date'  => $return_date,
        'return_time'  => $return_time,
        'return_token' => null,
        'claim_token'  => $claim_token,
    ]);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('[submit-item-borrow] ' . $e->getMessage());
    echo json_encode(['error' => 'Could not save the request. Please try again.']);
}

function sib_generate_unique_code(mysqli $conn): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $len   = strlen($chars);
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }
        $c1 = $conn->prepare("SELECT 1 FROM tbl_faculty_item_codes WHERE code = ? LIMIT 1");
        $c2 = $conn->prepare("SELECT 1 FROM tbl_faculty_codes WHERE code = ? LIMIT 1");
        if (!$c1 || !$c2) continue;
        $c1->bind_param('s', $code); $c1->execute(); $c1->store_result();
        $c2->bind_param('s', $code); $c2->execute(); $c2->store_result();
        $taken = $c1->num_rows > 0 || $c2->num_rows > 0;
        $c1->close(); $c2->close();
        if (!$taken) return $code;
    }
    return bin2hex(random_bytes(6)); // last-resort fallback
}
