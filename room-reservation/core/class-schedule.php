<?php

declare(strict_types=1);

/**
 * class-schedule.php
 *
 * Weekly CLASS meetings (who teaches what, in which room, on which day and time) and how they
 * occupy rooms. Read from the "Faculty Rooms" workbook (one row per class, with a "Meeting times"
 * column) and, optionally, the "Room Timetables" workbook (one grid per room; the only place the
 * classes that have no instructor yet - "TBA" - appear).
 *
 * A class meeting repeats EVERY WEEK until the next import replaces it. It is not a reservation:
 * it never expires, can't be bumped by anyone's priority, and blocks the room exactly like an
 * approved reservation does.
 *
 *   cs_ensure_schema()            create tbl_class_schedule + tbl_rooms.room_code (idempotent)
 *   cs_detect_file()              which kind of workbook is this?  faculty_schedule | room_timetable | null
 *   cs_parse_faculty_workbook()   -> meetings + faculty list
 *   cs_parse_timetable_workbook() -> rooms + unassigned (TBA) meetings
 *   cs_import_faculty_schedule()  / cs_import_timetable()   store them (rooms are matched or created)
 *   cs_link_accounts()            attach stored meetings to faculty accounts (by ID, else by name)
 *   cs_room_conflicts()           class meetings that overlap a requested room/date/time window
 *   cs_faculty_week_rows()        a faculty account's own meetings
 */

require_once __DIR__ . '/../../shared/core/faculty-id.php';
require_once __DIR__ . '/../../account-management/core/faculty-registry-xlsx.php';

const CS_DAYS = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6];
const CS_MAX_ROWS = 20000;

// ─────────────────────────────────────────────────────────────────────────────
// Schema
// ─────────────────────────────────────────────────────────────────────────────

/** Create what the class schedule needs. Safe to call any number of times. */
function cs_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `tbl_class_schedule` (
            `id`                 int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `import_id`          int(11) DEFAULT NULL,
            `faculty_id`         varchar(50) DEFAULT NULL,
            `faculty_name`       varchar(255) DEFAULT NULL,
            `name_key`           varchar(255) DEFAULT NULL,
            `employment`         varchar(30) DEFAULT NULL,
            `account_faculty_id` varchar(255) DEFAULT NULL,
            `room_id`            int(11) DEFAULT NULL,
            `room_code`          varchar(20) NOT NULL,
            `subject_code`       varchar(40) NOT NULL DEFAULT '',
            `subject_name`       varchar(255) NOT NULL DEFAULT '',
            `section`            varchar(60) NOT NULL DEFAULT '',
            `component`          varchar(20) NOT NULL DEFAULT 'Lecture',
            `group_label`        varchar(40) DEFAULT NULL,
            `day_of_week`        tinyint(3) UNSIGNED NOT NULL,
            `start_time`         time NOT NULL,
            `end_time`           time NOT NULL,
            `is_tba`             tinyint(1) NOT NULL DEFAULT 0,
            `term_label`         varchar(100) DEFAULT NULL,
            `created_at`         datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_cs_room_day` (`room_id`, `day_of_week`, `start_time`, `end_time`),
            KEY `idx_cs_account`  (`account_faculty_id`, `day_of_week`),
            KEY `idx_cs_name`     (`name_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    // The short code the timetable uses for a room (A201, C101, CL207). Remembered once a room is
    // matched, so later imports find it again even if the room is renamed.
    $r = $conn->query("SHOW COLUMNS FROM `tbl_rooms` LIKE 'room_code'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE `tbl_rooms` ADD COLUMN `room_code` varchar(20) DEFAULT NULL, ADD KEY `idx_rooms_code` (`room_code`)");
    }
    $done = true;
}

/** Read paths use this so a fresh install (before any import) just sees "no classes". */
function cs_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready === null) {
        $r = $conn->query("SHOW TABLES LIKE 'tbl_class_schedule'");
        $ready = (bool)($r && $r->num_rows > 0);
    }
    return $ready;
}

// ─────────────────────────────────────────────────────────────────────────────
// Reading the workbooks
// ─────────────────────────────────────────────────────────────────────────────

/**
 * First worksheet as a grid: rows[rowNumber][colIndex] = text (1-based rows, 0-based columns),
 * plus its merged ranges [[r1, c1, r2, c2], ...]. The archive is read in place, never extracted.
 */
