<?php

declare(strict_types=1);

/**
 * student-courses.php
 *
 * The courses a student can pick in the Student Portal (same order as the dropdown in
 * student-dashboard.js). This is the only thing the Student Portal still checks about
 * "who" the student is: the Student No. is NOT validated or looked up anywhere.
 *
 * USAGE:
 *   require_once __DIR__ . '/../../shared/core/student-courses.php';
 *   if (!student_course_valid($course)) { ... }
 */

const STUDENT_COURSES = ['BSIT', 'BSCpE', 'BSIE', 'BSBA', 'BSEEd', 'BSEd', 'BSPSY', 'DIT', 'DCET'];

if (!function_exists('student_course_valid')) {
    function student_course_valid(string $course): bool
    {
        return in_array($course, STUDENT_COURSES, true);
    }
}
