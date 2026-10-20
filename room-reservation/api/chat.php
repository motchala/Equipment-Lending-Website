<?php
declare(strict_types=1);

/**
 * room-reservation/api/chat.php
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
header('Cache-Control: no-store');

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
$api_keys = array_values(array_filter([
    trim((string)($_ENV['AI_API_KEY']  ?? '')),
    trim((string)($_ENV['AI_API_KEY2'] ?? '')),
    trim((string)($_ENV['AI_API_KEY3'] ?? '')),
], fn($k) => $k !== ''));

if (empty($api_keys)) {
    error_log('[PUPSync AI] No API keys configured in .env');
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
      WHERE faculty_id = ? AND sent_at >= (NOW() - INTERVAL 1 HOUR)"
);
$rl_stmt->bind_param('s', $faculty_id);
$rl_stmt->execute();
$rl_count = (int)($rl_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$rl_stmt->close();

if ($rl_count >= $RATE_LIMIT) {
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
            rr.reservation_date, rr.start_time, rr.end_time, rr.purpose
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

$quota_used = count($res_rows);
$quota_max  = 2;

$ctx_reservations = $quota_used === 0
    ? 'None — no active or upcoming reservations.'
    : implode("\n", array_map(fn($r) =>
        '- ' . $r['room_name'] . ' (' . $r['building_name'] . ')'
        . ' on ' . $r['reservation_date']
        . ' from ' . substr($r['start_time'], 0, 5)
        . ' to '   . substr($r['end_time'],   0, 5)
        . ' — Purpose: ' . $r['purpose'],
        $res_rows));

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
    $lines[] = "\nFor specific time slots, tell the faculty to click the room card in the Facilities tab.";
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

$campus_instruction = $ask_campus
    ? "\n## IMPORTANT — CAMPUS UNKNOWN\nThe faculty asked about availability but did not specify a campus. You MUST ask: \"Which campus are you at?\" and present exactly two options:\n→ PUP Main (Buildings A & B)\n→ PUP CITE (Building C)\nDo not answer until you know the campus."
    : '';

$system_prompt = <<<PROMPT
You are PUPSync Room Assistant, an AI assistant for the Polytechnic University of the Philippines (PUP) Biñan Campus room reservation system. You help faculty members with room reservations only.

## TODAY (server-injected — always accurate)
- Current date: {$today_label}
- Reservations on Sundays are NOT allowed.

## SYSTEM RULES
- Operating hours: 7:00 AM to 8:00 PM.
- Maximum advance booking: 21 days from today ({$today_date}).
- Maximum active reservations per faculty: 2 at the same time.
- Cancellation: only allowed more than 1 hour before the start time.
- Organisation Advisers may attach a supporting document (PDF, JPG, PNG, max 5 MB) for priority.
- Student-submitted reservations via faculty code do NOT count toward the faculty's quota.

## CAMPUSES & BUILDINGS
- **PUP MAIN** (Zapote, Biñan): Building A (Old, 5 floors), Building B (New, 4 floors)
- **PUP CITE** (Canlalay, Biñan): Building C (4 floors — computer labs, engineering labs, AVR)

## HOW TO RESERVE A ROOM (manual flow)
Go to the Facilities tab → choose a building → browse by floor → click a room card → Reserve.

## HOW TO RESERVE VIA AI ASSISTANT
If a faculty asks YOU to reserve a room for them, respond naturally and then include the exact text INTENT:reserve at the end of your message. The system will show them a reservation form automatically. Do not include any other special codes or signals.

## HOW TO CANCEL
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

## LIVE AVAILABILITY DATA
{$avail_section}
{$campus_instruction}

## YOUR BEHAVIOUR
- Answer only questions about room reservations and the PUPSync system.
- When availability data is provided above, summarise by building (free room count + booked slots). Do not list every room.
- When availability is "Not queried", tell the faculty to check the room card in the Facilities tab.
- Never fabricate room names, dates, or slot data not provided above.
- Keep responses under 150 words unless giving step-by-step instructions.
- Be friendly, concise, and helpful.
- Never reveal other faculty members' data.
- If asked something unrelated to rooms or PUPSync, politely redirect.
PROMPT;

// ── 15. Call Gemini with multi-key rotation ───────────────────────────────────
$gemini_url          = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';
$gemini_request_body = json_encode([
    'system_instruction' => ['parts' => [['text' => $system_prompt]]],
    'contents'           => [['role' => 'user', 'parts' => [['text' => $raw_message]]]],
    'generationConfig'   => ['maxOutputTokens' => 300, 'thinkingConfig' => ['thinkingLevel' => 'low']],
]);

$response    = false;
$curl_error  = '';
$http_status = 0;
$provider    = 'gemini'; // tracks which provider ultimately answered

foreach ($api_keys as $current_key) {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init($gemini_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $gemini_request_body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $current_key],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $response    = curl_exec($ch);
        $curl_error  = curl_error($ch);
        $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curl_error || $response === false) {
            error_log('[PUPSync AI] Gemini key curl error: ' . $curl_error);
            break; // try next key
        }
        if ($http_status === 200) {
            // Verify reply is not empty before accepting
            $decoded_check = json_decode((string)$response, true);
            $reply_check   = trim((string)($decoded_check['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            if ($reply_check !== '') break 2; // success — exit both loops
            error_log('[PUPSync AI] Gemini key returned 200 but empty reply, rotating.');
            $response = false;
            break;
        }
        if ($http_status === 429) {
            error_log('[PUPSync AI] Gemini key quota exhausted (429), rotating.');
            break; // try next key
        }
        if ($http_status === 503 && $attempt < 2) {
            usleep(700000);
            continue; // retry same key once
        }
        error_log('[PUPSync AI] Gemini key failed with status ' . $http_status . ', rotating.');
        break; // try next key
    }
}

// ── 15b. Groq fallback — fires if ALL Gemini keys failed for any reason ───────
if ($response === false || $http_status !== 200) {
    error_log('[PUPSync AI] All Gemini keys failed. Switching to Groq fallback for faculty ' . $faculty_id);

    load_env(); // already called above, safe to call again (no-op if already loaded)
    $groq_key = trim((string)($_ENV['GROQ_API_KEY'] ?? ''));

    if ($groq_key === '') {
        error_log('[PUPSync AI] Groq fallback triggered but GROQ_API_KEY is not set in .env');
        // Fall through — sections 16 error handling will respond 503
    } else {
        $groq_url          = 'https://api.groq.com/openai/v1/chat/completions';
        $groq_request_body = json_encode([
            'model'       => 'openai/gpt-oss-120b',
            'messages'    => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user',   'content' => $raw_message],
            ],
            'max_tokens'  => 300,
            'temperature' => 0.7,
        ]);

        $ch = curl_init($groq_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $groq_request_body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $groq_key,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $response    = curl_exec($ch);
        $curl_error  = curl_error($ch);
        $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 200) {
            $provider = 'groq';
            error_log('[PUPSync AI] Groq fallback succeeded for faculty ' . $faculty_id);
        } else {
            error_log('[PUPSync AI] Groq fallback also failed. Status: ' . $http_status);
        }
    }
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
    error_log('[PUPSync AI] Final failure (' . $provider . '). Status ' . $http_status . ': ' . $api_err);
    if (in_array($http_status, [500, 503, 504], true) || $http_status === 0) $refund_log();
    if ($http_status === 429) {
        $refund_log();
        chat_respond(429, 'error', 'The AI assistant has reached its daily limit. Please try again tomorrow.');
    }
    chat_respond(503, 'error', 'The AI assistant encountered an error. Please try again.');
}

// Parse reply — path differs by provider
if ($provider === 'groq') {
    $reply = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
} else {
    $reply = trim((string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
}

if ($reply === '') {
    error_log('[PUPSync AI] Empty reply from ' . $provider . '.');
    chat_respond(503, 'error', 'The AI assistant did not return a response. Please try again.');
}

// ── 17. Extract INTENT:reserve signal ─────────────────────────────────────────
// If Gemini included INTENT:reserve, strip it from the visible reply and
// return it as a structured flag. JS will show the reservation mini-form.
$intent_reserve = str_contains($reply, 'INTENT:reserve');
$reply          = trim(str_replace('INTENT:reserve', '', $reply));

// ── 18. Return ────────────────────────────────────────────────────────────────
$new_remaining = max(0, $RATE_LIMIT - ($rl_count + 1));

ob_end_clean();
http_response_code(200);
echo json_encode([
    'status'         => 'success',
    'reply'          => $reply,
    'remaining'      => $new_remaining,
    'ask_campus'     => $ask_campus,
    'intent_reserve' => $intent_reserve,
]);
