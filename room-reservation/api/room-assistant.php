<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/**
 * room-reservation/api/room-assistant.php
 *
 * PUPSync Room Assistant — AI Q&A endpoint.
 *
 * Responsibilities (Phase 2 only):
 *   - Answer questions about room reservations and the PUPSync system
 *   - Check live room availability per campus per date
 *   - Return faculty's own reservation context
 *   - Signal reservation intent (INTENT:reserve) so JS can show the mini-form
 *
 * Phase 3 (AI-driven conversational reservation flow) has been removed.
 * Room reservations are now handled by the structured mini-form in the chat UI,
 * which submits directly to chat-reserve.php. This endpoint does Q&A only.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

// ── 1. Security headers ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/security-headers.php';
date_default_timezone_set('Asia/Manila');

// ── 2. Output buffer + JSON header ───────────────────────────────────────────
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ── 3. Unified responder ──────────────────────────────────────────────────────
function chat_respond(int $code, string $status, string $message, array $extra = []): never
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// ── 4. Method guard ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chat_respond(405, 'error', 'Method not allowed.');
}

// ── 5. Session guard ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['faculty_id'])) {
    chat_respond(401, 'error', 'Unauthorized.');
}
$faculty_id   = (string)$_SESSION['faculty_id'];
$faculty_name = (string)($_SESSION['faculty_name'] ?? 'Faculty');

// ── 6. Parse JSON body ────────────────────────────────────────────────────────
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    chat_respond(400, 'error', 'Invalid request body.');
}

// ── 7. CSRF guard ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/csrf.php';
$submitted_token = trim((string)($body['csrf_token'] ?? ''));
$session_token   = (string)($_SESSION['csrf_token'] ?? '');
if ($session_token === '' || $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)) {
    chat_respond(403, 'error', 'Invalid session token. Please refresh the page.');
}

// ── 8. Load API keys ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/env.php';
load_env();
$groq_key = trim((string)($_ENV['GROQ_API_KEY'] ?? ''));

if ($groq_key === '') {
    error_log('[PUPSync AI] GROQ_API_KEY is not set in .env');
    chat_respond(503, 'error', 'The AI assistant is temporarily unavailable.');
}

// ── 9. Validate message ───────────────────────────────────────────────────────
$raw_message = trim((string)($body['message'] ?? ''));
if (mb_strlen($raw_message) < 3) {
    chat_respond(422, 'error', 'Please type a complete question (at least 3 characters).');
}
if (mb_strlen($raw_message) > 500) {
    chat_respond(422, 'error', 'Message is too long. Please keep it under 500 characters.');
}

// ── 10. Database connection ───────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
require_once __DIR__ . '/../core/room-rules.php';
RoomRules::load($conn);   // Settings → Arbitration → Reservation rules (the assistant quotes these)
$conn->set_charset('utf8mb4');

// ── 11. Rate limit (20 messages per rolling hour) ─────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `tbl_ai_chat_log` (
        `id`         int(11)     NOT NULL AUTO_INCREMENT,
        `faculty_id` varchar(50) NOT NULL,
        `sent_at`    datetime    NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_faculty_sent` (`faculty_id`, `sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$RATE_LIMIT = 20;

$rl_stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tbl_ai_chat_log
      WHERE faculty_id = ? AND sent_at >= (NOW() - INTERVAL 1 DAY)"
);
$rl_stmt->bind_param('s', $faculty_id);
$rl_stmt->execute();
$rl_count = (int)($rl_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$rl_stmt->close();

if ($rl_count >= $RATE_LIMIT) {
    $oldest_stmt = $conn->prepare(
        "SELECT MIN(sent_at) AS oldest FROM tbl_ai_chat_log
          WHERE faculty_id = ? AND sent_at >= (NOW() - INTERVAL 1 DAY)"
    );
    $oldest_stmt->bind_param('s', $faculty_id);
    $oldest_stmt->execute();
    $oldest = $oldest_stmt->get_result()->fetch_assoc()['oldest'] ?? null;
    $oldest_stmt->close();

    $reset_time = '';
    if ($oldest) {
        $reset_dt = new DateTime($oldest, new DateTimeZone('Asia/Manila'));
        $reset_dt->modify('+1 hour');
        $reset_time = $reset_dt->format('g:i A');
    }
    chat_respond(429, 'rate_limited',
        'You have reached the 20 message limit for today.' .
        ($reset_time ? " Your limit resets at {$reset_time}." : ''),
        ['reset_time' => $reset_time, 'remaining' => 0]
    );
}

// ── System-level daily cap ────────────────────────────────────────────────────
// Groq free tier: 200,000 tokens/day shared across all faculty.
// Each message costs ~3,050 tokens (2,500 system + 300 history + 50 user + 200 reply).
// Safe ceiling: 60 messages/day total (leaves buffer before hard Groq 429).
$SYSTEM_DAILY_CAP = 60;
$sys_stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tbl_ai_chat_log WHERE sent_at >= CURDATE()"
);
$sys_stmt->execute();
$sys_total = (int)($sys_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$sys_stmt->close();

if ($sys_total >= $SYSTEM_DAILY_CAP) {
    chat_respond(429, 'rate_limited',
        'The AI assistant has reached its daily usage limit for today across all users. ' .
        'It resets at midnight. You can still browse rooms in the Facilities tab.',
        ['reset_time' => '', 'remaining' => 0]
    );
}

// Log before API call — aborted calls still count
$log_insert_id = 0;
$log_stmt = $conn->prepare(
    "INSERT INTO tbl_ai_chat_log (faculty_id, sent_at) VALUES (?, NOW())"
);
$log_stmt->bind_param('s', $faculty_id);
$log_stmt->execute();
$log_insert_id = (int)$conn->insert_id;
$log_stmt->close();

if (mt_rand(1, 20) === 1) {
    $conn->query("DELETE FROM tbl_ai_chat_log WHERE sent_at < (NOW() - INTERVAL 24 HOUR)");
}

// ── 12. Faculty context from DB ───────────────────────────────────────────────
$user_stmt = $conn->prepare(
    "SELECT role, department FROM tbl_users WHERE faculty_id = ? LIMIT 1"
);
$user_stmt->bind_param('s', $faculty_id);
$user_stmt->execute();
$user_row     = $user_stmt->get_result()->fetch_assoc() ?? [];
$user_stmt->close();
$faculty_role = (string)($user_row['role'] ?? 'Regular Faculty');
$faculty_dept = (string)($user_row['department'] ?? '');

$res_stmt = $conn->prepare(
    "SELECT rr.id, r.room_name, b.name AS building_name,
            rr.reservation_date, rr.start_time, rr.end_time, rr.purpose,
            rr.submitted_as, rr.submitted_by_name, rr.submitted_by_id
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE rr.faculty_id   = ?
        AND rr.status        IN ('Approved', 'Waiting')
        AND (rr.reservation_date > CURDATE()
             OR (rr.reservation_date = CURDATE() AND rr.end_time > CURTIME()))
      ORDER BY rr.reservation_date ASC, rr.start_time ASC
      LIMIT 10"
);
$res_stmt->bind_param('s', $faculty_id);
$res_stmt->execute();
$res_rows = $res_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$res_stmt->close();

$wl_stmt = $conn->prepare(
    "SELECT r.room_name, b.name AS building_name,
            w.reservation_date, w.start_time, w.end_time
       FROM tbl_room_waitlist w
       JOIN tbl_rooms     r ON r.room_id     = w.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE w.faculty_id = ?
        AND (w.reservation_date > CURDATE()
             OR (w.reservation_date = CURDATE() AND w.end_time > CURTIME()))
      ORDER BY w.reservation_date ASC
      LIMIT 5"
);
$wl_stmt->bind_param('s', $faculty_id);
$wl_stmt->execute();
$wl_rows = $wl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$wl_stmt->close();

// ── 12b. Faculty timetable rooms (class schedule assignments this semester) ──
require_once __DIR__ . '/../../shared/core/arbitration-config.php';
$arb            = ArbitrationConfig::all($conn);
$semester_name  = (string)($arb['semester_name']  ?? '');
$semester_start = (string)($arb['semester_start'] ?? '');
$semester_end   = (string)($arb['semester_end']   ?? '');

$semester_label = '';
if ($semester_start !== '' && $semester_end !== '') {
    $sem_names = ['first' => '1st Semester', 'second' => '2nd Semester', 'summer' => 'Summer', 'none' => 'No active semester'];
    $semester_label = ($sem_names[$semester_name] ?? ucfirst($semester_name))
        . ' (' . $semester_start . ' to ' . $semester_end . ')';
} else {
    $semester_label = 'No semester configured';
}

// Rooms where THIS faculty has timetable classes
$my_rooms_stmt = $conn->prepare(
    "SELECT DISTINCT cs.room_id, r.room_name, r.room_code, b.name AS building_name
       FROM tbl_class_schedule cs
       JOIN tbl_rooms     r ON r.room_id     = cs.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE cs.account_faculty_id = ?
        AND cs.is_tba   = 0
        AND cs.room_id IS NOT NULL"
);
$my_rooms_stmt->bind_param('s', $faculty_id);
$my_rooms_stmt->execute();
$my_timetable_rooms = $my_rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$my_rooms_stmt->close();

$ctx_my_timetable = empty($my_timetable_rooms)
    ? 'None — no classes scheduled for this faculty this semester.'
    : implode("\n", array_map(fn($r) =>
        '- ' . $r['room_name'] . ' (' . $r['room_code'] . ') in ' . $r['building_name'],
        $my_timetable_rooms));

// All rooms that have ANY faculty timetable assignment (excluding this faculty)
// Used to warn faculty when they try to reserve a room used by another faculty's timetable
$all_assigned_stmt = $conn->prepare(
    "SELECT DISTINCT cs.room_id, r.room_name, r.room_code, b.name AS building_name,
            cs.faculty_name AS assigned_faculty
       FROM tbl_class_schedule cs
       JOIN tbl_rooms     r ON r.room_id     = cs.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE cs.account_faculty_id != ?
        AND cs.is_tba   = 0
        AND cs.room_id IS NOT NULL"
);
$all_assigned_stmt->bind_param('s', $faculty_id);
$all_assigned_stmt->execute();
$all_assigned_rows = $all_assigned_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_assigned_stmt->close();

// Group by room_id, collect unique faculty names per room
$assigned_rooms_map = [];
foreach ($all_assigned_rows as $row) {
    $rid = $row['room_id'];
    if (!isset($assigned_rooms_map[$rid])) {
        $assigned_rooms_map[$rid] = [
            'room_name'      => $row['room_name'],
            'room_code'      => $row['room_code'],
            'building_name'  => $row['building_name'],
            'faculty_names'  => [],
        ];
    }
    $fname = trim($row['assigned_faculty']);
    if ($fname !== '' && !in_array($fname, $assigned_rooms_map[$rid]['faculty_names'], true)) {
        $assigned_rooms_map[$rid]['faculty_names'][] = $fname;
    }
}

$ctx_assigned_rooms = empty($assigned_rooms_map)
    ? 'None — no rooms are assigned in another faculty\'s timetable this semester.'
    : implode("\n", array_map(fn($r) =>
        '- ' . $r['room_name'] . ' (' . $r['room_code'] . ') in ' . $r['building_name']
        . ' — used by: ' . implode(', ', $r['faculty_names']),
        array_values($assigned_rooms_map)));


// ── 12c. All bookable rooms per campus (for room suggestions & name matching) ─
// Fetched per campus only when known from the query to keep the prompt size down.
// For general reservation requests, all rooms are included.
$all_rooms_stmt = $conn->prepare(
    "SELECT r.room_id, r.room_name, r.room_code, r.floor_number,
            b.name AS building_name, c.campus_name
       FROM tbl_rooms r
       JOIN tbl_buildings b ON b.building_id = r.building_id
       JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
      WHERE r.status      = 'Available'
        AND r.is_archived = 0
      ORDER BY c.campus_id ASC, b.sort_order ASC, r.floor_number ASC, r.sort_order ASC"
);
$all_rooms_stmt->execute();
$all_rooms_rows = $all_rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_rooms_stmt->close();

// Build a structured list grouped by building for the system prompt
$rooms_by_building = [];
foreach ($all_rooms_rows as $rm) {
    $key = $rm['campus_name'] . ' — ' . $rm['building_name'];
    $rooms_by_building[$key][] = $rm['room_name']
        . ' (' . $rm['room_code'] . ', Floor ' . $rm['floor_number'] . ')';
}
$ctx_all_rooms = empty($rooms_by_building) ? 'No bookable rooms found.' :
    implode("\n", array_map(
        fn($bname, $rms) => $bname . ":\n" . implode("\n", array_map(fn($r) => '  - ' . $r, $rms)),
        array_keys($rooms_by_building), array_values($rooms_by_building)
    ));

$quota_used = count($res_rows);
$quota_max  = RoomRules::maxActive();
$rule_advance_days = RoomRules::maxAdvanceDays();
$rule_cutoff       = RoomRules::cancelCutoffLabel();
$rule_open_days    = RoomRules::openDaysLabel();
$rule_hours        = RoomRules::hoursLabel();
$rule_max_hours    = RoomRules::maxHours();

$ctx_reservations = $quota_used === 0
    ? 'None — no active or upcoming reservations.'
    : implode("\n", array_map(fn($r) =>
        '- [ID:' . $r['id'] . '] ' . $r['room_name'] . ' (' . $r['building_name'] . ')'
        . ' on ' . $r['reservation_date']
        . ' from ' . substr($r['start_time'], 0, 5)
        . ' to '   . substr($r['end_time'],   0, 5)
        . ' — Purpose: ' . $r['purpose']
        . ((string)($r['submitted_as'] ?? '') === 'student'
            ? ' — Student: ' . (string)($r['submitted_by_name'] ?? '')
                . ((string)($r['submitted_by_id'] ?? '') !== '' ? ' (' . (string)$r['submitted_by_id'] . ')' : '')
            : ''),
        $res_rows));

// Build a lookup map of valid reservation IDs this faculty owns.
// Used below to validate cancel signals — the AI must never emit an ID
// that isn't in this faculty's own reservation list.
$valid_reservation_ids = array_map(fn($r) => (int)$r['id'], $res_rows);

$ctx_waitlist = empty($wl_rows)
    ? 'None.'
    : implode("\n", array_map(fn($w) =>
        '- ' . $w['room_name'] . ' (' . $w['building_name'] . ')'
        . ' on ' . $w['reservation_date']
        . ' from ' . substr($w['start_time'], 0, 5)
        . ' to '   . substr($w['end_time'],   0, 5),
        $wl_rows));

// ── 13. Availability pipeline ─────────────────────────────────────────────────
function ai_is_availability_query(string $msg): bool
{
    $keywords = [
        'what rooms', 'which rooms', 'any rooms', 'rooms available',
        'rooms free', 'rooms open', 'rooms today', 'rooms tomorrow',
        'rooms on', 'rooms this', 'rooms next',
        'free rooms', 'available rooms', 'open rooms', 'empty rooms',
        'free today', 'free tomorrow', 'free on', 'free this', 'free next',
        'available today', 'available tomorrow', 'available on',
        'check availability', 'room availability', 'see availability',
        'is there a room', 'are there rooms', 'find a room',
        'any free', 'any available', 'any open',
        'which building', 'what building',
        'rooms this week', 'rooms next week',
        'rooms monday', 'rooms tuesday', 'rooms wednesday',
        'rooms thursday', 'rooms friday', 'rooms saturday',
    ];
    $lower = mb_strtolower($msg);
    foreach ($keywords as $kw) {
        if (str_contains($lower, $kw)) return true;
    }
    return false;
}

/**
 * PHP-side reservation intent classifier.
 *
 * Returns true ONLY when the faculty is asking you (the AI) to perform
 * the booking action right now — not when they are asking for information.
 *
 * Rules:
 *   DENY (information requests) — checked first
 *     - starts with question words: how, what, why, when, where, can i, is it, do i
 *     - contains "how do", "how to", "what is", "what are", "explain", "tell me how",
 *       "steps", "process", "guide", "help me understand"
 *   ALLOW (action requests) — checked second
 *     - explicit booking verbs targeted at the AI:
 *       "reserve me", "book me", "book a room for me", "make a reservation",
 *       "get me a room", "i want to reserve", "i need to reserve", "i want to book",
 *       "i need to book", "can you reserve", "can you book", "please reserve",
 *       "please book", "help me reserve", "set up a reservation"
 */
