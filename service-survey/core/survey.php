<?php

declare(strict_types=1);

const SS_MIN_RESPONSES = 5;
const SS_MAX_QUESTIONS = 5;
const SS_MIN_QUESTIONS = 1;
const SS_QUESTION_MIN = 8;
const SS_QUESTION_MAX = 160;
const SS_COMMENT_MAX = 500;

function ss_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ss_fail(int $code, string $message, ?string $reason = null): void
{
    $payload = ['status' => 'error', 'error' => $message];
    if ($reason !== null) {
        $payload['code'] = $reason;
    }
    ss_json($payload, $code);
}

function ss_set_exception_handler(string $endpoint): void
{
    set_exception_handler(static function (Throwable $e) use ($endpoint): void {
        error_log('[service-survey/' . $endpoint . '] ' . $e->getMessage());
        ss_fail(500, 'Something went wrong. Please try again.');
    });
}

function ss_json_body(): array
{
    $body = json_decode((string)file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}

function ss_verify_csrf(array $body): void
{
    $token = (string)($body['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        ss_fail(403, 'Invalid or expired session. Please refresh the page and try again.');
    }
}

function ss_ensure_schema(mysqli $conn): void
{
    $ddl = [
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_config (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            comment_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            question_text VARCHAR(160) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            responses INT UNSIGNED NOT NULL DEFAULT 0,
            total_score INT UNSIGNED NOT NULL DEFAULT 0,
            rating_1 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_2 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_3 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_4 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_5 INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_completions (
            source_type VARCHAR(16) NOT NULL,
            transaction_key VARCHAR(64) NOT NULL,
            answered TINYINT(1) NOT NULL DEFAULT 0,
            consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (source_type, transaction_key),
            KEY idx_service_survey_answered (answered)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_comments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            comment_text VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_service_survey_comment_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($ddl as $sql) {
        if (!$conn->query($sql)) {
            throw new RuntimeException('Could not create service survey tables: ' . $conn->error);
        }
    }

    if (!$conn->query('INSERT IGNORE INTO tbl_service_survey_config (id, enabled, comment_enabled) VALUES (1, 1, 1)')) {
        throw new RuntimeException('Could not initialize service survey settings: ' . $conn->error);
    }

    $count = $conn->query('SELECT COUNT(*) AS n FROM tbl_service_survey_questions WHERE active = 1');
    if (!$count) {
        throw new RuntimeException('Could not read service survey questions: ' . $conn->error);
    }
    $row = $count->fetch_assoc();
    $count->free();
    if ((int)($row['n'] ?? 0) === 0) {
        $defaults = [
            'How satisfied are you with the service you received?',
            'How helpful and respectful was the staff who assisted you?',
            'How clear was the information about your transaction?',
            'How satisfied are you with the time it took to complete your transaction?',
            'How likely are you to use this service again?',
        ];
        $stmt = $conn->prepare('INSERT INTO tbl_service_survey_questions (question_text, sort_order) VALUES (?, ?)');
        if (!$stmt) {
            throw new RuntimeException('Could not prepare default service survey questions: ' . $conn->error);
        }
        foreach ($defaults as $index => $text) {
            $order = $index + 1;
            $stmt->bind_param('si', $text, $order);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Could not initialize default service survey questions: ' . $stmt->error);
            }
        }
        $stmt->close();
    }
}

function ss_config(mysqli $conn): array
{
    $result = $conn->query('SELECT enabled, comment_enabled FROM tbl_service_survey_config WHERE id = 1 LIMIT 1');
    if (!$result) {
        throw new RuntimeException('Could not read service survey settings: ' . $conn->error);
    }
    $row = $result->fetch_assoc() ?: ['enabled' => 1, 'comment_enabled' => 1];
    $result->free();
    return [
        'enabled' => (bool)$row['enabled'],
        'comment_enabled' => (bool)$row['comment_enabled'],
    ];
}

function ss_questions(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT id, question_text, sort_order, responses, total_score, rating_1, rating_2, rating_3, rating_4, rating_5
         FROM tbl_service_survey_questions WHERE active = 1 ORDER BY sort_order ASC, id ASC'
    );
    if (!$result) {
        throw new RuntimeException('Could not read service survey questions: ' . $conn->error);
    }
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $counts = [];
        for ($star = 1; $star <= 5; $star++) {
            $counts[] = (int)$row['rating_' . $star];
        }
        $responses = (int)$row['responses'];
        $questions[] = [
            'id' => (int)$row['id'],
            'text' => (string)$row['question_text'],
            'responses' => $responses,
            'average' => $responses > 0 ? round((int)$row['total_score'] / $responses, 2) : null,
            'counts' => $counts,
            'total_score' => (int)$row['total_score'],
        ];
    }
    $result->free();
    return $questions;
}

function ss_results(mysqli $conn, int $offset = 0): array
{
    $config = ss_config($conn);
    $questions = ss_questions($conn);
    $responseResult = $conn->query('SELECT COUNT(*) AS n FROM tbl_service_survey_completions WHERE answered = 1');
    if (!$responseResult) {
        throw new RuntimeException('Could not count service survey responses: ' . $conn->error);
    }
    $responses = (int)($responseResult->fetch_assoc()['n'] ?? 0);
    $responseResult->free();

    $ratingCount = 0;
    $ratingTotal = 0;
    foreach ($questions as &$question) {
        $ratingCount += $question['responses'];
        $ratingTotal += $question['total_score'];
        if ($question['responses'] < SS_MIN_RESPONSES) {
            $question['average'] = null;
            $question['counts'] = null;
        }
        unset($question['total_score']);
    }
    unset($question);

    $commentCountResult = $conn->query('SELECT COUNT(*) AS n FROM tbl_service_survey_comments');
    if (!$commentCountResult) {
        throw new RuntimeException('Could not count service survey comments: ' . $conn->error);
    }
    $commentCount = (int)($commentCountResult->fetch_assoc()['n'] ?? 0);
    $commentCountResult->free();

    // Keep individual comments hidden until there are enough comments to protect contributors.
    $commentsVisible = $commentCount >= SS_MIN_RESPONSES && $responses >= SS_MIN_RESPONSES;
    $comments = [];
    $visibleCommentCount = 0;
    if ($commentsVisible) {
        $visibleCommentCount = $commentCount;
        $stmt = $conn->prepare(
            "SELECT comment_text, DATE_FORMAT(created_at, '%Y-%m') AS month
             FROM tbl_service_survey_comments
             ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare service survey comments: ' . $conn->error);
        }
        $stmt->bind_param('i', $offset);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Could not load service survey comments: ' . $stmt->error);
        }
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) {
            $comments[] = ['text' => (string)$row['comment_text'], 'month' => (string)$row['month']];
        }
        $stmt->close();
    }

    return [
        'enabled' => $config['enabled'],
        'comment_enabled' => $config['comment_enabled'],
        'responses' => $responses,
        'min_responses' => SS_MIN_RESPONSES,
        'ready' => $responses >= SS_MIN_RESPONSES && $ratingCount > 0,
        'overall' => ($responses >= SS_MIN_RESPONSES && $ratingCount > 0)
            ? round($ratingTotal / $ratingCount, 2)
            : null,
        'questions' => $questions,
        'comments' => $comments,
        'comment_total' => $visibleCommentCount,
        'limits' => [
            'min_questions' => SS_MIN_QUESTIONS,
            'max_questions' => SS_MAX_QUESTIONS,
            'question_min' => SS_QUESTION_MIN,
            'question_max' => SS_QUESTION_MAX,
        ],
    ];
}

