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

// A class-schedule workbook ("Faculty Rooms" or "Room Timetables") is read by the class-schedule reader.
// Any other workbook is an ordinary faculty list and is read exactly as before.
require_once __DIR__ . '/../../room-reservation/core/class-schedule.php';
$schedule      = null;
$schedule_kind = cs_detect_file($path);

try {
    if ($schedule_kind === 'faculty_schedule') {
        // One registry row per faculty member (their ID, or NEWLY HIRED, and the programs they teach),
        // plus a summary of the weekly class meetings that will be stored with them.
        $parsed = cs_parse_faculty_workbook($path);
        $rows = [];
        $hours = 0;
        $roomCodes = [];
        foreach ($parsed['faculty'] as $f) {
            $rows[] = [
                'faculty_id' => $f['id'] ?? 'NEWLY HIRED',
                'full_name'  => $f['name'],
                'department' => mb_strimwidth(implode(', ', $f['programs']), 0, 250, ''),
            ];
        }
        foreach ($parsed['meetings'] as $m) {
            $hours += ($m['end'] - $m['start']) / 60;
            $roomCodes[$m['room_code']] = true;
        }
        $schedule = [
            'kind'     => 'faculty_schedule',
            'meetings' => count($parsed['meetings']),
            'faculty'  => count($parsed['faculty']),
            'no_id'    => count(array_filter($parsed['faculty'], static fn($f) => $f['id'] === null)),
            'rooms'    => count($roomCodes),
            'hours'    => $hours,
            'warnings' => array_slice($parsed['warnings'], 0, 20),
        ];
    } elseif ($schedule_kind === 'room_timetable') {
        // No faculty rows: this workbook supplies room details and the classes that have no instructor yet.
        $parsed = cs_parse_timetable_workbook($path);
        $rows = [];
        $tba = array_filter($parsed['meetings'], static fn($m) => $m['is_tba'] === 1);
        $schedule = [
            'kind'       => 'room_timetable',
            'rooms'      => count($parsed['rooms']),
            'unassigned' => count($tba),
            'term'       => $parsed['term'],
        ];
    } else {
        $rows = faculty_registry_parse_xlsx($path);
    }
} catch (RuntimeException $e) {
    frf_respond(422, ['status' => 'error', 'error' => $e->getMessage()]);
}

frf_respond(200, ['status' => 'success', 'rows' => $rows] + ($schedule !== null ? ['schedule' => $schedule] : []));
