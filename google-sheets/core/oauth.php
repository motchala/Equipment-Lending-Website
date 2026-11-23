<?php

declare(strict_types=1);

/**
 * google-sheets/core/oauth.php
 *
 * OAuth 2.0 token management for the Google Sheets integration.
 *
 * WHERE THE TOKENS LIVE
 * ---------------------
 * The access token, refresh token and expiry are stored in a private file
 * (config/google-oauth-token.php), NOT in the database. That is deliberate:
 * anything in the database ends up in every phpMyAdmin / mysqldump export,
 * which is then committed to GitHub. A file that is git-ignored, FTP-deploy
 * excluded and denied by config/.htaccess can never leak through a SQL export.
 *
 * The file begins with a one-line PHP guard (`<?php ... exit; ?>`), so even on
 * a server that ignores .htaccess a direct request prints nothing. The JSON
 * payload follows the guard.
 *
 * LEGACY DATABASE ROWS
 * --------------------
 * Older versions kept the tokens in tbl_sync_config (oauth_access_token,
 * oauth_refresh_token, oauth_token_expires). Whenever this file is used, any
 * such rows are moved into the token file (if there is none yet) and then
 * deleted from the database, so an old SQL import cannot re-introduce them.
 *
 * Public API (unchanged):
 *   gs_token(mysqli $conn): ?string   — returns a valid Bearer token or null if not connected
 *   gs_token_store(mysqli, string $access, string $refresh, int $expires): bool
 *   gs_token_delete(mysqli $conn): void
 *   gs_oauth_connected(mysqli $conn): bool
 */

require_once __DIR__ . '/../../config/env.php';
load_env();

// ── Legacy DB keys (read/purged only; never written any more) ─────────────────
const GS_LEGACY_KEY_ACCESS  = 'oauth_access_token';
const GS_LEGACY_KEY_REFRESH = 'oauth_refresh_token';
const GS_LEGACY_KEY_EXPIRES = 'oauth_token_expires';

const GS_TOKEN_ENDPOINT  = 'https://oauth2.googleapis.com/token';
const GS_REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

/** First line of the token file. Stops the file from ever being served as text. */
const GS_TOKEN_FILE_GUARD = "<?php http_response_code(404); exit; ?>\n";

// ── Token file ────────────────────────────────────────────────────────────────

/** Absolute path of the private token file. */
function gs_token_file_path(): string
{
    return dirname(__DIR__, 2) . '/config/google-oauth-token.php';
}

/**
 * Read the stored tokens from the token file.
 *
 * @return array{access:string, refresh:string, expires:int}|null  null = not connected
 */
function gs_token_file_read(): ?array
{
    $path = gs_token_file_path();
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    // Skip the PHP guard line: the JSON payload starts right after the guard's closing tag.
    $pos = strpos($raw, '?>');
    if ($pos === false) {
        return null;
    }
    $data = json_decode(trim(substr($raw, $pos + 2)), true);
    if (!is_array($data)) {
        return null;
    }

    $refresh = trim((string)($data['refresh_token'] ?? ''));
    if ($refresh === '') {
        return null;
    }

    return [
        'access'  => trim((string)($data['access_token'] ?? '')),
        'refresh' => $refresh,
        'expires' => (int)($data['expires'] ?? 0),
    ];
}

/** Write the tokens to the token file (atomic where possible). Returns false on failure. */
function gs_token_file_write(string $access, string $refresh, int $expires): bool
{
    $json = json_encode([
        'access_token'  => $access,
        'refresh_token' => $refresh,
        'expires'       => $expires,
        'updated_at'    => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $path    = gs_token_file_path();
    $payload = GS_TOKEN_FILE_GUARD . $json . "\n";

    // Preferred: write a temp file next to the target, then rename over it,
    // so a concurrent reader never sees a half-written file.
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
        @chmod($tmp, 0600);
        if (@rename($tmp, $path)) {
            return true;
        }
        @unlink($tmp);
    }

    // Fallback: write in place.
    if (@file_put_contents($path, $payload, LOCK_EX) !== false) {
        @chmod($path, 0600);
        return true;
    }

    error_log('[gs_oauth] Could not write token file: ' . $path . ' (is config/ writable by PHP?)');
    return false;
}

/** Remove the token file. */
function gs_token_file_delete(): void
{
    $path = gs_token_file_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

// ── Legacy DB rows (migration + purge) ────────────────────────────────────────

/** SQL list of the legacy keys, built from constants only (no user input). */
function gs_legacy_key_list(): string
{
    return "'" . implode("','", [GS_LEGACY_KEY_ACCESS, GS_LEGACY_KEY_REFRESH, GS_LEGACY_KEY_EXPIRES]) . "'";
}

/**
 * Read legacy token rows from tbl_sync_config. When a key appears more than
 * once, the most recently updated row wins.
 *
 * @return array<string,string>  config_key => value
 */
function gs_legacy_rows_read(mysqli $conn): array
{
    $out = [];
    $res = $conn->query(
        'SELECT config_key, value FROM tbl_sync_config
          WHERE config_key IN (' . gs_legacy_key_list() . ')
          ORDER BY updated_at ASC'
    );
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $out[(string)$row['config_key']] = trim((string)$row['value']);
        }
        $res->free();
    }
    return $out;
}

