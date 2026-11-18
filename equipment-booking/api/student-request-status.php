<?php
declare(strict_types=1);
/**
 * student-request-status.php
 *
 * Lets the Student Portal find out what happened to the borrow requests it submitted — most importantly,
 * to receive the Claim QR / Return QR once an ADMIN approves a request that was Waiting.
 *
 * The portal has no login, so each request is identified by its id PLUS the secret status_key that
 * submit-student-borrow.php / submit-item-borrow.php handed to the student. A wrong or missing key
 * is answered exactly like an unknown request, so ids cannot be probed.
 *
 * POST (JSON): { "items": [ { "request_id": 12, "key": "…" }, … ] }   (at most 20)
 * Reply:       { "success": true, "items": [ { request_id, status, reason, claim_token, return_token } ] }
 *   status is the request's real status ("Waiting", "Approved", "Declined", "Cancelled", "On Loan",
 *   "Overdue", "Returned" …) or "Missing" when it no longer exists.
 *   claim_token / return_token are only filled while they are usable.
 */
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$body  = json_decode((string)file_get_contents('php://input'), true);
$asked = is_array($body['items'] ?? null) ? array_slice($body['items'], 0, 20) : [];
if (!$asked) {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../core/auto-approve-tick.php';   // also loads request-decision.php

$conn = getDB();
$conn->set_charset('utf8mb4');
BookingSchedule::ensureSchema($conn);
// A waiting request whose deadline passed is declined here too, so the student sees it without waiting
// for an admin or faculty page to do it.
equipment_auto_approve_tick($conn);

$stmt = $conn->prepare(
    "SELECT status, COALESCE(batch_id, '') AS batch_id, COALESCE(reason, '') AS reason, COALESCE(student_key, '') AS student_key,
            COALESCE(NULLIF(claim_token, ''), NULLIF(bundle_claim_token, '')) AS claim,
            NULLIF(return_token, '') AS ret
       FROM tbl_requests WHERE id = ? LIMIT 1"
);
if (!$stmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['error' => 'Could not check your request right now.']);
    exit;
}

$out = [];
foreach ($asked as $it) {
    $id  = (int)($it['request_id'] ?? 0);
    $key = (string)($it['key'] ?? '');
    $row = null;
    if ($id > 0 && $key !== '') {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        if ($row && ($row['student_key'] === '' || !hash_equals($row['student_key'], $key))) {
            $row = null;
        }
    }
    if (!$row) {
        $out[] = ['request_id' => $id, 'status' => 'Missing', 'reason' => '', 'claim_token' => null, 'return_token' => null];
        continue;
    }
    $status   = (string)$row['status'];
    // A request submitted before Claim QRs were issued at submission: give it one now.
    if ($status === 'Waiting' && !$row['claim']) {
        $made = equipment_issue_pending_tokens($conn, $id);
        $row['claim'] = $made['claim_token'];
        $row['ret']   = $made['return_token'];
    }
    $out[] = [
        'request_id'   => $id,
        'batch_id'     => (string)$row['batch_id'],
        'status'       => $status,
        'reason'       => (string)$row['reason'],
        // Both QRs are shown from the start. The Claim QR only works once Approved (claim_confirm.php); the Return QR
        // only works while the item is On Loan / Overdue (return_confirm.php, partial-return.php).
        'claim_token'  => in_array($status, ['Waiting', 'Approved'], true) ? ($row['claim'] ?: null) : null,
        'return_token' => in_array($status, ['Waiting', 'Approved', 'On Loan', 'Overdue'], true) ? ($row['ret'] ?: null) : null,
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'items' => $out]);
