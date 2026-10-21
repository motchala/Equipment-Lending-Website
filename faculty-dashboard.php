<?php

// application error disclosure vulnerability prevention 
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// csp vulnerability fix: generate a nonce for inline scripts and styles, and include it in the CSP header
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/role-splash.php';
if (!isset($_SESSION['faculty_id'])) {
    header("Location: landing-page.php");
    exit();
}
$fullname = $_SESSION['faculty_name'];
$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/faculty-account.php';   // department list, email mask, phone format, device label
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

require_once __DIR__ . '/equipment-booking/core/arbitration-engine.php';
require_once __DIR__ . '/equipment-booking/core/booking-schedule.php';
require_once __DIR__ . '/equipment-booking/core/faculty-activity.php';   // My Activity (equipment) + shared housekeeping
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
// One shared routine (equipment-booking/core/faculty-activity.php) so this page and the
// My Activity live refresh apply exactly the same rules:
//   - a Waiting request whose borrow date has passed is declined ("Request expired")
//   - overdue marking is time-aware (hourly bookings) and Later bookings take their stock when they start
$today = date('Y-m-d');
fact_eq_housekeeping($conn);

// ── Handle Borrow Request ──────────────────────────────────────────────────
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name']) || isset($_POST['submitted_as'])) {
    if (!isset($_SESSION['faculty_id'])) die("Unauthorized access");

    $submitted_as_post = $_POST['submitted_as'] ?? '';

    // ── ADVISER BRANCH ────────────────────────────────────────────────────
    if ($submitted_as_post === 'adviser') {

        // Org privileges come from the adviser role itself (read fresh from the DB here,
        // never from a form field): only an Organization Adviser may submit for an organization.
        $stmt_gate = $conn->prepare("SELECT role FROM tbl_users WHERE faculty_id = ? LIMIT 1");
        $stmt_gate->bind_param('s', $_SESSION['faculty_id']);
        $stmt_gate->execute();
        $gate_row = $stmt_gate->get_result()->fetch_assoc();
        $stmt_gate->close();
        if (($gate_row['role'] ?? '') !== 'Organization Adviser') {
            die("Error: Organisation borrowing not permitted for this account.");
        }

        // CSRF check before reading any $_POST / $_FILES (Requirement 10.2)
        csrf_verify();

        // Collect faculty info via prepared statement
        $user_id = $_SESSION['faculty_id'];
        $stmt_usr = $conn->prepare("SELECT fullname, faculty_id FROM tbl_users WHERE faculty_id = ? LIMIT 1");
        $stmt_usr->bind_param('s', $user_id);
        $stmt_usr->execute();
        $adv_user = $stmt_usr->get_result()->fetch_assoc();
        $stmt_usr->close();
        if (!$adv_user) die("User profile not found.");
        $faculty_name = $adv_user['fullname'];
        $faculty_id   = $adv_user['faculty_id'];

        // Where + when + which items. Everything is validated BEFORE anything is saved
        // (including the optional letter), so one bad item never leaves a half-filed batch.
        $instructor = $faculty_name; // auto-filled from account name
        $rr = BookingSchedule::resolveRoom($conn, (string)($_POST['room_id'] ?? ''));
        if (!$rr['ok']) bm_fail($rr['error']);
        $room    = $rr['label'];
        $room_id = $rr['id'];            // null = "Outside"

        $raw_items = $_POST['items'] ?? [];
        if (!is_array($raw_items) || empty($raw_items)) bm_fail('Please select at least one item.');
        $qty_post = (isset($_POST['qty']) && is_array($_POST['qty'])) ? $_POST['qty'] : [];
        $plan = [];
        $seen = [];
        foreach ($raw_items as $raw) {
            $name = trim((string)$raw);
            if ($name === '' || isset($seen[$name])) continue;
            $seen[$name] = true;
            $chk = BookingSchedule::parse($conn, $_POST, true, $name, max(1, (int)($qty_post[$name] ?? 1)));
            if (!$chk['ok']) bm_fail($name . ': ' . $chk['error']);
            $bk = $chk['data'];
            if (BookingSchedule::freeUnits($conn, $name, $bk['start_ts'], $bk['end_ts']) < $bk['qty']) {
                $next = $bk['mode'] === 'future'
                    ? BookingSchedule::earliestFreeDate(
                        $conn,
                        $name,
                        $bk['qty'],
                        substr($bk['borrow_time'], 0, 5),
                        (int)($_POST['duration'] ?? 1),
                        date('Y-m-d', strtotime($bk['borrow_date'] . ' +1 day'))
                    )
                    : null;
                bm_fail('"' . $name . '" is already booked for that schedule.'
                    . ($next ? ' The next free start is ' . date('D, M j', strtotime($next)) . '.' : ''));
            }
            $plan[] = ['name' => $name, 'qty' => $bk['qty'], 'bk' => $bk];
        }
        if (empty($plan)) bm_fail('Please select at least one item.');
        // Equipment cannot go to a room that another faculty has reserved for that time
        if ($room_id !== null) {
            $room_msg = BookingSchedule::roomConflictMessage($conn, $room_id, $plan[0]['bk']['start_ts'], $plan[0]['bk']['end_ts'], (string)$faculty_id);
            if ($room_msg) bm_fail($room_msg);
        }
        $borrow_date = $plan[0]['bk']['borrow_date'];
        $return_date = $plan[0]['bk']['return_date'];

        // The request letter is OPTIONAL for organization requests. If one is attached it is
        // validated and stored; without one the request is filed with no document (it just
        // gets no letter-based priority from the arbitration engine).
        $rel_path = null;
        if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['request_document']['error'] !== UPLOAD_ERR_OK) {
                die("File upload error. Please try again.");
            }
            if ($_FILES['request_document']['size'] > 5 * 1024 * 1024) {
                die("File too large. Maximum size is 5 MB.");
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['request_document']['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed_mimes, true)) {
                die("Unsupported file type. Please upload a PDF, JPG, PNG, or WEBP file.");
            }

            $orig_name = basename($_FILES['request_document']['name']);
            $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
            $dest_name = time() . '_' . $faculty_id . '_' . $safe_name;
            $dest_dir  = __DIR__ . '/uploads/request_letters/';
            $dest_path = $dest_dir . $dest_name;
            if (!move_uploaded_file($_FILES['request_document']['tmp_name'], $dest_path)) {
                die("Error: Could not save uploaded document.");
            }
            $rel_path = 'uploads/request_letters/' . $dest_name;
        }

        // Items were validated above ($plan)
        $items = array_column($plan, 'name');

        // Generate batch UUID (Requirement 4.5)
        $batch_id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        // Loop: one INSERT per selected item (Requirement 4.5, 3.3)
        $declined_items = [];
        $kept_items     = 0;
        foreach ($plan as $pl) {
            $item_name = $pl['name'];
            $bk        = $pl['bk'];

            $stmt_ins = $conn->prepare(
                "INSERT INTO tbl_requests
                 (faculty_name, faculty_id, equipment_name, instructor, room,
                  borrow_date, return_date, borrow_time, return_time, borrow_qty, room_id,
                  booking_mode, stock_applied, status, request_date,
                  submitted_as, batch_id, document_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'adviser', ?, ?)"
            );
            $stmt_ins->bind_param(
                'sssssssssiisss',
                $faculty_name,
                $faculty_id,
                $item_name,
                $instructor,
                $room,
                $borrow_date,
                $return_date,
                $bk['borrow_time'],
                $bk['return_time'],
                $bk['qty'],
                $room_id,
                $bk['mode'],
                $batch_id,
                $rel_path
            );
            $stmt_ins->execute();
            $new_request_id = $conn->insert_id;
            $stmt_ins->close();
            // An Organization Adviser request with a supporting letter is a
            // Super-Admin review item.  It must not be auto-decided by the
            // arbitration engine before that review happens.
            if ($rel_path !== null) {
                $hold = $conn->prepare("UPDATE tbl_requests SET reason = 'Awaiting Super Admin review.' WHERE id = ? AND status = 'Waiting'");
                if ($hold) {
                    $hold->bind_param('i', $new_request_id);
                    $hold->execute();
                    $hold->close();
                }
                $kept_items++;
                continue;
            }

            ArbitrationEngine::process($conn, $new_request_id);
            // A declined request is never kept: drop it and report the reason on the spot.
            $why_declined = ArbitrationEngine::discardIfDeclined($conn, $new_request_id);
            if ($why_declined !== null) {
                $declined_items[] = '"' . $item_name . '": ' . $why_declined;
                continue;
            }
            $kept_items++;

            // ── Generate return token if engine approved this item ────────────
            $chk = $conn->prepare(
                "SELECT id FROM tbl_requests WHERE id = ? AND status = 'Approved' AND return_token IS NULL LIMIT 1"
            );
            if ($chk) {
                $chk->bind_param('i', $new_request_id);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows > 0) {
                    $auto_token = bin2hex(random_bytes(32));
                    $tok = $conn->prepare(
                        "UPDATE tbl_requests SET return_token = ? WHERE id = ? AND status = 'Approved' AND return_token IS NULL"
                    );
                    if ($tok) {
                        $tok->bind_param('si', $auto_token, $new_request_id);
                        $tok->execute();
                        $tok->close();
                    }
                }
                $chk->close();
            }
        }

        if ($declined_items) {
            // Nothing is logged for the declined items: say why, right here.
            bm_fail('Not booked – ' . implode(' | ', $declined_items)
                . ($kept_items > 0 ? ' (The other items in your request were submitted.)' : ''));
        }
        header("Location: faculty-dashboard.php?success=1");
        exit();
    } else {
        // ── PERSONAL BRANCH (submitted_as = 'personal' OR absent — backwards-compat) ──

        csrf_verify();

        $user_id  = $_SESSION['faculty_id'];
        $stmt_usr = $conn->prepare("SELECT fullname, faculty_id, role FROM tbl_users WHERE faculty_id = ? LIMIT 1");
        $stmt_usr->bind_param('s', $user_id);
        $stmt_usr->execute();
        $user = $stmt_usr->get_result()->fetch_assoc();
        $stmt_usr->close();
        if (!$user) die("User profile not found.");
        $faculty_name   = $user['fullname'];
        $faculty_id     = $user['faculty_id'];

        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $instructor     = $faculty_name; // auto-filled from account name

        // Where (live room record, same tables as Facilities) and when (Today / Later rules).
        // Limits follow the account's role, read from the DB, never from the form.
        $rr = BookingSchedule::resolveRoom($conn, (string)($_POST['room_id'] ?? ''));
        if (!$rr['ok']) bm_fail($rr['error']);
        $room    = $rr['label'];
        $room_id = $rr['id'];            // null = "Outside"

        $chk = BookingSchedule::parse(
            $conn,
            $_POST,
            BookingSchedule::isAdviserRole((string)($user['role'] ?? '')),
            $equipment_name,
            max(1, (int)($_POST['qty'] ?? 1))
        );
        if (!$chk['ok']) bm_fail($chk['error']);
        $bk = $chk['data'];
        // Equipment cannot go to a room that another faculty has reserved for that time
        if ($room_id !== null) {
            $room_msg = BookingSchedule::roomConflictMessage($conn, $room_id, $bk['start_ts'], $bk['end_ts'], (string)$faculty_id);
            if ($room_msg) bm_fail($room_msg);
        }
        if (BookingSchedule::freeUnits($conn, $equipment_name, $bk['start_ts'], $bk['end_ts']) < $bk['qty']) {
            $next = $bk['mode'] === 'future'
                ? BookingSchedule::earliestFreeDate(
                    $conn,
                    $equipment_name,
                    $bk['qty'],
                    substr($bk['borrow_time'], 0, 5),
                    (int)($_POST['duration'] ?? 1),
                    date('Y-m-d', strtotime($bk['borrow_date'] . ' +1 day'))
                )
                : null;
            bm_fail('"' . $equipment_name . '" is already booked for that schedule.'
                . ($next ? ' The next free start is ' . date('D, M j', strtotime($next)) . '.' : ''));
        }
        $borrow_date = $bk['borrow_date'];
        $return_date = $bk['return_date'];

        // ── Document upload validation ─────────────────────────────────────────
        $document_path = null;
        if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['request_document']['error'] !== UPLOAD_ERR_OK) {
                die("File upload error. Please try again.");
            }
            if ($_FILES['request_document']['size'] > 5 * 1024 * 1024) {
                die("File too large. Maximum size is 5 MB.");
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['request_document']['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed_mimes, true)) {
                die("Unsupported file type. Please upload a PDF, JPG, PNG, or WEBP file.");
            }
            // $document_path will be set after INSERT
        }

        // Prepared INSERT — includes submitted_as = 'personal' (Requirement 3.2)
        $stmt_ins_p = $conn->prepare(
            "INSERT INTO tbl_requests
             (faculty_name, faculty_id, equipment_name, instructor, room,
              borrow_date, return_date, borrow_time, return_time, borrow_qty, room_id,
              booking_mode, stock_applied, status, request_date, submitted_as)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'personal')"
        );
        $stmt_ins_p->bind_param(
            'sssssssssiis',
            $faculty_name,
            $faculty_id,
            $equipment_name,
            $instructor,
            $room,
            $borrow_date,
            $return_date,
            $bk['borrow_time'],
            $bk['return_time'],
            $bk['qty'],
            $room_id,
            $bk['mode']
        );
        if ($stmt_ins_p->execute()) {
            $new_request_id = $conn->insert_id;
            $stmt_ins_p->close();

            // ── Move uploaded document and update document_path ────────────────
            if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] === UPLOAD_ERR_OK) {
                $orig_name = basename($_FILES['request_document']['name']);
                $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
                $dest_name = time() . '_' . $faculty_id . '_' . $safe_name;
                $dest_dir  = __DIR__ . '/uploads/request_letters/';
                $dest_path = $dest_dir . $dest_name;
                $rel_path  = 'uploads/request_letters/' . $dest_name;

                if (move_uploaded_file($_FILES['request_document']['tmp_name'], $dest_path)) {
                    $stmt_doc = $conn->prepare('UPDATE tbl_requests SET document_path = ? WHERE id = ?');
                    if ($stmt_doc) {
                        $stmt_doc->bind_param('si', $rel_path, $new_request_id);
                        $stmt_doc->execute();
                        $stmt_doc->close();
                    }
                }
            }

            ArbitrationEngine::process($conn, $new_request_id);

            // A declined request is never kept: drop it and report the reason on the spot.
            $why_declined = ArbitrationEngine::discardIfDeclined($conn, $new_request_id);
            if ($why_declined !== null) {
                bm_fail($why_declined);
            }

            // ── Generate return token if engine approved the request ──────────
            $check_approved = $conn->prepare(
                "SELECT id FROM tbl_requests WHERE id = ? AND status = 'Approved' AND return_token IS NULL LIMIT 1"
            );
            if ($check_approved) {
                $check_approved->bind_param('i', $new_request_id);
                $check_approved->execute();
                $check_approved->get_result(); // consume result
                if ($check_approved->affected_rows > 0 || $conn->affected_rows > 0) {
                    // NULL guard in the UPDATE prevents double-write
                    $auto_token = bin2hex(random_bytes(32));
                    $tok = $conn->prepare(
                        "UPDATE tbl_requests SET return_token = ? WHERE id = ? AND status = 'Approved' AND return_token IS NULL"
                    );
                    if ($tok) {
                        $tok->bind_param('si', $auto_token, $new_request_id);
                        $tok->execute();
                        $tok->close();
                    }
                }
                $check_approved->close();
            }

            header("Location: faculty-dashboard.php?success=1");
            exit();
        } else {
            error_log('[PUPSync] faculty-dashboard borrow insert failed: ' . $conn->error);
            $stmt_ins_p->close();
            die("Error processing request. Please try again later.");
        }
    }
}

// ── Inventory & Requests ───────────────────────────────────────────────────
$category_result  = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory WHERE is_archived = 0 ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY item_name ASC");
$uid_safe = mysqli_real_escape_string($conn, $_SESSION['faculty_id']);

// ── Stats ──────────────────────────────────────────────────────────────────
$stat_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe'"))['c'];
$stat_waiting  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Waiting'"))['c'];
$stat_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Approved'"))['c'];
$stat_declined = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Declined'"))['c'];
$stat_overdue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tbl_requests WHERE faculty_id='$uid_safe' AND status='Overdue'"))['c'];

$has_overdue_block = $stat_overdue > 0;

// The "Borrowing Blocked" notice shows as a toast once per login session —
// not on every page load/refresh. It reappears after logout+login, or once
// the PHP session itself has expired (e.g. from being away too long).
$show_overdue_toast = $has_overdue_block && empty($_SESSION['overdue_toast_shown']);
if ($has_overdue_block) {
    $_SESSION['overdue_toast_shown'] = true;
} else {
    // No longer blocked (e.g. the item was returned) — clear the flag so
    // the toast shows fresh again if they end up blocked in the future.
    unset($_SESSION['overdue_toast_shown']);
}
// ── Requests JSON for JS ───────────────────────────────────────────────────
$requests_raw = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE faculty_id='$uid_safe' ORDER BY request_date DESC");
$requests_js = [];
while ($row = mysqli_fetch_assoc($requests_raw)) $requests_js[] = $row;
$requests_json = json_encode($requests_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// ── Room Reservations for My Reservations history panel ───────────────────
$room_res_stmt = $conn->prepare(
    "SELECT rr.id, rr.room_id, rr.reservation_date, rr.start_time, rr.end_time,
            rr.purpose, rr.submitted_as, rr.status, rr.reason,
            rr.request_date,
            r.room_name,
            b.name AS building_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE rr.faculty_id = ?
      ORDER BY rr.request_date DESC"
);
$room_reservations = [];
if ($room_res_stmt) {
    $room_res_stmt->bind_param('s', $uid_safe);
    $room_res_stmt->execute();
    $room_res_result = $room_res_stmt->get_result();
    while ($row = $room_res_result->fetch_assoc()) {
        $room_reservations[] = $row;
    }
    $room_res_stmt->close();
}

// ── Notifications (live feed for the bell / modal) ─────────────────────────
// Built from this faculty member's own requests, reservations, waitlist,
// room issues and account activity — see faculty-notif-functions.php.
// Read/deleted state lives in tbl_faculty_notif_state; the modal is rendered
// client-side from this JSON and kept in sync via api/faculty-notif.php.
require_once __DIR__ . '/equipment-booking/core/faculty-notif-functions.php';
$notifications = fnotif_build_list($conn, $_SESSION['faculty_id']);
$notif_count   = fnotif_count_unread($notifications);
$notif_json    = json_encode(
    ['notifications' => $notifications, 'unread_count' => $notif_count],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
);

// ── Avatar initials ────────────────────────────────────────────────────────
$name_parts = explode(' ', trim($fullname));
$firstname  = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

// ── Profile ────────────────────────────────────────────────────────────────
$profile_row = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT email, backup_email, profile_picture, dob, gender, nationality, 
     department, faculty_rank, phone, present_address, permanent_address, landline,
     emergency_name, emergency_relationship, emergency_phone, role
     FROM tbl_users WHERE faculty_id='$uid_safe' LIMIT 1"
)) ?: [];
$db_email             = $profile_row['email']             ?? '';
$db_backup_email      = $profile_row['backup_email']      ?? '';
$db_profile_pic       = $profile_row['profile_picture']   ?? '';
$db_dob               = $profile_row['dob']               ?? '';
$db_gender            = $profile_row['gender']            ?? '';
$db_nationality       = $profile_row['nationality']       ?? '';
$db_department        = $profile_row['department']        ?? '';
$db_faculty_rank      = $profile_row['faculty_rank']      ?? '';
$db_phone             = $profile_row['phone']             ?? '';
$db_present_address   = $profile_row['present_address']   ?? '';
$db_permanent_address = $profile_row['permanent_address'] ?? '';
$db_landline          = $profile_row['landline']          ?? '';
$db_emergency_name    = $profile_row['emergency_name']        ?? '';
$db_emergency_rel     = $profile_row['emergency_relationship'] ?? '';
$db_emergency_phone   = $profile_row['emergency_phone']       ?? '';
$is_org_adviser       = ($profile_row['role'] ?? '') === 'Organization Adviser';

// Dual-mode (personal + organization) borrowing is automatic for Organization Advisers.
$is_dual_mode = $is_org_adviser;

// Faculty ID: set by the faculty member themselves (once). Until then the account is stored
// under an internal placeholder that must never be shown (see config/faculty-id.php).
$fid_unset = faculty_id_is_unset((string)$_SESSION['faculty_id']);
$fid_label = faculty_id_display((string)$_SESSION['faculty_id'], 'Not set');

// ── Adviser-mode inventory checklist — only loaded if dual mode active ────
$avail_items = [];
if ($is_dual_mode) {
    $avail_result = $conn->query(
        "SELECT item_id, item_name, category, quantity FROM tbl_inventory WHERE is_archived = 0 ORDER BY category, item_name"
    );
    if ($avail_result) {
        while ($av_row = $avail_result->fetch_assoc()) $avail_items[] = $av_row;
    }
}

