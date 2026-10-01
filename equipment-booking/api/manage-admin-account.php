<?php
/**
 * manage-admin-account.php
 *
 * Super-Admin-only actions on OTHER admin accounts (Settings → Manage Admins):
 *
 *   action=update      edit full name / email / password
 *   action=dormant     make the account unusable for 1–30 days
 *   action=reactivate  end a dormancy early
 *   action=delete      remove the account (frees a seat)
 *
 * Rules enforced here (never trusted to the UI):
 *   • POST only, valid CSRF token, logged-in Super Admin (re-checked in the DB).
 *   • You cannot act on your own account (use Settings → My Account).
 *   • Only accounts with the plain "Admin" role can be managed; Super Admin
 *     accounts (including the original main@admin.edu) are protected.
 *   • update / delete require the acting Super Admin's own password again.
 *   • Dormancy is capped at ADMIN_DORMANT_MAX_DAYS (30) days.
 *   • Credential changes and dormancy end the target's active sessions
 *     (see config/admin-auth.php → admin_session_guard()).
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Helpers ──────────────────────────────────────────────────────────────────
function respond(int $code, string $status, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message] + $extra);
    exit;
}

/** Number of admin seats in use / free (max 5, same limit as create-admin-account.php). */
function seat_counts(mysqli $conn): array
{
    $res   = $conn->query('SELECT COUNT(*) AS c FROM tbl_accounts');
    $count = (int)(($res ? $res->fetch_assoc()['c'] : 0));
    return ['accounts_count' => $count, 'accounts_remaining' => max(0, 5 - $count)];
}

/**
 * The acting Super Admin must confirm their own password for sensitive
 * actions. Wrong guesses are throttled per session (5 tries, then 5 minutes).
 */
function require_reauth(string $actor_hash): void
{
    $now = time();
    if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
        $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
        respond(429, 'error', "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.');
    }

    $given = (string)($_POST['current_password'] ?? '');
    if ($given === '') {
        respond(422, 'error', 'Enter your own password to confirm this action.');
    }
    if (!password_verify($given, $actor_hash)) {
        $_SESSION['mgmt_reauth_fails'] = (int)($_SESSION['mgmt_reauth_fails'] ?? 0) + 1;
        if ($_SESSION['mgmt_reauth_fails'] >= 5) {
            $_SESSION['mgmt_reauth_fails']      = 0;
            $_SESSION['mgmt_reauth_lock_until'] = $now + 300;
            respond(429, 'error', 'Too many incorrect passwords. Try again in 5 minutes.');
        }
        respond(403, 'error', 'Your password is incorrect.');
    }
    unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);
}

// ── Gate: method, session, CSRF ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'error', 'Method not allowed.');
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    respond(401, 'error', 'Unauthorized.');
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
admin_accounts_ensure_schema($conn);

// ── Who is acting? (fresh from the DB, not just the session) ────────────────
$actor = null;
if ($st = $conn->prepare('SELECT id, password, role FROM tbl_accounts WHERE email = ? LIMIT 1')) {
    $st->bind_param('s', $_SESSION['admin_email']);
    $st->execute();
    $r     = $st->get_result();
    $actor = ($r && $r->num_rows === 1) ? $r->fetch_assoc() : null;
    $st->close();
}
if (!$actor || $actor['role'] !== 'Super Admin') {
    respond(403, 'error', 'Only a Super Admin can manage admin accounts.');
}

// ── Which account? ───────────────────────────────────────────────────────────
$action    = (string)($_POST['action'] ?? '');
$target_id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($action, ['update', 'dormant', 'reactivate', 'delete'], true) || !$target_id || $target_id < 1) {
    respond(422, 'error', 'Invalid request.');
}

$target = null;
if ($st = $conn->prepare('SELECT id, fullName, email, role, dormant_until FROM tbl_accounts WHERE id = ? LIMIT 1')) {
    $st->bind_param('i', $target_id);
    $st->execute();
    $r      = $st->get_result();
    $target = ($r && $r->num_rows === 1) ? $r->fetch_assoc() : null;
    $st->close();
}
if (!$target) {
    respond(404, 'error', 'That account no longer exists. Refresh the page.');
}
if ((int)$target['id'] === (int)$actor['id']) {
    respond(403, 'error', 'You cannot change your own account here. Use Settings → My Account.');
}
if ($target['role'] !== 'Admin') {
    respond(403, 'error', 'Super Admin accounts are protected and cannot be edited, made dormant or deleted here.');
}

