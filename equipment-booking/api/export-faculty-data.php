<?php

/**
 * export-faculty-data.php
 *
 * Settings > Data Management > Export Data.
 * Streams ONE Excel-friendly CSV (UTF-8 with BOM, same convention as the admin activity export)
 * containing everything the signed-in faculty member owns:
 *
 *   1. Account information        (never the password hash or any session / security token)
 *   2. Equipment borrowing history  - PAST only: Returned or Declined
 *                                     (the same definition the My Activity screen uses)
 *   3. Room reservation history     - PAST only: Approved and already finished,
 *                                     or Declined / Cancelled
 *   4. Room issue reports           - PAST only: Resolved or Dismissed
 *
 * Present data is deliberately left out: pending requests, approved-but-not-returned or overdue
 * equipment, upcoming / ongoing reservations, open issue reports and waitlist entries.
 *
 * POST only (with the CSRF token), and only for the logged-in faculty member's own data.
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

header('Content-Type: application/json');   // for the CSRF failure message; replaced by the CSV headers below
require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

// Light throttle: the export runs several queries, so one click every few seconds is plenty.
$now_ts = time();
if (($now_ts - (int)($_SESSION['export_data_last'] ?? 0)) < 5) {
    export_fail(429, 'Please wait a few seconds before exporting again.');
}
$_SESSION['export_data_last'] = $now_ts;
session_write_close();   // do not hold the session lock while the file is generated

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/faculty-id.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

$faculty_id = (string)$_SESSION['faculty_id'];
$tz         = new DateTimeZone('Asia/Manila');
$now        = new DateTimeImmutable('now', $tz);
$now_sql    = $now->format('Y-m-d H:i:s');
const EXPORT_ROW_LIMIT = 5000;

/* ── helpers ───────────────────────────────────────────────────────────── */

/** Prepared SELECT -> list of associative rows. Throws on failure (caught per section). */
function export_rows(mysqli $conn, string $sql, string $types, array $params): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/**
 * Make a value safe for a spreadsheet cell: text that starts with = + - @ could be run as a
 * formula by Excel, so it gets a leading apostrophe. Plain numbers / phone numbers are left alone.
 */
function export_cell($v): string
{
    $s = trim((string)$v);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^[=+\-@\t\r]/', $s) && !preg_match('/^[+\-]?[\d\s().\-]+$/', $s)) {
        return "'" . $s;
    }
    return $s;
}

function export_date($v): string
{
    $s = trim((string)$v);
    return ($s === '' || str_starts_with($s, '0000')) ? '' : substr($s, 0, 10);
}

function export_time($v): string
{
    $s = trim((string)$v);
    return $s === '' ? '' : substr($s, 0, 5);
}

function export_datetime($v): string
{
    $s = trim((string)$v);
    return ($s === '' || str_starts_with($s, '0000')) ? '' : substr($s, 0, 16);
}

function export_line($out, array $cells): void
{
    fputcsv($out, array_map('export_cell', $cells), ',', '"', '\\');
}

function export_blank($out): void
{
    fputcsv($out, [], ',', '"', '\\');
}

/** Section title, column headers and rows (or a one-line "nothing here" note). */
function export_section($out, string $title, array $headers, array $rows, string $empty): void
{
    export_blank($out);
    export_line($out, [$title . ' (' . count($rows) . ')']);
    if (!$rows) {
        export_line($out, [$empty]);
        return;
    }
    export_line($out, $headers);
    foreach ($rows as $r) {
        export_line($out, $r);
    }
}

/* ── load the data ─────────────────────────────────────────────────────── */

$account    = [];
$equipment  = [];
$rooms      = [];
$issues     = [];
$load_error = [];

