<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: ../../landing-page.php');
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
$csrf = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    die('Invalid session token. Please go back and try again.');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';

$conn = getDB();
admin_activity_bootstrap($conn);
$acting_admin_id = admin_current_id($conn);
$admin_name      = (string)($_SESSION['admin_name'] ?? 'Admin');

$token          = trim((string)($_POST['token'] ?? ''));
$give_qty       = (array)($_POST['give_qty']      ?? []);   // [req_id => int] units actually handed over
$withheld_qty   = (array)($_POST['withheld_qty']  ?? []);   // [req_id => int] units kept back
$withheld_cond  = (array)($_POST['withheld_cond'] ?? []);   // [req_id => 'Good'|'Damaged'|'Missing']
$reasons        = (array)($_POST['reason']         ?? []);  // [req_id => reason string]

const VALID_WITHHOLD_COND = ['Good', 'Damaged', 'Missing'];

if ($token === '') {
    die('No token provided.');
}

// Load all Approved rows for this token (bundle or individual)
$items = [];
$bundle_stmt = $conn->prepare(
    "SELECT id, faculty_id, faculty_name, equipment_name, borrow_qty
       FROM tbl_requests
      WHERE bundle_claim_token = ? AND status = 'Approved'"
);
if ($bundle_stmt) {
    $bundle_stmt->bind_param('s', $token);
    $bundle_stmt->execute();
    $res = $bundle_stmt->get_result();
    while ($r = $res->fetch_assoc()) $items[] = $r;
    $bundle_stmt->close();
}
if (empty($items)) {
    $single = $conn->prepare(
        "SELECT id, faculty_id, faculty_name, equipment_name, borrow_qty
           FROM tbl_requests
          WHERE claim_token = ? AND status = 'Approved' LIMIT 1"
    );
    if ($single) {
        $single->bind_param('s', $token);
        $single->execute();
        $row = $single->get_result()->fetch_assoc();
        $single->close();
        if ($row) $items[] = $row;
    }
}

if (empty($items)) {
    die('No claimable items found for this token. They may have already been claimed.');
}

$claimed  = 0;
$withheld = 0;