function ai_is_reserve_intent(string $msg): bool
{
    $lower = mb_strtolower(trim($msg));

    // ── Deny list: information / how-to requests ─────────────────────────────
    $deny_starts = [
        'how do', 'how to', 'how can', 'how should', 'how would',
        'what is', 'what are', 'what\'s', 'why ', 'when can', 'when do',
        'where can', 'where do', 'can i ', 'can i?', 'is it possible',
        'is there a way', 'do i need', 'do i have to',
    ];
    foreach ($deny_starts as $pattern) {
        if (str_starts_with($lower, $pattern)) return false;
    }
    $deny_contains = [
        'explain', 'tell me how', 'show me how', 'what are the steps',
        'what is the process', 'what is the procedure', 'step by step',
        'instructions for', 'guide me', 'help me understand', 'how does',
    ];
    foreach ($deny_contains as $pattern) {
        if (str_contains($lower, $pattern)) return false;
    }

    // ── Allow list: explicit action requests targeting the AI ─────────────────
    $allow = [
        'reserve me', 'book me', 'book a room for me', 'make a reservation',
        'get me a room', 'i want to reserve', 'i need to reserve',
        'i want to book', 'i need to book', 'i would like to reserve',
        'i\'d like to reserve', 'i would like to book', 'i\'d like to book',
        'can you reserve', 'can you book', 'could you reserve', 'could you book',
        'please reserve', 'please book', 'help me reserve', 'help me book',
        'set up a reservation', 'open the reservation form', 'open the form',
        'do it for me',
    ];
    foreach ($allow as $pattern) {
        if (str_contains($lower, $pattern)) return true;
    }

    return false;
}

