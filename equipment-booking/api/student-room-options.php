<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../shared/core/student-room-options.php';

try {
    $conn = getDB();
    $conn->set_charset('utf8mb4');
    $rooms = student_room_options($conn);
    $conn->close();
    echo json_encode(['rooms' => $rooms], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} catch (Throwable $e) {
    error_log('[student-room-options] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load available rooms. Please try again.']);
}
