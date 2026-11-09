<?php

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/security-headers.php';
/**
 * ajax/admin-override.php
 *
 * AJAX endpoint — Admin emergency override for a single borrow request.
 *
 * Accepts:
 *   POST request_id      (int)    — PK of the tbl_requests row to override
 *   POST new_status      (string) — 'Approved' or 'Declined'
 *   POST override_reason (string) — Mandatory reason for the override (non-empty)
 *
 * Response: JSON { status: 'success'|'error', message: string }
 *
 * Requirements:
 *   13.2 — Prompt admin for a mandatory override reason before changing status.
 *   13.3 — Allow admin to set status to Approved or Declined with a mandatory reason.
 *   13.4 — Record override action in tbl_arbitration_log with admin name, reason, timestamp.
 *   13.5 — Decrement tbl_inventory.quantity by 1 when override approves a request (qty > 0).
 *   13.6 — Reject override if item quantity = 0 with "Cannot override: item is out of stock."
 */


date_default_timezone_set('Asia/Manila');

// ── Output buffering + JSON header ───────────────────────────────────────────
header('Content-Type: application/json');

// ── Helper: send a JSON response and exit ────────────────────────────────────
function send_json(int $http_status, string $status, string $message): void
{
    ob_end_clean();
    http_response_code($http_status);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ── Session guard — require active admin session ──────────────────────────────
require_once __DIR__ . '/../../config/session.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    send_json(401, 'error', 'Unauthorized. Admin access required.');
}

// ── CSRF guard ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/csrf.php';
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    send_json(403, 'error', 'Invalid or expired session. Please refresh the page and try again.');
}

$admin_name = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';

// ── Task 7.1: Parse POST inputs ───────────────────────────────────────────────
$raw_request_id      = $_POST['request_id']      ?? '';
$raw_new_status      = $_POST['new_status']       ?? '';
$raw_override_reason = trim((string)($_POST['override_reason'] ?? ''));
$raw_admin_notes     = trim((string)($_POST['admin_notes']     ?? ''));
// Sanitize: cap internal note to 1000 chars
if (mb_strlen($raw_admin_notes) > 1000) {
    $raw_admin_notes = mb_substr($raw_admin_notes, 0, 1000);
}
$admin_notes_value = $raw_admin_notes !== '' ? $raw_admin_notes : null;

// ── Validate override_reason — must be at least 5 characters ─────────────────
if (mb_strlen($raw_override_reason) < 5) {
    send_json(400, 'error', 'Override reason is required.');
}

// ── Validate new_status — must be 'Approved' or 'Declined' ───────────────────
$allowed_statuses = ['Approved', 'Declined'];

if (!in_array($raw_new_status, $allowed_statuses, true)) {
    send_json(400, 'error', 'Invalid status.');
}

$new_status = $raw_new_status;

// ── Validate request_id — must be a positive integer ─────────────────────────
if (!is_numeric($raw_request_id) || (int)$raw_request_id <= 0) {
    send_json(400, 'error', 'Invalid request ID.');
}

$request_id = (int)$raw_request_id;

// ── Database connection ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$conn->set_charset('utf8mb4');
require_once __DIR__ . '/../core/booking-schedule.php';
BookingSchedule::ensureSchema($conn);

// Activity log: make sure override_by_id exists (DDL) BEFORE the transaction starts.
require_once __DIR__ . '/../../shared/core/activity-logger.php';
admin_activity_bootstrap($conn);
$acting_admin_id = admin_current_id($conn);

// ── Task 7.2d: Verify request exists and fetch its data ──────────────────────
$fetch_stmt = $conn->prepare(
    "SELECT r.id,
            r.faculty_id,
            faculty_name,
            equipment_name,
            status,
            booking_mode,
            borrow_qty,
            stock_applied,
            submitted_as,
            document_path
       FROM tbl_requests r
      WHERE r.id = ?
      LIMIT 1"
);

if ($fetch_stmt === false) {
    send_json(500, 'error', 'Database error. Please try again.');
}

$fetch_stmt->bind_param('i', $request_id);

if (!$fetch_stmt->execute()) {
    $fetch_stmt->close();
    send_json(500, 'error', 'Database error. Please try again.');
}

$fetch_result = $fetch_stmt->get_result();
$request      = $fetch_result->fetch_assoc();
$fetch_stmt->close();

if ($request === null || $request === false) {
    send_json(404, 'error', 'Request not found.');
}

$borrower_id     = (string)$request['faculty_id'];
$borrower_name   = (string)($request['faculty_name'] ?? '');
$equipment_name  = (string)$request['equipment_name'];
$current_status  = (string)$request['status'];
// Today / Later bookings are judged against the schedule, not the shelf count
$is_scheduled    = in_array((string)($request['booking_mode'] ?? ''), ['today', 'future'], true);

// ── Enforce direction rules ───────────────────────────────────────────────────
// Approved/Overdue → only Declined is allowed (item is out on loan)
// Declined         → only Approved is allowed
// Waiting          → either Approved or Declined is allowed
$direction_ok = match ($current_status) {
    'Approved', 'Overdue' => $new_status === 'Declined',
    'Declined'            => $new_status === 'Approved',
    'Waiting'             => true,
    default               => false,
};

