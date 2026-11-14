<?php

if (!function_exists('notif_database_timezone')) {
    function notif_database_timezone(mysqli $conn): DateTimeZone
    {
        static $timezone = null;
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        $result = $conn->query('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW()) AS offset_minutes');
        $row = $result ? $result->fetch_assoc() : null;
        if (!$row || !is_numeric($row['offset_minutes'] ?? null)) {
            error_log('[PUPSync] Unable to determine the database timezone for notifications: ' . $conn->error);
            $timezone = new DateTimeZone(date_default_timezone_get());
            return $timezone;
        }

        $offset = (int)$row['offset_minutes'];
        $absoluteOffset = abs($offset);
        $timezone = new DateTimeZone(sprintf(
            '%s%02d:%02d',
            $offset < 0 ? '-' : '+',
            intdiv($absoluteOffset, 60),
            $absoluteOffset % 60
        ));
        return $timezone;
    }
}

if (!function_exists('notif_datetime')) {
    function notif_datetime(mysqli $conn, string $datetime): DateTimeImmutable
    {
        static $manilaTimezone = null;
        if ($manilaTimezone === null) {
            $manilaTimezone = new DateTimeZone('Asia/Manila');
        }

        $databaseDateTime = new DateTimeImmutable($datetime, notif_database_timezone($conn));
        return $databaseDateTime->setTimezone($manilaTimezone);
    }
}
