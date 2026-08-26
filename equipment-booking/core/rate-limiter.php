<?php
/**
 * rate-limiter.php
 *
 * Login rate-limiting and lockout enforcement for PUPSync.
 * Tracks failed login attempts per (email + IP) pair in tbl_login_attempts.
 *
 * Public API — three functions called from landing-page.php:
 *
 *   checkLoginAllowed(string $email, string $ip, mysqli $conn): array
 *     → Call BEFORE password verification. Returns whether the attempt is
 *       currently allowed and how many tries remain.
 *
 *   recordFailedAttempt(string $email, string $ip, bool $email_exists,
 *                       string $display_name, mysqli $conn): array
 *     → Call AFTER a confirmed credential failure. Increments the counter,
 *       triggers lockout + alert email at the threshold, returns the
 *       $login_error string to display.
 *
 *   recordSuccessfulLogin(string $email, string $ip, mysqli $conn): void
 *     → Call AFTER session is set, BEFORE header() redirect. Resets all
 *       counters and the lockout_level for this (email, IP) pair.
 *
 * Lockout escalation:
 *   After 3 failures  → lockout triggered; attempt_count resets to 0;
 *                        lockout_level increments (persists until success).
 *   Level 1 → 5 min  |  Level 2 → 15 min  |  Level 3+ → 60 min
 *
 * Passive decay (lazy — no cron, safe for InfinityFree shared hosting):
 *   If login_ok = 0 AND last_attempt is older than DECAY_WINDOW_HOURS with
 *   no intervening successful login, lockout_level steps down by 1 on the
 *   next attempt check.
 *
 * Compliance:
 *   Satisfies the risk-based technical access-control obligation under
 *   NPC Circular 2023-06, informed by ISO/IEC 27002:2022 §8.5.
 */

// ── Constants ─────────────────────────────────────────────────────────────────

/** Failed attempts allowed before a lockout is triggered. */
define('RL_MAX_ATTEMPTS', 3);

/**
 * Hours of complete dormancy after which lockout_level steps down by 1.
 * No attempts and no successful login must have occurred in this window.
 * Change this single value to adjust the decay window without touching logic.
 */
define('DECAY_WINDOW_HOURS', 24);

/** Lockout durations in minutes, keyed by lockout_level. Level 3+ uses key 3. */
define('RL_LOCKOUT_MINUTES', [
    1 => 5,
    2 => 15,
    3 => 60,
]);

// ── IP-wide throttle constants ────────────────────────────────────────────────

define('RL_IP_MAX_FAILS',       15);
define('RL_IP_WINDOW_MINUTES',  10);
define('RL_IP_LOCKOUT_MINUTES', 10);

// ── Dependency ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/mailer.php';


// ── Public functions ──────────────────────────────────────────────────────────

function checkLoginAllowed(string $email, string $ip, mysqli $conn): array
{
    $row = _rl_fetch_row($email, $ip, $conn);

    if ($row === null) {
        return _rl_allowed(RL_MAX_ATTEMPTS);
    }

    if ((int)$row['login_ok'] === 0 && (int)$row['lockout_level'] > 0) {
        $last_ts        = strtotime($row['last_attempt']);
        $decay_cutoff   = time() - (DECAY_WINDOW_HOURS * 3600);

        if ($last_ts !== false && $last_ts < $decay_cutoff) {
            $new_level = max(0, (int)$row['lockout_level'] - 1);
            $stmt = $conn->prepare(
                "UPDATE tbl_login_attempts
                    SET lockout_level = ?,
                        locked_until  = NULL
                  WHERE identifier = ? AND ip_address = ?"
            );
            if ($stmt) {
                $stmt->bind_param('iss', $new_level, $email, $ip);
                $stmt->execute();
                $stmt->close();
            }
            $row = _rl_fetch_row($email, $ip, $conn);
            if ($row === null) {
                return _rl_allowed(RL_MAX_ATTEMPTS);
            }
        }
    }

    if (!empty($row['locked_until'])) {
        $unlock_ts = strtotime($row['locked_until']);
        $now_ts    = time();

        if ($unlock_ts !== false && $unlock_ts > $now_ts) {
            return [
                'allowed'      => false,
                'attempts_left' => 0,
                'locked_until' => $row['locked_until'],
                'seconds_left' => $unlock_ts - $now_ts,
            ];
        }

        $stmt = $conn->prepare(
            "UPDATE tbl_login_attempts
                SET locked_until = NULL
              WHERE identifier = ? AND ip_address = ?"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $email, $ip);
            $stmt->execute();
            $stmt->close();
        }
        $row['locked_until'] = null;
    }

    $attempts_left = max(0, RL_MAX_ATTEMPTS - (int)$row['attempt_count']);
    return _rl_allowed($attempts_left);
}

