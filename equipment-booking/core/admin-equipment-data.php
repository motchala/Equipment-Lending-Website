<?php
// ================= FETCH ALL REQUESTS =================

// Supporting-letter requests from Organization Advisers are reviewed only by
// a Super Admin.  The session role was refreshed by admin_session_guard().
$can_view_sensitive_adviser_requests = (($_SESSION['admin_role'] ?? '') === 'Super Admin');
$sensitive_adviser_request_sql = "(tbl_requests.submitted_as = 'adviser' AND COALESCE(tbl_requests.document_path, '') <> '')";

$waiting_sql = "SELECT tbl_requests.*, tbl_inventory.`condition` AS item_condition
                FROM tbl_requests
                LEFT JOIN tbl_inventory ON tbl_inventory.item_name = tbl_requests.equipment_name
                WHERE tbl_requests.status='Waiting'";

if (!$can_view_sensitive_adviser_requests) {
    $waiting_sql .= " AND NOT {$sensitive_adviser_request_sql}";
}

if (!empty($_GET['waiting_search'])) {
    $search = "%" . $_GET['waiting_search'] . "%";
    $waiting_sql .= " AND (
        faculty_id LIKE ?
        OR faculty_name LIKE ?
        OR equipment_name LIKE ?
    ) ORDER BY request_date DESC";

    $stmt = $conn->prepare($waiting_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $waiting_result = $stmt->get_result();
} else {
    $waiting_sql .= " ORDER BY request_date DESC";
    $waiting_result = mysqli_query($conn, $waiting_sql);
}


$approved_sql = "SELECT tbl_requests.*, tbl_inventory.`condition` AS item_condition
                  FROM tbl_requests
                  LEFT JOIN tbl_inventory ON tbl_inventory.item_name = tbl_requests.equipment_name
                  WHERE tbl_requests.status IN ('Approved','Overdue')";
if (!empty($_GET['approved_search'])) {
    $search = "%" . $_GET['approved_search'] . "%";
    $approved_sql .= " AND (
        faculty_id LIKE ?
        OR faculty_name LIKE ?
        OR equipment_name LIKE ?
    ) ORDER BY request_date DESC";
    $stmt = $conn->prepare($approved_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $approved_result = $stmt->get_result();
} else {
    $approved_sql .= " ORDER BY request_date DESC";
    $approved_result = mysqli_query($conn, $approved_sql);
}


// Auto-declined requests are not shown; only an admin's own manual decline (arbitration_rule='override') is.
$declined_sql = "SELECT * FROM tbl_requests WHERE status='Declined' AND COALESCE(arbitration_rule,'')='override'";
if (!empty($_GET['declined_search'])) {
    $search = "%" . $_GET['declined_search'] . "%";
    $declined_sql .= " AND (
        faculty_id LIKE ?
        OR faculty_name LIKE ?
        OR equipment_name LIKE ?
    ) ORDER BY request_date DESC";
    $stmt = $conn->prepare($declined_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $declined_result = $stmt->get_result();
} else {
    $declined_sql .= " ORDER BY request_date DESC";
    $declined_result = mysqli_query($conn, $declined_sql);
}


$overdue_sql = "SELECT * FROM tbl_requests WHERE status='Overdue'";
$overdue_sql .= " ORDER BY return_date ASC";
$overdue_result = mysqli_query($conn, $overdue_sql);


$inventory_sql = "SELECT * FROM tbl_inventory WHERE is_archived = 0";

if (!empty($_GET['inventory_search'])) {
    $search = "%" . $_GET['inventory_search'] . "%";
    $inventory_sql .= "
        AND (item_name LIKE ? OR category LIKE ?)
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($inventory_sql);
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $inventory_result = $stmt->get_result();
} else {
    $inventory_sql .= " ORDER BY created_at DESC";
    $inventory_result = mysqli_query($conn, $inventory_sql);
}


$archive_sql = "
    SELECT * FROM tbl_inventory
    WHERE is_archived = 1
    ORDER BY item_name ASC
";
$archive_result = mysqli_query($conn, $archive_sql);


$raw_data_sql = "SELECT faculty_id, faculty_name, equipment_name, instructor, room, borrow_date, return_date, request_date FROM tbl_requests WHERE NOT (status='Declined' AND COALESCE(arbitration_rule,'')<>'override') AND status <> 'Cancelled'";

if (!empty($_GET['raw_search'])) {
    $search = "%" . $_GET['raw_search'] . "%";
    $raw_data_sql .= " WHERE faculty_name LIKE ? OR equipment_name LIKE ? OR faculty_id LIKE ? ORDER BY request_date DESC";
    $stmt = $conn->prepare($raw_data_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $raw_data_result = $stmt->get_result();
} else {
    $raw_data_sql .= " ORDER BY request_date DESC";
    $raw_data_result = mysqli_query($conn, $raw_data_sql);
}


// ================= STATS =================

$stat_waiting   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Waiting'"))['c'];
$stat_approved  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Approved'"))['c'];
$stat_declined  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Declined' AND COALESCE(arbitration_rule,'')='override'"))['c'];
$stat_overdue   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Overdue'"))['c'];
$stat_inv_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_inventory WHERE is_archived=0"))['c'];
$stat_inv_low   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_inventory WHERE quantity<=2 AND is_archived=0"))['c'];
$stat_total_req = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE NOT (status='Declined' AND COALESCE(arbitration_rule,'')<>'override') AND status <> 'Cancelled'"))['c'];


// ================= EDIT ITEM FETCH =================

$edit_item = null;

if (isset($_GET['edit_item'])) {
    $edit_id = intval($_GET['edit_item']);
    $edit_query = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE item_id=$edit_id");
    $edit_item = mysqli_fetch_assoc($edit_query);
}

