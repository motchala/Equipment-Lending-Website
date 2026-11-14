<?php
declare(strict_types=1);
/**
 * equipment-booking/api/borrow-rules.php
 *
 * Read-only: the borrowing window the Super Admin set in Settings → Arbitration
 * (booking hours, how far ahead, hours offered). The Student Portal reads it so its
 * pickers match what the server will accept. Nothing sensitive is returned.
 *
 * Returns: { ok: true, rules: { dayStart, lastStart, dayEnd, slot, maxAhead, durationHours, graceMin, ..., roomMaxAdvanceDays } }
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/security-headers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../../room-reservation/core/room-rules.php';

try {
    $conn = getDB();
    BookingSchedule::loadRules($conn);
    RoomRules::load($conn);
    $rules = BookingSchedule::metaRules();
    $rules['roomMaxAdvanceDays'] = RoomRules::maxAdvanceDays();   // how far ahead a room can be reserved
    echo json_encode(['ok' => true, 'rules' => $rules]);
} catch (Throwable $e) {
    error_log('[borrow-rules] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load the borrowing rules.']);
}