function recordFailedAttempt(
    string $email,
    string $ip,
    bool   $email_exists,
    string $display_name,
    mysqli $conn
): array {
    $stmt = $conn->prepare(
        "INSERT INTO tbl_login_attempts
             (identifier, ip_address, attempt_count, lockout_level, locked_until, last_attempt, login_ok)
         VALUES (?, ?, 1, 0, NULL, NOW(), 0)
         ON DUPLICATE KEY UPDATE
             attempt_count = attempt_count + 1,
             last_attempt  = NOW(),
             login_ok      = 0"
    );

    if (!$stmt) {
        error_log('[RateLimiter] UPSERT prepare failed: ' . $conn->error);
        return _rl_failed_result(
            $email_exists ? 'Wrong password.' : 'Account not found. Please register first.',
            false, '', 0
        );
    }
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $stmt->close();

    $row = _rl_fetch_row($email, $ip, $conn);
    if ($row === null) {
        return _rl_failed_result(
            $email_exists ? 'Wrong password.' : 'Account not found. Please register first.',
            false, '', 0
        );
    }

    $attempt_count = (int)$row['attempt_count'];
    $lockout_level = (int)$row['lockout_level'];

    if ($attempt_count >= RL_MAX_ATTEMPTS) {
        $new_level    = min($lockout_level + 1, 3);
        $duration_min = RL_LOCKOUT_MINUTES[$new_level] ?? 60;

        $stmt2 = $conn->prepare(
            "UPDATE tbl_login_attempts
                SET lockout_level = ?,
                    locked_until  = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    attempt_count = 0,
                    login_ok      = 0
              WHERE identifier = ? AND ip_address = ?"
        );
        if ($stmt2) {
            $stmt2->bind_param('iiss', $new_level, $duration_min, $email, $ip);
            $stmt2->execute();
            $stmt2->close();
        }

        $row2         = _rl_fetch_row($email, $ip, $conn);
        $locked_until = $row2['locked_until'] ?? '';
        $unlock_ts    = strtotime((string)$locked_until);
        $seconds_left = ($unlock_ts !== false) ? max(0, $unlock_ts - time()) : ($duration_min * 60);

        if ($email_exists) {
            _rl_send_lockout_alert($email, $display_name, $ip, $duration_min, $new_level);
        }

        $plural = $duration_min === 1 ? 'minute' : 'minutes';
        return _rl_failed_result(
            "Too many failed attempts. Try again in {$duration_min} {$plural}.",
            true, $locked_until, $seconds_left
        );
    }

    $attempts_left = max(0, RL_MAX_ATTEMPTS - $attempt_count);
    $plural        = $attempts_left === 1 ? 'attempt' : 'attempts';
    $message       = $email_exists
        ? "Wrong password. You have {$attempts_left} {$plural} left."
        : 'Account not found. Please register first.';

    return _rl_failed_result($message, false, '', 0);
}

function checkIpAllowed(string $ip, mysqli $conn): array
{
    $row = _rl_fetch_ip_row($ip, $conn);

    if ($row === null) {
        return _rl_allowed(RL_IP_MAX_FAILS);
    }

    if (!empty($row['locked_until'])) {
        $unlock_ts = strtotime($row['locked_until']);
        $now_ts    = time();

        if ($unlock_ts !== false && $unlock_ts > $now_ts) {
            return [
                'allowed'       => false,
                'attempts_left' => 0,
                'locked_until'  => $row['locked_until'],
                'seconds_left'  => $unlock_ts - $now_ts,
            ];
        }

        $stmt = $conn->prepare(
            "UPDATE tbl_ip_login_attempts
                SET locked_until = NULL
              WHERE ip_address = ?"
        );
        if ($stmt) {
            $stmt->bind_param('s', $ip);
            $stmt->execute();
            $stmt->close();
        }
        $row['locked_until'] = null;
    }

    $window_start_ts = strtotime($row['window_start']);
    $window_cutoff   = time() - (RL_IP_WINDOW_MINUTES * 60);

    if ($window_start_ts !== false && $window_start_ts < $window_cutoff) {
        $stmt = $conn->prepare(
            "UPDATE tbl_ip_login_attempts
                SET fail_count    = 0,
                    window_start  = NOW(),
                    locked_until  = NULL
              WHERE ip_address = ?"
        );
        if ($stmt) {
            $stmt->bind_param('s', $ip);
            $stmt->execute();
            $stmt->close();
        }
        return _rl_allowed(RL_IP_MAX_FAILS);
    }

    $attempts_left = max(0, RL_IP_MAX_FAILS - (int)$row['fail_count']);
    return _rl_allowed($attempts_left);
}

