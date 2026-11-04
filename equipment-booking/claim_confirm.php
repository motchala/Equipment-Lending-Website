<?php
// fix for csp vulnerability. nonce is generated per request and is unique.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$csp_nonce}' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

// claim_confirm.php — Admin scans Claim QR → marks equipment as On Loan (physical handover confirmed)
require_once __DIR__ . '/../config/session.php';
date_default_timezone_set('Asia/Manila');

// Only accessible to logged-in admins
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../landing-page.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
$conn = getDB();
require_once __DIR__ . '/core/booking-schedule.php';
BookingSchedule::ensureSchema($conn);

// Activity log bootstrap
require_once __DIR__ . '/../shared/core/activity-logger.php';
admin_activity_bootstrap($conn);
$acting_admin_id = admin_current_id($conn);

$token   = trim($_GET['token'] ?? '');
$message = '';
$success = false;
$req_row = null;
$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if ($token) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT id, faculty_name, equipment_name, status, claim_token, borrow_qty,
                    submitted_by_name, submitted_by_id
               FROM tbl_requests
              WHERE claim_token = ? AND status = 'Approved'
              LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $message = 'Invalid or already-used claim token.';
        } else {
            $req_id  = (int)$row['id'];
            $eq_name = $row['equipment_name'];
            $borrower = $row['faculty_name'];

            // Mark as On Loan — physical handover confirmed
            $upd = $conn->prepare(
                "UPDATE tbl_requests SET status = 'On Loan', claimed_at = NOW(), claim_token = NULL WHERE id = ?"
            );
            $upd->bind_param('i', $req_id);
            $upd->execute();
            $upd->close();

            // Log it
            $admin_name = $_SESSION['admin_name'] ?? 'Admin';
            $log = $conn->prepare(
                "INSERT INTO tbl_arbitration_log
                 (request_id, borrower_id, borrower_name, equipment_name, decision, rule_applied, reason, override_by, override_by_id, override_reason, created_at)
                 VALUES (?, ?, ?, ?, 'On Loan', 'qr_claim', 'Equipment claimed via QR scan', ?, ?, 'QR Token Claim', NOW())
                 ON DUPLICATE KEY UPDATE decision='On Loan', rule_applied='qr_claim', override_by=VALUES(override_by), override_by_id=VALUES(override_by_id), created_at=NOW()"
            );
            $log->bind_param('issssi', $req_id, $borrower, $borrower, $eq_name, $admin_name, $acting_admin_id);
            $log->execute();
            $log->close();

            $conn->commit();
            admin_log_activity(
                $conn,
                'request',
                'Confirmed claim of ' . $eq_name . ' by ' . $borrower,
                null,
                ['action' => 'request_claimed', 'ref_type' => 'request', 'ref_id' => $req_id]
            );
            $req_row = $row;
            $collector_display = trim((string)($row['submitted_by_name'] ?? ''));
            $who = ($collector_display !== '')
                ? htmlspecialchars($collector_display) . ', on behalf of ' . htmlspecialchars($borrower)
                : htmlspecialchars($borrower);
            $success = true;
            $message = "Claim confirmed — {$eq_name} handed to {$who}.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log('[claim_confirm] ' . $e->getMessage());
        $message = 'Error processing claim. Please try again.';
    }
} else {
    $message = 'No token provided.';
}

// Ajax callers (the admin QR scanner) get JSON
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
    <title>Claim Confirmation</title>
    <link rel="icon" href="../shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="../shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="../shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="../shared/assets/images/apple-touch-icon.png">
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

        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            margin-bottom: 16px;
            background: #e8f5e9;
            color: #2e7d32;
        }

        h2 {
            color: <?php echo $success ? '#2e7d32' : '#b71c1c'; ?>;
            margin-bottom: 8px;
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
        <?php if ($success): ?>
            <span class="badge">✓ On Loan</span>
            <h2>Claim Confirmed</h2>
            <p><?php echo htmlspecialchars($message); ?></p>
            <p style="font-size:.82rem;color:#888;margin-top:8px;">The borrower now has the equipment. They will present a Return QR when done.</p>
        <?php else: ?>
            <h2>Error</h2>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <a href="../admin-dashboard.php">Back to Dashboard</a>
    </div>
</body>

</html>
