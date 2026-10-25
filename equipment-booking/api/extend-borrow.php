<?php

/**
 * equipment-booking/api/extend-borrow.php
 *
 * Extend a borrowed item that has reached its return time (faculty My Activity).
 * The rules live in core/borrow-extension.php (BorrowExtension).
 *
 *   GET   ?id=<request id>
 *         What the Extend dialog shows: the loan and each extension length, with whether it can be
 *         granted right now and, when it cannot, why.
 *         -> { ok, item, qty, status, due, extended, options: [{length, label, until, available, reason}], error? }
 *
 *   POST  id=<request id>  length=<hours or days>  csrf_token=<token>
 *         Grants the extension or denies it (not enough units for that period, room reserved by
 *         another faculty, item no longer available ...). Nothing changes unless it is granted.
 *         -> { ok: true, message, until }   |   { ok: false, error }
 *
 * Faculty only, and only for the signed-in faculty member's own loans.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Your session has expired. Please sign in again.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-activity.php';   // also loads BookingSchedule + BorrowExtension

$conn       = getDB();
$faculty_id = (string)$_SESSION['faculty_id'];

// Limits follow the account's role, read from the DB, never from the request
$stmt = $conn->prepare('SELECT role FROM tbl_users WHERE faculty_id = ? LIMIT 1');
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$role = (string)($stmt->get_result()->fetch_assoc()['role'] ?? '');
$stmt->close();
$adviser = BookingSchedule::isAdviserRole($role);

// Bring statuses up to date first (a loan that just reached its return time becomes Overdue)
fact_eq_housekeeping($conn);

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    echo json_encode(
        BorrowExtension::preview($conn, $faculty_id, $id, $adviser),
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    $conn->close();
    exit();
}

// POST: CSRF first (sends its own 403 JSON and stops when the token is wrong)
csrf_verify();

$id     = (int)($_POST['id'] ?? 0);
$length = (int)($_POST['length'] ?? 0);
if ($id <= 0 || $length <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Please choose how long to extend.']);
    $conn->close();
    exit();
}

echo json_encode(
    BorrowExtension::extend($conn, $faculty_id, $id, $length, $adviser),
    JSON_INVALID_UTF8_SUBSTITUTE
);
$conn->close();
