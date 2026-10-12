<?php

/**
 * admin-activity.php — the unified activity feed (any signed-in admin).
 *
 *   GET  actor   all | auto | me | <admin id>        (default all)
 *        filter  all | requests | rooms | equipment | system | signins   (default all)
 *        range   today | 7d | 30d | all               (default 30d)
 *        offset  rows to skip                         (default 0)
 *
 * Returns { status, items:[event…], has_more, totals }
 *   event = { ts, actor_type: admin|auto, actor_id, actor_name, category, icon, tone,
 *             text, meta, tab }
 *
 * Sources, merged newest-first:
 *   • tbl_admin_activity          manual actions (always has the admin's id)
 *   • tbl_arbitration_log         AUTOMATIC request decisions (override_by empty)
 *   • tbl_room_arbitration_log    AUTOMATIC room-reservation decisions
 *
 * Who sees what:
 *   • Super Admin  — everything.
 *   • Admin        — request and room activity from everyone (decisions are shared),
 *                    plus everything they did themselves. Other admins' sign-ins,
 *                    equipment edits, account and settings changes stay private.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');

function act_respond(int $code, string $status, string $message, array $extra = []): void
{
    http_response_code($code);
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
require_once __DIR__ . '/../../config/admin-activity.php';
$conn = getDB();
admin_activity_bootstrap($conn);

$PAGE     = 15;
$is_super = ($_SESSION['admin_role'] ?? '') === 'Super Admin';
$viewer   = admin_current_id($conn) ?? 0;

// ── Inputs ───────────────────────────────────────────────────────────────────
$filter = (string)($_GET['filter'] ?? 'all');
$range  = (string)($_GET['range'] ?? '30d');
$offset = max(0, (int)($_GET['offset'] ?? 0));
$actor_raw = (string)($_GET['actor'] ?? 'all');

if (
    !in_array($filter, ['all', 'requests', 'rooms', 'equipment', 'system', 'signins'], true)
    || !in_array($range, ['today', '7d', '30d', 'all'], true)
) {
    act_respond(422, 'error', 'Invalid request.');
}

$actor_mode = 'all';   // all | auto | admin
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

// ── Presentation helpers ─────────────────────────────────────────────────────
/** Filter chip → tbl_admin_activity categories. */
function act_categories(string $filter): array
{
    switch ($filter) {
        case 'requests':
            return ['request'];
        case 'rooms':
            return ['room'];
        case 'equipment':
            return ['equipment'];
        case 'system':
            return ['account', 'faculty', 'settings'];
        case 'signins':
            return ['login'];
        default:
            return ['request', 'room', 'equipment', 'account', 'faculty', 'settings', 'login'];
    }
}

/** Category → [chip key shown in the UI, tab the item belongs to]. */
function act_category_meta(string $category): array
{
    switch ($category) {
        case 'request':
            return ['requests', 'requests'];
        case 'room':
            return ['rooms', 'rooms'];
        case 'equipment':
            return ['equipment', 'inventory'];
        case 'account':
            return ['system', 'admins'];
        case 'faculty':
            return ['system', 'faculty'];
        case 'settings':
            return ['system', 'settings'];
        default:
            return ['signins', ''];
    }
}

/** [material icon, tone] for an event. */
function act_style(string $category, ?string $action, string $summary): array
{
    $by_action = [
        'request_approved' => ['check_circle', 'ok'],
        'request_declined' => ['cancel', 'bad'],
        'request_returned' => ['assignment_return', 'ok'],
        'room_cancelled'   => ['event_busy', 'warn'],
        'issue_resolved'   => ['task_alt', 'ok'],
        'issue_dismissed'  => ['do_not_disturb_on', 'neutral'],
    ];
    if ($action !== null && isset($by_action[$action])) {
        return $by_action[$action];
    }
    if (stripos($summary, 'delet') !== false)        return ['delete', 'bad'];
    if (stripos($summary, 'unlocked') === 0)         return ['lock_open', 'neutral'];
    if (stripos($summary, 'locked') === 0)           return ['lock', 'warn'];
    if (stripos($summary, 'archived') === 0)         return ['archive', 'neutral'];

    $by_category = [
        'login'     => 'login',
        'request'   => 'assignment',
        'room'      => 'meeting_room',
        'equipment' => 'inventory_2',
        'account'   => 'manage_accounts',
        'faculty'   => 'group',
        'settings'  => 'tune',
    ];
    return [$by_category[$category] ?? 'history', 'neutral'];
}

