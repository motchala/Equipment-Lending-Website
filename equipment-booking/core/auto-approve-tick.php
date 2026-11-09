<?php

declare(strict_types=1);

/**
 * equipment-booking/core/auto-approve-tick.php
 *
 * Lazy auto-approve tick: finds Waiting requests older than the configured
 * buffer time (auto_approve_timeout_minutes) and approves them if stock allows.
 *
 * Call sites (mirrors BookingSchedule::tick):
 *   - poll-requests-data.php  (admin live-poll)
 *   - activity/core/faculty-data.php → fact_eq_housekeeping()  (faculty activity poll)
 *
 * Design rules:
 *   - Adviser requests with a supporting document are EXCLUDED (need human review).
 *   - Scheduled (today / future) bookings follow the schedule path, not the shelf count.
 *   - Uses the same transaction + FOR UPDATE pattern as admin-override.php.
 *   - Idempotent: uses `stock_applied`-style guard so two concurrent polls can't
 *     double-approve the same row.
 *   - Whole function is wrapped in try/catch; never throws to the caller.
 *   - Rule constant 'auto_approve_timeout' is used for tbl_arbitration_log.rule_applied.
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/arbitration-engine.php';

/**
 * Auto-approve Waiting equipment requests that have been waiting longer than
 * the configured buffer (auto_approve_timeout_minutes in tbl_arbitration_config).
 *
 * @param mysqli $conn Active DB connection
 */
