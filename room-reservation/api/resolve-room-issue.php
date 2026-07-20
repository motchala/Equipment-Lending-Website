<?php
/**
 * room-reservation/api/resolve-room-issue.php
 * Admin resolves or dismisses a room issue report.
 * Optionally sets the room's status to Maintenance.
 *
 * POST body (JSON):
 *   {
 *     issue_id:         int,
 *     resolution:       'Resolved' | 'Dismissed',
 *     admin_notes:      string (optional),
 *     set_maintenance:  bool (optional, only meaningful for 'Resolved'),
 *     csrf_token:       string
 *   }
 *
 * Admin-only endpoint.
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

$body = json_decode(file_get_contents('php://input'), true);

// CSRF
$token = $body['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token. Please refresh and try again.']);
    exit();
}

$issueId        = intval($body['issue_id']       ?? 0);
$resolution     = trim($body['resolution']       ?? '');
$adminNotes     = trim($body['admin_notes']      ?? '');
$setMaintenance = !empty($body['set_maintenance']);

$allowed = ['Resolved', 'Dismissed'];
if (!$issueId || !in_array($resolution, $allowed, true)) {
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}

$conn = getDB();

// Fetch issue to get room_id and verify it exists + is Open
$fetch = $conn->prepare(
    "SELECT id, room_id, status FROM tbl_room_issues WHERE id = ? LIMIT 1"
);
$fetch->bind_param('i', $issueId);
$fetch->execute();
$issue = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$issue) {
    echo json_encode(['error' => 'Issue report not found.']);
    exit();
}
if ($issue['status'] !== 'Open') {
    echo json_encode(['error' => 'This issue has already been resolved.']);
    exit();
}

$adminNotesVal = $adminNotes !== '' ? $adminNotes : null;

// Update issue record
$upd = $conn->prepare(
    "UPDATE tbl_room_issues
        SET status      = ?,
            admin_notes = ?,
            resolved_at = NOW()
      WHERE id = ? AND status = 'Open'"
);
$upd->bind_param('ssi', $resolution, $adminNotesVal, $issueId);
$upd->execute();
$upd->close();

// Optionally set room to Maintenance
if ($setMaintenance && $resolution === 'Resolved') {
    $roomUpd = $conn->prepare(
        "UPDATE tbl_rooms SET status = 'Maintenance'
          WHERE room_id = ? AND is_archived = 0"
    );
    $roomUpd->bind_param('i', $issue['room_id']);
    $roomUpd->execute();
    $roomUpd->close();
}

echo json_encode(['success' => true]);
