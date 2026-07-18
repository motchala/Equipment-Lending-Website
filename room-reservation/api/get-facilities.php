<?php
/**
 * room-reservation/api/get-facilities.php
 * Returns campus → building → room data as JSON for fcty-facilities.js.
 *
 * Depth: 2  (room-reservation/api/)
 * Config prefix: __DIR__ . '/../../config/…'
 *
 * Follows equipment-booking/api/ conventions exactly:
 *   1. security-headers.php first
 *   2. session.php
 *   3. Auth check → 401
 *   4. Content-Type: application/json
 *   5. db.php
 *   6. All DB queries use prepared statements
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// Must be logged in as faculty or admin
if (!isset($_SESSION['faculty_id']) && (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ── 1. Campuses ──────────────────────────────────────────────────────────
$campuses_result = $conn->query(
    "SELECT campus_id, campus_key, campus_name, description
     FROM tbl_campuses
     ORDER BY campus_id ASC"
);

if (!$campuses_result) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error fetching campuses']);
    exit();
}

$campuses = [];
while ($row = $campuses_result->fetch_assoc()) {
    $campuses[$row['campus_key']] = [
        'campus_id'   => (int) $row['campus_id'],
        'key'         => $row['campus_key'],
        'label'       => $row['campus_name'],
        'description' => $row['description'],
        'buildings'   => [],
    ];
}

// ── 2. Buildings ─────────────────────────────────────────────────────────
$buildings_result = $conn->query(
    "SELECT b.building_id, b.campus_id, b.building_key, b.name,
            b.wing, b.floor_count, b.icon, b.description, b.image_path,
            c.campus_key
     FROM tbl_buildings b
     JOIN tbl_campuses  c ON c.campus_id = b.campus_id
     ORDER BY b.campus_id ASC, b.sort_order ASC, b.building_id ASC"
);

if (!$buildings_result) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error fetching buildings']);
    exit();
}

// Map building_id → campus_key for room grouping below
$building_campus_map = [];

while ($row = $buildings_result->fetch_assoc()) {
    $campus_key  = $row['campus_key'];
    $building_id = (int) $row['building_id'];

    $building_campus_map[$building_id] = $campus_key;

    if (!isset($campuses[$campus_key])) continue;

    $campuses[$campus_key]['buildings'][$row['building_key']] = [
        'building_id' => $building_id,
        'id'          => $row['building_key'],          // matches JS building.id
        'name'        => $row['name'],
        'wing'        => $row['wing'] ?? '',
        'icon'        => $row['icon'] ?? 'domain',
        'desc'        => $row['description'] ?? '',
        'image'       => $row['image_path'] ?? '',
        'floors'      => (int) $row['floor_count'],
        'rooms'       => 0,                             // filled below from tbl_rooms
        'floor_data'  => [],                            // keyed by floor_number
    ];
}

// ── 3. Rooms grouped by building → floor ────────────────────────────────
$rooms_result = $conn->query(
    "SELECT r.room_id, r.building_id, r.room_name, r.floor_number,
            r.floor_label, r.seating_capacity, r.status, r.sort_order,
            b.building_key
     FROM tbl_rooms r
     JOIN tbl_buildings b ON b.building_id = r.building_id
     WHERE r.is_archived = 0
     ORDER BY r.building_id ASC, r.floor_number ASC,
              r.sort_order ASC, r.room_id ASC"
);

if (!$rooms_result) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error fetching rooms']);
    exit();
}

while ($row = $rooms_result->fetch_assoc()) {
    $building_id  = (int) $row['building_id'];
    $building_key = $row['building_key'];
    $campus_key   = $building_campus_map[$building_id] ?? null;

    if (!$campus_key || !isset($campuses[$campus_key]['buildings'][$building_key])) continue;

    $bref         = &$campuses[$campus_key]['buildings'][$building_key];
    $floor_num    = (int) $row['floor_number'];
    $floor_label  = !empty($row['floor_label']) ? $row['floor_label'] : _ordinal_floor($floor_num);

    if (!isset($bref['floor_data'][$floor_num])) {
        $bref['floor_data'][$floor_num] = [
            'label'    => $floor_label,
            'expanded' => $floor_num === 1,   // first floor open by default
            'rooms'    => [],
        ];
    }

    $bref['floor_data'][$floor_num]['rooms'][] = [
        'room_id'          => (int) $row['room_id'],
        'name'             => $row['room_name'],
        'status'           => $row['status'],
        'seating_capacity' => $row['seating_capacity'] !== null ? (int) $row['seating_capacity'] : null,
    ];

    $bref['rooms']++;
}

// ── 4. Flatten + clean output structure ─────────────────────────────────
// Convert associative floor_data map to a sorted array for JS consumption.

$output = [];
foreach ($campuses as $campus_key => $campus) {
    $campus_out = [
        'campus_id'   => $campus['campus_id'],
        'key'         => $campus['key'],
        'label'       => $campus['label'],
        'description' => $campus['description'],
        'buildings'   => [],
    ];

    foreach ($campus['buildings'] as $building_key => $building) {
        // Sort floors by floor_number (ksort on integer keys)
        ksort($building['floor_data']);
        $floors_array = array_values($building['floor_data']);

        $campus_out['buildings'][] = [
            'building_id' => $building['building_id'],
            'id'          => $building['id'],
            'name'        => $building['name'],
            'wing'        => $building['wing'],
            'icon'        => $building['icon'],
            'desc'        => $building['desc'],
            'image'       => $building['image'],
            'floors'      => $building['floors'],
            'rooms'       => $building['rooms'],
            'floor_data'  => $floors_array,
        ];
    }

    $output[] = $campus_out;
}

echo json_encode($output, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);

// ── Helper: ordinal floor label ──────────────────────────────────────────
function _ordinal_floor(int $n): string {
    if ($n === 1) return '1st Floor';
    if ($n === 2) return '2nd Floor';
    if ($n === 3) return '3rd Floor';
    return $n . 'th Floor';
}
