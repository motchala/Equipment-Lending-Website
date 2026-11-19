<?php

declare(strict_types=1);

/**
 * reports/core/data.php
 *
 * Data layer for the Generated Reports feature.
 * One function per report type — each returns the same envelope:
 *
 *   [
 *     'title'        => string,          // human-readable report name
 *     'date_range'   => string,          // "Jan 1 – Dec 31, 2026" or "All time"
 *     'cols'         => string[],        // column headers
 *     'rows'         => array[],         // parallel arrays matching cols
 *     'generated_at' => string,          // Asia/Manila timestamp
 *   ]
 *
 * Rules:
 *   - No HTML, no rendering, no session access.
 *   - $from / $to are validated YYYY-MM-DD strings or null (= no filter).
 *   - Every query uses prepared statements.
 *   - Caller: reports/api/generate.php
 */

date_default_timezone_set('Asia/Manila');

// ── Allowed report type keys (used by the API for whitelist validation) ───────
const RPT_TYPES = [
    'most_borrowed',
    'inventory_status',
    'borrowing_by_faculty',
    'damage_reports',
    'room_summary',
    'overdue_history',
];

// ── Shared helpers ─────────────────────────────────────────────────────────────

function rpt_date_label(?string $from, ?string $to): string
{
    if ($from === null && $to === null) {
        return 'All time';
    }
    $fmt = static function (string $d): string {
        $ts = strtotime($d);
        return $ts ? date('M j, Y', $ts) : $d;
    };
    if ($from !== null && $to !== null) {
        return $fmt($from) . ' – ' . $fmt($to);
    }
    return $from !== null ? 'From ' . $fmt($from) : 'Up to ' . $fmt($to ?? '');
}

/** Append WHERE / AND clauses for a date column. Returns the clause fragment + bound params. */
function rpt_date_clauses(string $col, ?string $from, ?string $to, bool $first = true): array
{
    $parts  = [];
    $types  = '';
    $values = [];

    if ($from !== null) {
        $parts[]  = "$col >= ?";
        $types   .= 's';
        $values[] = $from . ' 00:00:00';
    }
    if ($to !== null) {
        $parts[]  = "$col <= ?";
        $types   .= 's';
        $values[] = $to . ' 23:59:59';
    }

    if (!$parts) {
        return ['sql' => '', 'types' => '', 'values' => []];
    }

    $kw  = $first ? 'WHERE' : 'AND';
    $sql = $kw . ' ' . implode(' AND ', $parts);
    return ['sql' => $sql, 'types' => $types, 'values' => $values];
}

function rpt_envelope(string $title, ?string $from, ?string $to, array $cols, array $rows): array
{
    return [
        'title'        => $title,
        'date_range'   => rpt_date_label($from, $to),
        'cols'         => $cols,
        'rows'         => $rows,
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}

// ── Report functions ───────────────────────────────────────────────────────────

function rpt_most_borrowed(mysqli $conn, ?string $from, ?string $to): array
{
    $dc   = rpt_date_clauses('r.request_date', $from, $to);
    $sql  = "SELECT r.equipment_name,
                    COUNT(*)                    AS borrow_count,
                    COUNT(DISTINCT r.faculty_id) AS unique_borrowers
               FROM tbl_requests r
              WHERE r.status IN ('Returned','On Loan','Overdue')
                " . ($dc['sql'] ? str_replace('WHERE', 'AND', $dc['sql']) : '') . "
           GROUP BY r.equipment_name
           ORDER BY borrow_count DESC, r.equipment_name ASC
           LIMIT 100";

    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt) {
        if ($dc['types']) {
            $stmt->bind_param($dc['types'], ...$dc['values']);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['equipment_name'],
                (int)$r['borrow_count'],
                (int)$r['unique_borrowers'],
            ];
        }
        $stmt->close();
    }

    return rpt_envelope(
        'Most Borrowed Equipment', $from, $to,
        ['Equipment', 'Total Item Borrowed', 'No. of Borrowers'],
        $rows
    );
}

