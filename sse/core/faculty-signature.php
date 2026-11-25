<?php
/**
 * sse/core/faculty-signature.php
 *
 * One place that answers "did anything change for this faculty member?".
 *
 * Used by the three faculty SSE streams (sse/api/faculty-*.php) AND by the plain-HTTP pulse
 * (sse/api/faculty-pulse.php), so both always agree on what counts as a change.
 *
 * Each part is a short opaque string. When it differs from the previous one, the browser
 * knows that part of the screen is out of date and re-fetches it:
 *
 *   requests   the faculty's equipment requests: new ones, and ANY status change
 *              (Waiting -> Approved / Declined / Cancelled, claimed, returned, overdue, extended ...)
 *   rooms      the faculty's room reservations (new, approved, declined, cancelled, expired)
 *              plus their waitlist entries
 *   notifs     everything else the notification bell is built from: admin notices, the
 *              read / deleted state, and the faculty's room-issue reports
 *   inventory  stock levels shown on the Equipment screen
 *
 * Every probe is a single aggregate query. A missing table or column (older install, migration
 * not run yet) only blanks that one part; it never throws.
 *
 * No output, no session, no headers: pure helpers.
 */

if (!function_exists('ps_live_cols')) {
    /**
     * Which of $wanted columns exist on $table (cached for the life of the PHP process).
     * @return string[]
     */
    function ps_live_cols(mysqli $conn, string $table, array $wanted): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            $cache[$table] = [];
            try {
                $stmt = $conn->prepare(
                    'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                );
                if ($stmt) {
                    $stmt->bind_param('s', $table);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $cache[$table][strtolower((string)$row['COLUMN_NAME'])] = true;
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                error_log('[PUPSync live] ps_live_cols ' . $table . ': ' . $e->getMessage());
            }
        }
        return array_values(array_filter($wanted, static function ($c) use ($cache, $table) {
            return isset($cache[$table][strtolower($c)]);
        }));
    }
}

if (!function_exists('ps_live_row_hash')) {
    /** SQL fragment: a CRC of the listed columns of one row (NULL-safe, position-safe). */
    function ps_live_row_hash(array $cols): string
    {
        if (!$cols) return "''";
        $parts = [];
        foreach ($cols as $c) {
            $parts[] = "IFNULL(`{$c}`, '~')";
        }
        return 'CRC32(CONCAT_WS(\'|\', ' . implode(', ', $parts) . '))';
    }
}

if (!function_exists('ps_live_fingerprint')) {
    /**
     * "<row count>-<highest id>-<sum of per-row hashes>" for the rows of $table matching $where.
     * Any insert, delete or change to one of $cols moves at least one of the three numbers.
     */
    function ps_live_fingerprint(mysqli $conn, string $table, string $idCol, array $cols, string $where, string $uid): string
    {
        try {
            $cols = ps_live_cols($conn, $table, $cols);
            $hash = ps_live_row_hash($cols);
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS n, COALESCE(MAX(`{$idCol}`), 0) AS mx,
                        COALESCE(SUM({$hash}), 0) AS chk
                   FROM `{$table}` WHERE {$where}"
            );
            if (!$stmt) return '';
            $stmt->bind_param('s', $uid);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $r ? ($r['n'] . '-' . $r['mx'] . '-' . $r['chk']) : '';
        } catch (Throwable $e) {
            // Table missing on an old install, etc: this part just stays blank.
            return '';
        }
    }
}

if (!function_exists('ps_faculty_live_parts')) {
    /**
     * @param string[] $only  which parts to compute (default: all four)
     * @return array<string,string>  part name => opaque change signature
     */
    function ps_faculty_live_parts(mysqli $conn, string $uid, array $only = []): array
    {
        $want = $only ?: ['requests', 'rooms', 'notifs', 'inventory'];
        $out  = [];

        if (in_array('requests', $want, true)) {
            $out['requests'] = md5(ps_live_fingerprint(
                $conn, 'tbl_requests', 'id',
                ['status', 'borrow_date', 'borrow_time', 'return_date', 'return_time', 'returned_at',
                 'claimed_at', 'extension_count', 'borrow_qty', 'reason', 'buffer_until', 'room'],
                'faculty_id = ?', $uid
            ));
        }

        if (in_array('rooms', $want, true)) {
            $res = ps_live_fingerprint(
                $conn, 'tbl_room_reservations', 'id',
                ['status', 'reservation_date', 'start_time', 'end_time', 'cancelled_at', 'reason', 'buffer_until'],
                'faculty_id = ?', $uid
            );
            $wait = ps_live_fingerprint(
                $conn, 'tbl_room_waitlist', 'id',
                ['reservation_date', 'start_time', 'end_time'],
                'faculty_id = ?', $uid
            );
            $out['rooms'] = md5($res . '/' . $wait);
        }

        if (in_array('notifs', $want, true)) {
            $notice = ps_live_fingerprint(
                $conn, 'tbl_faculty_admin_notice', 'id', ['title', 'message'],
                'faculty_id = ?', $uid
            );
            // Read / deleted state, so a notification read or deleted on another tab or phone shows here too
            $state = ps_live_fingerprint(
                $conn, 'tbl_faculty_notif_state', 'id', ['is_read', 'is_deleted'],
                'faculty_id = ?', $uid
            );
            $issues = ps_live_fingerprint(
                $conn, 'tbl_room_issues', 'id', ['status', 'admin_notes', 'resolved_at'],
                'reported_by_id = ?', $uid
            );
            $out['notifs'] = md5($notice . '/' . $state . '/' . $issues);
        }

        if (in_array('inventory', $want, true)) {
            try {
                $cols = ps_live_cols($conn, 'tbl_inventory', ['item_id', 'quantity', 'is_archived']);
                $hash = ps_live_row_hash($cols);
                $r = $conn->query(
                    "SELECT COUNT(*) AS n, COALESCE(SUM(quantity), 0) AS q, COALESCE(SUM({$hash}), 0) AS chk
                       FROM tbl_inventory WHERE is_archived = 0"
                );
                $row = $r ? $r->fetch_assoc() : null;
                $out['inventory'] = $row ? md5($row['n'] . '-' . $row['q'] . '-' . $row['chk']) : '';
            } catch (Throwable $e) {
                $out['inventory'] = '';
            }
        }

        return $out;
    }
}