function ai_detect_campus(string $msg): ?string
{
    $lower = mb_strtolower($msg);
    foreach (['cite', 'building c', 'canlalay', 'pup cite'] as $kw) {
        if (str_contains($lower, $kw)) return 'cite';
    }
    foreach (['main', 'building a', 'building b', 'zapote', 'pup main'] as $kw) {
        if (str_contains($lower, $kw)) return 'main';
    }
    return null;
}

function ai_extract_date(string $msg): string
{
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);
    $lower = mb_strtolower(trim($msg));

    if (str_contains($lower, 'today'))    return $now->format('Y-m-d');
    if (str_contains($lower, 'tomorrow')) return $now->modify('+1 day')->format('Y-m-d');

    $days = ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,
             'friday'=>5,'saturday'=>6,'sunday'=>0];
    foreach ($days as $name => $dow) {
        if (!str_contains($lower, $name)) continue;
        $prefix = str_contains($lower, 'next ' . $name) ? 'next ' : '';
        try {
            $dt = new DateTimeImmutable($prefix . $name, $tz);
            if ($prefix === '' && $dt->format('Y-m-d') <= $now->format('Y-m-d')) {
                $dt = $dt->modify('+7 days');
            }
            if ($dt->format('Y-m-d') >= $now->format('Y-m-d')) return $dt->format('Y-m-d');
        } catch (\Exception $e) {}
    }

    $months = [
        'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
        'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12,
        'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'jun'=>6,'jul'=>7,
        'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
    ];
    foreach ($months as $mname => $mnum) {
        if (!str_contains($lower, $mname)) continue;
        $day = 0;
        if (preg_match('/\b' . preg_quote($mname, '/') . '\s+(\d{1,2})\b/', $lower, $m)) {
            $day = (int)$m[1];
        } elseif (preg_match('/\b(\d{1,2})\s+' . preg_quote($mname, '/') . '\b/', $lower, $m)) {
            $day = (int)$m[1];
        }
        if ($day < 1 || $day > 31) continue;
        $year = (int)$now->format('Y');
        $candidate = sprintf('%04d-%02d-%02d', $year, $mnum, $day);
        if ($candidate < $now->format('Y-m-d')) {
            $candidate = sprintf('%04d-%02d-%02d', $year + 1, $mnum, $day);
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $candidate, $tz);
        if ($dt && $dt->format('Y-m-d') === $candidate) return $candidate;
    }

    return $now->format('Y-m-d');
}

