<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$body = json_decode(file_get_contents('php://input'), true);

$code_db_id    = intval($body['code_db_id']    ?? 0);
$faculty_id    = trim($body['faculty_id']      ?? '');
$faculty_name  = trim($body['faculty_name']    ?? '');
$student_name  = trim($body['student_name']    ?? '');
$student_id    = trim($body['student_id']      ?? '');
$equipment     = trim($body['equipment_name']  ?? '');
$room          = trim($body['room']            ?? '');
$borrow_date   = trim($body['borrow_date']     ?? '');
$return_date   = trim($body['return_date']     ?? '');

if (!$code_db_id || !$faculty_id || !$student_name || !$student_id ||
    !$equipment || !$room || !$borrow_date || !$return_date) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

$today = date('Y-m-d');
if ($borrow_date < $today)       { echo json_encode(['error' => 'Borrow date cannot be in the past.']);         exit(); }
if ($return_date < $borrow_date) { echo json_encode(['error' => 'Return date cannot be before borrow date.']); exit(); }

$conn = new mysqli('localhost', 'root', '', 'lending_db');
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// ── Re-verify code is still unused (race-condition guard) ─────────────────
$chk = $conn->prepare("SELECT id FROM tbl_faculty_codes WHERE id = ? AND is_used = 0 LIMIT 1");
$chk->bind_param('i', $code_db_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close(); $conn->close();
    echo json_encode(['error' => 'This code was just used by someone else. Ask your faculty for a new code.']);
    exit();
}
$chk->close();

// ── Check equipment is still in stock ────────────────────────────────────
$stock = $conn->prepare(
    "SELECT quantity FROM tbl_inventory WHERE item_name = ? AND is_archived = 0 LIMIT 1"
);
$stock->bind_param('s', $equipment);
$stock->execute();
$stock_row = $stock->get_result()->fetch_assoc();
$stock->close();

if (!$stock_row || $stock_row['quantity'] < 1) {
    $conn->close();
    echo json_encode(['error' => 'Sorry, that item just went out of stock. Please go back and choose another.']);
    exit();
}

// ── Generate return token ─────────────────────────────────────────────────
$return_token = bin2hex(random_bytes(32));

// ── Insert as Approved (faculty code is the authorization) ────────────────
$ins = $conn->prepare(
    "INSERT INTO tbl_requests
        (faculty_name, faculty_id, equipment_name, instructor, room,
         borrow_date, return_date, status, request_date,
         return_token, submitted_by_name, submitted_by_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'Approved', NOW(), ?, ?, ?)"
);
$ins->bind_param(
    'ssssssssss',
    $faculty_name, $faculty_id, $equipment, $faculty_name, $room,
    $borrow_date, $return_date,
    $return_token, $student_name, $student_id
);

if (!$ins->execute()) {
    $ins->close(); $conn->close();
    echo json_encode(['error' => 'Failed to save request. Please try again.']);
    exit();
}
$request_id = $conn->insert_id;
$ins->close();

// ── Decrement inventory quantity ──────────────────────────────────────────
$dec = $conn->prepare(
    "UPDATE tbl_inventory SET quantity = quantity - 1
      WHERE item_name = ? AND is_archived = 0 AND quantity > 0"
);
$dec->bind_param('s', $equipment);
$dec->execute();
$dec->close();

// ── Mark code as used ─────────────────────────────────────────────────────
$upd = $conn->prepare(
    "UPDATE tbl_faculty_codes
        SET is_used = 1, used_by_name = ?, used_by_id = ?, used_at = NOW()
      WHERE id = ?"
);
$upd->bind_param('ssi', $student_name, $student_id, $code_db_id);
$upd->execute();
$upd->close();
$conn->close();

echo json_encode(['success' => true, 'request_id' => $request_id]);