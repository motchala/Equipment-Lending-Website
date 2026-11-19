<?php

/**
 * notifications/core/approval-receipts.php
 *
 * APPROVAL RECEIPT for the faculty.
 *
 * The moment an equipment request or a room reservation is approved (by an admin, by the automatic rules,
 * or by the faculty adviser), the faculty member gets a push notification that works as a receipt:
 * receipt number, what was approved, and when / where. The matching in-app item (the bell) is built live
 * by notifications/core/faculty-data.php (keys "reqapproved-<id>" / "roomapproved-<id>") and carries the
 * same receipt number, so what arrives on the phone and what is in the bell always agree.
 *
 * Every place that approves something is different (admin override, bundle approve, the automatic
 * decision, two room reviews, the student and faculty submit paths), so nothing here depends on which one
 * ran. ps_receipts_sweep() looks for approved rows that have no receipt yet, claims each one in
 * tbl_approval_receipts (UNIQUE kind + ref_id, so a row is announced exactly once even when two requests
 * sweep at the same time) and pushes. It is safe to call as often as you like, after the decision is
 * saved, and it never throws and never prints anything.
 *
 * When tbl_approval_receipts is first created, everything already approved is recorded silently, so only
 * approvals made from then on produce a push.
 *
 * Public API:
 *   ps_receipt_no(string $kind, int $id): string        "R-0123" (equipment, same number the admin sees) / "RM-0045" (room)
 *   ps_receipts_sweep(mysqli $conn, ?string $facultyId = null): int   how many receipts were pushed
 */

require_once __DIR__ . '/preferences.php';
require_once __DIR__ . '/push.php';

