<?php
/**
 * room-reservation/api/cancel-reservation.php
 * Cancel an Approved room reservation.
 *
 * Auth branching:
 *   Faculty session  → may only cancel their own reservation (ownership check)
 *   Admin session    → may cancel any Approved reservation (admin check)
 *
 * Both paths call RoomCancellation::cancel() — single code path for
 * the 1-hour cutoff, status UPDATE, and waitlist notification blast.
 * Admin cancellation additionally emails the reservation owner.
 *
 * POST body (JSON):
 *   { reservation_id: int, csrf_token: string }
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// Auth: must be faculty OR admin
$isFaculty = isset($_SESSION['faculty_id']);
$isAdmin   = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

if (!$isFaculty && !$isAdmin) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

$body = json_decode(file_get_contents('php://input'), true);

// CSRF verification
$token = $body['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token. Please refresh and try again.']);
    exit();
}

$reservationId = intval($body['reservation_id'] ?? 0);
if ($reservationId <= 0) {
    echo json_encode(['error' => 'Invalid reservation ID.']);
    exit();
}

$conn = getDB();

// ── Ownership / admin check ──────────────────────────────────────────────
if ($isFaculty && !$isAdmin) {
    // Faculty: verify they own this reservation
    $own = $conn->prepare(
        "SELECT id FROM tbl_room_reservations
          WHERE id = ? AND faculty_id = ? LIMIT 1"
    );
    $own->bind_param('is', $reservationId, $_SESSION['faculty_id']);
    $own->execute();
    $own->store_result();
    $ownsIt = $own->num_rows > 0;
    $own->close();

    if (!$ownsIt) {
        echo json_encode(['error' => 'You do not have permission to cancel this reservation.']);
        exit();
    }
}

$actor = $isAdmin ? 'admin' : 'faculty';

require_once __DIR__ . '/../core/room-cancellation.php';
$result = RoomCancellation::cancel($conn, $reservationId, $actor);

if (!$result['success']) {
    echo json_encode(['error' => $result['error']]);
    exit();
}

echo json_encode(['success' => true]);
