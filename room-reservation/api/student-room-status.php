<?php
declare(strict_types=1);
/**
 * student-room-status.php
 *
 * Returns the current status of a room reservation the student just submitted.
 * The portal has no login, so each reservation is identified by its id plus the
 * secret status_key returned by submit-student-reserve.php. A wrong or missing
 * key is answered exactly like an unknown reservation.
 *
 * POST (JSON): { "reservation_id": 42, "key": "…" }
 * Reply (success): { "success": true, "reservation_id": 42, "status": "…", "reason": "…", "room_name": "…" }
 * Reply (not found): { "success": true, "status": "Missing", "reason": "", "room_name": "" }
 */
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$body           = json_decode((string)file_get_contents('php://input'), true);
$reservation_id = (int)($body['reservation_id'] ?? 0);
$key            = (string)($body['key'] ?? '');

if ($reservation_id <= 0 || $key === '') {
    echo json_encode(['success' => true, 'status' => 'Missing', 'reason' => '', 'room_name' => '']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/room-expiry.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

// Expire any reservations whose window has passed before we answer.
ps_room_expire_waiting($conn);

$stmt = $conn->prepare(
    "SELECT rr.status, COALESCE(rr.reason, '') AS reason,
            COALESCE(t.room_name, '') AS room_name,
            COALESCE(rr.student_key, '') AS student_key
       FROM tbl_room_reservations rr
  LEFT JOIN tbl_rooms t ON t.room_id = rr.room_id
      WHERE rr.id = ?
      LIMIT 1"
);
if (!$stmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['error' => 'Could not check your reservation right now.']);
    exit;
}

$stmt->bind_param('i', $reservation_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$row || $row['student_key'] === '' || !hash_equals($row['student_key'], $key)) {
    $conn->close();
    echo json_encode(['success' => true, 'status' => 'Missing', 'reason' => '', 'room_name' => '']);
    exit;
}

$status    = (string)$row['status'];
$reason    = (string)$row['reason'];
$room_name = (string)$row['room_name'];

// For a decided reservation the waiting note no longer applies: use the arbitration log's reason.
if ($status === 'Declined' || $status === 'Cancelled') {
    $lg = $conn->prepare(
        'SELECT reason FROM tbl_arbitration_log
          WHERE reservation_id = ?
          ORDER BY id DESC LIMIT 1'
    );
    if ($lg) {
        $lg->bind_param('i', $reservation_id);
        $lg->execute();
        $logged = trim((string)($lg->get_result()->fetch_assoc()['reason'] ?? ''));
        $lg->close();
        if ($logged !== '') $reason = $logged;
    }
} elseif ($status === 'Approved') {
    $reason = '';
}

$conn->close();

echo json_encode([
    'success'        => true,
    'reservation_id' => $reservation_id,
    'status'         => $status,
    'reason'         => $reason,
    'room_name'      => $room_name,
]);