function ai_availability_summary(mysqli $conn, string $campus, string $date): string
{
    $campus_id    = ($campus === 'cite') ? 2 : 1;
    $campus_label = ($campus === 'cite') ? 'PUP CITE' : 'PUP MAIN';

    $stmt = $conn->prepare(
        "SELECT r.room_id, r.room_name, r.floor_number,
                b.name AS building_name
           FROM tbl_rooms r
           JOIN tbl_buildings b ON b.building_id = r.building_id
           JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
          WHERE c.campus_id  = ?
            AND r.status     = 'Available'
            AND r.is_archived = 0
          ORDER BY b.sort_order ASC, r.floor_number ASC, r.sort_order ASC"
    );
    $stmt->bind_param('i', $campus_id);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rooms)) return "No bookable rooms found for {$campus_label} on {$date}.";

    $room_ids     = array_column($rooms, 'room_id');
    $placeholders = implode(',', array_fill(0, count($room_ids), '?'));
    $types        = str_repeat('i', count($room_ids));

    $res_stmt  = $conn->prepare(
        "SELECT room_id,
                TIME_FORMAT(start_time,'%H:%i') AS start,
                TIME_FORMAT(end_time,  '%H:%i') AS end
           FROM tbl_room_reservations
          WHERE room_id IN ({$placeholders})
            AND reservation_date = ?
            AND status = 'Approved'
          ORDER BY room_id, start_time"
    );
    $bind_args = array_merge([$types . 's'], $room_ids, [$date]);
    $refs = [];
    foreach ($bind_args as $k => $v) { $refs[$k] = &$bind_args[$k]; }
    call_user_func_array([$res_stmt, 'bind_param'], $refs);
    $res_stmt->execute();
    $bookings_raw = $res_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $res_stmt->close();

    $bookings = [];
    foreach ($bookings_raw as $b) {
        $bookings[$b['room_id']][] = $b['start'] . '–' . $b['end'];
    }

    $by_building = [];
    foreach ($rooms as $r) {
        $bid  = $r['building_name'];
        $rid  = $r['room_id'];
        $free = !isset($bookings[$rid]);
        $by_building[$bid]['total'] = ($by_building[$bid]['total'] ?? 0) + 1;
        $by_building[$bid]['free']  = ($by_building[$bid]['free']  ?? 0) + ($free ? 1 : 0);
        if (!$free) {
            $by_building[$bid]['booked'][$r['room_name']] = $bookings[$rid];
        }
    }

    $date_label = (new DateTimeImmutable($date, new DateTimeZone('Asia/Manila')))->format('l, F j');
    $lines = ["## ROOM AVAILABILITY — {$campus_label} — {$date_label}"];
    foreach ($by_building as $bname => $data) {
        $lines[] = "\n### {$bname}";
        $lines[] = "Free rooms: {$data['free']} of {$data['total']} bookable";
        if ($data['free'] === 0) {
            $lines[] = "All rooms are booked for this date.";
        } elseif (!empty($data['booked'])) {
            $lines[] = "Rooms with bookings (avoid these times):";
            foreach ($data['booked'] as $rname => $slots) {
                $lines[] = "  - {$rname}: " . implode(', ', $slots);
            }
        }
    }
    $lines[] = "\nIf the faculty wants to reserve a specific slot, ask them for the time and emit INTENT:reserve with the available details.";
    return implode("\n", $lines);
}

