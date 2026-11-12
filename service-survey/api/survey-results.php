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
ss_set_exception_handler('survey-results');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    ss_fail(401, 'Admin sign-in is required.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    ss_fail(405, 'Method not allowed.');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/core/admin-auth.php';
$conn = getDB();
$conn->set_charset('utf8mb4');
ss_ensure_schema($conn);

$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);
$offset = $offset === false || $offset === null ? 0 : max(0, min((int)$offset, 100000));
ss_json([
    'status' => 'success',
    'is_super_admin' => admin_is_super_admin($conn),
    'results' => ss_results($conn, $offset),
]);
