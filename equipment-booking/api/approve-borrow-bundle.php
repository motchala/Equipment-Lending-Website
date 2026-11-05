<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');

function send_bundle_json(int $http_status, string $status, string $message, array $extra = []): void
{
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code($http_status);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    send_bundle_json(401, 'error', 'Unauthorized. Admin access required.');
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf_token = (string)($_POST['csrf_token'] ?? '');
if ($csrf_token === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf_token)) {
    send_bundle_json(403, 'error', 'Invalid or expired session. Please refresh the page and try again.');
}

$batch_id = trim((string)($_POST['batch_id'] ?? ''));
$reason = trim((string)($_POST['override_reason'] ?? ''));
if (!preg_match('/^[a-f0-9-]{36}$/i', $batch_id)) {
    send_bundle_json(400, 'error', 'Invalid bundle.');
}
if (mb_strlen($reason) < 5) {
    send_bundle_json(400, 'error', 'Approval reason must be at least 5 characters.');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';

$conn = getDB();
if (!BookingSchedule::ensureSchema($conn)) {
    send_bundle_json(500, 'error', 'Could not prepare bundle approval. Please try again.');
}
admin_activity_bootstrap($conn);
$admin_name = (string)($_SESSION['admin_name'] ?? 'Admin');
$acting_admin_id = admin_current_id($conn);

try {
    $conn->begin_transaction();

    $select = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name, status, booking_mode,
                document_path, submitted_as
           FROM tbl_requests
          WHERE batch_id = ?
          ORDER BY id
          FOR UPDATE"
    );
    if (!$select) {
        throw new RuntimeException('Bundle request query could not be prepared.');
    }
    $select->bind_param('s', $batch_id);
    if (!$select->execute()) {
        $select->close();
        throw new RuntimeException('Bundle request query failed.');
    }
    $result = $select->get_result();
    $batchRows = [];
    while ($row = $result->fetch_assoc()) {
        $batchRows[] = $row;
    }
    $select->close();

    if (count($batchRows) < 2) {
        $conn->rollback();
        send_bundle_json(404, 'error', 'This bundle no longer contains multiple requests.');
    }

    $waiting = array_values(array_filter($batchRows, static function (array $row): bool {
        return (string)$row['status'] === 'Waiting';
    }));
    if (count($waiting) < 2) {
        $conn->rollback();
        send_bundle_json(409, 'error', 'At least two bundled requests must still be pending to approve them together.');
    }

    foreach ($waiting as $row) {
        $sensitive = (string)($row['submitted_as'] ?? '') === 'adviser'
            && trim((string)($row['document_path'] ?? '')) !== '';
        if ($sensitive && !admin_is_super_admin($conn)) {
            $conn->rollback();
            send_bundle_json(403, 'error', 'Only a Super Admin can approve this bundle.');
        }
    }

    $inventoryNeeds = [];
    foreach ($waiting as $row) {
        if (!in_array((string)($row['booking_mode'] ?? ''), ['today', 'future'], true)) {
            $name = (string)$row['equipment_name'];
            $inventoryNeeds[$name] = ($inventoryNeeds[$name] ?? 0) + 1;
        }
    }
    ksort($inventoryNeeds, SORT_STRING);
    foreach ($inventoryNeeds as $name => $needed) {
        $stock = $conn->prepare('SELECT quantity FROM tbl_inventory WHERE item_name = ? LIMIT 1 FOR UPDATE');
        if (!$stock) {
            throw new RuntimeException('Inventory query could not be prepared.');
        }
        $stock->bind_param('s', $name);
        if (!$stock->execute()) {
            $stock->close();
            throw new RuntimeException('Inventory query failed.');
        }
        $stockRow = $stock->get_result()->fetch_assoc();
        $stock->close();
        if (!$stockRow || (int)$stockRow['quantity'] < $needed) {
            $conn->rollback();
            send_bundle_json(409, 'error', 'Cannot approve the whole bundle: not enough stock for ' . $name . '.');
        }
    }

    $bundle_claim_token = bin2hex(random_bytes(32));
    $update = $conn->prepare(
        "UPDATE tbl_requests
            SET status = 'Approved',
                arbitration_rule = 'override',
                return_token = ?,
                claim_token = NULL,
                bundle_claim_token = ?
          WHERE id = ? AND batch_id = ? AND status = 'Waiting'"
    );
    if (!$update) {
        throw new RuntimeException('Bundle approval update could not be prepared.');
    }

    $log = $conn->prepare(
        "INSERT INTO tbl_arbitration_log
            (request_id, borrower_id, borrower_name, equipment_name,
             decision, rule_applied, reason, override_by, override_by_id,
             override_reason, created_at)
         VALUES (?, ?, ?, ?, 'Approved', 'override', ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            decision = VALUES(decision),
            rule_applied = 'override',
            reason = VALUES(reason),
            override_by = VALUES(override_by),
            override_by_id = VALUES(override_by_id),
            override_reason = VALUES(override_reason),
            created_at = NOW()"
    );
    if (!$log) {
        throw new RuntimeException('Bundle decision log could not be prepared.');
    }

    foreach ($waiting as $row) {
        $request_id = (int)$row['id'];
        $return_token = bin2hex(random_bytes(32));
        $update->bind_param('ssis', $return_token, $bundle_claim_token, $request_id, $batch_id);
        if (!$update->execute() || $update->affected_rows !== 1) {
            throw new RuntimeException('A bundled request changed before it could be approved.');
        }

        $borrower_id = (string)$row['faculty_id'];
        $borrower_name = (string)$row['faculty_name'];
        $equipment_name = (string)$row['equipment_name'];
        $log->bind_param(
            'isssssis',
            $request_id,
            $borrower_id,
            $borrower_name,
            $equipment_name,
            $reason,
            $admin_name,
            $acting_admin_id,
            $reason
        );
        if (!$log->execute()) {
            throw new RuntimeException('A bundle decision could not be recorded.');
        }
    }
    $update->close();
    $log->close();

    foreach ($inventoryNeeds as $name => $needed) {
        $decrement = $conn->prepare('UPDATE tbl_inventory SET quantity = quantity - ? WHERE item_name = ? AND quantity >= ?');
        if (!$decrement) {
            throw new RuntimeException('Inventory update could not be prepared.');
        }
        $decrement->bind_param('isi', $needed, $name, $needed);
        if (!$decrement->execute() || $decrement->affected_rows !== 1) {
            $decrement->close();
            throw new RuntimeException('Inventory changed before the bundle could be approved.');
        }
        $decrement->close();
    }

    if (!$conn->commit()) {
        throw new RuntimeException('Bundle approval transaction could not be committed.');
    }
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[approve-borrow-bundle] ' . $e->getMessage());
    send_bundle_json(500, 'error', 'Bundle approval failed. No requests were changed. Please try again.');
}

foreach ($waiting as $row) {
    $request_id = (int)$row['id'];
    $equipment_name = (string)$row['equipment_name'];
    $borrower_name = (string)$row['faculty_name'];
    admin_log_activity(
        $conn,
        'request',
        'Approved bundled request #' . $request_id . ': ' . $equipment_name . ' for ' . $borrower_name,
        null,
        ['action' => 'request_approved', 'ref_type' => 'request', 'ref_id' => $request_id]
    );
}

send_bundle_json(200, 'success', 'Approved ' . count($waiting) . ' bundled requests. The faculty member will have one Claim Bundle QR.', [
    'approved_count' => count($waiting),
    'request_ids' => array_map(static fn(array $row): int => (int)$row['id'], $waiting),
]);
