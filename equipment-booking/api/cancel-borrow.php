<?php

/**
 * equipment-booking/api/cancel-borrow.php
 *
 * Faculty cancels an equipment request that has not started yet (My Activity > Upcoming).
 * Rules and side effects: core/borrow-cancellation.php (BorrowCancellation).
 *
 *   POST  id=<request id>  csrf_token=<token>
 *         -> { ok: true, message }   |   { ok: false, error }
 *
 * Faculty only, and only for the signed-in faculty member's own requests.
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
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit();
}

// Sends its own 403 JSON and stops when the token is wrong
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'We could not find that request.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-activity.php';   // also loads BookingSchedule + BorrowCancellation

$conn = getDB();

// A request that just ran out of time is declined/started by housekeeping, so cancel the real state
fact_eq_housekeeping($conn);

$result = BorrowCancellation::cancel($conn, $id, (string)$_SESSION['faculty_id']);
$conn->close();

if ($result['success']) {
    echo json_encode([
        'ok'      => true,
        'message' => 'Your request for ' . $result['item'] . ' was cancelled.',
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} else {
    echo json_encode(['ok' => false, 'error' => $result['error']], JSON_INVALID_UTF8_SUBSTITUTE);
}
