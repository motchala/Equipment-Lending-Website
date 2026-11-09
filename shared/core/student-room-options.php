<?php

declare(strict_types=1);

/**
 * Available rooms for the public student equipment-borrowing flow.
 *
 * @return list<array{
 *   room_id:int, room_name:string, floor_label:string, building_name:string,
 *   building_key:string, building_image:string, floor_number:int,
 *   seating_capacity:?int, status:string
 * }>
 */
function student_room_options(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label,
                r.seating_capacity, r.status, b.name AS building_name,
                b.building_key, b.image_path AS building_image
           FROM tbl_rooms r
           JOIN tbl_buildings b ON b.building_id = r.building_id
          WHERE r.is_archived = 0 AND r.status = 'Available'
          ORDER BY b.name ASC, r.floor_number ASC, r.sort_order ASC"
    );
    if (!$result) {
        throw new RuntimeException('Could not load available rooms.');
    }

    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $floorNumber = (int)$row['floor_number'];
        $floorLabel = trim((string)($row['floor_label'] ?? ''));
        if ($floorLabel === '') {
            $floorLabel = match ($floorNumber) {
                1 => '1st Floor',
                2 => '2nd Floor',
                3 => '3rd Floor',
                default => $floorNumber . 'th Floor',
            };
        }
        $rooms[] = [
            'room_id' => (int)$row['room_id'],
            'room_name' => (string)$row['room_name'],
            'floor_label' => $floorLabel,
            'building_name' => (string)$row['building_name'],
            'building_key' => (string)$row['building_key'],
            'building_image' => (string)($row['building_image'] ?? ''),
            'floor_number' => $floorNumber,
            'seating_capacity' => $row['seating_capacity'] !== null ? (int)$row['seating_capacity'] : null,
            'status' => (string)$row['status'],
        ];
    }
    $result->free();
    return $rooms;
}

/**
 * Resolve a selected room against the current available-room list.
 *
 * @return array{room_id:int, room_name:string}|null
 */
function student_room_find(mysqli $conn, int $roomId): ?array
{
    if ($roomId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT room_id, room_name
           FROM tbl_rooms
          WHERE room_id = ? AND is_archived = 0 AND status = 'Available'
          LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not verify the selected room.');
    }
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$room) {
        return null;
    }
    return ['room_id' => (int)$room['room_id'], 'room_name' => (string)$room['room_name']];
}
