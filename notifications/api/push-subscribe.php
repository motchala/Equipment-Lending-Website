<?php
declare(strict_types=1);

/**
 * notifications/api/push-subscribe.php
 *
 * Saves a Web Push subscription for the signed-in user (faculty or admin).
 * Called by push-init.js after the user grants notification permission.
 *
 * POST JSON: { subscription: { endpoint, keys: { auth, p256dh } }, csrf_token: string }
 * Response:  { success: true } or { success: false, error: string }
 *
 * The subscription endpoint + keys are stored in tbl_push_subscriptions.
 * The push sender (notifications/core/push.php) reads them back when sending.
 */

ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

$body = json_decode((string)file_get_contents('php://input'), true);

$csrf = (string)($body['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session. Please refresh the page.']);
    exit;
}

$subscription = $body['subscription'] ?? null;
if (
    !is_array($subscription) ||
    empty($subscription['endpoint']) ||
    empty($subscription['keys']['auth']) ||
    empty($subscription['keys']['p256dh'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid subscription.']);
    exit;
}

$endpoint = trim((string)$subscription['endpoint']);
$auth     = trim((string)$subscription['keys']['auth']);
$p256dh   = trim((string)$subscription['keys']['p256dh']);

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

$audience = '';
$user_id  = '';

if (!empty($_SESSION['faculty_id'])) {
    $audience = 'faculty';
    $user_id  = (string)$_SESSION['faculty_id'];
} elseif (!empty($_SESSION['admin']) && $_SESSION['admin'] === true) {
    $audience = 'admin';
    $email    = strtolower(trim((string)($_SESSION['admin_email'] ?? '')));
    $st       = $conn->prepare('SELECT id FROM tbl_accounts WHERE email = ? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $user_id = $row ? (string)(int)$row['id'] : '';
    }
} else {
    $conn->close();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}

if ($user_id === '') {
    $conn->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Could not identify your account.']);
    exit;
}

require_once __DIR__ . '/../core/push.php';
ps_push_ensure_table($conn);

try {
    // Store endpoint + keys; reuse player_id column for endpoint, add auth+p256dh columns lazily
    ps_push_ensure_columns($conn);

    $st = $conn->prepare(
        'INSERT INTO tbl_push_subscriptions (audience, user_id, player_id, auth_key, p256dh_key)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             player_id  = VALUES(player_id),
             auth_key   = VALUES(auth_key),
             p256dh_key = VALUES(p256dh_key),
             created_at = NOW()'
    );
    if (!$st) throw new RuntimeException('Prepare failed: ' . $conn->error);
    $st->bind_param('sssss', $audience, $user_id, $endpoint, $auth, $p256dh);
    $st->execute();
    $st->close();
    $conn->close();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[push-subscribe] ' . $e->getMessage());
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your subscription.']);
}