function recordIpFailedAttempt(string $ip, mysqli $conn): array
{
    $stmt = $conn->prepare(
        "INSERT INTO tbl_ip_login_attempts
             (ip_address, fail_count, window_start, locked_until)
         VALUES (?, 1, NOW(), NULL)
         ON DUPLICATE KEY UPDATE
             fail_count   = IF(
                                window_start < DATE_SUB(NOW(), INTERVAL ? MINUTE),
                                1,
                                fail_count + 1
                            ),
             window_start = IF(
                                window_start < DATE_SUB(NOW(), INTERVAL ? MINUTE),
                                NOW(),
                                window_start
                            ),
             locked_until = IF(
                                window_start < DATE_SUB(NOW(), INTERVAL ? MINUTE),
                                NULL,
                                locked_until
                            )"
    );

    if (!$stmt) {
        error_log('[RateLimiter/IP] UPSERT prepare failed: ' . $conn->error);
        return _rl_failed_result('Account not found. Please register first.', false, '', 0);
    }

    $w = RL_IP_WINDOW_MINUTES;
    $stmt->bind_param('siii', $ip, $w, $w, $w);
    $stmt->execute();
    $stmt->close();

    $row = _rl_fetch_ip_row($ip, $conn);
    if ($row === null) {
        return _rl_failed_result('Account not found. Please register first.', false, '', 0);
    }

    $fail_count = (int)$row['fail_count'];

    if ($fail_count >= RL_IP_MAX_FAILS) {
        $stmt2 = $conn->prepare(
            "UPDATE tbl_ip_login_attempts
                SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
              WHERE ip_address = ?"
        );
        if ($stmt2) {
            $duration = RL_IP_LOCKOUT_MINUTES;
            $stmt2->bind_param('is', $duration, $ip);
            $stmt2->execute();
            $stmt2->close();
        }

        $row2         = _rl_fetch_ip_row($ip, $conn);
        $locked_until = $row2['locked_until'] ?? '';
        $unlock_ts    = strtotime((string)$locked_until);
        $seconds_left = ($unlock_ts !== false)
            ? max(0, $unlock_ts - time())
            : (RL_IP_LOCKOUT_MINUTES * 60);

        return _rl_failed_result(
            "Too many failed attempts from this location. Try again in " . RL_IP_LOCKOUT_MINUTES . " minutes.",
            true,
            $locked_until,
            $seconds_left
        );
    }

    return _rl_failed_result('Account not found. Please register first.', false, '', 0);
}


