<?php
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Use POST to end all sessions.']);
    exit();
}

csrf_verify();

if (($_SESSION['admin'] ?? null) !== true || empty($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Your admin session has expired.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$email = (string)$_SESSION['admin_email'];

admin_accounts_ensure_schema($conn);
remember_me_ensure_schema($conn);

if (!$conn->begin_transaction()) {
    error_log('[logout-all] Could not start transaction: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not end all sessions. Please try again.']);
    exit();
}

$update = $conn->prepare(
    'UPDATE tbl_accounts SET session_epoch = GREATEST(UNIX_TIMESTAMP() + 1, session_epoch + 1) WHERE email = ?'
);
if (!$update) {
    $conn->rollback();
    error_log('[logout-all] Session update prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not end all sessions. Please try again.']);
    exit();
}
$update->bind_param('s', $email);
if (!$update->execute() || $update->affected_rows !== 1) {
    $error = $update->error;
    $update->close();
    $conn->rollback();
    error_log('[logout-all] Session update failed: ' . ($error ?: 'admin account not found'));
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not end all sessions. Please try again.']);
    exit();
}
$update->close();

$revoke = $conn->prepare(
    "DELETE FROM tbl_remember_tokens WHERE account_type = 'admin' AND account_email = ?"
);
if (!$revoke) {
    $conn->rollback();
    error_log('[logout-all] Remember-token revocation prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not end all sessions. Please try again.']);
    exit();
}
$revoke->bind_param('s', $email);
if (!$revoke->execute()) {
    $error = $revoke->error;
    $revoke->close();
    $conn->rollback();
    error_log('[logout-all] Remember-token revocation failed: ' . $error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not end all sessions. Please try again.']);
    exit();
}
$revoke->close();

if (!$conn->commit()) {
    $conn->rollback();
    error_log('[logout-all] Could not commit session revocation: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not end all sessions. Please try again.']);
    exit();
}

remember_me_clear_cookie();
$_SESSION = [];
$sessionCookie = session_get_cookie_params();
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $sessionCookie['path'],
        'domain' => $sessionCookie['domain'],
        'secure' => $sessionCookie['secure'],
        'httponly' => $sessionCookie['httponly'],
        'samesite' => $sessionCookie['samesite'] ?? 'Strict',
    ]);
}
session_destroy();

echo json_encode(['status' => 'success']);
