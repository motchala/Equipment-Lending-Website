<?php
/**
 * equipment-booking/api/manual-claim.php
 *
 * Record an administrator-verified equipment handover without a QR claim token.
 * Mirrors the behaviour of partial-claim.php but takes a plain request_id
 * instead of a bundle_claim_token / claim_token.
 *
 * POST params:
 *   request_id      int     — the tbl_requests row to claim
 *   give_qty        int     — units actually handed over (1..borrow_qty)
 *   withheld_cond   string  — condition of withheld units: Good|Damaged|Missing
 *   withheld_reason string  — required when withheld_cond === 'Good'
 *   csrf_token      string
 *
 * Response JSON:
 *   { status: 'success'|'error', message: string }
 */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');

function manual_claim_reply(int $code, string $status, string $message): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    manual_claim_reply(405, 'error', 'POST is required.');
}

require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    manual_claim_reply(401, 'error', 'Admin access is required.');
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    manual_claim_reply(403, 'error', 'Invalid or expired session. Please refresh and try again.');
}

$rawId = $_POST['request_id'] ?? '';
if (!is_scalar($rawId) || !ctype_digit((string)$rawId) || (int)$rawId < 1) {
    manual_claim_reply(400, 'error', 'Invalid loan record.');
}
$requestId    = (int)$rawId;
$giveQty      = max(0, (int)($_POST['give_qty'] ?? 1));
$withheldCond = trim((string)($_POST['withheld_cond'] ?? 'Good'));
$withheldReason = trim((string)($_POST['withheld_reason'] ?? ''));

