<?php

/**
 * delete-all-loans.php
 *
 * Super-Admin-only: permanently delete EVERY record shown in Equipment -> On Loan
 * -> All Loans (the "Delete All" button), after the acting Super Admin confirms
 * with their own password.
 *
 * POST fields
 *   csrf_token        required
 *   dry_run=1         optional - only count what would be deleted (no password needed,
 *                     nothing is changed). The confirmation dialog uses this to show
 *                     exact numbers before asking for the password.
 *   current_password  required for the real delete - the acting Super Admin's own
 *
 * What "the whole list" means: every request whose status is Waiting, Approved
 * (including scheduled / later-date bookings), Overdue or Returned. These are exactly
 * the four groups the All Loans table is built from. Counted over the whole table,
 * not just the 100 most recent Returned rows the screen draws, so the list really
 * ends up empty. Declined / cancelled leftovers are not part of that list and are
 * not touched.
 *
 * Rules enforced here (never trusted to the UI):
 *   - POST only, valid CSRF token, logged-in admin who is a Super Admin
 *     (re-checked in the DB, not just the session).
 *   - The password is checked against the acting admin's own row in tbl_accounts.
 *     Wrong guesses are throttled per session (5 tries, then 5 minutes) and share the
 *     counter used by Manage Admins, the faculty "delete anyway" confirmation and the
 *     faculty registry viewer, so this is no extra way to guess.
 *   - Everything happens in ONE transaction: stock is put back, then the rows are
 *     deleted. If anything fails, nothing changes.
 *   - Stock: a loan that is still out and has already taken its units off the shelf
 *     (status Approved/Overdue and stock_applied = 1 - the same rule manual-return.php
 *     uses) gets those units added back to tbl_inventory, because once the record is
 *     gone nothing could ever return them. Waiting and not-yet-started scheduled
 *     bookings never took stock; Returned loans already gave it back.
 *   - Uploaded supporting letters of the deleted requests are removed from disk, but
 *     only when no other request or room reservation still points at the same file.
 *   - The arbitration log and the admin activity history are NOT rewritten; this
 *     action adds its own line to the activity history.
 *
 * Returns JSON
 *   dry run : { status: "success", counts: { total, waiting, scheduled, out, returned,
 *               restore_units } }
 *   deleted : { status: "success", message, deleted, restored_units }
 *   failure : { status: "error", code, message }   (code "bad_password" for password problems)
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function dl_respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Newer PHP versions make mysqli throw on SQL errors. Turn anything unexpected
// into a clean JSON error instead of an empty 500 the UI can't read.
set_exception_handler(function (Throwable $e): void {
    error_log('[delete-all-loans] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'code' => 'server', 'message' => 'Could not delete the loan list. Nothing was changed. Please try again.']);
});

// -- Gate: method, session, CSRF ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dl_respond(405, ['status' => 'error', 'code' => 'method', 'message' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    dl_respond(401, ['status' => 'error', 'code' => 'unauthorized', 'message' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../../config/admin-activity.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    dl_respond(403, ['status' => 'error', 'code' => 'forbidden', 'message' => 'Only a Super Admin can delete the loan list.']);
}

// Makes sure booking_mode / stock_applied exist (idempotent, same call the return handler makes).
BookingSchedule::ensureSchema($conn);

$dry_run = (($_POST['dry_run'] ?? '0') === '1');
$now_ts  = date('Y-m-d H:i:s');

/**
 * What the All Loans table is made of, counted over the whole table.
 * $lock = true also locks those rows for the rest of the transaction.
 */
function dl_counts(mysqli $conn, string $now_ts, bool $lock): array
{
    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'Waiting'), 0)  AS waiting,
                COALESCE(SUM(status = 'Returned'), 0) AS returned,
                COALESCE(SUM(status = 'Approved'
                             AND COALESCE(booking_mode, '') = 'future'
                             AND TIMESTAMP(borrow_date, COALESCE(borrow_time, '00:00:00')) > ?), 0) AS scheduled,
                COALESCE(SUM(CASE WHEN status IN ('Approved','Overdue') AND stock_applied = 1
                                   AND EXISTS (SELECT 1 FROM tbl_inventory i WHERE i.item_name = tbl_requests.equipment_name)
                                  THEN GREATEST(borrow_qty, 1) ELSE 0 END), 0) AS restore_units
              FROM tbl_requests
             WHERE status IN ('Waiting','Approved','Overdue','Returned')"
        . ($lock ? ' FOR UPDATE' : '');
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('count prepare failed: ' . $conn->error);
    }
    $st->bind_param('s', $now_ts);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();

    $total     = (int)($row['total'] ?? 0);
    $waiting   = (int)($row['waiting'] ?? 0);
    $returned  = (int)($row['returned'] ?? 0);
    $scheduled = (int)($row['scheduled'] ?? 0);
    return [
        'total'         => $total,
        'waiting'       => $waiting,
        'scheduled'     => $scheduled,
        'out'           => max(0, $total - $waiting - $returned - $scheduled),   // Approved + Overdue, already started
        'returned'      => $returned,
        'restore_units' => (int)($row['restore_units'] ?? 0),
    ];
}

