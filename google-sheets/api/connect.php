<?php

declare(strict_types=1);

/**
 * google-sheets/api/connect.php
 *
 * Redirects the Super Admin to Google's OAuth 2.0 consent screen.
 * On return, Google calls google-sheets/api/callback.php.
 *
 * GET — no body. Super Admin auth + CSRF via query param.
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';
load_env();

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    exit('Unauthorized.');
}

$conn = getDB();
if (!admin_is_super_admin($conn)) {
    http_response_code(403);
    exit('Super Admin only.');
}

$clientId = trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? ''));
if ($clientId === '') {
    exit('GOOGLE_CLIENT_ID is not set in .env.');
}

// CSRF state — stored in DB (not session) so it survives the cross-site redirect from Google
// SameSite=Strict blocks the session cookie on cross-origin redirects, so we cannot use $_SESSION here.
$state = bin2hex(random_bytes(16));
$expires = date('Y-m-d H:i:s', time() + 300);  // 5 minutes
$s = $conn->prepare(
    "INSERT INTO tbl_sync_config (config_key, value)
     VALUES ('gs_oauth_state', ?)
     ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()"
);
// Also store the admin email so callback.php can restore the session
$adminEmail   = (string)($_SESSION['admin_email'] ?? '');
$statePayload = $state . '|' . $expires . '|' . $adminEmail;
if ($s) {
    $s->bind_param('ss', $statePayload, $statePayload);
    $s->execute();
    $s->close();
}

// Detect environment for redirect URI
$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal     = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
$scheme      = $isLocal ? 'http' : 'https';
$redirectUri = $scheme . '://' . $host . '/Equipment-Lending-Website/google-sheets/api/callback.php';
if (!$isLocal) {
    // Production: no subfolder
    $redirectUri = 'https://' . $host . '/google-sheets/api/callback.php';
}

$params = http_build_query([
    'client_id'             => $clientId,
    'redirect_uri'          => $redirectUri,
    'response_type'         => 'code',
    'scope'                 => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.metadata.readonly',
    'access_type'           => 'offline',   // needed for refresh token
    'prompt'                => 'consent',   // always ask — ensures refresh token is returned
    'state'                 => $state,
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
