<?php
declare(strict_types=1);
/**
 * get-item-code.php
 *
 * Returns (or lazily creates) the item code for a specific faculty + item.
 * Called when the faculty opens the item code chevron on an equipment card.
 *
 * POST fields: csrf_token, item_id
 * Returns: { status, code, item_name, is_used }
 */
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function gic_respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gic_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}
if (empty($_SESSION['faculty_id'])) {
    gic_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

$faculty_id   = (string)$_SESSION['faculty_id'];
$faculty_name = (string)($_SESSION['faculty_name'] ?? '');
$item_id      = (int)($_POST['item_id'] ?? 0);

if ($item_id <= 0) {
    gic_respond(422, ['status' => 'error', 'error' => 'Invalid item.']);
}

// Verify item exists and is not archived
$item_stmt = $conn->prepare(
    "SELECT item_id, item_name, quantity FROM tbl_inventory
      WHERE item_id = ? AND is_archived = 0 LIMIT 1"
);
if (!$item_stmt) { gic_respond(500, ['status' => 'error', 'error' => 'Server error.']); }
$item_stmt->bind_param('i', $item_id);
$item_stmt->execute();
$item = $item_stmt->get_result()->fetch_assoc();
$item_stmt->close();

if (!$item) {
    gic_respond(404, ['status' => 'error', 'error' => 'Item not found.']);
}

// Check if an existing code exists for this faculty + item
$chk = $conn->prepare(
    "SELECT id, code, is_used FROM tbl_faculty_item_codes
      WHERE faculty_id = ? AND item_id = ? LIMIT 1"
);
if (!$chk) { gic_respond(500, ['status' => 'error', 'error' => 'Server error.']); }
$chk->bind_param('si', $faculty_id, $item_id);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();
$chk->close();

if ($existing) {
    gic_respond(200, [
        'status'    => 'success',
        'code'      => (string)$existing['code'],
        'item_name' => (string)$item['item_name'],
        'is_used'   => (bool)$existing['is_used'],
        'quantity'  => (int)$item['quantity'],
    ]);
}

// No code exists yet — generate one
$code = gic_generate_unique_code($conn);

$ins = $conn->prepare(
    "INSERT INTO tbl_faculty_item_codes
        (faculty_id, faculty_name, item_name, item_id, code)
     VALUES (?, ?, ?, ?, ?)"
);
if (!$ins) { gic_respond(500, ['status' => 'error', 'error' => 'Server error.']); }
$ins->bind_param('sssis', $faculty_id, $faculty_name, $item['item_name'], $item_id, $code);
if (!$ins->execute()) {
    gic_respond(500, ['status' => 'error', 'error' => 'Could not generate code. Please try again.']);
}
$ins->close();

gic_respond(200, [
    'status'    => 'success',
    'code'      => $code,
    'item_name' => (string)$item['item_name'],
    'is_used'   => false,
    'quantity'  => (int)$item['quantity'],
]);

/**
 * Generates a 12-character alphanumeric code (A-Z, a-z, 0-9) that does not
 * exist anywhere in tbl_faculty_item_codes or tbl_faculty_codes.
 * Globally unique: never repeats across all faculty, all items, all time.
 */
function gic_generate_unique_code(mysqli $conn): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $len   = strlen($chars);
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }
        // Check both tables — item codes and faculty codes must never collide
        $c1 = $conn->prepare("SELECT 1 FROM tbl_faculty_item_codes WHERE code = ? LIMIT 1");
        $c2 = $conn->prepare("SELECT 1 FROM tbl_faculty_codes WHERE code = ? LIMIT 1");
        if (!$c1 || !$c2) continue;
        $c1->bind_param('s', $code); $c1->execute(); $c1->store_result();
        $c2->bind_param('s', $code); $c2->execute(); $c2->store_result();
        $taken = $c1->num_rows > 0 || $c2->num_rows > 0;
        $c1->close(); $c2->close();
        if (!$taken) return $code;
    }
    // Astronomically unlikely — 12-char code space has 58^12 ≈ 1.3×10^21 combinations
    error_log('[get-item-code] Failed to generate unique code after 20 attempts.');
    gic_respond(500, ['status' => 'error', 'error' => 'Could not generate a unique code. Please try again.']);
}
