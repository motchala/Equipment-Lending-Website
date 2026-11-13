<?php

/**
 * room-reservation/api/edit-reservation.php
 * Reschedule / edit an Approved room reservation (Admin only).
 *
 * POST JSON:
 *   {
 *     reservation_id: int,
 *     room_id: int,
 *     reservation_date: string (Y-m-d),
 *     start_time: string (H:i),
 *     end_time: string (H:i),
 *     notes: string (optional),
 *     csrf_token: string
 *   }
 */

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

function room_edit_reply(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['status' => $code < 400 ? 'success' : 'error', 'message' => $message], $extra));
    exit;
}

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

if (($_SESSION['admin'] ?? false) !== true) {
    room_edit_reply(401, 'Admin access is required.');
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($body['csrf_token'] ?? ''))) {
    room_edit_reply(403, 'Invalid request token. Please refresh and try again.');
}

$reservation_id = (int)($body['reservation_id'] ?? 0);
$new_room_id    = (int)($body['room_id'] ?? 0);
$new_date       = trim((string)($body['reservation_date'] ?? ''));
$new_start      = trim((string)($body['start_time'] ?? ''));
$new_end        = trim((string)($body['end_time'] ?? ''));
$notes          = trim((string)($body['notes'] ?? ''));

if ($reservation_id <= 0) {
    room_edit_reply(422, 'Invalid reservation ID.');
}
if ($new_room_id <= 0) {
    room_edit_reply(422, 'Please select a facility.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
    room_edit_reply(422, 'Invalid reservation date format (YYYY-MM-DD).');
}

// Normalize time to H:i:s
if (preg_match('/^\d{1,2}:\d{2}$/', $new_start)) {
    $new_start .= ':00';
}
if (preg_match('/^\d{1,2}:\d{2}$/', $new_end)) {
    $new_end .= ':00';
}

if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $new_start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $new_end)) {
    room_edit_reply(422, 'Invalid time format.');
}
if ($new_start >= $new_end) {
    room_edit_reply(422, 'Start time must be before end time.');
}

$conn = getDB();

// Fetch current reservation
$stmt = $conn->prepare(
    "SELECT rr.id, rr.room_id, rr.faculty_id, rr.faculty_name, rr.reservation_date,
            rr.start_time, rr.end_time, rr.status, rr.notes, r.room_name
       FROM tbl_room_reservations rr
       JOIN tbl_rooms r ON r.room_id = rr.room_id
      WHERE rr.id = ?
      LIMIT 1"
);
if (!$stmt) {
    room_edit_reply(500, 'Database error.');
}
$stmt->bind_param('i', $reservation_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    room_edit_reply(404, 'Reservation not found.');
}
if ($current['status'] !== 'Approved' && $current['status'] !== 'Waiting') {
    room_edit_reply(409, 'Only Approved or Waiting reservations can be rescheduled.');
}

// Fetch target room name
$rmStmt = $conn->prepare("SELECT room_name, status, is_archived FROM tbl_rooms WHERE room_id = ? LIMIT 1");
$rmStmt->bind_param('i', $new_room_id);
$rmStmt->execute();
$roomRow = $rmStmt->get_result()->fetch_assoc();
$rmStmt->close();

if (!$roomRow || (int)$roomRow['is_archived'] === 1) {
    room_edit_reply(422, 'The selected facility is no longer available.');
}
$new_room_name = (string)$roomRow['room_name'];

// ── Check overlap in tbl_room_reservations (excluding current reservation ID) ──
$conflictStmt = $conn->prepare(
    "SELECT id FROM tbl_room_reservations
      WHERE room_id          = ?
        AND reservation_date = ?
        AND status           = 'Approved'
        AND id              <> ?
        AND start_time       < ?
        AND end_time         > ?
      LIMIT 1"
);
if (!$conflictStmt) {
    room_edit_reply(500, 'Database query error.');
}
$conflictStmt->bind_param('isis', $new_room_id, $new_date, $reservation_id, $new_end, $new_start);
$conflictStmt->execute();
$hasConflict = $conflictStmt->get_result()->num_rows > 0;
$conflictStmt->close();

if ($hasConflict) {
    room_edit_reply(409, 'Conflict: This facility is already reserved for an overlapping time slot.');
}

// ── Check class schedule conflict if available ──────────────────────────────
try {
    require_once __DIR__ . '/../core/class-schedule.php';
    $class_hits = cs_room_conflicts($conn, $new_room_id, $new_date, $new_start, $new_end);
    if (!empty($class_hits)) {
        room_edit_reply(409, 'Conflict with scheduled class: ' . cs_class_label($class_hits[0]));
    }
} catch (Throwable $e) {
    // Continue if class schedule check not configured
}

