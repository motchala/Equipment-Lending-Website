<?php

declare(strict_types=1);

/**
 * imports.php
 *
 * Everything the Settings -> Imports tab reads, behind ONE password unlock.
 * POST only (form-encoded), always: logged-in Super Admin + CSRF token.
 *
 *   action=status    { unlocked, remaining }                 (touch=1 refreshes an open unlock)
 *   action=unlock    current_password -> opens the unlock (10 min, sliding)
 *   action=lock      closes it
 *   -- everything below needs the unlock; otherwise HTTP 403 { code: "locked" } --
 *   action=overview  counts + last import for the faculty list and for the rooms assigned
 *   action=list      kind=faculty|rooms -> the imported entries
 *   action=files     kind=faculty|rooms -> history of original files
 *   action=file      kind, id, disposition=inline|attachment -> streams the original file
 *
 * Importing itself is done by account-management/api/upload-faculty-registry(-file).php /
 * parse-faculty-registry-xlsx.php (Faculty Registry) and settings/api/rooms-import.php (Rooms Assigned),
 * which apply the same unlock check.
 * (The Equipment Inventory card on the Imports tab is a placeholder: no import is built yet.)
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../account-management/core/faculty-registry-files.php';
require_once __DIR__ . '/../core/rooms-import.php';
require_once __DIR__ . '/../../shared/core/imports-lock.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[imports] uncaught: ' . $e->getMessage());
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

/** The lists Imports manages (the faculty registry and the rooms assigned). */
function imp_kind(string $kind): ?array
{
    if ($kind === 'faculty') {
        return [
            'files_table' => 'tbl_faculty_registry_files',
            'ensure'      => 'frf_ensure_table',
            'path'        => 'frf_stored_path',
            'fallback'    => 'faculty-registry',
        ];
    }
    if ($kind === 'rooms') {
        return [
            'files_table' => 'tbl_rooms_import_files',
            'ensure'      => 'ria_ensure_table',
            'path'        => 'ria_stored_path',
            'fallback'    => 'rooms-assigned',
        ];
    }
    return null;
}

function imp_table_exists(mysqli $conn, string $table): bool
{
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return (bool)($r && $r->num_rows > 0);
}

$action = (string)($_POST['action'] ?? '');

switch ($action) {

    case 'status':
        if (!empty($_POST['touch'])) {
            imports_touch();
        }
        frf_respond(200, [
            'status'    => 'success',
            'unlocked'  => imports_is_unlocked(),
            'remaining' => imports_remaining(),
            'ttl'       => IMPORTS_UNLOCK_TTL,
        ]);

    case 'unlock':
        frf_verify_password($conn, (string)($_POST['current_password'] ?? ''));   // 5 wrong tries -> 5 min cool-down
        imports_unlock();
        require_once __DIR__ . '/../../shared/core/activity-logger.php';
        admin_log_activity($conn, 'settings', 'Unlocked the Imports tab');
        frf_respond(200, [
            'status'    => 'success',
            'unlocked'  => true,
            'remaining' => imports_remaining(),
            'ttl'       => IMPORTS_UNLOCK_TTL,
        ]);

    case 'lock':
        imports_lock();
        frf_respond(200, ['status' => 'success', 'unlocked' => false, 'remaining' => 0, 'ttl' => IMPORTS_UNLOCK_TTL]);
}

// ── Everything below needs the unlock ───────────────────────────────────────
imports_require_unlocked();

