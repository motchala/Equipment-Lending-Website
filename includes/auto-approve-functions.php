<?php
/**
 * auto-approve-functions.php
 *
 * Shared helper: contains only processAutoApprove() so it can be safely
 * included from faculty-dashboard.php without pulling in the admin-only
 * session guard, DB connection, or AJAX handlers that live in
 * admin-dashboard-functions.php.
 */

if (!function_exists('processAutoApprove')) {

    /**
     * Evaluates a newly inserted borrow request for auto-approval.
     * Approves, declines, or leaves as Waiting based on toggle state,
     * item set membership, and current stock.
     *
     * Logic flow:
     *  1. Fetch request row — bail if not found or status !== 'Waiting'
     *  2. Read is_enabled from settings (id=1) — bail if OFF or row absent
     *  3. Read auto-approved item set — bail if equipment_name not in set
     *  4. Check is_archived on tbl_inventory — bail if archived or not found
     *  5. Read quantity — if 0, decline with out-of-stock reason and return
     *  6. Approve: UPDATE tbl_requests SET status='Approved', reason=NULL
     *  7. Decrement: UPDATE tbl_inventory SET quantity=quantity-1
     *  8. If new quantity = 0, cascade-decline all remaining Waiting requests for that item
     *
     * @param mysqli $conn        Active DB connection
     * @param int    $request_id  The id of the newly inserted tbl_requests row
     */
    function processAutoApprove(mysqli $conn, int $request_id): void
    {
        // ── Step 1: Fetch the request row ────────────────────────────────────
        $stmt = $conn->prepare("SELECT equipment_name, status FROM tbl_requests WHERE id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result  = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();

        if (!$request || $request['status'] !== 'Waiting') {
            return;
        }

        $equipment_name = $request['equipment_name'];

        // ── Step 2: Read is_enabled from settings row (id=1) ─────────────────
        $stmt = $conn->prepare("SELECT is_enabled FROM tbl_auto_approve_settings WHERE id = 1");
        if (!$stmt) {
            return;
        }
        $stmt->execute();
        $result   = $stmt->get_result();
        $settings = $result->fetch_assoc();
        $stmt->close();

        if (!$settings || (int) $settings['is_enabled'] === 0) {
            return;
        }

        // ── Step 3: Read auto-approved item set; bail if item not in set ──────
        $stmt = $conn->prepare("SELECT item_name FROM tbl_auto_approve_settings WHERE is_auto_approved = 1");
        if (!$stmt) {
            return;
        }
        $stmt->execute();
        $result             = $stmt->get_result();
        $auto_approve_items = [];
        while ($row = $result->fetch_assoc()) {
            $auto_approve_items[] = $row['item_name'];
        }
        $stmt->close();

        if (!in_array($equipment_name, $auto_approve_items, true)) {
            return;
        }

        // ── Step 4: Check is_archived on tbl_inventory ───────────────────────
        $stmt = $conn->prepare("SELECT is_archived, quantity FROM tbl_inventory WHERE item_name = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("s", $equipment_name);
        $stmt->execute();
        $result    = $stmt->get_result();
        $inventory = $result->fetch_assoc();
        $stmt->close();

        if (!$inventory || (int) $inventory['is_archived'] === 1) {
            return;
        }

        // ── Step 5: Check quantity; decline if out of stock ──────────────────
        $quantity = (int) $inventory['quantity'];

        if ($quantity === 0) {
            $reason = 'Out of stock – maximum approved requests reached';
            $stmt   = $conn->prepare("UPDATE tbl_requests SET status = 'Declined', reason = ? WHERE id = ?");
            if (!$stmt) {
                return;
            }
            $stmt->bind_param("si", $reason, $request_id);
            $stmt->execute();
            $stmt->close();
            return;
        }

        // ── Step 6: Approve the request ──────────────────────────────────────
        $stmt = $conn->prepare("UPDATE tbl_requests SET status = 'Approved', reason = NULL WHERE id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();

        // ── Step 7: Decrement inventory quantity ─────────────────────────────
        $stmt = $conn->prepare("UPDATE tbl_inventory SET quantity = quantity - 1 WHERE item_name = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("s", $equipment_name);
        $stmt->execute();
        $stmt->close();

        // ── Step 8: Cascade-decline remaining Waiting requests if stock = 0 ──
        $new_quantity = $quantity - 1;

        if ($new_quantity === 0) {
            $reason = 'Out of stock – maximum approved requests reached';
            $stmt   = $conn->prepare(
                "UPDATE tbl_requests SET status = 'Declined', reason = ? WHERE equipment_name = ? AND status = 'Waiting'"
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param("ss", $reason, $equipment_name);
            $stmt->execute();
            $stmt->close();
        }
    }

}