// ── Borrow Request modal data ─────────────────────────────────────────────
$bk_rooms  = BookingSchedule::roomsTree($conn, true);   // Available rooms only (no Maintenance / Not Bookable)
$bk_totals = BookingSchedule::totalStockMap($conn);
$bk_items  = [];
$bk_res    = $conn->query("SELECT item_name, quantity FROM tbl_inventory WHERE is_archived = 0");
while ($bk_res && ($bk_row = $bk_res->fetch_assoc())) {
    $bk_items[$bk_row['item_name']] = [
        'shelf' => (int)$bk_row['quantity'],
        'total' => (int)($bk_totals[$bk_row['item_name']] ?? $bk_row['quantity']),
    ];
}
$bk_mine = BookingSchedule::myUpcomingReservations($conn, (string)($_SESSION['faculty_id'] ?? ''));
$bk_meta_json = json_encode([
    'items'   => $bk_items,
    'rooms'   => $bk_rooms,
    'mine'    => $bk_mine,
    'adviser' => (bool)$is_org_adviser,
    'today'   => date('Y-m-d'),
    'nowMin'  => (int)date('G') * 60 + (int)date('i'),
    'rules'   => [
        'dayStart'   => BookingSchedule::DAY_START,
        'dayEnd'     => BookingSchedule::DAY_END,
        'slot'       => BookingSchedule::SLOT_MINUTES,
        'maxAhead'   => BookingSchedule::MAX_ADVANCE_DAYS,
        'todayHours' => BookingSchedule::TODAY_HOURS,
        'adviserMaxHours' => BookingSchedule::ADVISER_MAX_HOURS,
        'futureDays' => BookingSchedule::FUTURE_DAYS,
        'adviserFutureDays' => BookingSchedule::ADVISER_FUTURE_DAYS,
        'graceMin'   => BookingSchedule::IMMEDIATE_GRACE_MIN,
    ],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);

$masked_email       = maskEmail($db_email);
$masked_backup      = maskEmail($db_backup_email);
$dob_display        = $db_dob ? date('F j, Y', strtotime($db_dob)) : '';
$dob_locked         = !empty($db_dob);
$gender_locked      = !empty($db_gender);
$nationality_locked = !empty($db_nationality);
$backup_locked      = !empty($db_backup_email);
$department_locked  = !empty($db_department);
$profile_pic_file = basename(str_replace('\\', '/', trim($db_profile_pic)));
$profile_pic_path = __DIR__ . '/uploads/profile_pictures/' . $profile_pic_file;
$profile_pic_url  = ($profile_pic_file !== '' && is_file($profile_pic_path))
    ? $uploads_url . 'profile_pictures/' . rawurlencode($profile_pic_file)
    : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>PUPSync | Faculty Dashboard</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
    <link rel="icon" href="assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
    <!-- Google Fonts: Hanken Grotesk + Inter (matches new design system) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Material Symbols -->
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet">
    <!-- Font Awesome (kept for existing icon references in JS) -->
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="equipment-booking/assets/css/faculty-dashboard.css?v=<?php echo @filemtime(__DIR__ . '/equipment-booking/assets/css/faculty-dashboard.css'); ?>">

    <!-- NOTE: faculty-code-card.css intentionally not loaded — it was a stale
         snapshot of this same stylesheet (predating several redesigns) that
         was silently overriding current styles because it loaded last. -->

    <!-- Responsive System -->
    <link rel="stylesheet" href="equipment-booking/assets/css/faculty-dashboard-responsive.css?v=<?php echo @filemtime(__DIR__ . '/equipment-booking/assets/css/faculty-dashboard-responsive.css'); ?>">

    <!-- Dashboard Redesign v3 — Google Font -->

    <!-- facilities tab portal -->
    <link rel="stylesheet" href="room-reservation/assets/css/fcty-facilities.css">

    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo @filemtime('assets/css/logout-modal.css'); ?>">

    <style nonce="<?php echo $csp_nonce; ?>">
        /* fix for csp vulnerability. inline styles */
        /* ================================================================
       DASHBOARD — #panel-home style overrides
    ================================================================ */

        /* ── Hero Header ──────────────────────────────────────────────── */
        .dash-hero {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--color-primary-container) 0%, #5a0000 100%);
            border-radius: var(--radius-xl);
            padding: 28px 32px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .dash-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .dash-hero-text {
            position: relative;
            z-index: 1;
        }

        .dash-hero-eyebrow {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 6px;
        }

        .dash-hero-title {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .dash-hero-name {
            color: #ffcece;
        }

        .dash-hero-sub {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 400;
        }

        .dash-hero-ornament {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 110px;
            height: 110px;
            color: #fff;
            opacity: 0.25;
            pointer-events: none;
        }

        [data-theme="dark"] .dash-hero {
            background: linear-gradient(135deg, #4a0000 0%, #2a0000 100%);
        }

        /* ── Stats Row layout override ────────────────────────────────── */
        /* .dashboard-stats-col already exists; we change it to a row */
        .dash-stats-row-layout {
            flex-direction: row !important;
            gap: 14px !important;
            margin-bottom: 20px;
        }

        .dash-stats-row-layout .stat-card {
            flex: 1;
            flex-direction: column;
            align-items: flex-start;
            padding: 18px 20px 16px;
            border-left: 3px solid var(--color-primary);
            border-radius: var(--radius-lg);
            gap: 2px;
        }

        .dash-stats-row-layout .stat-card-icon {
            position: static;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            background: color-mix(in srgb, var(--color-primary) 12%, transparent);
            margin-bottom: 10px;
            color: var(--color-primary);
        }

        .dash-stats-row-layout .stat-card-value {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            margin-bottom: 2px;
        }

        .dash-stats-row-layout .stat-card-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--color-secondary);
        }

        .dash-stats-row-layout .stat-card-overdue {
            border-left-color: var(--color-error);
        }

        .dash-stats-row-layout .stat-card-overdue .stat-card-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ── Main Body Layout override ────────────────────────────────── */
        /* .dashboard-grid changed from 256px 1fr to 1fr 300px */
        .dash-body-layout {
            grid-template-columns: 1fr 296px !important;
            gap: 20px !important;
            margin-bottom: 0 !important;
            align-items: start;
        }

        .dash-bento-col {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .dash-sidebar-col {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Bento cards: taller, more vivid ─────────────────────────── */
        #panel-home .bento-grid {
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        #panel-home .bento-item {
            min-height: 160px;
            border-radius: var(--radius-xl);
            padding: 22px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative corner arc */
        #panel-home .bento-item::after {
            content: '';
            position: absolute;
            bottom: -28px;
            right: -28px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 18px solid currentColor;
            opacity: 0.07;
            pointer-events: none;
        }

        #panel-home .bento-item:nth-child(1) {
            color: var(--color-primary);
        }

        #panel-home .bento-item:nth-child(2) {
            color: #4a6cf7;
        }

        #panel-home .bento-item:nth-child(3) {
            color: #0d7a5f;
        }

        [data-theme="dark"] #panel-home .bento-item:nth-child(2) {
            color: #7c9fff;
        }

        [data-theme="dark"] #panel-home .bento-item:nth-child(3) {
            color: #34d99a;
        }

        #panel-home .bento-icon .material-symbols-outlined {
            font-size: 32px;
            color: inherit;
            opacity: 0.8;
        }

        #panel-home .bento-title {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-top: 14px;
            margin-bottom: 3px;
            color: var(--color-on-surface);
        }

        /* ── Audit card inline (under bento) ─────────────────────────── */
        .dash-audit-inline {
            border-radius: var(--radius-lg);
            padding: 16px 18px;
        }

        /* ── Stat card clickable hover for row layout ─────────────────── */
        .dash-stats-row-layout .stat-card-clickable:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ── Section label spacing ────────────────────────────────────── */
        #panel-home .section-label {
            margin-top: 24px;
            margin-bottom: 12px;
        }

        /* ── Active card redesign: cleaner ────────────────────────────── */
        #panel-home .active-card {
            border-radius: var(--radius-xl);
            padding: 18px 20px;
            border-top: 3px solid var(--color-outline-variant);
            transition: transform var(--transition), box-shadow var(--transition), border-top-color var(--transition);
        }

        #panel-home .active-card:hover {
            border-top-color: var(--accent-gold-deep);
            transform: translateY(-2px);
        }

        #panel-home .active-card-overdue {
            border-top-color: var(--color-error) !important;
        }

        #panel-home .active-card-thumb {
            background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface-container));
            border-radius: var(--radius-md);
        }

        #panel-home .active-card-title {
            font-family: var(--font-display);
            font-weight: 700;
        }

        /* ── Quick actions pill style ─────────────────────────────────── */
        #panel-home .qa-btn {
            border-radius: var(--radius-full);
            padding: 9px 16px;
            font-size: 0.8rem;
            transition: background var(--transition), color var(--transition), box-shadow var(--transition);
        }

        #panel-home .qa-btn:hover {
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.12);
        }

        /* ── Faculty code card: matches sidebar ───────────────────────── */
        #panel-home .faculty-code-card {
            border-radius: var(--radius-lg);
        }

        /* ── Page header override (hide old one gracefully) ───────────── */
        #panel-home .page-header-block {
            display: none;
        }

        /* ── Dark theme hero ──────────────────────────────────────────── */
        [data-theme="dark"] .dash-hero-name {
            color: #ffb3b3;
        }

        /* ================================================================
           EQUIPMENT PANEL — scoped to #panel-lending only
        ================================================================ */

        /* ── Sub-nav ──────────────────────────────────────────────── */
        #panel-lending .lending-subnav,
        #panel-rooms .lending-subnav {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        #panel-lending .lending-nav-btn,
        #panel-rooms .lending-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 8px;
            border: 1.5px solid #e5e7eb;
            background: #ffffff;
            color: var(--color-on-surface-variant);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-sans);
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            transition: all .2s cubic-bezier(.4, 0, .2, 1);
        }

        #panel-lending .lending-nav-btn .material-symbols-outlined,
        #panel-rooms .lending-nav-btn .material-symbols-outlined {
            font-size: 16px;
        }

        #panel-lending .lending-nav-btn.active,
        #panel-rooms .lending-nav-btn.active {
            background: #570000;
            color: #fff;
            border-color: #570000;
            box-shadow: 0 3px 12px rgba(87, 0, 0, .28);
        }

        #panel-lending .lending-nav-btn:not(.active):hover,
        #panel-rooms .lending-nav-btn:not(.active):hover {
            background: #fdf1f1;
            border-color: #c0a0a0;
            color: #570000;
        }

        /* ── Sub headers: same look as the My Activity one (.fact-sub) ─── */
        #panel-home .dash-flat-sub,
        #panel-lending .page-subtitle,
        #panel-rooms .page-subtitle {
            margin: 4px 0 0;
            font-size: 0.875rem;
            color: var(--color-secondary);
        }

        /* ── Faculty Dashboard ─────────────────────────────────────────────────────── */
        #panel-home .fdb {
            --fdb-line: var(--color-outline-variant);
            --fdb-surface: var(--color-surface-lowest);
            --fdb-ink: var(--color-on-surface);
            --fdb-muted: var(--color-secondary);
        }

        #panel-home .fdb-tone-ok {
            --tone: var(--color-success);
        }

        #panel-home .fdb-tone-warn {
            --tone: var(--color-warning);
        }

        #panel-home .fdb-tone-danger {
            --tone: var(--color-error);
        }

        #panel-home .fdb-tone-info {
            --tone: var(--color-primary);
        }

        #panel-home .fdb-tone-muted {
            --tone: var(--color-secondary);
        }

        /* header (bell + avatar reuse .fd-dash-top-actions) */
        #panel-home .fdb-head {
            margin-bottom: 20px;
        }

        #panel-home .fdb-head-tools {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 12px 16px;
        }

        #panel-home .fdb-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        #panel-home .fdb-head-tools .fd-dash-top-actions {
            padding-left: 16px;
            border-left: 1px solid var(--fdb-line);
        }

        #panel-home .fdb-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid var(--fdb-line);
            background: var(--fdb-surface);
            color: var(--fdb-ink);
            font: 600 12.5px var(--font-sans);
            white-space: nowrap;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.15s;
        }

        #panel-home .fdb-btn .material-symbols-outlined {
            font-size: 18px;
            color: var(--color-primary);
        }

        #panel-home .fdb-btn:hover {
            background: var(--color-surface-container);
            border-color: var(--color-primary);
            transform: translateY(-1px);
        }

        #panel-home .fdb-btn--primary {
            background: linear-gradient(135deg, #800000 0%, #5a0000 100%);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 4px 12px rgba(96, 3, 2, 0.22);
        }

        #panel-home .fdb-btn--primary .material-symbols-outlined {
            color: #f2c14e;
        }

        #panel-home .fdb-btn--primary:hover {
            background: linear-gradient(135deg, #800000 0%, #5a0000 100%);
            border-color: transparent;
            box-shadow: 0 6px 16px rgba(96, 3, 2, 0.3);
        }

        #panel-home .fdb-btn:focus-visible,
        #panel-home .fdb-kpi:focus-visible,
        #panel-home .fdb-link:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        /* key figures: icon + number + one-word label */
        #panel-home .fdb-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        #panel-home .fdb-kpi {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            padding: 16px 18px 16px 22px;
            text-align: left;
            background: var(--fdb-surface);
            border: 1px solid var(--fdb-line);
            border-radius: 16px;
            color: var(--fdb-ink);
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.16s, box-shadow 0.16s, border-color 0.16s;
        }

        #panel-home .fdb-kpi::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--tone);
        }

        #panel-home .fdb-kpi:hover {
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--tone) 55%, var(--fdb-line));
            box-shadow: 0 8px 22px color-mix(in srgb, var(--tone) 14%, transparent);
        }

        #panel-home .fdb-kpi-icon {
            flex-shrink: 0;
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--tone) 13%, transparent);
            color: var(--tone);
        }

        #panel-home .fdb-kpi-icon .material-symbols-outlined {
            font-size: 22px;
        }

        #panel-home .fdb-kpi-body {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        #panel-home .fdb-kpi-val {
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
            color: var(--fdb-ink);
        }

        #panel-home .fdb-kpi-label {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 600;
            color: var(--fdb-muted);
        }

        /* grid + cards */
        #panel-home .fdb-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
            align-items: stretch;
        }

        #panel-home .fdb-area-chart {
            position: relative;
            grid-column: span 8;
        }

        #panel-home .fdb-area-code {
            grid-column: span 4;
        }

        #panel-home .fdb-area-attn,
        #panel-home .fdb-area-third {
            grid-column: span 4;
        }

        #panel-home .fdb-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 16px 18px 14px;
            background: var(--fdb-surface);
            border: 1px solid var(--fdb-line);
            border-radius: 16px;
        }

        #panel-home .fdb-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        #panel-home .fdb-card-head h2 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--fdb-ink);
        }

        #panel-home .fdb-link {
            border: 0;
            background: none;
            padding: 2px 4px;
            font: 600 12px var(--font-sans);
            color: var(--color-primary);
            cursor: pointer;
            border-radius: 6px;
        }

        #panel-home .fdb-link:hover {
            text-decoration: underline;
        }

        /* chart: borrows vs reservations, Months / Week (CSS-only toggle), details on hover / focus / tap */
        #panel-home .fdb-range-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        #panel-home .fdb-head-right {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px 16px;
        }

        #panel-home .fdb-legend {
            display: flex;
            gap: 14px;
            font-size: 11.5px;
            color: var(--fdb-muted);
        }

        #panel-home .fdb-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 6px;
            border-radius: 2px;
        }

        #panel-home .fdb-dot--eq,
        #panel-home .fdb-bar--eq {
            background: var(--color-primary-container);
        }

        #panel-home .fdb-dot--rm,
        #panel-home .fdb-bar--rm {
            background: #d9a521;
        }

        #panel-home .fdb-seg {
            display: inline-flex;
            gap: 2px;
            padding: 3px;
            border-radius: 10px;
            border: 1px solid var(--fdb-line);
            background: var(--color-surface-container);
        }

        #panel-home .fdb-seg label {
            padding: 4px 12px;
            border-radius: 7px;
            font: 600 11.5px var(--font-sans);
            color: var(--fdb-muted);
            cursor: pointer;
            user-select: none;
            transition: background 0.15s, color 0.15s;
        }

        #panel-home #fdbRangeMonths:checked~.fdb-card-head label[for="fdbRangeMonths"],
        #panel-home #fdbRangeWeek:checked~.fdb-card-head label[for="fdbRangeWeek"] {
            background: var(--fdb-surface);
            color: var(--color-primary);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.14);
        }

        #panel-home #fdbRangeMonths:focus-visible~.fdb-card-head label[for="fdbRangeMonths"],
        #panel-home #fdbRangeWeek:focus-visible~.fdb-card-head label[for="fdbRangeWeek"] {
            outline: 2px solid var(--color-primary);
            outline-offset: 1px;
        }

        /* Months and Week are stacked in the same cell and only their visibility is switched, so the
           card keeps the same height (the Week view's) and nothing around it moves when you toggle. */
        #panel-home .fdb-views {
            display: grid;
            flex: 1;
            min-width: 0;
        }

        #panel-home .fdb-view {
            grid-area: 1 / 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            visibility: hidden;
        }

        #panel-home #fdbRangeMonths:checked~.fdb-views .fdb-view--months,
        #panel-home #fdbRangeWeek:checked~.fdb-views .fdb-view--week {
            visibility: visible;
        }

        #panel-home .fdb-plot {
            --fdb-plot-h: 176px;
            flex: 1;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            column-gap: 10px;
            align-content: start;
            padding-top: 8px;
        }

        /* vertical axis: whole-number counts */
        #panel-home .fdb-yaxis {
            position: relative;
            width: 22px;
            height: var(--fdb-plot-h);
        }

        #panel-home .fdb-yaxis span {
            position: absolute;
            right: 0;
            transform: translateY(50%);
            font-size: 10.5px;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            color: var(--fdb-muted);
        }

        #panel-home .fdb-plotarea {
            position: relative;
            min-width: 0;
        }

        #panel-home .fdb-lines {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: var(--fdb-plot-h);
            pointer-events: none;
        }

        #panel-home .fdb-lines i {
            position: absolute;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--fdb-line);
            opacity: 0.7;
        }

        #panel-home .fdb-lines i:first-child {
            opacity: 1;
        }

        #panel-home .fdb-chart {
            position: relative;
            display: grid;
            gap: 6px;
        }

        #panel-home .fdb-chart-col {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            border-radius: 10px;
            outline: none;
        }

        #panel-home .fdb-chart-bars {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 5px;
            height: var(--fdb-plot-h);
            border-radius: 10px 10px 0 0;
            transition: background 0.12s;
        }

        #panel-home .fdb-chart-col:hover .fdb-chart-bars,
        #panel-home .fdb-chart-col:focus .fdb-chart-bars {
            background: color-mix(in srgb, var(--color-primary) 7%, transparent);
        }

        #panel-home .fdb-chart-col:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        #panel-home .fdb-bar {
            display: block;
            width: min(24px, 38%);
            border-radius: 5px 5px 1px 1px;
            transition: height 0.4s cubic-bezier(0.16, 1, 0.3, 1), filter 0.12s;
        }

        #panel-home .fdb-chart-col:hover .fdb-bar,
        #panel-home .fdb-chart-col:focus .fdb-bar {
            filter: brightness(1.1);
        }

        #panel-home .fdb-bar.is-zero {
            height: 2px !important;
            opacity: 0.3;
        }

        #panel-home .fdb-chart-x {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 8px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.25;
            min-height: 28px;
            color: var(--fdb-muted);
        }

        #panel-home .fdb-chart-x small {
            font-size: 10.5px;
            font-weight: 500;
            opacity: 0.85;
        }

        #panel-home .fdb-chart-col.is-now .fdb-chart-x {
            color: var(--color-primary);
            font-weight: 700;
        }

        /* hover card (same look as the admin Dashboard chart) */
        #panel-home .fdb-tip {
            position: absolute;
            z-index: 5;
            top: 4px;
            left: 50%;
            min-width: 138px;
            padding: 8px 10px;
            border-radius: 10px;
            background: #2b0f12;
            color: #fff;
            font-size: 11.5px;
            line-height: 1.5;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
            pointer-events: none;
            opacity: 0;
            transform: translate(-50%, 4px);
            transition: opacity 0.12s, transform 0.12s;
        }

        #panel-home .fdb-chart-col:nth-child(-n+2) .fdb-tip {
            left: 0;
            transform: translate(0, 4px);
        }

        #panel-home .fdb-chart-col:nth-last-child(-n+2) .fdb-tip {
            left: auto;
            right: 0;
            transform: translate(0, 4px);
        }

        #panel-home .fdb-chart-col:hover .fdb-tip,
        #panel-home .fdb-chart-col:focus .fdb-tip {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        #panel-home .fdb-chart-col:nth-child(-n+2):hover .fdb-tip,
        #panel-home .fdb-chart-col:nth-child(-n+2):focus .fdb-tip,
        #panel-home .fdb-chart-col:nth-last-child(-n+2):hover .fdb-tip,
        #panel-home .fdb-chart-col:nth-last-child(-n+2):focus .fdb-tip {
            transform: translate(0, 0);
        }

        #panel-home .fdb-tip>b {
            display: block;
            margin-bottom: 2px;
        }

        #panel-home .fdb-tip>span {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        /* needs your attention: icon + one short line */
        #panel-home .fdb-attn {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        #panel-home .fdb-attn-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px 11px;
            text-align: left;
            font: 600 12.5px var(--font-sans);
            color: var(--fdb-ink);
            background: color-mix(in srgb, var(--tone) 7%, transparent);
            border: 1px solid color-mix(in srgb, var(--tone) 24%, var(--fdb-line));
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.15s, border-color 0.15s;
        }

        #panel-home .fdb-attn-item:hover {
            transform: translateX(2px);
            border-color: var(--tone);
        }

        #panel-home .fdb-attn-item:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        #panel-home .fdb-attn-icon {
            flex-shrink: 0;
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: color-mix(in srgb, var(--tone) 15%, transparent);
            color: var(--tone);
        }

        #panel-home .fdb-attn-icon .material-symbols-outlined {
            font-size: 18px;
        }

        #panel-home .fdb-attn-text {
            flex: 1;
            min-width: 0;
        }

        #panel-home .fdb-attn-go {
            font-size: 18px;
            color: var(--fdb-muted);
        }

        /* lists: schedule + recent activity */
        #panel-home .fdb-list {
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        #panel-home .fdb-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-top: 1px solid var(--fdb-line);
        }

        #panel-home .fdb-row:first-child {
            border-top: 0;
        }

        #panel-home .fdb-row.is-past {
            opacity: 0.5;
        }

        #panel-home .fdb-row-icon {
            flex-shrink: 0;
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--tone, var(--color-primary)) 13%, var(--color-surface-container));
            color: var(--tone, var(--color-primary));
        }

        #panel-home .fdb-row-icon .material-symbols-outlined {
            font-size: 18px;
        }

        #panel-home .fdb-row-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        #panel-home .fdb-row-main strong {
            font-size: 13px;
            font-weight: 600;
            color: var(--fdb-ink);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #panel-home .fdb-row-main small {
            font-size: 11.5px;
            color: var(--fdb-muted);
        }

        #panel-home .fdb-time {
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--fdb-ink);
        }

        #panel-home .fdb-pill {
            flex-shrink: 0;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            color: var(--tone, var(--color-secondary));
            background: color-mix(in srgb, var(--tone, var(--color-secondary)) 13%, transparent);
        }

        /* empty states: one icon, one short line */
        #panel-home .fdb-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 26px 8px;
            font-size: 13px;
            color: var(--fdb-muted);
        }

        #panel-home .fdb-empty .material-symbols-outlined {
            font-size: 22px;
            opacity: 0.7;
        }

        #panel-home .fdb-empty--ok .material-symbols-outlined {
            color: var(--color-success);
            opacity: 1;
        }

        /* Student Access Code card sits in the grid as-is (its own styles + script) */
        #panel-home .fdb-area-code .dash-code-card {
            box-sizing: border-box;
            height: 100%;
            display: flex;
            flex-direction: column;
            border-radius: 16px;
        }

        /* Header at the top, button at the bottom, and the code block centred in the space between */
        #panel-home .fdb-area-code .dash-code-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 0;
            text-align: center;
        }

        #panel-home .fdb-area-code .fcc-code-display {
            display: flex;
            width: 100%;
            gap: 8px !important;
            justify-content: center !important;
            align-items: center !important;
        }

        #panel-home .fdb-area-code .fcc-code-value {
            flex: 0 1 auto !important;
            text-align: center;
        }

        #panel-home .fdb-area-code .fcc-copy-btn {
            margin-top: 0 !important;
        }

        #panel-home .fdb-area-code .fcc-status-row {
            display: flex;
            justify-content: center;
        }

        #panel-home .fdb-area-code .fcc-no-code,
        #panel-home .fdb-area-code .fcc-used-info {
            text-align: center;
        }

        @media (max-width: 1180px) {
            #panel-home .fdb-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            #panel-home .fdb-area-chart {
                grid-column: span 12;
            }

            #panel-home .fdb-area-code,
            #panel-home .fdb-area-attn,
            #panel-home .fdb-area-third {
                grid-column: span 6;
            }
        }

        @media (max-width: 768px) {

            #panel-home .fdb-area-code,
            #panel-home .fdb-area-attn,
            #panel-home .fdb-area-third {
                grid-column: span 12;
            }

            #panel-home .fdb-card-head {
                flex-wrap: wrap;
            }

            #panel-home .fdb-head-right {
                justify-content: space-between;
                width: 100%;
            }

            #panel-home .fdb-plot {
                --fdb-plot-h: 150px;
            }

            #panel-home .fdb-chart {
                gap: 3px;
            }

            #panel-home .fdb-head-tools {
                justify-content: stretch;
            }

            #panel-home .fdb-head-tools .fd-dash-top-actions {
                order: 1;
                margin-left: auto;
                padding-left: 0;
                border-left: 0;
            }

            #panel-home .fdb-actions {
                order: 2;
                width: 100%;
            }

            #panel-home .fdb-actions .fdb-btn {
                flex: 1;
                justify-content: center;
            }
        }

        @media (max-width: 520px) {
            #panel-home .fdb-kpis {
                gap: 10px;
            }

            #panel-home .fdb-kpi {
                padding: 14px 12px 14px 18px;
                gap: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            #panel-home .fdb *,
            #panel-home .fdb-kpi {
                transition: none !important;
            }
        }

        /* ── Settings: one unified screen (Account / Appearance / Notifications & Data)
              + Help & Support kept as its own view ─────────────────────────── */
        #settingsOverlay .sx-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px 24px;
        }

        #settingsOverlay .sx-head-text {
            min-width: 0;
        }

        #settingsOverlay .sov-pagehead-sub {
            margin: 4px 0 0;
            font-size: 0.875rem;
            color: var(--color-secondary);
        }

        #settingsOverlay .sx-switch {
            margin: 0;
        }

        #settingsOverlay .sx-section {
            margin: 0 0 36px;
        }

        #settingsOverlay .sx-section:last-child {
            margin-bottom: 0;
        }

        #settingsOverlay .sx-section-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 0 0 16px;
        }

        #settingsOverlay .sx-section-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            font-size: 22px;
            border-radius: 12px;
            color: var(--color-primary);
            background: var(--color-surface-container);
            border: 1px solid var(--color-outline-variant);
        }

        #settingsOverlay .sx-section-title {
            margin: 0;
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: -0.01em;
            color: var(--color-on-surface);
        }

        #settingsOverlay .sx-section-sub {
            margin: 2px 0 0;
            font-size: 0.85rem;
            color: var(--color-secondary);
        }

        #settingsOverlay .sx-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            align-items: start;
        }

        #settingsOverlay .sx-grid--lead {
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
        }

        #settingsOverlay .sx-grid--single {
            grid-template-columns: minmax(0, 1fr);
        }

        @media (max-width: 1024px) {

            #settingsOverlay .sx-grid,
            #settingsOverlay .sx-grid--lead {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 768px) {
            #settingsOverlay .sx-head-text {
                flex: 1 1 100%;
            }

            #settingsOverlay .sx-switch {
                width: 100%;
            }

            #settingsOverlay .sx-switch .sov-nav-item {
                flex: 1;
                justify-content: center;
            }

            #settingsOverlay .sx-section {
                margin-bottom: 28px;
            }

            #settingsOverlay .sx-section-icon {
                width: 36px;
                height: 36px;
            }
        }

        /* ── Page Header ──────────────────────────────────────────── */
        #panel-lending .page-header-block {
            display: block !important;
            margin-bottom: 24px;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
        }


        /* ── Featured Banner ──────────────────────────────────────── */
        #panel-lending .featured-section {
            margin-bottom: 32px;
        }

        #panel-lending .featured-label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-on-surface);
            margin-bottom: 16px;
            letter-spacing: -0.1px;
        }

        #panel-lending .featured-label .material-symbols-outlined {
            font-size: 18px;
            color: var(--accent-gold-deep);
            font-variation-settings: 'FILL' 1;
        }

        #panel-lending .featured-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            align-items: stretch;
        }

        /* Hero featured card — spans 2 cols */
        #panel-lending .feat-hero {
            grid-column: span 2;
            background: #ffffff;
            border: 1px solid #f0e0e0;
            border-radius: 14px;
            position: relative;
            display: flex;
            flex-direction: row;
            overflow: hidden;
            box-shadow: 0 4px 20px -4px rgba(87, 0, 0, .10);
            transition: box-shadow .25s, transform .25s;
            min-height: 200px;
        }

        #panel-lending .feat-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px -6px rgba(87, 0, 0, .14);
        }

        #panel-lending .feat-hero-body {
            flex: 1;
            padding: 28px 28px 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        #panel-lending .feat-hero-img {
            width: 46%;
            background: linear-gradient(145deg, #f7f0f0, #ede4e4);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        #panel-lending .feat-hero-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .45s cubic-bezier(.4, 0, .2, 1);
        }

        #panel-lending .feat-hero-img-placeholder {
            width: 46%;
            background: linear-gradient(145deg, #f7f0f0, #ede4e4);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #panel-lending .feat-hero-img-placeholder .material-symbols-outlined {
            font-size: 64px;
            color: #570000;
            opacity: .12;
        }

        #panel-lending .feat-hero:hover .feat-hero-img img {
            transform: scale(1.07);
        }

        #panel-lending .feat-hero-title {
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--color-on-surface);
            letter-spacing: -0.3px;
            margin-bottom: 6px;
            line-height: 1.25;
        }

        #panel-lending .feat-hero-desc {
            font-size: 0.835rem;
            color: var(--color-secondary);
            line-height: 1.55;
            margin-bottom: 0;
            flex: 1;
        }

        #panel-lending .feat-hero-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        #panel-lending .feat-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Secondary featured card */
        #panel-lending .feat-secondary {
            background: #ffffff;
            border: 1px solid #f0e0e0;
            border-radius: 14px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px -4px rgba(87, 0, 0, .08);
            transition: box-shadow .25s, transform .25s;
        }

        #panel-lending .feat-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -6px rgba(87, 0, 0, .13);
        }

        #panel-lending .feat-secondary-img {
            height: 130px;
            background: linear-gradient(145deg, #f7f0f0, #ede4e4);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        #panel-lending .feat-secondary-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s cubic-bezier(.4, 0, .2, 1);
        }

        #panel-lending .feat-secondary-img .material-symbols-outlined {
            font-size: 52px;
            color: #570000;
            opacity: .12;
        }

        #panel-lending .feat-secondary:hover .feat-secondary-img img {
            transform: scale(1.08);
        }

        #panel-lending .feat-secondary-body {
            padding: 16px 18px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        #panel-lending .feat-secondary-title {
            font-family: 'Hanken Grotesk', var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-on-surface);
            letter-spacing: -0.15px;
            margin-bottom: 3px;
        }

        #panel-lending .feat-secondary-cat {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--color-secondary);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 12px;
        }

        #panel-lending .feat-secondary-cat .material-symbols-outlined {
            font-size: 11px;
            color: rgba(87, 0, 0, .4);
        }

        /* ── Catalog section ──────────────────────────────────────── */
        #panel-lending .catalog-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }


        #panel-lending .catalog-count-chip {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--color-secondary);
            background: #f3f4f6;
            border-radius: 20px;
            padding: 3px 10px;
        }

        /* Catalog glass card */
        #panel-lending .catalog-card {
            background: rgba(255, 255, 255, .82) !important;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(240, 224, 224, .7) !important;
            /* Top accent is the curved gold ::before in faculty-dashboard.css */
            border-top: 0 !important;
            border-radius: 16px !important;
            padding: 24px !important;
            box-shadow: 0 4px 24px rgba(87, 0, 0, .05) !important;
        }

        /* Filter bar: search + category, one height, one radius, same focus ring as the modal fields */
        #panel-lending .catalog-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        #panel-lending .catalog-search-wrap {
            flex: 1;
            min-width: 220px;
            min-height: 46px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--color-surface-lowest) !important;
            border: 1.5px solid var(--color-outline-variant) !important;
            border-radius: 12px !important;
            padding: 0 8px 0 16px !important;
            transition: border-color .18s, box-shadow .18s;
        }

        #panel-lending .catalog-search-wrap:hover {
            border-color: var(--color-outline) !important;
        }

        #panel-lending .catalog-search-wrap:focus-within {
            border-color: var(--color-primary-container) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary-container) 16%, transparent) !important;
        }

        #panel-lending .catalog-search-wrap .material-symbols-outlined {
            font-size: 20px;
            color: var(--color-secondary);
            flex-shrink: 0;
        }

        #panel-lending .catalog-search-wrap input {
            flex: 1;
            min-width: 0;
            align-self: stretch;
            border: none;
            background: transparent;
            outline: none;
            font-family: var(--font-sans);
            font-size: 0.9rem;
            color: var(--color-on-surface);
            width: 100%;
        }

        #panel-lending .catalog-search-wrap input::placeholder {
            color: var(--color-secondary);
        }

        #panel-lending .catalog-search-clear {
            flex-shrink: 0;
            display: inline-grid;
            place-items: center;
            width: 30px;
            height: 30px;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: var(--color-secondary);
            cursor: pointer;
            transition: background .15s, color .15s;
        }

        #panel-lending .catalog-search-clear[hidden] {
            display: none;
        }

        #panel-lending .catalog-search-clear .material-symbols-outlined {
            font-size: 18px;
        }

        #panel-lending .catalog-search-clear:hover {
            background: var(--color-surface-container);
            color: var(--color-on-surface);
        }

        #panel-lending .catalog-search-clear:focus-visible {
            outline: 2px solid var(--accent-gold-deep);
            outline-offset: 1px;
        }

        #panel-lending .catalog-filter-select {
            min-height: 46px;
            min-width: 190px;
            padding: 0 40px 0 16px !important;
            border: 1.5px solid var(--color-outline-variant) !important;
            border-radius: 12px !important;
            background-color: var(--color-surface-lowest) !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23777' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            appearance: none;
            -webkit-appearance: none;
            color: var(--color-on-surface) !important;
            font-family: var(--font-sans);
            font-size: 0.9rem !important;
            font-weight: 500;
            outline: none;
            cursor: pointer;
            transition: border-color .18s, box-shadow .18s;
        }

        #panel-lending .catalog-filter-select:hover {
            border-color: var(--color-outline) !important;
        }

        #panel-lending .catalog-filter-select:focus {
            border-color: var(--color-primary-container) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary-container) 16%, transparent) !important;
        }

        /* ── Equipment grid ───────────────────────────────────────── */
        #panel-lending .eq-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important;
            gap: 16px !important;
        }

        /* ── Equipment card ───────────────────────────────────────── */
        #panel-lending .eq-item-card,
        #panel-lending .item-node {
            background: #ffffff !important;
            border: 1.5px solid #f0e0e0 !important;
            border-radius: 14px !important;
            overflow: hidden;
            box-shadow: 0 2px 12px -2px rgba(87, 0, 0, .07) !important;
            transition: transform .28s cubic-bezier(.4, 0, .2, 1), box-shadow .28s, border-color .28s !important;
            position: relative;
            display: flex !important;
            flex-direction: column !important;
        }

        #panel-lending .eq-item-card::after,
        #panel-lending .item-node::after,
        #panel-lending .feat-hero::after,
        #panel-lending .feat-secondary::after {
            /* accent: the same curved gold band as every other card and table, always shown */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 30px;
            border: 3px solid transparent;
            border-bottom: 0;
            border-top-left-radius: 13px;
            border-top-right-radius: 13px;
            background:
                linear-gradient(90deg, var(--accent-gold-deep) 0%, var(--accent-gold-light) 50%, var(--accent-gold-deep) 100%) top / 100% 3px no-repeat,
                linear-gradient(180deg, var(--accent-gold-deep) 0%, transparent 100%);
            background-origin: border-box;
            -webkit-mask: linear-gradient(#000 0 0) padding-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) padding-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            pointer-events: none;
            z-index: 2;
        }

        #panel-lending .eq-item-card:hover,
        #panel-lending .item-node:hover {
            transform: translateY(-4px) !important;
            border-color: rgba(184, 145, 58, .55) !important;
            box-shadow: 0 12px 28px -5px rgba(87, 0, 0, .13), 0 4px 10px -4px rgba(87, 0, 0, .07) !important;
        }

        /* Image zone */
        #panel-lending .eq-item-img-wrap {
            position: relative;
            overflow: hidden;
            background: linear-gradient(160deg, #fdf1f1 0%, #f5e8e8 100%);
            height: 160px;
        }

        #panel-lending .eq-item-img {
            width: 100% !important;
            height: 160px !important;
            object-fit: cover !important;
            display: block;
            transition: transform .45s cubic-bezier(.4, 0, .2, 1) !important;
        }

        #panel-lending .eq-item-card:hover .eq-item-img,
        #panel-lending .item-node:hover .eq-item-img {
            transform: scale(1.07) !important;
        }

        #panel-lending .eq-item-img-placeholder {
            width: 100% !important;
            height: 160px !important;
            background: linear-gradient(160deg, #fdf1f1 0%, #f0e4e4 100%) !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #panel-lending .eq-item-img-placeholder .material-symbols-outlined {
            font-size: 48px !important;
            color: #570000 !important;
            opacity: .12 !important;
        }

        /* Stock badge overlay on image */
        #panel-lending .eq-stock-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
        }

        /* Card body */
        #panel-lending .eq-item-body {
            padding: 14px 16px 16px !important;
            display: flex !important;
            flex-direction: column !important;
            flex: 1;
            background: #fff !important;
            border-top: 1px solid #f8eded !important;
        }

        #panel-lending .eq-item-name {
            font-family: 'Hanken Grotesk', var(--font-display) !important;
            font-weight: 700 !important;
            font-size: 0.975rem !important;
            color: var(--color-on-surface) !important;
            margin-bottom: 4px !important;
            letter-spacing: -0.1px;
            line-height: 1.3;
            transition: color .18s;
        }

        #panel-lending .eq-item-card:hover .eq-item-name,
        #panel-lending .item-node:hover .eq-item-name {
            color: #570000 !important;
        }

        #panel-lending .eq-item-cat {
            font-size: 0.69rem !important;
            font-weight: 600 !important;
            color: var(--color-secondary) !important;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 10px !important;
        }

        #panel-lending .eq-item-cat .material-symbols-outlined {
            font-size: 11px !important;
            color: rgba(87, 0, 0, .4) !important;
        }

        /* Stock badges */
        #panel-lending .stock-badge {
            display: inline-flex !important;
            align-items: center;
            gap: 4px !important;
            padding: 3px 9px !important;
            border-radius: 20px !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            margin-bottom: 12px !important;
            width: fit-content;
            letter-spacing: 0.01em;
        }

        #panel-lending .stock-avail {
            background: #d1fae5 !important;
            color: #065f46 !important;
            border: 1px solid rgba(16, 185, 129, .18) !important;
        }

        #panel-lending .stock-avail .material-symbols-outlined {
            font-size: 12px !important;
            color: #059669 !important;
        }

        #panel-lending .stock-unavail {
            background: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid rgba(239, 68, 68, .15) !important;
        }

        #panel-lending .stock-unavail .material-symbols-outlined {
            font-size: 12px !important;
            color: #dc2626 !important;
        }

        /* Borrow button: icon + label, one height with the featured button, gold keyboard focus */
        #panel-lending .btn-borrow {
            width: 100% !important;
            min-height: 42px;
            padding: 0 14px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #570000 !important;
            color: #fff !important;
            border: 1.5px solid #570000 !important;
            border-radius: 10px !important;
            cursor: pointer;
            font-weight: 600 !important;
            font-size: 0.86rem !important;
            font-family: var(--font-sans);
            margin-top: auto;
            box-shadow: 0 2px 8px rgba(87, 0, 0, .22) !important;
            transition: background .2s, transform .2s, box-shadow .2s !important;
            letter-spacing: 0.01em;
        }

        #panel-lending .btn-borrow .material-symbols-outlined {
            font-size: 18px;
            line-height: 1;
        }

        #panel-lending .btn-borrow:hover:not(:disabled) {
            background: #3d0000 !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 16px rgba(87, 0, 0, .32) !important;
        }

        #panel-lending .btn-borrow:active:not(:disabled) {
            transform: translateY(0) !important;
            box-shadow: 0 1px 4px rgba(87, 0, 0, .25) !important;
        }

        #panel-lending .btn-borrow:focus-visible,
        #panel-lending .btn-borrow-primary:focus-visible {
            outline: 2px solid var(--accent-gold-deep);
            outline-offset: 2px;
        }

        #panel-lending .btn-borrow:disabled,
        #panel-lending .btn-borrow[disabled] {
            background: var(--color-surface-container) !important;
            color: var(--color-secondary) !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            border: 1.5px solid var(--color-outline-variant) !important;
        }

        #panel-lending .btn-borrow-blocked {
            background: transparent !important;
            color: var(--color-secondary) !important;
            border: 1.5px solid var(--color-outline-variant) !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
        }

        /* Featured borrow button (outline ghost style) */
        #panel-lending .btn-borrow-ghost {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: 1.5px solid #e5e7eb;
            border-radius: 9px;
            background: transparent;
            color: var(--color-on-surface-variant);
            font-size: 0.82rem;
            font-weight: 600;
            font-family: var(--font-sans);
            cursor: pointer;
            transition: border-color .18s, background .18s, color .18s;
        }

        #panel-lending .btn-borrow-ghost:hover {
            border-color: #570000;
            color: #570000;
            background: #fdf1f1;
        }

        #panel-lending .btn-borrow-ghost .material-symbols-outlined {
            font-size: 15px;
        }

        /* Featured primary borrow button */
        #panel-lending .btn-borrow-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 22px;
            justify-content: center;
            background: #570000;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: var(--font-sans);
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(87, 0, 0, .28);
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        #panel-lending .btn-borrow-primary:hover:not(:disabled) {
            background: #3d0000;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(87, 0, 0, .32);
        }

        #panel-lending .btn-borrow-primary:disabled {
            background: var(--color-surface-container);
            color: var(--color-secondary);
            box-shadow: none;
            cursor: not-allowed;
        }

        #panel-lending .btn-borrow-primary .material-symbols-outlined {
            font-size: 18px;
        }

        /* Empty state */
        #panel-lending .eq-empty {
            grid-column: 1/-1;
            text-align: center;
            padding: 5rem 1rem;
        }

        #panel-lending .eq-empty .material-symbols-outlined {
            font-size: 52px !important;
            display: block;
            margin-bottom: 14px;
            opacity: .18 !important;
            color: #570000 !important;
        }

        #panel-lending .eq-empty p {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--color-secondary) !important;
        }

        /* ── Dark theme ───────────────────────────────────────────── */
        [data-theme="dark"] #panel-lending .eq-item-card,
        [data-theme="dark"] #panel-lending .item-node {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-body {
            background: var(--color-surface-low) !important;
            border-top-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-name {
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-card:hover .eq-item-name,
        [data-theme="dark"] #panel-lending .item-node:hover .eq-item-name {
            color: #f4a0a0 !important;
        }

        [data-theme="dark"] #panel-lending .catalog-card {
            background: rgba(35, 21, 21, .85) !important;
            border-color: var(--color-outline-variant) !important;
            border-top-color: #570000 !important;
        }

        [data-theme="dark"] #panel-lending .catalog-search-wrap,
        [data-theme="dark"] #panel-lending .catalog-filter-select {
            background-color: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .lending-nav-btn,
        [data-theme="dark"] #panel-rooms .lending-nav-btn {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
            color: var(--color-on-surface-variant) !important;
        }

        [data-theme="dark"] #panel-lending .lending-nav-btn.active,
        [data-theme="dark"] #panel-rooms .lending-nav-btn.active {
            background: var(--color-primary-container) !important;
            border-color: var(--color-primary-container) !important;
            color: #fff !important;
        }


        [data-theme="dark"] #panel-lending .btn-borrow:not(:disabled),
        [data-theme="dark"] #panel-lending .btn-borrow-primary:not(:disabled) {
            background: var(--color-primary-container) !important;
        }

        [data-theme="dark"] #panel-lending .feat-hero,
        [data-theme="dark"] #panel-lending .feat-secondary {
            background: var(--color-surface-container) !important;
            border-color: var(--color-outline-variant) !important;
        }

        [data-theme="dark"] #panel-lending .feat-hero-title,
        [data-theme="dark"] #panel-lending .feat-secondary-title,
        [data-theme="dark"] #panel-lending .featured-label {
            color: var(--color-on-surface) !important;
        }

        [data-theme="dark"] #panel-lending .eq-item-img-placeholder,
        [data-theme="dark"] #panel-lending .feat-hero-img-placeholder,
        [data-theme="dark"] #panel-lending .feat-secondary-img {
            background: var(--color-surface-high) !important;
        }

        [data-theme="dark"] #panel-lending .catalog-count-chip {
            background: var(--color-surface-high) !important;
            color: var(--color-on-surface-variant) !important;
        }

        [data-theme="dark"] #panel-lending .btn-borrow-ghost {
            border-color: var(--color-outline-variant) !important;
            color: var(--color-on-surface-variant) !important;
        }
    </style>
    <?php role_splash_head(); ?>
