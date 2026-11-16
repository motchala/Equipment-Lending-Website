<?php

/**
 * clear-history.php
 *
 * Super-Admin-only: permanently clear the equipment loan history and/or the room reservation
 * history in one sweep (Settings -> Data -> "Clear history"), after the acting Super Admin
 * confirms with their own password.
 *
 * POST fields
 *   csrf_token        required
 *   scope             required - "equipment" | "rooms" | "all"
 *   dry_run=1         optional - only count what would be deleted (no password needed, nothing is
 *                     changed). The confirmation dialog uses this to show exact numbers first.
 *   current_password  required for the real delete - the acting Super Admin's own
 *
 * What each scope removes
 *   equipment : EVERY row of tbl_requests (pending, scheduled, on loan, overdue, returned, declined,
 *               cancelled). Units of loans that are still out and already took stock are put back
 *               in tbl_inventory first (same rule delete-all-loans.php / manual-return.php use:
 *               status Approved/Overdue and stock_applied = 1), because once the record is gone
 *               nothing could ever return them.
 *   rooms     : EVERY row of tbl_room_reservations (upcoming, past, declined, cancelled).
 *   all       : both, in the same transaction.
 *
 * Never touched: the catalog, rooms and buildings, accounts, class schedules, room issue reports,
 * the waitlist, the arbitration logs and the admin activity history (this action only adds its own
 * line to the activity history).
 *
 * Rules enforced here (never trusted to the UI):
 *   - POST only, valid CSRF token, logged-in admin who is a Super Admin (re-checked in the DB).
 *   - The password is checked against the acting admin's own row in tbl_accounts. Wrong guesses are
 *     throttled per session (5 tries, then 5 minutes) and share the counter used by Manage Admins,
 *     the faculty "delete anyway" confirmation, the faculty registry viewer and delete-all-loans.
 *   - Everything happens in ONE transaction: stock is put back, then the rows are deleted. If
 *     anything fails, nothing changes.
 *   - Uploaded supporting letters of the deleted records are removed from disk, but only when no
 *     other request or room reservation still points at the same file.
 *
 * Returns JSON
 *   dry run : { status: "success", scope, counts: { equipment?: {...}, rooms?: {...} } }
 *   deleted : { status: "success", message, scope, deleted: { equipment, rooms }, restored_units }
 *   failure : { status: "error", code, message }   (code "bad_password" for password problems)
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function ch_respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Newer PHP versions make mysqli throw on SQL errors. Turn anything unexpected into a clean JSON
// error instead of an empty 500 the UI can't read.
set_exception_handler(function (Throwable $e): void {
    error_log('[clear-history] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'code' => 'server', 'message' => 'Could not clear the history. Nothing was changed. Please try again.']);
});

// -- Gate: method, session, CSRF ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ch_respond(405, ['status' => 'error', 'code' => 'method', 'message' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    ch_respond(401, ['status' => 'error', 'code' => 'unauthorized', 'message' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../equipment-booking/core/booking-schedule.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    ch_respond(403, ['status' => 'error', 'code' => 'forbidden', 'message' => 'Only a Super Admin can clear the history.']);
}

// -- Scope -------------------------------------------------------------------------
$scope = (string)($_POST['scope'] ?? '');
if (!in_array($scope, ['equipment', 'rooms', 'all'], true)) {
    ch_respond(422, ['status' => 'error', 'code' => 'scope', 'message' => 'Choose what to clear first.']);
}
$do_equipment = ($scope === 'equipment' || $scope === 'all');
$do_rooms     = ($scope === 'rooms' || $scope === 'all');

// Makes sure booking_mode / stock_applied exist (idempotent, same call the return handler makes).
BookingSchedule::ensureSchema($conn);

$dry_run = (($_POST['dry_run'] ?? '0') === '1');
$now_ts  = date('Y-m-d H:i:s');

/**
 * Equipment: what tbl_requests holds, counted over the whole table.
 * $lock = true also locks those rows for the rest of the transaction.
 */
