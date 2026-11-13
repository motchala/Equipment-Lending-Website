<?php

/**
 * faculty-account.php
 *
 * Small, dependency-free helpers for the faculty Settings screen:
 *   - faculty_mask_email()           mask an email's local part while keeping its domain visible
 *   - faculty_device_info()          turn a User-Agent into "Chrome on Windows PC"
 *   - faculty_ensure_department_column()  widen tbl_users.department if it is still varchar(50)
 */

require_once __DIR__ . '/../../shared/core/faculty-departments.php';

/**
 * "edmundodelacruz@pupsync.edu" -> "edmun••••••@pupsync.edu"
 * Only the first 5 letters of the address (the part before the "@") are shown. The rest of the
 * local part is hidden behind a FIXED number of dots, while the domain remains visible.
 * Not HTML-escaped.
 */
function faculty_mask_email(?string $email, int $visible = 5): string
{
    $email = trim((string)$email);
    if ($email === '') {
        return '';
    }
    $dots = static fn(int $n): string => str_repeat("\u{2022}", $n);

    $at = strrpos($email, '@');
    if ($at === false) {
        $local  = $email;
        $domain = '';
    } else {
        $local  = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
    }

    $chars   = preg_split('//u', $local, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $shown   = implode('', array_slice($chars, 0, $visible));
    $maskedL = $shown . (count($chars) > $visible ? $dots(6) : '');

    if ($at === false) {
        return $maskedL;
    }
    return $maskedL . '@' . $domain;
}

/**
 * Describe the device behind a User-Agent string.
 * Returns ['label' => 'Chrome on Windows PC', 'kind' => 'desktop'|'phone'|'tablet'|'unknown'].
 * (A User-Agent cannot tell Windows 10 from 11, or an iPad in "desktop" mode from a Mac.)
 */
function faculty_device_info(?string $ua): array
{
    $ua = trim((string)$ua);
    if ($ua === '') {
        return ['label' => 'Unknown device', 'kind' => 'unknown'];
    }

    // Browser: order matters, because most browsers also contain "Chrome" and/or "Safari".
    if (preg_match('~\b(Edg|EdgA|EdgiOS|Edge)/~', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('~\b(OPR|OPiOS|Opera)\b~', $ua)) {
        $browser = 'Opera';
    } elseif (stripos($ua, 'SamsungBrowser') !== false) {
        $browser = 'Samsung Internet';
    } elseif (stripos($ua, 'Firefox/') !== false || stripos($ua, 'FxiOS/') !== false) {
        $browser = 'Firefox';
    } elseif (preg_match('~(Chrome|CriOS|Chromium)/~', $ua)) {   // also matches "HeadlessChrome/"
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Safari/') !== false && stripos($ua, 'Version/') !== false) {
        $browser = 'Safari';
    } elseif (stripos($ua, 'MSIE ') !== false || stripos($ua, 'Trident/') !== false) {
        $browser = 'Internet Explorer';
    } else {
        $browser = 'Browser';
    }

    // Device / OS
    if (preg_match('~iPhone|iPod~i', $ua)) {
        $device = 'iPhone';
        $kind   = 'phone';
    } elseif (stripos($ua, 'iPad') !== false) {
        $device = 'iPad';
        $kind   = 'tablet';
    } elseif (stripos($ua, 'Android') !== false) {
        $isPhone = stripos($ua, 'Mobile') !== false;
        $device  = $isPhone ? 'Android phone' : 'Android tablet';
        $kind    = $isPhone ? 'phone' : 'tablet';
    } elseif (stripos($ua, 'CrOS') !== false) {
        $device = 'Chromebook';
        $kind   = 'desktop';
    } elseif (stripos($ua, 'Windows') !== false) {
        $device = 'Windows PC';
        $kind   = 'desktop';
    } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS X') !== false) {
        $device = 'Mac';
        $kind   = 'desktop';
    } elseif (stripos($ua, 'Linux') !== false || stripos($ua, 'X11') !== false) {
        $device = 'Linux PC';
        $kind   = 'desktop';
    } else {
        $device = 'Unknown device';
        $kind   = 'unknown';
    }

    $label = $device === 'Unknown device' ? $browser : ($browser . ' on ' . $device);
    return ['label' => $label, 'kind' => $kind];
}

/**
 * The new department names are longer than the old varchar(50). Widen the column the first time
 * it is needed so saving never fails or truncates. Returns true when the column is wide enough.
 * (database/lending_db.sql and lending_db_upgrade.sql already define it as varchar(100).)
 */
function faculty_ensure_department_column(mysqli $conn): bool
{
    $res = @$conn->query(
        "SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND COLUMN_NAME = 'department'"
    );
    $len = $res ? (int)(($res->fetch_assoc()['len'] ?? 0)) : 0;
    if ($len === 0 || $len >= FACULTY_DEPARTMENT_MAX_LEN) {
        return true;
    }
    return (bool)@$conn->query(
        'ALTER TABLE tbl_users MODIFY department varchar(' . FACULTY_DEPARTMENT_MAX_LEN . ') DEFAULT NULL'
    );
}
