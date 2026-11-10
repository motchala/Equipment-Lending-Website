<?php

/**
 * faculty-first-login.php
 *
 * Rules for the "first sign-in" setup every NEW faculty account goes through.
 *
 * How it works
 * ------------
 *   - A Super Admin creates the account with a randomly generated password
 *     (Faculty tab). The account is saved with tbl_users.must_change_password = 1.
 *   - While that flag is 1 the account is LOCKED: faculty_session_guard()
 *     (auth/core/admin-auth.php, runs on every request) sends every page to
 *     faculty-setup.php and answers every API call with a 403 "setup required".
 *   - faculty-setup.php (two steps: new password, then the essential details) posts
 *     once to account-management/api/first-login-setup.php, which saves the
 *     password + details and clears the flag in ONE statement. It is never asked again.
 *
 * Accounts that existed before this feature keep the column default (0), so they
 * are never locked.
 */

/** The only scripts a locked faculty session may still reach, as paths relative to the site root. */
const FACULTY_FIRST_LOGIN_ALLOWED = [
    'account-management/faculty-setup.php',
    'account-management/api/first-login-setup.php',
    'auth/api/logout.php',
];

/**
 * Add tbl_users.must_change_password when it is missing (older databases catch up by themselves,
 * same pattern as the other ensureSchema() helpers). Returns true when the column exists afterwards.
 */
function faculty_fl_ensure_schema(mysqli $conn): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $res = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'must_change_password'");
        if ($res && $res->num_rows > 0) {
            return $ok = true;
        }
        $conn->query("ALTER TABLE tbl_users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        $res = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'must_change_password'");
        return $ok = (bool)($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        error_log('[faculty-first-login] ensure schema: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * A random temporary password: 12 characters from an unambiguous alphabet (no 0/O, 1/l/I), always with
 * an upper-case letter, a lower-case letter, a digit and a symbol, and always rated Strong or better.
 * The Super Admin screen generates its own in the browser (Web Crypto); this server twin exists so the
 * rule lives in one documented place and can be reused (for example by a future "reset password").
 */
function faculty_fl_generate_password(int $length = 12): string
{
    $sets = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%&*?',
    ];
    $all = implode('', $sets);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {      // Fisher-Yates with a CSPRNG
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        $pw = implode('', $chars);
        if (faculty_fl_password_level($pw) >= 3) {
            return $pw;
        }
    }
    return bin2hex(random_bytes(8)) . 'Aa#';               // practically unreachable
}

/**
 * Password strength, -1 (too short) .. 4 (very strong). Same scoring as the Faculty tab used before and
 * as faculty-first-login.js uses on the setup screen, so the meter and the server always agree.
 * A password must reach 2 ("Fair") to be accepted.
 */
function faculty_fl_password_level(string $password): int
{
    if (strlen($password) < 8) {
        return -1;
    }

    $weak_patterns = [
        '0123', '1234', '2345', '3456', '4567', '5678', '6789', '7890',
        '9876', '8765', '7654', '6543', '5432', '4321', '3210',
        'abcd', 'bcde', 'cdef', 'defg', 'efgh', 'fghi', 'ghij', 'hijk',
        'ijkl', 'jklm', 'klmn', 'lmno', 'mnop', 'nopq', 'opqr', 'pqrs',
        'qrst', 'rstu', 'stuv', 'tuvw', 'uvwx', 'vwxy', 'wxyz',
        'dcba', 'edcb', 'fedc', 'gfed', 'hgfe', 'ihgf', 'jihg', 'kjih',
        'lkji', 'mlkj', 'nmlk', 'onml', 'ponm', 'qpon', 'rqpo', 'srqp',
        'tsrq', 'utsr', 'vuts', 'wvut', 'xwvu', 'yxwv', 'zyxw',
        'qwer', 'wert', 'erty', 'asdf', 'sdfg', 'dfgh', 'zxcv', 'xcvb',
        'password', 'passw0rd', 'qwerty', 'welcome', 'letmein', 'admin',
        'iloveyou', 'monkey', 'dragon', 'football', 'baseball', 'master',
    ];
    $normalized = strtolower($password);
    if (preg_match('/(.)\1{3,}/i', $password)) {
        return 1;
    }
    foreach ($weak_patterns as $pattern) {
        if (strpos($normalized, $pattern) !== false) {
            return 1;
        }
    }

    $score = strlen($password) >= 16 ? 3 : (strlen($password) >= 12 ? 2 : 1);
    if (preg_match('/[a-z]/', $password)) $score++;
    if (preg_match('/[A-Z]/', $password)) $score++;
    if (preg_match('/[0-9]/', $password)) $score++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;

    if ($score <= 1) return 0;
    if ($score <= 2) return 1;
    if ($score === 3) return 2;
    if ($score <= 5) return 3;
    return 4;
}

/** Where the site root is on the web ("/Equipment-Lending-Website/"), whatever folder the running script sits in. */
function faculty_fl_web_base(): string
{
    $root   = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $name   = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '' && strncmp($script, $root, strlen($root)) === 0) {
        $rel = ltrim(str_replace('\\', '/', substr($script, strlen($root))), '/');
        if ($rel !== '' && substr($name, -strlen($rel)) === $rel) {
            return substr($name, 0, strlen($name) - strlen($rel));
        }
    }
    return rtrim(dirname($name), '/') . '/';
}

/** The running script as a path relative to the site root ("settings/api/update-profile.php"). */
function faculty_fl_current_script(): string
{
    $root   = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    if ($script !== '' && strncmp($script, $root, strlen($root)) === 0) {
        return ltrim(str_replace('\\', '/', substr($script, strlen($root))), '/');
    }
    return basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
}

/** Is this a normal browser page load (redirect it) rather than a fetch()/XHR call (answer with JSON)? */
function faculty_fl_is_page_request(): bool
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return false;
    }
    if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
        return false;
    }
    $dest = strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    if ($dest !== '') {
        return $dest === 'document' || $dest === 'iframe';
    }
    return stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false;
}
