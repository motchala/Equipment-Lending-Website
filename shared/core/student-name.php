<?php

declare(strict_types=1);

/**
 * student-name.php
 *
 * Length check for the name a student types in the Student Portal (the faculty code / "Your name" form).
 * It is validation only: the form shows no counter or hint, and an error appears only when the name is
 * too long. Spaces count, runs of spaces count once, and letters like "ñ" count as one character.
 *
 * Change STUDENT_NAME_MAX_LENGTH here AND STUDENT_NAME_MAX in dashboard/assets/js/student-dashboard.js
 * (the browser check) together. The columns that store the name hold at least 100 characters.
 *
 * USAGE:
 *   require_once __DIR__ . '/../../shared/core/student-name.php';
 *   if (($err = student_name_error($student_name)) !== null) { echo json_encode(['error' => $err]); exit(); }
 */

const STUDENT_NAME_MAX_LENGTH = 50;

if (!function_exists('student_name_error')) {
    /** Error text when the name is too long, null when it is fine (an empty name is checked elsewhere). */
    function student_name_error(string $name): ?string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($name));
        if (!is_string($clean)) {
            $clean = trim($name);
        }
        // mbstring is normally on; the regex count is the same thing when a host leaves it out.
        $length = function_exists('mb_strlen') ? mb_strlen($clean, 'UTF-8') : (int)preg_match_all('/./su', $clean);
        if ($length > STUDENT_NAME_MAX_LENGTH) {
            return 'Your name is too long. Please check it and try again.';
        }
        return null;
    }
}
