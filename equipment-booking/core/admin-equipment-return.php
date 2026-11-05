<?php
// ── CONFIRM RETURN ─────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'return_confirm' && isset($_GET['id'])) {
    date_default_timezone_set('Asia/Manila');
    $req_id = intval($_GET['id']);
    $is_ajax_rc = ($_GET['ajax'] ?? '') === '1';
    if ($is_ajax_rc) {
        header('Content-Type: application/json');
    }
    require_once PS_ROOT . '/equipment-booking/core/booking-schedule.php';
    BookingSchedule::ensureSchema($conn);
    $res = $conn->query("SELECT equipment_name, borrow_qty, stock_applied FROM tbl_requests WHERE id = $req_id LIMIT 1");
    if ($res && $row_rc = $res->fetch_assoc()) {
        $returned_at = date('Y-m-d H:i:s');
        $upd = $conn->prepare("UPDATE tbl_requests SET status = 'Returned', return_token = NULL, returned_at = ? WHERE id = ?");
        $upd->bind_param('si', $returned_at, $req_id);
        $upd->execute();
        $upd->close();
        $eq = $conn->real_escape_string($row_rc['equipment_name']);
        // only units that actually left the shelf come back (see BookingSchedule)
        $ret_qty = ((int)($row_rc['stock_applied'] ?? 1) === 1) ? max(1, (int)($row_rc['borrow_qty'] ?? 1)) : 0;
        if ($ret_qty > 0) {
            $conn->query("UPDATE tbl_inventory SET quantity = quantity + $ret_qty WHERE item_name = '$eq'");
        }
        if ($is_ajax_rc) {
            echo json_encode(['status' => 'success', 'message' => 'Return confirmed.']);
            exit();
        }
    } else {
        if ($is_ajax_rc) {
            echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
            exit();
        }
    }
    header("Location: admin-dashboard.php?view=return-confirmation");
    exit();
}
