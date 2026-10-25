<?php

/**
 * admin-activity-feed.php — the ONE place that builds the activity timeline.
 *
 * Used by:
 *   • api/admin-activity.php  — the Activity page, the Admins-panel timeline and the CSV export
 *   • admin-dashboard.php     — the "Recent activity" card on the Dashboard
 *
 * Sources, merged newest-first:
 *   • tbl_admin_activity        manual actions (always has the admin's id)
 *   • tbl_arbitration_log       AUTOMATIC request decisions (override_by empty)
 *   • tbl_room_arbitration_log  AUTOMATIC room-reservation decisions
 *
 * Who sees what:
 *   • Super Admin — everything.
 *   • Admin       — request and room activity from everyone (decisions are shared),
 *                   plus everything they did themselves. Other admins' sign-ins,
 *                   equipment edits, account and settings changes stay private.
 */

require_once __DIR__ . '/admin-activity.php';

if (!function_exists('adm_act_categories')) {
    /** Filter chip → tbl_admin_activity categories ('decisions' is internal: requests + rooms). */
    function adm_act_categories(string $filter): array
    {
        switch ($filter) {
            case 'requests':
                return ['request'];
            case 'rooms':
                return ['room'];
            case 'decisions':
                return ['request', 'room'];
            case 'equipment':
                return ['equipment'];
            case 'system':
                return ['account', 'faculty', 'settings'];
            case 'signins':
                return ['login'];
            default:
                return ['request', 'room', 'equipment', 'account', 'faculty', 'settings', 'login'];
        }
    }

    /** Category → [chip key shown in the UI, tab the item belongs to]. */
    function adm_act_category_meta(string $category): array
    {
        switch ($category) {
            case 'request':
                return ['requests', 'requests'];
            case 'room':
                return ['rooms', 'rooms'];
            case 'equipment':
                return ['equipment', 'inventory'];
            case 'account':
                return ['system', 'admins'];
            case 'faculty':
                return ['system', 'faculty'];
            case 'settings':
                return ['system', 'settings'];
            default:
                return ['signins', ''];
        }
    }

    /** [material icon, tone] for an event. */
    function adm_act_style(string $category, ?string $action, string $summary): array
    {
        $by_action = [
            'request_approved' => ['check_circle', 'ok'],
            'request_declined' => ['cancel', 'bad'],
            'request_returned' => ['assignment_return', 'ok'],
            'room_cancelled'   => ['event_busy', 'warn'],
            'issue_resolved'   => ['task_alt', 'ok'],
            'issue_dismissed'  => ['do_not_disturb_on', 'neutral'],
        ];
        if ($action !== null && isset($by_action[$action])) {
            return $by_action[$action];
        }
        if (stripos($summary, 'delet') !== false)  return ['delete', 'bad'];
        if (stripos($summary, 'unlocked') === 0)   return ['lock_open', 'neutral'];
        if (stripos($summary, 'locked') === 0)     return ['lock', 'warn'];
        if (stripos($summary, 'archived') === 0)   return ['archive', 'neutral'];

        $by_category = [
            'login'     => 'login',
            'request'   => 'assignment',
            'room'      => 'meeting_room',
            'equipment' => 'inventory_2',
            'account'   => 'manage_accounts',
            'faculty'   => 'group',
            'settings'  => 'tune',
        ];
        return [$by_category[$category] ?? 'history', 'neutral'];
    }

    /** Human label for an engine rule id. */
    function adm_act_rule_label(?string $rule): string
    {
        $rule = (string)$rule;
        $known = [
            'rule_1_fifo'            => 'First-come, first-served',
            'rule_5_id_order'        => 'ID order',
            'rule_overdue_block'     => 'Overdue borrower',
            'rule_out_of_stock'      => 'Out of stock',
            'rule_missing_doc_hold'  => 'Missing document',
            'rule_missing_doc_block' => 'Missing document',
            'rule_duplicate_block'   => 'Duplicate request',
            'rule_archived'          => 'Item archived',
            'rule_past_datetime'     => 'Date already passed',
            'faculty_cancelled'      => 'Cancelled by the faculty member',
        ];
        if (isset($known[$rule])) {
            return $known[$rule];
        }
        if ($rule === '') {
            return '';
        }
        $pretty = trim(preg_replace('/^rule[_\s]*\d*[_\s]*/i', '', str_replace('_', ' ', $rule)));
        return $pretty === '' ? '' : ucfirst($pretty);
    }

    /** Run a prepared SELECT with dynamic parameters and return all rows. */
    function adm_act_rows(mysqli $conn, string $sql, string $types, array $params): array
    {
        $rows = [];
        try {
            $st = $conn->prepare($sql);
            if (!$st) {
                error_log('[admin-activity] prepare failed: ' . $conn->error);
                return $rows;
            }
            if ($types !== '') {
                $st->bind_param($types, ...$params);
            }
            $st->execute();
            $res = $st->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $rows[] = $r;
            }
            $st->close();
        } catch (Throwable $e) {
            error_log('[admin-activity] ' . $e->getMessage());
        }
        return $rows;
    }
}

