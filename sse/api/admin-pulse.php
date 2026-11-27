<?php
/**
 * sse/api/admin-pulse.php — the admin "did anything change?" check (plain HTTP, no streaming)
 *
 * The admin screens call this every few seconds while the page is visible (dashboard/assets/js/admin-live.js).
 * It returns only a handful of short change signatures; the browser compares them with the previous answer
 * and re-fetches just the part that changed (Equipment > Loans, Facilities > Reservations, the Catalog, the
 * activity feed, the dashboard figures).
 *
 * Why it exists next to the SSE stream: a stream needs a host that keeps one PHP process open per page and does
 * not buffer its output. Free shared hosting often does neither, which leaves a page that looks "connected" but
 * never hears anything. A short request every few seconds works on every host, so this is the safety net under
 * the stream; when the stream does fire, the page just asks this endpoint right away.
 *
 * Response: { ok: true, parts: { requests, rooms, inventory, activity }, t: <unix time> }
 *
 * Also runs the scheduled work (throttled, shared by every open page): a same-day request or reservation whose
 * 10-minute review buffer ended is decided - and therefore moves out of Pending - without anyone refreshing.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
session_write_close();   // never hold the session lock: this runs every few seconds next to every other request

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/admin-signature.php';
require_once __DIR__ . '/../../shared/core/pseudo-cron.php';
require_once __DIR__ . '/../../equipment-booking/core/booking-schedule.php';

$conn = getDB();

/** Time-driven status changes (On Loan -> Overdue, uncollected booking -> Cancelled ...), at most once per 10 s for everyone. */
function ps_admin_pulse_booking_tick(mysqli $conn, int $everySeconds = 10): void
{
    try {
        $stamp = rtrim(sys_get_temp_dir(), '/' . chr(92)) . DIRECTORY_SEPARATOR . 'pupsync-pulse-booking.stamp';
        $seen  = @filemtime($stamp);
        if ($seen !== false && time() - $seen < $everySeconds) return;
        @touch($stamp);
        BookingSchedule::tick($conn);
    } catch (Throwable $e) {
        error_log('[PUPSync] admin-pulse booking tick: ' . $e->getMessage());
    }
}

try {
    ps_pseudo_cron_tick($conn);   // decide anything that is due BEFORE reading, so the signature already includes it
    ps_admin_pulse_booking_tick($conn);
    echo json_encode(['ok' => true, 'parts' => ps_admin_live_parts($conn), 't' => time()]);
} catch (Throwable $e) {
    error_log('[PUPSync] admin-pulse: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not check for changes']);
}

$conn->close();
