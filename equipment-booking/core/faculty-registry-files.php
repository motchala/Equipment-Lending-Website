<?php

declare(strict_types=1);

/**
 * faculty-registry-files.php  (shared helpers)
 *
 * Everything the Faculty Registry needs to keep the ORIGINAL file of every
 * import (Faculty tab -> Faculty Registry), so each import is fully recorded:
 *
 *   upload-faculty-registry-file.php   stores the original file + a record
 *   upload-faculty-registry.php        marks that record as imported (+ counts)
 *   list-faculty-registry-files.php    password-gated history of all files
 *   get-faculty-registry-file.php      password-gated view / download
 *
 * Where things live
 *   - files:   uploads/faculty_registry/<random>.pdf|xlsx  (never the uploaded
 *              name; the folder denies ALL direct web access - files are only
 *              ever served through get-faculty-registry-file.php)
 *   - records: table tbl_faculty_registry_files (created on first use if the
 *              SQL dump was not re-imported)
 */

const FRF_MAX_BYTES = 10 * 1024 * 1024;   // same 10 MB cap as the Import form
const FRF_TOKEN_TTL = 600;                // seconds an unlock stays valid (10 min)

function frf_respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/** Absolute path of the (web-blocked) folder holding the original files. */
function frf_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/faculty_registry';
}

/** Creates the folder (and its "deny everything" .htaccess) if missing. */
function frf_ensure_dir(): bool
{
    $dir = frf_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents(
            $ht,
            "# Faculty Registry originals are never served directly.\n" .
            "# They are only available through equipment-booking/api/get-faculty-registry-file.php\n" .
            "# (Super Admin + password).\n" .
            "Options -Indexes\n" .
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        );
    }
    return is_writable($dir);
}

/** Full path for a stored file name, or null if the name isn't one we generated. */
function frf_stored_path(string $stored_name): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.(pdf|xlsx)$/', $stored_name)) {
        return null;
    }
    return frf_dir() . '/' . $stored_name;
}

/** Self-healing: creates the history table on first use. */
function frf_ensure_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS `tbl_faculty_registry_files` (
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
            UNIQUE KEY `uq_registry_file_stored` (`stored_name`),
            KEY `idx_registry_file_uploaded` (`uploaded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/** A safe display name: no paths, no control characters or quotes, max 255 bytes. */
function frf_clean_filename(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F"<>:*?|]+/u', '', $name) ?? '';
    $name = trim($name);
    if ($name === '') {
        return 'faculty-registry';
    }
    while (strlen($name) > 255) {
        $name = mb_substr($name, 0, mb_strlen($name) - 1);
    }
    return $name;
}

/**
 * Checks what the file really is (not what its name claims).
 * Returns ['ext' => 'pdf'|'xlsx', 'mime' => ...] or null if it is neither.
 */
function frf_detect_type(string $tmp_path, string $claimed_ext): ?array
{
    $fh = @fopen($tmp_path, 'rb');
    if (!$fh) {
        return null;
    }
    $head = (string)fread($fh, 1024);
    fclose($fh);

    if ($claimed_ext === 'pdf' && strpos($head, '%PDF-') !== false) {
        return ['ext' => 'pdf', 'mime' => 'application/pdf'];
    }
    if ($claimed_ext === 'xlsx' && strncmp($head, "PK\x03\x04", 4) === 0) {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmp_path) !== true) {
                return null;
            }
            $ok = $zip->locateName('xl/workbook.xml') !== false;
            $zip->close();
            if (!$ok) {
                return null;
            }
        }
        return ['ext' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }
    return null;
}

/* ---------------------------------------------------------------------------
 * Password re-entry: identical rules to view-faculty-registry.php, and it uses
 * the same per-session throttle (5 wrong tries, then 5 minutes), so this adds
 * no extra way to guess the password.
 * ------------------------------------------------------------------------ */
function frf_verify_password(mysqli $conn, string $given): void
{
    $now = time();
    if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
        $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
        frf_respond(429, [
            'status' => 'error',
            'code'   => 'bad_password',
            'error'  => "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.',
        ]);
    }

    if ($given === '') {
        frf_respond(422, ['status' => 'error', 'code' => 'bad_password', 'error' => 'Enter your password to continue.']);
    }

    $actor_hash = '';
    if ($st = $conn->prepare('SELECT password FROM tbl_accounts WHERE email = ? LIMIT 1')) {
        $email = (string)($_SESSION['admin_email'] ?? '');
        $st->bind_param('s', $email);
        $st->execute();
        $r = $st->get_result();
        $actor_hash = ($r && $r->num_rows === 1) ? (string)$r->fetch_assoc()['password'] : '';
        $st->close();
    }

    if ($actor_hash === '' || !password_verify($given, $actor_hash)) {
        $_SESSION['mgmt_reauth_fails'] = (int)($_SESSION['mgmt_reauth_fails'] ?? 0) + 1;
        if ($_SESSION['mgmt_reauth_fails'] >= 5) {
            $_SESSION['mgmt_reauth_fails']      = 0;
            $_SESSION['mgmt_reauth_lock_until'] = $now + 300;
            frf_respond(429, ['status' => 'error', 'code' => 'bad_password', 'error' => 'Too many incorrect passwords. Try again in 5 minutes.']);
        }
        frf_respond(403, ['status' => 'error', 'code' => 'bad_password', 'error' => 'Incorrect password.']);
    }
    unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);
}

/* ---------------------------------------------------------------------------
 * Unlock token. After the password is verified once, the list endpoint hands
 * back a random token (valid 10 minutes, tied to this login session) that the
 * View / Download buttons use, so the password never has to stay in the page.
 * Only a hash of the token is kept in the session.
 * ------------------------------------------------------------------------ */
function frf_issue_token(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['frf_access'] = [
        'hash'    => hash('sha256', $token),
        'expires' => time() + FRF_TOKEN_TTL,
    ];
    return $token;
}

function frf_token_valid(string $token): bool
{
    $a = $_SESSION['frf_access'] ?? null;
    if (!is_array($a) || $token === '' || !isset($a['hash'], $a['expires'])) {
        return false;
    }
    if (time() > (int)$a['expires']) {
        unset($_SESSION['frf_access']);
        return false;
    }
    return hash_equals((string)$a['hash'], hash('sha256', $token));
}
