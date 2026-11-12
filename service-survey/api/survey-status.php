<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../core/survey.php';
ss_set_exception_handler('survey-status');

if (empty($_SESSION['faculty_id'])) {
    ss_fail(401, 'Faculty sign-in is required.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    ss_fail(405, 'Method not allowed.');
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');
ss_ensure_schema($conn);

$config = ss_config($conn);
$pending = null;
if ($config['enabled']) {
    $facultyId = (string)$_SESSION['faculty_id'];
    foreach (ss_completed_transactions($conn, $facultyId) as $transaction) {
        if (ss_is_consumed($conn, $transaction['type'], $transaction['transaction_key'])) {
            continue;
        }
        $pending = [
            'key' => ss_token($transaction['type'], $transaction['transaction_key'], $facultyId),
            'type' => $transaction['type'],
            'label' => $transaction['label'],
        ];
        break;
    }
}

ss_json([
    'status' => 'success',
    'enabled' => $config['enabled'],
    'pending' => $pending,
    'questions' => ss_questions($conn),
    'comment_on' => $config['comment_enabled'],
    'comment_enabled' => $config['comment_enabled'],
    'comment_max' => SS_COMMENT_MAX,
]);
