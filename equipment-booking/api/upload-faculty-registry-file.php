<?php

declare(strict_types=1);

/**
 * upload-faculty-registry-file.php
 *
 * Step 1 of a Faculty Registry import: keeps the ORIGINAL file the Super Admin
 * chose (the browser still reads the rows out of it; this stores the file
 * itself) so every import can be looked at again later.
 * Step 2 is upload-faculty-registry.php, which receives the returned file_id and
 * marks this record as imported (with the added / updated / skipped counts).
 *
 * POST multipart/form-data
 *   csrf_token  required
 *   file        required - a PDF or XLSX, up to 10 MB
 *
 * Rules enforced here (never trusted to the UI): POST only, valid CSRF token,
 * logged-in Super Admin (re-checked in the DB), and the file must really be a
 * PDF / XLSX (checked by content, not just by name). The file is stored under
 * a random name in uploads/faculty_registry/, which the web server never serves.
 *
 * Returns JSON  { status: "success", file_id, name, size }  or
 *               { status: "error", error }
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../core/faculty-registry-files.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[upload-faculty-registry-file] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'error' => 'Could not save the file. Please try again.']);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    frf_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    frf_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}

// A request bigger than post_max_size makes PHP drop $_POST and $_FILES entirely,
// which would otherwise surface as a confusing "session expired" message below.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    frf_respond(413, ['status' => 'error', 'error' => 'That file is larger than this server accepts (limit ' . ini_get('post_max_size') . '). Choose a smaller file, or ask your server administrator to raise post_max_size.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    frf_respond(403, ['status' => 'error', 'error' => 'Super Admin only.']);
}

// -- The uploaded file ---------------------------------------------------------
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
        error_log('[upload-faculty-registry-file] upload error code ' . $f['error']);
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
if ($ext !== 'pdf' && $ext !== 'xlsx') {
    frf_respond(422, ['status' => 'error', 'error' => 'Unsupported file type. Please choose a PDF or XLSX file.']);
}
$type = frf_detect_type($tmp, $ext);
if ($type === null) {
    frf_respond(422, ['status' => 'error', 'error' => "That doesn't look like a valid " . strtoupper($ext) . ' file.']);
}

// -- Store it ------------------------------------------------------------------
if (!frf_ensure_dir()) {
    error_log('[upload-faculty-registry-file] cannot write to ' . frf_dir());
    frf_respond(500, ['status' => 'error', 'error' => "The server couldn't save the file (the uploads/faculty_registry folder is not writable). Nothing was imported."]);
}
$stored = bin2hex(random_bytes(16)) . '.' . $type['ext'];
$dest   = frf_dir() . '/' . $stored;
if (!move_uploaded_file($tmp, $dest)) {
    error_log('[upload-faculty-registry-file] move_uploaded_file failed for ' . $dest);
    frf_respond(500, ['status' => 'error', 'error' => "The server couldn't save the file. Nothing was imported."]);
}
@chmod($dest, 0640);

$sha  = (string)hash_file('sha256', $dest);
$mime = $type['mime'];
$by   = (string)($_SESSION['admin_name'] ?? 'Admin');

frf_ensure_table($conn);
$st = $conn->prepare(
    'INSERT INTO tbl_faculty_registry_files (original_name, stored_name, mime_type, file_size, sha256, uploaded_by, uploaded_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())'
);
$ok = false;
$file_id = 0;
if ($st) {
    $st->bind_param('sssiss', $orig, $stored, $mime, $size, $sha, $by);
    $ok = $st->execute();
    $file_id = (int)$conn->insert_id;
    $st->close();
}
if (!$ok || $file_id <= 0) {
    @unlink($dest);   // never keep a file that has no record
    error_log('[upload-faculty-registry-file] could not record the file: ' . $conn->error);
    frf_respond(500, ['status' => 'error', 'error' => 'Could not record the file. Nothing was imported.']);
}

frf_respond(200, ['status' => 'success', 'file_id' => $file_id, 'name' => $orig, 'size' => $size]);
