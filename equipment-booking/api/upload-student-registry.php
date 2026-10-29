<?php
declare(strict_types=1);
/**
 * upload-student-registry.php
 *
 * Super Admin imports the official student list (parsed in the browser from the AIMS
 * "Class List" PDFs). The Student Portal checks every student against this list
 * (see config/student-registry.php).
 *
 * POST (application/json):
 *   csrf_token   string
 *   rows         array of { student_no, full_name, section?, course?, year_level?, school_year?, semester? }
 *   replace_all  bool  - true: the list is replaced by this import (old entries removed)
 *                        false: merged - a Student No. already listed is updated, new ones are added
 *
 * Returns: { status, inserted, updated, skipped, total_after, sections_after, last_label, last_by, errors[] }
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

function usr_respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    usr_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    usr_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/student-registry.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    usr_respond(403, ['status' => 'error', 'error' => 'Super Admin only.']);
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    usr_respond(400, ['status' => 'error', 'error' => 'Invalid request body.']);
}

$submitted = trim((string)($body['csrf_token'] ?? ''));
$expected  = (string)($_SESSION['csrf_token'] ?? '');
if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
    usr_respond(403, ['status' => 'error', 'error' => 'Invalid session token. Please refresh the page.']);
}

$rows_input  = $body['rows'] ?? [];
$replace_all = !empty($body['replace_all']);
$admin_name  = (string)($_SESSION['admin_name'] ?? 'Admin');

if (!is_array($rows_input) || $rows_input === []) {
    usr_respond(422, ['status' => 'error', 'error' => 'No rows provided.']);
}
if (count($rows_input) > 5000) {
    usr_respond(422, ['status' => 'error', 'error' => 'Too many rows. Maximum is 5,000 per import.']);
}

// ── Validate every row BEFORE touching the table ─────────────────────────
// (so a "replace" with nothing usable can never wipe the existing list)
$clean   = [];
$skipped = 0;
$errors  = [];
$cut = static fn(string $s, int $n): string => mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? $s), 0, $n, 'UTF-8');

foreach ($rows_input as $i => $row) {
    $n = $i + 1;
    if (!is_array($row)) {
        $errors[] = "Row {$n}: invalid format, skipped.";
        $skipped++;
        continue;
    }

    $no = student_registry_normalize_no((string)($row['student_no'] ?? ''));
    if (!student_registry_is_valid_no($no)) {
        $errors[] = "Row {$n}: invalid Student No. '" . mb_substr($no, 0, 30) . "', skipped.";
        $skipped++;
        continue;
    }

    $full  = $cut((string)($row['full_name'] ?? ''), 255);
    $parts = student_registry_split_name($full);
    if ($parts === null) {
        $errors[] = "Row {$n} ({$no}): name is not in \"SURNAME, Given names\" form, skipped.";
        $skipped++;
        continue;
    }

    $clean[$no] = [   // keyed by number: a repeated Student No. in one file keeps the later row
        'student_no'  => $no,
        'full_name'   => $full,
        'last_name'   => mb_substr($parts['last'], 0, 120, 'UTF-8'),
        'given_names' => mb_substr($parts['given'], 0, 180, 'UTF-8'),
        'section'     => $cut((string)($row['section'] ?? ''), 60),
        'course'      => $cut((string)($row['course'] ?? ''), 30),
        'year_level'  => $cut((string)($row['year_level'] ?? ''), 20),
        'school_year' => $cut((string)($row['school_year'] ?? ''), 15),
        'semester'    => $cut((string)($row['semester'] ?? ''), 15),
    ];
}

if ($clean === []) {
    usr_respond(422, [
        'status' => 'error',
        'error'  => 'None of the rows could be used, so nothing was imported.',
        'errors' => array_slice($errors, 0, 20),
    ]);
}

// ── Write (all or nothing) ───────────────────────────────────────────────
student_registry_ensure_table($conn);

$inserted = 0;
$updated  = 0;

try {
    $conn->begin_transaction();

    if ($replace_all) {
        $conn->query('DELETE FROM tbl_student_registry');
    }

    $stmt = $conn->prepare(
        "INSERT INTO tbl_student_registry
            (student_no, full_name, last_name, given_names, section, course, year_level, school_year, semester, uploaded_at, uploaded_by)
         VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NOW(), ?)
         ON DUPLICATE KEY UPDATE
            full_name   = VALUES(full_name),
            last_name   = VALUES(last_name),
            given_names = VALUES(given_names),
            section     = VALUES(section),
            course      = VALUES(course),
            year_level  = VALUES(year_level),
            school_year = VALUES(school_year),
            semester    = VALUES(semester),
            uploaded_at = NOW(),
            uploaded_by = VALUES(uploaded_by)"
    );
    if (!$stmt) {
        throw new RuntimeException('prepare failed: ' . $conn->error);
    }

    foreach ($clean as $r) {
        $stmt->bind_param(
            'ssssssssss',
            $r['student_no'], $r['full_name'], $r['last_name'], $r['given_names'],
            $r['section'], $r['course'], $r['year_level'], $r['school_year'], $r['semester'],
            $admin_name
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('execute failed: ' . $stmt->error);
        }
        if ($stmt->affected_rows === 1) {
            $inserted++;
        } else {
            $updated++;          // 2 = changed, 0 = identical row re-imported
        }
    }
    $stmt->close();
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('[upload-student-registry] ' . $e->getMessage());
    usr_respond(500, ['status' => 'error', 'error' => 'The import failed and nothing was changed. Please try again.']);
}

$status = student_registry_status($conn);
$last_label = $status['last_at'] !== '' ? date('M j, Y g:i A', (int)strtotime($status['last_at'])) : '';

require_once __DIR__ . '/../../config/admin-activity.php';
admin_log_activity(
    $conn,
    'settings',
    'Imported student list: ' . $inserted . ' added, ' . $updated . ' updated, ' . $skipped . ' skipped'
        . ($replace_all ? ' (replaced the whole list)' : '')
);

usr_respond(200, [
    'status'         => 'success',
    'inserted'       => $inserted,
    'updated'        => $updated,
    'skipped'        => $skipped,
    'total_after'    => $status['count'],
    'sections_after' => $status['sections'],
    'last_label'     => $last_label,
    'last_by'        => $status['last_by'],
    'errors'         => array_slice($errors, 0, 20),
]);