switch ($action) {

    case 'overview':
        frf_ensure_table($conn);
        $out = [];

        // Faculty
        $f = ['count' => 0, 'extra' => 0, 'last_at' => '', 'last_by' => '', 'files' => 0, 'last_file' => '', 'rows' => 0];
        if (imp_table_exists($conn, 'tbl_faculty_registry')) {
            $q = $conn->query('SELECT COUNT(*) AS c, COALESCE(SUM(is_newly_hired), 0) AS nh, MAX(uploaded_at) AS last_at FROM tbl_faculty_registry');
            if ($q && ($r = $q->fetch_assoc())) {
                $f['count']   = (int)$r['c'];
                $f['extra']   = (int)$r['nh'];     // newly hired
                $f['last_at'] = (string)($r['last_at'] ?? '');
            }
            if ($f['count'] > 0) {
                $q = $conn->query('SELECT uploaded_by FROM tbl_faculty_registry ORDER BY uploaded_at DESC, id DESC LIMIT 1');
                if ($q && ($r = $q->fetch_assoc())) {
                    $f['last_by'] = (string)$r['uploaded_by'];
                }
            }
        }
        $q = $conn->query('SELECT COUNT(*) AS c FROM tbl_faculty_registry_files');
        $f['files'] = $q ? (int)$q->fetch_assoc()['c'] : 0;
        $q = $conn->query("SELECT original_name, row_count FROM tbl_faculty_registry_files WHERE status = 'imported' ORDER BY imported_at DESC, id DESC LIMIT 1");
        $r = $q ? $q->fetch_assoc() : null;
        $f['last_file'] = $r ? (string)$r['original_name'] : '';
        $f['rows']      = $r ? (int)($r['row_count'] ?? 0) : 0;
        $out['faculty'] = $f;

        // Rooms assigned: count = rooms that have classes, extra = faculty with classes, rows = rows in the last file,
        // chip_count = weekly class meetings (what the card's badge counts)
        ria_ensure_table($conn);
        $rm = ['count' => 0, 'extra' => 0, 'rows' => 0, 'chip_count' => 0, 'last_at' => '', 'last_by' => '', 'files' => 0, 'last_file' => ''];
        if (imp_table_exists($conn, 'tbl_class_schedule')) {
            $q = $conn->query("SELECT COUNT(*) AS m, COUNT(DISTINCT room_code) AS r, COUNT(DISTINCT NULLIF(name_key, '')) AS f, MAX(created_at) AS last_at FROM tbl_class_schedule");
            if ($q && ($r = $q->fetch_assoc())) {
                $rm['chip_count'] = (int)$r['m'];
                $rm['count']      = (int)$r['r'];
                $rm['extra']      = (int)$r['f'];
                $rm['last_at']    = (string)($r['last_at'] ?? '');
            }
        }
        $q = $conn->query('SELECT COUNT(*) AS c FROM tbl_rooms_import_files');
        $rm['files'] = $q ? (int)$q->fetch_assoc()['c'] : 0;
        $q = $conn->query("SELECT original_name, row_count, uploaded_by, imported_at FROM tbl_rooms_import_files WHERE status = 'imported' ORDER BY imported_at DESC, id DESC LIMIT 1");
        if ($q && ($r = $q->fetch_assoc())) {
            $rm['last_file'] = (string)$r['original_name'];
            $rm['rows']      = (int)($r['row_count'] ?? 0);
            $rm['last_by']   = (string)$r['uploaded_by'];
            if (!empty($r['imported_at'])) {
                $rm['last_at'] = (string)$r['imported_at'];
            }
        }
        $out['rooms'] = $rm;

        frf_respond(200, ['status' => 'success', 'remaining' => imports_remaining()] + $out);

    case 'list':
        $kind = (string)($_POST['kind'] ?? '');
        $entries = [];
        if ($kind === 'faculty') {
            if (imp_table_exists($conn, 'tbl_faculty_registry')) {
                $res = $conn->query(
                    'SELECT faculty_id, full_name, department, is_newly_hired
                       FROM tbl_faculty_registry
                      ORDER BY full_name ASC
                      LIMIT 10000'
                );
                while ($res && ($row = $res->fetch_assoc())) {
                    $entries[] = [
                        'faculty_id'     => (string)($row['faculty_id'] ?? ''),
                        'full_name'      => (string)$row['full_name'],
                        'department'     => (string)($row['department'] ?? ''),
                        'is_newly_hired' => (bool)$row['is_newly_hired'],
                    ];
                }
            }
        } elseif ($kind === 'rooms') {
            if (imp_table_exists($conn, 'tbl_class_schedule')) {
                $entries = ria_list_entries($conn);
            }
        } else {
            frf_respond(422, ['status' => 'error', 'code' => 'input', 'error' => 'Unknown list.']);
        }
        frf_respond(200, ['status' => 'success', 'total' => count($entries), 'entries' => $entries, 'remaining' => imports_remaining()]);

    case 'files':
        $k = imp_kind((string)($_POST['kind'] ?? ''));
        if ($k === null) {
            frf_respond(422, ['status' => 'error', 'code' => 'input', 'error' => 'Unknown list.']);
        }
        ($k['ensure'])($conn);
        $files = [];
        $res = $conn->query(
            'SELECT id, original_name, stored_name, mime_type, file_size, import_mode, row_count,
                    inserted_count, updated_count, skipped_count, status, uploaded_by, uploaded_at, imported_at
               FROM ' . $k['files_table'] . '
              ORDER BY uploaded_at DESC, id DESC
              LIMIT 500'
        );
        while ($res && ($row = $res->fetch_assoc())) {
            $path = ($k['path'])((string)$row['stored_name']);
            $files[] = [
                'id'          => (int)$row['id'],
                'name'        => (string)$row['original_name'],
                'size'        => (int)$row['file_size'],
                'mime'        => (string)$row['mime_type'],
                'status'      => (string)$row['status'],
                'import_mode' => (string)$row['import_mode'],
                'rows'        => $row['row_count'] === null ? null : (int)$row['row_count'],
                'inserted'    => $row['inserted_count'] === null ? null : (int)$row['inserted_count'],
                'updated'     => $row['updated_count'] === null ? null : (int)$row['updated_count'],
                'skipped'     => $row['skipped_count'] === null ? null : (int)$row['skipped_count'],
                'uploaded_by' => (string)$row['uploaded_by'],
                'uploaded_at' => (string)$row['uploaded_at'],
                'imported_at' => $row['imported_at'] === null ? null : (string)$row['imported_at'],
                'available'   => $path !== null && is_file($path),
            ];
        }
        frf_respond(200, ['status' => 'success', 'total' => count($files), 'files' => $files, 'remaining' => imports_remaining()]);

    case 'file':
        $k = imp_kind((string)($_POST['kind'] ?? ''));
        $file_id = (int)($_POST['id'] ?? 0);
        if ($k === null || $file_id <= 0) {
            frf_respond(422, ['status' => 'error', 'code' => 'input', 'error' => 'No file selected.']);
        }
        ($k['ensure'])($conn);
        $row = null;
        if ($st = $conn->prepare('SELECT original_name, stored_name, mime_type, sha256 FROM ' . $k['files_table'] . ' WHERE id = ? LIMIT 1')) {
            $st->bind_param('i', $file_id);
            $st->execute();
            $r = $st->get_result();
            $row = ($r && $r->num_rows === 1) ? $r->fetch_assoc() : null;
            $st->close();
        }
        if (!$row) {
            frf_respond(404, ['status' => 'error', 'code' => 'missing', 'error' => 'That file is not in the records.']);
        }
        $path = ($k['path'])((string)$row['stored_name']);
        if ($path === null || !is_file($path)) {
            frf_respond(404, ['status' => 'error', 'code' => 'missing', 'error' => 'The stored copy of this file is missing from the server.']);
        }
        if (!hash_equals((string)$row['sha256'], (string)hash_file('sha256', $path))) {
            error_log('[imports] fingerprint mismatch for ' . $k['files_table'] . ' id ' . $file_id);
            frf_respond(409, ['status' => 'error', 'code' => 'altered', 'error' => 'This stored file no longer matches the record made when it was imported, so it was not opened.']);
        }

        $name   = (string)$row['original_name'];
        $mime   = (string)$row['mime_type'];
        $inline = (($_POST['disposition'] ?? '') === 'inline') && $mime === 'application/pdf';
        $ascii  = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: $k['fallback'];

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;

    default:
        frf_respond(422, ['status' => 'error', 'code' => 'input', 'error' => 'Unknown action.']);
}
