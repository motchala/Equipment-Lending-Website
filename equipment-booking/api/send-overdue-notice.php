<?php

/** Send an administrator-authored overdue-return notice to one faculty inbox. */

declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');

function overdue_notice_response(int $code, string $message, array $extra = []): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['status' => $code < 400 ? 'success' : 'error', 'message' => $message], $extra));
    exit;
}

/** Every overdue notice this endpoint sends starts its title with this text (used for the once-a-day limit). */
const OVERDUE_NOTICE_TITLE_PREFIX = 'Immediate Return Required: ';

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
require_once __DIR__ . '/../../shared/core/activity-logger.php';
require_once __DIR__ . '/../../notifications/core/faculty-data.php';

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
    $title = OVERDUE_NOTICE_TITLE_PREFIX . $loan['equipment_name'];
    $message = 'This is an official overdue-return notice from PUPSync Admin. '
        . $loan['equipment_name'] . ' was due on ' . $dueLabel . ' and is now '
        . $daysOverdue . ' day' . ($daysOverdue === 1 ? '' : 's') . ' overdue. '
        . 'Please return this equipment to the Admin Office immediately. Your borrowing access remains blocked until the return is recorded.';

    // Once per day per faculty: the admin cannot spam the same faculty member. "Day" is the
    // calendar day in Asia/Manila. The check and the insert are ONE statement, so two admins
    // pressing Send at the same moment cannot both get through.
    $nowStr    = date('Y-m-d H:i:s');
    $dayStart  = date('Y-m-d') . ' 00:00:00';
    $unlockTs  = strtotime('tomorrow');
    $titleLike = OVERDUE_NOTICE_TITLE_PREFIX . '%';
    $facultyId = (string)$loan['faculty_id'];

    $insert = $conn->prepare(
        'INSERT INTO tbl_faculty_admin_notice
            (faculty_id, request_id, admin_id, admin_name, title, message, created_at)
         SELECT ?, ?, ?, ?, ?, ?, ?
           FROM DUAL
          WHERE NOT EXISTS (
                SELECT 1 FROM tbl_faculty_admin_notice
                 WHERE faculty_id = ? AND title LIKE ? AND created_at >= ?)'
    );
    if (!$insert) throw new RuntimeException('Could not prepare the notice.');
    $insert->bind_param('siisssssss', $facultyId, $requestId, $adminId, $adminName, $title, $message, $nowStr, $facultyId, $titleLike, $dayStart);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException('Could not send the notice.');
    }
    $sent = $insert->affected_rows === 1;
    $insert->close();

    if (!$sent) {
        $lastAt = null;
        $last = $conn->prepare('SELECT MAX(created_at) FROM tbl_faculty_admin_notice WHERE faculty_id = ? AND title LIKE ? AND created_at >= ?');
        if ($last) {
            $last->bind_param('sss', $facultyId, $titleLike, $dayStart);
            $last->execute();
            $last->bind_result($lastAt);
            $last->fetch();
            $last->close();
        }
        $when = isset($lastAt) && $lastAt !== null
            ? ' at ' . date('g:i A', strtotime((string)$lastAt))
            : '';
        overdue_notice_response(
            429,
            'A notice was already sent to ' . $loan['faculty_name'] . ' today' . $when . '. You can send another one tomorrow.',
            ['code' => 'already_sent_today', 'faculty_id' => $facultyId, 'unlock_ts' => $unlockTs]
        );
    }

    admin_log_activity(
        $conn,
        'request',
        'Sent overdue return notice for ' . $loan['equipment_name'] . ' to ' . $loan['faculty_name'],
        null,
        ['action' => 'overdue_notice_sent', 'ref_type' => 'request', 'ref_id' => $requestId]
    );

    // The On Loan screen calls this with fetch() and updates the row in place (no page reload).
    // A plain form post (no JavaScript) still gets the old redirect.
    if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
        header('Location: ../../admin-dashboard.php?view=inventory&notice_sent=1#tab-inventory', true, 303);
        exit;
    }
    $cnt = 1;
    $cntStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_faculty_admin_notice WHERE request_id = ?');
    if ($cntStmt) {
        $cntStmt->bind_param('i', $requestId);
        $cntStmt->execute();
        $cntStmt->bind_result($cnt);
        $cntStmt->fetch();
        $cntStmt->close();
    }
    overdue_notice_response(200, 'Overdue notice sent to the borrower.', [
        'request_id'   => $requestId,
        'faculty_id'   => $facultyId,
        'notice_count' => (int)$cnt,
        'last_label'   => date('M j, Y g:i A', strtotime($nowStr)),
        'unlock_ts'    => $unlockTs,
    ]);
} catch (Throwable $e) {
    error_log('[send-overdue-notice] ' . $e->getMessage());
    overdue_notice_response(500, 'Could not send the overdue notice. Please try again.');
}
