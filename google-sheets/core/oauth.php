<?php

declare(strict_types=1);

/**
 * google-sheets/core/oauth.php
 *
 * OAuth 2.0 token management for the Google Sheets integration.
 *
 * Tokens are stored in tbl_sync_config:
 *   oauth_access_token  — short-lived Bearer token (1 hour)
 *   oauth_refresh_token — permanent token (until revoked)
 *   oauth_token_expires — Unix timestamp when access token expires
 *
 * Public API:
 *   gs_token(mysqli $conn): ?string   — returns a valid Bearer token or null if not connected
 *   gs_token_store(mysqli, string $access, string $refresh, int $expires): void
 *   gs_token_delete(mysqli $conn): void
 *   gs_oauth_connected(mysqli $conn): bool
 */

require_once __DIR__ . '/../../config/env.php';
load_env();

// ── Config keys ───────────────────────────────────────────────────────────────
const GS_KEY_ACCESS   = 'oauth_access_token';
const GS_KEY_REFRESH  = 'oauth_refresh_token';
const GS_KEY_EXPIRES  = 'oauth_token_expires';

const GS_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
const GS_REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

/** Read one value from tbl_sync_config. Returns '' on miss. */
function gs_config_read(mysqli $conn, string $key): string
{
    $stmt = $conn->prepare('SELECT value FROM tbl_sync_config WHERE config_key = ? LIMIT 1');
    if (!$stmt) return '';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['value'] ?? ''));
}

/** Write one value to tbl_sync_config (upsert). */
function gs_config_write(mysqli $conn, string $key, string $value): void
{
    $stmt = $conn->prepare(
        "INSERT INTO tbl_sync_config (config_key, value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()"
    );
    if (!$stmt) return;
    $stmt->bind_param('sss', $key, $value, $value);
    $stmt->execute();
    $stmt->close();
}

/** True when a refresh token is stored (OAuth connected). */
function gs_oauth_connected(mysqli $conn): bool
{
    return gs_config_read($conn, GS_KEY_REFRESH) !== '';
}

/**
 * Returns a valid Bearer token, refreshing it if expired.
 * Returns null if OAuth is not connected or refresh fails.
 */
function gs_token(mysqli $conn): ?string
{
    $refresh = gs_config_read($conn, GS_KEY_REFRESH);
    if ($refresh === '') {
        return null;
    }

    $access  = gs_config_read($conn, GS_KEY_ACCESS);
    $expires = (int)gs_config_read($conn, GS_KEY_EXPIRES);

    // Use cached token if it has more than 60 seconds left
    if ($access !== '' && $expires > time() + 60) {
        return $access;
    }

    // Refresh
    $clientId     = trim((string)($_ENV['GOOGLE_CLIENT_ID']     ?? ''));
    $clientSecret = trim((string)($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));

    if ($clientId === '' || $clientSecret === '') {
        error_log('[gs_token] GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET missing from .env');
        return null;
    }

    $params = http_build_query([
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refresh,
        'grant_type'    => 'refresh_token',
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: PUPSync/1.0\r\n",
            'content' => $params,
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => false],
    ]);

    $raw = @file_get_contents(GS_TOKEN_ENDPOINT, false, $ctx);
    if ($raw === false) {
        error_log('[gs_token] Token refresh HTTP call failed');
        return null;
    }

    $data = json_decode($raw, true);
    if (empty($data['access_token'])) {
        error_log('[gs_token] Token refresh returned no access_token: ' . $raw);
        return null;
    }

    $newAccess  = (string)$data['access_token'];
    $newExpires = time() + (int)($data['expires_in'] ?? 3600);

    gs_config_write($conn, GS_KEY_ACCESS, $newAccess);
    gs_config_write($conn, GS_KEY_EXPIRES, (string)$newExpires);

    return $newAccess;
}

/** Store tokens after the OAuth callback. */
function gs_token_store(mysqli $conn, string $access, string $refresh, int $expires): void
{
    gs_config_write($conn, GS_KEY_ACCESS, $access);
    gs_config_write($conn, GS_KEY_REFRESH, $refresh);
    gs_config_write($conn, GS_KEY_EXPIRES, (string)$expires);
}

/** Revoke and delete all stored tokens. */
function gs_token_delete(mysqli $conn): void
{
    $refresh = gs_config_read($conn, GS_KEY_REFRESH);

    // Attempt Google-side revocation (best effort)
    if ($refresh !== '') {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: PUPSync/1.0\r\n",
                'content' => 'token=' . urlencode($refresh),
                'timeout' => 5,
            ],
            'ssl' => ['verify_peer' => false],
        ]);
        @file_get_contents(GS_REVOKE_ENDPOINT, false, $ctx);
    }

    // Delete from DB regardless of revocation result
    foreach ([GS_KEY_ACCESS, GS_KEY_REFRESH, GS_KEY_EXPIRES] as $key) {
        $del = $conn->prepare('DELETE FROM tbl_sync_config WHERE config_key = ?');
        if ($del) { $del->bind_param('s', $key); $del->execute(); $del->close(); }
    }
}