function ss_token(string $type, string $transactionKey, string $facultyId): string
{
    $payload = $type . '.' . rtrim(strtr(base64_encode($transactionKey), '+/', '-_'), '=');
    $secret = (string)($_SESSION['csrf_token'] ?? '');
    return $payload . '.' . hash_hmac('sha256', $payload . "\0" . $facultyId, $secret);
}

function ss_parse_token(string $token, string $facultyId): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3 || !in_array($parts[0], ['equipment', 'room'], true)) {
        return null;
    }
    $secret = (string)($_SESSION['csrf_token'] ?? '');
    $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1] . "\0" . $facultyId, $secret);
    if ($secret === '' || !hash_equals($expected, $parts[2])) {
        return null;
    }
    $encoded = strtr($parts[1], '-_', '+/');
    $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
    $transactionKey = base64_decode($encoded, true);
    if (!is_string($transactionKey) || $transactionKey === '' || strlen($transactionKey) > 64) {
        return null;
    }
    return ['type' => $parts[0], 'transaction_key' => $transactionKey];
}

function ss_completed_transactions(mysqli $conn, string $facultyId): array
{
    $transactions = [];

    $equipment = $conn->prepare(
        "SELECT
             CASE WHEN COALESCE(r.batch_id, '') <> '' THEN CONCAT('batch:', r.batch_id)
                  ELSE CONCAT('request:', r.id) END AS transaction_key,
             GROUP_CONCAT(DISTINCT r.equipment_name ORDER BY r.equipment_name SEPARATOR ', ') AS label,
             MAX(COALESCE(r.returned_at, TIMESTAMP(r.return_date, COALESCE(r.return_time, '23:59:59')))) AS completed_at
         FROM tbl_requests r
         WHERE r.faculty_id = ? AND r.status = 'Returned'
           AND (COALESCE(r.batch_id, '') = '' OR NOT EXISTS (
               SELECT 1 FROM tbl_requests pending
               WHERE pending.batch_id = r.batch_id AND pending.status IN ('Waiting', 'Approved', 'On Loan', 'Overdue')
           ))
         GROUP BY CASE WHEN COALESCE(r.batch_id, '') <> '' THEN CONCAT('batch:', r.batch_id)
                       ELSE CONCAT('request:', r.id) END"
    );
    if (!$equipment) {
        throw new RuntimeException('Could not prepare completed equipment transactions: ' . $conn->error);
    }
    $equipment->bind_param('s', $facultyId);
    if (!$equipment->execute()) {
        $equipment->close();
        throw new RuntimeException('Could not load completed equipment transactions: ' . $equipment->error);
    }
    $rows = $equipment->get_result();
    while ($row = $rows->fetch_assoc()) {
        $transactions[] = [
            'type' => 'equipment',
            'transaction_key' => (string)$row['transaction_key'],
            'label' => (string)$row['label'],
            'completed_at' => (string)$row['completed_at'],
        ];
    }
    $equipment->close();

    $rooms = $conn->prepare(
        "SELECT rr.id, rr.purpose, rm.room_name,
                TIMESTAMP(rr.reservation_date, rr.end_time) AS completed_at
         FROM tbl_room_reservations rr
         LEFT JOIN tbl_rooms rm ON rm.room_id = rr.room_id
         WHERE rr.faculty_id = ? AND rr.status = 'Approved'
           AND TIMESTAMP(rr.reservation_date, rr.end_time) <= NOW()"
    );
    if (!$rooms) {
        throw new RuntimeException('Could not prepare completed room reservations: ' . $conn->error);
    }
    $rooms->bind_param('s', $facultyId);
    if (!$rooms->execute()) {
        $rooms->close();
        throw new RuntimeException('Could not load completed room reservations: ' . $rooms->error);
    }
    $rows = $rooms->get_result();
    while ($row = $rows->fetch_assoc()) {
        $roomName = trim((string)($row['room_name'] ?? ''));
        $purpose = trim((string)$row['purpose']);
        $transactions[] = [
            'type' => 'room',
            'transaction_key' => (string)$row['id'],
            'label' => $roomName === '' ? $purpose : $purpose . ' - ' . $roomName,
            'completed_at' => (string)$row['completed_at'],
        ];
    }
    $rooms->close();

    usort($transactions, static function (array $a, array $b): int {
        return strcmp($b['completed_at'], $a['completed_at']);
    });
    return $transactions;
}

