<?php

declare(strict_types=1);

/**
 * rooms-import.php  (shared helpers)
 *
 * Everything the "Rooms Assigned" import (Settings -> Imports) needs besides the workbook reader itself
 * (room-reservation/core/class-schedule.php, which parses the file and stores the weekly class meetings):
 *
 *   settings/api/rooms-import.php   preview + import (stores the ORIGINAL file, then the class meetings)
 *   settings/api/imports.php        counts, the imported list, and the original file (view / download)
 *
 * The import is its own thing: it never touches the Faculty Registry list. It stores who has which
 * class in which room, and those classes block their rooms for reservations.
 *
 * Where things live
 *   - files:   uploads/rooms_assigned/<random>.xlsx   (never the uploaded name; the folder denies ALL
 *              direct web access - files are only ever served through settings/api/imports.php)
 *   - records: table tbl_rooms_import_files (created on first use)
 */

require_once __DIR__ . '/../../account-management/core/faculty-registry-files.php';   // FRF_MAX_BYTES, frf_respond(), frf_clean_filename(), frf_detect_type()

// ─────────────────────────────────────────────────────────────────────────────
// Original files
// ─────────────────────────────────────────────────────────────────────────────

/** Absolute path of the (web-blocked) folder holding the original files. */
function ria_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/rooms_assigned';
}

/** Creates the folder (and its "deny everything" .htaccess) if missing. */
function ria_ensure_dir(): bool
{
    $dir = ria_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents(
            $ht,
            "# Rooms Assigned originals are never served directly.\n" .
                "# They are only available through settings/api/imports.php\n" .
                "# (Super Admin + password).\n" .
                "Options -Indexes\n" .
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        );
    }
    return is_writable($dir);
}

/** Full path for a stored file name, or null if the name isn't one we generated. */
function ria_stored_path(string $stored_name): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.xlsx$/', $stored_name)) {
        return null;
    }
    return ria_dir() . '/' . $stored_name;
}

