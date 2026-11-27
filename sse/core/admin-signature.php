<?php
/**
 * sse/core/admin-signature.php
 *
 * One place that answers "did anything change that an admin is looking at?".
 *
 * Used by the admin SSE stream (sse/api/admin-equipment.php) AND by the plain-HTTP pulse
 * (sse/api/admin-pulse.php), so both always agree on what counts as a change. It is the admin twin of
 * sse/core/faculty-signature.php (same helpers, same idea, but over ALL rows instead of one faculty's).
 *
 *   requests   every equipment request (faculty and student): new ones and ANY status change
 *              (Waiting -> Approved / Declined / Cancelled, claimed, returned, overdue, extended ...)
 *   rooms      every room reservation: new, approved, declined, cancelled, rescheduled, expired
 *   inventory  the equipment catalog: stock levels, new / edited / archived items
 *   activity   the admin activity log
 *
 * Each part is a short opaque string; when it differs from the previous one, that part of the screen is out of date.
 * A missing table or column (older install) only blanks that one part; it never throws.
 *
 * No output, no session, no headers: pure helpers.
 */

require_once __DIR__ . '/faculty-signature.php';   // ps_live_cols(), ps_live_row_hash()

if (!function_exists('ps_admin_fingerprint')) {
    /**
     * "<row count>-<highest id>-<sum of per-row hashes>" for ALL rows of $table (optionally filtered by a fixed
     * $where with no parameters). Any insert, delete or change to one of $cols moves at least one of the numbers.
     */
    function ps_admin_fingerprint(mysqli $conn, string $table, string $idCol, array $cols, string $where = '1=1'): string
    {
        try {
            $cols = ps_live_cols($conn, $table, $cols);
            $hash = ps_live_row_hash($cols);
            $r = $conn->query(
                "SELECT COUNT(*) AS n, COALESCE(MAX(`{$idCol}`), 0) AS mx,
                        COALESCE(SUM({$hash}), 0) AS chk
                   FROM `{$table}` WHERE {$where}"
            );
            $row = $r ? $r->fetch_assoc() : null;
            return $row ? ($row['n'] . '-' . $row['mx'] . '-' . $row['chk']) : '';
        } catch (Throwable $e) {
            return '';   // table missing on an old install, etc: this part just stays blank
        }
    }
}

if (!function_exists('ps_admin_live_parts')) {
    /**
     * @param string[] $only  which parts to compute (default: all)
     * @return array<string,string>  part name => opaque change signature
     */
    function ps_admin_live_parts(mysqli $conn, array $only = []): array
    {
        $want = $only ?: ['requests', 'rooms', 'inventory', 'activity'];
        $out  = [];

        if (in_array('requests', $want, true)) {
            $out['requests'] = md5(ps_admin_fingerprint(
                $conn, 'tbl_requests', 'id',
                ['status', 'borrow_date', 'borrow_time', 'return_date', 'return_time', 'returned_at',
                 'claimed_at', 'extension_count', 'borrow_qty', 'reason', 'buffer_until', 'arbitration_rule', 'document_path']
            ));
        }

        if (in_array('rooms', $want, true)) {
            $out['rooms'] = md5(ps_admin_fingerprint(
                $conn, 'tbl_room_reservations', 'id',
                ['status', 'room_id', 'reservation_date', 'start_time', 'end_time', 'cancelled_at', 'reason',
                 'buffer_until', 'document_path']
            ));
        }

        if (in_array('inventory', $want, true)) {
            $out['inventory'] = md5(ps_admin_fingerprint(
                $conn, 'tbl_inventory', 'item_id',
                ['item_name', 'category', 'quantity', 'condition', 'is_archived', 'is_high_value', 'image_path']
            ));
        }

        if (in_array('activity', $want, true)) {
            $out['activity'] = md5(ps_admin_fingerprint($conn, 'tbl_admin_activity', 'id', ['action', 'summary']));
        }

        return $out;
    }
}
