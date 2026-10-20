<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$faculty_id = (string)$_SESSION['faculty_id'];
$RATE_LIMIT = 20;

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

// Ensure the log table exists (same self-healing guard as chat.php)
$conn->query("
    CREATE TABLE IF NOT EXISTS `tbl_ai_chat_log` (
        `id`         int(11)     NOT NULL AUTO_INCREMENT,
        `faculty_id` varchar(50) NOT NULL,
        `sent_at`    datetime    NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_faculty_sent` (`faculty_id`, `sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tbl_ai_chat_log
      WHERE faculty_id = ? AND sent_at >= (NOW() - INTERVAL 1 HOUR)"
);
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$used = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

$remaining = max(0, $RATE_LIMIT - $used);

http_response_code(200);
echo json_encode(['remaining' => $remaining]);
