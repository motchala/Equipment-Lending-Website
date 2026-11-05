<?php

declare(strict_types=1);

/**
 * imports-lock.php  (shared helpers)
 *
 * The "Imports" tab (Settings -> Imports, Super Admin only) is locked until the
 * Super Admin re-enters their own password. The unlock lives in the SERVER session
 * - never in the browser alone - and every endpoint that imports, lists or opens
 * an imported file calls imports_require_unlocked() first, so the lock cannot be
 * bypassed by calling an endpoint directly.
 *
 *   imports.php (action=unlock | lock | status)   opens / closes the unlock
 *   everything else                               imports_require_unlocked()
 *
 * The unlock is sliding: it lasts IMPORTS_UNLOCK_TTL seconds from the last gated
 * request (or from the page's keep-alive ping while the tab is being used).
 */

const IMPORTS_UNLOCK_TTL = 600;   // 10 minutes

function imports_is_unlocked(): bool
{
    return (int)($_SESSION['imports_unlocked_until'] ?? 0) > time();
}

function imports_remaining(): int
{
    return max(0, (int)($_SESSION['imports_unlocked_until'] ?? 0) - time());
}

function imports_unlock(): void
{
    $_SESSION['imports_unlocked_until'] = time() + IMPORTS_UNLOCK_TTL;
}

/** Extends an unlock that is still valid (never opens a closed one). */
function imports_touch(): void
{
    if (imports_is_unlocked()) {
        imports_unlock();
    }
}

function imports_lock(): void
{
    unset($_SESSION['imports_unlocked_until']);
}

/** Endpoint guard: stops the request with a JSON 403 unless the tab is unlocked. */
function imports_require_unlocked(): void
{
    if (!imports_is_unlocked()) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'code'   => 'locked',
            'error'  => 'Imports are locked. Unlock them with your password first.',
        ]);
        exit;
    }
    imports_touch();
}