$ctx_availability = '';
$ask_campus       = false;

if (ai_is_availability_query($raw_message)) {
    $campus = ai_detect_campus($raw_message);
    if ($campus === null) {
        $ask_campus = true;
    } else {
        $ctx_availability = ai_availability_summary($conn, $campus, ai_extract_date($raw_message));
    }
}

// ── 14. System prompt ─────────────────────────────────────────────────────────
$now_manila     = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$today_label    = $now_manila->format('l, F j, Y');
$today_date     = $now_manila->format('Y-m-d');
$today_day_name = $now_manila->format('l');

$avail_section = $ctx_availability !== ''
    ? $ctx_availability
    : 'Not queried — the faculty did not ask about availability.';

// $intent_gate is appended after the PROMPT heredoc below.
// Keeping it separate makes the heredoc readable and the gate auditable.

$campus_instruction = $ask_campus
    ? "\n## IMPORTANT — CAMPUS UNKNOWN\nThe faculty asked about availability but did not specify a campus. You MUST ask: \"Which campus are you at?\" and present exactly two options:\n→ PUP Main (Buildings A & B)\n→ PUP CITE (Building C)\nDo not answer until you know the campus."
    : '';

// PHP-side intent decision — overrides whatever the LLM would decide on its own.
// This is injected into the system prompt so the LLM receives a clear directive.
$php_reserve_intent = ai_is_reserve_intent($raw_message);
$intent_gate = $php_reserve_intent
    ? "\n## PHP INTENT DECISION: RESERVE\nThis message is a reservation request. You MUST include INTENT:reserve (with any detected prefill fields) at the end of your reply."
    : "\n## PHP INTENT DECISION: NO FORM\nThis message is NOT a reservation request — it is a question or information request. You MUST NOT include INTENT:reserve in your reply under any circumstances. Answer with text only.";

$system_prompt = <<<PROMPT
You are PUPSync Room Assistant, an AI assistant for the Polytechnic University of the Philippines (PUP) Biñan Campus room reservation system. You help faculty members with room reservations only.

## TODAY (server-injected — always accurate)
- Current date: {$today_label}
- Rooms can only be reserved on: {$rule_open_days}.

## SYSTEM RULES
- Operating hours: {$rule_hours}.
- Longest single reservation: {$rule_max_hours} hours.
- Maximum advance booking: {$rule_advance_days} days from today ({$today_date}).
- Maximum active reservations per faculty: {$quota_max} at the same time.
- Cancellation: only allowed more than {$rule_cutoff} before the start time.
- A request letter (PDF, JPG, PNG, max 5 MB) is recommended for reservations on a later date. It cannot be attached in this chat — tell the faculty to attach it in the Reserve form instead. Same-day reservations do not need a letter.
- Student-submitted reservations via faculty code count toward the faculty's quota and appear in the faculty's activity.

## CAMPUSES & BUILDINGS
- **PUP MAIN** (Zapote, Biñan): Building A (Old, 5 floors), Building B (New, 4 floors)
- **PUP CITE** (Canlalay, Biñan): Building C (4 floors — computer labs, engineering labs, AVR)

## HOW TO RESERVE A ROOM (manual flow)
Go to the Facilities tab → choose a building → browse by floor → click a room card → Reserve.

## INTENT DETECTION — CRITICAL RULES

### Availability queries (NEVER trigger INTENT:reserve for these)
When a faculty asks about room availability, free rooms, or which rooms are open — they are just ASKING.
They have NOT asked to reserve anything yet.
DO NOT emit INTENT:reserve for messages like:
- "what rooms are available", "which rooms are free", "are there any open rooms"
- "what's available tomorrow", "check availability", "room availability"
- "is there a room available", "any free rooms", "rooms today"
For these: answer with availability data from the LIVE AVAILABILITY DATA section above, then ask if they would like to reserve one. Wait for them to say yes before emitting INTENT:reserve.

### Reservation requests (DO trigger INTENT:reserve)
Only emit INTENT:reserve when the faculty explicitly asks YOU to do the booking action for them right now.
The faculty must express CURRENT ACTION INTENT, not just mention the word "reserve".

