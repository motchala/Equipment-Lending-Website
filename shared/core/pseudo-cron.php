<?php

declare(strict_types=1);

/**
 * shared/core/pseudo-cron.php
 *
 * The project's "cron job" for hosting WITHOUT cron (free InfinityFree): scheduled work runs whenever
 * someone's request is already reaching the server, so a logged-in browser acts as the clock.
 *
 * What one tick does (each part is lazy, idempotent and safe to run any number of times):
 *   - rooms:     ps_room_expire_unreviewed()        - decides same-day faculty requests whose 10-minute
 *                                                     review buffer ended, declines undecided later-date ones
 *   - equipment: equipment_auto_approve_tick()      - same for the equipment buffer / 12:01 AM auto-decline
 *
 * Who calls it:
 *   - sse/api/*.php            every live-update stream loops every 2 s for each logged-in user, so while
 *                              anyone is signed in a due request is decided within seconds
 *   - poll-reservations.php, poll-requests-data.php   the polling fallback when SSE is not available
 *   - shared/cron-tick.php     a URL for a REAL scheduler (hosting with cron, or an outside pinger on a
 *                              host that allows it); it needs CRON_TOKEN in .env
 *
 * ps_pseudo_cron_tick() throttles itself (default once every 5 seconds across ALL users, using the
 * modified time of a stamp file in the system temp folder), so many open pages cost almost nothing.
 * If the stamp file cannot be written it only throttles within the one PHP process.
 *
 * Nothing runs while nobody has any page open and no real scheduler calls shared/cron-tick.php; the
 * statuses are still correct the moment anyone looks, because every read re-checks what is due.
 */

if (!function_exists('ps_pseudo_cron_run')) {
    /**
     * Run every scheduled job once, no throttle.
     *
     * @return string[] names of the jobs that ran
     */
    function ps_pseudo_cron_run(mysqli $conn): array
    {
        $root = dirname(__DIR__, 2);
        $ran  = [];

        $rooms = $root . '/room-reservation/core/room-expiry.php';
        if (is_file($rooms)) {
            try {
                require_once $rooms;
                ps_room_expire_unreviewed($conn);
                $ran[] = 'rooms';
            } catch (Throwable $e) {
                error_log('[PUPSync] pseudo-cron rooms: ' . $e->getMessage());
            }
        }

        $equipment = $root . '/equipment-booking/core/auto-approve-tick.php';
        if (is_file($equipment)) {
            try {
                require_once $equipment;
                equipment_auto_approve_tick($conn);
                $ran[] = 'equipment';
            } catch (Throwable $e) {
                error_log('[PUPSync] pseudo-cron equipment: ' . $e->getMessage());
            }
        }

        return $ran;
    }
}

if (!function_exists('ps_pseudo_cron_tick')) {
    /**
     * Run the jobs unless they already ran in the last $everySeconds (by any user / process).
     * Never throws: housekeeping must not take a page or a live stream down.
     *
     * @return string[] names of the jobs that ran ([] when throttled)
     */
    function ps_pseudo_cron_tick(mysqli $conn, int $everySeconds = 5): array
    {
        static $lastRun = 0;
        try {
            $now = time();
            if ($now - $lastRun < $everySeconds) {
                return [];
            }
            $lastRun = $now;

            $stamp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'pupsync-pseudo-cron.stamp';
            $seen  = @filemtime($stamp);
            if ($seen !== false && $now - $seen < $everySeconds) {
                return [];
            }
            @touch($stamp);

            return ps_pseudo_cron_run($conn);
        } catch (Throwable $e) {
            error_log('[PUPSync] ps_pseudo_cron_tick: ' . $e->getMessage());
            return [];
        }
    }
}