function rpt_inventory_status(mysqli $conn, ?string $from, ?string $to): array
{
    // Date range is intentionally ignored for inventory — it reflects the current state.
    // GROUP BY item_name prevents duplicate rows when tbl_inventory has multiple
    // entries for the same item name (e.g. from multiple add operations).
    $res  = $conn->query(
        "SELECT item_name,
                MAX(category)                              AS category,
                SUM(quantity)                              AS quantity,
                MAX(`condition`)                           AS `condition`,
                IF(MAX(is_archived) = 1, 'Archived', 'Active') AS status
           FROM tbl_inventory
          GROUP BY item_name
          ORDER BY MAX(is_archived) ASC, MAX(category) ASC, item_name ASC"
    );
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['item_name'],
                $r['category'] ?? '—',
                (int)$r['quantity'],
                $r['condition'] ?? '—',
                $r['status'],
            ];
        }
    }

    return rpt_envelope(
        'Inventory Status', $from, $to,
        ['Item', 'Category', 'Quantity', 'Condition', 'Status'],
        $rows
    );
}

function rpt_borrowing_by_faculty(mysqli $conn, ?string $from, ?string $to): array
{
    $dc  = rpt_date_clauses('r.request_date', $from, $to);
    $sql = "SELECT r.faculty_name,
                   COUNT(*)                                              AS total_borrows,
                   SUM(r.status = 'Returned')                           AS returned,
                   SUM(r.status IN ('Overdue','Returned')
                       AND r.status = 'Overdue')                        AS currently_overdue,
                   SUM(r.arbitration_rule = 'rule_overdue_block'
                       OR r.status = 'Overdue')                         AS overdue_incidents
              FROM tbl_requests r
             WHERE r.status IN ('Returned','On Loan','Overdue','Approved')
               " . ($dc['sql'] ? str_replace('WHERE', 'AND', $dc['sql']) : '') . "
          GROUP BY r.faculty_id, r.faculty_name
          ORDER BY total_borrows DESC, r.faculty_name ASC
          LIMIT 200";

    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt) {
        if ($dc['types']) {
            $stmt->bind_param($dc['types'], ...$dc['values']);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['faculty_name'],
                (int)$r['total_borrows'],
                (int)$r['returned'],
                (int)$r['overdue_incidents'],
            ];
        }
        $stmt->close();
    }

    return rpt_envelope(
        'Borrowing Activity by Faculty', $from, $to,
        ['Faculty', 'Total Item Borrowed', 'Returned', 'Overdue Incidents'],
        $rows
    );
}

function rpt_damage_reports(mysqli $conn, ?string $from, ?string $to): array
{
    $dc  = rpt_date_clauses('r.returned_at', $from, $to);
    $sql = "SELECT r.faculty_name,
                   r.equipment_name,
                   COALESCE(r.return_condition, l.reason, '—') AS reported_condition,
                   DATE(COALESCE(r.returned_at, r.request_date)) AS return_date
              FROM tbl_requests r
              LEFT JOIN tbl_arbitration_log l ON l.request_id = r.id
                    AND l.rule_applied IN ('manual_return','qr_return','partial_return')
             WHERE r.status = 'Returned'
               AND (
                     r.return_condition IN ('Damaged','Missing')
                  OR l.reason LIKE '%Damaged%'
                  OR l.reason LIKE '%Missing%'
               )
               " . ($dc['sql'] ? str_replace('WHERE', 'AND', $dc['sql']) : '') . "
          GROUP BY r.id
          ORDER BY return_date DESC
          LIMIT 200";

    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt) {
        if ($dc['types']) {
            $stmt->bind_param($dc['types'], ...$dc['values']);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['faculty_name'],
                $r['equipment_name'],
                $r['reported_condition'],
                $r['return_date'] ?? '—',
            ];
        }
        $stmt->close();
    }

    return rpt_envelope(
        'Students / Faculty Who Reported Damage', $from, $to,
        ['Faculty / Student', 'Equipment', 'Condition', 'Return Date'],
        $rows
    );
}

