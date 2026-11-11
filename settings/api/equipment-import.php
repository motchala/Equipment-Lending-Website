<?php

declare(strict_types=1);

/**
 * equipment-import.php
 *
 * Settings -> Imports -> "Equipment Inventory". The equipment list is not uploaded as a file:
 * it is read from a Google Sheet that the Super Admin links by URL ("Anyone with the link can view").
 * This endpoint is everything that card does.
 *
 * POST application/x-www-form-urlencoded (always): logged-in Super Admin + CSRF token + Imports unlocked.
 *
 *   action=overview        -> { equipment: { url, last_sync, items, categories, archived, campuses[] } }
 *   action=save_url        url                      saves (or clears) the Google Sheets link
 *   action=sync            runs the sync now        -> { summary: { added, updated, archived, skipped_sheets[], errors[] } }
 *   action=categories      campus_id                -> { categories: [ { category_id, name, items } ] }
 *   action=category_add    campus_id, name          adds a category to that campus
 *   action=category_delete category_id              removes it (blocked while active items still use it)
 *   action=list                                     -> { entries[], total }  the items the sheet brought in
 *
 * The sync itself is NOT re-implemented here. It is equipment-booking/api/sync-inventory.php, the same
 * code the live background poller runs, so "Sync Now" here and the automatic sync can never disagree.
 * This file only adds the gates in front of it (CSRF + Imports unlock) and writes the activity log.
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../account-management/core/faculty-registry-files.php';   // frf_respond()
require_once __DIR__ . '/../../shared/core/imports-lock.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[equipment-import] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'code' => 'server', 'error' => 'Something went wrong. Please try again.']);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    frf_respond(405, ['status' => 'error', 'code' => 'method', 'error' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    frf_respond(401, ['status' => 'error', 'code' => 'unauthorized', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    frf_respond(403, ['status' => 'error', 'code' => 'forbidden', 'error' => 'Super Admin only.']);
}
imports_require_unlocked();   // Settings -> Imports must be unlocked with the Super Admin's password

require_once __DIR__ . '/../../shared/core/activity-logger.php';

/** One value from tbl_sync_config ('' when the key or the table is missing). */
function eqi_config(mysqli $conn, string $key): string
{
    $stmt = $conn->prepare('SELECT value FROM tbl_sync_config WHERE config_key = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['value'] ?? ''));
}

function eqi_count(mysqli $conn, string $sql): int
{
    $r = $conn->query($sql);
    $row = $r ? $r->fetch_row() : null;
    return (int)($row[0] ?? 0);
}

function eqi_campus_exists(mysqli $conn, int $campus_id): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM tbl_campuses WHERE campus_id = ? LIMIT 1');
    $stmt->bind_param('i', $campus_id);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $ok;
}

$action = (string)($_POST['action'] ?? '');

