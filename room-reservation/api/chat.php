<?php
declare(strict_types=1);

// ── 1. Security headers (always first for API endpoints at depth 2) ──────────
require_once __DIR__ . '/../../config/security-headers.php';
date_default_timezone_set('Asia/Manila');

// ── 2. Output buffer + JSON content type ─────────────────────────────────────
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── 3. Unified JSON responder ─────────────────────────────────────────────────
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

// ── 5. Session guard — faculty only ──────────────────────────────────────────
require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['faculty_id'])) {
    chat_respond(401, 'error', 'Unauthorized.');
}
$faculty_id   = (string)$_SESSION['faculty_id'];
$faculty_name = (string)($_SESSION['faculty_name'] ?? 'Faculty');

// ── 6. Parse JSON body (read input stream once) ───────────────────────────────
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    chat_respond(400, 'error', 'Invalid request body.');
}

// ── 7. CSRF guard — direct comparison against the session token ───────────────
// We do not use csrf_verify() here because that function re-reads php://input
// which is already consumed. Instead we read the token directly from the
// already-parsed $body and compare it ourselves — same security guarantee.
require_once __DIR__ . '/../../config/csrf.php';
$submitted_token = trim((string)($body['csrf_token'] ?? ''));
$session_token   = (string)($_SESSION['csrf_token'] ?? '');
if ($session_token === '' || $submitted_token === '' || !hash_equals($session_token, $submitted_token)) {
    chat_respond(403, 'error', 'Invalid session token. Please refresh the page.');
}

// ── 7. Load API key ───────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/env.php';
load_env();
$api_key = trim((string)($_ENV['AI_API_KEY'] ?? ''));
if ($api_key === '') {
    error_log('[PUPSync AI] AI_API_KEY is not set in .env');
    chat_respond(503, 'error', 'The AI assistant is temporarily unavailable. Please try again later.');
}

// ── 8. Validate and sanitise user message ─────────────────────────────────────
$raw_message = trim((string)($body['message'] ?? ''));
if (mb_strlen($raw_message) < 3) {
    chat_respond(422, 'error', 'Please type a complete question (at least 3 characters).');
}
if (mb_strlen($raw_message) > 500) {
    chat_respond(422, 'error', 'Message is too long. Please keep it under 500 characters.');
}

// ── 9. Database connection ────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

