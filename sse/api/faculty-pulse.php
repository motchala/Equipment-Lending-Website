<?php
/**
 * sse/api/faculty-pulse.php — the faculty "did anything change?" check (plain HTTP, no streaming)
 *
 * The faculty screens call this every few seconds while the page is visible (dashboard/assets/js/faculty-live.js).
 * It returns only a handful of short change signatures; the browser compares them with the previous answer
 * and re-fetches just the part that changed (My Activity, the notification bell, stock levels, the Home figures).
 *
 * Why it exists next to the SSE streams: a stream needs a host that keeps one PHP process open per page and
 * does not buffer its output. Free shared hosting often does neither, which leaves a page that looks "connected"
 * but never hears anything. A short request every few seconds works on every host, so this is the safety net
 * under the streams; when a stream does fire, the page just asks this endpoint right away.
 *
 * Response: { ok: true, parts: { requests, rooms, notifs, inventory }, t: <unix time> }
 *
 * Also runs the project's pseudo-cron tick (throttled, shared by every open page) so a request whose
 * 10-minute review buffer ended is decided - and therefore shows up as Approved / Declined - without anyone refreshing.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
$uid = (string)$_SESSION['faculty_id'];
session_write_close();   // never hold the session lock: this runs every few seconds next to every other request

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-signature.php';
require_once __DIR__ . '/../../shared/core/pseudo-cron.php';
require_once __DIR__ . '/../../equipment-booking/core/booking-schedule.php';

$conn = getDB();

/**
 * Time-driven status changes (On Loan -> Overdue, an uncollected booking -> Cancelled, a booking that starts now taking
 * its units off the shelf) are written by BookingSchedule::tick(). It used to run only when the faculty page polled the
 * inventory, so with everything else live it could go quiet. Run it here, at most once every 10 s across ALL users
 * (stamp file, same idea as the pseudo-cron), so those changes land in the database and move the signatures below.
 */
function ps_pulse_booking_tick(mysqli $conn, int $everySeconds = 10): void
{
    try {
        $stamp = rtrim(sys_get_temp_dir(), '/' . chr(92)) . DIRECTORY_SEPARATOR . 'pupsync-pulse-booking.stamp';
        $seen  = @filemtime($stamp);
        if ($seen !== false && time() - $seen < $everySeconds) return;
        @touch($stamp);
        BookingSchedule::tick($conn);
    } catch (Throwable $e) {
        error_log('[PUPSync] faculty-pulse booking tick: ' . $e->getMessage());
    }
}

try {
    ps_pseudo_cron_tick($conn);   // decide anything that is due BEFORE reading, so the signature already includes it
    ps_pulse_booking_tick($conn);
    $parts = ps_faculty_live_parts($conn, $uid);
    echo json_encode(['ok' => true, 'parts' => $parts, 't' => time()]);
} catch (Throwable $e) {
    error_log('[PUPSync] faculty-pulse: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not check for changes']);
}

$conn->close();
