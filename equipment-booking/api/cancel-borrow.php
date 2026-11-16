<?php

/**
 * equipment-booking/api/cancel-borrow.php
 *
 * Faculty cancels an equipment request that has not started yet (My Activity > Upcoming).
 * Rules and side effects: core/borrow-cancellation.php (BorrowCancellation).
 *
 *   POST  id=<request id>  [scope=bundle]  csrf_token=<token>
 *         scope=bundle cancels every item of that request's bundle that can still be cancelled.
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
require_once __DIR__ . '/../../activity/core/faculty-data.php';   // also loads BookingSchedule + BorrowCancellation

$conn = getDB();

// A request that just ran out of time is declined/started by housekeeping, so cancel the real state
fact_eq_housekeeping($conn);

$whole  = (string)($_POST['scope'] ?? '') === 'bundle';
$result = $whole
    ? BorrowCancellation::cancelBundle($conn, $id, (string)$_SESSION['faculty_id'])
    : BorrowCancellation::cancel($conn, $id, (string)$_SESSION['faculty_id']);
$conn->close();

if ($result['success']) {
    $count = (int)($result['count'] ?? 1);
    if ($count > 1) {
        $message = 'Cancelled ' . $count . ' items from your bundle.'
            . ((int)($result['skipped'] ?? 0) > 0 ? ' ' . (int)$result['skipped'] . ' could not be cancelled.' : '');
    } else {
        $message = 'Your request for ' . $result['item'] . ' was cancelled.';
    }
    echo json_encode(['ok' => true, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE);
} else {
    echo json_encode(['ok' => false, 'error' => $result['error']], JSON_INVALID_UTF8_SUBSTITUTE);
}
