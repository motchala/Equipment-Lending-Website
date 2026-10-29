<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../core/faculty-registry-files.php';
require_once __DIR__ . '/../core/faculty-registry-xlsx.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[parse-faculty-registry-xlsx] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'error' => 'Could not read the XLSX file. Please try again.']);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    frf_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    frf_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    frf_respond(413, ['status' => 'error', 'error' => 'That file is larger than this server accepts. Choose a smaller file.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');
if (!admin_is_super_admin($conn)) {
    frf_respond(403, ['status' => 'error', 'error' => 'Super Admin only.']);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
    frf_respond(422, ['status' => 'error', 'error' => 'No file was received.']);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        frf_respond(413, ['status' => 'error', 'error' => 'File is too large. Maximum size is 10 MB.']);
    }
    frf_respond(422, ['status' => 'error', 'error' => 'The XLSX file could not be uploaded. Please try again.']);
}

$path = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);
$extension = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!is_uploaded_file($path) || $extension !== 'xlsx' || $size <= 0) {
    frf_respond(422, ['status' => 'error', 'error' => 'Please choose a valid XLSX file.']);
}
if ($size > FRF_MAX_BYTES) {
    frf_respond(413, ['status' => 'error', 'error' => 'File is too large. Maximum size is 10 MB.']);
}
if (frf_detect_type($path, $extension) === null) {
    frf_respond(422, ['status' => 'error', 'error' => 'That does not look like a valid XLSX workbook.']);
}

try {
    $rows = faculty_registry_parse_xlsx($path);
} catch (RuntimeException $e) {
    frf_respond(422, ['status' => 'error', 'error' => $e->getMessage()]);
}

frf_respond(200, ['status' => 'success', 'rows' => $rows]);
