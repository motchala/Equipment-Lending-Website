<?php
// fix for csp vulnerability. nonce is generated per request and is unique.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$csp_nonce}' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

// return_confirm.php — Admin scans QR → marks equipment as Returned
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

// Activity log: make sure override_by_id exists (DDL) BEFORE the transaction starts.
require_once __DIR__ . '/../shared/core/activity-logger.php';
admin_activity_bootstrap($conn);
$acting_admin_id = admin_current_id($conn);

$token = trim($_GET['token'] ?? '');
$message = '';
$success = false;
$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

$ret_row = null;

if ($token) {
    $stmt = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name, status,
                return_token, borrow_qty, stock_applied, batch_id
           FROM tbl_requests
          WHERE return_token = ? AND status IN ('Approved','On Loan','Overdue')
          LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $ret_row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    // AJAX from QR scanner: return checklist data instead of auto-confirming
    if ($is_ajax) {
        if (!$ret_row) {
            echo json_encode(['success' => false, 'message' => 'Invalid or already-used QR token.']);
            exit();
        }

        $batch_id   = trim((string)($ret_row['batch_id'] ?? ''));
        $items_list = [];

        if ($batch_id !== '') {
            // Bundle: load ALL still-active items in this batch, not just the one
            // whose token was scanned. The faculty returns some or all at once.
            $bs = $conn->prepare(
                "SELECT id, equipment_name, borrow_qty, status, return_token
                   FROM tbl_requests
                  WHERE batch_id = ? AND status IN ('On Loan','Overdue')
                  ORDER BY id"
            );
            if ($bs) {
                $bs->bind_param('s', $batch_id);
                $bs->execute();
                $res = $bs->get_result();
                while ($r = $res->fetch_assoc()) {
                    $items_list[] = [
                        'id'             => (int)$r['id'],
                        'equipment_name' => (string)$r['equipment_name'],
                        'borrow_qty'     => (int)($r['borrow_qty'] ?? 1),
                        'status'         => (string)$r['status'],
                        'return_token'   => (string)$r['return_token'],
                    ];
                }
                $bs->close();
            }
        }

        // Fall back to single-item if not a bundle or bundle query returned nothing
        if (empty($items_list)) {
            $items_list[] = [
                'id'             => (int)$ret_row['id'],
                'equipment_name' => (string)$ret_row['equipment_name'],
                'borrow_qty'     => (int)($ret_row['borrow_qty'] ?? 1),
                'status'         => (string)$ret_row['status'],
                'return_token'   => (string)$ret_row['return_token'],
            ];
        }

        echo json_encode([
            'success'   => true,
            'checklist' => true,
            'token'     => $token,
            'borrower'  => $ret_row['faculty_name'],
            'items'     => $items_list,
        ]);
        exit();
    }
}

if ($is_ajax) {
    echo json_encode(['success' => false, 'message' => 'No token provided.']);
    exit();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Return Confirmation</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
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
    <div class="card" style="max-width:520px;text-align:left;">
        <?php if (!$ret_row): ?>
            <h2 style="color:#b71c1c;text-align:center;">Error</h2>
            <p style="text-align:center;color:#555;">Invalid or already-used return token.</p>
            <div style="text-align:center;"><a href="../admin-dashboard.php">Back to Dashboard</a></div>
        <?php else: ?>
            <div style="margin-bottom:1.25rem;">
                <span style="background:#e3f2fd;color:#1565c0;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;">Return Checklist</span>
                <h2 style="color:#1a1a1a;margin:.5rem 0 .25rem;font-size:1.2rem;">Return from <?php echo htmlspecialchars($ret_row['faculty_name']); ?></h2>
                <p style="margin:0;font-size:.85rem;color:#666;">Inspect the item and record its condition.</p>
            </div>
            <form method="POST" action="../equipment-booking/api/partial-return.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? csrf_token()); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;background:#fff;">
                        <div style="flex:1;">
                            <strong style="display:block;font-size:.95rem;color:#1a1a1a;"><?php echo htmlspecialchars($ret_row['equipment_name']); ?></strong>
                            <span style="font-size:.8rem;color:#888;">Qty: <?php echo (int)($ret_row['borrow_qty'] ?? 1); ?> · Status: <?php echo htmlspecialchars($ret_row['status']); ?></span>
                        </div>
                        <select name="condition[<?php echo (int)$ret_row['id']; ?>]"
                                style="padding:.45rem .75rem;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#fff;cursor:pointer;">
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Missing">Missing</option>
                        </select>
                    </div>
                </div>
                <p style="font-size:.8rem;color:#888;margin-bottom:1rem;">"Damaged" or "Missing" is flagged on the faculty's record and will block future borrows.</p>
                <button type="submit" style="width:100%;background:#600302;color:#fff;border:none;padding:.85rem;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;">
                    Confirm Return
                </button>
            </form>
            <div style="text-align:center;margin-top:.75rem;"><a href="../admin-dashboard.php" style="font-size:.85rem;color:#600302;">Cancel — Back to Dashboard</a></div>
        <?php endif; ?>
    </div>
</body>

</html>