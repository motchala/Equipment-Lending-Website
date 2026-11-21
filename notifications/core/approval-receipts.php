<?php

/**
 * notifications/core/approval-receipts.php
 *
 * Helpers for generating human-readable approval receipt numbers and
 * date/time stamps shown in faculty notifications.
 *
 * ps_receipts_sweep() was previously used by the Web Push notification
 * system to fan-out approval push payloads. That system has been removed,
 * so the function is now a no-op stub — it must still exist because
 * equipment-booking/core/request-buffer.php calls it after batch approvals.
 */

if (!function_exists('ps_receipt_no')) {
    /**
     * Format a receipt number from a request/reservation ID.
     *
     * Equipment requests:   #R-0042
     * Room reservations:    #RM-0042
     *
     * The zero-padded four-digit ID matches what the admin sees in the
     * arbitration log and what the previous push notification payload carried.
     *
     * @param string $type  'equipment' or 'room'
     * @param int    $id    The tbl_requests.id or tbl_room_reservations.id value
     * @return string       Formatted receipt number, e.g. '#R-0042' or '#RM-0042'
     */
    function ps_receipt_no(string $type, int $id): string
    {
        $prefix = ($type === 'room') ? 'RM' : 'R';
        return '#' . $prefix . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ps_receipts_stamp')) {
    /**
     * Format a borrow/return date + time pair into a human-readable stamp.
     *
     * Used in notification detail rows where the caller already has separate
     * borrow_date ('Y-m-d') and borrow_time ('H:i:s' or 'H:i') columns and
     * needs a compact display like "Nov 21, 9:00 AM".
     *
     * Returns an empty string when either input is empty so the caller can
     * fall back to its own formatted string without showing a broken value.
     *
     * @param string $date  Date string in 'Y-m-d' format (may be empty)
     * @param string $time  Time string in 'H:i:s' or 'H:i' format (may be empty)
     * @return string       Formatted stamp, e.g. "Nov 21, 9:00 AM", or '' on failure
     */
    function ps_receipts_stamp(string $date, string $time): string
    {
        if ($date === '' || $time === '') {
            return '';
        }

        $ts = strtotime($date . ' ' . $time);
        if ($ts === false) {
            return '';
        }

        return date('M j, g:i A', $ts);
    }
}

if (!function_exists('ps_receipts_sweep')) {
    /**
     * No-op stub — previously sent Web Push approval notifications.
     *
     * The Web Push fan-out was part of the push notification system that
     * has since been removed from this codebase. This stub preserves the
     * call site in request-buffer.php without causing a fatal error.
     *
     * @param mysqli $conn  Database connection (accepted but not used)
     */
    function ps_receipts_sweep(mysqli $conn): void
    {
        // Web Push system removed. No action required.
    }
}