function cs_xlsx_grid(string $path): array
{
    if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
        throw new RuntimeException('The server is missing the XLSX reader. Please contact the server administrator.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('The XLSX file could not be opened. Please choose a valid workbook.');
    }
    try {
        $main    = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $officeR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $pkgR    = 'http://schemas.openxmlformats.org/package/2006/relationships';

        $wb     = faculty_registry_xlsx_xml_document(faculty_registry_xlsx_xml($zip, 'xl/workbook.xml'));
        $sheets = $wb->getElementsByTagNameNS($main, 'sheet');
        $first  = $sheets->length > 0 ? $sheets->item(0) : null;
        if (!$first instanceof DOMElement) {
            throw new RuntimeException('The XLSX workbook does not contain a worksheet.');
        }
        $rid = $first->getAttributeNS($officeR, 'id');
        $rels = faculty_registry_xlsx_xml_document(faculty_registry_xlsx_xml($zip, 'xl/_rels/workbook.xml.rels'));
        $target = '';
        foreach ($rels->getElementsByTagNameNS($pkgR, 'Relationship') as $rel) {
            if ($rel instanceof DOMElement && $rel->getAttribute('Id') === $rid) {
                $target = $rel->getAttribute('Target');
                break;
            }
        }
        $sheetPath = $target === '' ? null : faculty_registry_xlsx_resolve_path($target);
        if ($sheetPath === null) {
            throw new RuntimeException('The first worksheet could not be found in this XLSX workbook.');
        }
        $sheet = faculty_registry_xlsx_xml_document(faculty_registry_xlsx_xml($zip, $sheetPath));

        $shared = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $ss = faculty_registry_xlsx_xml_document(faculty_registry_xlsx_xml($zip, 'xl/sharedStrings.xml'));
            foreach ($ss->getElementsByTagNameNS($main, 'si') as $si) {
                $text = '';
                foreach ($si->getElementsByTagNameNS($main, 't') as $t) {
                    $text .= $t->textContent;
                }
                $shared[] = $text;
            }
        }

        $grid = [];
        $count = 0;
        foreach ($sheet->getElementsByTagNameNS($main, 'row') as $rowNode) {
            if (!$rowNode instanceof DOMElement) {
                continue;
            }
            foreach ($rowNode->getElementsByTagNameNS($main, 'c') as $c) {
                if (!$c instanceof DOMElement || !preg_match('/^([A-Z]+)(\d+)$/', strtoupper($c->getAttribute('r')), $m)) {
                    continue;
                }
                $col  = faculty_registry_xlsx_column_index($m[1]);
                $rown = (int)$m[2];
                $vn   = $c->getElementsByTagNameNS($main, 'v')->item(0);
                $val  = $vn ? $vn->textContent : '';
                $type = $c->getAttribute('t');
                if ($type === 's') {
                    $val = $shared[(int)$val] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = '';
                    foreach ($c->getElementsByTagNameNS($main, 't') as $t) {
                        $val .= $t->textContent;
                    }
                }
                $val = trim((string)$val);
                if ($val !== '') {
                    $grid[$rown][$col] = $val;
                    if (++$count > CS_MAX_ROWS * 12) {
                        throw new RuntimeException('This workbook is too large to process.');
                    }
                }
            }
        }

        $merges = [];
        foreach ($sheet->getElementsByTagNameNS($main, 'mergeCell') as $mc) {
            if ($mc instanceof DOMElement && preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', strtoupper($mc->getAttribute('ref')), $m)) {
                $merges[] = [(int)$m[2], faculty_registry_xlsx_column_index($m[1]), (int)$m[4], faculty_registry_xlsx_column_index($m[3])];
            }
        }
        ksort($grid);
        return ['grid' => $grid, 'merges' => $merges];
    } finally {
        $zip->close();
    }
}

/** 'Faculty ID' / 'faculty id' / 'Faculty-ID' all become 'facultyid'. */
function cs_header_key(string $s): string
{
    return strtolower((string)preg_replace('/[^a-z0-9]/i', '', $s));
}

/** Which of the two supported workbooks is this? null = neither (an ordinary faculty list). */
function cs_detect_file(string $path): ?string
{
    try {
        $g = cs_xlsx_grid($path)['grid'];
    } catch (Throwable $e) {
        return null;
    }
    return cs_detect_grid($g);
}

function cs_detect_grid(array $grid): ?string
{
    foreach ($grid as $row) {      // the header is the first non-empty row
        $keys = array_map('cs_header_key', $row);
        if (in_array('meetingtimes', $keys, true) && in_array('room', $keys, true)
            && (in_array('faculty', $keys, true) || in_array('professorname', $keys, true))) {
            return 'faculty_schedule';
        }
        break;
    }
    $seen = 0;
    foreach ($grid as $row) {
        $first = (string)($row[0] ?? '');
        if (preg_match('/^ROOM\s+\S+\s+[–-]/u', $first) && ++$seen >= 1) {
            return 'room_timetable';
        }
    }
    return null;
}