// ── 10. Self-healing migration for tbl_ai_chat_log ───────────────────────────
// Safety net: creates the table if it was not added via the migration script.
$conn->query("
    CREATE TABLE IF NOT EXISTS `tbl_ai_chat_log` (
        `id`         int(11)     NOT NULL AUTO_INCREMENT,
        `faculty_id` varchar(50) NOT NULL,
        `sent_at`    datetime    NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_faculty_sent` (`faculty_id`, `sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── 11. Rate limit check (20 messages per rolling hour) ───────────────────────
$RATE_LIMIT = 20;

$rl_stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tbl_ai_chat_log
      WHERE faculty_id = ? AND sent_at >= (NOW() - INTERVAL 1 HOUR)"
);
$rl_stmt->bind_param('s', $faculty_id);
$rl_stmt->execute();
$rl_count = (int)($rl_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$rl_stmt->close();

if ($rl_count >= $RATE_LIMIT) {
    // Tell the faculty exactly when their limit resets
    $oldest_stmt = $conn->prepare(
        "SELECT MIN(sent_at) AS oldest FROM tbl_ai_chat_log
          WHERE faculty_id = ? AND sent_at >= (NOW() - INTERVAL 1 HOUR)"
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
        'You have reached the 20 message limit for this hour.' .
        ($reset_time ? " Your limit resets at {$reset_time}." : ''),
        ['reset_time' => $reset_time, 'remaining' => 0]
    );
}

// ── 12. Log this message BEFORE calling Gemini ────────────────────────────────
// Logged before the API call so aborted/timed-out calls still count.
// We store the inserted row ID so we can delete it if Gemini fails with a
// server-side error (503/curl error) — those are not the user's fault.
$log_stmt = $conn->prepare(
    "INSERT INTO tbl_ai_chat_log (faculty_id, sent_at) VALUES (?, NOW())"
);
$log_stmt->bind_param('s', $faculty_id);
$log_stmt->execute();
$log_insert_id = (int)$conn->insert_id;
$log_stmt->close();

// Cleanup old rows ~5% of requests to keep the table small
if (mt_rand(1, 20) === 1) {
    $conn->query("DELETE FROM tbl_ai_chat_log WHERE sent_at < (NOW() - INTERVAL 24 HOUR)");
}

// ── 13. Build faculty context from DB ────────────────────────────────────────
// 13a. Faculty role and department
$user_stmt = $conn->prepare(
    "SELECT role, department FROM tbl_users WHERE faculty_id = ? LIMIT 1"
);
$user_stmt->bind_param('s', $faculty_id);
$user_stmt->execute();
$user_row     = $user_stmt->get_result()->fetch_assoc() ?? [];
$user_stmt->close();
$faculty_role = (string)($user_row['role'] ?? 'Regular Faculty');
$faculty_dept = (string)($user_row['department'] ?? '');

// 13b. Active + upcoming room reservations (own, not student-submitted, not ended)
$res_stmt = $conn->prepare(
    "SELECT rr.id, r.room_name, b.name AS building_name,
            rr.reservation_date, rr.start_time, rr.end_time,
            rr.purpose, rr.submitted_as
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE rr.faculty_id   = ?
        AND rr.submitted_as <> 'student'
        AND rr.status        = 'Approved'
        AND (rr.reservation_date > CURDATE()
             OR (rr.reservation_date = CURDATE() AND rr.end_time > CURTIME()))
      ORDER BY rr.reservation_date ASC, rr.start_time ASC
      LIMIT 10"
);
$res_stmt->bind_param('s', $faculty_id);
$res_stmt->execute();
$res_rows = $res_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$res_stmt->close();

// 13c. Waitlist entries
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

// ── 14. Format faculty context as plain text ──────────────────────────────────
$quota_used = count($res_rows);
$quota_max  = 2;

if ($quota_used === 0) {
    $ctx_reservations = 'None — no active or upcoming reservations.';
} else {
    $ctx_reservations = implode("\n", array_map(function ($r) {
        return '- ' . $r['room_name'] . ' (' . $r['building_name'] . ')'
             . ' on ' . $r['reservation_date']
             . ' from ' . substr($r['start_time'], 0, 5)
             . ' to '   . substr($r['end_time'],   0, 5)
             . ' — Purpose: ' . $r['purpose'];
    }, $res_rows));
}

if (empty($wl_rows)) {
    $ctx_waitlist = 'None.';
} else {
    $ctx_waitlist = implode("\n", array_map(function ($w) {
        return '- ' . $w['room_name'] . ' (' . $w['building_name'] . ')'
             . ' on ' . $w['reservation_date']
             . ' from ' . substr($w['start_time'], 0, 5)
             . ' to '   . substr($w['end_time'],   0, 5);
    }, $wl_rows));
}

// ── 14b. Phase 2 — Availability intent detection + campus disambiguation ──────

/**
 * Returns true when the message appears to be asking about room availability.
 */
function ai_is_availability_query(string $msg): bool
{
    $keywords = [
        // Explicit availability phrases — unambiguous
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
        // Time-scoped phrases (only when paired with room context)
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
 * Detects which campus the faculty is asking about.
 * Returns 'main', 'cite', or null (unknown — must ask).
 */
function ai_detect_campus(string $msg): ?string
{
    $lower = mb_strtolower($msg);
    $main_kw = ['main', 'building a', 'building b', 'zapote', 'pup main'];
    $cite_kw = ['cite', 'building c', 'canlalay', 'pup cite'];
    foreach ($cite_kw as $kw) { if (str_contains($lower, $kw)) return 'cite'; }
    foreach ($main_kw as $kw) { if (str_contains($lower, $kw)) return 'main'; }
    return null;
}

/**
 * Extracts a target date from the message.
 * Understands: today, tomorrow, day names (Monday–Sunday),
 * "next Monday", and date strings like "October 25" / "Oct 25" / "25 Oct".
 * Returns a Y-m-d string in Asia/Manila timezone, defaulting to today.
 */
function ai_extract_date(string $msg): string
{
    $tz    = new DateTimeZone('Asia/Manila');
    $now   = new DateTimeImmutable('now', $tz);
    $lower = mb_strtolower(trim($msg));

    // "today"
    if (str_contains($lower, 'today'))    return $now->format('Y-m-d');
    // "tomorrow"
    if (str_contains($lower, 'tomorrow')) return $now->modify('+1 day')->format('Y-m-d');

    // Day names — "next Monday" or just "Monday"
    $days = ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,
             'friday'=>5,'saturday'=>6,'sunday'=>0];
    foreach ($days as $name => $dow) {
        if (str_contains($lower, $name)) {
            $prefix = str_contains($lower, 'next ' . $name) ? 'next ' : '';
            try {
                $dt = new DateTimeImmutable($prefix . $name, $tz);
                // If the result is today or in the past (without "next"), advance 7 days
                if ($prefix === '' && $dt->format('Y-m-d') <= $now->format('Y-m-d')) {
                    $dt = $dt->modify('+7 days');
                }
                if ($dt->format('Y-m-d') >= $now->format('Y-m-d')) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Exception $e) { /* fall through */ }
        }
    }

    // Month-name patterns: "October 25", "Oct 25", "25 October", "25 Oct"
    $month_names = [
        'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
        'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12,
        'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'jun'=>6,'jul'=>7,
        'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
    ];
    foreach ($month_names as $mname => $mnum) {
        if (!str_contains($lower, $mname)) continue;
        // "October 25" or "Oct 25"
        if (preg_match('/\b' . preg_quote($mname, '/') . '\s+(\d{1,2})\b/', $lower, $m)) {
            $day = (int)$m[1];
        // "25 October" or "25 Oct"
        } elseif (preg_match('/\b(\d{1,2})\s+' . preg_quote($mname, '/') . '\b/', $lower, $m)) {
            $day = (int)$m[1];
        } else {
            continue;
        }
        if ($day < 1 || $day > 31) continue;
        $year = (int)$now->format('Y');
        $candidate = sprintf('%04d-%02d-%02d', $year, $mnum, $day);
        // If the date has already passed this year, try next year
        if ($candidate < $now->format('Y-m-d')) {
            $candidate = sprintf('%04d-%02d-%02d', $year + 1, $mnum, $day);
        }
        // Validate it's a real date and within 21-day booking window
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $candidate, $tz);
        if ($dt && $dt->format('Y-m-d') === $candidate) {
            return $candidate;
        }
    }

    // Default to today
    return $now->format('Y-m-d');
}

/**
 * Queries room availability for a campus on a given date.
 * Returns a compact summary string for the system prompt.
 *
 * @param mysqli $conn
 * @param string $campus  'main' or 'cite'
 * @param string $date    Y-m-d
 * @return string
 */
function ai_availability_summary(mysqli $conn, string $campus, string $date): string
{
    // campus_id: 1 = PUP MAIN, 2 = PUP CITE
    $campus_id = ($campus === 'cite') ? 2 : 1;
    $campus_label = ($campus === 'cite') ? 'PUP CITE' : 'PUP MAIN';

    // Get all bookable rooms for this campus
    $stmt = $conn->prepare(
        "SELECT r.room_id, r.room_name, r.floor_label, r.floor_number,
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

    if (empty($rooms)) {
        return "No bookable rooms found for {$campus_label} on {$date}.";
    }

    // Get all Approved reservations for these rooms on this date
    $room_ids = array_column($rooms, 'room_id');
    $placeholders = implode(',', array_fill(0, count($room_ids), '?'));
    $types = str_repeat('i', count($room_ids));

    $res_stmt = $conn->prepare(
        "SELECT room_id,
                TIME_FORMAT(start_time, '%H:%i') AS start,
                TIME_FORMAT(end_time,   '%H:%i') AS end
           FROM tbl_room_reservations
          WHERE room_id IN ({$placeholders})
            AND reservation_date = ?
            AND status = 'Approved'
          ORDER BY room_id, start_time"
    );
    // bind all room_ids + date
    $bind_args = array_merge([$types . 's'], $room_ids, [$date]);
    // bind_param needs references
    $refs = [];
    foreach ($bind_args as $k => $v) { $refs[$k] = &$bind_args[$k]; }
    call_user_func_array([$res_stmt, 'bind_param'], $refs);
    $res_stmt->execute();
    $bookings_raw = $res_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $res_stmt->close();

    // Index bookings by room_id
    $bookings = [];
    foreach ($bookings_raw as $b) {
        $bookings[$b['room_id']][] = $b['start'] . '–' . $b['end'];
    }

    // Group by building for the summary
    $by_building = [];
    foreach ($rooms as $r) {
        $bid  = $r['building_name'];
        $rid  = $r['room_id'];
        $free = !isset($bookings[$rid]);
        $by_building[$bid]['total']  = ($by_building[$bid]['total']  ?? 0) + 1;
        $by_building[$bid]['free']   = ($by_building[$bid]['free']   ?? 0) + ($free ? 1 : 0);
        if (!$free) {
            $by_building[$bid]['booked_slots'][$r['room_name']] = $bookings[$rid];
        }
    }

    // Format output
    $date_label = (new DateTimeImmutable($date, new DateTimeZone('Asia/Manila')))->format('l, F j');
    $lines = ["## ROOM AVAILABILITY — {$campus_label} — {$date_label}"];
    foreach ($by_building as $bname => $data) {
        $free  = $data['free'];
        $total = $data['total'];
        $lines[] = "\n### {$bname}";
        $lines[] = "Free rooms: {$free} of {$total} bookable";
        if ($free === 0) {
            $lines[] = "All rooms are booked for this date.";
        } elseif (isset($data['booked_slots'])) {
            $lines[] = "Rooms with bookings (avoid these times):";
            foreach ($data['booked_slots'] as $rname => $slots) {
                $lines[] = "  - {$rname}: " . implode(', ', $slots);
            }
        }
    }
    $lines[] = "\nFor exact per-room time slots, tell the faculty to click the room card in the Facilities tab.";

    return implode("\n", $lines);
}

// ── Run the Phase 2 availability pipeline ────────────────────────────────────
$ctx_availability   = '';   // empty = not queried (fast path)
$ask_campus         = false; // true = AI should ask which campus

if (ai_is_availability_query($raw_message)) {
    $campus = ai_detect_campus($raw_message);
    if ($campus === null) {
        // Campus unknown — set flag so AI asks; don't query DB yet
        $ask_campus = true;
    } else {
        $target_date      = ai_extract_date($raw_message);
        $ctx_availability = ai_availability_summary($conn, $campus, $target_date);
    }
}

// ── 15. System prompt ─────────────────────────────────────────────────────────
$avail_section = $ctx_availability !== ''
    ? $ctx_availability
    : 'Not queried — the faculty did not ask about availability, or campus is unknown.';

$campus_instruction = $ask_campus
    ? "\n## IMPORTANT — CAMPUS UNKNOWN\nThe faculty asked about room availability but did not specify a campus. You MUST ask: \"Which campus are you at?\" and present exactly two options on separate lines:\n→ PUP Main (Buildings A & B)\n→ PUP CITE (Building C)\nDo not answer the availability question until you know the campus. Do not add anything else to this response."
    : '';

$system_prompt = <<<PROMPT
You are PUPSync Room Assistant, an AI assistant for the Polytechnic University of the Philippines (PUP) Biñan Campus room reservation system. You help faculty members with room reservations only.

## SYSTEM RULES (always accurate — never contradict these)
- Operating hours: 7:00 AM to 8:00 PM. Reservations must start and end within these hours.
- Maximum advance booking: 21 days from today.
- Maximum active reservations per faculty: 2 at the same time. "Active" means Approved and not yet ended.
- Cancellation policy: A reservation can only be cancelled more than 1 hour before the start time.
- Organisation Advisers may attach a supporting document (PDF, JPG, PNG, max 5 MB) when reserving. This increases their priority in the arbitration system.
- Student-submitted reservations via faculty code do NOT count toward the faculty's 2-room quota.

## CAMPUSES & BUILDINGS
- **PUP MAIN Campus** (Zapote, Biñan, Laguna)
  - Building A (Old) — 5 floors — lecture halls, org rooms, computer labs, AVR, chemistry lab
  - Building B (New) — 4 floors — research rooms, lecture rooms, AVR
- **PUP CITE Campus** (Canlalay, Biñan, Laguna)
  - Building C — 4 floors — computer labs, engineering labs, audiovisual room

## HOW TO RESERVE A ROOM
1. Go to the Facilities tab → choose a building → browse by floor → click a room card
2. Check the room schedule → click Reserve → fill in date, time, purpose, attendees → submit

## HOW TO CANCEL A RESERVATION
My Activity → Room reservations → find reservation → click Cancel (only if more than 1 hour before start).

## FACULTY CONTEXT (live data from the database)
Name: {$faculty_name}
Role: {$faculty_role}
Department: {$faculty_dept}
Quota used: {$quota_used} of {$quota_max} active reservations
Active/upcoming reservations:
{$ctx_reservations}
Waitlist:
{$ctx_waitlist}

## LIVE AVAILABILITY DATA
{$avail_section}
{$campus_instruction}

## YOUR BEHAVIOUR
- Answer only questions about room reservations and the PUPSync system.
- When availability data is provided above, use it to give a specific summary by building. Do not list every room — give the count of free rooms per building and mention rooms with bookings.
- When availability says "Not queried", do not speculate — tell the faculty to check the room card in the Facilities tab.
- Never fabricate room names, dates, or slot data not provided above.
- Keep responses under 150 words unless giving step-by-step instructions.
- Be friendly, concise, and helpful.
- Never reveal other faculty members' data.
- If asked something unrelated to rooms or PUPSync, politely redirect.
PROMPT;

// ── 16. Call Gemini API ───────────────────────────────────────────────────────
// Using gemini-3.8-flash via the legacy generateContent endpoint (still fully
// supported). Auth is sent as x-goog-api-key header instead of a query param
// per Google's current guidance for Gemini 3.x models.
$gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';

$request_body = json_encode([
    'system_instruction' => [
        'parts' => [['text' => $system_prompt]],
    ],
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => $raw_message]]],
    ],
    'generationConfig' => [
        // Note: Google recommends keeping temperature at its default (1.0) for
        // Gemini 3.x models. Lower values can cause looping. maxOutputTokens
        // is kept intentionally small to control cost and response length.
        'maxOutputTokens' => 300,
        'thinkingConfig'  => ['thinkingLevel' => 'low'],
    ],
]);

