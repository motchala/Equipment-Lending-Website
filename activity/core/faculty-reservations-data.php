<?php
// ── Room Reservations for My Reservations history panel ───────────────────
$room_res_stmt = $conn->prepare(
    "SELECT rr.id, rr.room_id, rr.reservation_date, rr.start_time, rr.end_time,
            rr.purpose, rr.submitted_as, rr.status, rr.reason,
            rr.request_date,
            r.room_name,
            b.name AS building_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE rr.faculty_id = ?
      ORDER BY rr.request_date DESC"
);
$room_reservations = [];
if ($room_res_stmt) {
    $room_res_stmt->bind_param('s', $uid_safe);
    $room_res_stmt->execute();
    $room_res_result = $room_res_stmt->get_result();
    while ($row = $room_res_result->fetch_assoc()) {
        $room_reservations[] = $row;
    }
    $room_res_stmt->close();
}