if (!function_exists('adm_act_specs')) {
    /**
     * The three sources as query specs, so the same filters drive both the page of rows
     * (admin_activity_feed) and the total (admin_activity_count).
     *
     * @return array<int, array{from:string, count_from:string, where:string, types:string, params:array, cols:string, order:string, map:callable}>
     */
    function adm_act_specs(array $q): array
    {
        $viewer     = (int)($q['viewer_id'] ?? 0);
        $is_super   = !empty($q['is_super']);
        $actor_mode = (string)($q['actor_mode'] ?? 'all');
        $actor_id   = (int)($q['actor_id'] ?? 0);
        $filter     = (string)($q['filter'] ?? 'all');
        $since      = isset($q['since']) ? (int)$q['since'] : null;
        $specs      = [];

        // ── Source 1: manual actions (tbl_admin_activity) ────────────────────────
        if ($actor_mode !== 'auto') {
            $cats   = adm_act_categories($filter);
            $where  = ['t.category IN (' . implode(',', array_fill(0, count($cats), '?')) . ')'];
            $types  = str_repeat('s', count($cats));
            $params = $cats;
            if ($actor_mode === 'admin') {
                $where[]  = 't.admin_id = ?';
                $types   .= 'i';
                $params[] = $actor_id;
            }
            if ($since !== null) {
                $where[]  = 't.created_at >= FROM_UNIXTIME(?)';
                $types   .= 'i';
                $params[] = $since;
            }
            if (!$is_super) {
                // Decisions are shared; everything else is private to the admin who did it.
                $where[]  = "(t.category IN ('request','room') OR t.admin_id = ?)";
                $types   .= 'i';
                $params[] = $viewer;
            }
            $specs[] = [
                'from'       => 'tbl_admin_activity t LEFT JOIN tbl_accounts a ON a.id = t.admin_id',
                'count_from' => 'tbl_admin_activity t',
                'where'      => implode(' AND ', $where),
                'types'      => $types,
                'params'     => $params,
                'cols'       => 't.admin_id, COALESCE(a.fullName, t.admin_name) AS actor_name, t.category, t.action, t.summary, UNIX_TIMESTAMP(t.created_at) AS ts',
                'order'      => 't.created_at DESC, t.id DESC',
                'map'        => static function (array $r): array {
                    [$chip, $tab]  = adm_act_category_meta($r['category']);
                    [$icon, $tone] = adm_act_style($r['category'], $r['action'], (string)$r['summary']);
                    return [
                        'ts'         => (int)$r['ts'],
                        'actor_type' => 'admin',
                        'actor_id'   => (int)$r['admin_id'],
                        'actor_name' => (string)$r['actor_name'],
                        'category'   => $chip,
                        'icon'       => $icon,
                        'tone'       => $tone,
                        'text'       => (string)$r['summary'],
                        'meta'       => '',
                        'tab'        => $tab,
                    ];
                },
            ];
        }

        // ── Source 2: automatic request decisions (tbl_arbitration_log) ──────────
        if ($actor_mode !== 'admin' && in_array($filter, ['all', 'requests', 'decisions'], true)) {
            $where  = ["(override_by IS NULL OR override_by = '')"];
            $types  = '';
            $params = [];
            if ($since !== null) {
                $where[]  = 'created_at >= FROM_UNIXTIME(?)';
                $types   .= 'i';
                $params[] = $since;
            }
            $specs[] = [
                'from'       => 'tbl_arbitration_log',
                'count_from' => 'tbl_arbitration_log',
                'where'      => implode(' AND ', $where),
                'types'      => $types,
                'params'     => $params,
                'cols'       => 'request_id, borrower_name, equipment_name, decision, rule_applied, UNIX_TIMESTAMP(created_at) AS ts',
                'order'      => 'created_at DESC, id DESC',
                'map'        => static function (array $r): array {
                    $d    = (string)$r['decision'];
                    $who  = trim((string)$r['borrower_name']);
                    $text = $d . ' request #' . (int)$r['request_id'] . ': ' . trim((string)$r['equipment_name']) . ($who !== '' ? ' for ' . $who : '');
                    [$icon, $tone] = $d === 'Approved' ? ['check_circle', 'ok'] : ($d === 'Declined' ? ['cancel', 'bad'] : ($d === 'Cancelled' ? ['event_busy', 'neutral'] : ['assignment', 'neutral']));
                    // A cancellation is the faculty member's own action, so it is shown as theirs
                    $byFaculty = (string)$r['rule_applied'] === 'faculty_cancelled' && $who !== '';
                    return [
                        'ts'         => (int)$r['ts'],
                        'actor_type' => 'auto',
                        'actor_id'   => 0,
                        'actor_name' => $byFaculty ? $who : 'Automatic',
                        'category'   => 'requests',
                        'icon'       => $icon,
                        'tone'       => $tone,
                        'text'       => $text,
                        'meta'       => adm_act_rule_label($r['rule_applied']),
                        'tab'        => 'requests',
                    ];
                },
            ];
        }

        // ── Source 3: automatic room-reservation decisions ───────────────────────
        if ($actor_mode !== 'admin' && in_array($filter, ['all', 'rooms', 'decisions'], true)) {
            $where  = ['1 = 1'];
            $types  = '';
            $params = [];
            if ($since !== null) {
                $where[]  = 'created_at >= FROM_UNIXTIME(?)';
                $types   .= 'i';
                $params[] = $since;
            }
            $specs[] = [
                'from'       => 'tbl_room_arbitration_log',
                'count_from' => 'tbl_room_arbitration_log',
                'where'      => implode(' AND ', $where),
                'types'      => $types,
                'params'     => $params,
                'cols'       => 'reservation_id, room_name, borrower_name, decision, rule_applied, UNIX_TIMESTAMP(created_at) AS ts',
                'order'      => 'created_at DESC, id DESC',
                'map'        => static function (array $r): array {
                    $d    = (string)$r['decision'];
                    $who  = trim((string)$r['borrower_name']);
                    $text = $d . ' reservation #' . (int)$r['reservation_id'] . ': ' . trim((string)$r['room_name']) . ($who !== '' ? ' for ' . $who : '');
                    [$icon, $tone] = $d === 'Approved' ? ['event_available', 'ok'] : ($d === 'Declined' ? ['event_busy', 'bad'] : ($d === 'Cancelled' ? ['event_busy', 'neutral'] : ['meeting_room', 'neutral']));
                    $byFaculty = (string)$r['rule_applied'] === 'faculty_cancelled' && $who !== '';
                    return [
                        'ts'         => (int)$r['ts'],
                        'actor_type' => 'auto',
                        'actor_id'   => 0,
                        'actor_name' => $byFaculty ? $who : 'Automatic',
                        'category'   => 'rooms',
                        'icon'       => $icon,
                        'tone'       => $tone,
                        'text'       => $text,
                        'meta'       => adm_act_rule_label($r['rule_applied']),
                        'tab'        => 'rooms',
                    ];
                },
            ];
        }

        // ── Source 4: extensions of borrowed equipment (tbl_request_events) ──────
        // One row per extension granted to a faculty member (equipment-booking/core/borrow-extension.php).
        // Shown amber because every extension happens after the loan was already due.
        if ($actor_mode !== 'admin' && in_array($filter, ['all', 'requests', 'decisions'], true)) {
            $where  = ["`event` = 'extended'"];
            $types  = '';
            $params = [];
            if ($since !== null) {
                $where[]  = 'created_at >= FROM_UNIXTIME(?)';
                $types   .= 'i';
                $params[] = $since;
            }
            $specs[] = [
                'from'       => 'tbl_request_events',
                'count_from' => 'tbl_request_events',
                'where'      => implode(' AND ', $where),
                'types'      => $types,
                'params'     => $params,
                'cols'       => 'request_id, borrower_name, equipment_name, detail, UNIX_TIMESTAMP(created_at) AS ts',
                'order'      => 'created_at DESC, id DESC',
                'map'        => static function (array $r): array {
                    $who = trim((string)$r['borrower_name']);
                    return [
                        'ts'         => (int)$r['ts'],
                        'actor_type' => 'auto',
                        'actor_id'   => 0,
                        'actor_name' => $who !== '' ? $who : 'Faculty',
                        'category'   => 'requests',
                        'icon'       => 'update',
                        'tone'       => 'warn',
                        'text'       => 'Extended request #' . (int)$r['request_id'] . ': ' . trim((string)$r['equipment_name']) . ($who !== '' ? ' for ' . $who : ''),
                        'meta'       => (string)$r['detail'],
                        'tab'        => 'requests',
                    ];
                },
            ];
        }
        return $specs;
    }
}