/** Human label for an engine rule id. */
function act_rule_label(?string $rule): string
{
    $rule = (string)$rule;
    $known = [
        'rule_1_fifo'            => 'First-come, first-served',
        'rule_5_id_order'        => 'ID order',
        'rule_overdue_block'     => 'Overdue borrower',
        'rule_out_of_stock'      => 'Out of stock',
        'rule_missing_doc_hold'  => 'Missing document',
        'rule_missing_doc_block' => 'Missing document',
        'rule_duplicate_block'   => 'Duplicate request',
        'rule_archived'          => 'Item archived',
        'rule_past_datetime'     => 'Date already passed',
    ];
    if (isset($known[$rule])) {
        return $known[$rule];
    }
    if ($rule === '') {
        return '';
    }
    $pretty = trim(preg_replace('/^rule[_\s]*\d*[_\s]*/i', '', str_replace('_', ' ', $rule)));
    return $pretty === '' ? '' : ucfirst($pretty);
}

/** Run a prepared SELECT with dynamic parameters and return all rows. */
function act_rows(mysqli $conn, string $sql, string $types, array $params): array
{
    $rows = [];
    try {
        $st = $conn->prepare($sql);
        if (!$st) {
            error_log('[admin-activity] prepare failed: ' . $conn->error);
            return $rows;
        }
        if ($types !== '') {
            $st->bind_param($types, ...$params);
        }
        $st->execute();
        $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $rows[] = $r;
        }
        $st->close();
    } catch (Throwable $e) {
        error_log('[admin-activity] ' . $e->getMessage());
    }
    return $rows;
}

$want  = $offset + $PAGE + 1; // enough from each source to know whether more exists
$items = [];

// ── Source 1: manual actions (tbl_admin_activity) ────────────────────────────
if ($actor_mode !== 'auto') {
    $cats  = act_categories($filter);
    $where = ['t.category IN (' . implode(',', array_fill(0, count($cats), '?')) . ')'];
    $types = str_repeat('s', count($cats));
    $params = $cats;

    if ($actor_mode === 'admin') {
        $where[] = 't.admin_id = ?';
        $types  .= 'i';
        $params[] = $actor_id;
    }
    if ($since !== null) {
        $where[] = 't.created_at >= FROM_UNIXTIME(?)';
        $types  .= 'i';
        $params[] = $since;
    }
    if (!$is_super) {
        // Decisions are shared; everything else is private to the admin who did it.
        $where[] = "(t.category IN ('request','room') OR t.admin_id = ?)";
        $types  .= 'i';
        $params[] = $viewer;
    }
    $types  .= 'i';
    $params[] = $want;

    $sql = "SELECT t.admin_id, COALESCE(a.fullName, t.admin_name) AS actor_name, t.category, t.action,
                   t.summary, UNIX_TIMESTAMP(t.created_at) AS ts
              FROM tbl_admin_activity t
              LEFT JOIN tbl_accounts a ON a.id = t.admin_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT ?";
    foreach (act_rows($conn, $sql, $types, $params) as $r) {
        [$chip, $tab]   = act_category_meta($r['category']);
        [$icon, $tone]  = act_style($r['category'], $r['action'], (string)$r['summary']);
        $items[] = [
            'ts'         => (int)$r['ts'],
            'actor_type' => 'admin',
            'actor_id'   => (int)$r['admin_id'],
            'actor_name' => (string)$r['actor_name'],
            'category'   => $chip,
            'icon'       => $icon,
            'tone'       => $tone,
            'text'       => (string)$r['summary'],
            'meta'       => '',
            'tab'        => $tab,
        ];
    }
}