TRIGGERS INTENT:reserve — faculty is asking you to act:
- "reserve me a room", "book a room for me", "can you reserve a room", "make a reservation for me"
- "get me a room", "I want to reserve a room now", "help me reserve"
- "What rooms are available? I want to reserve one" — availability question PLUS action request

DOES NOT trigger INTENT:reserve — faculty is asking for information only:
- "How do I reserve a room?" → answer with step-by-step instructions only, NO form
- "What is the process to reserve?" → explain the process, NO form
- "How does reservation work?" → explain, NO form
- "What are the steps?" → explain, NO form
- "Can I reserve a room?" → answer yes/no with how-to, NO form
- Any question starting with "How", "What", "Why", "When", "Where", "Can I", "Is it possible" → these are information requests, NOT action requests

Rule: if the faculty is asking HOW to do something, they want instructions. If they are asking you TO DO it, they want the form.

## HOW TO RESERVE VIA AI ASSISTANT
When a faculty clearly asks to reserve (not just check availability), respond with a short, friendly message that:
1. Acknowledges their request
2. Briefly notes that you will open a pre-filled form so they can confirm and submit it
3. Mentions any fields you detected from their message (room, date, time) so they know what will be pre-filled
Then append the reservation signal at the very end of your message using this format:
  INTENT:reserve|campus:{campus}|date:{date}|start:{start}|end:{end}
Only include fields you are confident about from the faculty's message. Omit any field you are not sure about — leave it blank and the faculty will fill it in.
Field values:
- campus: use exactly "main" for PUP Main (Buildings A & B) or "cite" for PUP CITE (Building C). Omit if unknown.
- date: use YYYY-MM-DD format. Resolve relative words (today, tomorrow, next Monday) using TODAY's date above. Omit if unknown.
- start: use HH:MM (24-hour). Omit if unknown.
- end: use HH:MM (24-hour). Omit if unknown.
Examples:
- Faculty says "what rooms are available tomorrow?" → answer with availability data, ask if they want to reserve. NO INTENT:reserve yet.
- Faculty says "reserve me a room" → reply naturally then append: INTENT:reserve
- Faculty says "I need a room, what's free?" → answer availability first, then since they said "I need a room", append: INTENT:reserve
- Faculty says "reserve a room tomorrow 9am to 11am" → append: INTENT:reserve|date:{tomorrow's date}|start:09:00|end:11:00
- Faculty says "book a room in Building C on Friday 2pm to 4pm" → append: INTENT:reserve|campus:cite|date:{next Friday's date}|start:14:00|end:16:00
- Faculty says "reserve Building A Room A402 tomorrow 8am to 11am" → append: INTENT:reserve|campus:main|date:{tomorrow's date}|start:08:00|end:11:00
- Faculty says "what rooms are available?" (no reservation intent) → list available rooms by building from LIVE AVAILABILITY DATA, ask "Would you like me to open the reservation form?" NO INTENT:reserve.
Do not include any other special codes or signals in INTENT:reserve lines.

## HOW TO CANCEL (manual)
My Activity → Room reservations → Cancel (only if more than 1 hour before start time).

## FACULTY CONTEXT
Name: {$faculty_name}
Role: {$faculty_role}
Department: {$faculty_dept}
Quota used: {$quota_used} of {$quota_max} active reservations
Active/upcoming reservations:
{$ctx_reservations}
Waitlist:
{$ctx_waitlist}

## CURRENT SEMESTER
{$semester_label}
Room assignments below are scoped to this semester's timetable. They are not permanent — the super admin replaces the timetable each semester via import.

## THIS FACULTY'S TIMETABLE ROOMS (rooms assigned to them this semester)
{$ctx_my_timetable}
IMPORTANT RULES for timetable rooms:
1. When a faculty asks to reserve a room AND they have timetable rooms, PROACTIVELY suggest their timetable rooms first. Example: "You already have Room A301 in your timetable — do you need that room, or a different one?"
2. If they confirm they want their timetable room, remind them they do NOT need to reserve it — it is already theirs during class hours. They only need to reserve if they want it outside their scheduled class times.
3. If they want a different room, proceed normally.

## OTHER FACULTY'S TIMETABLE ROOMS (rooms that cannot be reserved — used by other faculty this semester)
{$ctx_assigned_rooms}
STRICT RULE: A faculty member CANNOT reserve a room that is assigned in another faculty's timetable.
- If they ask to reserve any room listed above, tell them it is unavailable and name the faculty who uses it.
- Do NOT emit INTENT:reserve for any room on this list.
- Suggest available rooms from the AVAILABLE ROOMS list below instead.

## AVAILABLE ROOMS (all bookable rooms in the system)
{$ctx_all_rooms}
Use this list to suggest specific rooms when the faculty asks for available rooms or when redirecting them away from a blocked room. Only suggest rooms that are not in OTHER FACULTY'S TIMETABLE ROOMS above.

## HOW TO CANCEL VIA AI ASSISTANT
⚠️ MANDATORY: When a faculty member asks you to cancel any reservation, you MUST append a signal at the end of your message. Do NOT cancel anything yourself. Do NOT just say "Sure, I'll cancel it." Instead, respond naturally AND then add the signal so the system can show confirmation buttons.

The reservation IDs are shown as [ID:12] in the FACULTY CONTEXT above — use those exact integers.

Follow these rules in order:
1. No active reservations → tell them there is nothing to cancel. No signal needed.
2. Reservation starts within {$rule_cutoff} of now → tell them cancellation is blocked by the {$rule_cutoff} cutoff rule. No signal.
3. "cancel my reservation" or "cancel all" with exactly 1 reservation → ask to confirm, then ADD the exact text: INTENT:cancel_confirm:{id}
4. "cancel all" with 2 reservations → ask to confirm cancelling both, then ADD: INTENT:cancel_confirm_multi:{id1},{id2}
5. "cancel my reservation" with 2 reservations and no room specified → ask which one, then ADD: INTENT:cancel_pick
6. Faculty names specific room(s):
   - All match → use rule 3 or 4 signal depending on count
   - Some don't match → tell them which ones you couldn't find, use signal only for matched ones
   - None match → tell them they have no reservation for those rooms, list what they have. No signal unless you offer.

SIGNAL FORMATS — append these at the very end of your message, exactly as written:
  INTENT:cancel_confirm:{id}              (replace {id} with the integer ID from FACULTY CONTEXT)
  INTENT:cancel_confirm_multi:{id1},{id2} (replace with comma-separated IDs)
  INTENT:cancel_pick                      (when faculty must choose)

Example correct responses:
- 1 reservation: "You have a reservation for Room 308 · Building A on Oct 26, 10:00–12:00. Shall I cancel it? INTENT:cancel_confirm:12"
- 2 reservations, cancel all: "I'll cancel both. Room 308 on Oct 26 and Room 201 on Oct 27. Confirm? INTENT:cancel_confirm_multi:12,15"
- 2 reservations, not specified: "Which one? INTENT:cancel_pick"

⛔ WRONG (never do this): "Sure, I'll cancel your reservation." — NO SIGNAL = NOTHING HAPPENS
✅ RIGHT: Always end with the signal so the system shows confirmation buttons.

NEVER omit the signal when cancelling. NEVER use an ID not in FACULTY CONTEXT.

## LIVE AVAILABILITY DATA
{$avail_section}
{$campus_instruction}

## YOUR BEHAVIOUR
- Answer only questions about room reservations and the PUPSync system.
- When availability data is provided above, summarise by building (free room count + booked slots). Do not list every room individually — give a count and highlight any blocked times.
- After answering an availability question, always ask: "Would you like me to open the reservation form for one of these rooms?" — but only ask once per conversation turn.
- When availability is "Not queried" and the faculty asks about availability, tell them you can check if they tell you which campus and date, or they can browse rooms in the Facilities tab.
- Never fabricate room names, dates, or slot data not provided above.
- Keep responses under 150 words unless giving step-by-step instructions.
- Be friendly, concise, and helpful.
- Distinguish clearly between INQUIRY (just asking) and ACTION (wanting to reserve). An availability question is an inquiry. Only open the reservation form when the faculty explicitly wants to book.
- Never reveal other faculty members' data.
- If asked something unrelated to rooms or PUPSync, politely redirect.
- When the faculty asks for help with a reservation step by step, guide them conversationally: ask for campus → date → time → then emit INTENT:reserve with everything pre-filled. Never rush to the form before you have enough information.
PROMPT;

// Append the PHP intent gate — this overrides the LLM's own judgment.
$system_prompt .= $intent_gate;

// ── 15. Call Groq ─────────────────────────────────────────────────────────────
$groq_url          = 'https://api.groq.com/openai/v1/chat/completions';
// Build conversation history from the client (last 6 turns).
// Each entry must have role (user|assistant) and content (string).
// We validate strictly so malicious input cannot inject system-role messages.
$raw_history = $body['history'] ?? [];
$safe_history = [];
if (is_array($raw_history)) {
    foreach (array_slice($raw_history, -6) as $turn) {
        $role    = (string)($turn['role']    ?? '');
        $content = trim((string)($turn['content'] ?? ''));
        // Only allow user/assistant roles — never system — and non-empty content
        if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
            $safe_history[] = ['role' => $role, 'content' => mb_substr($content, 0, 500)];
        }
    }
}

// Build the full messages array: system prompt → history → current user message
$api_messages = array_merge(
    [['role' => 'system', 'content' => $system_prompt]],
    $safe_history,
    [['role' => 'user', 'content' => $raw_message]]
);

$groq_request_body = json_encode([
    'model'       => 'openai/gpt-oss-120b',
    'messages'    => $api_messages,
    'max_tokens'  => 500,
    'temperature' => 0.7,
]);

$groq_context = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nAuthorization: Bearer " . $groq_key,
        'content'       => $groq_request_body,
        'timeout'       => 15,
        'ignore_errors' => true,
    ],
]);

