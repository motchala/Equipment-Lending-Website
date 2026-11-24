<?php

const REMEMBER_ME_COOKIE = 'pupsync_remember';
const REMEMBER_ME_DAYS = 30;

function remember_me_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS tbl_remember_tokens (
        selector CHAR(32) NOT NULL PRIMARY KEY,
        validator_hash CHAR(64) NOT NULL,
        account_type ENUM('admin', 'faculty') NOT NULL,
        account_email VARCHAR(255) NOT NULL,
        credential_hash CHAR(64) NOT NULL,
        auth_time BIGINT UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT NULL,
        prev_selector CHAR(32) DEFAULT NULL,
        prev_validator_hash CHAR(64) DEFAULT NULL,
        prev_valid_until DATETIME DEFAULT NULL,
        INDEX idx_remember_prev_selector (prev_selector),
        INDEX idx_remember_expiry (expires_at),
        INDEX idx_remember_account (account_type, account_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Could not initialize remember-me tokens: ' . $conn->error);
    }

    // Self-heal: tables created before the "previous token grace window" existed
    // do not have the prev_* columns yet. Add them once. If the host refuses the
    // ALTER, the grace window is simply switched off and everything else works
    // exactly as before.
    $hasGrace = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM tbl_remember_tokens LIKE 'prev_selector'");
        $hasGrace = $col && $col->num_rows > 0;
        if ($col) {
            $col->free();
        }
        if (!$hasGrace) {
            $hasGrace = (bool)$conn->query(
                'ALTER TABLE tbl_remember_tokens '
                . 'ADD COLUMN prev_selector CHAR(32) DEFAULT NULL, '
                . 'ADD COLUMN prev_validator_hash CHAR(64) DEFAULT NULL, '
                . 'ADD COLUMN prev_valid_until DATETIME DEFAULT NULL, '
                . 'ADD INDEX idx_remember_prev_selector (prev_selector)'
            );
        }
    } catch (Throwable $e) {
        // Several requests can reach the ALTER at the same moment right after a
        // deploy (the loser gets "duplicate column"). Re-check before giving up.
        $hasGrace = false;
        try {
            $col = $conn->query("SHOW COLUMNS FROM tbl_remember_tokens LIKE 'prev_selector'");
            $hasGrace = $col && $col->num_rows > 0;
            if ($col) {
                $col->free();
            }
        } catch (Throwable $ignored) {
            $hasGrace = false;
        }
        if (!$hasGrace) {
            error_log('[PUPSync] remember-me grace columns unavailable: ' . $e->getMessage());
        }
    }
    remember_me_grace_state($hasGrace);

    $done = true;
}

// Whether the prev_* columns exist (set by remember_me_ensure_schema()).
function remember_me_grace_state(?bool $set = null): bool
{
    static $state = false;
    if ($set !== null) {
        $state = $set;
    }
    return $state;
}

// Seconds the previous cookie keeps working after a rotation. This covers a
// browser restoring several tabs at once, where every tab sends the same old cookie.
const REMEMBER_ME_GRACE_SECONDS = 60;

function remember_me_cookie_options(int $expires): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax', // Lax so the first visit from an outside link (email, Facebook) still restores the login
    ];
}

function remember_me_clear_cookie(): void
{
    setcookie(REMEMBER_ME_COOKIE, '', remember_me_cookie_options(time() - 3600));
    unset($_COOKIE[REMEMBER_ME_COOKIE]);
}

function remember_me_delete_selector(mysqli $conn, string $selector): void
{
    $stmt = $conn->prepare('DELETE FROM tbl_remember_tokens WHERE selector = ?');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remember-me token revocation: ' . $conn->error);
    }
    $stmt->bind_param('s', $selector);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not revoke remember-me token: ' . $error);
    }
    $stmt->close();
}