// -- Dry run: just report -------------------------------------------------------
if ($dry_run) {
    dl_respond(200, ['status' => 'success', 'counts' => dl_counts($conn, $now_ts, false)]);
}

// -- Re-enter password (own password, throttled) --------------------------------
$now = time();
if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
    $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
    dl_respond(429, [
        'status'  => 'error',
        'code'    => 'bad_password',
        'message' => "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.',
    ]);
}

$given = (string)($_POST['current_password'] ?? '');
if ($given === '') {
    dl_respond(422, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Enter your password to continue.']);
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
        dl_respond(429, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Too many incorrect passwords. Try again in 5 minutes.']);
    }
    dl_respond(403, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Incorrect password.']);
}
unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);

// -- Delete (single transaction) --------------------------------------------------
$deleted      = 0;
$restore_done = 0;
$docs         = [];

$conn->begin_transaction();
try {
    $counts = dl_counts($conn, $now_ts, true);   // also locks the rows we are about to remove

    if ($counts['total'] === 0) {
        $conn->rollback();
        dl_respond(200, ['status' => 'success', 'message' => 'The loan list is already empty.', 'deleted' => 0, 'restored_units' => 0]);
    }

    // 1) Put back the units of loans that are still out and already took stock.
    $res = $conn->query(
        "SELECT equipment_name, SUM(GREATEST(borrow_qty, 1)) AS qty
           FROM tbl_requests
          WHERE status IN ('Approved','Overdue') AND stock_applied = 1
          GROUP BY equipment_name"
    );
    $restore = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $restore[(string)$row['equipment_name']] = (int)$row['qty'];
        }
    }
    foreach ($restore as $item_name => $qty) {
        if ($qty < 1) {
            continue;
        }
        $up = $conn->prepare('UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ? LIMIT 1');
        if (!$up) {
            throw new RuntimeException('stock prepare failed: ' . $conn->error);
        }
        $up->bind_param('is', $qty, $item_name);
        $up->execute();
        if ($up->affected_rows === 1) {
            $restore_done += $qty;
        } else {
            // The catalog item was renamed or removed: there is no shelf to put units back on.
            error_log('[delete-all-loans] no inventory row for "' . $item_name . '" - ' . $qty . ' unit(s) not restored');
        }
        $up->close();
    }

    // 2) Remember the uploaded letters so they can be cleaned up after the commit.
    $res = $conn->query(
        "SELECT DISTINCT document_path FROM tbl_requests
          WHERE status IN ('Waiting','Approved','Overdue','Returned')
            AND document_path IS NOT NULL AND document_path <> ''"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $docs[] = (string)$row['document_path'];
        }
    }

    // 3) Delete the records.
    $del = $conn->prepare("DELETE FROM tbl_requests WHERE status IN ('Waiting','Approved','Overdue','Returned')");
    if (!$del) {
        throw new RuntimeException('delete prepare failed: ' . $conn->error);
    }
    $del->execute();
    $deleted = (int)$del->affected_rows;
    $del->close();

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('[delete-all-loans] ' . $e->getMessage());
    dl_respond(500, ['status' => 'error', 'code' => 'server', 'message' => 'Could not delete the loan list. Nothing was changed. Please try again.']);
}

// -- Best effort: remove the uploaded letters nobody points at any more ---------------
// Only a path inside <site>/uploads is ever touched, and only if no request or room
// reservation still references it. If a check cannot be made, the file is kept.
function dl_discard_upload(mysqli $conn, string $rel): void
{
    if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, 'uploads/') !== 0) {
        return;
    }
    foreach (['tbl_requests', 'tbl_room_reservations'] as $tbl) {
        try {
            $c = $conn->prepare("SELECT COUNT(*) AS n FROM {$tbl} WHERE document_path = ?");
            if (!$c) {
                return;
            }
            $c->bind_param('s', $rel);
            $c->execute();
            $n = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
            $c->close();
            if ($n > 0) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }
    }
    $root    = realpath(__DIR__ . '/../..');
    $uploads = $root ? realpath($root . '/uploads') : false;
    $abs     = $root ? realpath($root . '/' . $rel) : false;
    if ($uploads && $abs && strpos($abs, $uploads . DIRECTORY_SEPARATOR) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}
foreach ($docs as $rel) {
    dl_discard_upload($conn, $rel);
}

error_log(sprintf(
    '[delete-all-loans] %s deleted the whole loan list: %d record(s) (%d waiting, %d scheduled, %d out, %d returned); %d unit(s) put back in stock',
    (string)($_SESSION['admin_email'] ?? '?'),
    $deleted,
    $counts['waiting'],
    $counts['scheduled'],
    $counts['out'],
    $counts['returned'],
    $restore_done
));

admin_log_activity(
    $conn,
    'request',
    'Deleted the entire On Loan list (' . $deleted . ' record' . ($deleted === 1 ? '' : 's') . ')'
);

dl_respond(200, [
    'status'         => 'success',
    'message'        => 'The On Loan list was cleared. ' . $deleted . ' record' . ($deleted === 1 ? '' : 's') . ' deleted.',
    'deleted'        => $deleted,
    'restored_units' => $restore_done,
]);