</head>

<body>

    <?php render_role_splash('Faculty'); ?>

    <!-- ================================================================
     SIDE NAVIGATION
================================================================ -->
    <nav class="side-nav" id="sideNav">
        <div class="side-nav-brand">
            <!-- Logo is a plain mark now (no longer the collapse button) -->
            <div class="side-nav-logo" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"
                    aria-hidden="true">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div class="side-nav-brand-text">
                <span class="side-nav-title"><strong>PUP</strong><span class="snt-light">SYNC</span></span>
                <span class="side-nav-sub">Faculty Portal</span>
            </div>
            <!-- Sidebar toggle: desktop = collapse / expand, phones = close the drawer -->
            <button type="button" class="side-nav-toggle" id="sidebarCollapseBtn"
                title="Collapse sidebar" aria-label="Collapse sidebar" aria-expanded="true"
                aria-controls="sideNav">
                <svg class="sn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <rect x="3" y="4" width="18" height="16" rx="3.5" />
                    <path d="M9.5 4v16" />
                    <rect class="sn-fill" x="4.6" y="5.6" width="3.3" height="12.8" rx="1.4" fill="currentColor" stroke="none" />
                </svg>
            </button>
        </div>

        <div class="side-nav-links">
            <div class="side-nav-section" role="group" aria-labelledby="fnavg-overview">
                <span class="side-nav-section-title" id="fnavg-overview">Overview</span>
                <a class="side-nav-item active" id="nav-home" data-tab="home" href="#">
                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a class="side-nav-item" id="nav-activity" data-tab="activity" href="#">
                    <span class="material-symbols-outlined">history</span>
                    <span>My Activity</span>
                </a>
            </div>
            <div class="side-nav-section" role="group" aria-labelledby="fnavg-operations">
                <span class="side-nav-section-title" id="fnavg-operations">Operations</span>
                <a class="side-nav-item" id="nav-lending" data-tab="lending" href="#">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <span>Equipment</span>
                </a>
                <a class="side-nav-item" id="nav-rooms" data-tab="rooms" href="#">
                    <span class="material-symbols-outlined">meeting_room</span>
                    <span>Facilities</span>
                </a>
            </div>
        </div>

        <div class="side-nav-footer">
            <!-- Signed-in faculty: the name only. Not a button, no avatar. Notifications and Settings
                 live in the Dashboard's top-right bell / avatar. (.side-nav-user-name stays on the span:
                 faculty-dashboard.js reads and updates it.) -->
            <div class="side-nav-user" id="navAccountGroup" hidden aria-hidden="true" style="display:none;">
                <span class="side-nav-user-name"><?php echo htmlspecialchars($fullname); ?></span>
            </div>
            <!-- Settings: just under the divider line, directly above Log Out (opens the Settings overlay,
                 same as the Dashboard avatar). #nav-settings is what _openOverlayDOM() highlights. -->
            <a class="side-nav-item" id="nav-settings" href="#" data-action="open-overlay" data-target="settingsOverlay">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            <!-- Log Out — pinned to the very bottom of the sidebar -->
            <a class="side-nav-item side-nav-signout" id="nav-signout" href="#" data-action="logout">
                <span class="material-symbols-outlined">logout</span>
                <span>Log Out</span>
            </a>
        </div>
    </nav>

    <!-- ================================================================
     MAIN WRAPPER (right of sidebar)