function ch_equipment_counts(mysqli $conn, string $now_ts, bool $lock): array
{
    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'Waiting'), 0)  AS waiting,
                COALESCE(SUM(status = 'Returned'), 0) AS returned,
                COALESCE(SUM(status IN ('Approved','Overdue')), 0) AS approved_overdue,
                COALESCE(SUM(status = 'Approved'
                             AND COALESCE(booking_mode, '') = 'future'
                             AND TIMESTAMP(borrow_date, COALESCE(borrow_time, '00:00:00')) > ?), 0) AS scheduled,
                COALESCE(SUM(CASE WHEN status IN ('Approved','Overdue') AND stock_applied = 1
                                   AND EXISTS (SELECT 1 FROM tbl_inventory i WHERE i.item_name = tbl_requests.equipment_name)
                                  THEN GREATEST(borrow_qty, 1) ELSE 0 END), 0) AS restore_units
              FROM tbl_requests"
        . ($lock ? ' FOR UPDATE' : '');
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('equipment count prepare failed: ' . $conn->error);
    }
    $st->bind_param('s', $now_ts);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();

    $total     = (int)($row['total'] ?? 0);
    $waiting   = (int)($row['waiting'] ?? 0);
    $returned  = (int)($row['returned'] ?? 0);
    $approved  = (int)($row['approved_overdue'] ?? 0);
    $scheduled = (int)($row['scheduled'] ?? 0);
    return [
        'total'         => $total,
        'waiting'       => $waiting,
        'scheduled'     => $scheduled,
        'out'           => max(0, $approved - $scheduled),                 // Approved + Overdue that already started
        'returned'      => $returned,
        'other'         => max(0, $total - $waiting - $returned - $approved), // Declined / Cancelled / anything else
        'restore_units' => (int)($row['restore_units'] ?? 0),
    ];
}

/** Rooms: what tbl_room_reservations holds. */
function ch_room_counts(mysqli $conn, string $now_ts, bool $lock): array
{
    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'Approved' AND TIMESTAMP(reservation_date, end_time) >  ?), 0) AS upcoming,
                COALESCE(SUM(status = 'Approved' AND TIMESTAMP(reservation_date, end_time) <= ?), 0) AS completed,
                COALESCE(SUM(status = 'Declined'), 0)  AS declined,
                COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled
              FROM tbl_room_reservations"
        . ($lock ? ' FOR UPDATE' : '');
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('room count prepare failed: ' . $conn->error);
    }
    $st->bind_param('ss', $now_ts, $now_ts);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();

    return [
        'total'     => (int)($row['total'] ?? 0),
        'upcoming'  => (int)($row['upcoming'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
        'declined'  => (int)($row['declined'] ?? 0),
        'cancelled' => (int)($row['cancelled'] ?? 0),
    ];
}

// -- Dry run: just report -------------------------------------------------------
if ($dry_run) {
    $counts = [];
    if ($do_equipment) {
        $counts['equipment'] = ch_equipment_counts($conn, $now_ts, false);
    }
    if ($do_rooms) {
        $counts['rooms'] = ch_room_counts($conn, $now_ts, false);
    }
    ch_respond(200, ['status' => 'success', 'scope' => $scope, 'counts' => $counts]);
}

// -- Re-enter password (own password, throttled) --------------------------------
$now = time();
if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
    $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
    ch_respond(429, [
        'status'  => 'error',
        'code'    => 'bad_password',
        'message' => "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.',
    ]);
}

$given = (string)($_POST['current_password'] ?? '');
if ($given === '') {
    ch_respond(422, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Enter your password to continue.']);
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
        ch_respond(429, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Too many incorrect passwords. Try again in 5 minutes.']);
    }
    ch_respond(403, ['status' => 'error', 'code' => 'bad_password', 'message' => 'Incorrect password.']);
}
unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);

// -- Delete (single transaction) --------------------------------------------------
$deleted_equipment = 0;
$deleted_rooms     = 0;
$restore_done      = 0;
$docs              = [];
$eq_counts         = null;
$rm_counts         = null;

