<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$body = json_decode(file_get_contents('php://input'), true);
$code         = trim($body['code']         ?? '');
$student_name = trim($body['student_name'] ?? '');
$student_id   = trim($body['student_id']   ?? '');

if (!$code || !$student_name || !$student_id) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

require_once __DIR__ . '/db.php';
$conn = getDB();

// Look up the code
$stmt = $conn->prepare(
    "SELECT id, faculty_id, faculty_name, is_used
       FROM tbl_faculty_codes
      WHERE code = ?
      LIMIT 1"
);
$stmt->bind_param('s', $code);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Invalid faculty code. Please check the code and try again.']);
    exit();
}
if ($row['is_used']) {
    echo json_encode(['error' => 'This code has already been used. Ask your faculty for a new code.']);
    exit();
}

// ── Block if faculty has any overdue item ─────────────────────────────────
$overdue_chk = $conn->prepare(
    "SELECT id FROM tbl_requests
      WHERE faculty_id = ? AND status = 'Overdue'
      LIMIT 1"
);
$overdue_chk->bind_param('s', $row['faculty_id']);
$overdue_chk->execute();
$overdue_chk->store_result();
$has_overdue = $overdue_chk->num_rows > 0;
$overdue_chk->close();

if ($has_overdue) {
    $conn->close();
    echo json_encode([
        'error' => 'Your faculty advisor currently has an overdue item. Borrowing is not allowed until it is returned. Please coordinate with ' . htmlspecialchars($row['faculty_name']) . '.'
    ]);
    exit();
}
// ── End overdue block ─────────────────────────────────────────────────────


// ── Email 1: Notify faculty that student is now on the borrow form ────────
$fac_mail_stmt = $conn->prepare("SELECT email, fullname FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac_mail_stmt->bind_param('s', $row['faculty_id']);
$fac_mail_stmt->execute();
$fac_mail_row = $fac_mail_stmt->get_result()->fetch_assoc();
$fac_mail_stmt->close();

if ($fac_mail_row && !empty($fac_mail_row['email'])) {
    require_once __DIR__ . '/mailer.php';
    $email_subject = 'PUPSync: ' . $student_name . ' is about to borrow equipment';
    $email_body = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
        <div style="background:#800000;padding:24px 28px;">
            <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync · Equipment Borrowing Alert</h2>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;color:#333;font-size:.95rem;">
                Hello <strong>' . htmlspecialchars($fac_mail_row['fullname']) . '</strong>,
            </p>
            <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
                A student you authorized is currently filling out the borrow equipment form using your code.
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
                    <td style="padding:10px 14px;color:#e65100;font-weight:700;">Form opened — not yet submitted</td>
                </tr>
            </table>
            <p style="margin:20px 0 0;font-size:.8rem;color:#999;">
                If you did not authorize this student, your code may have been shared without your knowledge.
                Contact the admin office immediately.
            </p>
        </div>
        <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
            PUPSync · Polytechnic University of the Philippines – Biñan Campus
        </div>
    </div>';
    sendPupSyncEmail($fac_mail_row['email'], $fac_mail_row['fullname'], $email_subject, $email_body);
}
// ── End Email 1 ───────────────────────────────────────────────────────────


// Code is valid — fetch available inventory
$inv = $conn->query(
    "SELECT item_id, item_name, category, quantity, image_path
       FROM tbl_inventory
      WHERE is_archived = 0 AND quantity > 0
      ORDER BY item_name ASC"
);
$inventory = [];
while ($item = $inv->fetch_assoc()) $inventory[] = $item;

$conn->close();

echo json_encode([
    'valid'        => true,
    'faculty_id'   => $row['faculty_id'],
    'faculty_name' => $row['faculty_name'],
    'code_db_id'   => $row['id'],
    'inventory'    => $inventory,
]);
