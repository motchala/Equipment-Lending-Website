<?php
require_once __DIR__ . '/site-paths.php';
// application error disclosure vulnerability prevention 
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// csp vulnerability fix: generate a nonce for inline scripts and styles, and include it in the CSP header
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

require_once PS_ROOT . '/config/session.php';
require_once PS_ROOT . '/config/csrf.php';
require_once PS_ROOT . '/auth/core/role-splash.php';
if (!isset($_SESSION['faculty_id'])) {
    header("Location: landing-page.php");
    exit();
}
$fullname = $_SESSION['faculty_name'];
$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

require_once PS_ROOT . '/config/db.php';
require_once PS_ROOT . '/settings/core/faculty-helpers.php';   // department list, email mask, device label
$conn = getDB();

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
// Root URL: now at project root — SCRIPT_NAME dirname IS the project root.
$root_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
// Uploads live at the project root — use a root-relative prefix
// so image src values resolve correctly regardless of nesting depth.
$uploads_url = $root_url . 'uploads/';

/* ── Equipment photo lookup ───────────────────────────────────────────
   tbl_requests only stores equipment_name as free text (no item_id
   foreign key), so we match it against tbl_inventory.item_name to find
   a real photo. Built once here and reused by "Active Now" (Home tab)
   and every section of My Activity. */
$equip_image_map = [];
$equip_img_res = mysqli_query($conn, "SELECT item_name, image_path FROM tbl_inventory");
if ($equip_img_res) {
    while ($eir = mysqli_fetch_assoc($equip_img_res)) {
        if (!empty($eir['image_path']) && $eir['image_path'] !== 'uploads/default.png') {
            $equip_image_map[strtolower(trim($eir['item_name']))] = $eir['image_path'];
        }
    }
}

/* Returns a full <img> src for the given equipment name, or null when no
   catalog photo exists (caller should fall back to actEquipIcon()). */
function actEquipImage(string $equipmentName): ?string
{
    global $equip_image_map, $root_url;
    $key = strtolower(trim($equipmentName));
    if (isset($equip_image_map[$key])) {
        return $root_url . htmlspecialchars($equip_image_map[$key]);
    }
    return null;
}

/* ── Equipment icon helper — fallback when no photo is on file ───────── */
function actEquipIcon(string $name): string
{
    $n = strtolower($name);
    if (str_contains($n, 'projector'))                    return 'videocam';
    if (str_contains($n, 'remote') || str_contains($n, ' ac ') || $n === 'ac') return 'settings_remote';
    if (str_contains($n, 'cord') || str_contains($n, 'extension')) return 'power';
    if (str_contains($n, 'laptop') || str_contains($n, 'computer')) return 'laptop';
    if (str_contains($n, 'camera'))                       return 'photo_camera';
    if (str_contains($n, 'speaker') || str_contains($n, 'audio'))   return 'speaker';
    if (str_contains($n, 'mic'))                          return 'mic';
    if (str_contains($n, 'monitor') || str_contains($n, 'screen'))  return 'monitor';
    if (str_contains($n, 'tablet') || str_contains($n, 'ipad'))     return 'tablet';
    if (str_contains($n, 'printer'))                      return 'print';
    return 'inventory_2';
}

require_once PS_ROOT . '/equipment-booking/core/arbitration-engine.php';
require_once PS_ROOT . '/equipment-booking/core/booking-schedule.php';
require_once PS_ROOT . '/activity/core/faculty-data.php';   // My Activity (equipment) + shared housekeeping
BookingSchedule::ensureSchema($conn);

function maskEmail($email)
{
    if (!$email) return null;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return htmlspecialchars($email);
    $visible = htmlspecialchars(mb_substr($parts[0], 0, 4));
    return $visible . '***@' . htmlspecialchars($parts[1]);
}

/** Borrow form validation failed: back to the dashboard with a readable message. */
function bm_fail(string $msg): void
{
    header('Location: faculty-dashboard.php?borrow_error=' . rawurlencode($msg));
    exit();
}

// ── Auto-decline expired & mark overdue ───────────────────────────────────
// One shared routine (activity/core/faculty-data.php) so this page and the
// My Activity live refresh apply exactly the same rules:
//   - a Waiting request nobody decided is declined at 12:01 AM on its borrow date
//   - an On Loan item past its return time becomes Overdue; an Approved booking nobody collected
//     is closed as a no-show; Later bookings take their stock when they start
$today = date('Y-m-d');
fact_eq_housekeeping($conn);
