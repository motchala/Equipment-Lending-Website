<?php

declare(strict_types=1);

/**
 * settings/core/faculty-report.php
 *
 * DATA half of the faculty "Export Data" report (Settings > Data Management > Export).
 * It only READS and shapes data; the page layout lives in faculty-report-pdf.php and the
 * request handling (login, CSRF, download headers) in settings/api/export-faculty-data.php.
 *
 *   fr_build_report($conn, $faculty_id)  ->  one array holding every section of the report:
 *
 *     generated_at   'Y-m-d H:i:s' (Asia/Manila)
 *     profile        ['name', 'faculty_id', 'department', 'rank', 'photo' => path|null, 'fields' => [[label, value], ...]]
 *     equipment      rows, newest first  (EVERY status: pending, approved, on loan, overdue, returned, declined, cancelled)
 *     rooms          rows, newest first  (EVERY status: pending, upcoming, in progress, completed, declined, cancelled)
 *     students       the equipment + room rows a student filed with this faculty member's code, newest first
 *     schedule       ['term', 'meetings' => [...], 'subjects' => [...], 'weekly_minutes', 'employment']
 *     summary        the numbers for the "At a glance" strip
 *     notes          sections that could not be read (the report still prints, with a note)
 *
 * RULES (kept identical to the My Activity screen so the report and the screen never disagree)
 *   - Times are Asia/Manila. Equipment statuses: Waiting is shown "Pending"; "Returned late" is judged against
 *     the ORIGINAL return moment, so an extension never hides lateness (activity/core/faculty-data.php).
 *   - A room booking is Completed once its end time has passed, In progress while it runs, Upcoming before it
 *     starts; Waiting is "Pending" (room-reservation/core/room-quota.php).
 *   - Student-filed items (submitted_as = 'student') are the faculty member's responsibility, so they are listed
 *     in the normal tables AND again, together, in their own section.
 *   - Never exported: the password hash, remember-me / session tokens, QR claim & return tokens, student codes.
 */

require_once __DIR__ . '/../../shared/core/faculty-id.php';

const FR_ROW_LIMIT = 1000;   // per table; a note is printed when a faculty member has more than this

/* ── small helpers ─────────────────────────────────────────────────────── */

/** Prepared SELECT -> list of associative rows. Throws on failure (each section catches it). */
function fr_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

function fr_s($v): string
{
    return trim((string)($v ?? ''));
}

function fr_empty_date(string $s): bool
{
    return $s === '' || str_starts_with($s, '0000');
}

/** 'Mon, Nov 9, 2026' */
function fr_day(?string $date): string
{
    $s = fr_s($date);
    if (fr_empty_date($s)) {
        return '';
    }
    $t = strtotime(substr($s, 0, 10));
    return $t === false ? '' : date('D, M j, Y', $t);
}

/** 'Nov 9, 2026' */
function fr_date(?string $date): string
{
    $s = fr_s($date);
    if (fr_empty_date($s)) {
        return '';
    }
    $t = strtotime(substr($s, 0, 10));
    return $t === false ? '' : date('M j, Y', $t);
}

/** '7:10 PM' from 'HH:MM[:SS]' */
function fr_time(?string $time): string
{
    $s = fr_s($time);
    if ($s === '') {
        return '';
    }
    $t = strtotime('1970-01-01 ' . substr($s, 0, 8));
    return $t === false ? '' : date('g:i A', $t);
}

/** 'Nov 9, 2026, 7:10 PM' from a DATETIME */
function fr_stamp(?string $dt): string
{
    $s = fr_s($dt);
    if (fr_empty_date($s)) {
        return '';
    }
    $t = strtotime($s);
    return $t === false ? '' : date('M j, Y, g:i A', $t);
}

/** '7:10 PM – 8:10 PM' (or with the end date when it is another day). */
function fr_time_range(string $d1, string $t1, string $d2, string $t2): string
{
    $a = fr_time($t1);
    $b = fr_time($t2);
    $sameDay = ($d2 === '' || substr($d1, 0, 10) === substr($d2, 0, 10));
    if ($a === '' && $b === '') {
        return '';
    }
    if ($b === '') {
        return $a;
    }
    if ($sameDay) {
        return ($a !== '' ? $a . ' – ' : '') . $b;
    }
    return ($a !== '' ? $a . ' – ' : '') . fr_date($d2) . ', ' . $b;
}

