<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
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
session_start();

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    send_json(401, 'error', 'Unauthorized. Admin access required.');
}

$admin_name = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';

// ── Task 7.1: Parse POST inputs ───────────────────────────────────────────────
$raw_request_id    = $_POST['request_id']      ?? '';
$raw_new_status    = $_POST['new_status']       ?? '';
$raw_override_reason = trim((string)($_POST['override_reason'] ?? ''));

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
require_once __DIR__ . '/../includes/db.php';
$conn = getDB();

$conn->set_charset('utf8mb4');

// ── Task 7.2d: Verify request exists and fetch its data ──────────────────────
$fetch_stmt = $conn->prepare(
    "SELECT r.id,
            r.faculty_id,
            faculty_name,
            equipment_name,
            status
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

if (!$direction_ok) {
    send_json(422, 'error', 'Invalid status transition.');
}

// ── Begin transaction — wraps status UPDATE, inventory adjustment, and log INSERT
$conn->begin_transaction();

try {
    // ── Task 7.3: If approving, check inventory quantity > 0 ─────────────────
    if ($new_status === 'Approved') {
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
                arbitration_rule = 'override'
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

     // ── If approving, generate a unique return token ──────────────────────────
    if ($new_status === 'Approved') {
        $return_token = bin2hex(random_bytes(32)); // 64-char hex token
        $tok_stmt = $conn->prepare(
            "UPDATE tbl_requests SET return_token = ? WHERE id = ? AND return_token IS NULL"
        );
        if ($tok_stmt) {
            $tok_stmt->bind_param('si', $return_token, $request_id);
            $tok_stmt->execute();
            $tok_stmt->close();
        }
    }

    // ── If approving, decrement tbl_inventory.quantity by 1 ──────────────────
    if ($new_status === 'Approved') {
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
        $inc_stmt = $conn->prepare(
            "UPDATE tbl_inventory
                SET quantity = quantity + 1
              WHERE item_name = ?"
        );

        if ($inc_stmt === false) {
            throw new RuntimeException('Failed to prepare inventory increment.');
        }

        $inc_stmt->bind_param('s', $equipment_name);

        if (!$inc_stmt->execute()) {
            $inc_stmt->close();
            throw new RuntimeException('Failed to increment inventory quantity.');
        }

        $inc_stmt->close();
    }

    // ── INSERT or UPDATE tbl_arbitration_log (one row per request_id) ──────────
    // ON DUPLICATE KEY UPDATE ensures re-overrides update the existing row
    // rather than creating duplicates.
    $log_stmt = $conn->prepare(
        "INSERT INTO tbl_arbitration_log
            (request_id, borrower_id, borrower_name, equipment_name,
             decision, rule_applied, reason,
             override_by, override_reason, created_at)
         VALUES
            (?, ?, ?, ?,
             ?, 'override', ?,
             ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            decision        = VALUES(decision),
            rule_applied    = 'override',
            reason          = VALUES(reason),
            override_by     = VALUES(override_by),
            override_reason = VALUES(override_reason),
            created_at      = NOW()"
    );

    if ($log_stmt === false) {
        throw new RuntimeException('Failed to prepare arbitration log insert.');
    }

    $log_stmt->bind_param(
        'isssssss',
        $request_id,
        $borrower_id,
        $borrower_name,
        $equipment_name,
        $new_status,
        $raw_override_reason,
        $admin_name,
        $raw_override_reason
    );

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

$conn->close();

// ── Task 7.7: Return success response ────────────────────────────────────────
send_json(200, 'success', 'Override applied successfully.');