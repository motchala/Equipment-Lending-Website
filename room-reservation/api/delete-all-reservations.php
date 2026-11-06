<?php
/**
 * delete-all-reservations.php
 *
 * Super-Admin-only: permanently delete every record in tbl_room_reservations,
 * after the acting Super Admin confirms with their own password.
 *
 * POST fields
 *   csrf_token        required
 *   dry_run=1         optional – count only, no changes
 *   current_password  required for the real delete
 *
 * Returns JSON
 *   dry run : { status: "success", counts: { total, waiting, approved, cancelled, declined } }
 *   deleted : { status: "success", message, deleted }
 *   failure : { status: "error", code, message }
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function rr_respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

set_exception_handler(function (Throwable $e): void {
    error_log('[delete-all-reservations] uncaught: ' . $e->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['status' => 'error', 'code' => 'server', 'message' => 'Could not delete reservations. Nothing was changed. Please try again.']);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rr_respond(405, ['status' => 'error', 'code' => 'method', 'message' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    rr_respond(401, ['status' => 'error', 'code' => 'unauthorized', 'message' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    rr_respond(403, ['status' => 'error', 'code' => 'forbidden', 'message' => 'Only a Super Admin can delete all reservations.']);
}

function rr_counts(mysqli $conn): array
{
    $res = $conn->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'Waiting'),   0) AS waiting,
            COALESCE(SUM(status = 'Approved'),  0) AS approved,
            COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled,
            COALESCE(SUM(status = 'Declined'),  0) AS declined
         FROM tbl_room_reservations"
    );
    $row = $res ? $res->fetch_assoc() : [];
    return [
        'total'     => (int)($row['total']     ?? 0),
        'waiting'   => (int)($row['waiting']   ?? 0),
        'approved'  => (int)($row['approved']  ?? 0),
        'cancelled' => (int)($row['cancelled'] ?? 0),
        'declined'  => (int)($row['declined']  ?? 0),
    ];
}

$dry_run = (($_POST['dry_run'] ?? '0') === '1');

if ($dry_run) {
    rr_respond(200, ['status' => 'success', 'counts' => rr_counts($conn)]);
}

// Password check (shared throttle with other sensitive actions)
$now = time();
if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
    $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
    rr_respond(429, ['status' => 'error', 'code' => 'bad_password',
        'message' => "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.',
    ]);
}

$given = (string)($_POST['current_password'] ?? '');
if ($given === '') {
    rr_respond(422, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Enter your password to continue.']);
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
        rr_respond(429, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Too many incorrect passwords. Try again in 5 minutes.']);
    }
    rr_respond(403, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Incorrect password.']);
}
unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);

// Delete
$deleted = 0;
$conn->begin_transaction();
try {
    $counts = rr_counts($conn);
    if ($counts['total'] === 0) {
        $conn->rollback();
        rr_respond(200, ['status' => 'success', 'message' => 'The reservations list is already empty.', 'deleted' => 0]);
    }

    $del = $conn->prepare('DELETE FROM tbl_room_reservations');
    if (!$del) throw new RuntimeException('delete prepare failed: ' . $conn->error);
    $del->execute();
    $deleted = (int)$del->affected_rows;
    $del->close();

    $conn->commit();
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('[delete-all-reservations] ' . $e->getMessage());
    rr_respond(500, ['status' => 'error', 'code' => 'server', 'message' => 'Could not delete reservations. Nothing was changed. Please try again.']);
}

error_log(sprintf(
    '[delete-all-reservations] %s deleted all reservations: %d record(s)',
    (string)($_SESSION['admin_email'] ?? '?'),
    $deleted
));

admin_log_activity(
    $conn,
    'reservation',
    'Deleted all reservations (' . $deleted . ' record' . ($deleted === 1 ? '' : 's') . ')'
);

rr_respond(200, [
    'status'  => 'success',
    'message' => 'All reservations deleted. ' . $deleted . ' record' . ($deleted === 1 ? '' : 's') . ' removed.',
    'deleted' => $deleted,
]);
