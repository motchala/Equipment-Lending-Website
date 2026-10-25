<?php
declare(strict_types=1);
/**
 * regenerate-item-code.php
 *
 * Invalidates the current item code for a faculty + item and issues a new one.
 * Only the faculty member who owns the code can regenerate it.
 *
 * POST fields: csrf_token, item_id
 * Returns: { status, code }
 */
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function ric_respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ric_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}
if (empty($_SESSION['faculty_id'])) {
    ric_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

$faculty_id = (string)$_SESSION['faculty_id'];
$item_id    = (int)($_POST['item_id'] ?? 0);

if ($item_id <= 0) {
    ric_respond(422, ['status' => 'error', 'error' => 'Invalid item.']);
}

// Verify ownership — must be this faculty's code
$own = $conn->prepare(
    "SELECT id FROM tbl_faculty_item_codes
      WHERE faculty_id = ? AND item_id = ? LIMIT 1"
);
if (!$own) { ric_respond(500, ['status' => 'error', 'error' => 'Server error.']); }
$own->bind_param('si', $faculty_id, $item_id);
$own->execute();
$own->store_result();
if ($own->num_rows === 0) {
    $own->close();
    ric_respond(404, ['status' => 'error', 'error' => 'Code not found.']);
}
$own->close();

$new_code = ric_generate_unique_code($conn);

$upd = $conn->prepare(
    "UPDATE tbl_faculty_item_codes
        SET code = ?, is_used = 0, used_by_name = NULL,
            used_by_id = NULL, used_at = NULL, created_at = NOW()
      WHERE faculty_id = ? AND item_id = ?"
);
if (!$upd) { ric_respond(500, ['status' => 'error', 'error' => 'Server error.']); }
$upd->bind_param('ssi', $new_code, $faculty_id, $item_id);
if (!$upd->execute() || $upd->affected_rows !== 1) {
    ric_respond(500, ['status' => 'error', 'error' => 'Could not regenerate code. Please try again.']);
}
$upd->close();

ric_respond(200, ['status' => 'success', 'code' => $new_code]);

function ric_generate_unique_code(mysqli $conn): string
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
    error_log('[regenerate-item-code] Failed after 20 attempts.');
    ric_respond(500, ['status' => 'error', 'error' => 'Could not generate a unique code. Please try again.']);
}
