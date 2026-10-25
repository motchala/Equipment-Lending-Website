<?php
declare(strict_types=1);
/**
 * verify-item-code.php
 *
 * Student enters a code. The system detects it is an item code (12-char,
 * no hyphens) and routes here automatically from the unified verify endpoint.
 * No auth required — public endpoint, rate-limited by IP.
 *
 * POST fields (JSON): code, student_name, student_id
 * Returns: { valid, faculty_id, faculty_name, item_name, item_id,
 *            quantity, code_db_id } or { error }
 */
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

$body = json_decode((string)file_get_contents('php://input'), true);
$code         = trim((string)($body['code']         ?? ''));
$student_name = trim((string)($body['student_name'] ?? ''));
$student_id   = trim((string)($body['student_id']   ?? ''));

if (!$code || !$student_name || !$student_id) {
    http_response_code(422);
    echo json_encode(['error' => 'All fields are required.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

// ── Rate limit: 10 failed attempts per IP per 15 minutes ─────────────────
$ip         = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$window_sec = 900; // 15 minutes
$max_fails  = 10;

// Self-heal rate limit table
$conn->query("
    CREATE TABLE IF NOT EXISTS `tbl_item_code_attempts` (
        `id`           int(11)     NOT NULL AUTO_INCREMENT,
        `ip_address`   varchar(45) NOT NULL,
        `fail_count`   smallint(5) NOT NULL DEFAULT 0,
        `window_start` datetime    NOT NULL DEFAULT current_timestamp(),
        `locked_until` datetime    DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ip` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$rl = $conn->prepare(
    "SELECT fail_count, locked_until FROM tbl_item_code_attempts
      WHERE ip_address = ? LIMIT 1"
);
$rl->bind_param('s', $ip);
$rl->execute();
$rl_row = $rl->get_result()->fetch_assoc();
$rl->close();

if ($rl_row) {
    if ($rl_row['locked_until'] !== null && strtotime($rl_row['locked_until']) > time()) {
        $mins = (int)ceil((strtotime($rl_row['locked_until']) - time()) / 60);
        http_response_code(429);
        echo json_encode(['error' => "Too many failed attempts. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.' ]);
        exit;
    }
    // Reset window if it expired
    if (strtotime($rl_row['locked_until'] ?? '1970-01-01') <= time()) {
        $conn->query("UPDATE tbl_item_code_attempts SET fail_count = 0, locked_until = NULL WHERE ip_address = '" . $conn->real_escape_string($ip) . "'");
        $rl_row['fail_count'] = 0;
    }
}

// ── Look up the code ──────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT ic.id, ic.faculty_id, ic.faculty_name, ic.item_name,
            ic.item_id, ic.is_used,
            inv.quantity
       FROM tbl_faculty_item_codes ic
       JOIN tbl_inventory inv ON inv.item_id = ic.item_id AND inv.is_archived = 0
      WHERE ic.code = ? LIMIT 1"
);
$stmt->bind_param('s', $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    // Increment fail counter
    vic_record_fail($conn, $ip, (int)($rl_row['fail_count'] ?? 0), $max_fails, $window_sec);
    echo json_encode(['error' => 'Invalid code. Please check the code and try again.']);
    exit;
}

if ($row['is_used']) {
    vic_record_fail($conn, $ip, (int)($rl_row['fail_count'] ?? 0), $max_fails, $window_sec);
    echo json_encode(['error' => 'This item code has already been used. Ask your faculty for a new one.']);
    exit;
}

// Check faculty not overdue
$od = $conn->prepare(
    "SELECT id FROM tbl_requests WHERE faculty_id = ? AND status = 'Overdue' LIMIT 1"
);
$od->bind_param('s', $row['faculty_id']);
$od->execute();
$od->store_result();
$has_overdue = $od->num_rows > 0;
$od->close();

if ($has_overdue) {
    echo json_encode(['error' => htmlspecialchars($row['faculty_name']) .
        ' currently has an overdue item. Borrowing is not available until it is returned.']);
    exit;
}

if ((int)$row['quantity'] <= 0) {
    echo json_encode(['error' => 'Sorry, ' . htmlspecialchars($row['item_name']) . ' is currently out of stock.']);
    exit;
}

// Success — reset fail counter
vic_reset_fails($conn, $ip);

echo json_encode([
    'valid'        => true,
    'faculty_id'   => (string)$row['faculty_id'],
    'faculty_name' => (string)$row['faculty_name'],
    'item_name'    => (string)$row['item_name'],
    'item_id'      => (int)$row['item_id'],
    'quantity'     => (int)$row['quantity'],
    'code_db_id'   => (int)$row['id'],
]);

function vic_record_fail(mysqli $conn, string $ip, int $current_fails, int $max, int $window): void
{
    $new_fails = $current_fails + 1;
    $locked    = null;
    if ($new_fails >= $max) {
        $locked = date('Y-m-d H:i:s', time() + $window);
        $new_fails = 0; // reset counter after lockout
    }
    $conn->query(
        "INSERT INTO tbl_item_code_attempts (ip_address, fail_count, window_start, locked_until)
         VALUES ('" . $conn->real_escape_string($ip) . "', {$new_fails}, NOW(), " .
        ($locked ? "'" . $conn->real_escape_string($locked) . "'" : 'NULL') . ")
         ON DUPLICATE KEY UPDATE
           fail_count   = {$new_fails},
           locked_until = " . ($locked ? "'" . $conn->real_escape_string($locked) . "'" : 'NULL')
    );
}

function vic_reset_fails(mysqli $conn, string $ip): void
{
    $conn->query(
        "UPDATE tbl_item_code_attempts
            SET fail_count = 0, locked_until = NULL
          WHERE ip_address = '" . $conn->real_escape_string($ip) . "'"
    );
}
