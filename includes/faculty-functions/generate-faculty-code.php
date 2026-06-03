<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['faculty_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

// Expire any existing active codes for this faculty
$stmt = $conn->prepare("UPDATE tbl_faculty_codes SET status = 'expired' WHERE faculty_id = ? AND status = 'active'");
$stmt->bind_param("s", $faculty_id);
$stmt->execute();
$stmt->close();

// Generate cryptographically secure code (48 hex chars = 192 bits entropy)
$code = bin2hex(random_bytes(24));
$hash = password_hash($code, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO tbl_faculty_codes (faculty_id, code_hash, status, created_at) VALUES (?, ?, 'active', NOW())");
$stmt->bind_param("ss", $faculty_id, $hash);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true, 'code' => $code]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to store code']);
}