// ── Actions ──────────────────────────────────────────────────────────────────
switch ($action) {

    // ---------------------------------------------------------------- update
    case 'update':
        require_reauth($actor['password']);

        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $email     = strtolower(trim((string)($_POST['email'] ?? '')));
        $new_pw    = (string)($_POST['new_password'] ?? '');
        $confirm   = (string)($_POST['confirm_password'] ?? '');

        if ($full_name === '' || strlen($full_name) > 255) {
            respond(422, 'error', 'Full name is required (max 255 characters).');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(422, 'error', 'A valid admin email is required.');
        }
        if (!str_ends_with($email, '@admin.edu')) {
            respond(422, 'error', 'Admin emails must use the @admin.edu convention (e.g. name@admin.edu).');
        }
        if ($new_pw !== '') {
            if (strlen($new_pw) < 8) {
                respond(422, 'error', 'New password must be at least 8 characters.');
            }
            if ($new_pw !== $confirm) {
                respond(422, 'error', 'New passwords do not match.');
            }
        }

        $name_changed  = ($full_name !== (string)$target['fullName']);
        $email_changed = (strcasecmp($email, (string)$target['email']) !== 0);
        $pw_changed    = ($new_pw !== '');
        if (!$name_changed && !$email_changed && !$pw_changed) {
            respond(422, 'error', 'No changes to save.');
        }

        if ($email_changed) {
            $st = $conn->prepare('SELECT id FROM tbl_accounts WHERE email = ? AND id <> ? LIMIT 1');
            $st->bind_param('si', $email, $target_id);
            $st->execute();
            $st->store_result();
            $taken = $st->num_rows > 0;
            $st->close();
            if ($taken) {
                respond(409, 'error', 'Another admin account already uses this email.');
            }
        }

        // Build the UPDATE from only what changed. Any change to the login
        // details (email or password) also stamps session_epoch so the
        // account's existing sessions are ended.
        $sets   = [];
        $types  = '';
        $params = [];
        if ($name_changed)  { $sets[] = 'fullName = ?'; $types .= 's'; $params[] = $full_name; }
        if ($email_changed) { $sets[] = 'email = ?';    $types .= 's'; $params[] = $email; }
        if ($pw_changed)    { $sets[] = 'password = ?'; $types .= 's'; $params[] = password_hash($new_pw, PASSWORD_BCRYPT); }
        if ($email_changed || $pw_changed) {
            $sets[]   = 'session_epoch = ?';
            $types   .= 'i';
            $params[] = time();
        }
        $types   .= 'i';
        $params[] = $target_id;

        $st = $conn->prepare('UPDATE tbl_accounts SET ' . implode(', ', $sets) . " WHERE id = ? AND role = 'Admin'");
        if (!$st) {
            error_log('[manage-admin] update prepare failed: ' . $conn->error);
            respond(500, 'error', 'Could not save changes. Please try again.');
        }
        $st->bind_param($types, ...$params);
        if (!$st->execute()) {
            $dup = ($st->errno === 1062);
            error_log('[manage-admin] update failed: ' . $st->error);
            respond($dup ? 409 : 500, 'error', $dup
                ? 'Another admin account already uses this email.'
                : 'Could not save changes. Please try again.');
        }
        $st->close();

        error_log(sprintf('[manage-admin] %s updated admin #%d (name:%d email:%d password:%d)',
            $_SESSION['admin_email'], $target_id, $name_changed, $email_changed, $pw_changed));

        respond(200, 'success', 'Admin account updated.', [
            'full_name'     => $full_name,
            'email'         => $email,
            'signed_out'    => ($email_changed || $pw_changed),
        ]);

    // --------------------------------------------------------------- dormant
    case 'dormant':
        $days = filter_var($_POST['days'] ?? null, FILTER_VALIDATE_INT);
        if ($days === false || $days === null || $days < 1 || $days > ADMIN_DORMANT_MAX_DAYS) {
            respond(422, 'error', 'Choose between 1 and ' . ADMIN_DORMANT_MAX_DAYS . ' days.');
        }
        $until = time() + ($days * 86400);

        $st = $conn->prepare("UPDATE tbl_accounts SET dormant_until = ? WHERE id = ? AND role = 'Admin'");
        $st->bind_param('ii', $until, $target_id);
        if (!$st->execute()) {
            error_log('[manage-admin] dormant failed: ' . $st->error);
            respond(500, 'error', 'Could not update the account. Please try again.');
        }
        $st->close();

        error_log(sprintf('[manage-admin] %s made admin #%d dormant for %d day(s)', $_SESSION['admin_email'], $target_id, $days));

        respond(200, 'success', 'Account is now dormant for ' . $days . ' day' . ($days === 1 ? '' : 's') . '.', [
            'dormant_until' => $until,
            'dormant_label' => admin_format_time($until),
        ]);

    // ------------------------------------------------------------ reactivate
    case 'reactivate':
        $st = $conn->prepare("UPDATE tbl_accounts SET dormant_until = NULL WHERE id = ? AND role = 'Admin'");
        $st->bind_param('i', $target_id);
        if (!$st->execute()) {
            error_log('[manage-admin] reactivate failed: ' . $st->error);
            respond(500, 'error', 'Could not update the account. Please try again.');
        }
        $st->close();

        error_log(sprintf('[manage-admin] %s reactivated admin #%d', $_SESSION['admin_email'], $target_id));
        respond(200, 'success', 'Account reactivated.');

    // ---------------------------------------------------------------- delete
    case 'delete':
        require_reauth($actor['password']);

        $st = $conn->prepare("DELETE FROM tbl_accounts WHERE id = ? AND role = 'Admin' LIMIT 1");
        $st->bind_param('i', $target_id);
        if (!$st->execute() || $st->affected_rows !== 1) {
            error_log('[manage-admin] delete failed: ' . $st->error);
            respond(500, 'error', 'Could not delete the account. Please try again.');
        }
        $st->close();

        error_log(sprintf('[manage-admin] %s deleted admin #%d (%s)', $_SESSION['admin_email'], $target_id, $target['email']));
        respond(200, 'success', 'Admin account deleted.', seat_counts($conn));
}
