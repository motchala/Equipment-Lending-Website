<?php
declare(strict_types=1);
/**
 * submit-item-borrow.php
 *
 * Student submits the item-code borrow form.
 * - Item is pre-locked to the code's item
 * - borrow_date = today, return_date = today (same-day borrow)
 * - return_time stored in return_time column
 * - Marks code as used, auto-regenerates new code for next student
 * - Inserts into faculty notification feed
 *
 * POST fields (JSON): code_db_id, faculty_id, faculty_name,
 *                     student_name, student_id, item_name, item_id,
 *                     quantity, room, return_time (HH:MM)
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
$room         = trim((string)($body['room']         ?? ''));
$return_time  = trim((string)($body['return_time']  ?? ''));

if (!$code_db_id || !$faculty_id || !$student_name || !$student_id ||
    !$item_name || $item_id <= 0 || !$room || !$return_time) {
    echo json_encode(['error' => 'All fields are required.']);
    exit;
}

// Validate quantity
if ($quantity < 1) {
    echo json_encode(['error' => 'Quantity must be at least 1.']);
    exit;
}

// Validate return_time format HH:MM
if (!preg_match('/^\d{2}:\d{2}$/', $return_time)) {
    echo json_encode(['error' => 'Invalid return time.']);
    exit;
}

$today = date('Y-m-d');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/faculty-id.php';
require_once __DIR__ . '/../core/faculty-notif-functions.php';
require_once __DIR__ . '/../core/booking-schedule.php';

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

    // Check item still has enough stock
    $stock = $conn->prepare(
        "SELECT quantity FROM tbl_inventory WHERE item_id = ? AND is_archived = 0 LIMIT 1 FOR UPDATE"
    );
    if (!$stock) throw new RuntimeException('Could not check stock.');
    $stock->bind_param('i', $item_id);
    $stock->execute();
    $stock_row = $stock->get_result()->fetch_assoc();
    $stock->close();

    if (!$stock_row || (int)$stock_row['quantity'] < $quantity) {
        $conn->rollback();
        echo json_encode(['error' => 'Not enough stock available. Only ' . ((int)($stock_row['quantity'] ?? 0)) . ' unit(s) remaining.']);
        exit;
    }

    // Generate return token
    $return_token = bin2hex(random_bytes(32));

    // Insert request — borrow_date and return_date both = today (same-day)
    // return_time stored in the return_time column (BookingSchedule column)
    $ins = $conn->prepare(
        "INSERT INTO tbl_requests
            (faculty_name, faculty_id, equipment_name, instructor, room,
             borrow_date, return_date, borrow_qty, return_time,
             status, request_date, return_token,
             submitted_by_name, submitted_by_id, submitted_as)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW(), ?, ?, ?, 'student')"
    );
    if (!$ins) throw new RuntimeException('Could not save request.');
    $ins->bind_param(
        'sssssssissss',
        $faculty_name, $faculty_id, $item_name, $faculty_name, $room,
        $today, $today, $quantity, $return_time,
        $return_token, $student_name, $student_id
    );
    if (!$ins->execute()) throw new RuntimeException('Insert failed: ' . $ins->error);
    $request_id = (int)$conn->insert_id;
    $ins->close();

    // Decrement inventory
    $dec = $conn->prepare(
        "UPDATE tbl_inventory SET quantity = quantity - ?
          WHERE item_id = ? AND is_archived = 0 AND quantity >= ?"
    );
    if (!$dec) throw new RuntimeException('Could not update stock.');
    $dec->bind_param('iii', $quantity, $item_id, $quantity);
    $dec->execute();
    if ($dec->affected_rows !== 1) throw new RuntimeException('Stock update failed.');
    $dec->close();

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
    $message = $student_name . ' (' . $student_id . ') borrowed ' . $qty_str
        . ' of ' . $item_name . ' using your item code today. '
        . 'Return expected by ' . $return_time . '. Room/Location: ' . $room . '.';
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
        'return_token' => $return_token,
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
