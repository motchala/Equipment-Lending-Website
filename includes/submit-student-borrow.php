<?php
require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/session-config.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$body = json_decode(file_get_contents('php://input'), true);

$code_db_id    = intval($body['code_db_id']    ?? 0);
$faculty_id    = trim($body['faculty_id']      ?? '');
$faculty_name  = trim($body['faculty_name']    ?? '');
$student_name  = trim($body['student_name']    ?? '');
$student_id    = trim($body['student_id']      ?? '');
$equipment     = trim($body['equipment_name']  ?? '');
$room          = trim($body['room']            ?? '');
$borrow_date   = trim($body['borrow_date']     ?? '');
$return_date   = trim($body['return_date']     ?? '');

if (
    !$code_db_id || !$faculty_id || !$student_name || !$student_id ||
    !$equipment || !$room || !$borrow_date || !$return_date
) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

$today = date('Y-m-d');
if ($borrow_date < $today) {
    echo json_encode(['error' => 'Borrow date cannot be in the past.']);
    exit();
}
if ($return_date < $borrow_date) {
    echo json_encode(['error' => 'Return date cannot be before borrow date.']);
    exit();
}

require_once __DIR__ . '/db.php';
$conn = getDB();

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

// ── Check equipment is still in stock ────────────────────────────────────
$stock = $conn->prepare(
    "SELECT quantity FROM tbl_inventory WHERE item_name = ? AND is_archived = 0 LIMIT 1"
);
$stock->bind_param('s', $equipment);
$stock->execute();
$stock_row = $stock->get_result()->fetch_assoc();
$stock->close();

if (!$stock_row || $stock_row['quantity'] < 1) {
    $conn->close();
    echo json_encode(['error' => 'Sorry, that item just went out of stock. Please go back and choose another.']);
    exit();
}

// ── Generate return token ─────────────────────────────────────────────────
$return_token = bin2hex(random_bytes(32));

// ── Insert as Approved (faculty code is the authorization) ────────────────
$ins = $conn->prepare(
    "INSERT INTO tbl_requests
        (faculty_name, faculty_id, equipment_name, instructor, room,
         borrow_date, return_date, status, request_date,
         return_token, submitted_by_name, submitted_by_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'Approved', NOW(), ?, ?, ?)"
);
$ins->bind_param(
    'ssssssssss',
    $faculty_name,
    $faculty_id,
    $equipment,
    $faculty_name,
    $room,
    $borrow_date,
    $return_date,
    $return_token,
    $student_name,
    $student_id
);

if (!$ins->execute()) {
    $ins->close();
    $conn->close();
    echo json_encode(['error' => 'Failed to save request. Please try again.']);
    exit();
}
$request_id = $conn->insert_id;
$ins->close();

// ── Decrement inventory quantity ──────────────────────────────────────────
$dec = $conn->prepare(
    "UPDATE tbl_inventory SET quantity = quantity - 1
      WHERE item_name = ? AND is_archived = 0 AND quantity > 0"
);
$dec->bind_param('s', $equipment);
$dec->execute();
$dec->close();

// ── Mark code as used ─────────────────────────────────────────────────────
$upd = $conn->prepare(
    "UPDATE tbl_faculty_codes
        SET is_used = 1, used_by_name = ?, used_by_id = ?, used_at = NOW()
      WHERE id = ?"
);
$upd->bind_param('ssi', $student_name, $student_id, $code_db_id);
$upd->execute();
$upd->close();

// ── Email 2: Notify faculty that borrowing was successfully completed ──────
$fac_mail_stmt2 = $conn->prepare("SELECT email, fullname FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac_mail_stmt2->bind_param('s', $faculty_id);
$fac_mail_stmt2->execute();
$fac_mail_row2 = $fac_mail_stmt2->get_result()->fetch_assoc();
$fac_mail_stmt2->close();

if ($fac_mail_row2 && !empty($fac_mail_row2['email'])) {
    require_once __DIR__ . '/mailer.php';
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
                    <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($student_name) . ' &nbsp;·&nbsp; ' . htmlspecialchars($student_id) . '</td>
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

echo json_encode(['success' => true, 'request_id' => $request_id, 'return_token' => $return_token]);
