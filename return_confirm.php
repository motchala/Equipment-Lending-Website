<?php
// fix for csp vulnerability. nonce is generated per request and is unique.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$csp_nonce}' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");

// return_confirm.php — Admin scans QR → marks equipment as Returned
require_once __DIR__ . '/includes/session-config.php';
date_default_timezone_set('Asia/Manila');

// Only accessible to logged-in admins
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: landing-page.php");
    exit();
}

require_once __DIR__ . '/includes/db.php';
$conn = getDB();

$token = trim($_GET['token'] ?? '');
$message = '';
$success = false;
$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

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
            $message = "Return confirmed for {$row['equipment_name']} borrowed by {$row['faculty_name']}.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Error processing return. Please try again.';
    }
} else {
    $message = 'No token provided.';
}

// Ajax callers (the admin QR scanner) get JSON — no HTML overhead, no redirect issues
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Return Confirmation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style nonce="<?php echo $csp_nonce; ?>">
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
            color: <?php echo $success ? '#2e7d32' : '#b71c1c';
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