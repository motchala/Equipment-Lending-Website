<?php
session_start();
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$input_code = trim($_POST['faculty_code'] ?? '');
$student_name = trim($_POST['student_name'] ?? '');
$student_id = trim($_POST['student_id'] ?? '');
$action = $_POST['action'] ?? '';

if (!$input_code || !$student_name || !$student_id || !in_array($action, ['borrow', 'room'])) {
    echo json_encode(['success' => false, 'error' => 'Please fill in all fields.']);
    exit;
}

// Fetch all active codes (low volume, secure verification)
$result = mysqli_query($conn, "SELECT id, faculty_id, code_hash FROM tbl_faculty_codes WHERE status = 'active'");
$found = false;

while ($row = mysqli_fetch_assoc($result)) {
    if (password_verify($input_code, $row['code_hash'])) {
        // Consume the code immediately (one-time-use)
        $stmt = $conn->prepare("UPDATE tbl_faculty_codes 
            SET status = 'used', 
                used_at = NOW(), 
                used_by_student_id = ?, 
                used_by_student_name = ?, 
                used_for_action = ?,
                ip_address = ?
            WHERE id = ?");
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->bind_param("ssssi", $student_id, $student_name, $action, $ip, $row['id']);
        $stmt->execute();
        $stmt->close();

        // Store student auth session for next steps
        $_SESSION['student_auth'] = [
            'faculty_id' => $row['faculty_id'],
            'student_name' => $student_name,
            'student_id' => $student_id,
            'action' => $action,
            'verified_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode(['success' => true, 'redirect' => $action === 'borrow' ? 'student-borrow.php' : 'student-room.php']);
        $found = true;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Invalid or already used faculty code.']);
}