if (!function_exists('ps_receipt_no')) {
    /** Receipt number. Equipment matches the request number the admin sees (#R-0123); rooms use RM-. */
    function ps_receipt_no(string $kind, int $id): string
    {
        return ($kind === 'room' ? 'RM-' : 'R-') . str_pad((string)max(0, $id), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ps_receipts_ensure_table')) {
    /** Creates tbl_approval_receipts on first use and records everything already approved, silently. */
    function ps_receipts_ensure_table(mysqli $conn): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $chk     = $conn->query("SHOW TABLES LIKE 'tbl_approval_receipts'");
            $existed = $chk && $chk->num_rows > 0;
            if (!$existed) {
                $conn->query(
                    "CREATE TABLE IF NOT EXISTS tbl_approval_receipts (
                        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        kind       VARCHAR(12)  NOT NULL,
                        ref_id     INT          NOT NULL,
                        faculty_id VARCHAR(64)  NOT NULL DEFAULT '',
                        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uq_receipt (kind, ref_id),
                        KEY idx_receipt_faculty (faculty_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                // Past approvals are not announced; only approvals from now on are.
                try {
                    $conn->query(
                        "INSERT IGNORE INTO tbl_approval_receipts (kind, ref_id, faculty_id)
                         SELECT 'equipment', id, COALESCE(faculty_id, '') FROM tbl_requests
                          WHERE status IN ('Approved', 'On Loan', 'Overdue', 'Returned')"
                    );
                } catch (Throwable $e) {
                    error_log('[PUPSync] approval receipts seed (equipment): ' . $e->getMessage());
                }
                try {
                    $conn->query(
                        "INSERT IGNORE INTO tbl_approval_receipts (kind, ref_id, faculty_id)
                         SELECT 'room', id, COALESCE(faculty_id, '') FROM tbl_room_reservations
                          WHERE status = 'Approved'"
                    );
                } catch (Throwable $e) {
                    error_log('[PUPSync] approval receipts seed (rooms): ' . $e->getMessage());
                }
            }
            $ready = true;
        } catch (Throwable $e) {
            error_log('[PUPSync] ps_receipts_ensure_table: ' . $e->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('ps_receipts_stamp')) {
    /** "Nov 20, 9:00 AM" (time left off when the row has none). */
    function ps_receipts_stamp($date, $time): string
    {
        $date = trim((string)$date);
        $time = trim((string)$time);
        if ($date === '') {
            return '';
        }
        $ts = strtotime($date . ($time !== '' ? ' ' . $time : ''));
        if (!$ts) {
            return '';
        }
        return date('M j', $ts) . ($time !== '' ? ', ' . date('g:i A', $ts) : '');
    }
}

if (!function_exists('ps_receipts_url')) {
    /**
     * Where the push opens. ps_push_send() resolves the url against the folder of the script that is
     * running, and approvals happen in api/ folders, so climb back to the site root first.
     */
    function ps_receipts_url(string $tab): string
    {
        $root = realpath(dirname(__DIR__, 2));
        $dir  = isset($_SERVER['SCRIPT_FILENAME']) ? realpath(dirname((string)$_SERVER['SCRIPT_FILENAME'])) : false;
        $up   = '';
        if ($root && $dir && strpos($dir, $root) === 0) {
            $sub = trim(str_replace('\\', '/', substr($dir, strlen($root))), '/');
            $up  = $sub === '' ? '' : str_repeat('../', count(explode('/', $sub)));
        }
        return $up . 'faculty-dashboard.php?tab=' . $tab;
    }
}

if (!function_exists('ps_receipts_clip')) {
    /** Shortens to $max characters without needing the mbstring extension (the /u flag keeps UTF-8 intact). */
    function ps_receipts_clip(string $s, int $max): string
    {
        if (preg_match_all('/./us', $s) <= $max) {
            return $s;
        }
        return preg_match('/^.{0,' . max(1, $max - 1) . '}/us', $s, $m) ? rtrim($m[0]) . '…' : $s;
    }
}

if (!function_exists('ps_receipts_sweep_equipment')) {
    function ps_receipts_sweep_equipment(mysqli $conn, ?string $facultyId): int
    {
        $sql = "SELECT r.* FROM tbl_requests r
                  LEFT JOIN tbl_approval_receipts a ON a.kind = 'equipment' AND a.ref_id = r.id
                 WHERE a.id IS NULL
                   AND r.status IN ('Approved', 'On Loan', 'Overdue', 'Returned')
                   AND r.request_date >= (NOW() - INTERVAL 45 DAY)"
            . ($facultyId !== null ? ' AND r.faculty_id = ?' : '')
            . ' ORDER BY r.id ASC LIMIT 60';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($facultyId !== null) {
            $stmt->bind_param('s', $facultyId);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!$rows) {
            return 0;
        }

        $claim = $conn->prepare("INSERT IGNORE INTO tbl_approval_receipts (kind, ref_id, faculty_id) VALUES ('equipment', ?, ?)");
        if (!$claim) {
            return 0;
        }
        $groups = [];
        foreach ($rows as $r) {
            $id  = (int)$r['id'];
            $fid = trim((string)($r['faculty_id'] ?? ''));
            $claim->bind_param('is', $id, $fid);
            // Not affected = another request already announced it.
            if (!$claim->execute() || $claim->affected_rows !== 1) {
                continue;
            }
            // Already moved on (overdue / returned): record it, but it is old news, so no push.
            if ($fid === '' || in_array((string)$r['status'], ['Overdue', 'Returned'], true)) {
                continue;
            }
            // Items approved together from one cart are one receipt notification, not one each.
            $batch = trim((string)($r['batch_id'] ?? ''));
            $groups[$fid . '|' . ($batch !== '' ? $batch : '#' . $id)][] = $r;
        }
        $claim->close();

        $sent = 0;
        foreach ($groups as $items) {
          try {
            $first = $items[0];
            $fid   = (string)$first['faculty_id'];
            $names = [];
            foreach ($items as $it) {
                $q       = max(1, (int)($it['borrow_qty'] ?? 1));
                $names[] = (string)$it['equipment_name'] . ($q > 1 ? ' ×' . $q : '');
            }
            $no    = ps_receipt_no('equipment', (int)$first['id']);
            $count = count($items);
            $title = $count === 1
                ? 'Approved · Receipt ' . $no
                : 'Approved · ' . $count . ' items · Receipt ' . $no . ' +' . ($count - 1);
            $from  = ps_receipts_stamp($first['borrow_date'] ?? '', $first['borrow_time'] ?? '');
            $to    = ps_receipts_stamp($first['return_date'] ?? '', $first['return_time'] ?? '');
            $body  = ps_receipts_clip(implode(', ', $names), 90)
                . ($from !== '' ? ' · ' . $from . ($to !== '' ? ' → ' . $to : '') : '')
                . (trim((string)($first['room'] ?? '')) !== '' ? ' · ' . trim((string)$first['room']) : '');
            ps_push_send($conn, 'faculty', $fid, 'equipment', $title, $body, ps_receipts_url('lending'));
            $sent++;
          } catch (Throwable $e) {
            error_log('[PUPSync] approval receipt push (equipment): ' . $e->getMessage());
          }
        }
        return $sent;
    }
}

if (!function_exists('ps_receipts_sweep_rooms')) {
    function ps_receipts_sweep_rooms(mysqli $conn, ?string $facultyId): int
    {
        $sql = "SELECT rr.*, r.room_name
                  FROM tbl_room_reservations rr
                  LEFT JOIN tbl_rooms r ON r.room_id = rr.room_id
                  LEFT JOIN tbl_approval_receipts a ON a.kind = 'room' AND a.ref_id = rr.id
                 WHERE a.id IS NULL
                   AND rr.status = 'Approved'
                   AND rr.request_date >= (NOW() - INTERVAL 45 DAY)
                   AND TIMESTAMP(rr.reservation_date, rr.end_time) >= NOW()"
            . ($facultyId !== null ? ' AND rr.faculty_id = ?' : '')
            . ' ORDER BY rr.id ASC LIMIT 60';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($facultyId !== null) {
            $stmt->bind_param('s', $facultyId);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!$rows) {
            return 0;
        }

        $claim = $conn->prepare("INSERT IGNORE INTO tbl_approval_receipts (kind, ref_id, faculty_id) VALUES ('room', ?, ?)");
        if (!$claim) {
            return 0;
        }
        $sent = 0;
        foreach ($rows as $r) {
            $id  = (int)$r['id'];
            $fid = trim((string)($r['faculty_id'] ?? ''));
            $claim->bind_param('is', $id, $fid);
            if (!$claim->execute() || $claim->affected_rows !== 1 || $fid === '') {
                continue;
            }
            try {
            $start = strtotime(trim((string)$r['reservation_date'] . ' ' . (string)$r['start_time']));
            $end   = strtotime(trim((string)$r['reservation_date'] . ' ' . (string)$r['end_time']));
            $slot  = $start ? date('M j, g:i A', $start) . ($end ? ' – ' . date('g:i A', $end) : '') : '';
            $room  = trim((string)($r['room_name'] ?? '')) !== '' ? trim((string)$r['room_name']) : 'Your room';
            ps_push_send(
                $conn, 'faculty', $fid, 'facilities',
                'Approved · Receipt ' . ps_receipt_no('room', $id),
                $room . ($slot !== '' ? ' · ' . $slot : ''),
                ps_receipts_url('rooms')
            );
            $sent++;
            } catch (Throwable $e) {
                error_log('[PUPSync] approval receipt push (room): ' . $e->getMessage());
            }
        }
        $claim->close();
        return $sent;
    }
}

if (!function_exists('ps_receipts_sweep')) {
    /**
     * Announce every approval that has no receipt yet. $facultyId limits it to one faculty (used when
     * that faculty's own feed is being built); null checks everyone (used right after an admin decision).
     */
    function ps_receipts_sweep(mysqli $conn, ?string $facultyId = null): int
    {
        static $running = false;
        if ($running) {
            return 0;
        }
        $running = true;
        $sent    = 0;
        try {
            date_default_timezone_set('Asia/Manila');
            if (ps_receipts_ensure_table($conn)) {
                $sent += ps_receipts_sweep_equipment($conn, $facultyId);
                $sent += ps_receipts_sweep_rooms($conn, $facultyId);
            }
        } catch (Throwable $e) {
            error_log('[PUPSync] ps_receipts_sweep: ' . $e->getMessage());
        } finally {
            $running = false;
        }
        return $sent;
    }
}
