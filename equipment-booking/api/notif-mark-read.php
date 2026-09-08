<?php
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/notif-functions.php';
$conn = getDB();

$key = trim($_POST['key'] ?? '');
if ($key === '' || !preg_match('/^[a-z]+-\d+$/', $key)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification key.']);
    exit;
}

notif_set_state($conn, $key, true, null);

$notifications = notif_build_list($conn);
echo json_encode(['status' => 'success', 'unread_count' => notif_count_unread($notifications)]);
