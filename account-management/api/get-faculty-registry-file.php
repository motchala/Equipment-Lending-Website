<?php

declare(strict_types=1);

/**
 * get-faculty-registry-file.php
 *
 * Super-Admin-only: returns ONE original imported file, for viewing in the browser
 * (PDF) or downloading. Needs the access_token handed out by
 * list-faculty-registry-files.php after the password was entered (valid 10 min).
 *
 * POST fields
 *   csrf_token     required
 *   access_token   required
 *   file_id        required
 *   disposition    optional - "inline" (PDF only) or "attachment" (default)
 *
 * Success: the file itself (Content-Type of the file, nosniff, no-store).
 * Failure: JSON { status: "error", code, error } - code "token" means the unlock
 *          expired and the password has to be entered again.
 *
 * The file is checked against the SHA-256 fingerprint recorded when it was
 * uploaded; if it was changed or damaged on the server it is not served.
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../core/faculty-registry-files.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[get-faculty-registry-file] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'code' => 'server', 'error' => 'Could not open the file. Please try again.']);
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

if (!frf_token_valid((string)($_POST['access_token'] ?? ''))) {
    frf_respond(403, ['status' => 'error', 'code' => 'token', 'error' => 'Your access has expired. Enter your password again.']);
}

$file_id = (int)($_POST['file_id'] ?? 0);
if ($file_id <= 0) {
    frf_respond(422, ['status' => 'error', 'code' => 'input', 'error' => 'No file selected.']);
}

frf_ensure_table($conn);
$row = null;
if ($st = $conn->prepare('SELECT original_name, stored_name, mime_type, file_size, sha256 FROM tbl_faculty_registry_files WHERE id = ? LIMIT 1')) {
    $st->bind_param('i', $file_id);
    $st->execute();
    $r = $st->get_result();
    $row = ($r && $r->num_rows === 1) ? $r->fetch_assoc() : null;
    $st->close();
}
if (!$row) {
    frf_respond(404, ['status' => 'error', 'code' => 'missing', 'error' => 'That file is not in the records.']);
}

$path = frf_stored_path((string)$row['stored_name']);
if ($path === null || !is_file($path)) {
    frf_respond(404, ['status' => 'error', 'code' => 'missing', 'error' => 'The stored copy of this file is missing from the server.']);
}
if (!hash_equals((string)$row['sha256'], (string)hash_file('sha256', $path))) {
    error_log('[get-faculty-registry-file] fingerprint mismatch for file id ' . $file_id);
    frf_respond(409, ['status' => 'error', 'code' => 'altered', 'error' => 'This stored file no longer matches the record made when it was imported, so it was not opened.']);
}

$name = (string)$row['original_name'];
$mime = (string)$row['mime_type'];
// Only PDFs are shown in the browser; anything else is always a download.
$inline = (($_POST['disposition'] ?? '') === 'inline') && $mime === 'application/pdf';
$ascii  = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'faculty-registry';

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
