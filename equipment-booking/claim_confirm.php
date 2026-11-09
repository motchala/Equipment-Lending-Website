<?php
// claim_confirm.php — Admin scans Claim QR → shows per-item checklist for handover
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$csp_nonce}' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

require_once __DIR__ . '/../config/session.php';
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../landing-page.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
$conn = getDB();
require_once __DIR__ . '/core/booking-schedule.php';
BookingSchedule::ensureSchema($conn);
require_once __DIR__ . '/../shared/core/activity-logger.php';
admin_activity_bootstrap($conn);

$token    = trim($_GET['token'] ?? '');
$is_ajax  = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$items    = [];
$borrower = '';
$collector = '';

if ($token !== '') {
    // ── Try bundle token first ────────────────────────────────────────────
    $s = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name,
                bundle_claim_token, borrow_qty, submitted_by_name, submitted_by_id
           FROM tbl_requests
          WHERE bundle_claim_token = ? AND status = 'Approved'
          ORDER BY id"
    );
    if ($s) {
        $s->bind_param('s', $token);
        $s->execute();
        $res = $s->get_result();
        while ($r = $res->fetch_assoc()) $items[] = $r;
        $s->close();
    }

    // ── Fall back to individual claim token ───────────────────────────────
    if (empty($items)) {
        $s = $conn->prepare(
            "SELECT id, faculty_id, faculty_name, equipment_name,
                    claim_token, borrow_qty, submitted_by_name, submitted_by_id
               FROM tbl_requests
              WHERE claim_token = ? AND status = 'Approved'
              LIMIT 1"
        );
        if ($s) {
            $s->bind_param('s', $token);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $s->close();
            if ($row) $items[] = $row;
        }
    }

    if (!empty($items)) {
        $borrower  = (string)$items[0]['faculty_name'];
        $collector = trim((string)($items[0]['submitted_by_name'] ?? ''));
    }
}

// ── AJAX from QR scanner: return checklist data as JSON ──────────────────────
if ($is_ajax) {
    header('Content-Type: application/json');
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or already-used claim token.']);
    } else {
        echo json_encode([
            'success'   => true,
            'checklist' => true,
            'token'     => $token,
            'borrower'  => $borrower,
            'collector' => $collector,
            'items'     => array_map(fn($r) => [
                'id'             => (int)$r['id'],
                'equipment_name' => $r['equipment_name'],
                'borrow_qty'     => (int)($r['borrow_qty'] ?? 1),
            ], $items),
        ]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Checklist — PUPSync</title>
    <link rel="icon" href="../shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="../shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="../shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="../shared/assets/images/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style nonce="<?= $csp_nonce ?>">
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Inter, sans-serif;
            background: #f5f0f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 540px;
            width: 92%;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
        }
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .badge--green  { background: #e8f5e9; color: #2e7d32; }
        .badge--blue   { background: #e3f2fd; color: #1565c0; }
        .badge--red    { background: #fdecea; color: #b71c1c; }
        h2 { font-size: 1.15rem; color: #1a1a1a; margin-bottom: .3rem; }
        .sub { font-size: .84rem; color: #666; margin-bottom: 1.25rem; }
        .item-list {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .item-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .8rem 1rem;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
        }
        .item-row:last-child { border-bottom: none; }
        .item-row:nth-child(even) { background: #fafafa; }
        .item-row input[type=checkbox] {
            width: 18px; height: 18px;
            accent-color: #800000;
            flex-shrink: 0;
            cursor: pointer;
        }
        .item-info { flex: 1; }
        .item-name { font-size: .94rem; font-weight: 600; color: #1a1a1a; }
        .item-qty  { font-size: .78rem; color: #888; margin-top: 1px; }
        .item-chip {
            font-size: .75rem;
            background: #fff3cd;
            color: #856404;
            padding: 2px 10px;
            border-radius: 12px;
            white-space: nowrap;
        }
        .hint { font-size: .8rem; color: #888; margin-bottom: 1.25rem; }
        .btn-primary {
            width: 100%;
            background: #600302;
            color: #fff;
            border: none;
            padding: .85rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-primary:hover { background: #800000; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: .75rem;
            font-size: .84rem;
            color: #600302;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .error-state { text-align: center; }
        .error-state h2 { color: #b71c1c; margin-bottom: .5rem; }
        .error-state p  { color: #555; margin-bottom: 1.25rem; }
    </style>
</head>
<body>
<div class="card">
<?php if (empty($items)): ?>
    <div class="error-state">
        <span class="badge badge--red">Already Claimed</span>
        <h2>Nothing left to claim</h2>
        <p>All items linked to this QR code have already been handed over, or this code is no longer valid.</p>
        <p style="font-size:.82rem;color:#aaa;margin-top:.5rem;">If some items are still pending, ask the faculty to open My Activity — each remaining item will show its own Claim QR.</p>
        <a href="../admin-dashboard.php" class="btn-primary" style="display:inline-block;width:auto;padding:.7rem 2rem;text-decoration:none;margin-top:1rem;">Back to Dashboard</a>
    </div>
<?php else: ?>
    <span class="badge badge--green">Claim Checklist</span>
    <h2>Hand over to <?= htmlspecialchars($collector ?: $borrower) ?></h2>
    <p class="sub"><?= $collector ? 'On behalf of ' . htmlspecialchars($borrower) . ' · ' : '' ?>Tick every item you are physically handing over right now. Unticked items stay <em>Approved</em> and can be claimed later.</p>

    <form method="POST" action="../equipment-booking/api/partial-claim.php">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="item-list">
            <?php foreach ($items as $item): ?>
            <label class="item-row">
                <input type="checkbox" name="hand_over[]"
                       value="<?= (int)$item['id'] ?>" checked>
                <div class="item-info">
                    <div class="item-name"><?= htmlspecialchars($item['equipment_name']) ?></div>
                    <div class="item-qty">Qty: <?= (int)($item['borrow_qty'] ?? 1) ?></div>
                </div>
                <span class="item-chip">Approved</span>
            </label>
            <?php endforeach; ?>
        </div>

        <p class="hint">Unticked items will remain <em>Approved</em> — claim them later or cancel individually from the dashboard.</p>

        <button type="submit" class="btn-primary">Confirm Handover</button>
    </form>
    <a href="../admin-dashboard.php" class="back-link">Cancel — Back to Dashboard</a>
<?php endif; ?>
</div>
</body>
</html>
