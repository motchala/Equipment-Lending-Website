<?php

/**
 * grouping.php
 *
 * Shared by the admin and faculty notification feeds (admin-data.php, faculty-data.php).
 *
 * Notifications are computed live, one per request / reservation / item. When several of them belong to
 * ONE stream (the items of one borrow request, the overdue items of one borrower, ...) the feed shows a
 * single merged notification instead. A merged notification:
 *   - has its own key (see notif_group_key) that passes the same key rules as any other,
 *   - lists the keys of everything inside it in 'keys',
 *   - is read only when everything inside it is read,
 *   - and marking it read / deleting it applies to everything inside it (notif_expand_keys).
 */

if (!function_exists('notif_group_key')) {
    /** "greqapproved-12": a letters-only prefix plus the lowest numeric id among the members. */
    function notif_group_key(string $prefix, array $members): string
    {
        $ids = [];
        foreach ($members as $m) {
            if (preg_match('/-(\d+)$/', (string)($m['key'] ?? ''), $x)) {
                $ids[] = (int)$x[1];
            }
        }
        return preg_replace('/[^a-z]/', '', $prefix) . '-' . ($ids ? min($ids) : 0);
    }
}

if (!function_exists('notif_group_merge')) {
    /**
     * Merges items that share the same non-empty 'merge' id into one notification, built by
     * $compose(array $members): array. A merge id used by a single item leaves that item untouched.
     * The merged notification takes the position of its first member.
     */
    function notif_group_merge(array $items, callable $compose): array
    {
        $buckets = [];
        foreach ($items as $i => $it) {
            $m = (string)($it['merge'] ?? '');
            if ($m !== '') {
                $buckets[$m][] = $i;
            }
        }

        $out  = [];
        $done = [];
        foreach ($items as $it) {
            $m = (string)($it['merge'] ?? '');
            if ($m === '' || count($buckets[$m]) < 2) {
                unset($it['merge']);
                $out[] = $it;
                continue;
            }
            if (isset($done[$m])) {
                continue;
            }
            $done[$m] = true;

            $members = array_map(fn($j) => $items[$j], $buckets[$m]);
            $g = $compose($members);
            $g['keys']       = array_values(array_map(fn($x) => (string)$x['key'], $members));
            $g['is_read']    = !in_array(false, array_map(fn($x) => !empty($x['is_read']), $members), true);
            $g['is_deleted'] = !in_array(false, array_map(fn($x) => !empty($x['is_deleted']), $members), true);
            unset($g['merge']);
            $out[] = $g;
        }
        return $out;
    }
}

if (!function_exists('notif_expand_keys')) {
    /**
     * key => the real keys behind it (a merged notification maps to all its members, any other to itself),
     * from one or more feeds; the first feed that has a key wins.
     *
     * @return array<string, string[]>
     */
    function notif_expand_keys(array ...$feeds): array
    {
        $map = [];
        foreach ($feeds as $feed) {
            foreach ($feed as $it) {
                $k = (string)($it['key'] ?? '');
                if ($k !== '' && !isset($map[$k])) {
                    $map[$k] = !empty($it['keys']) && is_array($it['keys']) ? array_values($it['keys']) : [$k];
                }
            }
        }
        return $map;
    }
}

if (!function_exists('notif_plural')) {
    function notif_plural(int $n, string $one, ?string $many = null): string
    {
        return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
    }
}

if (!function_exists('notif_clip')) {
    /** Trim long free text to one short line. */
    function notif_clip(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if (function_exists('mb_strimwidth')) return mb_strimwidth($s, 0, $max, '…');
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
    }
}
