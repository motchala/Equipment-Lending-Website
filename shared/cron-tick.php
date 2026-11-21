<?php

/**
 * shared/cron-tick.php
 *
 * A URL a REAL scheduler can call to run the project's scheduled work (see core/pseudo-cron.php):
 *   - hosting that has cron jobs (premium InfinityFree, another host, a VPS):  every minute
 *       * * * * *  curl -fsS "https://your-site/shared/cron-tick.php?key=YOUR_TOKEN"
 *   - or an outside pinger, on a host that does not block automated requests
 *     (the free InfinityFree plan DOES block them, so there only the browser-driven ticks work)
 *
 * It does nothing until CRON_TOKEN (at least 16 characters) is added to .env:
 *       CRON_TOKEN=put-a-long-random-string-here
 * and the same value is sent as ?key=... or in an X-Cron-Key header. Without the right key it answers 403.
 *
 * Response: { "ok": true, "ran": ["rooms","equipment"], "at": "2026-11-22T09:10:00+08:00" }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../config/db.php';   // loads .env and defines getDB()
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Cache-Control: no-store');

$expected = (string)($_ENV['CRON_TOKEN'] ?? '');
if (strlen($expected) < 16) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'CRON_TOKEN is not configured']);
    exit();
}

$given = (string)($_SERVER['HTTP_X_CRON_KEY'] ?? ($_GET['key'] ?? ''));
if (!hash_equals($expected, $given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit();
}

require_once __DIR__ . '/core/pseudo-cron.php';

$conn = getDB();
$ran  = ps_pseudo_cron_run($conn);   // no throttle: the scheduler decides how often
$conn->close();

echo json_encode(['ok' => true, 'ran' => $ran, 'at' => (new DateTimeImmutable('now'))->format('c')]);
