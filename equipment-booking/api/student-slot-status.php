<?php

declare(strict_types=1);

/**
 * equipment-booking/api/student-slot-status.php
 *
 * Lets the Student Portal warn the student BEFORE they finish the "Where and when" form: does the faculty
 * member who gave them the code still have a student slot (and shared points) for the day and time they
 * picked? Read-only; the real check still happens when the request is submitted.
 *
 * The portal has no login, so, like verify-faculty-code.php, the student's own unused code entitles them to
 * ask, and its row id (code_db_id) must come back with the question.
 *
 * POST (JSON): { "code_db_id": 12, "date": "2026-11-25", "start": "09:00", "duration": 2 }
 * Reply:       { "success": true, "ok": true|false, "message": "" }
 *   message is the same student-facing text the submit would give; "" when there is room.
 *   A code that is used or unknown is answered with ok = true: the submit step reports that properly.
 *
 * No session is opened on purpose: a session lock would make quick date changes wait for each other.
 */

require_once __DIR__ . '/../../config/security-headers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$body     = json_decode((string)file_get_contents('php://input'), true);
$body     = is_array($body) ? $body : [];
$codeId   = (int)($body['code_db_id'] ?? 0);
$date     = trim((string)($body['date'] ?? ''));
$start    = trim((string)($body['start'] ?? ''));
$duration = (int)($body['duration'] ?? 0);

if ($codeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)
    || $duration < 1 || $duration > 12 || strtotime($date) === false) {
    http_response_code(422);
    echo json_encode(['error' => 'Choose a date, a start time and how long first.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';

try {
    $conn = getDB();
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare('SELECT faculty_id, is_used FROM tbl_faculty_codes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $codeId);
    $stmt->execute();
    $code = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $message = '';
    if ($code && !(int)$code['is_used']) {
        $startTs = $date . ' ' . $start . ':00';
        $endTs   = date('Y-m-d H:i:s', strtotime($startTs) + $duration * 3600);
        $message = BookingSchedule::studentSlotStatus($conn, (string)$code['faculty_id'], $startTs, $endTs)['message'];
    }
    $conn->close();

    echo json_encode(['success' => true, 'ok' => $message === '', 'message' => $message]);
} catch (Throwable $e) {
    error_log('[student-slot-status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not check that right now.']);
}
