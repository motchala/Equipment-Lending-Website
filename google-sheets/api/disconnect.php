<?php

declare(strict_types=1);

/**
 * google-sheets/api/disconnect.php
 *
 * POST: Super Admin disconnects the Google Sheet integration.
 * Revokes the OAuth token at Google and deletes the stored tokens
 * (private token file config/google-oauth-token.php, plus any legacy DB rows).
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit;
}

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid session. Refresh and try again.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/oauth.php';

$conn = getDB();
if (!admin_is_super_admin($conn)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Super Admin only.']);
    exit;
}

gs_token_delete($conn);

require_once __DIR__ . '/../../shared/core/activity-logger.php';
admin_log_activity($conn, 'equipment', 'Disconnected the Google Sheets integration.');

echo json_encode(['status' => 'success']);
exit;