try {
    $u = export_rows($conn, 'SELECT * FROM tbl_users WHERE faculty_id = ? LIMIT 1', 's', [$faculty_id])[0] ?? null;
    if (!$u) {
        export_fail(404, 'Account not found.');
    }

    $org_name = '';
    if (!empty($u['organization_id'])) {
        $o = export_rows($conn, 'SELECT name FROM tbl_organizations WHERE id = ? LIMIT 1', 'i', [(int)$u['organization_id']]);
        $org_name = (string)($o[0]['name'] ?? '');
    }

    // Whitelisted columns only, so the password hash can never end up in the file.
    $g = static fn(string $k): string => trim((string)($u[$k] ?? ''));
    $account = [
        ['Full Name',                $g('fullname')],
        ['Faculty ID',               faculty_id_display($faculty_id, 'Not set')],
        ['Email Address',            $g('email')],
        ['Backup Email',             $g('backup_email')],
        ['Department',               $g('department')],
        ['Position / Rank',          $g('faculty_rank')],
        ['Account Role',             $g('role')],
        ['Organization',             $org_name],
        ['Mobile Number',            $g('phone')],
        ['Landline',                 $g('landline')],
        ['Date of Birth',            export_date($g('dob'))],
        ['Gender',                   $g('gender')],
        ['Nationality',              $g('nationality')],
        ['Present Address',          $g('present_address')],
        ['Permanent Address',        $g('permanent_address')],
        ['Emergency Contact Name',   $g('emergency_name')],
        ['Emergency Contact Relationship', $g('emergency_relationship')],
        ['Emergency Contact Phone',  $g('emergency_phone')],
        ['Password Last Changed',    export_datetime($g('last_password_change'))],
    ];
} catch (Throwable $e) {
    error_log('[PUPSync export] account: ' . $e->getMessage());
    export_fail(500, 'Could not read your account details. Please try again.');
}

// Equipment: Returned or Declined (My Activity "History"). Includes requests a student filed with
// this faculty member's code, marked with the student's name, exactly like My Activity does.
try {
    $rows = export_rows(
        $conn,
        "SELECT id, equipment_name, borrow_qty, request_date, borrow_date, borrow_time, return_date,
                return_time, returned_at, status, instructor, room, submitted_as, submitted_by_name, reason
           FROM tbl_requests
          WHERE faculty_id = ? AND status IN ('Returned','Declined','Cancelled')
          ORDER BY request_date DESC, id DESC
          LIMIT " . EXPORT_ROW_LIMIT,
        's',
        [$faculty_id]
    );
    foreach ($rows as $r) {
        $equipment[] = [
            $r['id'],
            $r['equipment_name'],
            (int)($r['borrow_qty'] ?? 1) ?: 1,
            export_datetime($r['request_date']),
            export_date($r['borrow_date']),
            export_time($r['borrow_time'] ?? ''),
            export_date($r['return_date']),
            export_time($r['return_time'] ?? ''),
            export_datetime($r['returned_at']),
            $r['status'],
            $r['instructor'],
            $r['room'],
            ($r['submitted_by_name'] ?? '') !== '' ? $r['submitted_by_name'] : '',
            ucfirst((string)($r['submitted_as'] ?? '')),
            $r['reason'],
        ];
    }
} catch (Throwable $e) {
    error_log('[PUPSync export] equipment: ' . $e->getMessage());
    $load_error[] = 'Equipment borrowing history';
}

// Rooms: finished approved bookings plus declined / cancelled ones (same rule as My Activity).
// Reservations a student filed under this faculty code are not the faculty member's own.
try {
    $rows = export_rows(
        $conn,
        "SELECT rr.id, b.name AS building_name, r.room_name,
                COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
                rr.reservation_date, rr.start_time, rr.end_time, rr.purpose, rr.attendees, rr.notes,
                rr.status, rr.reason, rr.request_date, rr.cancelled_at, rr.submitted_as
           FROM tbl_room_reservations rr
           LEFT JOIN tbl_rooms     r ON r.room_id     = rr.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = r.building_id
          WHERE rr.faculty_id = ? AND rr.submitted_as <> 'student'
            AND ( rr.status IN ('Declined','Cancelled')
                  OR (rr.status = 'Approved' AND CONCAT(rr.reservation_date, ' ', rr.end_time) <= ?) )
          ORDER BY rr.reservation_date DESC, rr.start_time DESC, rr.id DESC
          LIMIT " . EXPORT_ROW_LIMIT,
        'ss',
        [$faculty_id, $now_sql]
    );
    foreach ($rows as $r) {
        $rooms[] = [
            $r['id'],
            $r['building_name'] ?? '',
            $r['room_name'] ?? 'Removed room',
            $r['floor_label'] ?? '',
            export_date($r['reservation_date']),
            export_time($r['start_time']),
            export_time($r['end_time']),
            $r['purpose'],
            $r['attendees'],
            $r['notes'],
            $r['status'] === 'Approved' ? 'Completed' : $r['status'],
            $r['reason'],
            export_datetime($r['request_date']),
            export_datetime($r['cancelled_at']),
            ucfirst((string)($r['submitted_as'] ?? '')),
        ];
    }
} catch (Throwable $e) {
    error_log('[PUPSync export] rooms: ' . $e->getMessage());
    $load_error[] = 'Room reservation history';
}