================================================================ -->
    <div class="main-wrapper">

        <!-- Top bar removed. Only the mobile menu button remains (shown at <=768px)
             so the sidebar drawer can still be opened on phones. -->
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation">
            <svg class="sn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <rect x="3" y="4" width="18" height="16" rx="3.5" />
                <path d="M9.5 4v16" />
                <rect class="sn-fill" x="4.6" y="5.6" width="3.3" height="12.8" rx="1.4" fill="currentColor" stroke="none" />
            </svg>
        </button>

        <?php
        /* ── GLOBAL SEARCH — DORMANT ──────────────────────────────────────
           Switched off on purpose for now and not rendered. Markup kept so
           it can be restored: put it back in a container and set
           GLOBAL_SEARCH_ENABLED = true in faculty-dashboard.js.

           <div class="top-bar-global-search" id="globalSearchWrap">
               <button type="button" class="top-bar-global-search-toggle" id="globalSearchToggle"
                   aria-label="Open dashboard search" aria-expanded="false">
                   <span class="material-symbols-outlined" aria-hidden="true">search</span>
               </button>
               <input type="search" id="globalSearch" placeholder="Search dashboard" autocomplete="off"
                   aria-label="Search dashboard">
           </div>
        ─────────────────────────────────────────────────────────────────── */
        ?>

        <!-- ================================================================
     MAIN CANVAS
