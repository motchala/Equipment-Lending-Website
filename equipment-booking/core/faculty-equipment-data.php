<?php
// ── Borrow Request modal data ─────────────────────────────────────────────
$bk_rooms  = BookingSchedule::roomsTree($conn, true);   // Available rooms only (no Maintenance / Not Bookable)
$bk_totals = BookingSchedule::totalStockMap($conn);
$bk_items  = [];
$bk_res    = $conn->query("SELECT item_name, quantity FROM tbl_inventory WHERE is_archived = 0");
while ($bk_res && ($bk_row = $bk_res->fetch_assoc())) {
    $bk_items[$bk_row['item_name']] = [
        'shelf' => (int)$bk_row['quantity'],
        'total' => (int)($bk_totals[$bk_row['item_name']] ?? $bk_row['quantity']),
    ];
}
$bk_mine = BookingSchedule::myUpcomingReservations($conn, (string)($_SESSION['faculty_id'] ?? ''));
$bk_meta_json = json_encode([
    'items'   => $bk_items,
    'rooms'   => $bk_rooms,
    'mine'    => $bk_mine,
    'today'   => date('Y-m-d'),
    'nowMin'  => (int)date('G') * 60 + (int)date('i'),
    'rules'   => [
        'dayStart'   => BookingSchedule::DAY_START,
        'dayEnd'     => BookingSchedule::DAY_END,
        'slot'       => BookingSchedule::SLOT_MINUTES,
        'maxAhead'   => BookingSchedule::MAX_ADVANCE_DAYS,
        'todayHours' => BookingSchedule::TODAY_HOURS,
        'futureDays' => BookingSchedule::FUTURE_DAYS,
        'graceMin'   => BookingSchedule::IMMEDIATE_GRACE_MIN,
    ],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
