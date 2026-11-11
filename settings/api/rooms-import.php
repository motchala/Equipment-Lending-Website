<?php

declare(strict_types=1);

/**
 * rooms-import.php
 *
 * Settings -> Imports -> "Rooms Assigned". Reads a Faculty Rooms workbook (one row per class:
 * Faculty, Faculty ID, Status, Room, Subject Code, Subject, Section / Block, Component, Hours / week,
 * Meeting times) - or a Room Timetables workbook for the classes that have no instructor yet - and
 * stores each weekly class meeting, so those rooms are blocked for reservations.
 *
 * This import is separate from the Faculty Registry: it never adds, changes or removes anyone on
 * the registry list.
 *
 * POST multipart/form-data (always): logged-in Super Admin + CSRF token + Imports unlocked.
 *   action=preview   file                  -> { summary, rows }       reads the file, changes NOTHING
 *   action=import    file, mode            -> { schedule, ... }       keeps the original file, then
 *                                                                       saves the class meetings
 *     mode=merge     (default) the classes of the faculty in the file are replaced, everyone else keeps theirs
 *     mode=replace   every faculty class meeting is removed first (classes with no instructor are kept)
 *
 * Counts, the imported list and the original file are served by settings/api/imports.php (kind=rooms).
 * Nothing the browser read is trusted: the server reads the uploaded file itself, both times.
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../core/rooms-import.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[rooms-import] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'error' => 'Something went wrong. Please try again.']);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    frf_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    frf_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}
// A request bigger than post_max_size makes PHP drop $_POST and $_FILES entirely.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    frf_respond(413, ['status' => 'error', 'error' => 'That file is larger than this server accepts (limit ' . ini_get('post_max_size') . '). Choose a smaller file, or ask your server administrator to raise post_max_size.']);
}

require_once __DIR__ . '/../../shared/core/imports-lock.php';
require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    frf_respond(403, ['status' => 'error', 'error' => 'Super Admin only.']);
}
imports_require_unlocked();   // Settings -> Imports must be unlocked with the Super Admin's password

$action = (string)($_POST['action'] ?? '');
if ($action !== 'preview' && $action !== 'import') {
    frf_respond(422, ['status' => 'error', 'error' => 'Unknown action.']);
}

// ── The uploaded file ──────────────────────────────────────────────────────
$f = $_FILES['file'] ?? null;
if (!is_array($f) || !isset($f['error']) || is_array($f['error'])) {
    frf_respond(422, ['status' => 'error', 'error' => 'No file was received.']);
}
switch ($f['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        frf_respond(413, ['status' => 'error', 'error' => 'That file is larger than this server accepts (limit ' . ini_get('upload_max_filesize') . '). Choose a smaller file, or ask your server administrator to raise upload_max_filesize.']);
    case UPLOAD_ERR_NO_FILE:
        frf_respond(422, ['status' => 'error', 'error' => 'No file was received.']);
    default:
        error_log('[rooms-import] upload error code ' . $f['error']);
        frf_respond(500, ['status' => 'error', 'error' => 'The file could not be uploaded. Please try again.']);
}

$tmp = (string)$f['tmp_name'];
if (!is_uploaded_file($tmp)) {
    frf_respond(400, ['status' => 'error', 'error' => 'Invalid upload.']);
}
$size = (int)($f['size'] ?? 0);
if ($size <= 0) {
    frf_respond(422, ['status' => 'error', 'error' => 'That file is empty.']);
}
if ($size > FRF_MAX_BYTES) {
    frf_respond(413, ['status' => 'error', 'error' => 'File is too large. Maximum size is 10 MB.']);
}
$orig = frf_clean_filename((string)($f['name'] ?? ''));
$ext  = strtolower((string)pathinfo($orig, PATHINFO_EXTENSION));
if ($ext !== 'xlsx') {
    frf_respond(422, ['status' => 'error', 'error' => 'Unsupported file type. Please choose an XLSX file.']);
}
$type = frf_detect_type($tmp, $ext);
if ($type === null) {
    frf_respond(422, ['status' => 'error', 'error' => "That doesn't look like a valid XLSX file."]);
}

// ── Read it (nothing is saved yet, so a bad file never changes anything) ───
require_once __DIR__ . '/../../room-reservation/core/class-schedule.php';

$kind = cs_detect_file($tmp);
if ($kind === null) {
    frf_respond(422, ['status' => 'error', 'error' => 'This workbook is not a room list. The first row needs the columns Faculty, Room and Meeting times (see the import guide).']);
}
try {
    $parsed = $kind === 'faculty_schedule' ? cs_parse_faculty_workbook($tmp) : cs_parse_timetable_workbook($tmp);
} catch (RuntimeException $e) {
    frf_respond(422, ['status' => 'error', 'error' => $e->getMessage()]);
}

if ($action === 'preview') {
    frf_respond(200, ['status' => 'success'] + ria_preview($conn, $kind, $parsed));
}

// ── Import: 1) keep the ORIGINAL file, 2) save the class meetings ──────────
$mode = (($_POST['mode'] ?? '') === 'replace' && $kind === 'faculty_schedule') ? 'replace' : 'merge';
$by   = (string)($_SESSION['admin_name'] ?? 'Admin');

ria_ensure_table($conn);
if (!ria_ensure_dir()) {
    error_log('[rooms-import] cannot write to ' . ria_dir());
    frf_respond(500, ['status' => 'error', 'error' => "The server couldn't save the file (the uploads/rooms_assigned folder is not writable). Nothing was imported."]);
}
$stored = bin2hex(random_bytes(16)) . '.xlsx';
$dest   = ria_dir() . '/' . $stored;
if (!move_uploaded_file($tmp, $dest)) {
    error_log('[rooms-import] move_uploaded_file failed for ' . $dest);
    frf_respond(500, ['status' => 'error', 'error' => "The server couldn't save the file. Nothing was imported."]);
}
@chmod($dest, 0640);
$sha  = (string)hash_file('sha256', $dest);
$mime = $type['mime'];

$file_id = 0;
if ($st = $conn->prepare(
    'INSERT INTO tbl_rooms_import_files (original_name, stored_name, mime_type, file_size, sha256, uploaded_by, uploaded_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())'
)) {
    $st->bind_param('sssiss', $orig, $stored, $mime, $size, $sha, $by);
    if ($st->execute()) {
        $file_id = (int)$conn->insert_id;
    }
    $st->close();
}
if ($file_id <= 0) {
    @unlink($dest);   // never keep a file that has no record
    error_log('[rooms-import] could not record the file: ' . $conn->error);
    frf_respond(500, ['status' => 'error', 'error' => 'Could not record the file. Nothing was imported.']);
}

try {
    $result = $kind === 'faculty_schedule'
        ? cs_import_faculty_schedule($conn, $parsed, $file_id, $mode === 'replace')
        : cs_import_timetable($conn, $parsed, $file_id);
} catch (Throwable $e) {
    error_log('[rooms-import] import failed: ' . $e->getMessage());
    if ($del = $conn->prepare('DELETE FROM tbl_rooms_import_files WHERE id = ?')) {
        $del->bind_param('i', $file_id);
        $del->execute();
        $del->close();
    }
    @unlink($dest);   // a failed import never costs the previous file and never leaves an orphan
    frf_respond(500, ['status' => 'error', 'error' => 'The class schedule could not be saved. Please try again.']);
}

// Record the outcome against the stored original file
$rowCount = $kind === 'faculty_schedule' ? (int)$parsed['rows'] : count($parsed['meetings']);
$saved    = (int)($result['meetings'] ?? $result['unassigned_classes'] ?? 0);
$skipped  = count($parsed['warnings'] ?? []);
$zero     = 0;
if ($up = $conn->prepare(
    "UPDATE tbl_rooms_import_files
        SET status = 'imported', import_mode = ?, row_count = ?, inserted_count = ?,
            updated_count = ?, skipped_count = ?, imported_at = NOW()
      WHERE id = ?"
)) {
    $up->bind_param('siiiii', $mode, $rowCount, $saved, $zero, $skipped, $file_id);
    $up->execute();
    $up->close();
}

// The import went through and its file is on record: this new file REPLACES the old one(s), so only the
// latest import's file is kept. (A Room Timetables workbook is a companion to the Faculty Rooms workbook,
// so it does not replace it.)
$old_removed = 0;
if ($kind === 'faculty_schedule') {
    $old_removed = ria_purge_other_files($conn, $file_id);
}

require_once __DIR__ . '/../../shared/core/activity-logger.php';
admin_log_activity(
    $conn,
    'rooms',
    $kind === 'faculty_schedule'
        ? 'Imported rooms assigned: ' . $saved . ' weekly class meetings for ' . $result['faculty'] . ' faculty in ' . $result['rooms'] . ' rooms'
            . ' (file: ' . mb_strimwidth($orig, 0, 80, '…') . ($mode === 'replace' ? ', replaced every class' : '') . ($old_removed > 0 ? ', replaced the previous file' : '') . ')'
        : 'Imported room timetable: ' . $saved . ' classes with no instructor yet in ' . $result['rooms'] . ' rooms (file: ' . mb_strimwidth($orig, 0, 80, '…') . ')'
);

frf_respond(200, [
    'status'   => 'success',
    'mode'     => $mode,
    'schedule' => ['kind' => $kind] + $result,
]);
