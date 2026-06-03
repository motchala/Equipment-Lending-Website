<?php
session_start();
if (!isset($_SESSION['student_auth']) || $_SESSION['student_auth']['action'] !== 'borrow') {
    header("Location: student-portal.php");
    exit();
}
$auth = $_SESSION['student_auth'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Borrow Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h2>Borrow Equipment</h2>
        <p>Authorized by Faculty: <strong><?php echo htmlspecialchars($auth['faculty_id']); ?></strong></p>
        <p>Student: <strong><?php echo htmlspecialchars($auth['student_name']); ?></strong> (<?php echo htmlspecialchars($auth['student_id']); ?>)</p>
        <a href="student-portal.php" class="btn btn-secondary">Back</a>
    </div>
</body>
</html>