// Room issue reports the faculty member filed that are already closed out.
try {
    $rows = export_rows(
        $conn,
        "SELECT i.id, b.name AS building_name, r.room_name, i.description, i.status, i.admin_notes,
                i.created_at, i.resolved_at
           FROM tbl_room_issues i
           LEFT JOIN tbl_rooms     r ON r.room_id     = i.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = r.building_id
          WHERE i.reported_by_id = ? AND i.status IN ('Resolved','Dismissed')
          ORDER BY i.created_at DESC, i.id DESC
          LIMIT " . EXPORT_ROW_LIMIT,
        's',
        [$faculty_id]
    );
    foreach ($rows as $r) {
        $issues[] = [
            $r['id'],
            $r['building_name'] ?? '',
            $r['room_name'] ?? 'Removed room',
            $r['description'],
            $r['status'],
            $r['admin_notes'],
            export_datetime($r['created_at']),
            export_datetime($r['resolved_at']),
        ];
    }
} catch (Throwable $e) {
    error_log('[PUPSync export] issues: ' . $e->getMessage());
    $load_error[] = 'Room issue reports';
}

/* ── write the file ────────────────────────────────────────────────────── */

$filename = 'pupsync-my-data-' . $now->format('Y-m-d') . '.csv';

header_remove('Content-Type');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
http_response_code(200);

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly

export_line($out, ['PUPSync - My Data Export']);
export_line($out, ['Exported on', $now->format('Y-m-d H:i') . ' (Philippine time)']);
export_line($out, ['Exported by', $account[0][1] . ' (' . $account[1][1] . ')']);
export_line($out, ['What is included', 'Your account details and your PAST activity only: returned or declined equipment requests, completed / declined / cancelled room reservations, and resolved room issue reports.']);
export_line($out, ['What is NOT included', 'Anything still current: pending, approved or overdue equipment, upcoming or ongoing reservations, open issue reports and waitlist entries. Your password is never exported.']);

export_blank($out);
export_line($out, ['ACCOUNT INFORMATION']);
export_line($out, ['Field', 'Value']);
foreach ($account as $row) {
    export_line($out, $row);
}

export_section(
    $out,
    'EQUIPMENT BORROWING HISTORY',
    ['Request ID', 'Equipment', 'Quantity', 'Requested On', 'Borrow Date', 'Borrow Time', 'Return Date', 'Return Time',
     'Returned On', 'Result', 'Instructor', 'Room', 'Filed By (Student)', 'Filed As', 'Reason / Note'],
    $equipment,
    'No past equipment requests yet.'
);

export_section(
    $out,
    'ROOM RESERVATION HISTORY',
    ['Reservation ID', 'Building', 'Room', 'Floor', 'Date', 'Start', 'End', 'Purpose', 'Attendees', 'Notes',
     'Result', 'Reason', 'Requested On', 'Cancelled On', 'Filed As'],
    $rooms,
    'No past room reservations yet.'
);

export_section(
    $out,
    'ROOM ISSUE REPORTS (RESOLVED OR DISMISSED)',
    ['Report ID', 'Building', 'Room', 'What You Reported', 'Status', 'Admin Notes', 'Reported On', 'Closed On'],
    $issues,
    'No closed room issue reports.'
);

if ($load_error) {
    export_blank($out);
    export_line($out, ['NOTE', 'These sections could not be read and may be incomplete: ' . implode(', ', $load_error) . '.']);
}

fclose($out);
exit;