/** '8:00a' -> 480, '12:00p' -> 720, '12:30a' -> 30. null when it isn't a time. */
function cs_time_to_minutes(string $s): ?int
{
    if (!preg_match('/^\s*(\d{1,2}):(\d{2})\s*([ap])m?\s*$/i', $s, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $min = (int)$m[2];
    if ($h < 1 || $h > 12 || $min > 59) {
        return null;
    }
    return ($h % 12 + (strtolower($m[3]) === 'p' ? 12 : 0)) * 60 + $min;
}

function cs_minutes_to_time(int $m): string
{
    return sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);
}

/** The "Programs Handled" of a section: 'BSCPE 3-1' -> 'BSCPE', 'BSBA-HRM 4-2' -> 'BSBA-HRM'. */
function cs_program_of_section(string $section): string
{
    return preg_match('/^(.*?)\s+\d+\s*-\s*\d+\s*$/', trim($section), $m) ? trim($m[1]) : trim($section);
}

function cs_clean(string $s): string
{
    return trim((string)preg_replace('/\s+/u', ' ', $s));
}

/**
 * Parse the "Faculty Rooms" workbook.
 * Returns ['meetings' => [...], 'faculty' => [name_key => [...]], 'warnings' => [...], 'rows' => n]
 * One spreadsheet row can hold several meetings ("Tue 1:00p-4:00p; Fri 8:00a-11:00a"); each becomes one meeting.
 */
function cs_parse_faculty_workbook(string $path): array
{
    $g = cs_xlsx_grid($path)['grid'];
    if (cs_detect_grid($g) !== 'faculty_schedule') {
        throw new RuntimeException('This is not a Faculty Rooms workbook (it needs Faculty, Room and Meeting times columns).');
    }
    $firstRowNo = array_key_first($g);
    $col = [];
    foreach ($g[$firstRowNo] as $ci => $h) {
        $k = cs_header_key($h);
        $map = [
            'faculty' => 'name', 'professorname' => 'name', 'facultyname' => 'name',
            'facultyid' => 'id', 'status' => 'status', 'room' => 'room',
            'subjectcode' => 'code', 'subject' => 'subject', 'sectionblock' => 'section', 'section' => 'section',
            'component' => 'component', 'hoursweek' => 'hours', 'meetingtimes' => 'times',
        ];
        if (isset($map[$k]) && !isset($col[$map[$k]])) {
            $col[$map[$k]] = $ci;
        }
    }
    foreach (['name', 'room', 'times'] as $need) {
        if (!isset($col[$need])) {
            throw new RuntimeException('The workbook is missing its ' . $need . ' column.');
        }
    }

    $meetings = [];
    $faculty  = [];
    $warnings = [];
    $rows     = 0;
    $get = static function (array $row, string $key) use ($col): string {
        return isset($col[$key]) ? cs_clean((string)($row[$col[$key]] ?? '')) : '';
    };

    foreach ($g as $rowNo => $row) {
        if ($rowNo <= $firstRowNo) {
            continue;
        }
        $name = $get($row, 'name');
        if ($name === '') {
            continue;
        }
        if (++$rows > CS_MAX_ROWS) {
            throw new RuntimeException('This workbook has more than ' . number_format(CS_MAX_ROWS) . ' rows. Split it into smaller files.');
        }
        $room = strtoupper(str_replace(' ', '', $get($row, 'room')));
        if ($room === '' || !preg_match('/^[A-Z0-9][A-Z0-9-]{0,19}$/', $room)) {
            $warnings[] = "Row $rowNo ($name): room '" . $get($row, 'room') . "' is not usable, skipped.";
            continue;
        }

        // Faculty ID: "Newly hired (no ID yet)" and blanks mean no ID.
        $rawId = strtoupper(preg_replace('/\s+/', '', $get($row, 'id')) ?? '');
        $fid = null;
        if ($rawId !== '' && strncmp($rawId, 'NEWLYHIRED', 10) !== 0) {
            if (strlen($rawId) >= 5 && strlen($rawId) <= 30 && preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $rawId) && preg_match('/\d/', $rawId)) {
                $fid = $rawId;
            } else {
                $warnings[] = "Row $rowNo ($name): Faculty ID '" . $get($row, 'id') . "' is not valid, treated as no ID yet.";
            }
        }

        $key = faculty_name_letters($name);
        if (!isset($faculty[$key])) {
            $faculty[$key] = ['name' => $name, 'id' => $fid, 'status' => $get($row, 'status'), 'programs' => []];
        } elseif ($faculty[$key]['id'] === null && $fid !== null) {
            $faculty[$key]['id'] = $fid;
        }
        $section = $get($row, 'section');
        if ($section !== '') {
            $faculty[$key]['programs'][cs_program_of_section($section)] = true;
        }

        $any = false;
        foreach (explode(';', (string)($row[$col['times']] ?? '')) as $part) {
            $part = cs_clean($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+(\d{1,2}:\d{2}\s*[ap])\s*[–-]\s*(\d{1,2}:\d{2}\s*[ap])(?:\s*\((.+)\))?$/iu', $part, $m)) {
                $warnings[] = "Row $rowNo ($name): meeting time '$part' could not be read, skipped.";
                continue;
            }
            $day = ucfirst(strtolower($m[1]));
            if (!isset(CS_DAYS[$day])) {
                $warnings[] = "Row $rowNo ($name): $day is a closed day, skipped.";
                continue;
            }
            $s = cs_time_to_minutes($m[2]);
            $e = cs_time_to_minutes($m[3]);
            if ($s === null || $e === null || $e <= $s) {
                $warnings[] = "Row $rowNo ($name): times '$part' are not valid, skipped.";
                continue;
            }
            $meetings[] = [
                'row' => $rowNo, 'faculty_key' => $key, 'faculty_name' => $name, 'faculty_id' => $fid,
                'employment' => $get($row, 'status'), 'room_code' => $room,
                'subject_code' => $get($row, 'code'), 'subject_name' => $get($row, 'subject'),
                'section' => $section, 'component' => $get($row, 'component') ?: 'Lecture',
                'group_label' => isset($m[4]) && $m[4] !== '' ? cs_clean($m[4]) : null,
                'day' => CS_DAYS[$day], 'start' => $s, 'end' => $e, 'is_tba' => 0,
            ];
            $any = true;
        }
        if (!$any) {
            $warnings[] = "Row $rowNo ($name): no readable meeting time.";
        }
    }
    if (!$meetings) {
        throw new RuntimeException('No class meetings were found in this workbook.');
    }
    foreach ($faculty as &$f) {
        $f['programs'] = array_keys($f['programs']);
    }
    unset($f);
    return ['meetings' => $meetings, 'faculty' => $faculty, 'warnings' => $warnings, 'rows' => $rows];
}

