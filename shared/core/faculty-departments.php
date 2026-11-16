<?php

/**
 * faculty-departments.php
 *
 * Single source of truth for the department list a faculty member can pick in
 * Settings > Personal Information. Used by faculty-dashboard.php (the <select>)
 * and settings/api/update-profile.php (server-side validation), so the
 * two can never drift apart.
 *
 * A faculty member picks 1 to 3 of them (the first is required, the other two are optional).
 * They are stored together in tbl_users.department, separated by FACULTY_DEPARTMENT_DELIM, so
 * everything that just shows the department (report, chat assistant, arbitration search) keeps
 * working with no change. A single plain name is simply a list of one.
 *
 * NOTE: tbl_users.department must be at least varchar(255) to hold three of these names
 * (see FACULTY_DEPARTMENT_MAX_LEN and faculty_ensure_department_column()).
 */

const FACULTY_DEPARTMENT_MAX_LEN = 255;
const FACULTY_DEPARTMENT_MAX_PICKS = 3;
const FACULTY_DEPARTMENT_DELIM = '; ';   // never part of a department name

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

/**
 * Splits a stored/submitted department value into its names (trimmed, no blanks, no repeats,
 * original order). Does NOT check them against the list, so an older value is still shown.
 *
 * @return string[]
 */
function faculty_departments_split(string $value): array
{
    $out = [];
    foreach (explode(';', $value) as $part) {
        $part = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
        if ($part !== '' && !in_array($part, $out, true)) {
            $out[] = $part;
        }
    }
    return $out;
}

/** The value kept in tbl_users.department for a list of names. */
function faculty_departments_join(array $names): string
{
    return implode(FACULTY_DEPARTMENT_DELIM, array_values($names));
}

/**
 * Validates what a faculty member submitted: 1 to 3 different names, all from the list.
 *
 * @return array{0: string[], 1: string}  [names, error] - error is '' when the choice is good.
 */
function faculty_departments_validate_input(string $raw): array
{
    $names = faculty_departments_split($raw);
    if (!$names) {
        return [[], 'Select at least one department.'];
    }
    if (count($names) > FACULTY_DEPARTMENT_MAX_PICKS) {
        return [[], 'You can choose up to ' . FACULTY_DEPARTMENT_MAX_PICKS . ' departments.'];
    }
    foreach ($names as $n) {
        if (!faculty_department_is_valid($n)) {
            return [[], 'Please choose your departments from the list.'];
        }
    }
    return [$names, ''];
}

/** "Department of Psychology" -> "Psychology" (the chips are short; the list shows the full name). */
function faculty_department_short(string $name): string
{
    $s = preg_replace('/^Department of\s+/i', '', $name);
    return ($s === null || $s === '') ? $name : $s;
}

/**
 * One line for the page banner / report header: the first department, "+N more" when there are
 * others (the full list is on the Settings page).
 */
function faculty_departments_summary(string $value): string
{
    $names = faculty_departments_split($value);
    if (!$names) {
        return '';
    }
    return $names[0] . (count($names) > 1 ? ' +' . (count($names) - 1) . ' more' : '');
}

/**
 * The check-mark dropdown (assets: shared/assets/css/dept-multiselect.css + js/dept-multiselect.js).
 * Renders a hidden input (the value that is submitted: the chosen names joined by the delimiter,
 * in the order they were picked) plus the accessible listbox. The script keeps them in step.
 *
 * $opts: 'edit_field' => value for data-edit-field (Settings > Edit Profile),
 *        'disabled' => bool, 'describedby' => id of a hint element, 'label' => accessible name.
 */
function faculty_department_multiselect(string $id, string $name, array $selected, array $opts = []): string
{
    $h   = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $max = FACULTY_DEPARTMENT_MAX_PICKS;
    $sel = [];
    foreach ($selected as $s) {
        if (faculty_department_is_valid((string)$s) && !in_array($s, $sel, true)) {
            $sel[] = (string)$s;
        }
    }
    $sel   = array_slice($sel, 0, $max);
    $full  = count($sel) >= $max;
    $label = $opts['label'] ?? 'Departments';

    $o  = '<div class="ps-multisel' . (!empty($opts['disabled']) ? ' is-disabled' : '') . '" data-psms'
        . ' data-max="' . $max . '" data-delim="' . $h(FACULTY_DEPARTMENT_DELIM) . '"'
        . ' data-placeholder="Select your department(s)&hellip;">';
    $o .= '<input type="hidden" id="' . $h($id) . '" name="' . $h($name) . '" value="' . $h(faculty_departments_join($sel)) . '"'
        . (isset($opts['edit_field']) ? ' data-edit-field="' . $h($opts['edit_field']) . '"' : '')
        . (!empty($opts['disabled']) ? ' disabled' : '') . '>';
    $o .= '<button type="button" class="ps-multisel-trigger" id="' . $h($id) . 'Btn" aria-haspopup="listbox" aria-expanded="false"'
        . ' aria-controls="' . $h($id) . 'Panel"'
        . (!empty($opts['describedby']) ? ' aria-describedby="' . $h($opts['describedby']) . '"' : '')
        . (!empty($opts['disabled']) ? ' disabled' : '') . '>';
    $o .= '<span class="ps-multisel-display">';
    if ($sel) {
        $o .= '<span class="ps-multisel-chips">';
        foreach ($sel as $s) {
            $o .= '<span class="ps-multisel-chip" title="' . $h($s) . '"><span>' . $h(faculty_department_short($s)) . '</span></span>';
        }
        $o .= '</span>';
    } else {
        $o .= '<span class="ps-multisel-placeholder">Select your department(s)&hellip;</span>';
    }
    $o .= '</span><span class="material-symbols-outlined ps-multisel-caret" aria-hidden="true">expand_more</span></button>';
    $o .= '<div class="ps-multisel-panel" id="' . $h($id) . 'Panel" role="listbox" aria-multiselectable="true"'
        . ' aria-label="' . $h($label) . ' (choose up to ' . $max . ')" tabindex="-1" hidden>';
    $o .= '<div class="ps-multisel-head"><span>Choose up to ' . $max . '</span>'
        . '<span class="ps-multisel-count' . ($full ? ' is-full' : '') . '" aria-live="polite">' . count($sel) . ' of ' . $max . ' selected</span></div>';
    foreach (FACULTY_DEPARTMENTS as $dept) {
        $on = in_array($dept, $sel, true);
        $o .= '<div class="ps-multisel-opt" role="option" tabindex="-1" data-value="' . $h($dept) . '" data-short="' . $h(faculty_department_short($dept)) . '"'
            . ' aria-selected="' . ($on ? 'true' : 'false') . '"' . (($full && !$on) ? ' aria-disabled="true"' : '') . '>'
            . '<span class="ps-multisel-box" aria-hidden="true"><span class="material-symbols-outlined">check</span></span>'
            . '<span class="ps-multisel-label">' . $h($dept) . '</span></div>';
    }
    $o .= '<p class="ps-multisel-note" role="status" hidden>You can pick up to ' . $max . ' departments. Uncheck one to choose another.</p>';
    $o .= '</div></div>';
    return $o;
}