// ── Update reservation ──────────────────────────────────────────────────────
$upd = $conn->prepare(
    "UPDATE tbl_room_reservations
        SET room_id          = ?,
            reservation_date = ?,
            start_time       = ?,
            end_time         = ?,
            notes            = ?
      WHERE id = ?"
);
if (!$upd) {
    room_edit_reply(500, 'Failed to update reservation.');
}
$final_notes = $notes !== '' ? $notes : ($current['notes'] ?? '');
$upd->bind_param('issssi', $new_room_id, $new_date, $new_start, $new_end, $final_notes, $reservation_id);
$upd->execute();
$upd->close();

// ── Activity log ────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../shared/core/activity-logger.php';
$start_fmt = date('g:i A', strtotime('1970-01-01 ' . $new_start));
$end_fmt   = date('g:i A', strtotime('1970-01-01 ' . $new_end));
$date_fmt  = date('M j, Y', strtotime($new_date));
$label     = 'Rescheduled reservation #' . $reservation_id . ': ' . $new_room_name . ' for ' . $current['faculty_name'] . ' to ' . $date_fmt . ' (' . $start_fmt . ' - ' . $end_fmt . ')';
admin_log_activity($conn, 'room', $label, null, [
    'action'   => 'room_rescheduled',
    'ref_type' => 'reservation',
    'ref_id'   => $reservation_id,
]);

// ── Email notification to faculty ───────────────────────────────────────────
try {
    $fac = $conn->prepare("SELECT email FROM tbl_users WHERE faculty_id = ? LIMIT 1");
    if ($fac) {
        $fac->bind_param('s', $current['faculty_id']);
        $fac->execute();
        $facRow = $fac->get_result()->fetch_assoc();
        $fac->close();

        require_once __DIR__ . '/../../notifications/core/preferences.php';
        // Respect the faculty's Notification Preferences (Facility reservations / master switch).
        if ($facRow && !empty($facRow['email']) && notif_family_enabled($conn, 'faculty', (string)$current['faculty_id'], 'facilities')) {
            require_once __DIR__ . '/../../shared/core/mailer.php';
            $facultyName = htmlspecialchars($current['faculty_name']);
            $subject     = 'PUPSync: Your Reservation Has Been Rescheduled — ' . $new_room_name;
            $bodyHtml    = '
<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
  <div style="background:#800000;padding:24px 28px;">
    <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync &middot; Reservation Schedule Updated</h2>
  </div>
  <div style="padding:28px;">
    <p style="margin:0 0 16px;color:#333;font-size:.95rem;">Hello <strong>' . $facultyName . '</strong>,</p>
    <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
      An administrator has adjusted the schedule for your facility reservation. Please review the updated details below:
    </p>
    <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
      <tr style="background:#f9f5f5;">
        <td style="padding:10px 14px;color:#666;width:40%;">Facility</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($new_room_name) . '</td>
      </tr>
      <tr>
        <td style="padding:10px 14px;color:#666;">Date</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($date_fmt) . '</td>
      </tr>
      <tr style="background:#f9f5f5;">
        <td style="padding:10px 14px;color:#666;">Time</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($start_fmt) . ' &ndash; ' . htmlspecialchars($end_fmt) . '</td>
      </tr>' . ($final_notes !== '' ? '
      <tr>
        <td style="padding:10px 14px;color:#666;">Admin Note</td>
        <td style="padding:10px 14px;color:#222;">' . htmlspecialchars($final_notes) . '</td>
      </tr>' : '') . '
    </table>
  </div>
  <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
    PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus
  </div>
</div>';
            sendPupSyncEmail($facRow['email'], $current['faculty_name'], $subject, $bodyHtml);
        }
    }
} catch (Throwable $e) {
    error_log('[edit-reservation] mailer: ' . $e->getMessage());
}

room_edit_reply(200, 'Reservation rescheduled successfully.', [
    'reservation' => [
        'id'         => $reservation_id,
        'room_id'    => $new_room_id,
        'room_name'  => $new_room_name,
        'date'       => $new_date,
        'date_fmt'   => $date_fmt,
        'start_time' => $new_start,
        'end_time'   => $new_end,
        'time_fmt'   => $start_fmt . ' - ' . $end_fmt,
        'notes'      => $final_notes,
    ]
]);