function equipment_auto_approve_tick(mysqli $conn): void
{
    try {
        // ── 1. Ensure scheduling columns exist ───────────────────────────────
        BookingSchedule::ensureSchema($conn);

        // ── 2. Load config ───────────────────────────────────────────────────
        $config = [];
        $res = $conn->query('SELECT config_key, config_value FROM tbl_arbitration_config');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $config[(string)$row['config_key']] = (string)$row['config_value'];
            }
        }

        $timeout_minutes = (int)($config['auto_approve_timeout_minutes'] ?? 0);

        // 0 or negative means the feature is disabled.
        if ($timeout_minutes <= 0) {
            return;
        }

        // ── 3. Find eligible Waiting requests older than the buffer ──────────
        //
        // Excluded:
        //   - Adviser requests WITH a document already attached
        //     (document attached = human review required by design)
        //   - Archived inventory items (same as the engine's step 6)
        //
        // We do NOT exclude requests whose document_path IS NULL / empty even
        // if they are adviser-mode, because a missing-document hold is the
        // engine's own "still waiting" state — once the timeout fires it means
        // the admin hasn't acted, so we treat it like any other Waiting row.
        // The plan spec says: exclude adviser requests WHERE document_path is
        // NOT NULL / NOT '' (i.e. a doc was actually uploaded and is pending
        // review).  Requests with no document go through.
        $cutoff = date('Y-m-d H:i:s', time() - $timeout_minutes * 60);

        $stmt = $conn->prepare(
            "SELECT r.id,
                    r.equipment_name,
                    r.faculty_id,
                    r.faculty_name,
                    r.booking_mode,
                    r.borrow_qty,
                    r.borrow_date,
                    r.borrow_time,
                    r.stock_applied,
                    r.batch_id
               FROM tbl_requests r
               JOIN tbl_inventory i ON i.item_name = r.equipment_name
              WHERE r.status = 'Waiting'
                AND r.request_date < ?
                AND (r.document_path IS NULL OR r.document_path = '')
                AND i.is_archived = 0
              ORDER BY r.request_date ASC"
        );

        if (!$stmt) {
            error_log('equipment_auto_approve_tick: prepare candidate query failed — ' . $conn->error);
            return;
        }

        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($candidates)) {
            return;
        }

        // ── 4. Group candidates: batch rows together, solo rows alone ────────
        // Rows sharing a batch_id must share one bundle_claim_token (same as
        // approve-borrow-bundle.php) so the faculty sees 1 Claim QR for a bundle,
        // not one per item. Rows with no batch_id are approved individually.
        $groups   = [];   // group_key => [rows]
        $seen_ids = [];   // guard: a row should only appear in one group

        foreach ($candidates as $req) {
            $rid      = (int)$req['id'];
            $batch    = trim((string)($req['batch_id'] ?? ''));
            $key      = $batch !== '' ? 'batch:' . $batch : 'solo:' . $rid;
            $groups[$key][] = $req;
            $seen_ids[$rid] = true;
        }

        foreach ($groups as $group_key => $group_rows) {
            $is_bundle = str_starts_with($group_key, 'batch:');

            // One shared bundle_claim_token for the whole group; NULL for solo rows.
            $bundle_claim_token = $is_bundle ? bin2hex(random_bytes(32)) : null;

            $conn->begin_transaction();
            $committed = false;

            try {
                foreach ($group_rows as $req) {
                    $request_id    = (int)$req['id'];
                    $item_name     = (string)$req['equipment_name'];
                    $borrower_id   = (string)$req['faculty_id'];
                    $borrower_name = (string)$req['faculty_name'];
                    $is_scheduled  = in_array((string)($req['booking_mode'] ?? ''), ['today', 'future'], true);
                    $borrow_qty    = max(1, (int)($req['borrow_qty'] ?? 1));

                    // ── Idempotency guard ─────────────────────────────────────
                    $check = $conn->prepare(
                        "SELECT status FROM tbl_requests WHERE id = ? FOR UPDATE"
                    );
                    if (!$check) { $conn->rollback(); continue 2; }
                    $check->bind_param('i', $request_id);
                    $check->execute();
                    $check_row = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$check_row || (string)$check_row['status'] !== 'Waiting') {
                        $conn->rollback();
                        continue 2;
                    }

                    // ── Stock check (non-scheduled only) ──────────────────────
                    if (!$is_scheduled) {
                        $inv = $conn->prepare(
                            "SELECT quantity FROM tbl_inventory WHERE item_name = ? LIMIT 1 FOR UPDATE"
                        );
                        if (!$inv) { $conn->rollback(); continue 2; }
                        $inv->bind_param('s', $item_name);
                        $inv->execute();
                        $inv_row = $inv->get_result()->fetch_assoc();
                        $inv->close();
                        if (!$inv_row || (int)$inv_row['quantity'] < $borrow_qty) {
                            $conn->rollback();
                            continue 2;
                        }
                    }

                    // ── Flip to Approved ──────────────────────────────────────
                    $upd = $conn->prepare(
                        "UPDATE tbl_requests
                            SET status           = 'Approved',
                                arbitration_rule = 'auto_approve_timeout',
                                reason           = 'Auto-approved: no admin action within the buffer time.'
                          WHERE id = ? AND status = 'Waiting'"
                    );
                    if (!$upd) { $conn->rollback(); continue 2; }
                    $upd->bind_param('i', $request_id);
                    $upd->execute();
                    $affected = $upd->affected_rows;
                    $upd->close();

                    if ($affected === 0) { $conn->rollback(); continue 2; }

                    // ── Tokens ────────────────────────────────────────────────
                    // Bundle rows: shared bundle_claim_token + NULL claim_token
                    //              (same as approve-borrow-bundle.php)
                    // Solo rows:   individual claim_token + NULL bundle_claim_token
                    $return_token = bin2hex(random_bytes(32));

                    if ($is_bundle) {
                        $tok = $conn->prepare(
                            "UPDATE tbl_requests
                                SET return_token = ?,
                                    claim_token = NULL,
                                    bundle_claim_token = ?
                              WHERE id = ?"
                        );
                        if (!$tok) { $conn->rollback(); continue 2; }
                        $tok->bind_param('ssi', $return_token, $bundle_claim_token, $request_id);
                    } else {
                        $claim_token = bin2hex(random_bytes(32));
                        $tok = $conn->prepare(
                            "UPDATE tbl_requests
                                SET return_token = ?,
                                    claim_token = ?,
                                    bundle_claim_token = NULL
                              WHERE id = ?"
                        );
                        if (!$tok) { $conn->rollback(); continue 2; }
                        $tok->bind_param('ssi', $return_token, $claim_token, $request_id);
                    }
                    $tok->execute();
                    $tok->close();

                    // ── Inventory ─────────────────────────────────────────────
                    if ($is_scheduled) {
                        $sa = $conn->prepare(
                            "UPDATE tbl_requests SET stock_applied = 0 WHERE id = ?"
                        );
                        if ($sa) {
                            $sa->bind_param('i', $request_id);
                            $sa->execute();
                            $sa->close();
                        }
                    } else {
                        $dec = $conn->prepare(
                            "UPDATE tbl_inventory
                                SET quantity = quantity - ?
                              WHERE item_name = ? AND quantity >= ?"
                        );
                        if (!$dec) { $conn->rollback(); continue 2; }
                        $dec->bind_param('isi', $borrow_qty, $item_name, $borrow_qty);
                        $dec->execute();
                        $dec->close();
                    }

                    // ── Arbitration log ───────────────────────────────────────
                    $log = $conn->prepare(
                        "INSERT INTO tbl_arbitration_log
                            (request_id, borrower_id, borrower_name, equipment_name,
                             decision, rule_applied, reason,
                             override_by, override_by_id, override_reason, created_at)
                         VALUES (?, ?, ?, ?,
                             'Approved', 'auto_approve_timeout',
                             'Auto-approved: no admin action within the buffer time.',
                             NULL, NULL, NULL, NOW())
                         ON DUPLICATE KEY UPDATE
                            decision     = 'Approved',
                            rule_applied = 'auto_approve_timeout',
                            reason       = 'Auto-approved: no admin action within the buffer time.',
                            created_at   = NOW()"
                    );
                    if ($log) {
                        $log->bind_param('isss', $request_id, $borrower_id, $borrower_name, $item_name);
                        $log->execute();
                        $log->close();
                    }
                } // end foreach group_rows

                $conn->commit();
                $committed = true;

            } catch (\Throwable $e) {
                if (!$committed) $conn->rollback();
                error_log('equipment_auto_approve_tick[group=' . $group_key . '] — ' . $e->getMessage());
            }
        } // end foreach groups

    } catch (\Throwable $e) {
        error_log('equipment_auto_approve_tick — ' . $e->getMessage());
    }
}