$conn->begin_transaction();
try {
    // Counting also locks the rows we are about to remove.
    if ($do_equipment) {
        $eq_counts = ch_equipment_counts($conn, $now_ts, true);
    }
    if ($do_rooms) {
        $rm_counts = ch_room_counts($conn, $now_ts, true);
    }

    $eq_total = $eq_counts['total'] ?? 0;
    $rm_total = $rm_counts['total'] ?? 0;
    if ($eq_total + $rm_total === 0) {
        $conn->rollback();
        ch_respond(200, [
            'status'         => 'success',
            'message'        => 'There was nothing to clear. The selected history is already empty.',
            'scope'          => $scope,
            'deleted'        => ['equipment' => 0, 'rooms' => 0],
            'restored_units' => 0,
        ]);
    }

    if ($do_equipment && $eq_total > 0) {
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
                error_log('[clear-history] no inventory row for "' . $item_name . '" - ' . $qty . ' unit(s) not restored');
            }
            $up->close();
        }

        // 2) Remember the uploaded letters so they can be cleaned up after the commit.
        $res = $conn->query("SELECT DISTINCT document_path FROM tbl_requests WHERE document_path IS NOT NULL AND document_path <> ''");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $docs[] = (string)$row['document_path'];
            }
        }

        // 3) Delete the records.
        $del = $conn->prepare('DELETE FROM tbl_requests');
        if (!$del) {
            throw new RuntimeException('equipment delete prepare failed: ' . $conn->error);
        }
        $del->execute();
        $deleted_equipment = (int)$del->affected_rows;
        $del->close();
    }

    if ($do_rooms && $rm_total > 0) {
        $res = $conn->query("SELECT DISTINCT document_path FROM tbl_room_reservations WHERE document_path IS NOT NULL AND document_path <> ''");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $docs[] = (string)$row['document_path'];
            }
        }

        $del = $conn->prepare('DELETE FROM tbl_room_reservations');
        if (!$del) {
            throw new RuntimeException('room delete prepare failed: ' . $conn->error);
        }
        $del->execute();
        $deleted_rooms = (int)$del->affected_rows;
        $del->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('[clear-history] ' . $e->getMessage());
    ch_respond(500, ['status' => 'error', 'code' => 'server', 'message' => 'Could not clear the history. Nothing was changed. Please try again.']);
}

// -- Best effort: remove the uploaded letters nobody points at any more ---------------
// Only a path inside <site>/uploads is ever touched, and only if no request or room reservation
// still references it. If a check cannot be made, the file is kept.
function ch_discard_upload(mysqli $conn, string $rel): void
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
foreach (array_unique($docs) as $rel) {
    ch_discard_upload($conn, $rel);
}

$plural = static function (int $n, string $one, string $many): string {
    return $n . ' ' . ($n === 1 ? $one : $many);
};

error_log(sprintf(
    '[clear-history] %s cleared scope "%s": %d equipment record(s), %d room reservation(s); %d unit(s) put back in stock',
    (string)($_SESSION['admin_email'] ?? '?'),
    $scope,
    $deleted_equipment,
    $deleted_rooms,
    $restore_done
));

if ($do_equipment && $deleted_equipment > 0) {
    admin_log_activity($conn, 'equipment', 'Cleared the entire equipment loan history (' . $plural($deleted_equipment, 'record', 'records') . ')');
}
if ($do_rooms && $deleted_rooms > 0) {
    admin_log_activity($conn, 'room', 'Cleared the entire room reservation history (' . $plural($deleted_rooms, 'reservation', 'reservations') . ')');
}

$parts = [];
if ($do_equipment) {
    $parts[] = $plural($deleted_equipment, 'equipment record', 'equipment records');
}
if ($do_rooms) {
    $parts[] = $plural($deleted_rooms, 'room reservation', 'room reservations');
}

ch_respond(200, [
    'status'         => 'success',
    'message'        => 'History cleared: ' . implode(' and ', $parts) . ' deleted.',
    'scope'          => $scope,
    'deleted'        => ['equipment' => $deleted_equipment, 'rooms' => $deleted_rooms],
    'restored_units' => $restore_done,
]);
