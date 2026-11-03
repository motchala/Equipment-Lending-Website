<?php

declare(strict_types=1);

/**
 * list-faculty-registry-files.php
 *
 * Super-Admin-only: the history of every file imported into the Faculty Registry
 * (Faculty tab -> Faculty Registry -> "Imported files"), but ONLY after the acting
 * Super Admin re-enters their own password - same rules, same throttle as
 * view-faculty-registry.php.
 *
 * POST fields
 *   csrf_token        required
 *   current_password  required - the acting Super Admin's own password
 *
 * Returns JSON
 *   success: { status: "success", total, access_token, expires_in, files: [
 *               { id, name, size, mime, status, import_mode, rows, inserted, updated,
 *                 skipped, uploaded_by, uploaded_at, imported_at, available } ] }
 *   failure: { status: "error", code, error }   (code "bad_password" for password problems)
 *
 * access_token is what get-faculty-registry-file.php wants for View / Download,
 * so the password itself never has to stay in the page.
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../core/faculty-registry-files.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[list-faculty-registry-files] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'code' => 'server', 'error' => 'Could not load the files. Please try again.']);
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

frf_verify_password($conn, (string)($_POST['current_password'] ?? ''));

// -- Password OK ---------------------------------------------------------------------
frf_ensure_table($conn);

$files = [];
$res = $conn->query(
    'SELECT id, original_name, stored_name, mime_type, file_size, import_mode, row_count,
            inserted_count, updated_count, skipped_count, status, uploaded_by, uploaded_at, imported_at
       FROM tbl_faculty_registry_files
      ORDER BY uploaded_at DESC, id DESC
      LIMIT 500'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $path = frf_stored_path((string)$row['stored_name']);
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
}

frf_respond(200, [
    'status'       => 'success',
    'total'        => count($files),
    'access_token' => frf_issue_token(),
    'expires_in'   => FRF_TOKEN_TTL,
    'files'        => $files,
]);
