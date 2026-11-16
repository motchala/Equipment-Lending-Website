<?php
declare(strict_types=1);

/**
 * notifications/core/push.php
 *
 * PUPSync push notification helper (OneSignal Web Push).
 *
 * Public API:
 *   ps_push_send(mysqli $conn, string $audience, string $user_id,
 *                string $family, string $title, string $body, string $url): void
 *
 *   ps_push_ensure_table(mysqli $conn): void
 *
 * audience: 'faculty' (user_id = faculty_id) or 'admin' (user_id = tbl_accounts.id string)
 * family:   preference family key — same keys used by notif_family_enabled()
 *           e.g. 'equipment', 'overdue', 'facilities', 'requests', 'stock'
 *
 * Credentials from .env:
 *   ONESIGNAL_APP_ID
 *   ONESIGNAL_REST_API_KEY
 *
 * Failures are silently logged — push never interrupts the main action.
 */

if (!function_exists('ps_push_ensure_table')) {
    function ps_push_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS `tbl_push_subscriptions` (
                    `id`         int(11)     NOT NULL AUTO_INCREMENT,
                    `audience`   varchar(10) NOT NULL,
                    `user_id`    varchar(64) NOT NULL,
                    `player_id`  varchar(64) NOT NULL,
                    `created_at` datetime    DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_push_sub` (`audience`, `user_id`),
                    KEY `idx_player` (`player_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Throwable $e) {
            error_log('[ps_push] ensure_table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ps_push_get_player_id')) {
    function ps_push_get_player_id(mysqli $conn, string $audience, string $user_id): ?string
    {
        try {
            ps_push_ensure_table($conn);
            $st = $conn->prepare(
                'SELECT player_id FROM tbl_push_subscriptions
                  WHERE audience = ? AND user_id = ? LIMIT 1'
            );
            if (!$st) return null;
            $st->bind_param('ss', $audience, $user_id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            return $row ? (string)$row['player_id'] : null;
        } catch (Throwable $e) {
            error_log('[ps_push] get_player_id: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('ps_push_send')) {
    /**
     * Send a push notification to one user.
     * Respects the user's notification preferences via notif_family_enabled().
     *
     * @param string $audience 'faculty' or 'admin'
     * @param string $user_id  faculty_id or tbl_accounts.id string
     * @param string $family   preference family (e.g. 'equipment', 'overdue')
     * @param string $title    notification title shown on device
     * @param string $body     notification body text
     * @param string $url      path relative to site root (e.g. 'faculty-dashboard.php?tab=activity')
     */
    function ps_push_send(
        mysqli $conn,
        string $audience,
        string $user_id,
        string $family,
        string $title,
        string $body,
        string $url
    ): void {
        try {
            // Respect notification preferences — same gate the email and bell use
            if (!notif_family_enabled($conn, $audience, $user_id, $family)) {
                return;
            }

            $player_id = ps_push_get_player_id($conn, $audience, $user_id);
            if ($player_id === null) {
                return; // user has not subscribed to push yet — silent, not an error
            }

            require_once __DIR__ . '/../../config/env.php';
            load_env();

            $app_id  = trim((string)($_ENV['ONESIGNAL_APP_ID']      ?? ''));
            $api_key = trim((string)($_ENV['ONESIGNAL_REST_API_KEY'] ?? ''));

            if ($app_id === '' || $api_key === '') {
                error_log('[ps_push] ONESIGNAL_APP_ID or ONESIGNAL_REST_API_KEY missing from .env');
                return;
            }

            // Build absolute deep-link URL
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base     = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
            $full_url = $scheme . '://' . $host . $base . '/' . ltrim($url, '/');

            $payload = json_encode([
                'app_id'             => $app_id,
                'include_player_ids' => [$player_id],
                'headings'           => ['en' => $title],
                'contents'           => ['en' => $body],
                'url'                => $full_url,
                'web_push_topic'     => 'pupsync_' . $family,
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        =>
                        "Content-Type: application/json\r\n" .
                        "Authorization: Key " . $api_key . "\r\n" .
                        "User-Agent: PUPSync/1.0\r\n",
                    'content'       => $payload,
                    'timeout'       => 5,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => false],
            ]);

            $response = @file_get_contents('https://api.onesignal.com/notifications', false, $ctx);

            if ($response === false) {
                error_log('[ps_push] HTTP call failed — player_id=' . $player_id);
            }

        } catch (Throwable $e) {
            error_log('[ps_push] send: ' . $e->getMessage());
        }
    }
}
