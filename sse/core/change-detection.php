<?php
/**
 * SSE change-detection helpers.
 *
 * No DB queries, no includes, no business logic — pure signal helpers used
 * by SSE tick loops to decide whether to emit an update event.
 */

/**
 * Returns true when $current differs from $last, and updates $last to $current.
 * Returns false and leaves $last unchanged when they are equal.
 *
 * Properties guaranteed:
 *   P1 — sse_data_changed($any, '')    always returns true  (empty string never equals a real sig)
 *   P2 — sse_data_changed($s, $s)      always returns false (equal strings, no change)
 *   P3 — sse_data_changed($a, $b) where $a !== $b  always returns true and sets $last = $a
 */
function sse_data_changed(string $current, string &$last): bool
{
    if ($current !== $last) {
        $last = $current;
        return true;
    }
    return false;
}

/**
 * Builds a cheap, opaque change signature from an array of scalar values.
 * Uses pipe-delimited concatenation — no hashing, no external calls.
 *
 * Example: sse_make_sig(['2026-11-21 10:00:00', '5']) === '2026-11-21 10:00:00|5'
 */
function sse_make_sig(array $values): string
{
    return implode('|', $values);
}