// Exception: a Waiting request that tick() promoted to Overdue before admin reviewed it
// can still be approved — the admin's decision takes precedence over the schedule tick.
if (!$direction_ok && $current_status === 'Overdue' && $new_status === 'Approved') {
    $orig_rule = (string)($request['arbitration_rule'] ?? '');
    if ($orig_rule === '' || $orig_rule === 'waiting') {
        $direction_ok = true;
    }
}

if (!$direction_ok) {
    send_json(422, 'error', 'Invalid status transition.');
}

// An already-approved booking that an admin revokes is a CANCELLATION, not a plain decline.
// The marker lives in the decision-log row only (tbl_requests.arbitration_rule stays 'override'),
// and the faculty notification feed reads it to send a "Booking Cancelled" notice.
// $log_rule only ever holds one of these two fixed strings (it is interpolated into the SQL below).
$is_cancellation = $new_status === 'Declined' && in_array($current_status, ['Approved', 'Overdue'], true);
$log_rule        = $is_cancellation ? 'booking_cancelled' : 'override';

// ── Begin transaction — wraps status UPDATE, inventory adjustment, and log INSERT
$conn->begin_transaction();

try {
    // ── Task 7.3: If approving, check inventory quantity > 0 ─────────────────
    if ($new_status === 'Approved' && !$is_scheduled) {
        $inv_stmt = $conn->prepare(
            "SELECT quantity
               FROM tbl_inventory
              WHERE item_name = ?
              LIMIT 1
              FOR UPDATE"
        );

        if ($inv_stmt === false) {
            throw new RuntimeException('Failed to prepare inventory query.');
        }

        $inv_stmt->bind_param('s', $equipment_name);

        if (!$inv_stmt->execute()) {
            $inv_stmt->close();
            throw new RuntimeException('Failed to execute inventory query.');
        }

        $inv_result = $inv_stmt->get_result();
        $inv_row    = $inv_result->fetch_assoc();
        $inv_stmt->close();

        // Item must exist in inventory and have quantity > 0
        if ($inv_row === null || $inv_row === false || (int)$inv_row['quantity'] <= 0) {
            $conn->rollback();
            send_json(409, 'error', 'Cannot override: item is out of stock.');
        }
    }

    // ── Task 7.4: UPDATE tbl_requests.status to new_status ───────────────────
    $upd_stmt = $conn->prepare(
        "UPDATE tbl_requests
            SET status           = ?,
                arbitration_rule = 'override',
                bundle_claim_token = NULL
          WHERE id = ?"
    );

    if ($upd_stmt === false) {
        throw new RuntimeException('Failed to prepare request status update.');
    }

    $upd_stmt->bind_param('si', $new_status, $request_id);

    if (!$upd_stmt->execute()) {
        $upd_stmt->close();
        throw new RuntimeException('Failed to update request status.');
    }

    $upd_stmt->close();

    // ── If approving, generate return token + claim token ─────────────────────
    if ($new_status === 'Approved') {
        $return_token = bin2hex(random_bytes(32)); // 64-char hex token
        $claim_token  = bin2hex(random_bytes(32)); // 64-char hex token
        $tok_stmt = $conn->prepare(
            "UPDATE tbl_requests
                SET return_token = ?, claim_token = ?, bundle_claim_token = NULL
              WHERE id = ?"
        );
        if (!$tok_stmt) {
            throw new RuntimeException('Failed to prepare request token update.');
        }
        $tok_stmt->bind_param('ssi', $return_token, $claim_token, $request_id);
        if (!$tok_stmt->execute()) {
            $tok_stmt->close();
            throw new RuntimeException('Failed to update request tokens.');
        }
        $tok_stmt->close();
    }

    // ── If approving, decrement tbl_inventory.quantity by 1 ──────────────────
    if ($new_status === 'Approved' && $is_scheduled) {
        // stock is taken when the booking starts (BookingSchedule::tick)
        $sa = $conn->prepare('UPDATE tbl_requests SET stock_applied = 0 WHERE id = ?');
        if ($sa) {
            $sa->bind_param('i', $request_id);
            $sa->execute();
            $sa->close();
        }
    }
    if ($new_status === 'Approved' && !$is_scheduled) {
        $dec_stmt = $conn->prepare(
            "UPDATE tbl_inventory
                SET quantity = quantity - 1
              WHERE item_name = ?
                AND quantity > 0"
        );

        if ($dec_stmt === false) {
            throw new RuntimeException('Failed to prepare inventory decrement.');
        }

        $dec_stmt->bind_param('s', $equipment_name);

        if (!$dec_stmt->execute()) {
            $dec_stmt->close();
            throw new RuntimeException('Failed to decrement inventory quantity.');
        }

        $dec_stmt->close();
    }

    // ── If declining a previously Approved/Overdue request, return 1 unit to stock ───
    if ($new_status === 'Declined' && in_array($current_status, ['Approved', 'Overdue'], true)) {
        // Give back exactly what left the shelf: legacy rows = 1; scheduled rows = their
        // quantity, and nothing at all if the booking had not started yet.
        $give_back = ((int)($request['stock_applied'] ?? 1) === 1) ? max(1, (int)($request['borrow_qty'] ?? 1)) : 0;
        $inc_stmt = $conn->prepare(
            "UPDATE tbl_inventory
                SET quantity = quantity + ?
              WHERE item_name = ?
                AND ? > 0"
        );

        if ($inc_stmt === false) {
            throw new RuntimeException('Failed to prepare inventory increment.');
        }

        $inc_stmt->bind_param('isi', $give_back, $equipment_name, $give_back);

        if (!$inc_stmt->execute()) {
            $inc_stmt->close();
            throw new RuntimeException('Failed to increment inventory quantity.');
        }

        $inc_stmt->close();
    }

    // ── INSERT or UPDATE tbl_arbitration_log (one row per request_id) ──────────
    // ON DUPLICATE KEY UPDATE ensures re-overrides update the existing row
    // rather than creating duplicates.
    //
    // admin_notes is an optional internal note (not shown to faculty) — only
    // written when the column exists (guarded by a runtime schema check below).
    $_arb_has_notes = false;
    $_col_check = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_arbitration_log' AND COLUMN_NAME = 'admin_notes' LIMIT 1");
    if ($_col_check && $_col_check->num_rows > 0) {
        $_arb_has_notes = true;
    }

    $log_sql_cols   = "(request_id, borrower_id, borrower_name, equipment_name,\n             decision, rule_applied, reason,\n             override_by, override_by_id, override_reason, created_at)";
    $log_sql_vals   = "(?, ?, ?, ?,\n             ?, '{$log_rule}', ?,\n             ?, ?, ?, NOW())";
    $log_sql_update = "decision        = VALUES(decision),\n            rule_applied    = '{$log_rule}',\n            reason          = VALUES(reason),\n            override_by     = VALUES(override_by),\n            override_by_id  = VALUES(override_by_id),\n            override_reason = VALUES(override_reason),\n            created_at      = NOW()";
    $log_bind_types = 'issssssis';

    if ($_arb_has_notes) {
        $log_sql_cols   = "(request_id, borrower_id, borrower_name, equipment_name,\n             decision, rule_applied, reason,\n             override_by, override_by_id, override_reason, admin_notes, created_at)";
        $log_sql_vals   = "(?, ?, ?, ?,\n             ?, '{$log_rule}', ?,\n             ?, ?, ?, ?, NOW())";
        $log_sql_update = "decision        = VALUES(decision),\n            rule_applied    = '{$log_rule}',\n            reason          = VALUES(reason),\n            override_by     = VALUES(override_by),\n            override_by_id  = VALUES(override_by_id),\n            override_reason = VALUES(override_reason),\n            admin_notes     = VALUES(admin_notes),\n            created_at      = NOW()";
        $log_bind_types = 'issssssiss';
    }

    $log_stmt = $conn->prepare(
        "INSERT INTO tbl_arbitration_log\n            {$log_sql_cols}\n         VALUES\n            {$log_sql_vals}\n         ON DUPLICATE KEY UPDATE\n            {$log_sql_update}"
    );

    if ($log_stmt === false) {
        throw new RuntimeException('Failed to prepare arbitration log insert.');
    }

    if ($_arb_has_notes) {
        $log_stmt->bind_param(
            $log_bind_types,
            $request_id,
            $borrower_id,
            $borrower_name,
            $equipment_name,
            $new_status,
            $raw_override_reason,
            $admin_name,
            $acting_admin_id,
            $raw_override_reason,
            $admin_notes_value
        );
    } else {
        $log_stmt->bind_param(
            $log_bind_types,
            $request_id,
            $borrower_id,
            $borrower_name,
            $equipment_name,
            $new_status,
            $raw_override_reason,
            $admin_name,
            $acting_admin_id,
            $raw_override_reason
        );
    }

    if (!$log_stmt->execute()) {
        $log_stmt->close();
        throw new RuntimeException('Failed to insert arbitration log entry.');
    }

    $log_stmt->close();

    // ── Commit the transaction ────────────────────────────────────────────────
    if (!$conn->commit()) {
        throw new RuntimeException('Transaction commit failed.');
    }
} catch (RuntimeException $e) {
    $conn->rollback();
    error_log('[admin-override] ' . $e->getMessage());
    send_json(500, 'error', 'Override failed. Please try again.');
}

// Activity log: who made this decision (shared with every admin).
admin_log_activity(
    $conn,
    'request',
    ($is_cancellation ? 'Cancelled booking' : $new_status . ' request') . ' #' . $request_id . ': ' . $equipment_name . ($borrower_name !== '' ? ' for ' . $borrower_name : ''),
    null,
    ['action' => $new_status === 'Approved' ? 'request_approved' : 'request_declined', 'ref_type' => 'request', 'ref_id' => $request_id]
);

$conn->close();

// ── Task 7.7: Return success response ────────────────────────────────────────
send_json(200, 'success', 'Override applied successfully.');