function remember_me_remove_current(mysqli $conn): void
{
    $cookie = (string)($_COOKIE[REMEMBER_ME_COOKIE] ?? '');
    if (preg_match('/\A([a-f0-9]{32})\.[a-f0-9]{64}\z/i', $cookie, $matches)) {
        remember_me_ensure_schema($conn);
        remember_me_delete_selector($conn, $matches[1]);
    }

    remember_me_clear_cookie();
}

function remember_me_issue(mysqli $conn, string $accountType, string $email, string $passwordHash, int $authTime): void
{
    if (!in_array($accountType, ['admin', 'faculty'], true)) {
        throw new InvalidArgumentException('Unsupported remember-me account type.');
    }

    remember_me_remove_current($conn);
    remember_me_ensure_schema($conn);
    if (!$conn->query('DELETE FROM tbl_remember_tokens WHERE expires_at <= UTC_TIMESTAMP()')) {
        throw new RuntimeException('Could not remove expired remember-me tokens: ' . $conn->error);
    }

    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);
    $credentialHash = hash('sha256', $passwordHash);
    $expiresAt = time() + (REMEMBER_ME_DAYS * 86400);
    $expiresAtSql = gmdate('Y-m-d H:i:s', $expiresAt);

    $stmt = $conn->prepare(
        'INSERT INTO tbl_remember_tokens '
        . '(selector, validator_hash, account_type, account_email, credential_hash, auth_time, expires_at) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remember-me token: ' . $conn->error);
    }
    $stmt->bind_param('sssssis', $selector, $validatorHash, $accountType, $email, $credentialHash, $authTime, $expiresAtSql);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not create remember-me token: ' . $error);
    }
    $stmt->close();

    if (!setcookie(REMEMBER_ME_COOKIE, $selector . '.' . $validator, remember_me_cookie_options($expiresAt))) {
        remember_me_delete_selector($conn, $selector);
        throw new RuntimeException('Could not set the remember-me cookie because response headers were already sent.');
    }
}

// Looks a cookie up in tbl_remember_tokens. Returns the row with two extra keys:
//   live_selector - the selector the token has RIGHT NOW (differs from the cookie's
//                   selector when the cookie is the previous, already-rotated one)
//   via_grace     - true when the cookie matched only the previous token, inside
//                   its short grace window
// 'validator_hash' always holds the hash that the cookie's validator must match.
function remember_me_find_token(mysqli $conn, string $selector): ?array
{
    $stmt = $conn->prepare(
        'SELECT selector AS live_selector, validator_hash, account_type, account_email, credential_hash, auth_time, expires_at '
        . 'FROM tbl_remember_tokens WHERE selector = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remember-me lookup: ' . $conn->error);
    }
    $stmt->bind_param('s', $selector);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not look up remember-me token: ' . $error);
    }
    $result = $stmt->get_result();
    $token = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($token) {
        $token['via_grace'] = false;
        return $token;
    }

    if (!remember_me_grace_state()) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT selector AS live_selector, prev_validator_hash AS validator_hash, account_type, account_email, credential_hash, auth_time, expires_at '
        . 'FROM tbl_remember_tokens WHERE prev_selector = ? AND prev_valid_until > UTC_TIMESTAMP() '
        . 'AND expires_at > UTC_TIMESTAMP() LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remember-me grace lookup: ' . $conn->error);
    }
    $stmt->bind_param('s', $selector);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not look up previous remember-me token: ' . $error);
    }
    $result = $stmt->get_result();
    $token = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($token) {
        $token['via_grace'] = true;
    }
    return $token;
}

