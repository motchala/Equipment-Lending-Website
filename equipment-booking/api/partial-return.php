<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: ../../landing-page.php');
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    die('Invalid session token. Please go back and try again.');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';
require_once __DIR__ . '/../core/booking-schedule.php';

$conn = getDB();
BookingSchedule::ensureSchema($conn);
admin_activity_bootstrap($conn);
$acting_admin_id = admin_current_id($conn);
$admin_name      = (string)($_SESSION['admin_name'] ?? 'Admin');

$token       = trim((string)($_POST['token'] ?? ''));
$conditions  = (array)($_POST['condition'] ?? []);   // [request_id => condition]
$item_tokens = (array)($_POST['item_token'] ?? []);  // [request_id => return_token] (bundle items)

const VALID_CONDITIONS = ['Good', 'Fair', 'Damaged', 'Missing'];

if ($token === '') {
    die('No token provided.');
}

$returned = 0;
$damage_flags = [];

foreach ($conditions as $req_id_raw => $condition_raw) {
    $req_id    = (int)$req_id_raw;
    $condition = in_array($condition_raw, VALID_CONDITIONS, true) ? $condition_raw : 'Good';
    if ($req_id <= 0) continue;

    $conn->begin_transaction();
    try {
        // Use per-item token if provided (bundle return), otherwise the single token
        $item_token = isset($item_tokens[$req_id_raw]) ? trim((string)$item_tokens[$req_id_raw]) : $token;

        $stmt = $conn->prepare(
            "SELECT id, faculty_id, faculty_name, equipment_name,
                    borrow_qty, stock_applied, status
               FROM tbl_requests
              WHERE id = ? AND return_token = ?
                AND status IN ('Approved','On Loan','Overdue')
              LIMIT 1 FOR UPDATE"
        );
        if (!$stmt) { $conn->rollback(); continue; }
        $stmt->bind_param('is', $req_id, $item_token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) { $conn->rollback(); continue; }

        $eq_name     = (string)$row['equipment_name'];
        $returned_at = date('Y-m-d H:i:s');

        // Mark returned
        $upd = $conn->prepare(
            "UPDATE tbl_requests
                SET status = 'Returned', returned_at = ?,
                    return_token = NULL,
                    return_condition = ?
              WHERE id = ?"
        );
        if (!$upd) { $conn->rollback(); continue; }
        $upd->bind_param('ssi', $returned_at, $condition, $req_id);
        $upd->execute();
        $upd->close();

        // Restore inventory (only if stock left the shelf)
        $ret_qty = ((int)($row['stock_applied'] ?? 1) === 1)
            ? max(1, (int)($row['borrow_qty'] ?? 1)) : 0;
        if ($ret_qty > 0 && $condition !== 'Missing') {
            // Missing items never come back — don't restore stock
            $inc = $conn->prepare(
                "UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?"
            );
            if ($inc) {
                $inc->bind_param('is', $ret_qty, $eq_name);
                $inc->execute();
                $inc->close();
            }
        }

        // Log to arbitration log
        $rule    = 'qr_return';
        $reason  = 'Equipment returned via QR scan — condition: ' . $condition;
        $log = $conn->prepare(
            "INSERT INTO tbl_arbitration_log
                (request_id, borrower_id, borrower_name, equipment_name,
                 decision, rule_applied, reason,
                 override_by, override_by_id, override_reason, created_at)
             VALUES (?, ?, ?, ?, 'Returned', ?, ?, ?, ?, 'QR Return with condition', ?)
             ON DUPLICATE KEY UPDATE
                decision = 'Returned', rule_applied = VALUES(rule_applied),
                reason = VALUES(reason),
                override_by = VALUES(override_by),
                override_by_id = VALUES(override_by_id),
                created_at = VALUES(created_at)"
        );
        if ($log) {
            $log->bind_param(
                'issssssis',
                $req_id,
                $row['faculty_id'],
                $row['faculty_name'],
                $eq_name,
                $rule,
                $reason,
                $admin_name,
                $acting_admin_id,
                $returned_at
            );
            $log->execute();
            $log->close();
        }

        // Damage/missing flag: insert into tbl_faculty_admin_notice so faculty sees it
        if (in_array($condition, ['Damaged', 'Missing'], true)) {
            $damage_flags[] = ['faculty_id' => $row['faculty_id'], 'faculty_name' => $row['faculty_name'], 'item' => $eq_name, 'condition' => $condition];
            $notice_title   = ($condition === 'Missing' ? 'Missing Item Reported' : 'Damaged Item Reported') . ': ' . $eq_name;
            $notice_msg     = 'Admin recorded your returned item "' . $eq_name . '" as ' . strtolower($condition)
                . '. This has been flagged on your account and may affect future borrow requests. Contact the admin office if you believe this is an error.';
            $notice_stmt = $conn->prepare(
                "INSERT INTO tbl_faculty_admin_notice
                    (faculty_id, request_id, admin_id, admin_name, title, message, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            if ($notice_stmt) {
                $notice_stmt->bind_param(
                    'siisss',
                    $row['faculty_id'],
                    $req_id,
                    $acting_admin_id,
                    $admin_name,
                    $notice_title,
                    $notice_msg
                );
                $notice_stmt->execute();
                $notice_stmt->close();
            }
        }

        $conn->commit();
        $returned++;

        admin_log_activity(
            $conn,
            'request',
            'Returned ' . $eq_name . ' (' . $condition . ') — ' . $row['faculty_name'],
            null,
            ['action' => 'request_returned', 'ref_type' => 'request', 'ref_id' => $req_id]
        );
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[partial-return] id=' . $req_id . ': ' . $e->getMessage());
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'returned' => $returned]);
exit;