/** Hours with at most one decimal: 1.5, 3, 4.5 */
function fr_hours(int $minutes): string
{
    $h = round($minutes / 60, 1);
    return rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.');
}

/** Name of a column-safe "where": the live room record when there is one, else the text that was typed. */
function fr_place(array $r): string
{
    $room = fr_s($r['room_name'] ?? '');
    if ($room !== '') {
        $bits = array_filter([fr_s($r['building_name'] ?? ''), fr_s($r['floor_label'] ?? '')], 'strlen');
        return $room . ($bits ? ' · ' . implode(' · ', $bits) : '');
    }
    return fr_s($r['room'] ?? '');
}

/** The student a row was filed by ('' when the faculty member filed it). */
function fr_student(array $r): array
{
    if (fr_s($r['submitted_as'] ?? '') !== 'student') {
        return ['', ''];
    }
    $name = fr_s($r['submitted_by_name'] ?? '');
    return [$name !== '' ? $name : 'Student', fr_s($r['submitted_by_id'] ?? '')];
}

/* ── 1. who the faculty member is ──────────────────────────────────────── */

function fr_load_profile(mysqli $conn, string $faculty_id, ?string $employment): array
{
    $u = fr_rows($conn, 'SELECT * FROM tbl_users WHERE faculty_id = ? LIMIT 1', 's', [$faculty_id])[0] ?? null;
    if (!$u) {
        throw new RuntimeException('account not found');
    }
    $g = static fn(string $k): string => fr_s($u[$k] ?? '');

    $phone = $g('phone');
    if ($phone !== '') {
        if (!function_exists('faculty_phone_display')) {
            require_once __DIR__ . '/faculty-helpers.php';
        }
        if (function_exists('faculty_phone_display')) {
            $pretty = faculty_phone_display($phone);
            $phone  = $pretty !== '' ? $pretty : $phone;
        }
    }

    // Whitelisted columns only, so the password hash can never reach the file.
    $fields = [
        ['Full Name',                $g('fullname')],
        ['Faculty ID',               faculty_id_display($faculty_id, 'Not set yet')],
        ['Email Address',            $g('email')],
        ['Backup Email',             $g('backup_email')],
        ['Department',               $g('department')],
        ['Position / Rank',          $g('faculty_rank')],
        ['Employment Status',        (string)$employment],
        ['Account Role',             $g('role')],
        ['Mobile Number',            $phone],
        ['Landline',                 $g('landline')],
        ['Date of Birth',            fr_date($g('dob'))],
        ['Gender',                   $g('gender')],
        ['Nationality',              $g('nationality')],
        ['Present Address',          $g('present_address')],
        ['Permanent Address',        $g('permanent_address')],
        ['Emergency Contact',        $g('emergency_name')],
        ['Emergency Relationship',   $g('emergency_relationship')],
        ['Emergency Phone',          $g('emergency_phone')],
        ['Password Last Changed',    fr_stamp($g('last_password_change'))],
    ];

    return [
        'name'       => $g('fullname'),
        'faculty_id' => faculty_id_display($faculty_id, 'Not set yet'),
        'department' => $g('department'),
        'rank'       => $g('faculty_rank'),
        'photo'      => fr_profile_photo($g('profile_picture')),
        'fields'     => $fields,
    ];
}

/**
 * Absolute path of the profile picture when it is a real JPG/PNG inside uploads/profile_pictures, else null.
 * Only the file NAME stored in the database is used, never a path, so it can't point anywhere else.
 */
function fr_profile_photo(string $stored): ?string
{
    $file = basename(str_replace('\\', '/', $stored));
    if ($file === '' || $file === '.' || $file === '..') {
        return null;
    }
    $dir  = realpath(__DIR__ . '/../../uploads/profile_pictures');
    $path = $dir !== false ? realpath($dir . DIRECTORY_SEPARATOR . $file) : false;
    if ($path === false || strncmp($path, $dir . DIRECTORY_SEPARATOR, strlen($dir) + 1) !== 0 || !is_file($path)) {
        return null;
    }
    if (filesize($path) > 3 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesize($path);
    return ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) ? $path : null;
}

/* ── 2. equipment borrowed ─────────────────────────────────────────────── */

