<?php

declare(strict_types=1);

/**
 * imports-lock.php  (shared helpers)
 *
 * The Imports screen (Super Admin only) used to be locked behind a password step. That lock was
 * removed: Imports is simply open to the Super Admin. The helpers below are kept, always "open", so
 * every import endpoint that calls them keeps working without edits.
 *
 * Who may use Imports is still enforced on the server by each endpoint (signed-in Super Admin +
 * CSRF token); nothing here changes that.
 */

const IMPORTS_UNLOCK_TTL = 600;   // kept only so older callers still find the constant

function imports_is_unlocked(): bool
{
    return true;
}

function imports_remaining(): int
{
    return IMPORTS_UNLOCK_TTL;
}

function imports_unlock(): void
{
    // nothing to do: Imports is always open
}

function imports_touch(): void
{
    // nothing to do: there is no timer any more
}

function imports_lock(): void
{
    // nothing to do: there is no lock any more
}

/** Endpoint guard: kept so existing endpoints need no change; it never blocks. */
function imports_require_unlocked(): void
{
}
