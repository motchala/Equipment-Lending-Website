<?php

declare(strict_types=1);

/**
 * equipment-booking/core/auto-approve-tick.php
 *
 * Lazy housekeeping for equipment requests that are still Waiting.
 *
 * NOTE: the file and function keep their old name so the existing callers do not change. The old
 * "approve after the buffer time" behaviour is gone: it approved requests without applying the
 * borrowing rules and made the outcome depend on whose page happened to poll first. Now (see
 * request-decision.php for the full rules):
 *
 *   - a same-day request waits in a 10-minute approval buffer (an admin may decide it meanwhile),
 *     then the system approves or declines it (see request-buffer.php);
 *   - a later-date request waits for an admin and, if nobody decides, is DECLINED at
 *     12:01 AM on its borrow date.
 *
 * What this tick does on every poll:
 *   1. decide any same-day request that was missed at submit time (safety net; it is put into its
 *      10-minute buffer; requests sent to an admin on purpose are left alone);
 *   2. settle every same-day request whose 10-minute buffer has ended (core/request-buffer.php):
 *      approved when every item has stock and no overlapping booking, otherwise declined;
 *   3. decline every undecided later-date request whose deadline has passed.
 *
 * Call sites (mirrors BookingSchedule::tick):
 *   - poll-requests-data.php  (admin live-poll)
 *   - activity/core/faculty-data.php -> fact_eq_housekeeping()  (faculty activity poll)
 *
 * The auto_approve_timeout_minutes setting in tbl_arbitration_config is no longer read.
 * Older requests that were auto-approved keep their 'auto_approve_timeout' rule label in the log.
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/request-decision.php';
require_once __DIR__ . '/request-buffer.php';

/**
 * @param mysqli $conn Active DB connection
 */
function equipment_auto_approve_tick(mysqli $conn): void
{
    try {
        BookingSchedule::ensureSchema($conn);
        equipment_decide_stuck($conn);
        eq_buffer_settle($conn);
        equipment_expire_unreviewed($conn);
    } catch (\Throwable $e) {
        error_log('equipment_auto_approve_tick - ' . $e->getMessage());
    }
}