function recordSuccessfulLogin(string $email, string $ip, mysqli $conn): void
{
    $stmt = $conn->prepare(
        "UPDATE tbl_login_attempts
            SET login_ok      = 1,
                lockout_level = 0,
                attempt_count = 0,
                locked_until  = NULL
          WHERE identifier = ? AND ip_address = ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $email, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

function rl_get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip    = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}


// ── Private helpers ───────────────────────────────────────────────────────────

function _rl_fetch_row(string $email, string $ip, mysqli $conn): ?array
{
    $stmt = $conn->prepare(
        "SELECT identifier, ip_address, attempt_count, lockout_level,
                locked_until, last_attempt, login_ok
           FROM tbl_login_attempts
          WHERE identifier = ? AND ip_address = ?
          LIMIT 1"
    );
    if (!$stmt) {
        error_log('[RateLimiter] SELECT prepare failed: ' . $conn->error);
        return null;
    }
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function _rl_fetch_ip_row(string $ip, mysqli $conn): ?array
{
    $stmt = $conn->prepare(
        "SELECT ip_address, fail_count, window_start, locked_until
           FROM tbl_ip_login_attempts
          WHERE ip_address = ?
          LIMIT 1"
    );
    if (!$stmt) {
        error_log('[RateLimiter/IP] SELECT prepare failed: ' . $conn->error);
        return null;
    }
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function _rl_allowed(int $attempts_left): array
{
    return [
        'allowed'      => true,
        'attempts_left' => $attempts_left,
        'locked_until' => '',
        'seconds_left' => 0,
    ];
}

function _rl_failed_result(string $message, bool $locked, string $locked_until, int $seconds_left): array
{
    return [
        'message'      => $message,
        'locked'       => $locked,
        'locked_until' => $locked_until,
        'seconds_left' => $seconds_left,
    ];
}

function _rl_send_lockout_alert(
    string $email,
    string $display_name,
    string $ip,
    int    $duration_min,
    int    $lockout_level
): void {
    date_default_timezone_set('Asia/Manila');
    $timestamp    = date('F j, Y \a\t g:i A T');
    $greeting     = !empty($display_name) ? "Hi {$display_name}," : 'Hello,';
    $plural       = $duration_min === 1 ? 'minute' : 'minutes';
    $lockout_note = "Your account has been temporarily locked for {$duration_min} {$plural}.";

    if ($lockout_level >= 3) {
        $escalation_note = 'This is a repeated lockout event. If you did not make these attempts, please contact your system administrator immediately.';
    } else {
        $escalation_note = 'If you did not make these attempts, please change your password when access is restored or contact your system administrator.';
    }

    $html = "
    <div style=\"font-family:'Outfit',Arial,sans-serif;max-width:520px;margin:0 auto;
                background:#fff;border:1px solid #e5e5e5;border-radius:8px;overflow:hidden;\">
      <div style=\"background:#6d1b23;padding:24px 32px;\">
        <h1 style=\"color:#fff;font-size:18px;margin:0;letter-spacing:0.3px;\">
          PUPSync Security Alert
        </h1>
      </div>
      <div style=\"padding:28px 32px;color:#333;font-size:14px;line-height:1.6;\">
        <p style=\"margin:0 0 16px;\">{$greeting}</p>
        <p style=\"margin:0 0 16px;\">
          We detected <strong>" . RL_MAX_ATTEMPTS . " consecutive failed login attempts</strong>
          on your PUPSync account.
        </p>
        <table style=\"width:100%;border-collapse:collapse;margin:0 0 16px;font-size:13px;\">
          <tr>
            <td style=\"padding:8px 12px;background:#f8f8f8;border:1px solid #eee;
                        font-weight:600;width:40%;\">Time</td>
            <td style=\"padding:8px 12px;border:1px solid #eee;\">{$timestamp}</td>
          </tr>
          <tr>
            <td style=\"padding:8px 12px;background:#f8f8f8;border:1px solid #eee;
                        font-weight:600;\">IP Address</td>
            <td style=\"padding:8px 12px;border:1px solid #eee;\">{$ip}</td>
          </tr>
        </table>
        <p style=\"margin:0 0 16px;\">{$lockout_note}</p>
        <p style=\"margin:0 0 24px;\">{$escalation_note}</p>
        <hr style=\"border:none;border-top:1px solid #eee;margin:0 0 16px;\">
        <p style=\"margin:0;font-size:12px;color:#888;\">
          This is an automated security notification from PUPSync &mdash;
          Polytechnic University of the Philippines Bi&ntilde;an Campus.
          Do not reply to this email.
        </p>
      </div>
    </div>";

    // ── Async send via shutdown function ───────────────────────────────────
    // Capture all variables needed by the mailer into the closure so they
    // are available after the HTTP response has been flushed to the browser.
    $to      = $email;
    $name    = !empty($display_name) ? $display_name : 'PUPSync User';
    $subject = 'PUPSync Security Alert — Unusual Login Activity';
    $body    = $html;

    // Tell PHP not to abort execution if the client disconnects — the shutdown
    // function must complete even after the browser has received its redirect.
    ignore_user_abort(true);

    register_shutdown_function(function () use ($to, $name, $subject, $body) {
        // Flush the HTTP response to the browser before starting the SMTP
        // connection. This is what actually decouples the email send from the
        // page response time.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        // SMTP send happens here, after the browser has its response.
        sendPupSyncEmail($to, $name, $subject, $body);
    });
}