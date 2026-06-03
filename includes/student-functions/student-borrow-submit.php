<?php
session_start();
if (!isset($_SESSION['student_auth']) || $_SESSION['student_auth']['action'] !== 'borrow') {
    header("Location: ../../student-portal.php");
    exit();
}

$auth = $_SESSION['student_auth'];

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) die("Connection failed");

// Sanitize
$equipment_name = mysqli_real_escape_string($conn, trim($_POST['equipment_name'] ?? ''));
$room = mysqli_real_escape_string($conn, trim($_POST['room'] ?? ''));
$borrow_date = mysqli_real_escape_string($conn, $_POST['borrow_date'] ?? '');
$return_date = mysqli_real_escape_string($conn, $_POST['return_date'] ?? '');

// Validation
$today = date('Y-m-d');
if ($borrow_date < $today) {
    die("Borrow date cannot be in the past.");
}
if ($return_date < $borrow_date) {
    die("Return date must be after borrow date.");
}

// Insert into tbl_requests
// faculty_name = student name, faculty_id = student id, authorized_by_faculty_id = the faculty who gave code
$stmt = $conn->prepare("INSERT INTO tbl_requests 
    (faculty_name, faculty_id, authorized_by_faculty_id, equipment_name, instructor, room, borrow_date, return_date, status, request_date) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Waiting', NOW())");

$student_name = $auth['student_name'];
$student_id = $auth['student_id'];
$faculty_id = $auth['faculty_id'];
$instructor = $student_name; // or fetch faculty name if you prefer

$stmt->bind_param("ssssssss", $student_name, $student_id, $faculty_id, $equipment_name, $instructor, $room, $borrow_date, $return_date);
$stmt->execute();
$stmt->close();

// Clear auth so they need a new code next time
unset($_SESSION['student_auth']);

header("Location: student-borrow.php?success=1");
exit();
?>