$response    = @file_get_contents($groq_url, false, $groq_context);
$curl_error  = ($response === false) ? 'file_get_contents failed' : '';
$http_status = 0;

if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $http_status = (int)$m[1];
            break;
        }
    }
}

if ($http_status !== 200) {
    error_log('[PUPSync AI] Groq request failed. Status: ' . $http_status);
}

// ── 16. Error handling ────────────────────────────────────────────────────────
$refund_log = function () use ($conn, $log_insert_id): void {
    if ($log_insert_id > 0) {
        $d = $conn->prepare("DELETE FROM tbl_ai_chat_log WHERE id = ?");
        if ($d) { $d->bind_param('i', $log_insert_id); $d->execute(); $d->close(); }
    }
};

if ($curl_error || $response === false) {
    $refund_log();
    chat_respond(503, 'error', 'Could not reach the AI service. Please try again in a moment.');
}

$decoded = json_decode((string)$response, true);
if ($http_status !== 200 || isset($decoded['error'])) {
    $api_err = $decoded['error']['message'] ?? 'Unknown error';
    error_log('[PUPSync AI] Groq request failed. Status ' . $http_status . ': ' . $api_err);
    if (in_array($http_status, [500, 503, 504], true) || $http_status === 0) $refund_log();
    if ($http_status === 429) {
        $refund_log();
        chat_respond(429, 'error', 'The AI assistant has reached its daily limit. Please try again tomorrow.');
    }
    chat_respond(503, 'error', 'The AI assistant encountered an error. Please try again.');
}

