<?php
// ── Borrow Request modal data ─────────────────────────────────────────────
BookingSchedule::loadRules($conn);                      // Settings → Arbitration values for the rules below
$bk_rooms  = BookingSchedule::roomsTree($conn, true);   // Available rooms only (no Maintenance / Not Bookable)
$bk_totals = BookingSchedule::totalStockMap($conn);
$bk_items  = [];
// One entry per item NAME: the same name can exist once per campus, so the shelf quantities are added up
// (a plain "last row wins" made an item that is in stock on one campus look unavailable)
$bk_res    = $conn->query("SELECT item_name, SUM(quantity) AS quantity FROM tbl_inventory WHERE is_archived = 0 GROUP BY item_name");
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
    'rules'   => BookingSchedule::metaRules(),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
