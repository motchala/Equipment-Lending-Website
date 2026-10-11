<?php
/**
 * admin-activity.php — activity history of ONE admin account (Super Admin only).
 *
 *   GET  id        (int)    tbl_accounts.id of the admin
 *        filter    (string) all | requests | equipment | accounts | signins   (default all)
 *        offset    (int)    rows to skip   (default 0)
 *
 * Returns { status, items:[{ts, category, icon, tone, text}], has_more, totals:{...} }
 *
 * Sources, merged newest-first:
 *   • tbl_admin_activity  – sign-ins, equipment and account changes (see config/admin-activity.php)
 *   • tbl_arbitration_log – request approvals / declines / QR returns (override_by = admin name)
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
if (($_SESSION['admin_role'] ?? '') !== 'Super Admin') {
    act_respond(403, 'error', 'Only a Super Admin can view admin activity.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    act_respond(405, 'error', 'Method not allowed.');
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/admin-activity.php';
$conn = getDB();

$PAGE = 12;
$admin_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$offset   = max(0, (int)($_GET['offset'] ?? 0));
$filter   = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'requests', 'equipment', 'accounts', 'signins'], true) || !$admin_id || $admin_id < 1) {
    act_respond(422, 'error', 'Invalid request.');
}

// Who is this admin? (request decisions are matched by name)
$admin_name = null;
if ($st = $conn->prepare('SELECT fullName FROM tbl_accounts WHERE id = ? LIMIT 1')) {
    $st->bind_param('i', $admin_id);
    $st->execute();
    $st->bind_result($n);
    if ($st->fetch()) {
        $admin_name = (string)$n;
    }
    $st->close();
}
if ($admin_name === null) {
    act_respond(404, 'error', 'That account no longer exists.');
}

admin_activity_ensure_table($conn);

/** Map a filter chip to the tbl_admin_activity categories it covers. */
function act_categories(string $filter): array
{
    switch ($filter) {
        case 'equipment': return ['equipment'];
        case 'accounts':  return ['account', 'faculty'];
        case 'signins':   return ['login'];
        case 'requests':  return [];
        default:          return ['equipment', 'account', 'faculty', 'login'];
    }
}

$want  = $offset + $PAGE + 1; // enough from each source to know whether more exists
$items = [];

// ── Source 1: tbl_admin_activity ─────────────────────────────────────────────
$cats = act_categories($filter);
if ($cats) {
    $in  = implode(',', array_fill(0, count($cats), '?'));
    $sql = "SELECT category, summary, UNIX_TIMESTAMP(created_at) AS ts
              FROM tbl_admin_activity
             WHERE admin_id = ? AND category IN ($in)
             ORDER BY created_at DESC, id DESC
             LIMIT ?";
    if ($st = $conn->prepare($sql)) {
        $types  = 'i' . str_repeat('s', count($cats)) . 'i';
        $params = array_merge([$admin_id], $cats, [$want]);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $cat = $r['category'];
            $icon = 'history';
            $tone = 'neutral';
            if ($cat === 'login')     { $icon = 'login'; }
            if ($cat === 'equipment') { $icon = 'inventory_2'; }
            if ($cat === 'account')   { $icon = 'manage_accounts'; }
            if ($cat === 'faculty')   { $icon = 'group'; }
            if (stripos($r['summary'], 'delet') !== false) { $icon = 'delete'; $tone = 'bad'; }
            if (stripos($r['summary'], 'locked') === 0)    { $icon = 'lock'; $tone = 'warn'; }
            if (stripos($r['summary'], 'unlocked') === 0)  { $icon = 'lock_open'; }
            $items[] = [
                'ts'       => (int)$r['ts'],
                'category' => $cat === 'faculty' ? 'account' : $cat,
                'icon'     => $icon,
                'tone'     => $tone,
                'text'     => (string)$r['summary'],
            ];
        }
        $st->close();
    }
}

// ── Source 2: request decisions and returns (tbl_arbitration_log) ────────────
if ($filter === 'all' || $filter === 'requests') {
    $sql = "SELECT request_id, borrower_name, equipment_name, decision, UNIX_TIMESTAMP(created_at) AS ts
              FROM tbl_arbitration_log
             WHERE override_by = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?";
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('si', $admin_name, $want);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $d = (string)$r['decision'];
            $who = trim((string)$r['borrower_name']);
            $eq  = trim((string)$r['equipment_name']);
            if ($d === 'Returned') {
                $text = 'Marked ' . $eq . ' as returned' . ($who !== '' ? ' (' . $who . ')' : '');
                $icon = 'assignment_return';
                $tone = 'ok';
            } elseif ($d === 'Declined') {
                $text = 'Declined request #' . (int)$r['request_id'] . ': ' . $eq . ($who !== '' ? ' for ' . $who : '');
                $icon = 'cancel';
                $tone = 'bad';
            } elseif ($d === 'Approved') {
                $text = 'Approved request #' . (int)$r['request_id'] . ': ' . $eq . ($who !== '' ? ' for ' . $who : '');
                $icon = 'check_circle';
                $tone = 'ok';
            } else {
                $text = $d . ' request #' . (int)$r['request_id'] . ': ' . $eq;
                $icon = 'assignment';
                $tone = 'neutral';
            }
            $items[] = ['ts' => (int)$r['ts'], 'category' => 'requests', 'icon' => $icon, 'tone' => $tone, 'text' => $text];
        }
        $st->close();
    }
}

usort($items, static function ($a, $b) {
    return $b['ts'] <=> $a['ts'];
});

$page     = array_slice($items, $offset, $PAGE);
$has_more = count($items) > $offset + $PAGE;

// ── Totals (only on the first page; used by the detail header) ───────────────
$totals = null;
if ($offset === 0) {
    $total = 0;
    if ($st = $conn->prepare('SELECT COUNT(*) FROM tbl_admin_activity WHERE admin_id = ?')) {
        $st->bind_param('i', $admin_id);
        $st->execute();
        $st->bind_result($c);
        $st->fetch();
        $total += (int)$c;
        $st->close();
    }
    if ($st = $conn->prepare('SELECT COUNT(*) FROM tbl_arbitration_log WHERE override_by = ?')) {
        $st->bind_param('s', $admin_name);
        $st->execute();
        $st->bind_result($c);
        $st->fetch();
        $total += (int)$c;
        $st->close();
    }
    $totals = ['all' => $total];
}

act_respond(200, 'success', 'OK', [
    'items'    => $page,
    'has_more' => $has_more,
    'totals'   => $totals,
]);
