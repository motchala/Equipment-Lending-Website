<?php

/**
 * faculty-departments.php
 *
 * Single source of truth for the department list a faculty member can pick in
 * Settings > Personal Information. Used by faculty-dashboard.php (the <select>)
 * and equipment-booking/api/update-profile.php (server-side validation), so the
 * two can never drift apart.
 *
 * NOTE: tbl_users.department must be at least varchar(100) to hold these names
 * (see FACULTY_DEPARTMENT_MAX_LEN and faculty_ensure_department_column()).
 */

const FACULTY_DEPARTMENT_MAX_LEN = 100;

const FACULTY_DEPARTMENTS = [
    'Department of Information Technology/Computing',
    'Department of Computer Engineering & Engineering Sciences',
    'Department of Business Administration / Management',
    'Department of Psychology',
    'Department of Teacher Education',
    'Department of General Education & Social Sciences',
    'Department of Engineering Technology / Technical Programs',
];

function faculty_departments(): array
{
    return FACULTY_DEPARTMENTS;
}

function faculty_department_is_valid(string $department): bool
{
    return in_array($department, FACULTY_DEPARTMENTS, true);
}