if (!function_exists('admin_activity_count')) {
    /** How many events match (same filters and visibility as admin_activity_feed). */
    function admin_activity_count(mysqli $conn, array $q): int
    {
        admin_activity_bootstrap($conn);
        $total = 0;
        foreach (adm_act_specs($q) as $spec) {
            if (in_array($spec['from'], ['tbl_room_arbitration_log', 'tbl_request_events'], true) && !admin_activity_table_exists($conn, $spec['from'])) {
                continue;
            }
            $rows = adm_act_rows($conn, 'SELECT COUNT(*) AS c FROM ' . $spec['count_from'] . ' WHERE ' . $spec['where'], $spec['types'], $spec['params']);
            $total += (int)($rows[0]['c'] ?? 0);
        }
        return $total;
    }
}

if (!function_exists('admin_activity_feed')) {
    /**
     * One page of the timeline, newest first.
     *
     * @param array $q {
     *   viewer_id  int     the signed-in admin's account id
     *   is_super   bool
     *   actor_mode string  all | auto | admin
     *   actor_id   int     used when actor_mode = admin
     *   filter     string  all | requests | rooms | equipment | system | signins | decisions
     *   since      ?int    unix timestamp lower bound (null = no limit)
     *   offset     int
     *   limit      int
     * }
     * @return array{items: array<int,array>, has_more: bool}
     */
    function admin_activity_feed(mysqli $conn, array $q): array
    {
        admin_activity_bootstrap($conn);

        $offset = max(0, (int)($q['offset'] ?? 0));
        $limit  = max(1, (int)($q['limit'] ?? 15));
        $want   = $offset + $limit + 1; // enough from each source to know whether more exists
        $items  = [];

        foreach (adm_act_specs($q) as $spec) {
            if (in_array($spec['from'], ['tbl_room_arbitration_log', 'tbl_request_events'], true) && !admin_activity_table_exists($conn, $spec['from'])) {
                continue;
            }
            $sql = 'SELECT ' . $spec['cols'] . ' FROM ' . $spec['from'] . ' WHERE ' . $spec['where']
                . ' ORDER BY ' . $spec['order'] . ' LIMIT ?';
            foreach (adm_act_rows($conn, $sql, $spec['types'] . 'i', array_merge($spec['params'], [$want])) as $r) {
                $items[] = ($spec['map'])($r);
            }
        }

        usort($items, static function ($a, $b) {
            return $b['ts'] <=> $a['ts'];
        });

        return [
            'items'    => array_slice($items, $offset, $limit),
            'has_more' => count($items) > $offset + $limit,
        ];
    }
}

