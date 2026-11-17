<?php
declare(strict_types=1);

/**
 * notifications/api/push-csrf.php
 *
 * Returns the current session CSRF token for push subscription calls.
 * GET — requires an active session (faculty or admin).
 * Response: { token: string } or { token: null }
 */

ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

if (empty($_SESSION['faculty_id']) && empty($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['token' => null]);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
echo json_encode(['token' => csrf_token()]);
