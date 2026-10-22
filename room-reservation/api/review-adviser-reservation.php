<?php

/**
 * Super-Admin-only decision endpoint for Organization Adviser reservations
 * that have a supporting document. Ordinary admins are intentionally denied
 * even if they call this endpoint directly.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

function adviser_review_reply(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => $code < 400 ? 'success' : 'error', 'message' => $message]);
    exit;
}

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

if (($_SESSION['admin'] ?? false) !== true) {
    adviser_review_reply(401, 'Admin access is required.');
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($body['csrf_token'] ?? ''))) {
    adviser_review_reply(403, 'Invalid request token. Please refresh and try again.');
}

$reservation_id = (int)($body['reservation_id'] ?? 0);
$decision       = (string)($body['decision'] ?? '');
$reason         = trim((string)($body['reason'] ?? ''));
if ($reservation_id < 1 || !in_array($decision, ['Approved', 'Declined'], true)) {
    adviser_review_reply(422, 'Invalid reservation decision.');
}
if (mb_strlen($reason) < 5) {
    adviser_review_reply(422, 'Please provide a decision reason of at least 5 characters.');
}

$conn = getDB();
if (!admin_is_super_admin($conn)) {
    adviser_review_reply(403, 'Only a Super Admin can review an Organization Adviser reservation with a supporting document.');
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "SELECT rr.id, rr.room_id, rr.faculty_id, rr.faculty_name, rr.reservation_date,
                rr.start_time, rr.end_time, rr.status, rr.submitted_as, rr.document_path,
                r.room_name
           FROM tbl_room_reservations rr
           JOIN tbl_rooms r ON r.room_id = rr.room_id
          WHERE rr.id = ?
          FOR UPDATE"
    );
    if (!$stmt) throw new RuntimeException('Could not load the reservation.');
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $is_sensitive = $reservation
        && (string)$reservation['status'] === 'Waiting'
        && (string)$reservation['submitted_as'] === 'adviser'
        && trim((string)$reservation['document_path']) !== '';
    if (!$is_sensitive) {
        $conn->rollback();
        adviser_review_reply(409, 'This reservation is no longer waiting for Super Admin review.');
    }

    if ($decision === 'Approved') {
        $conflict = $conn->prepare(
            "SELECT 1 FROM tbl_room_reservations
              WHERE room_id = ? AND reservation_date = ? AND status = 'Approved'
                AND start_time < ? AND end_time > ?
              LIMIT 1"
        );
        if (!$conflict) throw new RuntimeException('Could not check facility availability.');
        $conflict->bind_param('isss', $reservation['room_id'], $reservation['reservation_date'], $reservation['end_time'], $reservation['start_time']);
        $conflict->execute();
        $has_conflict = $conflict->get_result()->num_rows > 0;
        $conflict->close();
        if ($has_conflict) {
            $conn->rollback();
            adviser_review_reply(409, 'This facility is already reserved for an overlapping time. Decline it or choose another time.');
        }
    }

    $upd = $conn->prepare("UPDATE tbl_room_reservations SET status = ?, reason = ? WHERE id = ? AND status = 'Waiting'");
    if (!$upd) throw new RuntimeException('Could not save the decision.');
    $upd->bind_param('ssi', $decision, $reason, $reservation_id);
    $upd->execute();
    if ($upd->affected_rows !== 1) {
        $upd->close();
        $conn->rollback();
        adviser_review_reply(409, 'This reservation was already reviewed.');
    }
    $upd->close();

    $log = $conn->prepare(
        "INSERT INTO tbl_room_arbitration_log
            (reservation_id, room_id, room_name, borrower_id, borrower_name, decision, rule_applied, reason)
         VALUES (?, ?, ?, ?, ?, ?, 'override', ?)"
    );
    if (!$log) throw new RuntimeException('Could not record the decision.');
    $log->bind_param(
        'iisssss',
        $reservation_id,
        $reservation['room_id'],
        $reservation['room_name'],
        $reservation['faculty_id'],
        $reservation['faculty_name'],
        $decision,
        $reason
    );
    $log->execute();
    $log->close();

    require_once __DIR__ . '/../../config/admin-activity.php';
    admin_log_activity(
        $conn,
        'room',
        $decision . ' adviser reservation #' . $reservation_id . ': ' . $reservation['room_name'],
        $reason,
        ['action' => $decision === 'Approved' ? 'room_approved' : 'room_declined', 'ref_type' => 'reservation', 'ref_id' => $reservation_id]
    );
    $conn->commit();
    adviser_review_reply(200, $decision === 'Approved' ? 'Reservation approved.' : 'Reservation declined.');
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // no-op; rollback below is safe even if the query failed first
    }
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('[review-adviser-reservation] ' . $e->getMessage());
    adviser_review_reply(500, 'Could not save the reservation decision.');
}