/**
 * Parse the "Room Timetables" workbook: one block per room ("ROOM A201 - Building A | Classroom, 60 seats | ..."),
 * hours down the side, Monday-Saturday across, a merged cell for every class longer than an hour.
 * Returns ['rooms' => [code => meta], 'meetings' => [...every block...], 'term' => label|null]
 */
function cs_parse_timetable_workbook(string $path): array
{
    $x = cs_xlsx_grid($path);
    $g = $x['grid'];
    if (cs_detect_grid($g) !== 'room_timetable') {
        throw new RuntimeException('This is not a Room Timetables workbook.');
    }
    $span = [];                                   // "row:col" -> number of rows the merged cell covers
    foreach ($x['merges'] as [$r1, $c1, $r2, $c2]) {
        $span[$r1 . ':' . $c1] = $r2 - $r1 + 1;
    }

    $term = null;
    if (isset($g[1][0]) && preg_match('/(\d+(?:st|nd|rd|th) Semester,?\s*AY\s*[\d-]+)/iu', (string)$g[1][0], $tm)) {
        $term = cs_clean($tm[1]);
    }

    $dayCols = [];
    $rooms = [];
    $meetings = [];
    $rowNos = array_keys($g);
    $maxRow = $rowNos ? max($rowNos) : 0;
    for ($r = 1; $r <= $maxRow; $r++) {
        $a = (string)($g[$r][0] ?? '');
        if (!preg_match('/^ROOM\s+(\S+)\s+[–-]\s+(.+?)\s*\|\s*(.+?),\s*(\d+)\s*seats/iu', $a, $m)) {
            continue;
        }
        $code = strtoupper($m[1]);
        $rooms[$code] = ['code' => $code, 'building' => cs_clean($m[2]), 'type' => cs_clean($m[3]), 'seats' => (int)$m[4]];

        // header row: Time | Monday | ... find the day columns
        $hr = $r + 1;
        $dayCols = [];
        foreach (($g[$hr] ?? []) as $ci => $label) {
            $d = ucfirst(strtolower(substr($label, 0, 3)));
            if (isset(CS_DAYS[$d])) {
                $dayCols[$ci] = CS_DAYS[$d];
            }
        }
        for ($rr = $hr + 1; $rr <= $maxRow; $rr++) {
            $tl = (string)($g[$rr][0] ?? '');
            if (!preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)\s*[–-]/iu', $tl, $tm2)) {
                break;                                           // end of this room's block
            }
            $startMin = (((int)$tm2[1]) % 12 + (strtoupper($tm2[3]) === 'PM' ? 12 : 0)) * 60 + (int)$tm2[2];
            foreach ($dayCols as $ci => $dayNo) {
                $cell = (string)($g[$rr][$ci] ?? '');
                if ($cell === '') {
                    continue;
                }
                $lines = preg_split('/\R/u', $cell, 2) ?: [$cell];
                $prof  = cs_clean($lines[0]);
                $rest  = cs_clean(str_replace(["\r", "\n"], '', (string)($lines[1] ?? '')));
                $section = $rest;
                $code2 = '';
                if (str_contains($rest, ' · ')) {
                    [$section, $code2] = array_map('cs_clean', explode(' · ', $rest, 2));
                }
                $hours = $span[$rr . ':' . $ci] ?? 1;
                $isTba = stripos($prof, 'TBA') === 0;
                $meetings[] = [
                    'row' => $rr, 'faculty_key' => $isTba ? '' : faculty_name_letters($prof),
                    'faculty_name' => $isTba ? null : $prof, 'faculty_id' => null, 'employment' => '',
                    'room_code' => $code, 'subject_code' => $code2, 'subject_name' => '',
                    'section' => $section, 'component' => 'Lecture', 'group_label' => null,
                    'day' => $dayNo, 'start' => $startMin, 'end' => $startMin + 60 * $hours, 'is_tba' => $isTba ? 1 : 0,
                ];
            }
        }
    }
    if (!$rooms) {
        throw new RuntimeException('No rooms were found in this workbook.');
    }
    return ['rooms' => $rooms, 'meetings' => $meetings, 'term' => $term];
}

// ─────────────────────────────────────────────────────────────────────────────
// Rooms
// ─────────────────────────────────────────────────────────────────────────────

function cs_ordinal_floor(int $n): string
{
    $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$n] ?? 'th';
    return $n . $suffix . ' Floor';
}

/**
 * Match every room code to a room in the system, creating the ones that don't exist yet.
 *   1. a room that already carries this code            (A201 found again on a later import)
 *   2. "Room 201" in "Building A ..."                   (code = building letter + room number)
 *   3. otherwise a new room is created in that building (floor from the first digit)
 * Returns [code => ['room_id' => int|null, 'how' => 'known'|'matched'|'created'|'unresolved', 'name' => string]]
 */
