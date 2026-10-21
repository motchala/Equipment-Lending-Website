<?php
/** Send an administrator-authored overdue-return notice to one faculty inbox. */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');

function overdue_notice_response(int $code, string $message): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    echo json_encode(['status' => $code < 400 ? 'success' : 'error', 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    overdue_notice_response(405, 'POST is required.');
}

require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    overdue_notice_response(401, 'Unauthorized. Admin access is required.');
}
require_once __DIR__ . '/../../config/csrf.php';
$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    overdue_notice_response(403, 'Invalid or expired session. Please refresh the page and try again.');
}

$rawId = $_POST['request_id'] ?? '';
if (!is_scalar($rawId) || !ctype_digit((string)$rawId) || (int)$rawId < 1) {
    overdue_notice_response(400, 'Invalid overdue loan.');
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/admin-activity.php';
require_once __DIR__ . '/../core/faculty-notif-functions.php';

$conn = getDB();
$conn->set_charset('utf8mb4');
admin_activity_bootstrap($conn);
fnotif_ensure_admin_notice_table($conn);

$requestId = (int)$rawId;
$adminName = (string)($_SESSION['admin_name'] ?? 'Admin');
$adminId = admin_current_id($conn);

try {
    $loanStmt = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name, return_date
           FROM tbl_requests
          WHERE id = ? AND status = 'Overdue'
          LIMIT 1"
    );
    if (!$loanStmt) throw new RuntimeException('Could not read the overdue loan.');
    $loanStmt->bind_param('i', $requestId);
    $loanStmt->execute();
    $loan = $loanStmt->get_result()->fetch_assoc();
    $loanStmt->close();

    if (!$loan) {
        overdue_notice_response(409, 'This loan is no longer overdue, so no notice was sent.');
    }

    $dueTs = strtotime((string)$loan['return_date']) ?: time();
    $daysOverdue = max(1, (int)floor((time() - $dueTs) / 86400));
    $dueLabel = date('F j, Y', $dueTs);
    $title = 'Immediate Return Required: ' . $loan['equipment_name'];
    $message = 'This is an official overdue-return notice from PUPSync Admin. '
        . $loan['equipment_name'] . ' was due on ' . $dueLabel . ' and is now '
        . $daysOverdue . ' day' . ($daysOverdue === 1 ? '' : 's') . ' overdue. '
        . 'Please return this equipment to the Admin Office immediately. Your borrowing access remains blocked until the return is recorded.';

    $insert = $conn->prepare(
        'INSERT INTO tbl_faculty_admin_notice
            (faculty_id, request_id, admin_id, admin_name, title, message, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$insert) throw new RuntimeException('Could not prepare the notice.');
    $insert->bind_param('siisss', $loan['faculty_id'], $requestId, $adminId, $adminName, $title, $message);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException('Could not send the notice.');
    }
    $insert->close();

    admin_log_activity(
        $conn,
        'request',
        'Sent overdue return notice for ' . $loan['equipment_name'] . ' to ' . $loan['faculty_name'],
        null,
        ['action' => 'overdue_notice_sent', 'ref_type' => 'request', 'ref_id' => $requestId]
    );

    // The dashboard uses a normal form post here so the notice action remains
    // reliable even when the page has other live polling requests in flight.
    header('Location: ../../admin-dashboard.php?view=inventory&notice_sent=1#tab-inventory', true, 303);
    exit;
} catch (Throwable $e) {
    error_log('[send-overdue-notice] ' . $e->getMessage());
    overdue_notice_response(500, 'Could not send the overdue notice. Please try again.');
}
