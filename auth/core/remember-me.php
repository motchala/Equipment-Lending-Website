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
        INDEX idx_remember_expiry (expires_at),
        INDEX idx_remember_account (account_type, account_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Could not initialize remember-me tokens: ' . $conn->error);
    }
    $done = true;
}

function remember_me_cookie_options(int $expires): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
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

    $stmt = $conn->prepare(
        'SELECT validator_hash, account_type, account_email, credential_hash, auth_time, expires_at '
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

    if (!$token || !hash_equals((string)$token['validator_hash'], hash('sha256', $validator))) {
        remember_me_delete_selector($conn, $selector);
        remember_me_clear_cookie();
        return;
    }

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
        $accountStmt = $conn->prepare(
            'SELECT faculty_id, fullname, email, password FROM tbl_users WHERE email = ? LIMIT 1'
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

    if (!$validAccount) {
        remember_me_delete_selector($conn, $selector);
        remember_me_clear_cookie();
        return;
    }

    $newSelector = bin2hex(random_bytes(16));
    $newValidator = bin2hex(random_bytes(32));
    $newValidatorHash = hash('sha256', $newValidator);
    $rotate = $conn->prepare(
        'UPDATE tbl_remember_tokens SET selector = ?, validator_hash = ?, last_used_at = UTC_TIMESTAMP() '
        . 'WHERE selector = ? AND validator_hash = ? AND expires_at > UTC_TIMESTAMP()'
    );
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
        remember_me_clear_cookie();
        return;
    }

    $expiresAt = strtotime((string)$token['expires_at'] . ' UTC');
    if ($expiresAt === false || !setcookie(
        REMEMBER_ME_COOKIE,
        $newSelector . '.' . $newValidator,
        remember_me_cookie_options($expiresAt)
    )    ) {
        remember_me_delete_selector($conn, $newSelector);
        throw new RuntimeException('Could not refresh the remember-me cookie.');
    }
    $_COOKIE[REMEMBER_ME_COOKIE] = $newSelector . '.' . $newValidator;

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not renew the session for remember-me authentication.');
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
    }
}