if (!function_exists('admin_request_decisions')) {
    /**
     * Who approved / declined / returned each request — for the "Approved by" and
     * "Returned by" columns in Approvals.
     *
     * @param  int[] $request_ids
     * @return array<int, array{approved?:array, declined?:array, returned?:array}>
     *         each entry: ['by' => name, 'auto' => bool, 'rule' => label, 'ts' => unix]
     */
    function admin_request_decisions(mysqli $conn, array $request_ids): array
    {
        $ids = [];
        foreach ($request_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (!$ids) {
            return [];
        }
        admin_activity_bootstrap($conn);
        $in  = implode(',', $ids); // integers only — safe to inline
        $map = [];

        // Manual: latest row per action wins.
        $rows = adm_act_rows(
            $conn,
            "SELECT t.ref_id, t.action, COALESCE(a.fullName, t.admin_name) AS name, UNIX_TIMESTAMP(t.created_at) AS ts
               FROM tbl_admin_activity t
               LEFT JOIN tbl_accounts a ON a.id = t.admin_id
              WHERE t.ref_type = 'request' AND t.ref_id IN ($in)
                AND t.action IN ('request_approved','request_declined','request_returned')
              ORDER BY t.created_at ASC, t.id ASC",
            '',
            []
        );
        foreach ($rows as $r) {
            $key = substr((string)$r['action'], strlen('request_')); // approved | declined | returned
            $map[(int)$r['ref_id']][$key] = ['by' => (string)$r['name'], 'auto' => false, 'rule' => '', 'ts' => (int)$r['ts']];
        }

        // Automatic: only where no admin decided it.
        $rows = adm_act_rows(
            $conn,
            "SELECT request_id, decision, rule_applied, UNIX_TIMESTAMP(created_at) AS ts
               FROM tbl_arbitration_log
              WHERE request_id IN ($in) AND (override_by IS NULL OR override_by = '')",
            '',
            []
        );
        foreach ($rows as $r) {
            $key = $r['decision'] === 'Approved' ? 'approved' : ($r['decision'] === 'Declined' ? 'declined' : '');
            $rid = (int)$r['request_id'];
            if ($key !== '' && !isset($map[$rid][$key])) {
                $map[$rid][$key] = ['by' => 'Automatic', 'auto' => true, 'rule' => adm_act_rule_label($r['rule_applied']), 'ts' => (int)$r['ts']];
            }
        }
        return $map;
    }
}

if (!function_exists('admin_decider_html')) {
    /** Table-cell content for one decision (see admin_request_decisions). */
    function admin_decider_html(?array $d): string
    {
        $h = static function ($v): string {
            return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        };
        if (!$d) {
            return '<span class="ps-decider-none">&mdash;</span>';
        }
        if (!empty($d['auto'])) {
            return '<span class="ps-decider"><span class="ps-decider-name ps-decider-name--auto">'
                . '<span class="material-symbols-outlined" aria-hidden="true">smart_toy</span>Automatic</span>'
                . ($d['rule'] !== '' ? '<span class="ps-decider-sub">' . $h($d['rule']) . '</span>' : '')
                . '</span>';
        }
        return '<span class="ps-decider"><span class="ps-decider-name">' . $h($d['by']) . '</span>'
            . '<span class="ps-decider-sub">' . $h(date('M j, g:i A', (int)$d['ts'])) . '</span></span>';
    }
}

if (!function_exists('admin_activity_my_summary')) {
    /** The signed-in admin's own numbers for the last 30 days (My Account → Activity Summary). */
    function admin_activity_my_summary(mysqli $conn, int $admin_id): array
    {
        $out = ['approved' => 0, 'declined' => 0, 'returns' => 0, 'actions' => 0];
        if ($admin_id <= 0) {
            return $out;
        }
        admin_activity_bootstrap($conn);
        $rows = adm_act_rows(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(action = 'request_approved') AS approved,
                    SUM(action = 'request_declined') AS declined,
                    SUM(action = 'request_returned') AS returns
               FROM tbl_admin_activity
              WHERE admin_id = ? AND created_at >= (NOW() - INTERVAL 30 DAY)",
            'i',
            [$admin_id]
        );
        if ($rows) {
            $out = [
                'approved' => (int)$rows[0]['approved'],
                'declined' => (int)$rows[0]['declined'],
                'returns'  => (int)$rows[0]['returns'],
                'actions'  => (int)$rows[0]['total'],
            ];
        }
        return $out;
    }
}
