<?php
declare(strict_types=1);
/**
 * upload-faculty-registry.php
 *
 * Accepts parsed registry rows as JSON (extracted client-side from PDF via
 * PDF.js, or from .xlsx via SimpleXLSX also running in-browser).
 *
 * POST fields (application/json body):
 *   csrf_token  string
 *   rows        array of { faculty_id: string|"NEWLY HIRED", full_name: string, department?: string }
 *   replace_all bool  — if true, clears the table first (full re-import)
 *   file_id     int   — the ORIGINAL file, already stored by upload-faculty-registry-file.php;
 *                       required, so every import is backed by the exact file it came from
 *
 * Returns: { status, inserted, updated, skipped, errors: string[] }
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function ur_respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ur_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    ur_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    ur_respond(403, ['status' => 'error', 'error' => 'Super Admin only.']);
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    ur_respond(400, ['status' => 'error', 'error' => 'Invalid request body.']);
}

// CSRF from JSON body
$submitted_token = trim((string)($body['csrf_token'] ?? ''));
$session_token   = (string)($_SESSION['csrf_token'] ?? '');
if ($session_token === '' || $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)) {
    ur_respond(403, ['status' => 'error', 'error' => 'Invalid session token. Please refresh the page.']);
}

$rows_input = $body['rows'] ?? [];
$replace_all = !empty($body['replace_all']);
$admin_name  = (string)($_SESSION['admin_name'] ?? 'Admin');

if (!is_array($rows_input) || empty($rows_input)) {
    ur_respond(422, ['status' => 'error', 'error' => 'No rows provided.']);
}
if (count($rows_input) > 5000) {
    ur_respond(422, ['status' => 'error', 'error' => 'Too many rows. Maximum is 5,000 per upload.']);
}

// The original file must have been stored first (upload-faculty-registry-file.php) so the
// import is recorded together with the exact file it came from. Checked BEFORE anything
// is changed, so a missing file never leaves a half-recorded import behind.
require_once __DIR__ . '/../core/faculty-registry-files.php';
frf_ensure_table($conn);
$file_id   = (int)($body['file_id'] ?? 0);
$file_name = '';
if ($file_id > 0 && ($fst = $conn->prepare("SELECT original_name FROM tbl_faculty_registry_files WHERE id = ? AND status = 'uploaded' LIMIT 1"))) {
    $fst->bind_param('i', $file_id);
    $fst->execute();
    $fres = $fst->get_result();
    if ($fres && $fres->num_rows === 1) {
        $file_name = (string)$fres->fetch_assoc()['original_name'];
    }
    $fst->close();
}
if ($file_name === '') {
    ur_respond(422, ['status' => 'error', 'error' => 'The original file was not saved with this import. Please refresh the page and choose the file again.']);
}

// Ensure table exists (self-healing)
$conn->query("
    CREATE TABLE IF NOT EXISTS `tbl_faculty_registry` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `faculty_id` varchar(50) DEFAULT NULL,
        `full_name` varchar(255) NOT NULL,
        `department` varchar(255) DEFAULT NULL,
        `is_newly_hired` tinyint(1) NOT NULL DEFAULT 0,
        `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
        `uploaded_by` varchar(255) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_registry_faculty_id` (`faculty_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if ($replace_all) {
    $conn->query("DELETE FROM tbl_faculty_registry");
}

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$errors   = [];

// Two prepared statements: one for rows with a real faculty_id (upsert by faculty_id),
// one for newly hired rows (upsert by full_name, since faculty_id is NULL and
// MySQL never treats two NULLs as equal in a UNIQUE constraint).
$stmt_id = $conn->prepare(
    "INSERT INTO tbl_faculty_registry (faculty_id, full_name, department, is_newly_hired, uploaded_at, uploaded_by)
     VALUES (?, ?, ?, ?, NOW(), ?)
     ON DUPLICATE KEY UPDATE
       full_name       = VALUES(full_name),
       department      = VALUES(department),
       is_newly_hired  = VALUES(is_newly_hired),
       uploaded_at     = NOW(),
       uploaded_by     = VALUES(uploaded_by)"
);
$stmt_name = $conn->prepare(
    "INSERT INTO tbl_faculty_registry (faculty_id, full_name, department, is_newly_hired, uploaded_at, uploaded_by)
     VALUES (NULL, ?, ?, ?, NOW(), ?)
     ON DUPLICATE KEY UPDATE
       department      = VALUES(department),
       is_newly_hired  = VALUES(is_newly_hired),
       uploaded_at     = NOW(),
       uploaded_by     = VALUES(uploaded_by)"
);

if (!$stmt_id || !$stmt_name) {
    ur_respond(500, ['status' => 'error', 'error' => 'Database error. Please try again.']);
}

foreach ($rows_input as $i => $row) {
    if (!is_array($row)) {
        $errors[] = "Row " . ($i + 1) . ": invalid format, skipped.";
        $skipped++;
        continue;
    }

    $full_name  = trim((string)($row['full_name'] ?? ''));
    $raw_id     = strtoupper(trim((string)($row['faculty_id'] ?? '')));
    $department = trim((string)($row['department'] ?? ''));

    if ($full_name === '') {
        $errors[] = "Row " . ($i + 1) . ": missing name, skipped.";
        $skipped++;
        continue;
    }

    // Detect "NEWLY HIRED" marker
    $is_newly_hired = (strtoupper($raw_id) === 'NEWLY HIRED' || $raw_id === '') ? 1 : 0;
    $faculty_id     = $is_newly_hired ? null : $raw_id;

    // Validate faculty ID format if present
    if ($faculty_id !== null) {
        $len = strlen($faculty_id);
        if ($len < 5 || $len > 30 || !preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $faculty_id) || !preg_match('/\d/', $faculty_id)) {
            $errors[] = "Row " . ($i + 1) . " (" . htmlspecialchars($full_name) . "): invalid Faculty ID format '" . htmlspecialchars($faculty_id) . "', skipped.";
            $skipped++;
            continue;
        }
    }

    if ($faculty_id !== null) {
        // Row has a real ID — upsert keyed on faculty_id (unique column)
        $stmt_id->bind_param('sssss', $faculty_id, $full_name, $department, $is_newly_hired, $admin_name);
        $ok = $stmt_id->execute();
        $affected = $stmt_id->affected_rows;
    } else {
        // Newly hired — upsert keyed on full_name (no unique faculty_id)
        $stmt_name->bind_param('ssss', $full_name, $department, $is_newly_hired, $admin_name);
        $ok = $stmt_name->execute();
        $affected = $stmt_name->affected_rows;
    }

    if (!$ok) {
        $errors[] = "Row " . ($i + 1) . " (" . htmlspecialchars($full_name) . "): could not save.";
        $skipped++;
        continue;
    }

    if ($affected === 1) {
        $inserted++;
    } elseif ($affected === 2) {
        $updated++;
    }
    // affected_rows === 0 means the row existed and nothing changed — counts as updated silently
}

$stmt_id->close();
$stmt_name->close();

// Record the outcome against the stored original file
$mode  = $replace_all ? 'replace' : 'merge';
$total = count($rows_input);
if ($ust = $conn->prepare(
    "UPDATE tbl_faculty_registry_files
        SET status = 'imported', import_mode = ?, row_count = ?, inserted_count = ?,
            updated_count = ?, skipped_count = ?, imported_at = NOW()
      WHERE id = ?"
)) {
    $ust->bind_param('siiiii', $mode, $total, $inserted, $updated, $skipped, $file_id);
    $ust->execute();
    $ust->close();
}

require_once __DIR__ . '/../../config/admin-activity.php';
admin_log_activity(
    $conn,
    'faculty',
    'Uploaded faculty registry: ' . $inserted . ' inserted, ' . $updated . ' updated, ' . $skipped . ' skipped'
        . ' (file: ' . mb_strimwidth($file_name, 0, 80, '…') . ($replace_all ? ', replaced the whole registry' : '') . ')'
);

// Count total entries now in the registry for the badge update
$total_after_res = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_faculty_registry");
$total_after = $total_after_res ? (int)$total_after_res->fetch_assoc()['cnt'] : 0;

ur_respond(200, [
    'status'      => 'success',
    'inserted'    => $inserted,
    'updated'     => $updated,
    'skipped'     => $skipped,
    'total_after' => $total_after,
    'errors'      => array_slice($errors, 0, 20),
]);
