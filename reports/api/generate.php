<?php

declare(strict_types=1);

/**
 * reports/api/generate.php
 *
 * Streams a generated report as a PDF or XLSX download.
 *
 * POST params:
 *   report_type   string  — one of RPT_TYPES (see reports/core/data.php)
 *   date_from     string  — YYYY-MM-DD or empty
 *   date_to       string  — YYYY-MM-DD or empty
 *   format        string  — 'pdf' or 'xlsx'
 *   csrf_token    string
 *
 * On success  : streams the file (Content-Disposition: attachment).
 * On error    : HTTP 4xx/500 + JSON { status:'error', message:string }.
 */

ob_start();
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

function rpt_api_fail(int $code, string $msg): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rpt_api_fail(405, 'POST required.');
}

// Auth: Admin or Super Admin
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    rpt_api_fail(401, 'Admin access required.');
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    rpt_api_fail(403, 'Invalid or expired session. Please refresh and try again.');
}

require_once __DIR__ . '/../../reports/core/data.php';

// Validate report type
$type = trim((string)($_POST['report_type'] ?? ''));
if (!in_array($type, RPT_TYPES, true)) {
    rpt_api_fail(400, 'Unknown report type.');
}

// Validate format
$format = trim((string)($_POST['format'] ?? 'pdf'));
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    rpt_api_fail(400, 'Format must be pdf or xlsx.');
}

// Validate dates
$from = trim((string)($_POST['date_from'] ?? ''));
$to   = trim((string)($_POST['date_to']   ?? ''));
$from = $from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
$to   = $to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)   ? $to   : null;

if ($from !== null && $to !== null && $from > $to) {
    rpt_api_fail(400, 'Start date cannot be after end date.');
}

require_once __DIR__ . '/../../config/db.php';
date_default_timezone_set('Asia/Manila');

$conn = getDB();
$conn->set_charset('utf8mb4');

$data = rpt_generate($conn, $type, $from, $to);
if ($data === null) {
    rpt_api_fail(500, 'Failed to generate report data.');
}

// Safe filename
$slug    = preg_replace('/[^a-z0-9]+/', '-', strtolower($data['title']));
$dated   = date('Y-m-d');
$ext     = $format === 'xlsx' ? 'xlsx' : 'pdf';
$filename = 'pupsync-' . $slug . '-' . $dated . '.' . $ext;

try {
    if ($format === 'xlsx') {
        require_once __DIR__ . '/../../reports/core/xlsx.php';
        $bytes = rpt_render_xlsx($data);
        $mime  = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } else {
        require_once __DIR__ . '/../../reports/core/pdf.php';
        $bytes = rpt_render_pdf($data);
        $mime  = 'application/pdf';
    }
} catch (Throwable $e) {
    error_log('[reports/generate] ' . $e->getMessage());
    rpt_api_fail(500, 'Could not render the report. Please try again.');
}

while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: no-store');
echo $bytes;
exit;