/** Self-healing: creates the history table on first use (same columns as the Faculty Registry one). */
function ria_ensure_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS `tbl_rooms_import_files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `original_name` varchar(255) NOT NULL,
            `stored_name` varchar(64) NOT NULL,
            `mime_type` varchar(100) NOT NULL,
            `file_size` int(10) UNSIGNED NOT NULL,
            `sha256` char(64) NOT NULL,
            `import_mode` enum('merge','replace') NOT NULL DEFAULT 'merge',
            `row_count` int(11) DEFAULT NULL,
            `inserted_count` int(11) DEFAULT NULL,
            `updated_count` int(11) DEFAULT NULL,
            `skipped_count` int(11) DEFAULT NULL,
            `status` enum('uploaded','imported') NOT NULL DEFAULT 'uploaded',
            `uploaded_by` varchar(255) NOT NULL,
            `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
            `imported_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_rooms_import_stored` (`stored_name`),
            KEY `idx_rooms_import_uploaded` (`uploaded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * Keeps ONLY the file of the import that just finished: every older stored file (and its record) is
 * removed, so a new import replaces the previous file instead of piling up next to it. A record is kept
 * if its file could not be deleted, so nothing is hidden from the list while still sitting on disk.
 *
 * @return int number of old files removed
 */
function ria_purge_other_files(mysqli $conn, int $keep_id): int
{
    $removed = 0;
    $keep_stored = '';
    $old = [];
    if ($st = $conn->prepare('SELECT id, stored_name FROM tbl_rooms_import_files')) {
        $st->execute();
        $st->bind_result($rid, $rstored);
        while ($st->fetch()) {
            if ((int)$rid === $keep_id) {
                $keep_stored = (string)$rstored;
            } else {
                $old[] = ['id' => (int)$rid, 'stored' => (string)$rstored];
            }
        }
        $st->close();
    }

    foreach ($old as $o) {
        $path = ria_stored_path($o['stored']);
        if ($path !== null && is_file($path)) {
            if (!@unlink($path)) {
                error_log('[rooms-import] could not delete old file ' . $path);
                continue;   // keep its record; it is retried on the next import
            }
            $removed++;
        }
        if ($del = $conn->prepare('DELETE FROM tbl_rooms_import_files WHERE id = ?')) {
            $del->bind_param('i', $o['id']);
            $del->execute();
            $del->close();
        }
    }

    // Files in the folder that no record points to (never touches the kept file)
    if ($keep_stored !== '' && is_dir(ria_dir())) {
        foreach ((array)scandir(ria_dir()) as $f) {
            if ($f === $keep_stored || !preg_match('/^[a-f0-9]{32}\.xlsx$/', (string)$f)) {
                continue;
            }
            $path = ria_dir() . '/' . $f;
            if (is_file($path) && @unlink($path)) {
                $removed++;
            }
        }
    }
    return $removed;
}

// ─────────────────────────────────────────────────────────────────────────────
// Showing classes (preview before the import, list after it)
// ─────────────────────────────────────────────────────────────────────────────

const RIA_DAY_NAMES = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

/** 480 -> '8:00 AM', 720 -> '12:00 PM', 1020 -> '5:00 PM'. */
function ria_clock(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return (($h % 12) ?: 12) . ':' . sprintf('%02d', $m) . ' ' . ($h >= 12 ? 'PM' : 'AM');
}

/** 'Mon 8:00 AM–11:00 AM' (minutes since midnight). */
function ria_when(int $day, int $start, int $end): string
{
    return (RIA_DAY_NAMES[$day] ?? '?') . ' ' . ria_clock($start) . '–' . ria_clock($end);
}

/** 'COMP 017 · DIT 3-1 (Lecture)' - what the class is, without who teaches it. */
function ria_class_label(string $code, string $section, string $component, ?string $group = null): string
{
    $label = trim($code . ' · ' . $section, ' ·');
    if ($label === '') {
        $label = 'Class';
    }
    $extra = trim($component . ($group !== null && $group !== '' ? ', ' . $group : ''), ', ');
    return $extra !== '' ? $label . ' (' . $extra . ')' : $label;
}

/**
 * Where each room code would land, WITHOUT changing anything (the real import is cs_resolve_rooms()).
 * Returns [code => ['room' => name, 'building' => name, 'exists' => bool, 'placed' => bool]].
 */
function ria_lookup_rooms(mysqli $conn, array $codes): array
{
    $buildings = [];                                   // letter -> [building_id, name]
    $br = $conn->query('SELECT building_id, name FROM tbl_buildings');
    while ($br && ($b = $br->fetch_assoc())) {
        if (preg_match('/^Building\s+([A-Z])\b/i', (string)$b['name'], $m)) {
            $buildings[strtoupper($m[1])] = ['id' => (int)$b['building_id'], 'name' => (string)$b['name']];
        }
    }
    $hasCode = false;
    $cr = $conn->query("SHOW COLUMNS FROM `tbl_rooms` LIKE 'room_code'");
    if ($cr && $cr->num_rows > 0) {
        $hasCode = true;
    }

    $out = [];
    foreach (array_unique($codes) as $code) {
        $code = strtoupper((string)$code);

        if ($hasCode && ($st = $conn->prepare(
            'SELECT r.room_name, b.name AS building FROM tbl_rooms r JOIN tbl_buildings b ON b.building_id = r.building_id
              WHERE r.room_code = ? AND r.is_archived = 0 ORDER BY r.room_id LIMIT 1'
        ))) {
            $st->bind_param('s', $code);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row) {
                $out[$code] = ['room' => (string)$row['room_name'], 'building' => (string)$row['building'], 'exists' => true, 'placed' => true];
                continue;
            }
        }

        if (!preg_match('/^([A-Z])(L?)(\d{2,3})$/', $code, $m) || !isset($buildings[$m[1]])) {
            $out[$code] = ['room' => $code, 'building' => 'Building not found', 'exists' => false, 'placed' => false];
            continue;
        }
        $name = ($m[2] === 'L' ? 'Computer Lab ' : 'Room ') . $m[3];
        $bid  = $buildings[$m[1]]['id'];
        $exists = false;
        if ($st = $conn->prepare('SELECT 1 FROM tbl_rooms WHERE building_id = ? AND LOWER(room_name) = LOWER(?) AND is_archived = 0 LIMIT 1')) {
            $st->bind_param('is', $bid, $name);
            $st->execute();
            $exists = $st->get_result()->num_rows > 0;
            $st->close();
        }
        $out[$code] = ['room' => $name, 'building' => $buildings[$m[1]]['name'], 'exists' => $exists, 'placed' => true];
    }
    return $out;
}

/** 'Room A201 has two classes at once on day 1 (08:30:00).' -> '... on Mon at 8:30 AM.' */
function ria_friendly_overlap(string $line): string
{
    return (string)preg_replace_callback(
        '/on day (\d) \((\d{2}):(\d{2}):\d{2}\)/',
        static fn($m) => 'on ' . (RIA_DAY_NAMES[(int)$m[1]] ?? 'one day') . ' at ' . ria_clock((int)$m[2] * 60 + (int)$m[3]),
        $line
    );
}

/**
 * What the Super Admin sees before importing: a summary and one row per weekly class meeting that
 * WILL be saved. $kind is 'faculty_schedule' or 'room_timetable' (cs_detect_file()).
 */
function ria_preview(mysqli $conn, string $kind, array $parsed): array
{
    $meetings = $kind === 'room_timetable'
        ? array_values(array_filter($parsed['meetings'], static fn($m) => $m['is_tba'] === 1))   // only these are stored
        : $parsed['meetings'];

    $codes = array_values(array_unique(array_column($meetings, 'room_code')));
    if ($kind === 'room_timetable') {
        $codes = array_values(array_unique(array_merge($codes, array_keys($parsed['rooms']))));
    }
    $rooms = ria_lookup_rooms($conn, $codes);

    usort($meetings, static fn($a, $b) => [$a['room_code'], $a['day'], $a['start']] <=> [$b['room_code'], $b['day'], $b['start']]);

    $rows = [];
    $hours = 0;
    foreach ($meetings as $m) {
        $room = $rooms[$m['room_code']] ?? ['room' => $m['room_code'], 'building' => '', 'exists' => false, 'placed' => false];
        $hours += ($m['end'] - $m['start']) / 60;
        $rows[] = [
            'room'        => $room['room'],
            'building'    => $room['building'],
            'assigned_to' => $m['is_tba'] === 1 ? 'No instructor yet' : (string)$m['faculty_name'],
            'class_label' => ria_class_label((string)$m['subject_code'], (string)$m['section'], (string)$m['component'], $m['group_label']),
            'when'        => ria_when((int)$m['day'], (int)$m['start'], (int)$m['end']),
        ];
    }

    $newRooms = [];
    $unplaced = [];
    foreach ($rooms as $code => $info) {
        if (!$info['placed']) {
            $unplaced[] = $code;
        } elseif (!$info['exists']) {
            $newRooms[] = $info['room'] . ' (' . $info['building'] . ')';
        }
    }

    $faculty = [];
    $noId = 0;
    if ($kind === 'faculty_schedule') {
        $faculty = $parsed['faculty'];
        $noId = count(array_filter($faculty, static fn($f) => $f['id'] === null));
    }

    return [
        'summary' => [
            'kind'        => $kind,
            'meetings'    => count($meetings),
            'rooms'       => count(array_unique(array_column($meetings, 'room_code'))),
            'faculty'     => count($faculty),
            'no_id'       => $noId,
            'hours'       => $hours,
            'term'        => $kind === 'room_timetable' ? ($parsed['term'] ?? null) : null,
            'new_rooms'   => $newRooms,
            'unplaced'    => $unplaced,
            'overlaps'    => array_map('ria_friendly_overlap', array_slice(cs_find_overlaps($parsed['meetings']), 0, 5)),
            'warnings'    => array_slice($parsed['warnings'] ?? [], 0, 20),
        ],
        'rows' => $rows,
    ];
}

/** Every stored class meeting, for the "Imported list" window. */
function ria_list_entries(mysqli $conn, int $limit = 10000): array
{
    $entries = [];
    $res = $conn->query(
        'SELECT cs.room_code, rm.room_name, b.name AS building_name, cs.faculty_name, cs.subject_code, cs.section,
                cs.component, cs.group_label, cs.day_of_week,
                TIME_TO_SEC(cs.start_time) AS start_sec, TIME_TO_SEC(cs.end_time) AS end_sec
           FROM tbl_class_schedule cs
           LEFT JOIN tbl_rooms rm ON rm.room_id = cs.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
          ORDER BY cs.room_code, cs.day_of_week, cs.start_time
          LIMIT ' . max(1, $limit)
    );
    while ($res && ($r = $res->fetch_assoc())) {
        $entries[] = [
            'room'        => (string)($r['room_name'] ?? '') !== '' ? (string)$r['room_name'] : (string)$r['room_code'],
            'building'    => (string)($r['building_name'] ?? ''),
            'assigned_to' => (string)($r['faculty_name'] ?? '') !== '' ? (string)$r['faculty_name'] : 'No instructor yet',
            'class_label' => ria_class_label((string)$r['subject_code'], (string)$r['section'], (string)$r['component'], $r['group_label'] !== null ? (string)$r['group_label'] : null),
            'when'        => ria_when((int)$r['day_of_week'], intdiv((int)$r['start_sec'], 60), intdiv((int)$r['end_sec'], 60)),
        ];
    }
    return $entries;
}
