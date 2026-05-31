<?php
// return_confirm.php — Admin scans QR → marks equipment as Returned
session_start();
date_default_timezone_set('Asia/Manila');

// Only accessible to logged-in admins
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: landing-page.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'lending_db');
if ($conn->connect_error) die("DB error.");

$token = trim($_GET['token'] ?? '');
$message = '';
$success = false;

if ($token) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT id, faculty_name, equipment_name, status, return_token
               FROM tbl_requests
              WHERE return_token = ? AND status IN ('Approved','Overdue')
              LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $message = 'Invalid or already-used QR token.';
        } else {
            $req_id   = (int)$row['id'];
            $eq_name  = $row['equipment_name'];

            // Mark as Returned
            $upd = $conn->prepare(
                "UPDATE tbl_requests SET status = 'Returned', returned_at = NOW(), return_token = NULL WHERE id = ?"
            );
            $upd->bind_param('i', $req_id);
            $upd->execute();
            $upd->close();

            // Restore inventory
            $inc = $conn->prepare(
                "UPDATE tbl_inventory SET quantity = quantity + 1 WHERE item_name = ?"
            );
            $inc->bind_param('s', $eq_name);
            $inc->execute();
            $inc->close();

            // Log it
            $admin_name = $_SESSION['admin_name'] ?? 'Admin';
            $log = $conn->prepare(
                "INSERT INTO tbl_arbitration_log
                 (request_id, borrower_id, borrower_name, equipment_name, decision, rule_applied, reason, override_by, override_reason, created_at)
                 VALUES (?, ?, ?, ?, 'Returned', 'qr_return', 'Equipment returned via QR scan', ?, 'QR Token Return', NOW())
                 ON DUPLICATE KEY UPDATE decision='Returned', rule_applied='qr_return', override_by=VALUES(override_by), created_at=NOW()"
            );
            $log->bind_param('issss', $req_id, $row['faculty_name'], $row['faculty_name'], $eq_name, $admin_name);
            $log->execute();
            $log->close();

            $conn->commit();
            $success = true;
            $message = "✅ Return confirmed for <strong>" . htmlspecialchars($eq_name) . "</strong> borrowed by <strong>" . htmlspecialchars($row['faculty_name']) . "</strong>.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Error processing return. Please try again.';
    }
} else {
    $message = 'No token provided.';
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Return Confirmation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Inter, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f0f0;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: <?php echo $success ? '#2e7d32': '#b71c1c';
            ?>;
            margin-bottom: 12px;
        }

        p {
            color: #555;
            line-height: 1.6;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 28px;
            background: #600302;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>
            <?php echo $success ? 'Return Confirmed' : 'Error'; ?>
        </h2>
        <p>
            <?php echo $message; ?>
        </p>
        <a href="admin-dashboard.php">Back to Dashboard</a>
    </div>
</body>

</html>