<?php

declare(strict_types=1);

/**
 * google-sheets/api/callback.php
 *
 * Google redirects here after the Super Admin clicks Allow.
 * Exchanges the authorization code for access + refresh tokens,
 * stores them in the private token file (config/google-oauth-token.php —
 * never in the database), redirects back to the Imports tab.
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';
load_env();
require_once __DIR__ . '/../core/oauth.php';
require_once __DIR__ . '/../../auth/core/admin-auth.php';

function gs_cb_fail(string $msg): never
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    header('Location: ' . $scheme . '://' . $host . $base . '/admin-dashboard.php?gs_error=' . urlencode($msg) . '#tab-imports');
    exit;
}

// No session check here — SameSite=Strict drops the session cookie on the cross-site redirect
// from Google. CSRF protection comes from the state token stored in tbl_sync_config instead.

// Validate state against DB (not session)
$state_param = trim((string)($_GET['state'] ?? ''));
if ($state_param === '') {
    gs_cb_fail('Missing state parameter.');
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

$sr = $conn->prepare("SELECT value FROM tbl_sync_config WHERE config_key = 'gs_oauth_state' LIMIT 1");
$stored_payload = '';
if ($sr) {
    $sr->execute();
    $row = $sr->get_result()->fetch_assoc();
    $sr->close();
    $stored_payload = trim((string)($row['value'] ?? ''));
}

// Delete the state immediately (one-time use)
$conn->query("DELETE FROM tbl_sync_config WHERE config_key = 'gs_oauth_state'");

if ($stored_payload === '') {
    gs_cb_fail('Session expired. Please try connecting again.');
}

// Parse: state|expires|admin_email
$parts = explode('|', $stored_payload, 3);
if (count($parts) < 2) {
    gs_cb_fail('Invalid state format. Please try again.');
}
$stored_state = $parts[0];
$expires      = $parts[1];
$admin_email  = $parts[2] ?? '';

if (!hash_equals($stored_state, $state_param)) {
    gs_cb_fail('Invalid state. Please try again.');
}
if (strtotime($expires) === false || strtotime($expires) < time()) {
    gs_cb_fail('Connection request expired. Please try again.');
}

// Restore the admin session using the stored email (session was lost on cross-origin redirect from Google)
if ($admin_email !== '' && empty($_SESSION['admin'])) {
    $restore = $conn->prepare(
        'SELECT email, fullname, role, dormant_until, session_epoch
           FROM tbl_accounts WHERE email = ? LIMIT 1'
    );
    if ($restore) {
        $restore->bind_param('s', $admin_email);
        $restore->execute();
        $row = $restore->get_result()->fetch_assoc();
        $restore->close();
        if ($row && !admin_is_dormant($row['dormant_until'])) {
            $_SESSION['admin']       = true;
            $_SESSION['admin_email'] = (string)$row['email'];
            $_SESSION['admin_name']  = (string)$row['fullname'];
            $_SESSION['admin_role']  = ($row['role'] === 'Super Admin') ? 'Super Admin' : 'Admin';
            $_SESSION['login_time']  = (int)$row['session_epoch'];
        }
    }
}

$code  = trim((string)($_GET['code']  ?? ''));
$error = trim((string)($_GET['error'] ?? ''));

if ($error !== '') {
    gs_cb_fail('Google returned an error: ' . $error);
}
if ($code === '') {
    gs_cb_fail('No authorization code received.');
}

$clientId     = trim((string)($_ENV['GOOGLE_CLIENT_ID']     ?? ''));
$clientSecret = trim((string)($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));

$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal     = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
$redirectUri = $isLocal
    ? 'http://' . $host . '/Equipment-Lending-Website/google-sheets/api/callback.php'
    : 'https://' . $host . '/google-sheets/api/callback.php';

$params = http_build_query([
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: PUPSync/1.0\r\n",
        'content' => $params,
        'timeout' => 15,
    ],
    'ssl' => ['verify_peer' => false],
]);

$raw = @file_get_contents(GS_TOKEN_ENDPOINT, false, $ctx);
if ($raw === false) {
    gs_cb_fail('Could not reach Google. Check your connection and try again.');
}

$data = json_decode($raw, true);
if (empty($data['access_token']) || empty($data['refresh_token'])) {
    error_log('[gs_callback] token exchange failed: ' . $raw);
    gs_cb_fail('Google did not return the expected tokens. Try connecting again.');
}

$conn = getDB();
$saved = gs_token_store(
    $conn,
    (string)$data['access_token'],
    (string)$data['refresh_token'],
    time() + (int)($data['expires_in'] ?? 3600)
);
if (!$saved) {
    gs_cb_fail('Could not save the Google connection on the server. Make sure the config/ folder is writable by PHP.');
}

// SameSite=Strict means a cross-origin redirect from Google will not carry the session cookie.
// A meta-refresh is a same-origin navigation — the session cookie is sent normally.
// We do NOT use a <script> redirect because security-headers.php sets a CSP that blocks inline scripts.
$base        = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$destination = htmlspecialchars($base . '/admin-dashboard.php?gs_connected=1#tab-imports', ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="0; url=<?php echo $destination; ?>">
<title>Connecting…</title>
</head>
<body>Connecting to Google Sheets, please wait…</body>
</html>
<?php
exit;