foreach ($items as $item) {
    $req_id   = (int)$item['id'];
    $max_qty  = max(1, (int)($item['borrow_qty'] ?? 1));
    $give     = isset($give_qty[$req_id]) ? max(0, min($max_qty, (int)$give_qty[$req_id])) : $max_qty;
    $keep_qty = $max_qty - $give;

    // If admin gave 0 units AND didn't send give_qty at all — skip (item not in checklist)
    if (!isset($give_qty[$req_id])) {
        continue;
    }

    $conn->begin_transaction();
    try {
        // Lock + re-check status
        $chk = $conn->prepare("SELECT status, stock_applied FROM tbl_requests WHERE id = ? FOR UPDATE");
        if (!$chk) { $conn->rollback(); continue; }
        $chk->bind_param('i', $req_id);
        $chk->execute();
        $chk_row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$chk_row || $chk_row['status'] !== 'Approved') {
            $conn->rollback();
            continue;
        }

        $stock_applied_before = (int)($chk_row['stock_applied'] ?? 1);
        $eq_name    = (string)$item['equipment_name'];
        $faculty_id = (string)$item['faculty_id'];
        $fac_name   = (string)$item['faculty_name'];

        if ($give > 0) {
            // ── Units handed over → On Loan ───────────────────────────────
            $upd = $conn->prepare(
                "UPDATE tbl_requests
                    SET status = 'On Loan', claimed_at = NOW(),
                        claim_token = NULL, bundle_claim_token = NULL,
                        stock_applied = 1, borrow_qty = ?
                  WHERE id = ? AND status = 'Approved'"
            );
            if (!$upd) { $conn->rollback(); continue; }
            $upd->bind_param('ii', $give, $req_id);
            $upd->execute();
            $upd->close();

            // If stock hadn't been taken yet, take only the given qty now
            if ($stock_applied_before === 0) {
                $dec = $conn->prepare(
                    "UPDATE tbl_inventory SET quantity = GREATEST(quantity - ?, 0) WHERE item_name = ?"
                );
                if ($dec) {
                    $dec->bind_param('is', $give, $eq_name);
                    $dec->execute();
                    $dec->close();
                }
            } elseif ($keep_qty > 0) {
                // Stock was already taken for full qty; give back the withheld portion now
                $inc = $conn->prepare(
                    "UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?"
                );
                if ($inc) {
                    $inc->bind_param('is', $keep_qty, $eq_name);
                    $inc->execute();
                    $inc->close();
                }
            }
        } else {
            // ── 0 units handed over → entire request Declined ────────────
            $reason_text = trim((string)($reasons[$req_id] ?? 'No units available at handover.'));
            $upd = $conn->prepare(
                "UPDATE tbl_requests
                    SET status = 'Declined', arbitration_rule = 'claim_zero',
                        reason = ?,
                        claim_token = NULL, bundle_claim_token = NULL
                  WHERE id = ? AND status = 'Approved'"
            );
            if (!$upd) { $conn->rollback(); continue; }
            $upd->bind_param('si', $reason_text, $req_id);
            $upd->execute();
            $upd->close();
            // Return any stock that had been reserved
            if ($stock_applied_before === 1) {
                $inc = $conn->prepare(
                    "UPDATE tbl_inventory SET quantity = quantity + ? WHERE item_name = ?"
                );
                if ($inc) {
                    $inc->bind_param('is', $max_qty, $eq_name);
                    $inc->execute();
                    $inc->close();
                }
            }
        }

        // ── Update inventory condition for withheld units ─────────────────
        if ($keep_qty > 0) {
            $wc = trim((string)($withheld_cond[$req_id] ?? 'Good'));
            if (!in_array($wc, VALID_WITHHOLD_COND, true)) $wc = 'Good';
            if ($wc !== 'Good') {
                $cond_upd = $conn->prepare(
                    "UPDATE tbl_inventory SET `condition` = ? WHERE item_name = ? LIMIT 1"
                );
                if ($cond_upd) {
                    $cond_upd->bind_param('ss', $wc, $eq_name);
                    $cond_upd->execute();
                    $cond_upd->close();
                }
            }
            $withheld += $keep_qty;
        }

        // ── Arbitration log ───────────────────────────────────────────────
        $log_decision = $give > 0 ? 'On Loan' : 'Declined';
        $log_rule     = $give > 0 ? 'qr_claim' : 'claim_zero';
        $log_reason   = $give > 0
            ? 'Claimed ' . $give . ' of ' . $max_qty . ' unit(s)' . ($keep_qty > 0 ? '; ' . $keep_qty . ' withheld (' . ($withheld_cond[$req_id] ?? 'unspecified') . ')' : '')
            : trim((string)($reasons[$req_id] ?? 'No units given at handover.'));
        $log = $conn->prepare(
            "INSERT INTO tbl_arbitration_log
                (request_id, borrower_id, borrower_name, equipment_name,
                 decision, rule_applied, reason,
                 override_by, override_by_id, override_reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                decision = VALUES(decision), rule_applied = VALUES(rule_applied),
                reason = VALUES(reason), override_by = VALUES(override_by),
                override_by_id = VALUES(override_by_id), override_reason = VALUES(override_reason),
                created_at = NOW()"
        );
        if ($log) {
            $log->bind_param(
                'isssssssis',
                $req_id, $faculty_id, $fac_name, $eq_name,
                $log_decision, $log_rule, $log_reason,
                $admin_name, $acting_admin_id, $log_reason
            );
            $log->execute();
            $log->close();
        }

        // ── Notify faculty if units were withheld ─────────────────────────
        if ($keep_qty > 0) {
            $wc      = trim((string)($withheld_cond[$req_id] ?? 'Good'));
            $reason  = trim((string)($reasons[$req_id] ?? ''));
            $n_title = $keep_qty . ' unit(s) of ' . $eq_name . ' withheld at pickup';
            $n_msg   = $keep_qty . ' of ' . $max_qty . ' unit(s) of "' . $eq_name
                . '" could not be handed over (condition: ' . $wc . ').'
                . ($reason !== '' ? ' Reason: ' . $reason : '')
                . ($give > 0 ? ' You received ' . $give . ' unit(s).' : ' Your request was cancelled.');
            $notif = $conn->prepare(
                "INSERT INTO tbl_faculty_admin_notice
                    (faculty_id, request_id, admin_id, admin_name, title, message, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            if ($notif) {
                $notif->bind_param('siisss', $faculty_id, $req_id, $acting_admin_id, $admin_name, $n_title, $n_msg);
                $notif->execute();
                $notif->close();
            }
        }

        $conn->commit();
        if ($give > 0) $claimed++;

        admin_log_activity(
            $conn,
            'request',
            ($give > 0 ? 'Claimed ' . $give . ' of ' . $max_qty . ' ' . $eq_name : 'Declined ' . $eq_name . ' at claim — 0 units given') . ' for ' . $fac_name,
            null,
            ['action' => $give > 0 ? 'request_claimed' : 'request_declined', 'ref_type' => 'request', 'ref_id' => $req_id]
        );
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[partial-claim] id=' . $req_id . ': ' . $e->getMessage());
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'claimed' => $claimed, 'withheld' => $withheld]);
exit;
        continue; // admin left this one unchecked — leave as Approved
    }

    $conn->begin_transaction();
    try {
        // Lock + re-check status
        $chk = $conn->prepare("SELECT status FROM tbl_requests WHERE id = ? FOR UPDATE");
        if (!$chk) { $conn->rollback(); continue; }
        $chk->bind_param('i', $req_id);
        $chk->execute();
        $status_row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$status_row || $status_row['status'] !== 'Approved') {
            $conn->rollback();
            continue;
        }

        // Read stock_applied and borrow_qty before updating status
        $sa_stmt = $conn->prepare(
            "SELECT stock_applied, borrow_qty, equipment_name FROM tbl_requests WHERE id = ? LIMIT 1"
        );
        $stock_applied = 1;
        $borrow_qty    = 1;
        $equip_name    = (string)$item['equipment_name'];
        if ($sa_stmt) {
            $sa_stmt->bind_param('i', $req_id);
            $sa_stmt->execute();
            $sa_row = $sa_stmt->get_result()->fetch_assoc();
            $sa_stmt->close();
            if ($sa_row) {
                $stock_applied = (int)$sa_row['stock_applied'];
                $borrow_qty    = max(1, (int)($sa_row['borrow_qty'] ?? 1));
                $equip_name    = (string)$sa_row['equipment_name'];
            }
        }

        $upd = $conn->prepare(
            "UPDATE tbl_requests
                SET status = 'On Loan', claimed_at = NOW(),
                    claim_token = NULL, bundle_claim_token = NULL,
                    stock_applied = 1
              WHERE id = ? AND status = 'Approved'"
        );
        if (!$upd) { $conn->rollback(); continue; }
        $upd->bind_param('i', $req_id);
        $upd->execute();
        $upd->close();

        // If stock hadn't been taken yet (scheduled booking), take it now at handover time
        if ($stock_applied === 0) {
            $dec = $conn->prepare(
                "UPDATE tbl_inventory SET quantity = GREATEST(quantity - ?, 0) WHERE item_name = ?"
            );
            if ($dec) {
                $dec->bind_param('is', $borrow_qty, $equip_name);
                $dec->execute();
                $dec->close();
            }
        }

        $log = $conn->prepare(
            "INSERT INTO tbl_arbitration_log
                (request_id, borrower_id, borrower_name, equipment_name,
                 decision, rule_applied, reason,
                 override_by, override_by_id, override_reason, created_at)
             VALUES (?, ?, ?, ?, 'On Loan', 'qr_claim',
                     'Equipment claimed via checklist (partial claim supported)',
                     ?, ?, 'Partial Claim', NOW())
             ON DUPLICATE KEY UPDATE
                decision = 'On Loan', rule_applied = 'qr_claim',
                override_by = VALUES(override_by),
                override_by_id = VALUES(override_by_id), created_at = NOW()"
        );
        if ($log) {
            $log->bind_param(
                'issssi',
                $req_id,
                $item['faculty_id'],
                $item['faculty_name'],
                $item['equipment_name'],
                $admin_name,
                $acting_admin_id
            );
            $log->execute();
            $log->close();
        }

        $conn->commit();
        $claimed++;

        admin_log_activity(
            $conn,
            'request',
            'Confirmed claim of ' . $item['equipment_name'] . ' by ' . $item['faculty_name'],
            null,
            ['action' => 'request_claimed', 'ref_type' => 'request', 'ref_id' => $req_id]
        );
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[partial-claim] id=' . $req_id . ': ' . $e->getMessage());
    }
}

