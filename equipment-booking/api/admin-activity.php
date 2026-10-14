<?php

/**
 * admin-activity.php — the unified activity feed (any signed-in admin).
 *
 *   GET  actor   all | auto | me | <admin id>        (default all)
 *        filter  all | requests | rooms | equipment | system | signins   (default all)
 *        range   today | 7d | 30d | all               (default 30d)
 *        offset  rows to skip                         (default 0)
 *        format  json (default) | csv                 (csv = every matching row, max 5000)
 *
 * Returns { status, items:[event…], has_more, totals }
 *   event = { ts, actor_type: admin|auto, actor_id, actor_name, category, icon, tone,
 *             text, meta, tab }
 *
 * All the logic (sources, permissions) lives in config/admin-activity-feed.php so the
 * Dashboard card reads exactly the same data.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

function act_respond(int $code, string $status, string $message, array $extra = []): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if (($_SESSION['admin'] ?? false) !== true) {
    act_respond(401, 'error', 'Unauthorized.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    act_respond(405, 'error', 'Method not allowed.');
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/admin-activity-feed.php';
$conn = getDB();
admin_activity_bootstrap($conn);

$is_super = ($_SESSION['admin_role'] ?? '') === 'Super Admin';
$viewer   = admin_current_id($conn) ?? 0;

// ── Inputs ───────────────────────────────────────────────────────────────────
$filter    = (string)($_GET['filter'] ?? 'all');
$range     = (string)($_GET['range'] ?? '30d');
$offset    = max(0, (int)($_GET['offset'] ?? 0));
$actor_raw = (string)($_GET['actor'] ?? 'all');
$format    = (string)($_GET['format'] ?? 'json');

if (
    !in_array($filter, ['all', 'requests', 'rooms', 'equipment', 'system', 'signins'], true)
    || !in_array($range, ['today', '7d', '30d', 'all'], true)
    || !in_array($format, ['json', 'csv'], true)
) {
    act_respond(422, 'error', 'Invalid request.');
}

$actor_mode = 'all';
$actor_id   = 0;
if ($actor_raw === 'auto') {
    $actor_mode = 'auto';
} elseif ($actor_raw === 'me') {
    $actor_mode = 'admin';
    $actor_id   = $viewer;
} elseif (ctype_digit($actor_raw) && (int)$actor_raw > 0) {
    $actor_mode = 'admin';
    $actor_id   = (int)$actor_raw;
} elseif ($actor_raw !== 'all') {
    act_respond(422, 'error', 'Invalid request.');
}

$since = null;
if ($range === 'today') {
    $since = strtotime('today');
} elseif ($range === '7d') {
    $since = time() - 7 * 86400;
} elseif ($range === '30d') {
    $since = time() - 30 * 86400;
}

$query = [
    'viewer_id'  => $viewer,
    'is_super'   => $is_super,
    'actor_mode' => $actor_mode,
    'actor_id'   => $actor_id,
    'filter'     => $filter,
    'since'      => $since,
];

// ── CSV export ───────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $feed = admin_activity_feed($conn, $query + ['offset' => 0, 'limit' => 5000]);

    $labels = ['requests' => 'Requests', 'rooms' => 'Rooms', 'equipment' => 'Equipment', 'system' => 'System', 'signins' => 'Sign-ins'];
    // A cell starting with = + - @ would run as a formula when opened in Excel.
    $safe = static function ($v): string {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly
    fputcsv($out, ['Date', 'Time', 'Who', 'Type', 'Activity', 'Detail']);
    foreach ($feed['items'] as $e) {
        fputcsv($out, [
            date('Y-m-d', $e['ts']),
            date('g:i A', $e['ts']),
            $safe($e['actor_name']),
            $labels[$e['category']] ?? $e['category'],
            $safe($e['text']),
            $safe($e['meta']),
        ]);
    }
    fclose($out);
    exit;
}

// ── JSON ─────────────────────────────────────────────────────────────────────
$feed = admin_activity_feed($conn, $query + ['offset' => $offset, 'limit' => 15]);

// Totals: how many actions one admin has logged (shown in the Admins panel).
$totals = null;
if ($offset === 0 && $actor_mode === 'admin' && $actor_id > 0) {
    $sql    = 'SELECT COUNT(*) AS c FROM tbl_admin_activity WHERE admin_id = ?';
    $types  = 'i';
    $params = [$actor_id];
    if (!$is_super) {
        $sql    .= " AND (category IN ('request','room') OR admin_id = ?)";
        $types  .= 'i';
        $params[] = $viewer;
    }
    $row    = adm_act_rows($conn, $sql, $types, $params);
    $totals = ['all' => (int)($row[0]['c'] ?? 0)];
}

act_respond(200, 'success', 'OK', [
    'items'    => $feed['items'],
    'has_more' => $feed['has_more'],
    'totals'   => $totals,
]);
