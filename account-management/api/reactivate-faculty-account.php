<?php

/**
 * reactivate-faculty-account.php
 *
 * Super-Admin-only: reactivate a deactivated faculty account (Faculty tab -> Edit -> Reactivate).
 * The undo of deactivate-faculty-account.php: sets tbl_users.is_active back to 1. Nothing else changes -
 * the person signs in again with the same email and password, and finds their profile and history intact.
 * (Things a deactivation clears on purpose - unused student codes and room waitlist entries - are not
 * brought back; the faculty simply generates a new code / joins a waitlist again.)
 *
 * POST fields
 *   csrf_token   required
 *   faculty_id   required
 *
 * Rules enforced here (never trusted to the UI):
 *   - POST only, valid CSRF token, logged-in admin who is a Super Admin (re-checked in the DB).
 *   - The account must exist and must currently be deactivated.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function respond(int $code, string $status, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message] + $extra);
    exit;
}

set_exception_handler(function (Throwable $e): void {
    error_log('[reactivate-faculty-account] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not reactivate this account. Please try again.']);
});

// -- Gate: method, session, CSRF ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'error', 'Method not allowed.');
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    respond(401, 'error', 'Unauthorized.');
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-status.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    respond(403, 'error', 'Only a Super Admin can reactivate faculty accounts.');
}
if (!faculty_status_ensure_schema($conn)) {
    respond(500, 'error', 'Could not reactivate this account. Please try again.');
}

// -- Which account? -----------------------------------------------------------
$faculty_id = trim((string)($_POST['faculty_id'] ?? ''));
if ($faculty_id === '' || strlen($faculty_id) > 255) {
    respond(422, 'error', 'Faculty account not found.');
}

$stmt = $conn->prepare('SELECT fullname, is_active FROM tbl_users WHERE faculty_id = ? LIMIT 1');
if (!$stmt) {
    error_log('[reactivate-faculty-account] lookup prepare failed: ' . $conn->error);
    respond(500, 'error', 'Could not reactivate this account. Please try again.');
}
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$res    = $stmt->get_result();
$target = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
$stmt->close();

if (!$target) {
    respond(404, 'error', 'That account no longer exists. Refresh the page.');
}
if ((int)$target['is_active'] === 1) {
    respond(409, 'error', 'That account is already active. Refresh the page.', ['code' => 'already_active']);
}

// -- Reactivate ---------------------------------------------------------------
$upd = $conn->prepare('UPDATE tbl_users SET is_active = 1 WHERE faculty_id = ? AND is_active = 0');
if (!$upd) {
    error_log('[reactivate-faculty-account] update prepare failed: ' . $conn->error);
    respond(500, 'error', 'Could not reactivate this account. Please try again.');
}
$upd->bind_param('s', $faculty_id);
if (!$upd->execute()) {
    error_log('[reactivate-faculty-account] update failed: ' . $upd->error);
    $upd->close();
    respond(500, 'error', 'Could not reactivate this account. Please try again.');
}
$changed = $upd->affected_rows;
$upd->close();

if ($changed !== 1) {
    respond(409, 'error', 'That account is already active. Refresh the page.', ['code' => 'already_active']);
}

require_once __DIR__ . '/../../shared/core/activity-logger.php';
admin_log_activity($conn, 'faculty', 'Reactivated faculty account of ' . trim((string)($target['fullname'] ?? $faculty_id)));
respond(200, 'success', 'Faculty account reactivated.');