/** Delete every legacy token row from tbl_sync_config. */
function gs_legacy_rows_purge(mysqli $conn): void
{
    $conn->query('DELETE FROM tbl_sync_config WHERE config_key IN (' . gs_legacy_key_list() . ')');
}

/**
 * Load the stored tokens. Once per request it also looks for legacy tokens in
 * the database: they are moved into the token file (when there is no file yet)
 * and then deleted from the database.
 *
 * If the token file cannot be written, the legacy rows are left in place so
 * nothing is lost, and the in-memory copy is used for this request.
 *
 * @return array{access:string, refresh:string, expires:int}|null
 */
function gs_token_load(mysqli $conn): ?array
{
    static $legacyChecked = false;

    $state = gs_token_file_read();

    if (!$legacyChecked) {
        $legacyChecked = true;

        try {
            $legacy = gs_legacy_rows_read($conn);

            if ($legacy !== []) {
                $legacyRefresh = $legacy[GS_LEGACY_KEY_REFRESH] ?? '';

                if ($state === null && $legacyRefresh !== '') {
                    $state = [
                        'access'  => $legacy[GS_LEGACY_KEY_ACCESS] ?? '',
                        'refresh' => $legacyRefresh,
                        'expires' => (int)($legacy[GS_LEGACY_KEY_EXPIRES] ?? 0),
                    ];
                    if (!gs_token_file_write($state['access'], $state['refresh'], $state['expires'])) {
                        // Keep the DB rows; try again on the next request.
                        return $state;
                    }
                    error_log('[gs_oauth] Moved Google OAuth tokens from the database into config/google-oauth-token.php');
                }

                gs_legacy_rows_purge($conn);
            }
        } catch (Throwable $e) {
            // e.g. tbl_sync_config missing — must never break the token flow.
            error_log('[gs_oauth] Legacy token check skipped: ' . $e->getMessage());
        }
    }

    return $state;
}

// ── Public API ────────────────────────────────────────────────────────────────

/** True when a refresh token is stored (OAuth connected). */
function gs_oauth_connected(mysqli $conn): bool
{
    return gs_token_load($conn) !== null;
}

/**
 * Returns a valid Bearer token, refreshing it if expired.
 * Returns null if OAuth is not connected or refresh fails.
 */
function gs_token(mysqli $conn): ?string
{
    $state = gs_token_load($conn);
    if ($state === null) {
        return null;
    }

    $refresh = $state['refresh'];
    $access  = $state['access'];
    $expires = $state['expires'];

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

    // If saving fails the fresh token is still returned for this request.
    gs_token_file_write($newAccess, $refresh, $newExpires);

    return $newAccess;
}

/**
 * Store tokens after the OAuth callback (private file; never the database).
 * Also clears any stale legacy rows. Returns false if the file could not be written.
 */
function gs_token_store(mysqli $conn, string $access, string $refresh, int $expires): bool
{
    if (!gs_token_file_write($access, $refresh, $expires)) {
        return false;
    }

    try {
        gs_legacy_rows_purge($conn);
    } catch (Throwable $e) {
        error_log('[gs_oauth] Legacy purge skipped: ' . $e->getMessage());
    }
    return true;
}

/** Revoke and delete all stored tokens. */
function gs_token_delete(mysqli $conn): void
{
    $state   = gs_token_load($conn);
    $refresh = $state['refresh'] ?? '';

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

    // Delete locally regardless of revocation result
    gs_token_file_delete();
    try {
        gs_legacy_rows_purge($conn);
    } catch (Throwable $e) {
        error_log('[gs_oauth] Legacy purge skipped: ' . $e->getMessage());
    }
}