switch ($action) {

    // ── Numbers and settings shown on the card ───────────────────────────────
    case 'overview':
        $campuses = [];
        $cq = $conn->query('SELECT campus_id, campus_name FROM tbl_campuses ORDER BY campus_id ASC');
        while ($cq && ($c = $cq->fetch_assoc())) {
            $campuses[] = ['campus_id' => (int)$c['campus_id'], 'campus_name' => (string)$c['campus_name']];
        }
        $last = eqi_config($conn, 'gsheet_last_sync');
        frf_respond(200, [
            'status'    => 'success',
            'remaining' => imports_remaining(),
            'equipment' => [
                'url'        => eqi_config($conn, 'gsheet_url'),
                'last_sync'  => $last,
                'items'      => eqi_count($conn, 'SELECT COUNT(*) FROM tbl_inventory WHERE synced_from_sheet = 1 AND is_archived = 0'),
                'categories' => eqi_count($conn, 'SELECT COUNT(*) FROM tbl_equipment_categories'),
                'archived'   => eqi_count($conn, 'SELECT COUNT(*) FROM tbl_inventory WHERE synced_from_sheet = 1 AND is_archived = 1'),
                'campuses'   => $campuses,
            ],
        ]);

    // ── Save (or clear) the Google Sheets link ───────────────────────────────
    case 'save_url':
        $url = trim((string)($_POST['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https://docs\.google\.com/spreadsheets/d/[a-zA-Z0-9_-]+#', $url)) {
            frf_respond(422, ['status' => 'error', 'error' => 'That does not look like a Google Sheets link. It should start with https://docs.google.com/spreadsheets/d/']);
        }
        if (strlen($url) > 1000) {
            frf_respond(422, ['status' => 'error', 'error' => 'That link is too long.']);
        }
        $stmt = $conn->prepare("INSERT INTO tbl_sync_config (config_key, value) VALUES ('gsheet_url', ?) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()");
        $stmt->bind_param('ss', $url, $url);
        $stmt->execute();
        $stmt->close();
        admin_log_activity($conn, 'equipment', $url === '' ? 'Removed the Google Sheets link from the Equipment Inventory import' : 'Saved the Google Sheets link for the Equipment Inventory import');
        frf_respond(200, ['status' => 'success', 'url' => $url, 'remaining' => imports_remaining()]);

    // ── Sync Now: the existing sync, behind the gates above ─────────────────
    case 'sync':
        ob_start();
        require __DIR__ . '/../../equipment-booking/api/sync-inventory.php';
        // sync-inventory.php exits by itself on every failure (the error JSON is already on its way to
        // the browser). Reaching this line means it finished and printed its success JSON into the buffer.
        $out = (string)ob_get_clean();
        $d = json_decode($out, true);
        if (is_array($d) && ($d['status'] ?? '') === 'success') {
            $s = $d['summary'] ?? [];
            admin_log_activity($conn, 'equipment', sprintf(
                'Synced the equipment inventory from Google Sheets (%d added, %d updated, %d archived)',
                (int)($s['added'] ?? 0), (int)($s['updated'] ?? 0), (int)($s['archived'] ?? 0)
            ));
            $d['remaining']  = imports_remaining();
            $d['last_sync']  = eqi_config($conn, 'gsheet_last_sync');
            $d['items']      = eqi_count($conn, 'SELECT COUNT(*) FROM tbl_inventory WHERE synced_from_sheet = 1 AND is_archived = 0');
            $d['categories'] = eqi_count($conn, 'SELECT COUNT(*) FROM tbl_equipment_categories');
            $d['archived']   = eqi_count($conn, 'SELECT COUNT(*) FROM tbl_inventory WHERE synced_from_sheet = 1 AND is_archived = 1');
            echo json_encode($d);
        } else {
            echo $out !== '' ? $out : json_encode(['status' => 'error', 'error' => 'The sync did not finish. Please try again.']);
        }
        exit;

    // ── Categories of one campus ────────────────────────────────────────────
    case 'categories':
        $campus_id = (int)($_POST['campus_id'] ?? 0);
        if ($campus_id <= 0) {
            frf_respond(422, ['status' => 'error', 'error' => 'Choose a campus.']);
        }
        $stmt = $conn->prepare(
            'SELECT ec.category_id, ec.name,
                    (SELECT COUNT(*) FROM tbl_inventory i
                      WHERE i.campus_id = ec.campus_id AND i.category = ec.name AND i.is_archived = 0) AS items
               FROM tbl_equipment_categories ec
              WHERE ec.campus_id = ?
              ORDER BY ec.sort_order ASC, ec.name ASC'
        );
        $stmt->bind_param('i', $campus_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$r) {
            $r['category_id'] = (int)$r['category_id'];
            $r['items']       = (int)$r['items'];
        }
        unset($r);
        frf_respond(200, ['status' => 'success', 'categories' => $rows, 'remaining' => imports_remaining()]);

    case 'category_add':
        $campus_id = (int)($_POST['campus_id'] ?? 0);
        $name      = trim((string)($_POST['name'] ?? ''));
        if ($campus_id <= 0 || $name === '') {
            frf_respond(422, ['status' => 'error', 'error' => 'Enter a category name.']);
        }
        if (mb_strlen($name) > 100) {
            frf_respond(422, ['status' => 'error', 'error' => 'A category name can be 100 characters at most.']);
        }
        if (!eqi_campus_exists($conn, $campus_id)) {
            frf_respond(422, ['status' => 'error', 'error' => 'That campus does not exist.']);
        }
        $chk = $conn->prepare('SELECT 1 FROM tbl_equipment_categories WHERE campus_id = ? AND name = ? LIMIT 1');
        $chk->bind_param('is', $campus_id, $name);
        $chk->execute();
        $exists = (bool)$chk->get_result()->fetch_row();
        $chk->close();
        if ($exists) {
            frf_respond(409, ['status' => 'error', 'error' => 'That category already exists for this campus.']);
        }
        $ins = $conn->prepare('INSERT INTO tbl_equipment_categories (campus_id, name) VALUES (?, ?)');
        $ins->bind_param('is', $campus_id, $name);
        if (!$ins->execute()) {
            $ins->close();
            frf_respond(500, ['status' => 'error', 'error' => 'Could not save the category. Please try again.']);
        }
        $new_id = (int)$conn->insert_id;
        $ins->close();
        admin_log_activity($conn, 'equipment', 'Added the equipment category "' . $name . '"');
        frf_respond(200, ['status' => 'success', 'category_id' => $new_id, 'name' => $name, 'remaining' => imports_remaining()]);

    case 'category_delete':
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($category_id <= 0) {
            frf_respond(422, ['status' => 'error', 'error' => 'Invalid category.']);
        }
        $get = $conn->prepare('SELECT campus_id, name FROM tbl_equipment_categories WHERE category_id = ? LIMIT 1');
        $get->bind_param('i', $category_id);
        $get->execute();
        $cat = $get->get_result()->fetch_assoc();
        $get->close();
        if (!$cat) {
            frf_respond(404, ['status' => 'error', 'error' => 'That category no longer exists.']);
        }
        // Same rule as before: a category that active items still use cannot be removed
        $use = $conn->prepare('SELECT COUNT(*) FROM tbl_inventory WHERE campus_id = ? AND category = ? AND is_archived = 0');
        $use->bind_param('is', $cat['campus_id'], $cat['name']);
        $use->execute();
        $in_use = (int)($use->get_result()->fetch_row()[0] ?? 0);
        $use->close();
        if ($in_use > 0) {
            frf_respond(409, ['status' => 'error', 'error' => 'Cannot delete: ' . $in_use . ' active item' . ($in_use === 1 ? '' : 's') . ' use this category. Re-assign them first.']);
        }
        $del = $conn->prepare('DELETE FROM tbl_equipment_categories WHERE category_id = ?');
        $del->bind_param('i', $category_id);
        $del->execute();
        $del->close();
        admin_log_activity($conn, 'equipment', 'Deleted the equipment category "' . $cat['name'] . '"');
        frf_respond(200, ['status' => 'success', 'remaining' => imports_remaining()]);

    // ── "Imported list": what the sheet brought in ──────────────────────────
    case 'list':
        $res = $conn->query(
            "SELECT i.item_name, i.category, i.quantity, i.`condition` AS item_condition, c.campus_name
               FROM tbl_inventory i
          LEFT JOIN tbl_campuses c ON c.campus_id = i.campus_id
              WHERE i.synced_from_sheet = 1 AND i.is_archived = 0
              ORDER BY c.campus_name ASC, i.category ASC, i.item_name ASC
              LIMIT 20000"
        );
        $entries = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $entries[] = [
                'item_name' => (string)$r['item_name'],
                'category'  => (string)$r['category'],
                'campus'    => (string)($r['campus_name'] ?? ''),
                'quantity'  => (int)$r['quantity'],
                'condition' => (string)$r['item_condition'],
            ];
        }
        frf_respond(200, ['status' => 'success', 'entries' => $entries, 'total' => count($entries), 'remaining' => imports_remaining()]);

    default:
        frf_respond(422, ['status' => 'error', 'error' => 'Unknown action.']);
}
