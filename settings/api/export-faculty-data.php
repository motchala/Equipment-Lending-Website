<?php

/**
 * export-faculty-data.php
 *
 * Settings > Data Management > Export.
 * Sends ONE PDF, the signed-in faculty member's full activity report:
 *
 *   1. Faculty information        (never the password hash or any session / security token)
 *   2. Equipment borrowed         every request, with day, time, status and when it was returned
 *   3. Rooms & facilities reserved  every booking, with day, time, status and when it was completed
 *   4. Student-authorized transactions  what students filed with this faculty member's code
 *   5. Weekly class timetable     and
 *   6. Subjects of the semester   from the imported class schedule
 *
 * The data is read by settings/core/faculty-report.php and laid out by settings/core/faculty-report-pdf.php
 * (bundled FPDF in vendor/fpdf, no extra install). This file only does the request handling.
 *
 * POST only (with the CSRF token), and only ever for the logged-in faculty member's own data.
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

function export_fail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => $msg]);
    exit;
}

if (!isset($_SESSION['faculty_id'])) {
    export_fail(401, 'Unauthorized. Please log in again.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    export_fail(405, 'Please use the Export button in Settings.');
}

header('Content-Type: application/json');   // for the CSRF failure message; replaced by the PDF headers below
require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

// Light throttle: building the report runs several queries, so one click every few seconds is plenty.
$now_ts = time();
if (($now_ts - (int)($_SESSION['export_data_last'] ?? 0)) < 5) {
    export_fail(429, 'Please wait a few seconds before exporting again.');
}
$_SESSION['export_data_last'] = $now_ts;
$faculty_id = (string)$_SESSION['faculty_id'];
session_write_close();   // do not hold the session lock while the file is generated

date_default_timezone_set('Asia/Manila');
@set_time_limit(60);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-report.php';
require_once __DIR__ . '/../core/faculty-report-pdf.php';

try {
    $conn = getDB();
    $conn->set_charset('utf8mb4');
    $report = fr_build_report($conn, $faculty_id);
    $pdf    = fr_render_pdf($report);
} catch (Throwable $e) {
    error_log('[PUPSync export] ' . $e->getMessage());
    export_fail(500, 'Could not build your report. Please try again.');
}

// File name: the Faculty ID when there is one, else the name; letters, digits and dashes only.
$label = $report['profile']['faculty_id'] !== 'Not set yet' ? $report['profile']['faculty_id'] : $report['profile']['name'];
$label = trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label)), '-');
$filename = 'PUPSync-Activity-Report-' . ($label !== '' ? $label . '-' : '') . date('Y-m-d') . '.pdf';

header_remove('Content-Type');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
http_response_code(200);
echo $pdf;
exit;
