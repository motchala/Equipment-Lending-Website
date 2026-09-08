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

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/notif-functions.php';
$conn = getDB();

$notifications = notif_build_list($conn);
$unread        = notif_count_unread($notifications);

// Strip internal-only fields before sending to the client.
$out = array_map(function ($n) {
    unset($n['is_deleted']);
    return $n;
}, $notifications);

echo json_encode([
    'status'        => 'success',
    'notifications' => $out,
    'unread_count'  => $unread,
]);