// Retry up to 2 extra times on 503 (Gemini overload) with short back-off.
$response    = false;
$curl_error  = '';
$http_status = 0;
$max_attempts = 3;

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    $ch = curl_init($gemini_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $request_body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $api_key,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $response    = curl_exec($ch);
    $curl_error  = curl_error($ch);
    $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Only retry on 503 (server overload) — not on auth/quota errors
    if ($http_status !== 503 || $attempt === $max_attempts) break;
    usleep(600000 * $attempt); // 0.6 s, then 1.2 s
}

// ── 17. Handle errors ─────────────────────────────────────────────────────────
// Helper: remove the log row so a server-side failure doesn't cost the user a message.
$refund_log = function () use ($conn, $log_insert_id): void {
    if ($log_insert_id > 0) {
        $d = $conn->prepare("DELETE FROM tbl_ai_chat_log WHERE id = ?");
        $d->bind_param('i', $log_insert_id);
        $d->execute();
        $d->close();
    }
};

if ($curl_error || $response === false) {
    error_log('[PUPSync AI] curl error: ' . $curl_error);
    $refund_log();
    chat_respond(503, 'error', 'Could not reach the AI service. Please try again in a moment.');
}

$decoded = json_decode((string)$response, true);

if ($http_status !== 200 || isset($decoded['error'])) {
    $gemini_err = $decoded['error']['message'] ?? 'Unknown error';
    error_log('[PUPSync AI] Gemini error ' . $http_status . ': ' . $gemini_err);

    // Refund the log entry for server-side failures (503 overload, network issues).
    // Do NOT refund 400/401/429 — those are caused by the request itself.
    if (in_array($http_status, [500, 503, 504], true) || $http_status === 0) {
        $refund_log();
    }

    if ($http_status === 503) {
        chat_respond(503, 'error', 'The AI assistant is busy right now. Please wait a moment and try again.');
    }
    chat_respond(503, 'error', 'AI service error (' . $http_status . '). Please try again.');
}

$reply = trim((string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
if ($reply === '') {
    error_log('[PUPSync AI] Empty reply. Response: ' . $response);
    chat_respond(503, 'error', 'The AI assistant did not return a response. Please try again.');
}

// ── 18. Return success ────────────────────────────────────────────────────────
$new_remaining = max(0, $RATE_LIMIT - ($rl_count + 1));

ob_end_clean();
http_response_code(200);
echo json_encode([
    'status'     => 'success',
    'reply'      => $reply,
    'remaining'  => $new_remaining,
    'ask_campus' => $ask_campus,
]);