$reply = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));

if ($reply === '') {
    error_log('[PUPSync AI] Empty reply from Groq.');
    chat_respond(503, 'error', 'The AI assistant did not return a response. Please try again.');
}

// ── 17. Extract intent signals ────────────────────────────────────────────────
// INTENT:reserve[|field:value ...] — show reservation mini-form with optional prefill data
$intent_reserve  = false;
$reserve_prefill = [];
if (preg_match('/INTENT:reserve([^\n]*)/', $reply, $rm)) {
    $intent_reserve  = true;
    $signal_tail     = trim($rm[1]);
    // Parse pipe-delimited key:value pairs after INTENT:reserve
    if ($signal_tail !== '') {
        foreach (explode('|', ltrim($signal_tail, '|')) as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            $sep = strpos($pair, ':');
            if ($sep === false) continue;
            $k = trim(substr($pair, 0, $sep));
            $v = trim(substr($pair, $sep + 1));
            if ($k !== '' && $v !== '') {
                $reserve_prefill[$k] = $v;
            }
        }
    }
    // Sanitise values: campus must be 'main' or 'cite', date must be YYYY-MM-DD,
    // times must be HH:MM — anything else is dropped so the form stays blank
    if (isset($reserve_prefill['campus']) && !in_array($reserve_prefill['campus'], ['main', 'cite'], true)) {
        unset($reserve_prefill['campus']);
    }
    if (isset($reserve_prefill['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reserve_prefill['date'])) {
        unset($reserve_prefill['date']);
    }
    foreach (['start', 'end'] as $tf) {
        if (isset($reserve_prefill[$tf]) && !preg_match('/^\d{2}:\d{2}$/', $reserve_prefill[$tf])) {
            unset($reserve_prefill[$tf]);
        }
    }
    $reply = trim(preg_replace('/INTENT:reserve[^\n]*/', '', $reply));
}

// INTENT:cancel_confirm:{id} — confirm cancelling one reservation
$intent_cancel_confirm = null;
if (preg_match('/INTENT:cancel_confirm:(\d+)/', $reply, $m)) {
    $cid = (int)$m[1];
    // Security: only honour IDs that belong to this faculty
    if (in_array($cid, $valid_reservation_ids, true)) {
        // Find the matching reservation row to send display data to JS
        foreach ($res_rows as $r) {
            if ((int)$r['id'] === $cid) {
                $intent_cancel_confirm = [
                    'id'   => $cid,
                    'room' => $r['room_name'] . ' · ' . $r['building_name'],
                    'date' => date('M j, Y', strtotime($r['reservation_date'])),
                    'time' => substr($r['start_time'], 0, 5) . ' – ' . substr($r['end_time'], 0, 5),
                ];
                break;
            }
        }
    }
    $reply = trim(preg_replace('/INTENT:cancel_confirm:\d+/', '', $reply));
}

// INTENT:cancel_confirm_multi:{id1},{id2} — confirm cancelling multiple reservations
$intent_cancel_confirm_multi = null;
if (preg_match('/INTENT:cancel_confirm_multi:([\d,]+)/', $reply, $m)) {
    $raw_ids  = array_map('intval', explode(',', $m[1]));
    $safe_ids = array_filter($raw_ids, fn($id) => in_array($id, $valid_reservation_ids, true));
    if (count($safe_ids) >= 2) {
        $multi_rows = [];
        foreach ($res_rows as $r) {
            if (in_array((int)$r['id'], $safe_ids, true)) {
                $multi_rows[] = [
                    'id'   => (int)$r['id'],
                    'room' => $r['room_name'] . ' · ' . $r['building_name'],
                    'date' => date('M j, Y', strtotime($r['reservation_date'])),
                    'time' => substr($r['start_time'], 0, 5) . ' – ' . substr($r['end_time'], 0, 5),
                ];
            }
        }
        if (count($multi_rows) >= 2) {
            $intent_cancel_confirm_multi = $multi_rows;
        }
    }
    $reply = trim(preg_replace('/INTENT:cancel_confirm_multi:[\d,]+/', '', $reply));
}

// INTENT:cancel_pick — faculty needs to choose from their reservations
$intent_cancel_pick = false;
if (str_contains($reply, 'INTENT:cancel_pick')) {
    $intent_cancel_pick = true;
    $reply = trim(str_replace('INTENT:cancel_pick', '', $reply));
}

// ── 18. Return ────────────────────────────────────────────────────────────────
$new_remaining = max(0, $RATE_LIMIT - ($rl_count + 1));

// Build pick options from the faculty's own reservations (used when intent_cancel_pick is true)
$cancel_pick_options = array_map(fn($r) => [
    'id'   => (int)$r['id'],
    'room' => $r['room_name'] . ' · ' . $r['building_name'],
    'date' => date('M j, Y', strtotime($r['reservation_date'])),
    'time' => substr($r['start_time'], 0, 5) . ' – ' . substr($r['end_time'], 0, 5),
], $res_rows);

ob_end_clean();
http_response_code(200);
echo json_encode([
    'status'                      => 'success',
    'reply'                       => $reply,
    'remaining'                   => $new_remaining,
    'ask_campus'                  => $ask_campus,
    'intent_reserve'              => $intent_reserve,
    'reserve_prefill'             => $reserve_prefill,
    'intent_cancel_confirm'       => $intent_cancel_confirm,
    'intent_cancel_confirm_multi' => $intent_cancel_confirm_multi,
    'intent_cancel_pick'          => $intent_cancel_pick,
    'cancel_pick_options'         => $cancel_pick_options,
]);
