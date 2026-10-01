<?php

/**
 * delete-faculty-account.php
 *
 * Super-Admin-only: permanently delete a faculty account (Faculty tab ->
 * Edit -> Delete Account).
 *
 * POST fields
 *   csrf_token        required
 *   faculty_id        required
 *   dry_run=1         optional - only report what (if anything) blocks the delete
 *   override=1        optional - delete even though bookings are still open;
 *                     requires current_password (the acting Super Admin's own)
 *   current_password  required with override=1
 *
 * Rules enforced here (never trusted to the UI):
 *   - POST only, valid CSRF token, logged-in admin who is a Super Admin
 *     (re-checked in the DB, not just the session).
 *   - The account must exist.
 *   - By default the delete is BLOCKED while the faculty still has open
 *     bookings: pending requests, equipment that is not back yet, or upcoming
 *     room reservations. The 409 response lists the counts.
 *   - A Super Admin can override that block by confirming with their own
 *     password (same 5-tries-then-5-minutes throttle as Manage Admins).
 *     An override:
 *       * declines the faculty's pending (Waiting) equipment requests,
 *       * cancels their upcoming room reservations,
 *       * leaves equipment that is already out untouched, so it can still be
 *         returned by QR scan and put back into stock (inventory is only
 *         restored by that return, never by the delete).
 *   - Borrow / reservation HISTORY is kept: those tables store the faculty
 *     id and name as plain text, there is no foreign key to tbl_users.
 *   - Leftovers that only make sense for a live account are removed: unused
 *     student codes the faculty issued, notification state, room waitlist
 *     entries, and the profile picture file.
 *   - If the faculty is logged in right now, config/session.php ->
 *     faculty_session_guard() ends their session on their next request.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function respond(int $code, string $status, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message] + $extra);
    exit;
}

// Newer PHP versions make mysqli throw on SQL errors. Turn anything unexpected
// into a clean JSON error instead of an empty 500 the UI can't read.
set_exception_handler(function (Throwable $e): void {
    error_log('[delete-faculty-account] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not delete this account. Please try again.']);
});

// -- Gate: method, session, CSRF ----------------------------------------------
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
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    respond(403, 'error', 'Only a Super Admin can delete faculty accounts.');
}

$dry_run  = (($_POST['dry_run'] ?? '0') === '1');
$override = (($_POST['override'] ?? '0') === '1');

// -- Which account? -----------------------------------------------------------
$faculty_id = trim((string)($_POST['faculty_id'] ?? ''));
if ($faculty_id === '' || strlen($faculty_id) > 255) {
    respond(422, 'error', 'Faculty account not found.');
}

$stmt = $conn->prepare('SELECT fullname, profile_picture FROM tbl_users WHERE faculty_id = ? LIMIT 1');
if (!$stmt) {
    error_log('[delete-faculty-account] lookup prepare failed: ' . $conn->error);
    respond(500, 'error', 'Could not delete this account. Please try again.');
}
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$res    = $stmt->get_result();
$target = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
$stmt->close();

if (!$target) {
    respond(404, 'error', 'That account no longer exists. Refresh the page.');
}

// -- What is still open? ------------------------------------------------------
// Both tables always exist, so a failure here fails CLOSED (nothing is deleted).
//   waiting  = requests still waiting for a decision
//   out      = equipment approved/overdue and not returned yet
//   rooms    = room reservations that have not ended yet
$stmt = $conn->prepare(
    "SELECT
        SUM(status = 'Waiting') AS waiting,
        SUM(status IN ('Approved','Overdue') AND returned_at IS NULL) AS out_now
       FROM tbl_requests
      WHERE faculty_id = ?"
);
if (!$stmt) {
    error_log('[delete-faculty-account] request check prepare failed: ' . $conn->error);
    respond(500, 'error', 'Could not delete this account. Please try again.');
}
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$cnt = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$waiting_count = (int)($cnt['waiting'] ?? 0);
$out_count     = (int)($cnt['out_now'] ?? 0);

// Manila date/time from PHP (the DB server's CURDATE() may be in another timezone).
$today_ph = date('Y-m-d');
$now_ph   = date('H:i:s');

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS c FROM tbl_room_reservations
      WHERE faculty_id = ?
        AND status IN ('Waiting','Approved')
        AND (reservation_date > ?
             OR (reservation_date = ? AND end_time > ?))"
);
if (!$stmt) {
    error_log('[delete-faculty-account] room check prepare failed: ' . $conn->error);
    respond(500, 'error', 'Could not delete this account. Please try again.');
}
$stmt->bind_param('ssss', $faculty_id, $today_ph, $today_ph, $now_ph);
$stmt->execute();
$rooms_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$details = ['waiting' => $waiting_count, 'out' => $out_count, 'rooms' => $rooms_count];
$blocked = ($waiting_count + $out_count + $rooms_count) > 0;

// -- Dry run: just report -------------------------------------------------------
if ($dry_run) {
    respond(200, 'success', $blocked ? 'blocked' : 'clear', ['blocked' => $blocked, 'details' => $details]);
}

// -- Blocked unless a Super Admin overrides with their password -----------------
if ($blocked) {
    if (!$override) {
        respond(409, 'error', 'This account still has open bookings.', [
            'code'    => 'blocked',
            'details' => $details,
        ]);
    }

    // The acting Super Admin must confirm with their OWN password.
    // Wrong guesses are throttled per session (5 tries, then 5 minutes) and
    // share the counter used by Settings -> Manage Admins.
    $now = time();
    if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
        $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
        respond(429, 'error', "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.', ['code' => 'bad_password']);
    }

    $given = (string)($_POST['current_password'] ?? '');
    if ($given === '') {
        respond(422, 'error', 'Enter your password to continue.', ['code' => 'bad_password']);
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
            respond(429, 'error', 'Too many incorrect passwords. Try again in 5 minutes.', ['code' => 'bad_password']);
        }
        respond(403, 'error', 'Incorrect password.', ['code' => 'bad_password']);
    }
    unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);
}

// -- Delete (single transaction) ------------------------------------------------
// Optional clean-ups touch tables that are created lazily on some installs,
// so a missing table is logged and skipped rather than blocking the delete.
function cleanup_optional(mysqli $conn, string $sql, string $faculty_id): void
{
    try {
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('s', $faculty_id);
            $st->execute();
            $st->close();
        }
    } catch (Throwable $e) {
        error_log('[delete-faculty-account] optional cleanup skipped: ' . $e->getMessage());
    }
}

$conn->begin_transaction();
try {
    if ($blocked) {
        // Override: close out what can safely be closed.
        // 1) Pending requests can never be approved for a deleted account -> decline.
        //    (No inventory change: Waiting requests never held stock.)
        $reason = 'Faculty account removed by Super Admin';
        $st = $conn->prepare(
            "UPDATE tbl_requests
                SET status = 'Declined', reason = ?
              WHERE faculty_id = ? AND status = 'Waiting'"
        );
        $st->bind_param('ss', $reason, $faculty_id);
        $st->execute();
        $st->close();

        // 2) Upcoming room reservations -> cancelled.
        $room_sql_full = "UPDATE tbl_room_reservations
                             SET status = 'Cancelled', cancelled_at = NOW()
                           WHERE faculty_id = ?
                             AND status IN ('Waiting','Approved')
                             AND (reservation_date > ?
                                  OR (reservation_date = ? AND end_time > ?))";
        $st = false;
        try {
            $st = $conn->prepare($room_sql_full);
        } catch (Throwable $e) {
            $st = false; // mysqli in exception mode
        }
        if (!$st) {
            // Older database without the cancelled_at column - cancel without stamping it.
            $st = $conn->prepare(str_replace(', cancelled_at = NOW()', '', $room_sql_full));
        }
        $st->bind_param('ssss', $faculty_id, $today_ph, $today_ph, $now_ph);
        $st->execute();
        $st->close();

        // 3) Equipment already out is deliberately left alone (see header).
        error_log(sprintf(
            '[delete-faculty-account] OVERRIDE by %s: %s deleted with %d pending, %d still borrowed, %d upcoming room reservation(s)',
            (string)($_SESSION['admin_email'] ?? '?'),
            $faculty_id,
            $waiting_count,
            $out_count,
            $rooms_count
        ));
    }

    // Unused student codes issued by this faculty (used ones stay as history).
    cleanup_optional($conn, 'DELETE FROM tbl_faculty_codes WHERE faculty_id = ? AND is_used = 0', $faculty_id);
    cleanup_optional($conn, 'DELETE FROM tbl_faculty_notif_state WHERE faculty_id = ?', $faculty_id);
    cleanup_optional($conn, 'DELETE FROM tbl_room_waitlist WHERE faculty_id = ?', $faculty_id);

    $del = $conn->prepare('DELETE FROM tbl_users WHERE faculty_id = ?');
    if (!$del) {
        throw new RuntimeException('DELETE prepare failed: ' . $conn->error);
    }
    $del->bind_param('s', $faculty_id);
    if (!$del->execute()) {
        $err = $del->error;
        $del->close();
        throw new RuntimeException('DELETE execute failed: ' . $err);
    }
    $deleted = $del->affected_rows;
    $del->close();

    if ($deleted !== 1) {
        $conn->rollback();
        respond(404, 'error', 'That account no longer exists. Refresh the page.');
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[delete-faculty-account] ' . $e->getMessage());
    respond(500, 'error', 'Could not delete this account. Please try again.');
}

// -- Best effort: remove the profile picture file ------------------------------
// Only a bare filename inside uploads/profile_pictures is ever touched.
$pic = basename(str_replace('\\', '/', trim((string)($target['profile_picture'] ?? ''))));
if ($pic !== '' && $pic !== '.' && $pic !== '..') {
    $dir  = realpath(__DIR__ . '/../../uploads/profile_pictures');
    $path = $dir ? realpath($dir . DIRECTORY_SEPARATOR . $pic) : false;
    if ($dir && $path && strpos($path, $dir . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
        @unlink($path);
    }
}

respond(200, 'success', 'Faculty account deleted.');
