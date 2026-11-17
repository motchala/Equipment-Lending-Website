<?php
declare(strict_types=1);

/**
 * notifications/api/push-vapid-key.php
 *
 * Returns the VAPID public key for browser push subscription.
 * Public — no auth needed (the public key is safe to expose).
 *
 * GET  → { key: string } or { key: null }
 */

ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../config/env.php';
load_env();

$key = trim((string)($_ENV['VAPID_PUBLIC_KEY'] ?? ''));
echo json_encode(['key' => $key !== '' ? $key : null]);