function remember_me_restore(mysqli $conn): void
{
    if (isset($_SESSION['admin']) || isset($_SESSION['faculty_id'])) {
        return;
    }

    $cookie = (string)($_COOKIE[REMEMBER_ME_COOKIE] ?? '');
    if ($cookie === '') {
        return;
    }

    if (!preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/i', $cookie, $matches)) {
        remember_me_clear_cookie();
        return;
    }

    remember_me_ensure_schema($conn);
    $selector = $matches[1];
    $validator = $matches[2];

    $token = remember_me_find_token($conn, $selector);

    if (!$token || !hash_equals((string)$token['validator_hash'], hash('sha256', $validator))) {
        // A real mismatch (or an unknown selector): drop the token and the cookie.
        remember_me_delete_selector($conn, $token ? (string)$token['live_selector'] : $selector);
        remember_me_clear_cookie();
        return;
    }

    // true when this cookie is the previous one, rotated away moments ago by
    // another request (e.g. several tabs restoring at once). In that case the
    // newer cookie is already on its way to / in the browser, so this request
    // signs the user in but neither rotates the token nor touches the cookie.
    $viaGrace = (bool)$token['via_grace'];
    $liveSelector = (string)$token['live_selector'];

    $accountType = (string)$token['account_type'];
    $email = (string)$token['account_email'];
    $account = null;

    if ($accountType === 'admin') {
        admin_accounts_ensure_schema($conn);
        $accountStmt = $conn->prepare(
            'SELECT fullName, email, password, role, dormant_until, session_epoch '
            . 'FROM tbl_accounts WHERE email = ? LIMIT 1'
        );
    } elseif ($accountType === 'faculty') {
        require_once __DIR__ . '/../../account-management/core/faculty-status.php';
        $accountStmt = $conn->prepare(
            'SELECT faculty_id, fullname, email, password, ' . faculty_status_select($conn)
            . ' FROM tbl_users WHERE email = ? LIMIT 1'
        );
    } else {
        $accountStmt = false;
    }

    if ($accountStmt) {
        $accountStmt->bind_param('s', $email);
        if (!$accountStmt->execute()) {
            $error = $accountStmt->error;
            $accountStmt->close();
            throw new RuntimeException('Could not validate remember-me account: ' . $error);
        }
        $accountResult = $accountStmt->get_result();
        $account = ($accountResult && $accountResult->num_rows === 1) ? $accountResult->fetch_assoc() : null;
        $accountStmt->close();
    }

    $authTime = (int)$token['auth_time'];
    $validAccount = $account
        && hash_equals((string)$token['credential_hash'], hash('sha256', (string)$account['password']));

    if ($validAccount && $accountType === 'admin') {
        $validAccount = !admin_is_dormant($account['dormant_until'])
            && (int)$account['session_epoch'] <= $authTime;
    }
    // A deactivated faculty account is not restored; the token and cookie are dropped below.
    if ($validAccount && $accountType === 'faculty') {
        $validAccount = (int)$account['is_active'] !== 0;
    }

    if (!$validAccount) {
        remember_me_delete_selector($conn, $liveSelector);
        remember_me_clear_cookie();
        return;
    }

    // Regenerate the session ID first so the new session is established
    // before we rotate the remember-me token in the DB.  On some shared
    // hosts (e.g. InfinityFree) session_regenerate_id(true) can fail because
    // the session save path is not writable or the old file cannot be
    // deleted.  We fall back to a plain regenerate (no delete) in that case,
    // and log a warning, rather than throwing — which would leave the DB
    // token already rotated but no new cookie sent, permanently breaking
    // remember-me for the user.
    if (!@session_regenerate_id(true)) {
        // Fallback: regenerate without deleting the old session file.
        @session_regenerate_id(false);
        error_log('[PUPSync] remember_me_restore: session_regenerate_id(true) failed; fell back to no-delete regeneration.');
    }

    if (!$viaGrace) {
        $newSelector = bin2hex(random_bytes(16));
        $newValidator = bin2hex(random_bytes(32));
        $newValidatorHash = hash('sha256', $newValidator);

        // With the grace columns available, the token being replaced is kept for
        // REMEMBER_ME_GRACE_SECONDS so that sibling requests still carrying the old
        // cookie are not mistaken for theft. (MySQL evaluates SET left to right, so
        // the prev_* values copy the OLD selector/validator before they are replaced.)
        if (remember_me_grace_state()) {
            $rotate = $conn->prepare(
                'UPDATE tbl_remember_tokens SET prev_selector = selector, prev_validator_hash = validator_hash, '
                . 'prev_valid_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . (int)REMEMBER_ME_GRACE_SECONDS . ' SECOND), '
                . 'selector = ?, validator_hash = ?, last_used_at = UTC_TIMESTAMP() '
                . 'WHERE selector = ? AND validator_hash = ? AND expires_at > UTC_TIMESTAMP()'
            );
        } else {
            $rotate = $conn->prepare(
                'UPDATE tbl_remember_tokens SET selector = ?, validator_hash = ?, last_used_at = UTC_TIMESTAMP() '
                . 'WHERE selector = ? AND validator_hash = ? AND expires_at > UTC_TIMESTAMP()'
            );
        }
        if (!$rotate) {
            throw new RuntimeException('Could not prepare remember-me token rotation: ' . $conn->error);
        }
        $rotate->bind_param('ssss', $newSelector, $newValidatorHash, $selector, $token['validator_hash']);
        if (!$rotate->execute()) {
            $error = $rotate->error;
            $rotate->close();
            throw new RuntimeException('Could not rotate remember-me token: ' . $error);
        }
        $rotated = $rotate->affected_rows === 1;
        $rotate->close();

        if (!$rotated) {
            // Another request rotated this very token between our lookup and our
            // update (e.g. two tabs restoring at the same moment). That is not theft:
            // if the old cookie is still inside its grace window, sign this request
            // in too, and leave the cookie alone so the winner's new cookie survives.
            $retry = remember_me_find_token($conn, $selector);
            if (!$retry || !$retry['via_grace'] || !hash_equals((string)$retry['validator_hash'], hash('sha256', $validator))) {
                // Never clear the cookie here: the browser may already hold the winner's new one.
                return;
            }
            $viaGrace = true;
        }
    }

    if (!$viaGrace) {
        $expiresAt = strtotime((string)$token['expires_at'] . ' UTC');
        // setcookie() can silently return false on some shared hosts even when
        // headers have not been sent yet.  Treat failure as non-fatal: the
        // session variables are already set so this page-load succeeds; the
        // next page-load will just re-issue from the now-rotated token stored in
        // the DB (the old cookie value will fail the validator check, which is
        // the normal theft-detection path, so a new remember_me_issue() will be
        // needed on the next full login).
        if ($expiresAt !== false) {
            $cookieSent = setcookie(
                REMEMBER_ME_COOKIE,
                $newSelector . '.' . $newValidator,
                remember_me_cookie_options($expiresAt)
            );
            if ($cookieSent) {
                $_COOKIE[REMEMBER_ME_COOKIE] = $newSelector . '.' . $newValidator;
            } else {
                error_log('[PUPSync] remember_me_restore: setcookie() returned false during token rotation.');
            }
        }
    }

    if ($accountType === 'admin') {
        $_SESSION['admin'] = true;
        $_SESSION['admin_name'] = (string)($account['fullName'] ?: 'Administrator');
        $_SESSION['admin_email'] = (string)$account['email'];
        $_SESSION['admin_role'] = ($account['role'] === 'Super Admin') ? 'Super Admin' : 'Admin';
        $_SESSION['login_time'] = $authTime;
    } else {
        $_SESSION['faculty_id'] = (string)$account['faculty_id'];
        $_SESSION['faculty_name'] = (string)$account['fullname'];
        $_SESSION['faculty_email'] = (string)$account['email'];
        $_SESSION['login_time'] = $authTime;

        // Getting back in through "stay logged in" counts as signing in: an open password-reset request
        // (auth/core/password-reset.php) is withdrawn automatically.
        require_once __DIR__ . '/password-reset.php';
        pwr_clear_on_success($conn, (string)$account['email']);
    }
}