function fr_load_equipment(mysqli $conn, string $faculty_id, DateTimeImmutable $now): array
{
    // Same optional columns My Activity guards for: older databases may not have the extension ones yet.
    $ext = 'r.extension_count, r.original_return_date, r.original_return_time';
    $chk = $conn->query("SHOW COLUMNS FROM tbl_requests LIKE 'original_return_date'");
    if (!$chk || $chk->num_rows === 0) {
        $ext = '0 AS extension_count, NULL AS original_return_date, NULL AS original_return_time';
    }

    $rows = fr_rows(
        $conn,
        "SELECT r.id, r.equipment_name, r.borrow_qty, r.room, r.request_date, r.borrow_date, r.borrow_time,
                r.return_date, r.return_time, r.claimed_at, r.returned_at, r.status, r.reason, r.submitted_as,
                r.submitted_by_name, r.submitted_by_id, $ext,
                rm.room_name, b.name AS building_name,
                COALESCE(rm.floor_label, CONCAT(rm.floor_number, 'F')) AS floor_label
           FROM tbl_requests r
           LEFT JOIN tbl_rooms     rm ON rm.room_id    = r.room_id
           LEFT JOIN tbl_buildings b  ON b.building_id = rm.building_id
          WHERE r.faculty_id = ?
          ORDER BY r.request_date DESC, r.id DESC
          LIMIT " . (FR_ROW_LIMIT + 1),
        's',
        [$faculty_id]
    );

    $out = [];
    foreach ($rows as $r) {
        $raw    = fr_s($r['status']);
        $status = $raw === 'Waiting' ? 'Pending' : ($raw !== '' ? $raw : 'Pending');

        // "Returned late": judged against the ORIGINAL return moment (an extension moves the current one).
        $late = false;
        if ($raw === 'Returned' && !fr_empty_date(fr_s($r['returned_at']))) {
            $origDate = fr_s($r['original_return_date']) !== '' ? fr_s($r['original_return_date']) : fr_s($r['return_date']);
            $origTime = fr_s($r['original_return_date']) !== '' ? fr_s($r['original_return_time']) : fr_s($r['return_time']);
            $due      = strtotime($origDate . ' ' . ($origTime !== '' ? $origTime : '23:59:59'));
            $back     = strtotime((string)$r['returned_at']);
            $late     = $due !== false && $back !== false && $back > $due;
        }
        if ($late) {
            $status = 'Returned late';
        }

        [$student, $student_id] = fr_student($r);
        $ext_n = max(0, (int)($r['extension_count'] ?? 0));
        $out[] = [
            'id'          => (int)$r['id'],
            'item'        => fr_s($r['equipment_name']),
            'qty'         => max(1, (int)($r['borrow_qty'] ?? 1)),
            'day'         => fr_day($r['borrow_date']),
            'time'        => fr_time_range(fr_s($r['borrow_date']), fr_s($r['borrow_time']), fr_s($r['return_date']), fr_s($r['return_time'])),
            'picked_up'   => fr_stamp($r['claimed_at']),
            'requested'   => fr_stamp($r['request_date']),
            'status'      => $status,
            'returned_at' => $raw === 'Returned' ? fr_stamp($r['returned_at']) : '',
            'extended'    => $ext_n,
            'reason'      => in_array($raw, ['Declined', 'Cancelled'], true) ? fr_s($r['reason']) : '',
            'where'       => fr_place($r),
            'student'     => $student,
            'student_id'  => $student_id,
            'sort'        => fr_s($r['borrow_date']) . ' ' . fr_s($r['borrow_time']),   // the day it is for (the list is by request date)
        ];
    }
    return $out;
}

/* ── 3. rooms / facilities reserved ────────────────────────────────────── */