function rpt_room_summary(mysqli $conn, ?string $from, ?string $to): array
{
    $dc  = rpt_date_clauses('rv.request_date', $from, $to);
    $sql = "SELECT rm.room_name,
                   COALESCE(b.name, '—')           AS building,
                   SUM(rv.status = 'Approved')     AS approved,
                   SUM(rv.status = 'Declined')     AS declined,
                   SUM(rv.status = 'Cancelled')    AS cancelled,
                   COUNT(*)                        AS total
              FROM tbl_room_reservations rv
              JOIN tbl_rooms rm ON rm.room_id = rv.room_id
              LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
             " . $dc['sql'] . "
          GROUP BY rv.room_id
          ORDER BY total DESC, rm.room_name ASC
          LIMIT 200";

    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt) {
        if ($dc['types']) {
            $stmt->bind_param($dc['types'], ...$dc['values']);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['room_name'],
                $r['building'],
                (int)$r['approved'],
                (int)$r['declined'],
                (int)$r['cancelled'],
                (int)$r['total'],
            ];
        }
        $stmt->close();
    }

    return rpt_envelope(
        'Room Reservation Summary', $from, $to,
        ['Room', 'Building', 'Approved', 'Declined', 'Cancelled', 'Total'],
        $rows
    );
}

function rpt_overdue_history(mysqli $conn, ?string $from, ?string $to): array
{
    $dc  = rpt_date_clauses('r.borrow_time', $from, $to);
    $sql = "SELECT r.faculty_name,
                   r.equipment_name,
                   DATE(r.borrow_time)             AS borrow_date,
                   DATE(r.return_time)             AS due_date,
                   CASE
                     WHEN r.status = 'Overdue'
                       THEN DATEDIFF(NOW(), r.return_time)
                     WHEN r.status = 'Returned' AND r.returned_at > r.return_time
                       THEN DATEDIFF(r.returned_at, r.return_time)
                     ELSE 0
                   END AS days_overdue
              FROM tbl_requests r
             WHERE r.status IN ('Overdue','Returned')
               AND (
                     r.status = 'Overdue'
                  OR (r.status = 'Returned' AND r.returned_at > r.return_time)
               )
               " . ($dc['sql'] ? str_replace('WHERE', 'AND', $dc['sql']) : '') . "
          ORDER BY days_overdue DESC, r.borrow_time DESC
          LIMIT 200";

    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt) {
        if ($dc['types']) {
            $stmt->bind_param($dc['types'], ...$dc['values']);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['faculty_name'],
                $r['equipment_name'],
                $r['borrow_date'] ?? '—',
                $r['due_date']    ?? '—',
                max(0, (int)$r['days_overdue']),
            ];
        }
        $stmt->close();
    }

    return rpt_envelope(
        'Overdue History', $from, $to,
        ['Faculty', 'Equipment', 'Borrow Date', 'Due Date', 'Days Overdue'],
        $rows
    );
}

/** Dispatch: maps a report_type string to the right function. Returns null on unknown type. */
function rpt_generate(mysqli $conn, string $type, ?string $from, ?string $to): ?array
{
    switch ($type) {
        case 'most_borrowed':       return rpt_most_borrowed($conn, $from, $to);
        case 'inventory_status':    return rpt_inventory_status($conn, $from, $to);
        case 'borrowing_by_faculty':return rpt_borrowing_by_faculty($conn, $from, $to);
        case 'damage_reports':      return rpt_damage_reports($conn, $from, $to);
        case 'room_summary':        return rpt_room_summary($conn, $from, $to);
        case 'overdue_history':     return rpt_overdue_history($conn, $from, $to);
        default:                    return null;
    }
}
