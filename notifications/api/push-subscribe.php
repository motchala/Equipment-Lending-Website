<?php
declare(strict_types=1);

/**
 * notifications/api/push-subscribe.php
 *
 * Saves a OneSignal player ID for the signed-in user (faculty or admin).
 * Called by push-init.js after the user grants notification permission.
 *
 * POST JSON: { player_id: string, csrf_token: string }
 * Response:  { success: true } or { success: false, error: string }
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

// Determine audience and user_id from session
$audience = '';
$user_id  = '';

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

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

$player_id = trim((string)($body['player_id'] ?? ''));
if ($player_id === '' || strlen($player_id) > 64) {
    $conn->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid subscription ID.']);
    exit;
}

require_once __DIR__ . '/../core/push.php';
ps_push_ensure_table($conn);

try {
    $st = $conn->prepare(
        'INSERT INTO tbl_push_subscriptions (audience, user_id, player_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), created_at = NOW()'
    );
    if (!$st) throw new RuntimeException('Prepare failed: ' . $conn->error);
    $st->bind_param('sss', $audience, $user_id, $player_id);
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