function fr_load_rooms(mysqli $conn, string $faculty_id, DateTimeImmutable $now): array
{
    $rows = fr_rows(
        $conn,
        "SELECT rr.id, rr.reservation_date, rr.start_time, rr.end_time, rr.purpose, rr.status, rr.reason,
                rr.request_date, rr.cancelled_at, rr.submitted_as, rr.submitted_by_name, rr.submitted_by_id,
                r.room_name, b.name AS building_name,
                COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
           FROM tbl_room_reservations rr
           LEFT JOIN tbl_rooms     r ON r.room_id     = rr.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = r.building_id
          WHERE rr.faculty_id = ?
          ORDER BY rr.reservation_date DESC, rr.start_time DESC, rr.id DESC
          LIMIT " . (FR_ROW_LIMIT + 1),
        's',
        [$faculty_id]
    );

    $tz  = $now->getTimezone();
    $out = [];
    foreach ($rows as $r) {
        $raw   = fr_s($r['status']);
        $date  = fr_s($r['reservation_date']);
        $start = fr_s($r['start_time']);
        $end   = fr_s($r['end_time']);
        $endAt = $date !== '' ? date_create_immutable($date . ' ' . ($end !== '' ? $end : '23:59:59'), $tz) : false;
        $stAt  = $date !== '' ? date_create_immutable($date . ' ' . ($start !== '' ? $start : '00:00:00'), $tz) : false;

        $closed = '';                                   // "Completed / Cancelled on"
        $status = $raw;
        if ($raw === 'Approved') {
            if ($endAt && $endAt <= $now) {
                $status = 'Completed';
                $closed = fr_stamp($endAt->format('Y-m-d H:i:s'));
            } elseif ($stAt && $stAt <= $now) {
                $status = 'In progress';
            } else {
                $status = 'Upcoming';
            }
        } elseif ($raw === 'Waiting') {
            $status = 'Pending';
        } elseif ($raw === 'Cancelled') {
            $closed = fr_stamp($r['cancelled_at']);
        }

        [$student, $student_id] = fr_student($r);
        $bits = array_filter([fr_s($r['building_name']), fr_s($r['floor_label'])], 'strlen');
        $out[] = [
            'id'         => (int)$r['id'],
            'room'       => fr_s($r['room_name']) !== '' ? fr_s($r['room_name']) : 'Removed room',
            'building'   => implode(' · ', $bits),
            'day'        => fr_day($date),
            'time'       => fr_time($start) . ($end !== '' ? ' – ' . fr_time($end) : ''),
            'purpose'    => fr_s($r['purpose']),
            'status'     => $status,
            'closed_at'  => $closed,
            'closed_lbl' => $status === 'Cancelled' ? 'Cancelled' : '',
            'requested'  => fr_stamp($r['request_date']),
            'reason'     => $raw === 'Declined' ? fr_s($r['reason']) : '',   // a cancelled booking keeps its old approval note; not shown
            'student'    => $student,
            'student_id' => $student_id,
            'sort'       => $date . ' ' . $start,
        ];
    }
    return $out;
}

/* ── 4. weekly timetable + subjects ────────────────────────────────────── */

/** '1st Semester, AY 2026-2027' by the calendar month (the same rule the Facilities-tab schedule uses). */
function fr_semester_label(DateTimeImmutable $now): string
{
    $m = (int)$now->format('n');
    $y = (int)$now->format('Y');
    if ($m >= 8) {
        return '1st Semester, A.Y. ' . $y . '–' . ($y + 1);
    }
    if ($m >= 6) {
        return 'Summer, A.Y. ' . ($y - 1) . '–' . $y;
    }
    return '2nd Semester, A.Y. ' . ($y - 1) . '–' . $y;
}

