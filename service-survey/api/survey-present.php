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
ss_set_exception_handler('survey-present');

if (empty($_SESSION['faculty_id'])) {
    ss_fail(401, 'Faculty sign-in is required.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_fail(405, 'Method not allowed.');
}

$body = ss_json_body();
ss_verify_csrf($body);
$token = (string)($body['key'] ?? '');
if ($token === '') {
    ss_fail(400, 'The survey transaction is missing.');
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');
ss_ensure_schema($conn);
if (!ss_config($conn)['enabled']) {
    ss_fail(409, 'The survey is turned off.', 'disabled');
}

$facultyId = (string)$_SESSION['faculty_id'];
$parsed = ss_parse_token($token, $facultyId);
if ($parsed === null) {
    ss_fail(400, 'This survey is no longer available.', 'not_eligible');
}
if (ss_find_owned_transaction($conn, $parsed['type'], $parsed['transaction_key'], $facultyId) === null) {
    ss_fail(409, 'This transaction is no longer eligible for a survey.', 'not_eligible');
}

$presented = ss_claim_presentation($conn, $parsed['type'], $parsed['transaction_key']);
ss_json(['status' => 'success', 'presented' => $presented]);
