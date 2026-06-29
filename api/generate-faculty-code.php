<?php
require_once __DIR__ . '/../config/security-headers.php';
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/csrf.php';
csrf_verify();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/db.php';
$conn = getDB();

$faculty_id   = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];

// ── Block generation if faculty has any overdue item ─────────────────────
$overdue_chk = $conn->prepare(
    "SELECT id FROM tbl_requests
      WHERE faculty_id = ? AND status = 'Overdue'
      LIMIT 1"
);
$overdue_chk->bind_param('s', $faculty_id);
$overdue_chk->execute();
$overdue_chk->store_result();
$has_overdue = $overdue_chk->num_rows > 0;
$overdue_chk->close();

if ($has_overdue) {
    $conn->close();
    echo json_encode([
        'error' => 'You have an overdue item. Please return it before generating a new borrowing code.'
    ]);
    exit();
}
// ── End overdue block ─────────────────────────────────────────────────────

// Remove any unused (not yet redeemed) codes for this faculty
$del = $conn->prepare("DELETE FROM tbl_faculty_codes WHERE faculty_id = ? AND is_used = 0");
$del->bind_param('s', $faculty_id);
$del->execute();
$del->close();

// Generate a unique code in the format: abc-123-xy4
function makeCode(): string
{
    $pool = 'abcdefghjkmnpqrstuvwxyz23456789'; // no confusing chars (0,1,i,l,o)
    $len  = strlen($pool);
    $code = '';
    for ($g = 0; $g < 3; $g++) {
        if ($g > 0) $code .= '-';
        for ($c = 0; $c < 3; $c++) {
            $code .= $pool[random_int(0, $len - 1)];
        }
    }
    return $code;
}

$code    = '';
$tries   = 0;
$unique  = false;
while (!$unique && $tries < 15) {
    $candidate = makeCode();
    $chk = $conn->prepare("SELECT id FROM tbl_faculty_codes WHERE code = ? LIMIT 1");
    $chk->bind_param('s', $candidate);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        $code = $candidate;
        $unique = true;
    }
    $chk->close();
    $tries++;
}

if (!$unique) {
    echo json_encode(['error' => 'Could not generate unique code. Try again.']);
    exit();
}

$ins = $conn->prepare("INSERT INTO tbl_faculty_codes (faculty_id, faculty_name, code) VALUES (?, ?, ?)");
$ins->bind_param('sss', $faculty_id, $faculty_name, $code);

if ($ins->execute()) {
    echo json_encode(['success' => true, 'code' => $code, 'created_at' => date('M j, Y g:i A')]);
} else {
    echo json_encode(['error' => 'Database error. Please try again.']);
}
$ins->close();
$conn->close();
