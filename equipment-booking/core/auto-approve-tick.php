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
                    r.stock_applied
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

        // ── 4. Process each candidate ────────────────────────────────────────
        foreach ($candidates as $req) {
            $request_id    = (int)$req['id'];
            $item_name     = (string)$req['equipment_name'];
            $borrower_id   = (string)$req['faculty_id'];
            $borrower_name = (string)$req['faculty_name'];
            $is_scheduled  = in_array((string)($req['booking_mode'] ?? ''), ['today', 'future'], true);
            $borrow_qty    = max(1, (int)($req['borrow_qty'] ?? 1));

            $conn->begin_transaction();

            try {
                // ── 4a. Idempotency guard: re-check status inside the transaction ──
                // Another poll may have already approved this row between our SELECT
                // above and now; skip it if so.
                $check = $conn->prepare(
                    "SELECT status FROM tbl_requests WHERE id = ? FOR UPDATE"
                );
                if (!$check) {
                    $conn->rollback();
                    continue;
                }
                $check->bind_param('i', $request_id);
                $check->execute();
                $check_row = $check->get_result()->fetch_assoc();
                $check->close();

                if (!$check_row || (string)$check_row['status'] !== 'Waiting') {
                    // Already approved / declined by another concurrent call.
                    $conn->rollback();
                    continue;
                }

                // ── 4b. Stock check (non-scheduled items only) ───────────────
                if (!$is_scheduled) {
                    $inv = $conn->prepare(
                        "SELECT quantity FROM tbl_inventory WHERE item_name = ? LIMIT 1 FOR UPDATE"
                    );
                    if (!$inv) {
                        $conn->rollback();
                        continue;
                    }
                    $inv->bind_param('s', $item_name);
                    $inv->execute();
                    $inv_row = $inv->get_result()->fetch_assoc();
                    $inv->close();

                    if (!$inv_row || (int)$inv_row['quantity'] <= 0) {
                        // Out of stock — leave as Waiting; a human can deal with it.
                        $conn->rollback();
                        continue;
                    }
                }

                // ── 4c. Flip status to Approved ──────────────────────────────
                $upd = $conn->prepare(
                    "UPDATE tbl_requests
                        SET status           = 'Approved',
                            arbitration_rule = 'auto_approve_timeout',
                            reason           = 'Auto-approved: no admin action within the buffer time.'
                      WHERE id = ? AND status = 'Waiting'"
                );
                if (!$upd) {
                    $conn->rollback();
                    continue;
                }
                $upd->bind_param('i', $request_id);
                $upd->execute();
                $affected = $upd->affected_rows;
                $upd->close();

                if ($affected === 0) {
                    // Another concurrent process already moved this row.
                    $conn->rollback();
                    continue;
                }

                // ── 4d. Generate return + claim tokens ───────────────────────
                $return_token = bin2hex(random_bytes(32));
                $claim_token  = bin2hex(random_bytes(32));

                $tok = $conn->prepare(
                    "UPDATE tbl_requests
                        SET return_token = ?, claim_token = ?, bundle_claim_token = NULL
                      WHERE id = ? AND claim_token IS NULL"
                );
                if (!$tok) {
                    $conn->rollback();
                    continue;
                }
                $tok->bind_param('ssi', $return_token, $claim_token, $request_id);
                $tok->execute();
                $tok->close();

                // ── 4e. Inventory handling ───────────────────────────────────
                if ($is_scheduled) {
                    // Scheduled: stock will be taken when the booking starts
                    // (BookingSchedule::activateDue). Mark stock_applied = 0
                    // so activateDue knows it still needs to act.
                    $sa = $conn->prepare(
                        "UPDATE tbl_requests SET stock_applied = 0 WHERE id = ?"
                    );
                    if ($sa) {
                        $sa->bind_param('i', $request_id);
                        $sa->execute();
                        $sa->close();
                    }
                } else {
                    // Non-scheduled: take stock immediately.
                    $dec = $conn->prepare(
                        "UPDATE tbl_inventory SET quantity = quantity - 1 WHERE item_name = ? AND quantity > 0"
                    );
                    if (!$dec) {
                        $conn->rollback();
                        continue;
                    }
                    $dec->bind_param('s', $item_name);
                    $dec->execute();
                    $dec->close();
                }

                // ── 4f. Write arbitration log ────────────────────────────────
                $log = $conn->prepare(
                    "INSERT INTO tbl_arbitration_log
                        (request_id, borrower_id, borrower_name, equipment_name,
                         decision, rule_applied, reason,
                         override_by, override_by_id, override_reason, created_at)
                     VALUES
                        (?, ?, ?, ?,
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

                $conn->commit();

            } catch (\Throwable $e) {
                $conn->rollback();
                error_log('equipment_auto_approve_tick[id=' . $request_id . '] — ' . $e->getMessage());
            }
        }

    } catch (\Throwable $e) {
        error_log('equipment_auto_approve_tick — ' . $e->getMessage());
    }
}