function fr_load_schedule(mysqli $conn, string $faculty_id, DateTimeImmutable $now): array
{
    $meetings = [];
    $term     = '';
    $employment = '';

    $check = $conn->query("SHOW TABLES LIKE 'tbl_class_schedule'");
    if ($check && $check->num_rows > 0) {
        $rows = [];
        try {
            $rows = fr_rows(
                $conn,
                'SELECT cs.day_of_week, TIME_FORMAT(cs.start_time, "%H:%i") AS start_hm, TIME_FORMAT(cs.end_time, "%H:%i") AS end_hm,
                        TIME_TO_SEC(TIMEDIFF(cs.end_time, cs.start_time)) AS span_sec,
                        cs.subject_code, cs.subject_name, cs.section, cs.component, cs.group_label, cs.room_code,
                        cs.term_label, cs.employment,
                        rm.room_name, rm.floor_label, b.name AS building_name
                   FROM tbl_class_schedule cs
                   LEFT JOIN tbl_rooms rm ON rm.room_id = cs.room_id
                   LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
                  WHERE cs.account_faculty_id = ? AND cs.is_tba = 0
                  ORDER BY cs.day_of_week, cs.start_time',
                's',
                [$faculty_id]
            );
        } catch (Throwable $e) {
            // An older table without term_label / employment: fall back to the shared reader the Facilities tab uses.
            error_log('[PUPSync report] schedule (extended read): ' . $e->getMessage());
            require_once __DIR__ . '/../../room-reservation/core/class-schedule.php';
            $rows = cs_faculty_week_rows($conn, $faculty_id);
        }
        foreach ($rows as $c) {
            $minutes = (int)round(((int)$c['span_sec']) / 60);
            $dow     = (int)$c['day_of_week'];
            if ($minutes <= 0 || $dow < 1 || $dow > 6) {
                continue;
            }
            if ($term === '' && fr_s($c['term_label'] ?? '') !== '') {
                $term = fr_s($c['term_label']);
            }
            if ($employment === '' && fr_s($c['employment'] ?? '') !== '') {
                $employment = fr_s($c['employment']);
            }
            $bits = array_filter([fr_s($c['building_name'] ?? ''), fr_s($c['floor_label'] ?? '')], 'strlen');
            $meetings[] = [
                'dow'       => $dow,
                'start'     => fr_s($c['start_hm']),
                'end'       => fr_s($c['end_hm']),
                'minutes'   => $minutes,
                'code'      => fr_s($c['subject_code']),
                'subject'   => fr_s($c['subject_name']),
                'section'   => fr_s($c['section']),
                'component' => fr_s($c['component']),
                'group'     => fr_s($c['group_label'] ?? ''),
                'room'      => fr_s($c['room_name'] ?? '') !== '' ? fr_s($c['room_name']) : fr_s($c['room_code']),
                'building'  => implode(' · ', $bits),
            ];
        }
    }

    // One line per subject + section + component + room, with every day it meets.
    $subjects = [];
    $total    = 0;
    $days     = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
    foreach ($meetings as $m) {
        $total += $m['minutes'];
        $key = mb_strtolower($m['code'] . '|' . $m['subject'] . '|' . $m['section'] . '|' . $m['component'] . '|' . $m['group'] . '|' . $m['room']);
        if (!isset($subjects[$key])) {
            $subjects[$key] = [
                'code' => $m['code'], 'subject' => $m['subject'], 'section' => $m['section'],
                'component' => $m['component'] . ($m['group'] !== '' ? ' (' . $m['group'] . ')' : ''),
                'room' => $m['room'], 'building' => $m['building'], 'minutes' => 0, 'by_time' => [],
            ];
        }
        $subjects[$key]['minutes'] += $m['minutes'];
        $slot = fr_time($m['start']) . ' – ' . fr_time($m['end']);
        $subjects[$key]['by_time'][$slot][] = $days[$m['dow']];
    }
    foreach ($subjects as &$s) {
        $parts = [];
        foreach ($s['by_time'] as $slot => $ds) {
            $parts[] = implode(', ', $ds) . ' ' . $slot;
        }
        $s['when'] = implode("\n", $parts);
        unset($s['by_time']);
    }
    unset($s);
    $subjects = array_values($subjects);
    usort($subjects, static fn(array $a, array $b): int => [$a['code'], $a['section'], $a['component']] <=> [$b['code'], $b['section'], $b['component']]);

    return [
        'term'           => $term !== '' ? $term : fr_semester_label($now),
        'term_imported'  => $term !== '',
        'meetings'       => $meetings,
        'subjects'       => $subjects,
        'weekly_minutes' => $total,
        'employment'     => $employment,
    ];
}

/* ── the whole report ──────────────────────────────────────────────────── */

/**
 * @return array<string, mixed>   see the file header. Throws only when the account itself cannot be read.
 */