// ── Source 2: automatic request decisions (tbl_arbitration_log) ──────────────
if ($actor_mode !== 'admin' && ($filter === 'all' || $filter === 'requests')) {
    $where  = ["(override_by IS NULL OR override_by = '')"];
    $types  = '';
    $params = [];
    if ($since !== null) {
        $where[] = 'created_at >= FROM_UNIXTIME(?)';
        $types  .= 'i';
        $params[] = $since;
    }
    $types  .= 'i';
    $params[] = $want;
    $sql = "SELECT request_id, borrower_name, equipment_name, decision, rule_applied, UNIX_TIMESTAMP(created_at) AS ts
              FROM tbl_arbitration_log
             WHERE " . implode(' AND ', $where) . "
             ORDER BY created_at DESC, id DESC
             LIMIT ?";
    foreach (act_rows($conn, $sql, $types, $params) as $r) {
        $d   = (string)$r['decision'];
        $who = trim((string)$r['borrower_name']);
        $text = $d . ' request #' . (int)$r['request_id'] . ': ' . trim((string)$r['equipment_name']) . ($who !== '' ? ' for ' . $who : '');
        [$icon, $tone] = $d === 'Approved' ? ['check_circle', 'ok'] : ($d === 'Declined' ? ['cancel', 'bad'] : ['assignment', 'neutral']);
        $items[] = [
            'ts'         => (int)$r['ts'],
            'actor_type' => 'auto',
            'actor_id'   => 0,
            'actor_name' => 'Automatic',
            'category'   => 'requests',
            'icon'       => $icon,
            'tone'       => $tone,
            'text'       => $text,
            'meta'       => act_rule_label($r['rule_applied']),
            'tab'        => 'requests',
        ];
    }
}

// ── Source 3: automatic room-reservation decisions ───────────────────────────
if ($actor_mode !== 'admin' && ($filter === 'all' || $filter === 'rooms') && admin_activity_table_exists($conn, 'tbl_room_arbitration_log')) {
    $where  = ['1 = 1'];
    $types  = '';
    $params = [];
    if ($since !== null) {
        $where[] = 'created_at >= FROM_UNIXTIME(?)';
        $types  .= 'i';
        $params[] = $since;
    }
    $types  .= 'i';
    $params[] = $want;
    $sql = "SELECT reservation_id, room_name, borrower_name, decision, rule_applied, UNIX_TIMESTAMP(created_at) AS ts
              FROM tbl_room_arbitration_log
             WHERE " . implode(' AND ', $where) . "
             ORDER BY created_at DESC, id DESC
             LIMIT ?";
    foreach (act_rows($conn, $sql, $types, $params) as $r) {
        $d   = (string)$r['decision'];
        $who = trim((string)$r['borrower_name']);
        $text = $d . ' reservation #' . (int)$r['reservation_id'] . ': ' . trim((string)$r['room_name']) . ($who !== '' ? ' for ' . $who : '');
        [$icon, $tone] = $d === 'Approved' ? ['event_available', 'ok'] : ($d === 'Declined' ? ['event_busy', 'bad'] : ['meeting_room', 'neutral']);
        $items[] = [
            'ts'         => (int)$r['ts'],
            'actor_type' => 'auto',
            'actor_id'   => 0,
            'actor_name' => 'Automatic',
            'category'   => 'rooms',
            'icon'       => $icon,
            'tone'       => $tone,
            'text'       => $text,
            'meta'       => act_rule_label($r['rule_applied']),
            'tab'        => 'rooms',
        ];
    }
}

usort($items, static function ($a, $b) {
    return $b['ts'] <=> $a['ts'];
});

$page     = array_slice($items, $offset, $PAGE);
$has_more = count($items) > $offset + $PAGE;

// ── Totals: how many actions one admin has logged (shown in the Admins panel) ─
$totals = null;
if ($offset === 0 && $actor_mode === 'admin' && $actor_id > 0) {
    $sql = 'SELECT COUNT(*) AS c FROM tbl_admin_activity WHERE admin_id = ?';
    $types = 'i';
    $params = [$actor_id];
    if (!$is_super) {
        $sql .= " AND (category IN ('request','room') OR admin_id = ?)";
        $types .= 'i';
        $params[] = $viewer;
    }
    $row = act_rows($conn, $sql, $types, $params);
    $totals = ['all' => (int)($row[0]['c'] ?? 0)];
}

act_respond(200, 'success', 'OK', [
    'items'    => $page,
    'has_more' => $has_more,
    'totals'   => $totals,
]);