function cs_resolve_rooms(mysqli $conn, array $codes, array $meta = [], bool $create = true): array
{
    cs_ensure_schema($conn);
    $out = [];

    $buildings = [];                                   // letter -> building_id
    $br = $conn->query('SELECT building_id, name FROM tbl_buildings');
    while ($br && ($b = $br->fetch_assoc())) {
        if (preg_match('/^Building\s+([A-Z])\b/i', (string)$b['name'], $m)) {
            $buildings[strtoupper($m[1])] = (int)$b['building_id'];
        }
    }

    foreach (array_unique($codes) as $code) {
        $code = strtoupper($code);
        // 1) known by code
        $st = $conn->prepare('SELECT room_id, room_name FROM tbl_rooms WHERE room_code = ? AND is_archived = 0 ORDER BY room_id LIMIT 1');
        $st->bind_param('s', $code);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $out[$code] = ['room_id' => (int)$row['room_id'], 'how' => 'known', 'name' => (string)$row['room_name']];
            continue;
        }

        if (!preg_match('/^([A-Z])(L?)(\d{2,3})$/', $code, $m) && !preg_match('/^(C)(L)(\d{3})$/', $code, $m)) {
            $out[$code] = ['room_id' => null, 'how' => 'unresolved', 'name' => $code];
            continue;
        }
        // CL207 = computer lab 207 in Building C; A201 = room 201 in Building A
        $letter = $m[1];
        $isLab  = $m[2] === 'L';
        $number = $m[3];
        $bid    = $buildings[$letter] ?? null;
        if ($bid === null) {
            $out[$code] = ['room_id' => null, 'how' => 'unresolved', 'name' => $code];
            continue;
        }
        $name = $isLab ? 'Computer Lab ' . $number : 'Room ' . $number;

        // 2) the room already exists under its normal name
        $st = $conn->prepare('SELECT room_id, room_name FROM tbl_rooms WHERE building_id = ? AND LOWER(room_name) = LOWER(?) AND is_archived = 0 ORDER BY room_id LIMIT 1');
        $st->bind_param('is', $bid, $name);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $rid = (int)$row['room_id'];
            $up = $conn->prepare('UPDATE tbl_rooms SET room_code = ? WHERE room_id = ?');
            $up->bind_param('si', $code, $rid);
            $up->execute();
            $up->close();
            $out[$code] = ['room_id' => $rid, 'how' => 'matched', 'name' => (string)$row['room_name']];
            continue;
        }

        // 3) create it
        if (!$create) {
            $out[$code] = ['room_id' => null, 'how' => 'unresolved', 'name' => $name];
            continue;
        }
        $floorNo    = max(1, (int)substr($number, 0, 1));
        $floorLabel = cs_ordinal_floor($floorNo);
        $seats      = isset($meta[$code]['seats']) && $meta[$code]['seats'] > 0 ? (int)$meta[$code]['seats'] : null;
        $amen       = $isLab ? 'Computer Lab' : null;
        $so = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tbl_rooms WHERE building_id = ? AND floor_number = ?');
        $so->bind_param('ii', $bid, $floorNo);
        $so->execute();
        $so->bind_result($sort);
        $so->fetch();
        $so->close();
        $sort = (int)$sort;
        $ins = $conn->prepare(
            "INSERT INTO tbl_rooms (building_id, room_name, floor_number, floor_label, seating_capacity, amenities, status, is_archived, is_locked, sort_order, room_code)
             VALUES (?, ?, ?, ?, ?, ?, 'Available', 0, 0, ?, ?)"
        );
        $ins->bind_param('isisisis', $bid, $name, $floorNo, $floorLabel, $seats, $amen, $sort, $code);
        $ins->execute();
        $rid = (int)$conn->insert_id;
        $ins->close();
        $out[$code] = ['room_id' => $rid, 'how' => 'created', 'name' => $name];
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────────
// Names -> accounts
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Is this account ("Edmundo Dela Cruz") the person this timetable name ("Edmundo S. Dela Cruz") refers to?
 * The surname must be the same and the given name must match the way registry look-ups match it,
 * so middle names and initials on either side don't get in the way.
 */