$flagged = 0;

// ── Process flagged items (Damaged / Missing at claim time) ──────────────────
const VALID_CLAIM_COND = ['Damaged', 'Missing'];

foreach ($flag_items as $req_id) {
    if ($req_id <= 0) continue;

    $cond_raw  = trim((string)($flag_condition[$req_id] ?? ''));
    $condition = in_array($cond_raw, VALID_CLAIM_COND, true) ? $cond_raw : 'Damaged';
    $reason    = trim((string)($flag_reason[$req_id] ?? 'Flagged at claim time by admin.'));

    // Find the item name for this request
    $fi_stmt = $conn->prepare(
        "SELECT equipment_name, faculty_id, faculty_name FROM tbl_requests WHERE id = ? LIMIT 1"
    );
    if (!$fi_stmt) continue;
    $fi_stmt->bind_param('i', $req_id);
    $fi_stmt->execute();
    $fi_row = $fi_stmt->get_result()->fetch_assoc();
    $fi_stmt->close();
    if (!$fi_row) continue;

    $eq_name      = (string)$fi_row['equipment_name'];
    $faculty_id   = (string)$fi_row['faculty_id'];
    $faculty_name = (string)$fi_row['faculty_name'];

    $conn->begin_transaction();
    try {
        // Decline the request — item is not handed over
        $dec_upd = $conn->prepare(
            "UPDATE tbl_requests
                SET status = 'Declined',
                    arbitration_rule = 'claim_flagged',
                    reason = ?,
                    claim_token = NULL, bundle_claim_token = NULL
              WHERE id = ? AND status = 'Approved'"
        );
        if (!$dec_upd) { $conn->rollback(); continue; }
        $decline_reason = $condition . ' at handover: ' . $reason;
        $dec_upd->bind_param('si', $decline_reason, $req_id);
        $dec_upd->execute();
        $dec_upd->close();

        // Update inventory condition (item stays in cabinet but flagged)
        $inv_upd = $conn->prepare(
            "UPDATE tbl_inventory SET `condition` = ? WHERE item_name = ? LIMIT 1"
        );
        if ($inv_upd) {
            $inv_upd->bind_param('ss', $condition, $eq_name);
            $inv_upd->execute();
            $inv_upd->close();
        }

        // Log to arbitration log
        $log = $conn->prepare(
            "INSERT INTO tbl_arbitration_log
                (request_id, borrower_id, borrower_name, equipment_name,
                 decision, rule_applied, reason,
                 override_by, override_by_id, override_reason, created_at)
             VALUES (?, ?, ?, ?, 'Declined', 'claim_flagged', ?,
                     ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                decision = 'Declined', rule_applied = 'claim_flagged',
                reason = VALUES(reason), override_by = VALUES(override_by),
                override_by_id = VALUES(override_by_id), override_reason = VALUES(override_reason),
                created_at = NOW()"
        );
        if ($log) {
            $log->bind_param(
                'issssssis',
                $req_id,
                $faculty_id,
                $faculty_name,
                $eq_name,
                $decline_reason,
                $admin_name,
                $acting_admin_id,
                $decline_reason
            );
            $log->execute();
            $log->close();
        }

        // Notify faculty
        $notice_title = $condition . ' item at pickup: ' . $eq_name;
        $notice_msg   = 'The item "' . $eq_name . '" was found ' . strtolower($condition)
            . ' when you came to collect it. Your request has been cancelled. Reason: ' . $reason
            . '. Please contact the admin office.';
        $notif = $conn->prepare(
            "INSERT INTO tbl_faculty_admin_notice
                (faculty_id, request_id, admin_id, admin_name, title, message, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        if ($notif) {
            $notif->bind_param(
                'siisss',
                $faculty_id, $req_id, $acting_admin_id, $admin_name, $notice_title, $notice_msg
            );
            $notif->execute();
            $notif->close();
        }

        $conn->commit();
        $flagged++;

        admin_log_activity(
            $conn,
            'request',
            'Flagged ' . $eq_name . ' as ' . $condition . ' at claim — request declined',
            null,
            ['action' => 'request_declined', 'ref_type' => 'request', 'ref_id' => $req_id]
        );
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[partial-claim-flag] id=' . $req_id . ': ' . $e->getMessage());
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'claimed' => $claimed, 'flagged' => $flagged]);
exit;