================================================================ -->
        <main class="app-main" id="appMain">

            <!-- Success Alert -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-banner alert-success" id="success-alert">
                    <span class="material-symbols-outlined">check_circle</span>
                    <strong>Success!</strong> Your borrow request has been submitted for approval.
                    <button class="alert-close" data-action="dismiss-alert" data-target="success-alert" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Borrow request could not be filed -->
            <?php if (!empty($_GET['borrow_error'])): ?>
                <div class="alert-banner alert-error" id="borrow-error-alert" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span><strong>Could not submit your request.</strong> <?php echo htmlspecialchars(mb_substr((string)$_GET['borrow_error'], 0, 300)); ?></span>
                    <button class="alert-close" data-action="dismiss-alert" data-target="borrow-error-alert" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Overdue Toast — bottom-center overlay, doesn't affect layout.
                 Shown once per login session (server-gated below); auto-
                 dismisses after 8s via JS (see checkOverdueState/showOverdueToast). -->
            <div class="overdue-toast" id="overdue-alert" data-should-show="<?php echo $show_overdue_toast ? '1' : '0'; ?>" role="alert" aria-live="assertive">
                <span class="material-symbols-outlined">warning</span>
                <div class="overdue-toast-text">
                    <strong>Borrowing Blocked:</strong> You have overdue equipment. Return it to the Admin Office before you can borrow again.
                </div>
                <button class="overdue-toast-close" data-action="dismiss-alert" data-target="overdue-alert" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- ============================================================
         TAB: DASHBOARD (HOME)  — Redesigned v3
    ============================================================ -->
            <div class="tab-panel active" id="panel-home">
                <?php
                // ── Faculty Dashboard ──────────────────────────────────────────────────
                // Built from this faculty member's own loans ($requests_js), room reservations
                // ($room_reservations) and notification count, all already loaded by this page.
                $fdb_h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                $fdb_pl       = fn(int $n, string $w) => $n . ' ' . $w . ($n === 1 ? '' : 's');
                $fdb_today    = date('Y-m-d');
                $fdb_now_hms  = date('H:i:s');
                $fdb_t12      = fn(?string $t) => $t ? date('g:i A', strtotime($t)) : '';
                $fdb_ago      = function (?string $dt): string {
                    $t = $dt ? strtotime($dt) : 0;
                    if (!$t) return '';
                    $d = time() - $t;
                    if ($d < 60)     return 'Just now';
                    if ($d < 3600)   return floor($d / 60) . ' min ago';
                    if ($d < 86400)  return floor($d / 3600) . ' hr ago';
                    if ($d < 604800) {
                        $n = (int)floor($d / 86400);
                        return $n . ' day' . ($n === 1 ? '' : 's') . ' ago';
                    }
                    return date('M j', $t);
                };

                // Loans + rooms: counts for the cards, today's schedule
                $fdb_due_today = 0;
                $fdb_rooms_n   = 0;
                $fdb_sched     = [];
                foreach ($requests_js as $fdb_v_r) {
                    $fdb_v_st = trim((string)($fdb_v_r['status'] ?? ''));
                    if ($fdb_v_st !== 'Approved' || substr((string)$fdb_v_r['return_date'], 0, 10) !== $fdb_today) continue;
                    $fdb_due_today++;
                    $fdb_v_qty = max(1, (int)($fdb_v_r['borrow_qty'] ?? 1));
                    $fdb_sched[] = [
                        'sort'  => !empty($fdb_v_r['return_time']) ? $fdb_v_r['return_time'] : '99:99:99',
                        'time'  => !empty($fdb_v_r['return_time']) ? $fdb_t12($fdb_v_r['return_time']) : 'Due',
                        'icon'  => 'inventory_2',
                        'title' => $fdb_v_r['equipment_name'] . ($fdb_v_qty > 1 ? ' ×' . $fdb_v_qty : ''),
                        'meta'  => 'Return',
                        'past'  => false,
                    ];
                }
                foreach ($room_reservations as $fdb_v_rr) {
                    if (($fdb_v_rr['status'] ?? '') !== 'Approved') continue;
                    $fdb_v_d = (string)$fdb_v_rr['reservation_date'];
                    $fdb_v_over = ($fdb_v_d === $fdb_today && (string)$fdb_v_rr['end_time'] < $fdb_now_hms);
                    if ($fdb_v_d > $fdb_today || ($fdb_v_d === $fdb_today && !$fdb_v_over)) $fdb_rooms_n++;
                    if ($fdb_v_d !== $fdb_today) continue;
                    $fdb_sched[] = [
                        'sort'  => $fdb_v_rr['start_time'],
                        'time'  => $fdb_t12($fdb_v_rr['start_time']),
                        'icon'  => 'meeting_room',
                        'title' => $fdb_v_rr['room_name'],
                        'meta'  => 'Until ' . $fdb_t12($fdb_v_rr['end_time']),
                        'past'  => $fdb_v_over,
                    ];
                }
                usort($fdb_sched, fn($a, $b) => strcmp($a['sort'], $b['sort']));
                $fdb_sched = array_slice($fdb_sched, 0, 5);

                // Chart data: borrows (equipment requests) and room reservations, counted on the day
                // they are for (borrow date / reservation date). Declined and cancelled ones don't count.
                $fdb_by_day = [];
                foreach ($requests_js as $fdb_v_r) {
                    if (in_array(trim((string)($fdb_v_r['status'] ?? '')), ['Declined', 'Cancelled'], true)) continue;
                    $fdb_v_d = substr((string)($fdb_v_r['borrow_date'] ?? ''), 0, 10);
                    if ($fdb_v_d === '') continue;
                    $fdb_by_day[$fdb_v_d]['eq'] = ($fdb_by_day[$fdb_v_d]['eq'] ?? 0) + 1;
                }
                foreach ($room_reservations as $fdb_v_rr) {
                    if (in_array((string)($fdb_v_rr['status'] ?? ''), ['Declined', 'Cancelled'], true)) continue;
                    $fdb_v_d = substr((string)($fdb_v_rr['reservation_date'] ?? ''), 0, 10);
                    if ($fdb_v_d === '') continue;
                    $fdb_by_day[$fdb_v_d]['rm'] = ($fdb_by_day[$fdb_v_d]['rm'] ?? 0) + 1;
                }
                // Months view: last 6 months (this month included)
                $fdb_months = [];
                for ($fdb_v_i = 5; $fdb_v_i >= 0; $fdb_v_i--) {
                    $fdb_v_ts = strtotime(date('Y-m-01') . " -$fdb_v_i months");
                    $fdb_months[date('Y-m', $fdb_v_ts)] = [
                        'label' => date('M', $fdb_v_ts),
                        'sub' => '',
                        'long' => date('F Y', $fdb_v_ts),
                        'now' => date('Y-m', $fdb_v_ts) === date('Y-m'),
                        'eq' => 0,
                        'rm' => 0
                    ];
                }
                foreach ($fdb_by_day as $fdb_v_d => $fdb_v_c) {
                    $fdb_v_k = substr($fdb_v_d, 0, 7);
                    if (!isset($fdb_months[$fdb_v_k])) continue;
                    $fdb_months[$fdb_v_k]['eq'] += $fdb_v_c['eq'] ?? 0;
                    $fdb_months[$fdb_v_k]['rm'] += $fdb_v_c['rm'] ?? 0;
                }
                // Week view: Monday to Sunday of this week
                $fdb_week = [];
                $fdb_mon  = strtotime('-' . ((int)date('N') - 1) . ' days', strtotime($fdb_today));
                for ($fdb_v_i = 0; $fdb_v_i < 7; $fdb_v_i++) {
                    $fdb_v_ts = strtotime("+$fdb_v_i days", $fdb_mon);
                    $fdb_v_d  = date('Y-m-d', $fdb_v_ts);
                    $fdb_week[] = [
                        'label' => date('D', $fdb_v_ts),
                        'sub' => date('j', $fdb_v_ts),
                        'long' => date('l, F j', $fdb_v_ts),
                        'now' => $fdb_v_d === $fdb_today,
                        'eq' => (int)($fdb_by_day[$fdb_v_d]['eq'] ?? 0),
                        'rm' => (int)($fdb_by_day[$fdb_v_d]['rm'] ?? 0)
                    ];
                }
                $fdb_has_chart = array_sum(array_column($fdb_months, 'eq')) + array_sum(array_column($fdb_months, 'rm'))
                    + array_sum(array_column($fdb_week, 'eq')) + array_sum(array_column($fdb_week, 'rm')) > 0;

                // Vertical axis: whole numbers only, 2 or 4 even steps up to the biggest value
                $fdb_axis = function (array $rows): array {
                    $max = max(1, max(array_merge(array_column($rows, 'eq'), array_column($rows, 'rm'))));
                    if ($max <= 2) return [1, 2];
                    $step = (int)ceil($max / 4);
                    foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 500, 1000] as $nice) {
                        if ($nice >= $step) {
                            $step = $nice;
                            break;
                        }
                    }
                    return [$step, 4];
                };
                // One chart (numbered vertical axis + two bars per column + hover card)
                $fdb_chart = function (array $rows, string $label) use ($fdb_axis, $fdb_h): string {
                    [$step, $n] = $fdb_axis($rows);
                    $top = $step * $n;
                    ob_start(); ?>
                    <div class="fdb-plot">
                        <div class="fdb-yaxis" aria-hidden="true">
                            <?php for ($i = 0; $i <= $n; $i++): ?><span style="bottom:<?php echo round($i / $n * 100, 2); ?>%"><?php echo $i * $step; ?></span><?php endfor; ?>
                        </div>
                        <div class="fdb-plotarea">
                            <div class="fdb-lines" aria-hidden="true">
                                <?php for ($i = 0; $i <= $n; $i++): ?><i style="bottom:<?php echo round($i / $n * 100, 2); ?>%"></i><?php endfor; ?>
                            </div>
                            <div class="fdb-chart" role="group" aria-label="<?php echo $fdb_h($label); ?>" style="grid-template-columns:repeat(<?php echo count($rows); ?>,minmax(0,1fr))">
                                <?php foreach ($rows as $c): ?>
                                    <div class="fdb-chart-col<?php echo $c['now'] ? ' is-now' : ''; ?>" tabindex="0"
                                        aria-label="<?php echo $fdb_h($c['long']); ?> - Borrows: <?php echo (int)$c['eq']; ?>, Reservations: <?php echo (int)$c['rm']; ?>">
                                        <div class="fdb-tip" role="presentation">
                                            <b><?php echo $fdb_h($c['long']); ?></b>
                                            <span><span><i class="fdb-dot fdb-dot--eq"></i>Borrows</span><b><?php echo (int)$c['eq']; ?></b></span>
                                            <span><span><i class="fdb-dot fdb-dot--rm"></i>Reservations</span><b><?php echo (int)$c['rm']; ?></b></span>
                                        </div>
                                        <div class="fdb-chart-bars">
                                            <span class="fdb-bar fdb-bar--eq<?php echo $c['eq'] ? '' : ' is-zero'; ?>" style="height:<?php echo $c['eq'] ? max(3, round($c['eq'] / $top * 100, 1)) : 0; ?>%"></span>
                                            <span class="fdb-bar fdb-bar--rm<?php echo $c['rm'] ? '' : ' is-zero'; ?>" style="height:<?php echo $c['rm'] ? max(3, round($c['rm'] / $top * 100, 1)) : 0; ?>%"></span>
                                        </div>
                                        <span class="fdb-chart-x"><?php echo $fdb_h($c['label']); ?><?php if ($c['sub'] !== '') echo '<small>' . $fdb_h($c['sub']) . '</small>'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php return ob_get_clean();
                };

                // Recent activity (requests + reservations, newest first)
                $fdb_recent = [];
                $fdb_req_map = [
                    'Waiting' => ['Pending', 'warn'],
                    'Approved' => ['Active', 'ok'],
                    'Overdue' => ['Overdue', 'danger'],
                    'Returned' => ['Returned', 'muted'],
                    'Declined' => ['Declined', 'danger']
                ];
                foreach ($requests_js as $fdb_v_r) {
                    $fdb_v_st = trim((string)($fdb_v_r['status'] ?? ''));
                    [$fdb_v_lbl, $fdb_v_tone] = $fdb_req_map[$fdb_v_st] ?? [$fdb_v_st !== '' ? $fdb_v_st : 'Request', 'muted'];
                    $fdb_v_qty = max(1, (int)($fdb_v_r['borrow_qty'] ?? 1));
                    $fdb_recent[] = [
                        'ts' => strtotime((string)$fdb_v_r['request_date']),
                        'dt' => $fdb_v_r['request_date'],
                        'icon' => 'inventory_2',
                        'title' => $fdb_v_r['equipment_name'] . ($fdb_v_qty > 1 ? ' ×' . $fdb_v_qty : ''),
                        'lbl' => $fdb_v_lbl,
                        'tone' => $fdb_v_tone
                    ];
                }
                $fdb_room_map = ['Approved' => ['Reserved', 'info'], 'Cancelled' => ['Cancelled', 'muted'], 'Declined' => ['Declined', 'danger']];
                foreach ($room_reservations as $fdb_v_rr) {
                    $fdb_v_st = (string)($fdb_v_rr['status'] ?? '');
                    [$fdb_v_lbl, $fdb_v_tone] = $fdb_room_map[$fdb_v_st] ?? [$fdb_v_st, 'muted'];
                    $fdb_recent[] = [
                        'ts' => strtotime((string)$fdb_v_rr['request_date']),
                        'dt' => $fdb_v_rr['request_date'],
                        'icon' => 'meeting_room',
                        'title' => $fdb_v_rr['room_name'],
                        'lbl' => $fdb_v_lbl,
                        'tone' => $fdb_v_tone
                    ];
                }
                usort($fdb_recent, fn($a, $b) => $b['ts'] <=> $a['ts']);
                $fdb_recent = array_slice($fdb_recent, 0, 5);

                // Needs your attention (one short line each)
                $fdb_attn = [];
                if ($stat_overdue > 0)  $fdb_attn[] = ['tone' => 'danger', 'icon' => 'schedule',        'act' => 'activity', 'title' => $fdb_pl((int)$stat_overdue, 'overdue return')];
                if ($fdb_due_today > 0) $fdb_attn[] = ['tone' => 'warn',   'icon' => 'event_available', 'act' => 'activity', 'title' => $fdb_due_today . ' due back today'];
                if ($stat_waiting > 0)  $fdb_attn[] = ['tone' => 'warn',   'icon' => 'hourglass_top',   'act' => 'activity', 'title' => $fdb_pl((int)$stat_waiting, 'pending request')];
                if ((int)$notif_count > 0) $fdb_attn[] = ['tone' => 'info', 'icon' => 'notifications',  'act' => 'notif',    'title' => (int)$notif_count . ' unread'];

                $fdb_kpis = [
                    ['tone' => 'ok',                                'icon' => 'swap_horiz',    'label' => 'Active loans', 'val' => (int)$stat_approved, 'stat' => 'approved', 'id' => ''],
                    ['tone' => $stat_overdue > 0 ? 'danger' : 'ok', 'icon' => 'schedule',      'label' => 'Overdue',      'val' => (int)$stat_overdue,  'stat' => 'overdue',  'id' => 'statOverdueVal'],
                    ['tone' => $stat_waiting > 0 ? 'warn' : 'ok',   'icon' => 'hourglass_top', 'label' => 'Pending',      'val' => (int)$stat_waiting,  'stat' => 'waiting',  'id' => ''],
                    ['tone' => 'info',                              'icon' => 'meeting_room',  'label' => 'Reservations', 'val' => $fdb_rooms_n,        'stat' => '',         'id' => ''],
                ];
                ?>

                <div class="fdb" id="fdb">

                    <!-- Header: greeting, date, quick actions, bell + avatar -->
                    <div class="dash-flat-header fdb-head">
                        <div class="dash-flat-titles">
                            <h1 class="dash-flat-title">Good <?php
                                                                $fdb_v_h = (int)date('H');
                                                                echo $fdb_v_h < 12 ? 'morning' : ($fdb_v_h < 17 ? 'afternoon' : 'evening');
                                                                ?>, <span data-first-name><?php echo htmlspecialchars($firstname); ?></span>.</h1>
                            <p class="dash-flat-sub"><?php echo date('l, F j, Y'); ?></p>
                        </div>
                        <div class="fdb-head-tools">
                            <div class="fdb-actions">
                                <button type="button" class="fdb-btn fdb-btn--primary" data-action="go-tab" data-tab="lending" data-lending="browse">
                                    <span class="material-symbols-outlined" aria-hidden="true">add_shopping_cart</span>Borrow
                                </button>
                                <button type="button" class="fdb-btn" data-action="go-tab" data-tab="rooms">
                                    <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>Reserve a room
                                </button>
                            </div>
                            <!-- Dashboard-only shortcuts (same as the admin Dashboard). They live inside #panel-home, so
                             they only show on this tab. The badge keeps id="notifBadge": the notification code in
                             faculty-dashboard.js updates it. The avatar has .avatar-btn so name / photo changes reach it. -->
                            <div class="fd-dash-top-actions">
                                <button type="button" class="fd-top-btn" id="dashNotifBtn" data-action="open-notif-modal"
                                    aria-label="Open notifications<?php echo $notif_count > 0 ? ' — ' . $notif_count . ' unread' : ''; ?>"
                                    title="Notifications">
                                    <span class="material-symbols-outlined">notifications</span>
                                    <b class="fd-top-badge" id="notifBadge"
                                        <?php if ($notif_count <= 0) echo 'hidden'; ?>><?php echo $notif_count; ?></b>
                                </button>
                                <button type="button" class="avatar-btn fd-top-avatar" id="dashAvatarBtn"
                                    data-action="open-overlay" data-target="settingsOverlay"
                                    aria-label="Open my account" title="My account"><?php if ($profile_pic_url): ?><img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.removeProperty('display');"><span class="avatar-initials-fallback" style="display:none;"><?php echo htmlspecialchars($initials); ?></span><?php else: ?><?php echo htmlspecialchars($initials); ?><?php endif; ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Key figures -->
                    <section class="fdb-kpis" id="dashStats" aria-label="Key figures">
                        <?php foreach ($fdb_kpis as $fdb_v_k): ?>
                            <button type="button" class="fdb-kpi fdb-tone-<?php echo $fdb_h($fdb_v_k['tone']); ?>"
                                <?php if ($fdb_v_k['stat'] !== '') echo 'data-stat="' . $fdb_h($fdb_v_k['stat']) . '"'; ?>
                                data-action="go-tab" data-tab="activity">
                                <span class="fdb-kpi-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_k['icon']); ?></span></span>
                                <span class="fdb-kpi-body">
                                    <span class="fdb-kpi-val" <?php if ($fdb_v_k['id'] !== '') echo ' id="' . $fdb_h($fdb_v_k['id']) . '"'; ?>><?php echo (int)$fdb_v_k['val']; ?></span>
                                    <span class="fdb-kpi-label"><?php echo $fdb_h($fdb_v_k['label']); ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </section>

                    <div class="fdb-grid">

                        <!-- My activity: borrows + reservations, Months / Week (CSS-only toggle), hover for details -->
                        <section class="fdb-card fdb-area-chart" aria-labelledby="fdbChartTitle">
                            <?php if ($fdb_has_chart): ?>
                                <input type="radio" name="fdbRange" id="fdbRangeMonths" class="fdb-range-input" checked>
                                <input type="radio" name="fdbRange" id="fdbRangeWeek" class="fdb-range-input">
                            <?php endif; ?>
                            <header class="fdb-card-head">
                                <h2 id="fdbChartTitle">My activity</h2>
                                <?php if ($fdb_has_chart): ?>
                                    <div class="fdb-head-right">
                                        <div class="fdb-legend">
                                            <span><i class="fdb-dot fdb-dot--eq"></i>Borrows</span>
                                            <span><i class="fdb-dot fdb-dot--rm"></i>Reservations</span>
                                        </div>
                                        <div class="fdb-seg" role="radiogroup" aria-label="Chart range">
                                            <label for="fdbRangeMonths">Months</label>
                                            <label for="fdbRangeWeek">Week</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </header>
                            <?php if ($fdb_has_chart): ?>
                                <div class="fdb-views">
                                    <div class="fdb-view fdb-view--months"><?php echo $fdb_chart(array_values($fdb_months), 'Borrows and reservations per month'); ?></div>
                                    <div class="fdb-view fdb-view--week"><?php echo $fdb_chart($fdb_week, 'Borrows and reservations per day this week'); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="fdb-empty"><span class="material-symbols-outlined" aria-hidden="true">bar_chart</span><span>No activity yet</span></div>
                            <?php endif; ?>
                        </section>

                        <!-- Student Access Code (faculty-only; filled in by the existing code script) -->
                        <section class="fdb-area-code" aria-label="Student access code">
                            <div class="dash-code-card" id="facultyCodePanel">
                                <div class="dash-code-card-header">
                                    <span class="dash-code-card-title">Student Access Code</span>
                                    <span class="dash-code-active-badge" id="fccBadge">ACTIVE</span>
                                </div>
                                <div class="dash-code-body" id="fccBody">
                                    <div class="fcc-loading" style="padding:20px 0;">
                                        <span class="material-symbols-outlined" style="font-size:1.2rem;opacity:.4;animation:spin 1s linear infinite;">sync</span>
                                    </div>
                                </div>
                                <button class="dash-code-generate-btn" id="btnGenerateCode">
                                    Generate New Code
                                </button>
                            </div>
                        </section>

                        <!-- Needs your attention -->
                        <section class="fdb-card fdb-area-attn" aria-labelledby="fdbAttnTitle">
                            <header class="fdb-card-head">
                                <h2 id="fdbAttnTitle">Needs your attention</h2>
                            </header>
                            <?php if ($fdb_attn): ?>
                                <ul class="fdb-attn">
                                    <?php foreach ($fdb_attn as $fdb_v_a): ?>
                                        <li>
                                            <button type="button" class="fdb-attn-item fdb-tone-<?php echo $fdb_h($fdb_v_a['tone']); ?>"
                                                <?php echo $fdb_v_a['act'] === 'notif' ? 'data-action="open-notif-modal"' : 'data-action="go-tab" data-tab="activity"'; ?>>
                                                <span class="fdb-attn-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_a['icon']); ?></span></span>
                                                <span class="fdb-attn-text"><?php echo $fdb_h($fdb_v_a['title']); ?></span>
                                                <span class="material-symbols-outlined fdb-attn-go" aria-hidden="true">chevron_right</span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="fdb-empty fdb-empty--ok"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><span>All caught up</span></div>
                            <?php endif; ?>
                        </section>

                        <!-- My schedule today -->
                        <section class="fdb-card fdb-area-third" aria-labelledby="fdbSchedTitle">
                            <header class="fdb-card-head">
                                <h2 id="fdbSchedTitle">My schedule today</h2>
                            </header>
                            <?php if ($fdb_sched): ?>
                                <ul class="fdb-list">
                                    <?php foreach ($fdb_sched as $fdb_v_s): ?>
                                        <li class="fdb-row<?php echo $fdb_v_s['past'] ? ' is-past' : ''; ?>">
                                            <span class="fdb-row-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_s['icon']); ?></span></span>
                                            <span class="fdb-row-main">
                                                <strong><?php echo $fdb_h($fdb_v_s['title']); ?></strong>
                                                <small><?php echo $fdb_h($fdb_v_s['meta']); ?></small>
                                            </span>
                                            <span class="fdb-time"><?php echo $fdb_h($fdb_v_s['time']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="fdb-empty"><span class="material-symbols-outlined" aria-hidden="true">event_available</span><span>Nothing scheduled today</span></div>
                            <?php endif; ?>
                        </section>

                        <!-- Recent activity -->
                        <section class="fdb-card fdb-area-third" aria-labelledby="fdbRecentTitle">
                            <header class="fdb-card-head">
                                <h2 id="fdbRecentTitle">Recent activity</h2>
                                <button type="button" class="fdb-link" data-action="go-tab" data-tab="activity">View all</button>
                            </header>
                            <?php if ($fdb_recent): ?>
                                <ul class="fdb-list">
                                    <?php foreach ($fdb_recent as $fdb_v_ev): ?>
                                        <li class="fdb-row fdb-tone-<?php echo $fdb_h($fdb_v_ev['tone']); ?>">
                                            <span class="fdb-row-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_ev['icon']); ?></span></span>
                                            <span class="fdb-row-main">
                                                <strong><?php echo $fdb_h($fdb_v_ev['title']); ?></strong>
                                                <small><?php echo $fdb_h($fdb_ago($fdb_v_ev['dt'])); ?></small>
                                            </span>
                                            <span class="fdb-pill"><?php echo $fdb_h($fdb_v_ev['lbl']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="fdb-empty"><span class="material-symbols-outlined" aria-hidden="true">history</span><span>No activity yet</span></div>
                            <?php endif; ?>
                        </section>

                    </div><!-- /fdb-grid -->
                </div><!-- /fdb -->

            </div><!-- /panel-home -->

            <!-- ============================================================
         TAB: EQUIPMENT (LENDING)
    ============================================================ -->
            <div class="tab-panel" id="panel-lending">


                <!-- ── Sub: Browse ─────────────────────────────────────── -->
                <div class="lending-sub active" id="lending-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Browse Equipment</h2>
                        <p class="page-subtitle">Browse the catalog, check availability and request equipment for your class.</p>
                    </div>

                    <!-- ── Featured Section ────────────────────────────────── -->
                    <?php
                    // Featured = the 2 most-borrowed items overall (that are still
                    // available to request). "Borrowed" counts requests that were
                    // actually granted — Approved, Overdue, or Returned — not ones
                    // still pending or that got declined.
                    mysqli_data_seek($inventory_result, 0);
                    $all_items = [];
                    while ($row = mysqli_fetch_assoc($inventory_result)) $all_items[] = $row;

                    $equip_borrow_count = [];
                    $borrow_count_res = mysqli_query(
                        $conn,
                        "SELECT equipment_name, COUNT(*) as cnt FROM tbl_requests
                         WHERE status IN ('Approved','Overdue','Returned')
                         GROUP BY equipment_name"
                    );
                    if ($borrow_count_res) {
                        while ($bcr = mysqli_fetch_assoc($borrow_count_res)) {
                            $equip_borrow_count[strtolower(trim($bcr['equipment_name']))] = (int)$bcr['cnt'];
                        }
                    }
                    function actBorrowCount(string $itemName): int
                    {
                        global $equip_borrow_count;
                        return $equip_borrow_count[strtolower(trim($itemName))] ?? 0;
                    }

                    $featured_avail = array_filter($all_items, fn($r) => $r['quantity'] > 0);
                    usort($featured_avail, function ($a, $b) {
                        $diff = actBorrowCount($b['item_name']) - actBorrowCount($a['item_name']);
                        return $diff !== 0 ? $diff : ($b['quantity'] - $a['quantity']);
                    });
                    $featured_hero = $featured_avail[0] ?? null;
                    $featured_sec  = $featured_avail[1] ?? null;
                    if ($featured_hero || $featured_sec):
                    ?>
                        <div class="featured-section">
                            <div class="featured-label">
                                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">local_fire_department</span>
                                Most Borrowed
                            </div>
                            <div class="featured-grid">
                                <!-- Hero Featured -->
                                <?php if ($featured_hero): ?>
                                    <div class="feat-hero">
                                        <div class="feat-hero-body">
                                            <div>
                                                <div class="feat-hero-meta">
                                                    <span class="stock-badge stock-avail" style="margin-bottom:0;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                        <?php echo (int)$featured_hero['quantity']; ?> available
                                                    </span>
                                                    <?php $heroBorrowCount = actBorrowCount($featured_hero['item_name']);
                                                    if ($heroBorrowCount > 0): ?>
                                                        <span class="stock-badge stock-popular" style="margin-bottom:0;">
                                                            <span class="material-symbols-outlined" style="font-size:12px;">local_fire_department</span>
                                                            Borrowed <?php echo $heroBorrowCount; ?>&times;
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="feat-hero-title" style="margin-top:12px;"><?php echo htmlspecialchars($featured_hero['item_name']); ?></div>
                                                <div class="feat-hero-desc" style="margin-top:6px;"><?php echo !empty($featured_hero['description']) ? htmlspecialchars(mb_substr($featured_hero['description'], 0, 110)) . (mb_strlen($featured_hero['description']) > 110 ? '…' : '') : 'Available for borrowing. Submit a request to reserve this item for your class or event.'; ?></div>
                                            </div>
                                            <div class="feat-hero-actions" style="margin-top:20px;">
                                                <?php if ($has_overdue_block): ?>
                                                    <button class="btn-borrow-primary" disabled>
                                                        <span class="material-symbols-outlined">block</span> Overdue Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-borrow-primary"
                                                        data-action="open-borrow-form"
                                                        data-item="<?php echo htmlspecialchars($featured_hero['item_name'], ENT_QUOTES); ?>">
                                                        <span class="material-symbols-outlined">add_circle</span> Borrow Now
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($featured_hero['image_path'])): ?>
                                            <div class="feat-hero-img">
                                                <img src="<?php echo $root_url . htmlspecialchars($featured_hero['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured_hero['item_name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="feat-hero-img-placeholder">
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Secondary Featured -->
                                <?php if ($featured_sec): ?>
                                    <div class="feat-secondary">
                                        <div class="feat-secondary-img" style="position:relative;">
                                            <?php if (!empty($featured_sec['image_path'])): ?>
                                                <img src="<?php echo $root_url . htmlspecialchars($featured_sec['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured_sec['item_name']); ?>">
                                            <?php else: ?>
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            <?php endif; ?>
                                            <span class="stock-badge stock-avail"
                                                style="position:absolute;top:10px;left:10px;margin-bottom:0;z-index:2;background:rgba(209,250,229,.95);backdrop-filter:blur(4px);">
                                                <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                <?php echo (int)$featured_sec['quantity']; ?> available
                                            </span>
                                            <?php $secBorrowCount = actBorrowCount($featured_sec['item_name']);
                                            if ($secBorrowCount > 0): ?>
                                                <span class="stock-badge stock-popular"
                                                    style="position:absolute;top:10px;right:10px;margin-bottom:0;z-index:2;background:rgba(255,255,255,.85);backdrop-filter:blur(4px);">
                                                    <span class="material-symbols-outlined" style="font-size:12px;">local_fire_department</span>
                                                    <?php echo $secBorrowCount; ?>&times;
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="feat-secondary-body">
                                            <div class="feat-secondary-title"><?php echo htmlspecialchars($featured_sec['item_name']); ?></div>
                                            <div class="feat-secondary-cat">
                                                <span class="material-symbols-outlined">label</span>
                                                <?php echo htmlspecialchars($featured_sec['category']); ?>
                                            </div>
                                            <div style="margin-top:auto;">
                                                <?php if ($has_overdue_block): ?>
                                                    <button class="btn-borrow-primary" style="width:100%;" disabled>
                                                        <span class="material-symbols-outlined" aria-hidden="true">block</span> Overdue Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-borrow-primary" style="width:100%;justify-content:center;"
                                                        data-action="open-borrow-form"
                                                        data-item="<?php echo htmlspecialchars($featured_sec['item_name'], ENT_QUOTES); ?>">
                                                        <span class="material-symbols-outlined" aria-hidden="true">add_shopping_cart</span> Borrow
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ── All Equipment Catalog ───────────────────────────── -->
                    <div class="catalog-card">
                        <div class="catalog-section-header">
                            <span class="catalog-section-title">All Equipment</span>
                            <span class="catalog-count-chip" id="equipCountChip">
                                <?php echo count($all_items); ?> items
                            </span>
                        </div>
                        <div class="catalog-filters">
                            <div class="catalog-search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="equipmentSearch" placeholder="Search by equipment name…" autocomplete="off" aria-label="Search equipment by name">
                                <button type="button" class="catalog-search-clear" id="equipmentSearchClear" data-action="equip-search-clear" aria-label="Clear search" hidden>
                                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                </button>
                            </div>
                            <select id="categoryFilter" class="catalog-filter-select" aria-label="Filter by category">
                                <option value="">All Categories</option>
                                <?php
                                mysqli_data_seek($category_result, 0);
                                while ($cat = mysqli_fetch_assoc($category_result)) {
                                    if (strtolower($cat['category']) === 'others') continue;
                                    echo '<option value="' . htmlspecialchars($cat['category']) . '">' . htmlspecialchars($cat['category']) . '</option>';
                                }
                                ?>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="eq-grid" id="equipmentList">
                            <?php if (empty($all_items)): ?>
                                <div class="eq-empty">
                                    <span class="material-symbols-outlined">inventory_2</span>
                                    <p>No equipment available at the moment.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($all_items as $item): ?>
                                    <div class="eq-item-card item-node"
                                        data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>"
                                        data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>"
                                        data-item-id="<?php echo (int)$item['item_id']; ?>">
                                        <div class="eq-item-img-wrap">
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img class="eq-item-img"
                                                    src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <?php else: ?>
                                                <div class="eq-item-img-placeholder">
                                                    <span class="material-symbols-outlined">inventory_2</span>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Stock badge overlay on image -->
                                            <div class="eq-stock-overlay">
                                                <?php if ($item['quantity'] > 0): ?>
                                                    <span class="stock-badge stock-avail" style="margin-bottom:0;backdrop-filter:blur(4px);background:var(--color-success-container) !important;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                        <?php echo (int)$item['quantity']; ?> available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-badge stock-unavail" style="margin-bottom:0;backdrop-filter:blur(4px);background:var(--color-error-container) !important;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">cancel</span>
                                                        Out of stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="eq-item-body">
                                            <div class="eq-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="eq-item-cat">
                                                <span class="material-symbols-outlined">label</span>
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>
                                            <?php if ($has_overdue_block): ?>
                                                <button class="btn-borrow btn-borrow-blocked" disabled
                                                    title="You have an overdue item. Return it before borrowing again.">
                                                    <span class="material-symbols-outlined" aria-hidden="true">block</span>
                                                    <span class="btn-borrow-label">Overdue Block</span>
                                                </button>
                                            <?php else: ?>
                                                <?php
                                                $eq_shelf = (int)$item['quantity'];
                                                $eq_total = (int)(($bk_items[$item['item_name']]['total'] ?? $eq_shelf));
                                                $eq_ahead = ($eq_shelf <= 0 && $eq_total > 0);   // out now, but it exists: can be booked for later
                                                ?>
                                                <button class="btn-borrow<?php echo $eq_ahead ? ' btn-borrow-ahead' : ''; ?>"
                                                    <?php if ($eq_shelf <= 0 && !$eq_ahead) echo 'disabled'; ?>
                                                    data-action="open-borrow-form"
                                                    data-item="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                    <span class="material-symbols-outlined" aria-hidden="true"><?php echo ($eq_shelf > 0) ? 'add_shopping_cart' : ($eq_ahead ? 'event' : 'block'); ?></span>
                                                    <span class="btn-borrow-label"><?php echo ($eq_shelf > 0) ? 'Borrow' : ($eq_ahead ? 'Book ahead' : 'Unavailable'); ?></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- No results (search/filter matched nothing) -->
                        <div class="eq-empty" id="equipNoResults" style="display:none;">
                            <span class="material-symbols-outlined">search_off</span>
                            <p>No equipment matches your search.</p>
                        </div>

                        <!-- ── Pagination (12 items per page; featured cards above are not counted) ── -->
                        <div class="eq-pagination" id="equipPagination" style="display:none;">
                            <div class="eq-pg-info" id="equipPageInfo"></div>
                            <div class="eq-pg-controls">
                                <button class="eq-pg-btn" id="equipPrevBtn" data-action="equip-page-prev"
                                    aria-label="Previous page">
                                    <span class="material-symbols-outlined">chevron_left</span>
                                </button>
                                <div class="eq-pg-numbers" id="equipPageNumbers"></div>
                                <button class="eq-pg-btn" id="equipNextBtn" data-action="equip-page-next"
                                    aria-label="Next page">
                                    <span class="material-symbols-outlined">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div><!-- /lending-browse -->

                <!-- ── Sub: Borrow Form (now a modal — this sub-panel is kept as an
                     empty shell so existing references to it don't break) ── -->
                <div class="lending-sub" id="lending-form"></div><!-- /lending-form -->


            </div><!-- /panel-lending -->

            <!-- ============================================================
         TAB: FACILITIES (ROOMS)
    ============================================================ -->
            <div class="tab-panel" id="panel-rooms">


                <!-- ── Sub: Browse Facilities ─────────────────────────────── -->
                <div class="lending-sub active" id="rooms-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Browse Facilities</h2>
                        <p class="page-subtitle">Find a room, check its weekly schedule and reserve it for your class or event.</p>
                    </div>
                    <?php include __DIR__ . '/room-reservation/fcty-facilities.php'; ?>
                </div><!-- /rooms-browse -->


            </div><!-- /panel-rooms -->

            <!-- ============================================================
                 TAB: MY ACTIVITY
            ============================================================ -->
            <div class="tab-panel" id="panel-activity">

                <?php
                /* ── My Activity ─────────────────────────────────────────────
                   Three layers, top to bottom:

                     1. Summary         four cards: Active loans · Overdue · Pending · Room reservations
                                        (same words and numbers as the Home tab's key figures)
                     2. Right now       Equipment card | Room reservations card
                                        each one: In progress / Upcoming (rooms also: Waitlist)
                     3. History         one table for both, filtered All / Equipment / Rooms,
                                        with its page navigation in the header next to the filters

                   Both halves are drawn by shared renderers, so the first paint and the live
                   refresh are always identical:
                     equipment  equipment-booking/core/faculty-activity.php   fact_eq_*
                                refreshed by api/poll-requests.php?view=activity
                     rooms      room-reservation/core/faculty-room-quota.php  fact_*
                                refreshed by api/poll-reservations.php?view=activity

                   Status words and colours come from one place, fact_status_chip():
                     Pending = amber · Approved = green · Overdue / Declined = red
                     Returned / Completed / Cancelled = grey
                ─────────────────────────────────────────────────────────────── */
                $act_eq         = fact_eq_load($conn, (string) $_SESSION['faculty_id']);
                $act_eq_stats   = fact_eq_stats($act_eq);
                $act_rooms      = fact_load_rooms($conn, (string) $_SESSION['faculty_id']);
                $act_room_stats = fact_room_stats($act_rooms);
                ?>

                <!-- ── Header ───────────────────────────────────────────── -->
                <header class="fact-head">
                    <div>
                        <h2 class="myact-title">My Activity</h2>
                        <p class="fact-sub">Your equipment loans and room reservations, all in one place.</p>
                    </div>
                    <button type="button" class="fact-btn fact-btn-ghost" data-fact-print>
                        <span class="material-symbols-outlined" aria-hidden="true">download</span>Download Report
                    </button>
                </header>

                <!-- ── Summary: four cards ──────────────────────────────── -->
                <div class="fact-stats" role="group" aria-label="Activity summary">
                    <div class="fact-stat is-ok">
                        <span class="fact-stat-label">Active loans</span>
                        <span class="fact-stat-value" data-fact-stat="loans"><?php echo (int) $act_eq_stats['loans']; ?></span>
                        <span class="fact-stat-note">Approved equipment</span>
                    </div>
                    <div class="fact-stat is-bad<?php echo $act_eq_stats['overdue'] > 0 ? ' is-alert' : ''; ?>">
                        <span class="fact-stat-label">Overdue</span>
                        <span class="fact-stat-value" data-fact-stat="overdue"><?php echo (int) $act_eq_stats['overdue']; ?></span>
                        <span class="fact-stat-note">Past the return time</span>
                    </div>
                    <div class="fact-stat is-wait">
                        <span class="fact-stat-label">Pending</span>
                        <span class="fact-stat-value" data-fact-stat="pending"><?php echo (int) $act_eq_stats['pending']; ?></span>
                        <span class="fact-stat-note">Awaiting approval</span>
                    </div>
                    <div class="fact-stat">
                        <span class="fact-stat-label">Room reservations</span>
                        <span class="fact-stat-value" data-fact-stat="reserved"><?php echo fact_h($act_room_stats['reserved']); ?></span>
                        <span class="fact-stat-note">Active, out of the limit</span>
                    </div>
                </div>

                <!-- ── Right now: Equipment | Room reservations ─────────── -->
                <div class="fact-grid">

                    <section class="fact-card" id="factEquipment" aria-labelledby="factEquipTitle">
                        <header class="fact-card-head">
                            <h3 class="fact-card-title" id="factEquipTitle">Equipment</h3>
                        </header>
                        <!-- Filled here on first paint; swapped live by the My Activity block at the end of faculty-dashboard.js -->
                        <div class="fact-card-body" id="factEquipBody"><?php echo fact_eq_render_body($act_eq, $root_url); ?></div>
                    </section>

                    <section class="fact-card" id="factRooms" aria-labelledby="factRoomsTitle">
                        <header class="fact-card-head">
                            <h3 class="fact-card-title" id="factRoomsTitle">Room reservations</h3>
                        </header>
                        <div class="fact-card-body" id="factRoomsBody"><?php echo fact_render_rooms_body($act_rooms); ?></div>
                    </section>

                </div><!-- /fact-grid -->

                <!-- ── History (equipment + rooms) ──────────────────────── -->
                <section class="fact-card fact-history" id="factHistory" aria-labelledby="factHistTitle">
                    <header class="fact-card-head">
                        <h3 class="fact-card-title" id="factHistTitle">History</h3>
                        <div class="fact-tools">
                            <div class="fact-seg" id="factKindTabs" role="group" aria-label="Show">
                                <button type="button" class="fact-seg-btn is-active" data-fact-kind="all" aria-pressed="true">All</button>
                                <button type="button" class="fact-seg-btn" data-fact-kind="equipment" aria-pressed="false">Equipment</button>
                                <button type="button" class="fact-seg-btn" data-fact-kind="room" aria-pressed="false">Rooms</button>
                            </div>
                            <label class="fact-search">
                                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                                <input type="text" id="factSearch" placeholder="Search history…" autocomplete="off" aria-label="Search history">
                            </label>
                            <nav class="fact-pager" id="factPager" aria-label="History pages" hidden>
                                <span class="fact-pager-info" id="factPagerInfo"></span>
                                <div class="fact-pager-ctl">
                                    <button type="button" class="fact-pager-btn" id="factPrev" aria-label="Previous page"><span class="material-symbols-outlined">chevron_left</span></button>
                                    <div class="fact-pager-nums" id="factNums"></div>
                                    <button type="button" class="fact-pager-btn" id="factNext" aria-label="Next page"><span class="material-symbols-outlined">chevron_right</span></button>
                                </div>
                            </nav>
                        </div>
                    </header>

                    <div class="fact-table-wrap">
                        <table class="fact-table">
                            <thead>
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">When</th>
                                    <th scope="col" class="fact-col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody id="factHistBody"><?php
                                                        echo fact_eq_render_history_rows($act_eq, $root_url);
                                                        echo fact_render_room_history_rows($act_rooms);
                                                        ?></tbody>
                        </table>
                    </div>

                    <p class="fact-noresults" id="factNoResults" hidden>No records match your search.</p>
                    <div class="fact-empty" id="factHistEmpty" hidden>
                        <span class="material-symbols-outlined" aria-hidden="true">history</span>
                        <p class="fact-empty-title">No history yet</p>
                        <p class="fact-empty-sub">Returned equipment and past room reservations will show up here.</p>
                    </div>

                </section><!-- /factHistory -->

            </div><!-- /panel-activity -->

            <!-- ── AI Support Hub ──────────────────────────────────────────── -->
            <div class="act-ai-chat" id="actAiChat">

                <!-- Header -->
                <div class="act-ai-chat-head">
                    <div class="act-ai-chat-head-info">
                        <div class="act-ai-chat-avatar-wrap">
                            <div class="act-ai-chat-avatar">
                                <span class="material-symbols-outlined">smart_toy</span>
                            </div>
                            <span class="act-ai-online-dot"></span>
                        </div>
                        <div>
                            <div class="act-ai-chat-name">PUPSync Room Assistant</div>
                            <div class="act-ai-chat-status">Active now</div>
                        </div>
                    </div>
                    <div class="act-ai-chat-head-btns">
                        <button class="act-ai-chat-head-btn" data-action="ai-chat-minimize" title="Minimise">
                            <span class="material-symbols-outlined">remove</span>
                        </button>
                        <button class="act-ai-chat-head-btn" data-action="ai-chat-close" title="Close">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>

                <!-- Message body -->
                <div class="act-ai-chat-body" id="actAiChatBody">
                    <div class="act-chat-row act-chat-row-ai">
                        <div class="act-chat-ai-avatar">
                            <span class="material-symbols-outlined">smart_toy</span>
                        </div>
                        <div class="act-chat-col">
                            <div class="act-chat-bubble act-chat-bubble-ai">
                                Hi <?php echo htmlspecialchars($fullname); ?>! 👋 I'm your PUPSync Room Assistant. Ask me about room availability, how to reserve, or anything about the facilities.
                            </div>
                            <div class="act-chat-chips" id="actAiChips">
                                <button class="act-chat-chip" data-chip="How do I reserve a room?">How do I reserve a room?</button>
                                <button class="act-chat-chip" data-chip="What rooms are free today?">What rooms are free today?</button>
                                <button class="act-chat-chip" data-chip="How far in advance can I book?">How far in advance can I book?</button>
                                <button class="act-chat-chip" data-chip="Cancel my reservation">Cancel my reservation</button>
                                <button class="act-chat-chip" data-chip="Reservation rules">Reservation rules</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input bar -->
                <div class="act-ai-chat-input-wrap">
                    <div class="act-ai-chat-counter" id="actAiCounterBar">
                        <span id="actAiCounter">—</span> messages remaining this hour
                    </div>
                    <div class="act-ai-chat-input">
                        <input type="text" id="actAiInput" placeholder="Aa" autocomplete="off" maxlength="500">
                        <button class="act-ai-chat-send" id="actAiSendBtn" data-action="ai-chat-send" title="Send">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="act-ai-chat-footer">
                    <a href="#" data-action="ai-chat-report-room-issue">
                        <span class="material-symbols-outlined">flag</span>
                        Report a Room Issue
                    </a>
                </div>

            </div>

            <!-- FAB — hidden by default; JS shows it only on the Facilities tab -->
            <button class="act-ai-fab" id="actAiFab" style="display:none;"
                data-action="ai-fab-toggle" title="Ask about rooms">
                <span class="material-symbols-outlined">smart_toy</span>
                <span class="act-ai-fab-tooltip">Ask about rooms</span>
                <div class="act-ai-fab-popover" id="actAiFabPopover">
                    <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0;">smart_toy</span>
                    <span>Need help finding or booking a room? Ask the AI assistant!</span>
                    <button class="act-ai-fab-popover-close" id="actAiFabPopoverClose" title="Dismiss">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </button>

        </main><!-- /app-main -->
    </div><!-- /main-wrapper -->

    <!-- ================================================================
     OVERLAY: ACCOUNT — merged into settingsOverlay below
     (kept as hidden anchor for legacy data-target references)
================================================================ -->
    <div class="overlay-page" id="accountOverlay">
        <div class="overlay-topbar">
            <button class="overlay-back-btn" data-action="close-overlay" data-target="accountOverlay">
                <span class="material-symbols-outlined">arrow_back</span> Back
            </button>
            <span class="overlay-topbar-title">My Account</span>
            <div class="overlay-topbar-brand"><strong>PUP</strong>SYNC</div>
        </div>
        <div class="account-layout">
            <div class="account-sidebar">
                <span class="account-sidebar-label">My Account</span>
                <button class="acc-nav-btn active" data-acc-tab="acc-overview">
                    <span class="material-symbols-outlined">badge</span> Overview
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-academic">
                    <span class="material-symbols-outlined">school</span> Department Info
                </button>
                <button class="acc-nav-btn" data-acc-tab="acc-contact">
                    <span class="material-symbols-outlined">contacts</span> Contact Details
                </button>
            </div>
            <div class="account-content">

                <!-- Overview -->
                <div id="acc-overview" class="overlay-sub-panel active">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ║ Overview</span>
                        <h2>Profile &amp; Identity</h2>
                        <p>Your personal details and login information.</p>
                    </div>
                    <div class="account-hero-card">
                        <div class="acc-avatar-section">
                            <div class="acc-avatar-large" id="accOverlayAvatar">
                                <?php if ($profile_pic_url): ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile"
                                        class="avatar-img"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.removeProperty('display');">
                                    <span class="avatar-initials-fallback" style="display:none;"><?php echo htmlspecialchars($initials); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($initials); ?>
                                <?php endif; ?>
                            </div>
                            <div style="position:relative;">
                                <button class="btn-change-profile" id="accOverlayChangePhotoBtn"
                                    data-action="open-picture-menu">Change Photo</button>
                                <div class="picture-menu" id="accOverlayPictureMenu" style="display:none;">
                                    <button class="pic-menu-item" data-action="upload-picture">
                                        <span class="material-symbols-outlined"
                                            style="font-size:15px;margin-right:8px;">upload</span>Upload Photo
                                    </button>
                                    <?php if ($profile_pic_url): ?>
                                        <button class="pic-menu-item pic-menu-danger" data-action="remove-picture">
                                            <span class="material-symbols-outlined"
                                                style="font-size:15px;margin-right:8px;">delete</span>Remove Photo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="file" id="accOverlayPicInput" accept="image/jpeg,image/png,image/jpg,image/webp"
                                style="display:none;">
                        </div>
                        <div class="acc-hero-info">
                            <h2>
                                <?php echo htmlspecialchars($fullname); ?>
                            </h2>
                            <p>ID: <span data-fid-display><?php echo htmlspecialchars($fid_label); ?></span></p>
                            <span class="acc-badge">
                                <span
                                    style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px;vertical-align:middle;"></span>
                                Active Faculty
                            </span>
                        </div>
                        <div class="acc-action-wrap">
                            <button class="btn-edit-acc" id="accOverlayEditBtn" data-action="profile-edit">Edit
                                Profile</button>
                            <button class="btn-save-acc" id="accOverlaySaveBtn" style="display:none;"
                                data-action="profile-save">
                                <span class="material-symbols-outlined"
                                    style="font-size:14px;margin-right:4px;">check</span>Save
                            </button>
                            <button class="btn-cancel-acc" id="accOverlayCancelBtn" style="display:none;"
                                data-action="profile-cancel">Cancel</button>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Personal Information</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val" data-field="fullname">
                                <?php echo htmlspecialchars($fullname); ?>
                            </span>
                            <input class="info-input-f" data-input="fullname"
                                value="<?php echo htmlspecialchars($fullname); ?>" disabled style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Faculty ID</span>
                            <span class="info-val <?php echo $fid_unset ? 'empty' : ''; ?>" data-fid-display><?php echo htmlspecialchars($fid_label); ?></span>
                            <?php if ($fid_unset): ?>
                                <button class="btn-inline-action" data-action="open-faculty-id-modal">Add</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Login &amp; Security</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Primary Email</span>
                            <span class="info-val <?php echo $masked_email ? '' : 'empty'; ?>">
                                <?php echo $masked_email ?: '— Not provided'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Backup Email</span>
                            <span class="info-val <?php echo $masked_backup ? '' : 'empty'; ?>"
                                data-field="backup_email">
                                <?php echo $masked_backup ?: '— Not provided'; ?>
                            </span>
                            <?php if (!$backup_locked): ?>
                                <button class="btn-inline-action" data-action="open-backup-email-modal">Add</button>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Password</span>
                            <span class="info-val">••••••••••</span>
                            <button class="btn-inline-action" data-action="open-email-verify-modal">Change</button>
                        </div>
                    </div>
                </div><!-- /acc-overview -->

                <!-- Department -->
                <div id="acc-academic" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ║ Department</span>
                        <h2>Department Information</h2>
                        <p>Your faculty department and assignment details.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Department Assignment</h3>
                            <button class="btn-edit-acc" id="editAcademicBtn" data-action="academic-edit"
                                style="display:inline-flex;">Edit</button>
                            <button class="btn-save-acc" id="saveAcademicBtn" style="display:none;"
                                data-action="academic-save">Save Changes</button>
                            <button class="btn-cancel-acc" id="cancelAcademicBtn" style="display:none;"
                                data-action="academic-cancel">Cancel</button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Faculty ID</span>
                            <span class="info-val <?php echo $fid_unset ? 'empty' : ''; ?>" data-fid-display><?php echo htmlspecialchars($fid_label); ?></span>
                            <?php if ($fid_unset): ?>
                                <button class="btn-inline-action" data-action="open-faculty-id-modal">Add</button>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Full Name</span>
                            <span class="info-val">
                                <?php echo htmlspecialchars($fullname); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Department</span>
                            <span class="info-val <?php echo $department_locked ? '' : 'empty'; ?>" data-field="department">
                                <?php echo $department_locked ? htmlspecialchars($db_department) : '— Not provided'; ?>
                            </span>
                            <?php if (!$department_locked): ?>
                                <select class="info-input-f" data-input="department" disabled style="display:none;">
                                    <option value="">Select Department...</option>
                                    <?php foreach (['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'] as $p): ?>
                                        <option value="<?php echo $p; ?>">
                                            <?php echo $p; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Position / Rank</span>
                            <span class="info-val <?php echo $db_faculty_rank ? '' : 'empty'; ?>" data-field="faculty_rank">
                                <?php echo $db_faculty_rank ? htmlspecialchars($db_faculty_rank) : '— Not provided'; ?>
                            </span>
                            <select class="info-input-f" data-input="faculty_rank" disabled style="display:none;">
                                <option value="">Select Position...</option>
                                <?php foreach (['Instructor I', 'Instructor II', 'Instructor III', 'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Associate Professor I', 'Associate Professor II', 'Professor I', 'Professor II', 'Part-time Faculty'] as $rank): ?>
                                    <option value="<?php echo $rank; ?>">
                                        <?php echo $rank; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Status</span>
                            <span class="info-val"><span class="stock-badge stock-avail">Active / Regular</span></span>
                        </div>
                    </div>
                </div><!-- /acc-academic -->

                <!-- Contact -->
                <div id="acc-contact" class="overlay-sub-panel">
                    <div class="overlay-section-header">
                        <span class="section-eyebrow">My Account ║ Contact</span>
                        <h2>Contact Details</h2>
                        <p>How we can reach you.</p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Address</h3>
                            <button class="btn-edit-acc" id="editContactBtn" data-action="contact-edit"
                                style="display:inline-flex;">Edit</button>
                            <button class="btn-save-acc" id="saveContactBtn" style="display:none;"
                                data-action="contact-save">Save Changes</button>
                            <button class="btn-cancel-acc" id="cancelContactBtn" style="display:none;"
                                data-action="contact-cancel">Cancel</button>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Present Address</span>
                            <span class="info-val <?php echo $db_present_address ? '' : 'empty'; ?>"
                                data-field="present_address">
                                <?php echo $db_present_address ? htmlspecialchars($db_present_address) : '— Not provided'; ?>
                            </span>
                            <textarea class="info-input-f" data-input="present_address"
                                placeholder="Enter your current address" disabled
                                style="display:none;min-height:60px;"></textarea>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Permanent Address</span>
                            <span class="info-val <?php echo $db_permanent_address ? '' : 'empty'; ?>"
                                data-field="permanent_address">
                                <?php echo $db_permanent_address ? htmlspecialchars($db_permanent_address) : '— Not provided'; ?>
                            </span>
                            <textarea class="info-input-f" data-input="permanent_address"
                                placeholder="Enter your permanent address" disabled
                                style="display:none;min-height:60px;"></textarea>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-head">
                            <h3>Phone</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Mobile Number</span>
                            <span class="info-val <?php echo $db_phone ? '' : 'empty'; ?>" data-field="phone">
                                <?php echo $db_phone ? htmlspecialchars($db_phone) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="phone" placeholder="e.g. +63 912 345 6789" disabled
                                style="display:none;">
                        </div>
                        <div class="info-row">
                            <span class="info-lbl">Landline</span>
                            <span class="info-val <?php echo $db_landline ? '' : 'empty'; ?>" data-field="landline">
                                <?php echo $db_landline ? htmlspecialchars($db_landline) : '— Not provided'; ?>
                            </span>
                            <input class="info-input-f" data-input="landline" placeholder="e.g. (02) 1234-5678" disabled
                                style="display:none;">
                        </div>
                    </div>
                </div><!-- /acc-contact -->

            </div>
        </div>
    </div><!-- /accountOverlay -->

    <!-- ================================================================
     OVERLAY: SETTINGS
================================================================ -->
    <div class="overlay-page" id="settingsOverlay">

        <!-- Shell -->
        <div class="sov-shell">

            <!-- -- Page heading + view switch ------------------------ -->
            <div class="sov-pagehead sx-head">
                <div class="sx-head-text">
                    <h1 class="sov-pagehead-title">Settings</h1>
                    <p class="sov-pagehead-sub">Manage your profile, security, appearance and notifications in one place.</p>
                </div>
                <!-- Two views only: the unified Settings screen, and Help &amp; Support kept on its own.
                     (Same .sov-nav-item / data-sov-tab hooks the existing tab script already handles.) -->
                <nav class="acct-tabs sx-switch" id="sovSidenav" aria-label="Settings views">
                    <button type="button" class="sov-nav-item active" data-sov-tab="sov-tab-settings">
                        <span class="material-symbols-outlined">tune</span>
                        Settings
                    </button>
                    <button type="button" class="sov-nav-item" data-sov-tab="sov-tab-help">
                        <span class="material-symbols-outlined">help_outline</span>
                        Help &amp; Support
                    </button>
                </nav>
            </div>

            <!-- -- Body ------------------------------------------- -->
            <div class="sov-body">

                <?php
                $acct_meta_parts = array_filter([$db_department]);
                $acct_meta = $acct_meta_parts ? implode(' &middot; ', array_map('htmlspecialchars', $acct_meta_parts)) : 'PUPSync Faculty';
                ?>

                <!-- Identity banner -->
                <div class="acct-banner">
                    <div class="acct-banner-left">
                        <div class="acct-banner-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="acct-banner-text">
                            <h2 class="acct-banner-name"><?php echo htmlspecialchars($fullname); ?></h2>
                            <p class="acct-banner-meta"><?php echo $acct_meta; ?> &middot; ID: <span data-fid-display><?php echo htmlspecialchars($fid_label); ?></span></p>
                            <span class="acct-banner-badge">
                                <span class="material-symbols-outlined">check_circle</span>
                                Active Faculty Account
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Views -->
                <div class="sov-content">

                    <!-- VIEW: Settings -- Account, Appearance and Notifications & Data on ONE screen -->
                    <div class="sov-tab-panel active" id="sov-tab-settings">

                        <section class="sx-section" aria-labelledby="sxAccountTitle">
                            <header class="sx-section-head">
                                <span class="sx-section-icon material-symbols-outlined">person</span>
                                <div>
                                    <h2 class="sx-section-title" id="sxAccountTitle">Account</h2>
                                    <p class="sx-section-sub">Your profile details and sign-in security.</p>
                                </div>
                            </header>
                            <div class="sx-grid sx-grid--lead">

                                <!-- Personal Information: read-only until "Edit Account" is clicked -->
                                <?php
                                $sov_email_masked = faculty_mask_email($db_email);
                                $sov_phone_view   = faculty_phone_display($db_phone);
                                ?>
                                <div class="acct-card sov-edit-card" id="sovPersonalCard" data-editing="false">
                                    <div class="acct-card-head">
                                        <span class="material-symbols-outlined">badge</span>
                                        <h3>Personal Information</h3>
                                        <button type="button" class="sov-btn-outline sov-edit-btn" id="sovEditBtn" data-action="account-edit">
                                            <span class="material-symbols-outlined" aria-hidden="true">edit</span>
                                            Edit Account
                                        </button>
                                    </div>
                                    <div class="acct-rows">
                                        <div class="acct-row">
                                            <label class="acct-row-label" for="sovFullName">Full Name</label>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static" id="sovFullNameView" data-view="fullname"><?php echo htmlspecialchars($fullname); ?></span>
                                                <input class="acct-row-input" id="sovFullName" name="fullname" type="text" maxlength="120"
                                                    autocomplete="name" value="<?php echo htmlspecialchars($fullname); ?>"
                                                    data-edit-field="fullname" disabled>
                                                <p class="acct-field-error" data-error-for="fullname" role="alert" hidden></p>
                                            </div>
                                        </div>
                                        <div class="acct-row">
                                            <span class="acct-row-label">Faculty ID</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static <?php echo $fid_unset ? 'acct-row-static-muted' : ''; ?>" id="sovFacultyId" data-fid-display><?php echo htmlspecialchars($fid_label); ?></span>
                                            </div>
                                            <?php if ($fid_unset): ?>
                                                <button type="button" class="sov-btn-outline sov-fid-add" data-action="open-faculty-id-modal">Add</button>
                                            <?php else: ?>
                                                <span class="material-symbols-outlined fid-lock" title="Your Faculty ID is permanent">lock</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="acct-row">
                                            <span class="acct-row-label">Email Address</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static <?php echo $sov_email_masked === '' ? 'acct-row-static-muted' : 'acct-row-email'; ?>" id="sovEmailMasked"
                                                    <?php echo $sov_email_masked === '' ? '' : 'aria-label="Email address, partially hidden for privacy"'; ?>><?php echo $sov_email_masked === '' ? 'Not set' : htmlspecialchars($sov_email_masked); ?></span>
                                            </div>
                                            <span class="material-symbols-outlined fid-lock" title="Your email is your sign-in ID and is managed by the administrator">lock</span>
                                        </div>
                                        <div class="acct-row">
                                            <label class="acct-row-label" for="sovDepartment">Department</label>
                                            <div class="acct-row-value">
                                                <?php if ($department_locked): ?>
                                                    <span class="acct-row-static" id="sovDepartmentView"><?php echo htmlspecialchars($db_department); ?></span>
                                                <?php else: ?>
                                                    <span class="acct-row-static acct-row-static-muted" id="sovDepartmentView" data-view="department">Not set</span>
                                                    <select class="acct-row-input" id="sovDepartment" name="department" data-edit-field="department" disabled>
                                                        <option value="">Select your department&hellip;</option>
                                                        <?php foreach (faculty_departments() as $sov_dept): ?>
                                                            <option value="<?php echo htmlspecialchars($sov_dept); ?>"><?php echo htmlspecialchars($sov_dept); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <p class="acct-field-hint" data-hint-for="department" hidden>You can choose this once &mdash; it can&rsquo;t be changed afterwards.</p>
                                                <?php endif; ?>
                                                <p class="acct-field-error" data-error-for="department" role="alert" hidden></p>
                                            </div>
                                            <?php if ($department_locked): ?>
                                                <span class="material-symbols-outlined fid-lock" title="Your department is permanent once set">lock</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="acct-row">
                                            <label class="acct-row-label" for="sovPhone">Mobile Number</label>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static <?php echo $sov_phone_view === '' ? 'acct-row-static-muted' : ''; ?>" id="sovPhoneView" data-view="phone"><?php echo $sov_phone_view === '' ? 'Not set' : htmlspecialchars($sov_phone_view); ?></span>
                                                <input class="acct-row-input" id="sovPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                                                    maxlength="20" placeholder="09XX XXX XXXX" value="<?php echo htmlspecialchars($sov_phone_view); ?>"
                                                    data-edit-field="phone" disabled>
                                                <p class="acct-field-error" data-error-for="phone" role="alert" hidden></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="acct-card-foot sov-edit-foot">
                                        <button type="button" class="sov-btn-outline" data-action="account-cancel">Cancel</button>
                                        <button type="button" class="sov-btn-primary" id="sovSaveBtn" data-action="account-save" disabled>Save Changes</button>
                                    </div>
                                </div>


                                <!-- Password & Security -->
                                <div class="acct-card">
                                    <div class="acct-card-head">
                                        <span class="material-symbols-outlined">lock</span>
                                        <h3>Password &amp; Security</h3>
                                    </div>
                                    <div class="acct-rows">
                                        <div class="acct-row">
                                            <span class="acct-row-label">Password</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                                            </div>
                                            <button class="sov-btn-outline" data-action="open-pw-modal">Change</button>
                                        </div>
                                        <div class="acct-row">
                                            <span class="acct-row-label">Backup Email</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static">
                                                    <?php echo $masked_backup ? htmlspecialchars($masked_backup) : 'Not set'; ?>
                                                </span>
                                            </div>
                                            <button class="sov-btn-outline" data-action="open-backup-email-modal">
                                                <?php echo $backup_locked ? 'Edit' : 'Add'; ?>
                                            </button>
                                        </div>
                                        <?php
                                        $sov_dev      = faculty_device_info($_SERVER['HTTP_USER_AGENT'] ?? '');
                                        $sov_dev_icon = ['phone' => 'smartphone', 'tablet' => 'tablet_mac', 'desktop' => 'computer'][$sov_dev['kind']] ?? 'devices';
                                        ?>
                                        <div class="acct-row">
                                            <span class="acct-row-label">This Device</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static acct-device">
                                                    <span class="material-symbols-outlined" aria-hidden="true"><?php echo $sov_dev_icon; ?></span>
                                                    <span><?php echo htmlspecialchars($sov_dev['label']); ?></span>
                                                </span>
                                                <span class="acct-device-note">The device you are signed in on right now</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </section>

                        <section class="sx-section" aria-labelledby="sxAppearanceTitle">
                            <header class="sx-section-head">
                                <span class="sx-section-icon material-symbols-outlined">palette</span>
                                <div>
                                    <h2 class="sx-section-title" id="sxAppearanceTitle">Appearance</h2>
                                    <p class="sx-section-sub">How PUPSync looks on your screen.</p>
                                </div>
                            </header>
                            <div class="sx-grid sx-grid--single">

                                <!-- Theme -->
                                <div class="acct-card">
                                    <div class="acct-card-head">
                                        <span class="material-symbols-outlined">palette</span>
                                        <h3>Theme</h3>
                                    </div>
                                    <div class="sov-theme-row">
                                        <div class="sov-theme-option" data-action="apply-theme" data-theme="light">
                                            <div class="sov-theme-swatch sov-swatch-light">
                                                <div class="sov-swatch-topbar"></div>
                                                <div class="sov-swatch-body">
                                                    <div class="sov-swatch-sidebar"></div>
                                                    <div class="sov-swatch-content"></div>
                                                </div>
                                            </div>
                                            <div class="sov-theme-check" id="tc-light">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <span class="sov-theme-name">Light</span>
                                        </div>
                                        <div class="sov-theme-option" data-action="apply-theme" data-theme="dark">
                                            <div class="sov-theme-swatch sov-swatch-dark">
                                                <div class="sov-swatch-topbar"></div>
                                                <div class="sov-swatch-body">
                                                    <div class="sov-swatch-sidebar"></div>
                                                    <div class="sov-swatch-content"></div>
                                                </div>
                                            </div>
                                            <div class="sov-theme-check" id="tc-dark">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <span class="sov-theme-name">Dark</span>
                                        </div>
                                        <div class="sov-theme-option" data-action="apply-theme" data-theme="high-contrast">
                                            <div class="sov-theme-swatch sov-swatch-hc">
                                                <div class="sov-swatch-topbar"></div>
                                                <div class="sov-swatch-body">
                                                    <div class="sov-swatch-sidebar"></div>
                                                    <div class="sov-swatch-content"></div>
                                                </div>
                                            </div>
                                            <div class="sov-theme-check" id="tc-hc">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <span class="sov-theme-name">High Contrast</span>
                                        </div>
                                    </div>
                                    <!-- Hidden legacy elements kept for JS compatibility -->
                                    <select id="themeSelectUnified" style="display:none;">
                                        <option value="light">Light</option>
                                        <option value="dark">Dark</option>
                                        <option value="high-contrast">High Contrast</option>
                                    </select>
                                    <div style="display:none;">
                                        <div id="tp-light"></div>
                                        <div id="tp-dark"></div>
                                        <div id="tp-hc"></div>
                                    </div>
                                    <p class="sov-desc-small">Current theme: <strong id="currentThemeLabel">Light</strong></p>
                                </div>

                            </div>
                        </section>

                        <section class="sx-section" aria-labelledby="sxNotifTitle">
                            <header class="sx-section-head">
                                <span class="sx-section-icon material-symbols-outlined">notifications</span>
                                <div>
                                    <h2 class="sx-section-title" id="sxNotifTitle">Notifications &amp; Data</h2>
                                    <p class="sx-section-sub">Choose what you hear about, and manage your data.</p>
                                </div>
                            </header>
                            <div class="sx-grid ">

                                <!-- Notification Preferences -->
                                <div class="acct-card">
                                    <div class="acct-card-head">
                                        <span class="material-symbols-outlined">notifications</span>
                                        <h3>Notification Preferences</h3>
                                    </div>
                                    <div class="acct-rows">
                                        <div class="acct-row">
                                            <span class="acct-row-label">Email Alerts</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static acct-row-static-muted">Overdue reminders via email</span>
                                            </div>
                                            <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                                        </div>
                                        <div class="acct-row">
                                            <span class="acct-row-label">Reservation Reminders</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static acct-row-static-muted">24 hours before a booking</span>
                                            </div>
                                            <label class="toggle-sw"><input type="checkbox" checked><span class="toggle-track"></span></label>
                                        </div>
                                        <div class="acct-row">
                                            <span class="acct-row-label">Account Activity</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static acct-row-static-muted">Security &amp; login notifications</span>
                                            </div>
                                            <label class="toggle-sw"><input type="checkbox"><span class="toggle-track"></span></label>
                                        </div>
                                    </div>
                                </div>


                                <!-- Data Management -->
                                <div class="acct-card">
                                    <div class="acct-card-head">
                                        <span class="material-symbols-outlined">database</span>
                                        <h3>Data Management</h3>
                                    </div>
                                    <div class="acct-rows">
                                        <div class="acct-row">
                                            <span class="acct-row-label">Export Data</span>
                                            <div class="acct-row-value">
                                                <span class="acct-row-static acct-row-static-muted">Your account details and past activity (completed requests &amp; bookings) as a spreadsheet file</span>
                                            </div>
                                            <button type="button" class="sov-btn-outline" id="sovExportBtn" data-action="export-my-data">Export</button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </section>
                    </div><!-- /sov-tab-settings -->

                    <div class="sov-tab-panel" id="sov-tab-help">
                        <header class="sx-section-head">
                            <span class="sx-section-icon material-symbols-outlined">support_agent</span>
                            <div>
                                <h2 class="sx-section-title">Help &amp; Support</h2>
                                <p class="sx-section-sub">Answers to common questions, and how to reach the administrator.</p>
                            </div>
                        </header>
                        <div class="acct-card acct-card-full">
                            <div class="acct-card-head">
                                <span class="material-symbols-outlined">help_outline</span>
                                <h3>Frequently Asked Questions</h3>
                            </div>

                            <div class="sov-help-list">

                                <details class="help-item">
                                    <summary class="help-item-q">
                                        <span class="material-symbols-outlined">help_outline</span>
                                        How do I borrow equipment?
                                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                    </summary>
                                    <div class="help-item-a">
                                        Go to the <strong>Equipment</strong> tab on the sidebar, browse the catalog, and click
                                        <em>Borrow</em> on any available item. Fill in the borrow date, return date, room, and
                                        instructor, then submit. Your request will be reviewed by the admin.
                                    </div>
                                </details>

                                <details class="help-item">
                                    <summary class="help-item-q">
                                        <span class="material-symbols-outlined">help_outline</span>
                                        How do I return a borrowed item?
                                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                    </summary>
                                    <div class="help-item-a">
                                        Physically return the borrowed item to the Admin Office. The administrator will then confirm and
                                        mark your item as returned in the system. You can track the status update in <strong>Equipment
                                            &mdash; My Requests</strong> or the <strong>My Activity</strong> tab.
                                    </div>
                                </details>

                                <details class="help-item">
                                    <summary class="help-item-q">
                                        <span class="material-symbols-outlined">help_outline</span>
                                        Why is my request showing as Overdue?
                                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                    </summary>
                                    <div class="help-item-a">
                                        Your item's return date has passed without it being marked as returned. Please return the item
                                        to the Admin Office immediately. Contact the system administrator if you believe this is an
                                        error.
                                    </div>
                                </details>

                                <details class="help-item">
                                    <summary class="help-item-q">
                                        <span class="material-symbols-outlined">help_outline</span>
                                        How do I reserve a facility or room?
                                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                    </summary>
                                    <div class="help-item-a">
                                        Go to the <strong>Facilities</strong> tab on the sidebar. Browse available rooms, check their
                                        availability, and submit a reservation request. Approvals are handled by the facilities
                                        coordinator.
                                    </div>
                                </details>

                                <details class="help-item">
                                    <summary class="help-item-q">
                                        <span class="material-symbols-outlined">help_outline</span>
                                        How do I update my profile or change my password?
                                        <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                    </summary>
                                    <div class="help-item-a">
                                        Open <strong>Settings</strong> from the sidebar. Under <strong>Account</strong> you can update your
                                        name, email, department and mobile number, and change your password securely.
                                    </div>
                                </details>

                            </div>

                            <div class="help-contact-card">
                                <span class="material-symbols-outlined help-contact-icon">support_agent</span>
                                <h4>Still need help?</h4>
                                <p>Contact the PUPSync system administrator for technical issues or escalations.</p>
                                <a href="mailto:admin@pupsync.edu.ph" class="sov-btn-primary help-contact-btn">
                                    <span class="material-symbols-outlined">mail</span>
                                    Email Administrator
                                </a>
                            </div>
                        </div>
                    </div><!-- /sov-tab-help -->

                </div><!-- /sov-content -->
            </div><!-- /sov-body -->
        </div><!-- /sov-shell -->
    </div><!-- /settingsOverlay -->

    <!-- ================================================================
     MODAL: NOTIFICATIONS  (faculty)
     Same structural pattern as the admin notification modal — opened
     from the account flyout in the sidebar instead of living on its own page.
     Content is scoped to what a faculty member actually gets — live from
     the DB: borrow-request updates, overdue/return reminders, room
     reservations, waitlist, reported room issues and account activity.
================================================================ -->
    <div class="modal-backdrop fnotif-backdrop" id="notifModal" style="display:none;" role="dialog" aria-modal="true"
        aria-labelledby="notifModalTitle">
        <div class="fnotif-box">

            <!-- Head: icon + title + close -->
            <div class="fnotif-head">
                <div class="fnotif-head-icon">
                    <span class="material-symbols-outlined">notifications</span>
                </div>
                <div class="fnotif-head-text">
                    <h3 class="fnotif-head-title" id="notifModalTitle">Notifications</h3>
                    <p class="fnotif-count-text">
                        You have <strong id="unreadCount"><?php echo (int)$notif_count; ?> unread</strong>
                        notification<span id="unreadPlural"><?php echo $notif_count !== 1 ? 's' : ''; ?></span>.
                    </p>
                </div>
                <button class="modal-close-btn" data-action="close-notif-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Filter pills (counts are filled in live by faculty-dashboard.js) -->
            <div class="fnotif-tabs">
                <button class="notif-tab active" data-notif-filter="all">
                    All <span class="fnotif-pill-count" data-pill="all" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="unread">
                    Unread <span class="fnotif-pill-count" data-pill="unread" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="overdue">
                    Overdue <span class="fnotif-pill-count" data-pill="overdue" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="borrow">
                    Borrow <span class="fnotif-pill-count" data-pill="borrow" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="room">
                    Rooms <span class="fnotif-pill-count" data-pill="room" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="system">
                    System <span class="fnotif-pill-count" data-pill="system" hidden>0</span>
                </button>
            </div>

            <!-- Scrollable list — cards are rendered by faculty-dashboard.js -->
            <div class="fnotif-body">
                <div class="notif-card-list" id="notifList"></div>

                <div class="fnotif-empty" id="notifEmptyState" style="display:none;">
                    <span class="material-symbols-outlined">notifications_off</span>
                    <p id="notifEmptyText">Nothing here right now.</p>
                </div>
            </div><!-- /fnotif-body -->

            <!-- Pagination — client-side only for now; keeps the list from
                 rendering everything at once as real notifications grow. -->
            <div class="fnotif-pagination" id="notifPagination" style="display:none;">
                <div class="fnotif-pg-info" id="notifPageInfo"></div>
                <div class="fnotif-pg-controls">
                    <button class="fnotif-pg-btn" id="notifPrevBtn" data-action="notif-page-prev"
                        aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <div class="fnotif-pg-numbers" id="notifPageNumbers"></div>
                    <button class="fnotif-pg-btn" id="notifNextBtn" data-action="notif-page-next"
                        aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
            <!-- Footer — Mark all as read · Select · Delete all (Delete all only shows in select mode) -->
            <div class="fnotif-footer" id="notifFooter">

                <!-- Inline confirmation for bulk deletes -->
                <div class="fnotif-confirm" id="notifConfirm" role="alertdialog" aria-live="assertive" hidden>
                    <span class="material-symbols-outlined">warning</span>
                    <span class="fnotif-confirm-text" id="notifConfirmText"></span>
                    <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-nbar="confirm-cancel">Cancel</button>
                    <button type="button" class="btn-save-acc fnotif-foot-btn fnotif-foot-solid-danger" data-nbar="confirm-ok">Delete</button>
                </div>

                <!-- Selection bar — only while selecting -->
                <div class="fnotif-selbar" id="notifSelBar" hidden>
                    <label class="fnotif-selall">
                        <input type="checkbox" id="notifSelectAll">
                        <span>Select all</span>
                    </label>
                    <span class="fnotif-sel-count" id="notifSelCount" aria-live="polite">0 selected</span>
                    <div class="fnotif-sel-actions">
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-nbar="mark-read" data-needs-sel>
                            <span class="material-symbols-outlined">drafts</span> Read
                        </button>
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-nbar="mark-unread" data-needs-sel>
                            <span class="material-symbols-outlined">mark_email_unread</span> Unread
                        </button>
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn fnotif-foot-danger" data-nbar="delete-selected" data-needs-sel>
                            <span class="material-symbols-outlined">delete</span> Delete<span class="fnotif-sel-word"> selected</span>
                        </button>
                    </div>
                </div>

                <div class="fnotif-footer-main">
                    <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-action="mark-all-read">
                        <span class="material-symbols-outlined">done_all</span> Mark all as read
                    </button>
                    <div class="fnotif-footer-right">
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn fnotif-foot-danger" id="notifDeleteAllBtn" data-nbar="delete-all" hidden>
                            <span class="material-symbols-outlined">delete_sweep</span> Delete all
                        </button>
                        <button type="button" class="btn-save-acc fnotif-foot-btn" id="notifSelectBtn" data-nbar="select" aria-pressed="false">
                            <span class="material-symbols-outlined">checklist</span> <span id="notifSelectLbl">Select</span>
                        </button>
                    </div>
                </div>
            </div><!-- /fnotif-footer -->
        </div><!-- /fnotif-box -->
    </div><!-- /notifModal -->



    <!-- ================================================================
     MODALS
================================================================ -->

    <!-- ── Borrow Request Modal ───────────────────────────────────────── -->
    <?php if (!$is_dual_mode): ?>
        <!-- SINGLE-MODE: existing form unchanged (Requirement 4.1, 11.4) -->
        <div class="modal-backdrop" id="borrowModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
            <div class="modal-box borrow-modal-box">
                <div class="modal-header bm-head">
                    <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">inventory_2</span></span>
                    <div class="bm-heading">
                        <h3 id="borrowModalTitle">Borrow Request</h3>
                        <p class="bm-sub">Tell us where and when you need the equipment.</p>
                    </div>
                    <button class="modal-close-btn" data-action="close-borrow-modal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="bm-item" id="selectedItemBanner">
                        <span class="bm-item-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                        <div class="bm-item-text">
                            <span class="bm-item-cap">Selected equipment</span>
                            <span class="bm-item-name" id="selectedItemLabel">No item selected</span>
                        </div>
                    </div>
                    <form id="borrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="equipment_name" id="selectedItem">
                        <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                        <?= BookingSchedule::renderSchedule('bm1', false) ?>
                        <?= BookingSchedule::renderRoomPicker('borrow_room') ?>
                        <?php if ($is_org_adviser): ?>
                            <div class="form-group">
                                <span class="form-label">Request Letter <span class="bm-opt">Optional</span></span>
                                <label class="bm-drop" for="request_document">
                                    <span class="material-symbols-outlined bm-drop-icon" aria-hidden="true">upload_file</span>
                                    <span class="bm-drop-text">
                                        <strong data-bm-drop-title>Choose a file or drag it here</strong>
                                        <small>PDF, JPG, PNG or WEBP &middot; max 5 MB</small>
                                    </span>
                                    <input type="file" id="request_document" name="request_document"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp">
                                </label>
                                <p class="bm-hint">A signed letter may help prioritise your request.</p>
                            </div>
                        <?php endif; ?>
                        <div class="bm-actions">
                            <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                            <button type="submit" class="btn-save-acc">
                                <span class="material-symbols-outlined">send</span> Submit Borrow Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div><!-- /borrowModal -->
    <?php else: ?>
        <!-- DUAL-MODE: two-tab layout (Requirement 4.2, 4.3, 4.4) -->
        <style nonce="<?= $csp_nonce ?>">
            /* Panel switching for the dual-mode tabs. Visual styling of the tabs,
               checklist and form lives in faculty-dashboard.css (Borrow Request modal). */
            .borrow-subtab-panel {
                display: none;
            }

            .borrow-subtab-panel.active {
                display: block;
            }
        </style>
        <div class="modal-backdrop" id="borrowModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
            <div class="modal-box borrow-modal-box">
                <div class="modal-header bm-head">
                    <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">inventory_2</span></span>
                    <div class="bm-heading">
                        <h3 id="borrowModalTitle">Borrow Request</h3>
                        <p class="bm-sub">Tell us where and when you need the equipment.</p>
                    </div>
                    <button class="modal-close-btn" data-action="close-borrow-modal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Sub-tab controls -->
                    <div class="borrow-subtab-bar" role="tablist" aria-label="Borrowing mode">
                        <button class="borrow-subtab-btn active" id="subtab-personal-btn" role="tab"
                            aria-selected="true" aria-controls="subtab-personal" type="button"
                            data-action="borrow-subtab" data-tab="personal">
                            <span class="material-symbols-outlined" style="font-size:16px;">person</span>
                            Personal
                        </button>
                        <button class="borrow-subtab-btn" id="subtab-adviser-btn" role="tab"
                            aria-selected="false" aria-controls="subtab-adviser" type="button"
                            data-action="borrow-subtab" data-tab="adviser">
                            <span class="material-symbols-outlined" style="font-size:16px;">groups</span>
                            Adviser
                        </button>
                    </div>

                    <!-- ── Personal Tab ──────────────────────────────────── -->
                    <div class="borrow-subtab-panel active" id="subtab-personal" role="tabpanel" aria-labelledby="subtab-personal-btn">
                        <div class="bm-item" id="selectedItemBanner">
                            <span class="bm-item-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                            <div class="bm-item-text">
                                <span class="bm-item-cap">Selected equipment</span>
                                <span class="bm-item-name" id="selectedItemLabel">No item selected</span>
                            </div>
                        </div>
                        <form id="borrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="equipment_name" id="selectedItem">
                            <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                            <input type="hidden" name="submitted_as" value="personal">
                            <?= BookingSchedule::renderSchedule('bm2', false) ?>
                            <?= BookingSchedule::renderRoomPicker('bm2Room') ?>
                            <!-- No document upload in personal tab for dual-mode accounts (Requirement 4.3) -->
                            <div class="bm-actions">
                                <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                                <button type="submit" class="btn-save-acc">
                                    <span class="material-symbols-outlined">send</span> Submit Borrow Request
                                </button>
                            </div>
                        </form>
                    </div><!-- /subtab-personal -->

                    <!-- ── Adviser Tab ───────────────────────────────────── -->
                    <div class="borrow-subtab-panel" id="subtab-adviser" role="tabpanel" aria-labelledby="subtab-adviser-btn">
                        <form id="adviserBorrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="submitted_as" value="adviser">

                            <!-- Multi-item checklist (Requirement 4.4, 5.1) -->
                            <div class="form-group">
                                <span class="form-label">Select Equipment Items</span>
                                <p id="advChecklistValidation" class="adv-validation-msg" role="alert">
                                    Please select at least one item before submitting.
                                </p>
                                <div class="adv-checklist-wrap" role="group" aria-label="Available equipment">
                                    <?php if (empty($avail_items)): ?>
                                        <p style="font-size:0.85rem;color:var(--color-secondary);margin:0;padding:8px;">No equipment has been set up yet.</p>
                                        <?php else:
                                        $current_category = null;
                                        foreach ($avail_items as $av_item):
                                            if ($av_item['category'] !== $current_category):
                                                $current_category = $av_item['category'];
                                        ?>
                                                <div class="adv-checklist-category"><?= htmlspecialchars($current_category) ?></div>
                                            <?php endif;
                                            $av_meta = $bk_items[$av_item['item_name']] ?? ['shelf' => (int)$av_item['quantity'], 'total' => (int)$av_item['quantity']];
                                            ?>
                                            <label class="adv-checklist-item" data-bm-item="<?= htmlspecialchars($av_item['item_name'], ENT_QUOTES) ?>"
                                                data-shelf="<?= (int)$av_meta['shelf'] ?>" data-total="<?= (int)$av_meta['total'] ?>">
                                                <input type="checkbox"
                                                    name="items[]"
                                                    value="<?= htmlspecialchars($av_item['item_name']) ?>"
                                                    data-category="<?= htmlspecialchars($av_item['category']) ?>">
                                                <span class="adv-checklist-name"><?= htmlspecialchars($av_item['item_name']) ?></span>
                                                <span class="adv-checklist-qty" data-bm-stock>
                                                    <?= (int)$av_meta['shelf'] > 0 ? '(' . (int)$av_meta['shelf'] . ' available)' : '(out now)' ?>
                                                </span>
                                                <span class="bm-mini-stepper" data-bm-item-qty hidden>
                                                    <button type="button" data-bm-iq-dec aria-label="Decrease quantity"><span class="material-symbols-outlined">remove</span></button>
                                                    <input type="number" name="qty[<?= htmlspecialchars($av_item['item_name'], ENT_QUOTES) ?>]" value="1" min="1" max="<?= max(1, (int)$av_meta['total']) ?>" inputmode="numeric" disabled aria-label="Quantity for <?= htmlspecialchars($av_item['item_name'], ENT_QUOTES) ?>">
                                                    <button type="button" data-bm-iq-inc aria-label="Increase quantity"><span class="material-symbols-outlined">add</span></button>
                                                </span>
                                                <span class="bm-item-status" data-bm-item-status></span>
                                            </label>
                                    <?php endforeach;
                                    endif; ?>
                                </div>
                            </div>

                            <!-- Shared fields -->
                            <?= BookingSchedule::renderSchedule('bm3', true) ?>
                            <?= BookingSchedule::renderRoomPicker('adv_room') ?>

                            <!-- Document upload — optional -->
                            <div class="form-group">
                                <span class="form-label">Request Letter <span class="bm-opt">Optional</span></span>
                                <label class="bm-drop" for="adv_request_document">
                                    <span class="material-symbols-outlined bm-drop-icon" aria-hidden="true">upload_file</span>
                                    <span class="bm-drop-text">
                                        <strong data-bm-drop-title>Choose a file or drag it here</strong>
                                        <small>PDF, JPG, PNG or WEBP &middot; max 5 MB</small>
                                    </span>
                                    <input type="file" id="adv_request_document" name="request_document"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp">
                                </label>
                                <p class="bm-hint">A signed letter may help prioritise your request.</p>
                            </div>

                            <div class="bm-actions">
                                <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                                <button type="submit" class="btn-save-acc">
                                    <span class="material-symbols-outlined">send</span> Submit Adviser Request
                                </button>
                            </div>
                        </form>
                    </div><!-- /subtab-adviser -->

                </div>
            </div>
        </div><!-- /borrowModal -->

        <script nonce="<?= $csp_nonce ?>">
            (function() {
                'use strict';

                /* ── Sub-tab switching ──────────────────────────────────────── */
                var btnPersonal = document.getElementById('subtab-personal-btn');
                var btnAdviser = document.getElementById('subtab-adviser-btn');
                var panelPersonal = document.getElementById('subtab-personal');
                var panelAdviser = document.getElementById('subtab-adviser');

                function activateTab(tab) {
                    if (tab === 'personal') {
                        btnPersonal.classList.add('active');
                        btnPersonal.setAttribute('aria-selected', 'true');
                        btnAdviser.classList.remove('active');
                        btnAdviser.setAttribute('aria-selected', 'false');
                        panelPersonal.classList.add('active');
                        panelAdviser.classList.remove('active');
                    } else {
                        btnAdviser.classList.add('active');
                        btnAdviser.setAttribute('aria-selected', 'true');
                        btnPersonal.classList.remove('active');
                        btnPersonal.setAttribute('aria-selected', 'false');
                        panelAdviser.classList.add('active');
                        panelPersonal.classList.remove('active');
                    }
                }

                // NOTE: click switching is handled via data-action="borrow-subtab" in faculty-dashboard.js
                // activateTab() below is used only by the MutationObserver reset on modal close.

                /* ── Adviser form: client-side "at least one item" guard ────── */
                var advForm = document.getElementById('adviserBorrowForm');
                var advValidationMsg = document.getElementById('advChecklistValidation');

                if (advForm) {
                    advForm.addEventListener('submit', function(e) {
                        var checked = advForm.querySelectorAll('input[type="checkbox"][name="items[]"]:checked');
                        if (checked.length === 0) {
                            e.preventDefault();
                            if (advValidationMsg) {
                                advValidationMsg.style.display = 'block';
                            }
                        } else {
                            if (advValidationMsg) {
                                advValidationMsg.style.display = 'none';
                            }
                        }
                    });
                }

                /* ── Reset adviser form fields on modal close / re-open ─────── */
                function resetAdviserForm() {
                    if (!advForm) return;
                    // Uncheck all checkboxes
                    advForm.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                    });
                    // Clear text/date/file inputs
                    var roomInp = document.getElementById('adv_room');
                    var borrowInp = document.getElementById('adv_borrow_date');
                    var returnInp = document.getElementById('adv_return_date');
                    var fileInp = document.getElementById('adv_request_document');
                    if (roomInp) roomInp.value = '';
                    if (borrowInp) borrowInp.value = '';
                    if (returnInp) returnInp.value = '';
                    if (fileInp) fileInp.value = '';
                    if (window.syncBorrowDrops) window.syncBorrowDrops();
                    if (window.BorrowSchedule) window.BorrowSchedule.resetAll();
                    if (advValidationMsg) advValidationMsg.style.display = 'none';
                }

                /* Hook into the existing closeBorrowModal / openBorrowForm cycle.
                   The modal close button and backdrop click already call
                   closeBorrowModal() in faculty-dashboard.js which sets
                   display:none on #borrowModal — we just need to reset
                   state when the modal is hidden. We observe the modal
                   element for style changes. */
                var borrowModal = document.getElementById('borrowModal');
                if (borrowModal && window.MutationObserver) {
                    var _modalObserver = new MutationObserver(function(mutations) {
                        mutations.forEach(function(m) {
                            if (m.attributeName === 'style') {
                                var hidden = borrowModal.style.display === 'none' || borrowModal.style.display === '';
                                if (hidden) {
                                    resetAdviserForm();
                                    // Reset to Personal tab on close
                                    activateTab('personal');
                                }
                            }
                        });
                    });
                    _modalObserver.observe(borrowModal, {
                        attributes: true
                    });
                }

            }());
        </script>
    <?php endif; ?><!-- /dual-mode borrowModal -->

    <!-- ── Choose a Room Modal ─────────────────────────────────────────────
         Opened from the Room field of the Borrow Request modal. Shows only rooms the
         admin left Available; rooms another faculty has reserved for the chosen date and
         time stay visible but cannot be picked. The content is built by RoomPicker in
         faculty-dashboard.js. -->
    <div class="modal-backdrop rp-backdrop" id="roomPickModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="roomPickTitle">
        <div class="modal-box rp-box">
            <div class="modal-header bm-head">
                <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">meeting_room</span></span>
                <div class="bm-heading">
                    <h3 id="roomPickTitle">Choose a room</h3>
                    <p class="bm-sub" id="roomPickSub">Where will the equipment be used?</p>
                </div>
                <button type="button" class="modal-close-btn" data-rp-close aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="rp-tools">
                <label class="rp-search">
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <input type="search" id="roomPickSearch" placeholder="Search rooms" autocomplete="off" aria-label="Search rooms">
                </label>
                <div class="rp-tabs" id="roomPickTabs" role="tablist" aria-label="Buildings" hidden></div>
            </div>
            <div class="rp-scroll" id="roomPickBody" aria-live="polite"></div>
            <div class="rp-foot">
                <div class="rp-legend" aria-hidden="true">
                    <span class="rp-key"><i class="rp-dot rp-dot--free"></i>Free</span>
                    <span class="rp-key"><i class="rp-dot rp-dot--taken"></i>Reserved</span>
                </div>
                <span class="rp-count" id="roomPickCount"></span>
                <button type="button" class="btn-cancel-acc" data-rp-close>Cancel</button>
            </div>
        </div>
    </div><!-- /roomPickModal -->

    <!-- Confirmation Modal -->
    <div class="modal-backdrop" id="confirmationModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">task_alt</span>Confirm Changes
                </h3>
                <button class="modal-close-btn" data-action="close-confirmation-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Please review the
                    changes you're about to make:</p>
                <div class="changes-summary" id="changesSummary"></div>
                <div class="warning-box">
                    <span class="material-symbols-outlined" style="color:var(--color-on-warning-container);flex-shrink:0;">warning</span>
                    <div><strong style="color:var(--color-on-warning-container);display:block;margin-bottom:4px;">Important Notice</strong>
                        <p style="color:var(--color-on-warning-container);margin:0;font-size:0.875rem;" id="warningMessage">Some changes cannot be
                            reversed once saved.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-confirmation-modal">Cancel</button>
                <button class="btn-save-acc" id="confirmChangesBtn" data-action="confirm-changes">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Confirm
                    &amp; Save
                </button>
            </div>
        </div>
    </div>

    <!-- Email Verify Modal -->
    <div class="modal-backdrop" id="emailVerifyModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">mail</span>Verify Your Email</h3>
                <button class="modal-close-btn" data-action="close-email-verify-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">For security,
                    verify your email address before changing your password.</p>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="verifyEmailInput" class="form-input"
                        placeholder="Enter your registered email" autocomplete="email">
                </div>
                <p class="modal-error" id="emailVerifyError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-email-verify-modal">Cancel</button>
                <button class="btn-save-acc" id="emailVerifyBtn" data-action="submit-email-verify">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Verify
                    &amp; Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Backup Email Modal -->
    <div class="modal-backdrop" id="backupEmailModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">alternate_email</span>Backup
                    Email</h3>
                <button class="modal-close-btn" data-action="close-backup-email-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Add a backup email
                    for account recovery and important notifications.</p>
                <div class="form-group">
                    <label class="form-label">Backup Email Address</label>
                    <input type="email" id="backupEmailInput" class="form-input" placeholder="Enter backup email"
                        autocomplete="email" value="<?php echo htmlspecialchars($db_backup_email); ?>">
                </div>
                <p class="modal-error" id="backupEmailError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-backup-email-modal">Cancel</button>
                <button class="btn-save-acc" id="backupEmailSaveBtn" data-action="save-backup-email">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Save
                    Backup Email
                </button>
            </div>
        </div>
    </div>

    <!-- Faculty ID Modal: set once, permanent -->
    <div class="modal-backdrop" id="facultyIdModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="facultyIdTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="facultyIdTitle"><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">badge</span>Faculty ID</h3>
                <button class="modal-close-btn" data-action="close-faculty-id-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>

            <!-- Step 1: enter -->
            <div id="facultyIdStepEnter">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="facultyIdInput">Your Faculty ID</label>
                        <input type="text" id="facultyIdInput" class="form-input" placeholder="e.g. 2023-00123-BN-0"
                            maxlength="30" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    </div>
                    <p class="modal-error" id="facultyIdError" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel-acc" data-action="close-faculty-id-modal">Cancel</button>
                    <button class="btn-save-acc" id="facultyIdContinueBtn" data-action="faculty-id-continue">Continue</button>
                </div>
            </div>

            <!-- Step 2: confirm (permanent) -->
            <div id="facultyIdStepConfirm" style="display:none;">
                <div class="modal-body">
                    <div class="fid-confirm-id" id="facultyIdConfirmValue"></div>
                    <div class="warning-box">
                        <span class="material-symbols-outlined" style="color:#856404;flex-shrink:0;">lock</span>
                        <div><strong style="color:#856404;display:block;margin-bottom:2px;">This is permanent</strong>
                            <p style="color:#856404;margin:0;font-size:0.875rem;">You can&rsquo;t change your Faculty ID after saving it, and neither can an admin.</p>
                        </div>
                    </div>
                    <p class="modal-error" id="facultyIdConfirmError" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel-acc" data-action="faculty-id-back">Go Back</button>
                    <button class="btn-save-acc" id="facultyIdConfirmBtn" data-action="faculty-id-confirm">
                        <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">lock</span>Confirm &amp; Lock ID
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal-backdrop" id="pwModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">lock</span>Change Password</h3>
                <button class="modal-close-btn" data-action="close-pw-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwCurrent" class="form-input"
                            placeholder="Enter your current password" autocomplete="current-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwCurrent"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwNew" class="form-input" placeholder="At least 6 characters"
                            autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwNew"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                    <div class="pw-strength-bar" id="pwStrengthBar" style="display:none;">
                        <div class="pw-strength-fill" id="pwStrengthFill"></div>
                    </div>
                    <span class="pw-strength-label" id="pwStrengthLabel"></span>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwConfirm" class="form-input" placeholder="Repeat your new password"
                            autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwConfirm"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <p class="modal-error" id="pwModalError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-pw-modal">Cancel</button>
                <button class="btn-save-acc" id="pwSubmitBtn" data-action="submit-pw-change">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Update
                    Password
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Config for JS -->
    <script nonce="<?php echo $csp_nonce; ?>">
        window.USER_PROFILE_LOCKS = {
            dob: <?php echo $dob_locked ? 'true' : 'false'; ?>,
            gender: <?php echo $gender_locked ? 'true' : 'false'; ?>,
            nationality: <?php echo $nationality_locked ? 'true' : 'false'; ?>
        };
    </script>

    <!-- Loading Overlay -->
    <div id="loading-overlay"
        style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;">
        <div
            style="background:var(--color-surface);border-radius:16px;padding:2rem 2.5rem;display:flex;flex-direction:column;align-items:center;gap:12px;">
            <div class="spinner"></div>
            <p style="font-weight:600;color:var(--color-on-surface);font-size:0.9rem;">Processing your request…</p>
        </div>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>

    <script nonce="<?php echo $csp_nonce; ?>">
        window.REQUESTS_DATA = <?php echo $requests_json; ?>;
        window.BOOKING_META = <?php echo $bk_meta_json; ?>;
        window.FNOTIF_DATA = <?php echo $notif_json; ?>;
        window.USER_SLUG = '<?php echo $user_slug; ?>';
        window.OVERDUE_COUNT = <?php echo (int)$stat_overdue; ?>;
        window.SERVER_BASE_URL = '<?php
                                    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";

                                    // Get the real network IP of the laptop (not loopback 127.0.0.1)
                                    $server_addr = $_SERVER["SERVER_ADDR"] ?? "127.0.0.1";
                                    if ($server_addr === "127.0.0.1" || $server_addr === "::1") {
                                        $server_addr = gethostbyname(gethostname());
                                    }

                                    $port    = $_SERVER["SERVER_PORT"] ?? "80";
                                    $portStr = ($port == "80" || $port == "443") ? "" : ":" . $port;
                                    $path    = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
                                    echo $scheme . "://" . $server_addr . $portStr . $path . "/";
                                    ?>';
    </script>
    <!-- Mobile Nav Backdrop -->
    <div class="nav-backdrop" id="navBackdrop"></div>

    <script src="assets/js/logout-modal.js?v=<?php echo @filemtime('assets/js/logout-modal.js'); ?>"></script>
    <script src="equipment-booking/assets/js/faculty-dashboard.js?v=<?php echo @filemtime(__DIR__ . '/equipment-booking/assets/js/faculty-dashboard.js'); ?>"></script>
    <script src="room-reservation/assets/js/fcty-facilities.js"></script>
</body>

</html>