function cs_account_matches_name(string $account_fullname, string $schedule_name): bool
{
    $parts = faculty_registry_name_parts($schedule_name);
    $lastLetters = faculty_name_letters($parts['last']);
    if ($lastLetters === '' || $parts['first'] === '') {
        return false;
    }
    $tokens = preg_split('/\s+/', trim($account_fullname), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    for ($k = count($tokens) - 1; $k >= 1; $k--) {
        if (faculty_name_letters(implode(' ', array_slice($tokens, $k))) === $lastLetters) {
            return faculty_first_name_matches(implode(' ', array_slice($tokens, 0, $k)), $parts['first']);
        }
    }
    return false;
}

/**
 * Attach stored class meetings to faculty accounts: first by Faculty ID, otherwise by name
 * (only when exactly one account fits and that account fits no other timetable name).
 * $only_account limits it to one account (used right after that account is created).
 * Returns the number of accounts that now have class meetings.
 */
function cs_link_accounts(mysqli $conn, ?string $only_account_id = null, ?string $only_account_name = null): int
{
    if (!cs_table_ready($conn)) {
        return 0;
    }
    // every distinct person in the timetable
    $people = [];
    $r = $conn->query("SELECT name_key, MAX(faculty_name) AS name, MAX(faculty_id) AS fid FROM tbl_class_schedule WHERE is_tba = 0 AND name_key IS NOT NULL AND name_key <> '' GROUP BY name_key");
    while ($r && ($p = $r->fetch_assoc())) {
        $people[(string)$p['name_key']] = ['name' => (string)$p['name'], 'fid' => $p['fid'] !== null ? (string)$p['fid'] : null];
    }
    if (!$people) {
        return 0;
    }
    $accounts = [];
    if ($only_account_id !== null) {
        $accounts[] = ['faculty_id' => $only_account_id, 'fullname' => (string)$only_account_name];
    } else {
        $ur = $conn->query('SELECT faculty_id, fullname FROM tbl_users');
        while ($ur && ($u = $ur->fetch_assoc())) {
            $accounts[] = ['faculty_id' => (string)$u['faculty_id'], 'fullname' => (string)$u['fullname']];
        }
    }

    // account -> the timetable people it could be
    $linked = 0;
    $up = $conn->prepare('UPDATE tbl_class_schedule SET account_faculty_id = ? WHERE name_key = ? AND is_tba = 0');
    $claimed = [];                                       // name_key -> account (so two accounts can't both take one person)
    $owner = $conn->prepare('SELECT u.faculty_id FROM tbl_class_schedule cs JOIN tbl_users u ON u.faculty_id = cs.account_faculty_id WHERE cs.name_key = ? LIMIT 1');
    foreach ($accounts as $a) {
        $hit = [];
        $certain = false;
        foreach ($people as $key => $p) {
            if ($p['fid'] !== null && strcasecmp($p['fid'], $a['faculty_id']) === 0) {
                $hit = [$key];                          // same Faculty ID: certain
                $certain = true;
                break;
            }
            if (cs_account_matches_name($a['fullname'], $p['name'])) {
                $hit[] = $key;
            }
        }
        if (count($hit) !== 1 || isset($claimed[$hit[0]])) {
            continue;                                   // none, or ambiguous: leave unlinked rather than guess
        }
        if (!$certain && $only_account_id !== null) {   // a name match must not take over someone already linked to another account
            $k0 = $hit[0];
            $owner->bind_param('s', $k0);
            $owner->execute();
            $cur = $owner->get_result()->fetch_row();
            if ($cur && strcasecmp((string)$cur[0], $a['faculty_id']) !== 0) {
                continue;
            }
        }
        $claimed[$hit[0]] = $a['faculty_id'];
        $fid = $a['faculty_id'];
        $key = $hit[0];
        $up->bind_param('ss', $fid, $key);
        $up->execute();
        $linked++;
    }
    $up->close();
    $owner->close();
    return $linked;
}

// ─────────────────────────────────────────────────────────────────────────────
// Importing
// ─────────────────────────────────────────────────────────────────────────────

/** Overlaps inside the file itself (same room, or the same person, in two places at once). */
function cs_find_overlaps(array $meetings): array
{
    $found = [];
    foreach (['room' => 'room_code', 'person' => 'faculty_key'] as $label => $field) {
        $buckets = [];
        foreach ($meetings as $m) {
            if ($m[$field] === '' || $m[$field] === null) {
                continue;
            }
            $buckets[$m[$field] . '|' . $m['day']][] = $m;
        }
        foreach ($buckets as $list) {
            usort($list, static fn($a, $b) => $a['start'] <=> $b['start']);
            for ($i = 1, $n = count($list); $i < $n; $i++) {
                if ($list[$i]['start'] < $list[$i - 1]['end']) {
                    $found[] = ($label === 'room' ? 'Room ' . $list[$i]['room_code'] : (string)($list[$i]['faculty_name'] ?? 'A faculty member'))
                        . ' has two classes at once on day ' . $list[$i]['day'] . ' (' . cs_minutes_to_time($list[$i]['start']) . ').';
                }
            }
        }
    }
    return $found;
}

function cs_insert_meetings(mysqli $conn, array $meetings, array $rooms, ?int $import_id, ?string $term): int
{
    $st = $conn->prepare(
        'INSERT INTO tbl_class_schedule
            (import_id, faculty_id, faculty_name, name_key, employment, room_id, room_code, subject_code, subject_name,
             section, component, group_label, day_of_week, start_time, end_time, is_tba, term_label)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $n = 0;
    foreach ($meetings as $m) {
        $rid   = $rooms[$m['room_code']]['room_id'] ?? null;
        $key   = $m['faculty_key'] !== '' ? $m['faculty_key'] : null;
        $start = cs_minutes_to_time($m['start']);
        $end   = cs_minutes_to_time($m['end']);
        $emp   = $m['employment'] !== '' ? $m['employment'] : null;
        $st->bind_param(
            'issssissssssissis',
            $import_id, $m['faculty_id'], $m['faculty_name'], $key, $emp, $rid, $m['room_code'], $m['subject_code'],
            $m['subject_name'], $m['section'], $m['component'], $m['group_label'], $m['day'], $start, $end, $m['is_tba'], $term
        );
        $st->execute();
        $n++;
    }
    $st->close();
    return $n;
}

/** Approved reservations from today on that now overlap a class meeting (reported, never cancelled automatically). */
function cs_overlapping_reservations(mysqli $conn): array
{
    $sql = "SELECT rr.id, rr.reservation_date, rr.start_time, rr.end_time, rm.room_name, rr.faculty_name, cs.subject_code, cs.section
              FROM tbl_room_reservations rr
              JOIN tbl_class_schedule cs
                ON cs.room_id = rr.room_id
               AND cs.day_of_week = WEEKDAY(rr.reservation_date) + 1
               AND cs.start_time < rr.end_time AND cs.end_time > rr.start_time
              JOIN tbl_rooms rm ON rm.room_id = rr.room_id
             WHERE rr.status = 'Approved' AND rr.reservation_date >= CURDATE()
             ORDER BY rr.reservation_date, rr.start_time LIMIT 200";
    $out = [];
    $r = $conn->query($sql);
    while ($r && ($row = $r->fetch_assoc())) {
        $out[] = $row;
    }
    return $out;
}

/**
 * Store the faculty workbook. Only the faculty in the file are replaced (their old meetings are removed first),
 * unless $replace_all, which clears every faculty class meeting first. Unassigned (TBA) meetings are left alone.
 */
function cs_import_faculty_schedule(mysqli $conn, array $parsed, ?int $import_id = null, bool $replace_all = false): array
{
    cs_ensure_schema($conn);
    $codes = array_values(array_unique(array_column($parsed['meetings'], 'room_code')));
    $conn->begin_transaction();
    try {
        $rooms = cs_resolve_rooms($conn, $codes, [], true);

        if ($replace_all) {
            $conn->query('DELETE FROM tbl_class_schedule WHERE is_tba = 0');
        } else {
            $del = $conn->prepare('DELETE FROM tbl_class_schedule WHERE name_key = ? AND is_tba = 0');
            foreach (array_keys($parsed['faculty']) as $key) {
                $del->bind_param('s', $key);
                $del->execute();
            }
            $del->close();
        }
        $stored = cs_insert_meetings($conn, $parsed['meetings'], $rooms, $import_id, null);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    $linked   = cs_link_accounts($conn);
    $howCount = array_count_values(array_column($rooms, 'how'));
    $created  = [];
    $unres    = [];
    foreach ($rooms as $code => $info) {
        if ($info['how'] === 'created') {
            $created[] = $code . ' → ' . $info['name'];
        } elseif ($info['how'] === 'unresolved') {
            $unres[] = $code;
        }
    }
    $overlaps = cs_overlapping_reservations($conn);
    $withId   = 0;
    foreach ($parsed['faculty'] as $f) {
        $withId += $f['id'] !== null ? 1 : 0;
    }
    return [
        'meetings'         => $stored,
        'faculty'          => count($parsed['faculty']),
        'faculty_no_id'    => count($parsed['faculty']) - $withId,
        'accounts_linked'  => $linked,
        'rooms'            => count($rooms),
        'rooms_matched'    => ($howCount['matched'] ?? 0) + ($howCount['known'] ?? 0),
        'rooms_created'    => $created,
        'rooms_unresolved' => $unres,
        'file_overlaps'    => array_slice(cs_find_overlaps($parsed['meetings']), 0, 20),
        'reservations_overlapping' => $overlaps,
        'warnings'         => array_slice($parsed['warnings'], 0, 30),
    ];
}

/**
 * Store the room-timetable workbook: it supplies the room details (building, type, seats) and the classes that
 * have no instructor yet. Faculty classes in it are NOT re-imported (the Faculty Rooms workbook owns those).
 */
function cs_import_timetable(mysqli $conn, array $parsed, ?int $import_id = null): array
{
    cs_ensure_schema($conn);
    $tba = array_values(array_filter($parsed['meetings'], static fn($m) => $m['is_tba'] === 1));
    $conn->begin_transaction();
    try {
        $rooms = cs_resolve_rooms($conn, array_keys($parsed['rooms']), $parsed['rooms'], true);
        $conn->query('DELETE FROM tbl_class_schedule WHERE is_tba = 1');
        $stored = cs_insert_meetings($conn, $tba, $rooms, $import_id, $parsed['term']);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    $howCount = array_count_values(array_column($rooms, 'how'));
    $created  = [];
    foreach ($rooms as $code => $info) {
        if ($info['how'] === 'created') {
            $created[] = $code . ' → ' . $info['name'];
        }
    }
    return [
        'unassigned_classes' => $stored,
        'rooms'              => count($rooms),
        'rooms_matched'      => ($howCount['matched'] ?? 0) + ($howCount['known'] ?? 0),
        'rooms_created'      => $created,
        'term'               => $parsed['term'],
        'reservations_overlapping' => cs_overlapping_reservations($conn),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Reading it back (used by availability checks and the faculty calendar)
// ─────────────────────────────────────────────────────────────────────────────

/** One line for a meeting: 'COMP 017 · DIT 3-1 (Lecture) - Edmundo S. Dela Cruz'. */
function cs_describe(array $row): string
{
    $who = !empty($row['faculty_name']) ? (string)$row['faculty_name'] : 'no instructor yet';
    $what = trim(((string)($row['subject_code'] ?? '')) . ' · ' . ((string)($row['section'] ?? '')), ' ·');
    return ($what !== '' ? $what : 'Class') . ' - ' . $who;
}

/** Class meetings in a room that overlap [start, end) on a date. Times 'HH:MM' or 'HH:MM:SS'. */
function cs_room_conflicts(mysqli $conn, int $room_id, string $date, string $start, string $end): array
{
    if (!cs_table_ready($conn)) {
        return [];
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return [];
    }
    $dow = (int)date('N', $ts);
    if ($dow > 6) {
        return [];                                       // Sunday: closed
    }
    $st = $conn->prepare(
        'SELECT id, faculty_name, subject_code, subject_name, section, component, group_label,
                TIME_FORMAT(start_time, "%H:%i") AS start_hm, TIME_FORMAT(end_time, "%H:%i") AS end_hm
           FROM tbl_class_schedule
          WHERE room_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ?
          ORDER BY start_time'
    );
    $st->bind_param('iiss', $room_id, $dow, $end, $start);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

/** Every class meeting in a room on one date (for the room's day view). */
function cs_room_day_rows(mysqli $conn, int $room_id, string $date): array
{
    return cs_room_conflicts($conn, $room_id, $date, '00:00:00', '23:59:59');
}

/** A faculty account's own meetings, Monday first. */
function cs_faculty_week_rows(mysqli $conn, string $account_faculty_id): array
{
    if (!cs_table_ready($conn)) {
        return [];
    }
    $st = $conn->prepare(
        'SELECT cs.id, cs.day_of_week, TIME_FORMAT(cs.start_time, "%H:%i") AS start_hm, TIME_FORMAT(cs.end_time, "%H:%i") AS end_hm,
                TIME_TO_SEC(TIMEDIFF(cs.end_time, cs.start_time)) AS span_sec,
                cs.subject_code, cs.subject_name, cs.section, cs.component, cs.group_label, cs.room_code,
                rm.room_name, rm.floor_label, b.name AS building_name
           FROM tbl_class_schedule cs
           LEFT JOIN tbl_rooms rm ON rm.room_id = cs.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
          WHERE cs.account_faculty_id = ? AND cs.is_tba = 0
          ORDER BY cs.day_of_week, cs.start_time'
    );
    $st->bind_param('s', $account_faculty_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}


/** 'COMP 017 · DIT 3-1': what a class is called when its instructor shouldn't be shown (decline reasons, availability). */
function cs_class_label(array $row): string
{
    $label = trim(((string)($row['subject_code'] ?? '')) . ' · ' . ((string)($row['section'] ?? '')), ' ·');
    return $label !== '' ? $label : 'a class';
}

/** Of these rooms, the ones that have a class overlapping [start, end) on a date. Returns room ids. */
function cs_rooms_with_conflict(mysqli $conn, array $room_ids, string $date, string $start, string $end): array
{
    $room_ids = array_values(array_unique(array_map('intval', $room_ids)));
    if (!$room_ids || !cs_table_ready($conn)) {
        return [];
    }
    $ts = strtotime($date);
    $dow = $ts === false ? 7 : (int)date('N', $ts);
    if ($dow > 6) {
        return [];
    }
    $in = implode(',', $room_ids);                       // integers only (cast above)
    $st = $conn->prepare("SELECT DISTINCT room_id FROM tbl_class_schedule WHERE room_id IN ($in) AND day_of_week = ? AND start_time < ? AND end_time > ?");
    $st->bind_param('iss', $dow, $end, $start);
    $st->execute();
    $out = array_map('intval', array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'room_id'));
    $st->close();
    return $out;
}

/** Room ids that have a class in session at this moment. */
function cs_rooms_in_class_now(mysqli $conn, string $date, string $time): array
{
    if (!cs_table_ready($conn)) {
        return [];
    }
    $ts = strtotime($date);
    $dow = $ts === false ? 7 : (int)date('N', $ts);
    if ($dow > 6) {
        return [];
    }
    $st = $conn->prepare('SELECT DISTINCT room_id FROM tbl_class_schedule WHERE room_id IS NOT NULL AND day_of_week = ? AND start_time <= ? AND end_time > ?');
    $st->bind_param('iss', $dow, $time, $time);
    $st->execute();
    $out = array_map('intval', array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'room_id'));
    $st->close();
    return $out;
}

/** Every class meeting in one room, Monday first (the room's weekly timetable). */
function cs_room_week_rows(mysqli $conn, int $room_id): array
{
    if (!cs_table_ready($conn)) {
        return [];
    }
    $st = $conn->prepare(
        'SELECT id, day_of_week, faculty_name, subject_code, subject_name, section, component, group_label,
                TIME_FORMAT(start_time, "%H:%i") AS start_hm, TIME_FORMAT(end_time, "%H:%i") AS end_hm
           FROM tbl_class_schedule WHERE room_id = ? ORDER BY day_of_week, start_time'
    );
    $st->bind_param('i', $room_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
