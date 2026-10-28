<?php

declare(strict_types=1);

/**
 * student-registry.php
 *
 * The official student list (imported by a Super Admin in Settings -> Student List)
 * and the check the Student Portal runs against it.
 *
 * USAGE (student side):
 *   require_once __DIR__ . '/../../config/student-registry.php';
 *   $sv = student_registry_validate($conn, $student_id);
 *   if (!$sv['ok']) { echo json_encode(['error' => $sv['error']]); exit; }
 *   $student_id = $sv['student_no'];   // canonical form (upper-case, no spaces)
 *
 * RULES
 *   - Until a list has been imported (table empty) nothing is enforced, so a fresh
 *     deployment keeps working. From the first imported student on, EVERY student's
 *     Student No. must exist on the list. Only the number is checked - never the name.
 *   - Self-healing: the table is created on first use (same approach as the rest of
 *     the project), so no manual migration is needed.
 */

/** PUP student number, e.g. 2024-00048-BN-0 */
const STUDENT_NO_PATTERN = '/^\d{4}-\d{5}-[A-Z]{2}-\d$/';

/** Courses a student can pick in the Student Portal (same order as the dropdown). */
const STUDENT_COURSES = ['BSIT', 'BSCpE', 'BSIE', 'BSBA', 'BSEEd', 'BSEd', 'BSPSY', 'DIT', 'DCET'];

if (!function_exists('student_registry_valid_course')) {
    function student_registry_valid_course(string $course): bool
    {
        return in_array($course, STUDENT_COURSES, true);
    }
}

if (!function_exists('student_registry_ensure_table')) {
    function student_registry_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS `tbl_student_registry` (
                `id`          int(11)      NOT NULL AUTO_INCREMENT,
                `student_no`  varchar(30)  NOT NULL,
                `full_name`   varchar(255) NOT NULL,
                `last_name`   varchar(120) NOT NULL DEFAULT '',
                `given_names` varchar(180) NOT NULL DEFAULT '',
                `section`     varchar(60)  DEFAULT NULL,
                `course`      varchar(30)  DEFAULT NULL,
                `year_level`  varchar(20)  DEFAULT NULL,
                `school_year` varchar(15)  DEFAULT NULL,
                `semester`    varchar(15)  DEFAULT NULL,
                `uploaded_at` datetime     NOT NULL DEFAULT current_timestamp(),
                `uploaded_by` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_student_no` (`student_no`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $done = true;
    }
}

/** Canonical Student No.: trimmed, upper-case, no spaces, any dash variant -> "-". */
if (!function_exists('student_registry_normalize_no')) {
    function student_registry_normalize_no(string $raw): string
    {
        $s = mb_strtoupper(trim($raw), 'UTF-8');
        $s = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $s) ?? $s;
        $s = preg_replace('/\s+/u', '', $s) ?? $s;
        return $s;
    }
}

if (!function_exists('student_registry_is_valid_no')) {
    function student_registry_is_valid_no(string $normalized): bool
    {
        return (bool)preg_match(STUDENT_NO_PATTERN, $normalized);
    }
}

/**
 * "ABAD, KYRINE ANTONETTE" -> ['last' => 'ABAD', 'given' => 'KYRINE ANTONETTE'].
 * Splits on the FIRST comma, so "ORFELLA JR., RODERICK" keeps "JR." with the surname.
 * Returns null when the name has no "SURNAME, Given names" shape.
 */
if (!function_exists('student_registry_split_name')) {
    function student_registry_split_name(string $full): ?array
    {
        $full = trim(preg_replace('/\s+/u', ' ', $full) ?? $full);
        $pos  = strpos($full, ',');
        if ($pos === false) {
            return null;
        }
        $last  = trim(substr($full, 0, $pos));
        $given = trim(substr($full, $pos + 1));
        if ($last === '' || $given === '') {
            return null;
        }
        return ['last' => $last, 'given' => $given];
    }
}

if (!function_exists('student_registry_count')) {
    function student_registry_count(mysqli $conn): int
    {
        student_registry_ensure_table($conn);
        $res = $conn->query('SELECT COUNT(*) AS cnt FROM tbl_student_registry');
        return $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
    }
}

/** Numbers for the Settings card. */
if (!function_exists('student_registry_status')) {
    function student_registry_status(mysqli $conn): array
    {
        student_registry_ensure_table($conn);
        $out = ['count' => 0, 'sections' => 0, 'last_at' => '', 'last_by' => ''];
        $res = $conn->query(
            "SELECT COUNT(*) AS cnt,
                    COUNT(DISTINCT section) AS secs,
                    MAX(uploaded_at) AS last_at
               FROM tbl_student_registry"
        );
        if ($res && ($r = $res->fetch_assoc())) {
            $out['count']    = (int)($r['cnt'] ?? 0);
            $out['sections'] = (int)($r['secs'] ?? 0);
            $out['last_at']  = (string)($r['last_at'] ?? '');
        }
        if ($out['count'] > 0) {
            $res = $conn->query('SELECT uploaded_by FROM tbl_student_registry ORDER BY uploaded_at DESC, id DESC LIMIT 1');
            if ($res && ($r = $res->fetch_assoc())) {
                $out['last_by'] = (string)($r['uploaded_by'] ?? '');
            }
        }
        return $out;
    }
}

/**
 * The check the Student Portal runs.
 *
 * @return array{ok:bool, enforced:bool, student_no:string, error:string}
 */
if (!function_exists('student_registry_validate')) {
    function student_registry_validate(mysqli $conn, string $student_no_raw): array
    {
        $no  = student_registry_normalize_no($student_no_raw);
        $out = ['ok' => true, 'enforced' => false, 'student_no' => $no, 'error' => ''];

        try {
            if (student_registry_count($conn) === 0) {
                return $out;                               // no list imported yet -> not enforced
            }
            $out['enforced'] = true;

            $generic = 'We could not find this Student ID on the official student list. '
                . 'Please check it and try again, or visit the Admin Office for help.';

            if (!student_registry_is_valid_no($no)) {
                $out['ok']    = false;
                $out['error'] = $generic;
                return $out;
            }

            $found = false;
            if ($st = $conn->prepare('SELECT 1 FROM tbl_student_registry WHERE student_no = ? LIMIT 1')) {
                $st->bind_param('s', $no);
                $st->execute();
                $res   = $st->get_result();
                $found = $res && $res->num_rows === 1;
                $st->close();
            }

            if (!$found) {
                $out['ok']    = false;
                $out['error'] = $generic;
            }
            return $out;
        } catch (Throwable $e) {
            error_log('[student-registry] validate failed: ' . $e->getMessage());
            return [
                'ok'         => false,
                'enforced'   => true,
                'student_no' => $no,
                'error'      => 'The student list could not be checked right now. Please try again in a moment.',
            ];
        }
    }
}