function fr_build_report(mysqli $conn, string $faculty_id): array
{
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);

    // Same housekeeping My Activity runs before it reads: expired requests are declined, overdue / started
    // states brought up to date, so the report states exactly what the screen shows right now.
    try {
        require_once __DIR__ . '/../../activity/core/faculty-data.php';
        fact_eq_housekeeping($conn);
        ps_room_expire_unreviewed($conn);
    } catch (Throwable $e) {
        error_log('[PUPSync report] housekeeping: ' . $e->getMessage());
    }

    $notes     = [];
    $truncated = [];

    $schedule = ['term' => fr_semester_label($now), 'term_imported' => false, 'meetings' => [], 'subjects' => [], 'weekly_minutes' => 0, 'employment' => ''];
    try {
        $schedule = fr_load_schedule($conn, $faculty_id, $now);
    } catch (Throwable $e) {
        error_log('[PUPSync report] schedule: ' . $e->getMessage());
        $notes[] = 'Weekly schedule and subjects';
    }

    $profile = fr_load_profile($conn, $faculty_id, $schedule['employment']);   // throws when the account is missing

    $equipment = [];
    try {
        $equipment = fr_load_equipment($conn, $faculty_id, $now);
    } catch (Throwable $e) {
        error_log('[PUPSync report] equipment: ' . $e->getMessage());
        $notes[] = 'Equipment borrowing history';
    }

    $rooms = [];
    try {
        $rooms = fr_load_rooms($conn, $faculty_id, $now);
    } catch (Throwable $e) {
        error_log('[PUPSync report] rooms: ' . $e->getMessage());
        $notes[] = 'Room and facility reservations';
    }

    if (count($equipment) > FR_ROW_LIMIT) {
        $equipment   = array_slice($equipment, 0, FR_ROW_LIMIT);
        $truncated[] = 'equipment requests';
    }
    if (count($rooms) > FR_ROW_LIMIT) {
        $rooms       = array_slice($rooms, 0, FR_ROW_LIMIT);
        $truncated[] = 'room reservations';
    }

    // Everything a student filed with this faculty member's code, both kinds together, newest first.
    $students = [];
    foreach ($equipment as $e) {
        if ($e['student'] !== '') {
            $students[] = [
                'type' => 'Equipment', 'what' => $e['item'] . ($e['qty'] > 1 ? ' ×' . $e['qty'] : ''),
                'student' => $e['student'], 'student_id' => $e['student_id'],
                'when' => trim($e['day'] . ($e['time'] !== '' ? ', ' . $e['time'] : ''), ', '),
                'status' => $e['status'], 'done_at' => $e['returned_at'], 'sort' => $e['sort'],
            ];
        }
    }
    foreach ($rooms as $r) {
        if ($r['student'] !== '') {
            $students[] = [
                'type' => 'Room', 'what' => $r['room'] . ($r['building'] !== '' ? ' · ' . $r['building'] : ''),
                'student' => $r['student'], 'student_id' => $r['student_id'],
                'when' => trim($r['day'] . ($r['time'] !== '' ? ', ' . $r['time'] : ''), ', '),
                'status' => $r['status'], 'done_at' => $r['closed_at'], 'sort' => $r['sort'],
            ];
        }
    }
    usort($students, static fn(array $a, array $b): int => strcmp($b['sort'], $a['sort']));

    $count = static function (array $rows, array $statuses): int {
        return count(array_filter($rows, static fn(array $r): bool => in_array($r['status'], $statuses, true)));
    };
    $summary = [
        'equipment_total'    => count($equipment),
        'equipment_returned' => $count($equipment, ['Returned', 'Returned late']),
        'equipment_open'     => $count($equipment, ['Pending', 'Approved', 'On Loan', 'Overdue']),
        'equipment_other'    => $count($equipment, ['Declined', 'Cancelled']),
        'rooms_total'        => count($rooms),
        'rooms_completed'    => $count($rooms, ['Completed']),
        'rooms_open'         => $count($rooms, ['Pending', 'Upcoming', 'In progress']),
        'rooms_other'        => $count($rooms, ['Declined', 'Cancelled']),
        'students_total'     => count($students),
        'subjects'           => count(array_unique(array_filter(array_map(static fn(array $s): string => mb_strtolower($s['code'] . '|' . $s['subject']), $schedule['subjects']), 'strlen'))),
        'weekly_minutes'     => $schedule['weekly_minutes'],
    ];

    return [
        'generated_at' => $now->format('Y-m-d H:i:s'),
        'profile'      => $profile,
        'equipment'    => $equipment,
        'rooms'        => $rooms,
        'students'     => $students,
        'schedule'     => $schedule,
        'summary'      => $summary,
        'notes'        => $notes,
        'truncated'    => $truncated,
    ];
}