function ss_is_consumed(mysqli $conn, string $type, string $transactionKey): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM tbl_service_survey_completions WHERE source_type = ? AND transaction_key = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare survey completion lookup: ' . $conn->error);
    }
    $stmt->bind_param('ss', $type, $transactionKey);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not check survey completion: ' . $stmt->error);
    }
    $stmt->store_result();
    $consumed = $stmt->num_rows > 0;
    $stmt->close();
    return $consumed;
}

function ss_claim_presentation(mysqli $conn, string $type, string $transactionKey): bool
{
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO tbl_service_survey_completions (source_type, transaction_key, answered)
         VALUES (?, ?, 0)'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare survey presentation claim: ' . $conn->error);
    }
    $stmt->bind_param('ss', $type, $transactionKey);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not claim survey presentation: ' . $stmt->error);
    }
    $claimed = $stmt->affected_rows === 1;
    $stmt->close();
    return $claimed;
}

function ss_find_owned_transaction(mysqli $conn, string $type, string $transactionKey, string $facultyId): ?array
{
    if ($type === 'room') {
        if (!ctype_digit($transactionKey) || (int)$transactionKey < 1) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT rr.id, rr.purpose, rm.room_name
             FROM tbl_room_reservations rr
             LEFT JOIN tbl_rooms rm ON rm.room_id = rr.room_id
             WHERE rr.id = ? AND rr.faculty_id = ? AND rr.status = 'Approved'
               AND TIMESTAMP(rr.reservation_date, rr.end_time) <= NOW()
             LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare room transaction check: ' . $conn->error);
        }
        $id = (int)$transactionKey;
        $stmt->bind_param('is', $id, $facultyId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Could not check room transaction: ' . $stmt->error);
        }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $roomName = trim((string)($row['room_name'] ?? ''));
        $purpose = trim((string)$row['purpose']);
        return ['label' => $roomName === '' ? $purpose : $purpose . ' - ' . $roomName];
    }

    if ($type !== 'equipment') {
        return null;
    }
    if (strncmp($transactionKey, 'request:', 8) === 0) {
        $idText = substr($transactionKey, 8);
        if (!ctype_digit($idText) || (int)$idText < 1) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT equipment_name
             FROM tbl_requests
             WHERE id = ? AND faculty_id = ? AND status = 'Returned' AND COALESCE(batch_id, '') = ''
             LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare equipment transaction check: ' . $conn->error);
        }
        $id = (int)$idText;
        $stmt->bind_param('is', $id, $facultyId);
    } elseif (strncmp($transactionKey, 'batch:', 6) === 0) {
        $batchId = substr($transactionKey, 6);
        if ($batchId === '' || strlen($batchId) > 58) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT GROUP_CONCAT(DISTINCT r.equipment_name ORDER BY r.equipment_name SEPARATOR ', ') AS label
             FROM tbl_requests r
             WHERE r.batch_id = ? AND r.faculty_id = ? AND r.status = 'Returned'
               AND NOT EXISTS (
                   SELECT 1 FROM tbl_requests pending
                   WHERE pending.batch_id = r.batch_id AND pending.status IN ('Waiting', 'Approved', 'On Loan', 'Overdue')
               )
             GROUP BY r.batch_id
             LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare equipment batch check: ' . $conn->error);
        }
        $stmt->bind_param('ss', $batchId, $facultyId);
    } else {
        return null;
    }
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not check equipment transaction: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $label = (string)($row['label'] ?? $row['equipment_name'] ?? '');
    return $label === '' ? null : ['label' => $label];
}