if (!in_array($withheldCond, ['Good', 'Damaged', 'Missing'], true)) {
    manual_claim_reply(400, 'error', 'Invalid condition value.');
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';

$conn = getDB();
$conn->set_charset('utf8mb4');
BookingSchedule::ensureSchema($conn);
admin_activity_bootstrap($conn);
$adminName = (string)($_SESSION['admin_name'] ?? 'Admin');
$adminId   = admin_current_id($conn);

try {
    $conn->begin_transaction();

    // Lock the row
    $find = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name, borrow_qty, stock_applied, status
           FROM tbl_requests
          WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    if (!$find) throw new RuntimeException('Could not read the loan record.');
    $find->bind_param('i', $requestId);
    $find->execute();
    $loan = $find->get_result()->fetch_assoc();
    $find->close();

    if (!$loan) {
        $conn->rollback();
        manual_claim_reply(404, 'error', 'Loan record not found.');
    }
    if ($loan['status'] !== 'Approved') {
        $conn->rollback();
        manual_claim_reply(409, 'error', 'This item has already been claimed or is no longer awaiting pickup.');
    }

    $maxQty   = max(1, (int)$loan['borrow_qty']);
    $giveQty  = min($giveQty, $maxQty);
    $keepQty  = $maxQty - $giveQty;
    $claimedAt = date('Y-m-d H:i:s');
    $stockApplied = (int)($loan['stock_applied'] ?? 1);

    if ($giveQty > 0) {
        // ── Units handed over → On Loan ───────────────────────────────────
        $upd = $conn->prepare(
            "UPDATE tbl_requests
                SET status = 'On Loan', claimed_at = ?,
                    claim_token = NULL, bundle_claim_token = NULL,
                    stock_applied = 1, borrow_qty = ?
              WHERE id = ? AND status = 'Approved'"
        );
        if (!$upd) throw new RuntimeException('Could not update the loan record.');
        $upd->bind_param('sii', $claimedAt, $giveQty, $requestId);
        $upd->execute();
        if ($upd->affected_rows !== 1) {
            $upd->close();
            $conn->rollback();
            manual_claim_reply(409, 'error', 'This item changed status before the claim could be recorded.');
        }
        $upd->close();

        if ($stockApplied === 0) {
            // Stock not yet decremented — take only given qty
            $dec = $conn->prepare(
                'UPDATE tbl_inventory SET quantity = GREATEST(quantity - ?, 0) WHERE item_name = ?'
            );
            if ($dec) { $dec->bind_param('is', $giveQty, $loan['equipment_name']); $dec->execute(); $dec->close(); }
        } elseif ($keepQty > 0) {
            // Stock was taken for full qty — return the withheld portion
            $inc = $conn->prepare(
                'UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?'
            );
            if ($inc) { $inc->bind_param('is', $keepQty, $loan['equipment_name']); $inc->execute(); $inc->close(); }
        }

        // If withheld units have a bad condition, flag the catalog item
        if ($keepQty > 0 && in_array($withheldCond, ['Damaged', 'Missing'], true)) {
            $cond = $conn->prepare(
                "UPDATE tbl_inventory SET `condition` = ? WHERE item_name = ? LIMIT 1"
            );
            if ($cond) { $cond->bind_param('ss', $withheldCond, $loan['equipment_name']); $cond->execute(); $cond->close(); }
        }
    } else {
        // ── 0 units handed over → Declined ───────────────────────────────
        $reason = $withheldReason !== '' ? $withheldReason : 'No units available at handover (manual claim).';
        $upd = $conn->prepare(
            "UPDATE tbl_requests
                SET status = 'Declined', arbitration_rule = 'claim_zero',
                    reason = ?,
                    claim_token = NULL, bundle_claim_token = NULL
              WHERE id = ? AND status = 'Approved'"
        );
        if (!$upd) throw new RuntimeException('Could not decline the loan record.');
        $upd->bind_param('si', $reason, $requestId);
        $upd->execute();
        if ($upd->affected_rows !== 1) {
            $upd->close();
            $conn->rollback();
            manual_claim_reply(409, 'error', 'This item changed status before the claim could be recorded.');
        }
        $upd->close();

        // Return the reserved stock if it was decremented
        if ($stockApplied === 1) {
            $inc = $conn->prepare(
                'UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?'
            );
            if ($inc) { $inc->bind_param('is', $maxQty, $loan['equipment_name']); $inc->execute(); $inc->close(); }
        }
    }

    // Log to arbitration log
    $decision  = $giveQty > 0 ? 'Approved' : 'Declined';
    $logReason = $giveQty > 0
        ? 'Equipment handed over manually by admin (no QR).' . ($keepQty > 0 ? ' ' . $keepQty . ' unit(s) withheld (' . $withheldCond . ').' : '')
        : 'Equipment not handed over at pickup — 0 units given.';
    $log = $conn->prepare(
        "INSERT INTO tbl_arbitration_log
            (request_id, borrower_id, borrower_name, equipment_name, decision,
             rule_applied, reason, override_by, override_by_id, override_reason, created_at)
         VALUES (?, ?, ?, ?, ?, 'manual_claim', ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            decision = VALUES(decision), rule_applied = 'manual_claim',
            reason = VALUES(reason), override_by = VALUES(override_by),
            override_by_id = VALUES(override_by_id),
            override_reason = VALUES(override_reason),
            created_at = VALUES(created_at)"
    );
    if ($log) {
        $log->bind_param(
            'isssssiss',
            $requestId, $loan['faculty_id'], $loan['faculty_name'],
            $loan['equipment_name'], $decision,
            $logReason, $adminName, $adminId, $logReason, $claimedAt
        );
        $log->execute();
        $log->close();
    }

    $conn->commit();

    admin_log_activity(
        $conn,
        'request',
        'Recorded manual claim: ' . $loan['equipment_name'] . ' → ' . $loan['faculty_name']
            . ($giveQty > 0 ? ' (' . $giveQty . ' unit(s))' : ' — 0 units, declined'),
        null,
        ['action' => 'request_claimed', 'ref_type' => 'request', 'ref_id' => $requestId]
    );

    $msg = $giveQty > 0
        ? $loan['equipment_name'] . ' handed over to ' . $loan['faculty_name'] . '.'
            . ($keepQty > 0 ? ' ' . $keepQty . ' unit(s) withheld.' : '')
        : 'Claim recorded with 0 units — request declined.';

    manual_claim_reply(200, 'success', $msg);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('[manual-claim] ' . $e->getMessage());
    manual_claim_reply(500, 'error', 'An unexpected error occurred. Please try again.');
}
