<?php

declare(strict_types=1);

/**
 * student-registry-files.php  (shared helpers)
 *
 * Keeps the ORIGINAL class-list PDF of the latest Student List import (Settings ->
 * Imports), exactly like faculty-registry-files.php does for the Faculty Registry:
 * a new import replaces the previous file.
 *
 *   account-management/api/upload-student-registry-file.php   stores the original file + a record
 *   account-management/api/upload-student-registry.php        marks that record as imported (+ counts)
 *   settings/api/imports.php               lists the files / opens one (unlocked only)
 *
 * Files live in uploads/student_registry/<random>.pdf. The folder denies ALL direct
 * web access - a file is only ever served through settings/api/imports.php after the Super Admin
 * has unlocked Imports with their password.
 */

require_once __DIR__ . '/faculty-registry-files.php';   // shared: frf_respond, frf_detect_type, frf_clean_filename, FRF_MAX_BYTES

function srf_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/student_registry';
}

function srf_ensure_dir(): bool
{
    $dir = srf_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents(
            $ht,
            "# Student List originals are never served directly.\n" .
            "# They are only available through settings/api/imports.php\n" .
            "# (Super Admin + password).\n" .
            "Options -Indexes\n" .
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        );
    }
    return is_writable($dir);
}

function srf_stored_path(string $stored_name): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.pdf$/', $stored_name)) {
        return null;
    }
    return srf_dir() . '/' . $stored_name;
}

/**
 * Keeps ONLY the file of the import that just finished (same rule as the Faculty
 * Registry): every older stored file and its history record is removed, plus any
 * leftover file in the folder that has no record. A record is kept if its file
 * could not be deleted, so nothing is hidden from the list while still on disk.
 *
 * @return int number of old files removed
 */
function srf_purge_other_files(mysqli $conn, int $keep_id): int
{
    $removed = 0;
    $keep_stored = '';
    $old = [];

    if ($st = $conn->prepare('SELECT id, stored_name FROM tbl_student_registry_files')) {
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
        $path = srf_stored_path($o['stored']);
        if ($path !== null && is_file($path)) {
            if (!@unlink($path)) {
                error_log('[student-registry-files] could not delete old file ' . $path);
                continue;   // keep its record; it is retried on the next import
            }
            $removed++;
        }
        if ($del = $conn->prepare('DELETE FROM tbl_student_registry_files WHERE id = ?')) {
            $del->bind_param('i', $o['id']);
            $del->execute();
            $del->close();
        }
    }

    if ($keep_stored !== '' && is_dir(srf_dir())) {
        foreach ((array)scandir(srf_dir()) as $f) {
            if ($f === $keep_stored || !preg_match('/^[a-f0-9]{32}\.pdf$/', (string)$f)) {
                continue;
            }
            $path = srf_dir() . '/' . $f;
            if (is_file($path) && @unlink($path)) {
                $removed++;
            }
        }
    }
    return $removed;
}

function srf_ensure_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS `tbl_student_registry_files` (
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
            UNIQUE KEY `uq_student_file_stored` (`stored_name`),
            KEY `idx_student_file_uploaded` (`uploaded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}
