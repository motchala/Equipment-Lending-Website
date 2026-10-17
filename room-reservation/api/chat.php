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

// ── 14. Format context as plain text ─────────────────────────────────────────
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

// ── 15. System prompt ─────────────────────────────────────────────────────────
$system_prompt = <<<PROMPT
You are PUPSync Room Assistant, an AI assistant for the Polytechnic University of the Philippines (PUP) Biñan Campus room reservation system. You help faculty members with room reservations only.

## SYSTEM RULES (always accurate — never contradict these)
- Operating hours: 7:00 AM to 8:00 PM. Reservations must start and end within these hours.
- Maximum advance booking: 21 days from today.
- Maximum active reservations per faculty: 2 at the same time. "Active" means Approved and not yet ended.
- Cancellation policy: A reservation can only be cancelled more than 1 hour before the start time.
- Organisation Advisers may attach a supporting document (PDF, JPG, PNG, max 5 MB) when reserving. This increases their priority in the arbitration system.
- Student-submitted reservations via faculty code do NOT count toward the faculty's 2-room quota.

## BUILDINGS
- Building A (Old) — PUP MAIN Campus — 5 floors — lecture halls, org rooms, computer labs, AVR, chemistry lab
- Building B (New) — PUP MAIN Campus — 4 floors — research rooms, lecture rooms, AVR
- Building C (CITE) — PUP CITE Campus — 4 floors — computer labs, engineering labs, audiovisual room

## HOW TO RESERVE A ROOM
1. Go to the Facilities tab
2. Choose a building
3. Browse by floor and click a room card
4. Check the room schedule to find a free slot
5. Click Reserve, fill in the date, time, purpose, and attendees
6. Submit — the system approves or adds you to the waitlist automatically

## HOW TO CANCEL A RESERVATION
Go to My Activity → Room reservations → find the reservation → click Cancel.
Only allowed more than 1 hour before the start time. The cancel button is locked within 1 hour.

## FACULTY CONTEXT (live data from the database)
Name: {$faculty_name}
Role: {$faculty_role}
Department: {$faculty_dept}
Quota used: {$quota_used} of {$quota_max} active reservations
Active/upcoming reservations:
{$ctx_reservations}
Waitlist:
{$ctx_waitlist}

## YOUR BEHAVIOUR
- Answer only questions about room reservations and the PUPSync system.
- For real-time availability of specific rooms and slots, tell the user to check the room card in the Facilities tab — you do not have live slot data.
- Never fabricate room names, dates, or availability data you were not given above.
- Keep responses under 120 words unless giving step-by-step instructions.
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
    'status'    => 'success',
    'reply'     => $reply,
    'remaining' => $new_remaining,
]);
