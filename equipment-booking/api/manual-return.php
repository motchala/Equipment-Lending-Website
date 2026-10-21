<?php
/** Record an administrator-verified equipment return without a QR token. */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');

function manual_return_response(int $code, string $status, string $message): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    manual_return_response(405, 'error', 'POST is required.');
}

require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    manual_return_response(401, 'error', 'Unauthorized. Admin access is required.');
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    manual_return_response(403, 'error', 'Invalid or expired session. Please refresh the page and try again.');
}

$rawId = $_POST['request_id'] ?? '';
if (!is_scalar($rawId) || !ctype_digit((string)$rawId) || (int)$rawId < 1) {
    manual_return_response(400, 'error', 'Invalid loan record.');
}
$condition = trim((string)($_POST['return_condition'] ?? 'Good'));
if (!in_array($condition, ['Good', 'Fair', 'Damaged', 'Missing'], true)) {
    manual_return_response(400, 'error', 'Invalid return condition.');
}
$notes = trim((string)($_POST['admin_notes'] ?? ''));
if (mb_strlen($notes) > 2000) {
    manual_return_response(400, 'error', 'Admin notes must be 2,000 characters or fewer.');
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../../config/admin-activity.php';

$conn = getDB();
$conn->set_charset('utf8mb4');
BookingSchedule::ensureSchema($conn);
admin_activity_bootstrap($conn);
$requestId = (int)$rawId;
$adminName = (string)($_SESSION['admin_name'] ?? 'Admin');
$adminId = admin_current_id($conn);

try {
    $conn->begin_transaction();
    $find = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name, status, borrow_qty, stock_applied
           FROM tbl_requests WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    if (!$find) throw new RuntimeException('Could not read the loan record.');
    $find->bind_param('i', $requestId);
    $find->execute();
    $loan = $find->get_result()->fetch_assoc();
    $find->close();

    if (!$loan) {
        $conn->rollback();
        manual_return_response(404, 'error', 'Loan record not found.');
    }
    if (!in_array($loan['status'], ['Approved', 'Overdue'], true)) {
        $conn->rollback();
        manual_return_response(409, 'error', 'This loan has already been closed or cannot be returned.');
    }

    $update = $conn->prepare(
        "UPDATE tbl_requests SET status = 'Returned', returned_at = NOW(), return_token = NULL
          WHERE id = ? AND status IN ('Approved', 'Overdue')"
    );
    if (!$update) throw new RuntimeException('Could not update the loan record.');
    $update->bind_param('i', $requestId);
    $update->execute();
    if ($update->affected_rows !== 1) {
        $update->close();
        throw new RuntimeException('The loan changed before its return could be recorded.');
    }
    $update->close();

    // Do not add stock for a future booking that has never taken stock out.
    $returnQty = ((int)$loan['stock_applied'] === 1) ? max(1, (int)$loan['borrow_qty']) : 0;
    if ($returnQty > 0) {
        $stock = $conn->prepare('UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?');
        if (!$stock) throw new RuntimeException('Could not restore inventory.');
        $stock->bind_param('is', $returnQty, $loan['equipment_name']);
        $stock->execute();
        if ($stock->affected_rows !== 1) {
            $stock->close();
            throw new RuntimeException('The inventory item no longer exists.');
        }
        $stock->close();
    }

    $details = 'Condition: ' . $condition . ($notes === '' ? '' : "\nNotes: " . $notes);
    $reason = 'Equipment returned manually by an administrator.';
    $log = $conn->prepare(
        "INSERT INTO tbl_arbitration_log
            (request_id, borrower_id, borrower_name, equipment_name, decision, rule_applied, reason, override_by, override_by_id, override_reason, created_at)
         VALUES (?, ?, ?, ?, 'Returned', 'manual_return', ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            decision = 'Returned', rule_applied = 'manual_return', reason = VALUES(reason),
            override_by = VALUES(override_by), override_by_id = VALUES(override_by_id),
            override_reason = VALUES(override_reason), created_at = NOW()"
    );
    if (!$log) throw new RuntimeException('Could not record the return.');
    $log->bind_param('isssssis', $requestId, $loan['faculty_id'], $loan['faculty_name'], $loan['equipment_name'], $reason, $adminName, $adminId, $details);
    $log->execute();
    $log->close();

    $conn->commit();
    admin_log_activity(
        $conn,
        'request',
        'Recorded manual return: ' . $loan['equipment_name'] . ' (' . $loan['faculty_name'] . ')',
        null,
        ['action' => 'request_returned', 'ref_type' => 'request', 'ref_id' => $requestId]
    );
    // A regular form post is deliberately used by the On Loan screen: it is
    // more reliable than leaving a disabled button waiting on an async fetch.
    // Keep JSON for any future AJAX caller.
    if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
        header('Location: ../../admin-dashboard.php?view=inventory&manual_return=1#tab-inventory', true, 303);
        exit;
    }
    manual_return_response(200, 'success', 'Manual return recorded.');
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) { }
    error_log('[manual-return] ' . $e->getMessage());
    manual_return_response(500, 'error', 'Could not record the return. Please try again.');
}
