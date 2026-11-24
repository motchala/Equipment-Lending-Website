<?php
ini_set('display_errors', '0');

/**
 * request-password-reset.php
 *
 * The "Notify Super Admin" button on the sign-in form. A faculty member who has used all their
 * attempts (see auth/core/password-reset.php) asks the Super Admin for a reset: this stamps
 * tbl_faculty_pw_reset.notified_at, which shows a notification to Super Admins.
 *
 * Nothing is sent from the browser except the CSRF token. WHICH account is asked about comes from the
 * session (pw_reset_offer_email, written by the sign-in handler after the 5th wrong password), and the
 * account is re-checked here: a request for an account that has not used up its attempts is refused.
 * Pressing the button again is harmless (it just reports that the Super Admin was already notified).
 *
 * POST: csrf_token
 * JSON: { status: success|error, code: sent|already|..., message }
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function pwr_respond(int $code, string $status, string $reply_code, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'code' => $reply_code, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pwr_respond(405, 'error', 'method', 'Invalid request.');
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/password-reset.php';
$conn = getDB();

$email = (string)($_SESSION['pw_reset_offer_email'] ?? '');
if ($email === '') {
    pwr_respond(403, 'error', 'no_offer', 'Please try signing in again first.');
}

switch (pwr_mark_notified($conn, $email)) {
    case 'sent':
        pwr_respond(200, 'success', 'sent', 'The Super Admin has been notified. They will give you a temporary password.');
    case 'already':
        pwr_respond(200, 'success', 'already', 'The Super Admin was already notified. Please wait for your temporary password.');
    case 'not_eligible':
        unset($_SESSION['pw_reset_offer_email']);
        pwr_respond(409, 'error', 'not_eligible', 'This request is no longer needed. Try signing in again.');
    default:
        pwr_respond(500, 'error', 'error', 'We could not send your request. Please try again.');
}
