<?php
/**
 * delete-faculty-account.php
 *
 * Super-Admin-only: permanently delete a faculty account (Faculty tab →
 * Edit → Delete Account).
 *
 * Rules enforced here (never trusted to the UI):
 *   • POST only, valid CSRF token, logged-in admin who is a Super Admin
 *     (re-checked in the DB, not just the session).
 *   • The account must exist.
 *   • Blocked while the faculty still has equipment that is not back yet
 *     (status Waiting / Approved / Overdue and not returned) or an upcoming
 *     room reservation - the admin has to resolve those first so nothing is
 *     left borrowed by an account that no longer exists.
 *   • Borrow / reservation HISTORY is kept: those tables store the faculty
 *     id and name as plain text, there is no foreign key to tbl_users.
 *   • Leftovers that only make sense for a live account are removed: unused
 *     student codes the faculty issued, notification state, room waitlist
 *     entries, and the profile picture file.
 *   • If the faculty is logged in right now, config/session.php →
 *     faculty_session_guard() ends their session on their next request.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function respond(int $code, string $status, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
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
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    respond(403, 'error', 'Only a Super Admin can delete faculty accounts.');
}

// ── Which account? ───────────────────────────────────────────────────────────
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

// ── Safety check: unresolved equipment / upcoming rooms ──────────────────────
// Both tables always exist, so a failure here fails CLOSED (nothing is deleted).
$open_requests = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS c FROM tbl_requests
      WHERE faculty_id = ?
        AND status IN ('Waiting','Approved','Overdue')
        AND returned_at IS NULL"
);
if (!$stmt) {
    error_log('[delete-faculty-account] request check prepare failed: ' . $conn->error);
    respond(500, 'error', 'Could not delete this account. Please try again.');
}
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$open_requests = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$upcoming_rooms = 0;
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
// Manila date/time from PHP (the DB server's CURDATE() may be in another timezone).
$today_ph = date('Y-m-d');
$now_ph   = date('H:i:s');
$stmt->bind_param('ssss', $faculty_id, $today_ph, $today_ph, $now_ph);
$stmt->execute();
$upcoming_rooms = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

if ($open_requests > 0 || $upcoming_rooms > 0) {
    $parts = [];
    if ($open_requests > 0) {
        $parts[] = $open_requests . ' pending or unreturned equipment request' . ($open_requests === 1 ? '' : 's');
    }
    if ($upcoming_rooms > 0) {
        $parts[] = $upcoming_rooms . ' upcoming room reservation' . ($upcoming_rooms === 1 ? '' : 's');
    }
    respond(
        409,
        'error',
        'This account still has ' . implode(' and ', $parts)
            . '. Resolve or cancel them first, then try deleting again.'
    );
}

// ── Delete (single transaction) ──────────────────────────────────────────────
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

// ── Best effort: remove the profile picture file ────────────────────────────
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
