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
            semester_start DATE DEFAULT NULL,
            semester_end DATE DEFAULT NULL,
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
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_periods (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            semester_start DATE NOT NULL,
            semester_end DATE NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            response_count INT UNSIGNED NOT NULL DEFAULT 0,
            rating_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_score BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_service_survey_period (semester_start, semester_end, period_start),
            KEY idx_service_survey_semester (semester_start, semester_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_period_questions (
            period_id BIGINT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            question_version CHAR(64) NOT NULL,
            question_text VARCHAR(160) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            responses INT UNSIGNED NOT NULL DEFAULT 0,
            total_score BIGINT UNSIGNED NOT NULL DEFAULT 0,
            rating_1 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_2 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_3 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_4 INT UNSIGNED NOT NULL DEFAULT 0,
            rating_5 INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (period_id, question_id, question_version),
            KEY idx_service_survey_period_question (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tbl_service_survey_comments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            period_id BIGINT UNSIGNED DEFAULT NULL,
            comment_text VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_service_survey_comment_date (created_at),
            KEY idx_service_survey_comment_period (period_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($ddl as $sql) {
        if (!$conn->query($sql)) {
            throw new RuntimeException('Could not create service survey tables: ' . $conn->error);
        }
    }

    ss_ensure_column($conn, 'tbl_service_survey_config', 'semester_start', 'DATE DEFAULT NULL');
    ss_ensure_column($conn, 'tbl_service_survey_config', 'semester_end', 'DATE DEFAULT NULL');
    ss_ensure_column($conn, 'tbl_service_survey_comments', 'period_id', 'BIGINT UNSIGNED DEFAULT NULL');
    ss_ensure_index($conn, 'tbl_service_survey_comments', 'idx_service_survey_comment_period', '(period_id)');

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

function ss_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
{
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$result) {
        throw new RuntimeException('Could not inspect service survey schema: ' . $conn->error);
    }
    $exists = $result->num_rows > 0;
    $result->free();
    if ($exists || $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
        return;
    }

    $checkAgain = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$checkAgain || $checkAgain->num_rows === 0) {
        if ($checkAgain) {
            $checkAgain->free();
        }
        throw new RuntimeException('Could not add service survey schema field: ' . $conn->error);
    }
    $checkAgain->free();
}

function ss_ensure_index(mysqli $conn, string $table, string $index, string $columns): void
{
    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
    if (!$result) {
        throw new RuntimeException('Could not inspect service survey indexes: ' . $conn->error);
    }
    $exists = $result->num_rows > 0;
    $result->free();
    if ($exists || $conn->query("ALTER TABLE `$table` ADD INDEX `$index` $columns")) {
        return;
    }

    $checkAgain = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
    if (!$checkAgain || $checkAgain->num_rows === 0) {
        if ($checkAgain) {
            $checkAgain->free();
        }
        throw new RuntimeException('Could not add service survey index: ' . $conn->error);
    }
    $checkAgain->free();
}

function ss_config(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT enabled, comment_enabled, semester_start, semester_end
         FROM tbl_service_survey_config WHERE id = 1 LIMIT 1'
    );
    if (!$result) {
        throw new RuntimeException('Could not read service survey settings: ' . $conn->error);
    }
    $row = $result->fetch_assoc() ?: ['enabled' => 1, 'comment_enabled' => 1];
    $result->free();
    return [
        'enabled' => (bool)$row['enabled'],
        'comment_enabled' => (bool)$row['comment_enabled'],
        'semester_start' => !empty($row['semester_start']) ? (string)$row['semester_start'] : null,
        'semester_end' => !empty($row['semester_end']) ? (string)$row['semester_end'] : null,
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
            'sort_order' => (int)$row['sort_order'],
            'responses' => $responses,
            'average' => $responses > 0 ? round((int)$row['total_score'] / $responses, 2) : null,
            'counts' => $counts,
            'total_score' => (int)$row['total_score'],
        ];
    }
    $result->free();
    return $questions;
}

function ss_parse_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $date;
}

function ss_has_semester(array $config): bool
{
    $start = ss_parse_date((string)($config['semester_start'] ?? ''));
    $end = ss_parse_date((string)($config['semester_end'] ?? ''));
    return $start !== null && $end !== null && $start <= $end;
}

function ss_period_for_date(array $config, string $dateValue): ?array
{
    if (!ss_has_semester($config)) {
        return null;
    }
    $semesterStart = ss_parse_date((string)$config['semester_start']);
    $semesterEnd = ss_parse_date((string)$config['semester_end']);
    $date = ss_parse_date($dateValue);
    if (!$semesterStart || !$semesterEnd || !$date || $date < $semesterStart || $date > $semesterEnd) {
        return null;
    }

    $elapsedDays = (int)$semesterStart->diff($date)->format('%a');
    $periodStart = $semesterStart->modify('+' . (intdiv($elapsedDays, 14) * 14) . ' days');
    $periodEnd = $periodStart->modify('+13 days');
    if ($periodEnd > $semesterEnd) {
        $periodEnd = $semesterEnd;
    }
    return [
        'semester_start' => $semesterStart->format('Y-m-d'),
        'semester_end' => $semesterEnd->format('Y-m-d'),
        'period_start' => $periodStart->format('Y-m-d'),
        'period_end' => $periodEnd->format('Y-m-d'),
    ];
}

function ss_ensure_semester_periods(mysqli $conn, array $config): void
{
    if (!ss_has_semester($config)) {
        return;
    }
    $start = ss_parse_date((string)$config['semester_start']);
    $end = ss_parse_date((string)$config['semester_end']);
    if (!$start || !$end) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT IGNORE INTO tbl_service_survey_periods
         (semester_start, semester_end, period_start, period_end) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare survey period creation: ' . $conn->error);
    }
    for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+14 days')) {
        $periodEnd = $cursor->modify('+13 days');
        if ($periodEnd > $end) {
            $periodEnd = $end;
        }
        $semesterStart = $start->format('Y-m-d');
        $semesterEnd = $end->format('Y-m-d');
        $periodStart = $cursor->format('Y-m-d');
        $periodEndValue = $periodEnd->format('Y-m-d');
        $stmt->bind_param('ssss', $semesterStart, $semesterEnd, $periodStart, $periodEndValue);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Could not create survey period: ' . $error);
        }
    }
    $stmt->close();
}

function ss_period_record(mysqli $conn, array $period): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, response_count, rating_count, total_score
         FROM tbl_service_survey_periods
         WHERE semester_start = ? AND semester_end = ? AND period_start = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare survey period lookup: ' . $conn->error);
    }
    $stmt->bind_param('sss', $period['semester_start'], $period['semester_end'], $period['period_start']);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not read survey period: ' . $error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'responses' => (int)$row['response_count'],
        'rating_count' => (int)$row['rating_count'],
        'total_score' => (int)$row['total_score'],
    ];
}

function ss_semester_totals(mysqli $conn, string $semesterStart, string $semesterEnd): array
{
    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(response_count), 0) AS responses,
                COALESCE(SUM(rating_count), 0) AS rating_count,
                COALESCE(SUM(total_score), 0) AS total_score
         FROM tbl_service_survey_periods
         WHERE semester_start = ? AND semester_end = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare semester survey summary: ' . $conn->error);
    }
    $stmt->bind_param('ss', $semesterStart, $semesterEnd);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not read semester survey summary: ' . $error);
    }
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return [
        'responses' => (int)($row['responses'] ?? 0),
        'rating_count' => (int)($row['rating_count'] ?? 0),
        'total_score' => (int)($row['total_score'] ?? 0),
    ];
}

function ss_period_questions(mysqli $conn, ?int $periodId, array $activeQuestions): array
{
    $questions = [];
    $seen = [];
    if ($periodId !== null) {
        $stmt = $conn->prepare(
            'SELECT question_id, question_version, question_text, sort_order, responses, total_score,
                    rating_1, rating_2, rating_3, rating_4, rating_5
             FROM tbl_service_survey_period_questions
             WHERE period_id = ?
             ORDER BY sort_order ASC, question_id ASC, question_text ASC'
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare period question summary: ' . $conn->error);
        }
        $stmt->bind_param('i', $periodId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Could not read period question summary: ' . $error);
        }
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) {
            $questionId = (int)$row['question_id'];
            $version = (string)$row['question_version'];
            $responseCount = (int)$row['responses'];
            $counts = [];
            for ($star = 1; $star <= 5; $star++) {
                $counts[] = (int)$row['rating_' . $star];
            }
            $questions[] = [
                'id' => $questionId,
                'text' => (string)$row['question_text'],
                'sort_order' => (int)$row['sort_order'],
                'responses' => $responseCount,
                'average' => $responseCount > 0 ? round((int)$row['total_score'] / $responseCount, 2) : null,
                'counts' => $counts,
                'total_score' => (int)$row['total_score'],
            ];
            $seen[$questionId . ':' . $version] = true;
        }
        $stmt->close();
    }

    foreach ($activeQuestions as $question) {
        $version = hash('sha256', (string)$question['text']);
        $key = (int)$question['id'] . ':' . $version;
        if (isset($seen[$key])) {
            continue;
        }
        $questions[] = [
            'id' => (int)$question['id'],
            'text' => (string)$question['text'],
            'sort_order' => (int)$question['sort_order'],
            'responses' => 0,
            'average' => null,
            'counts' => [0, 0, 0, 0, 0],
            'total_score' => 0,
        ];
    }

    usort($questions, static function (array $a, array $b): int {
        return ($a['sort_order'] <=> $b['sort_order'])
            ?: ($a['id'] <=> $b['id'])
            ?: strcmp($a['text'], $b['text']);
    });
    return $questions;
}

function ss_reset_question_aggregates(mysqli $conn, int $questionId): void
{
    $read = $conn->prepare(
        'SELECT period_id, SUM(responses) AS rating_count, SUM(total_score) AS total_score
         FROM tbl_service_survey_period_questions
         WHERE question_id = ?
         GROUP BY period_id'
    );
    if (!$read) {
        throw new RuntimeException('Could not prepare period question reset: ' . $conn->error);
    }
    $read->bind_param('i', $questionId);
    if (!$read->execute()) {
        $error = $read->error;
        $read->close();
        throw new RuntimeException('Could not read period question totals to reset: ' . $error);
    }
    $periods = [];
    $rows = $read->get_result();
    while ($row = $rows->fetch_assoc()) {
        $periods[] = [
            'period_id' => (int)$row['period_id'],
            'rating_count' => (int)($row['rating_count'] ?? 0),
            'total_score' => (int)($row['total_score'] ?? 0),
        ];
    }
    $read->close();

    if ($periods !== []) {
        $update = $conn->prepare(
            'UPDATE tbl_service_survey_periods
             SET rating_count = GREATEST(rating_count - ?, 0),
                 total_score = GREATEST(total_score - ?, 0)
             WHERE id = ?'
        );
        if (!$update) {
            throw new RuntimeException('Could not prepare period reset update: ' . $conn->error);
        }
        foreach ($periods as $entry) {
            $update->bind_param('iii', $entry['rating_count'], $entry['total_score'], $entry['period_id']);
            if (!$update->execute()) {
                $error = $update->error;
                $update->close();
                throw new RuntimeException('Could not subtract cleared question totals: ' . $error);
            }
        }
        $update->close();
    }

    $delete = $conn->prepare('DELETE FROM tbl_service_survey_period_questions WHERE question_id = ?');
    if (!$delete) {
        throw new RuntimeException('Could not prepare period question delete: ' . $conn->error);
    }
    $delete->bind_param('i', $questionId);
    if (!$delete->execute()) {
        $error = $delete->error;
        $delete->close();
        throw new RuntimeException('Could not remove cleared question periods: ' . $error);
    }
    $delete->close();
}

function ss_record_period_answer(
    mysqli $conn,
    int $periodId,
    int $questionId,
    string $questionText,
    int $sortOrder,
    int $rating
): void {
    $version = hash('sha256', $questionText);
    $zero = 0;
    $stars = [$zero, $zero, $zero, $zero, $zero];
    $stars[$rating - 1] = 1;
    $stmt = $conn->prepare(
        'INSERT INTO tbl_service_survey_period_questions
         (period_id, question_id, question_version, question_text, sort_order, responses, total_score,
          rating_1, rating_2, rating_3, rating_4, rating_5)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             sort_order = VALUES(sort_order),
             responses = responses + 1,
             total_score = total_score + VALUES(total_score),
             rating_1 = rating_1 + VALUES(rating_1),
             rating_2 = rating_2 + VALUES(rating_2),
             rating_3 = rating_3 + VALUES(rating_3),
             rating_4 = rating_4 + VALUES(rating_4),
             rating_5 = rating_5 + VALUES(rating_5)'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare period rating write: ' . $conn->error);
    }
    $stmt->bind_param(
        'iissiiiiiii',
        $periodId,
        $questionId,
        $version,
        $questionText,
        $sortOrder,
        $rating,
        $stars[0],
        $stars[1],
        $stars[2],
        $stars[3],
        $stars[4]
    );
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not save period rating: ' . $error);
    }
    $stmt->close();
}

function ss_record_period_response(mysqli $conn, int $periodId, int $ratingCount, int $totalScore): void
{
    $stmt = $conn->prepare(
        'UPDATE tbl_service_survey_periods
         SET response_count = response_count + 1,
             rating_count = rating_count + ?,
             total_score = total_score + ?
         WHERE id = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare period response update: ' . $conn->error);
    }
    $stmt->bind_param('iii', $ratingCount, $totalScore, $periodId);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not update period response count: ' . $error);
    }
    $stmt->close();
}

function ss_results(mysqli $conn, int $offset = 0): array
{
    $config = ss_config($conn);
    $editorQuestions = ss_questions($conn);
    $responseResult = $conn->query('SELECT COUNT(*) AS n FROM tbl_service_survey_completions WHERE answered = 1');
    if (!$responseResult) {
        throw new RuntimeException('Could not count service survey responses: ' . $conn->error);
    }
    $allTimeResponses = (int)($responseResult->fetch_assoc()['n'] ?? 0);
    $responseResult->free();

    $today = date('Y-m-d');
    $hasSemester = ss_has_semester($config);
    $period = null;
    $periodRow = null;
    $semester = [
        'configured' => false,
        'start' => null,
        'end' => null,
        'responses' => 0,
        'ready' => false,
        'overall' => null,
        'legacy_responses' => $allTimeResponses,
    ];
    if ($hasSemester) {
        ss_ensure_semester_periods($conn, $config);
        $period = ss_period_for_date($config, $today);
        $periodRow = $period ? ss_period_record($conn, $period) : null;
        $questions = ss_period_questions($conn, $periodRow['id'] ?? null, $editorQuestions);
        $periodTotals = ss_semester_totals(
            $conn,
            (string)$config['semester_start'],
            (string)$config['semester_end']
        );
        $periodizedResult = $conn->query(
            'SELECT COALESCE(SUM(response_count), 0) AS responses FROM tbl_service_survey_periods'
        );
        if (!$periodizedResult) {
            throw new RuntimeException('Could not count periodized survey responses: ' . $conn->error);
        }
        $periodizedResponses = (int)($periodizedResult->fetch_assoc()['responses'] ?? 0);
        $periodizedResult->free();
        $semesterReady = $periodTotals['responses'] >= SS_MIN_RESPONSES && $periodTotals['rating_count'] > 0;
        $semester = [
            'configured' => true,
            'start' => $config['semester_start'],
            'end' => $config['semester_end'],
            'responses' => $periodTotals['responses'],
            'ready' => $semesterReady,
            'overall' => $semesterReady
                ? round($periodTotals['total_score'] / $periodTotals['rating_count'], 2)
                : null,
            'legacy_responses' => max(0, $allTimeResponses - $periodizedResponses),
        ];
        $responses = $periodRow['responses'] ?? 0;
        $currentPeriod = [
            'configured' => true,
            'active' => $period !== null,
            'start' => $period['period_start'] ?? null,
            'end' => $period['period_end'] ?? null,
            'responses' => $responses,
        ];
    } else {
        $questions = $editorQuestions;
        $responses = $allTimeResponses;
        $currentPeriod = [
            'configured' => false,
            'active' => false,
            'start' => null,
            'end' => null,
            'responses' => $responses,
        ];
    }

    $ratingCount = 0;
    $ratingTotal = 0;
    foreach ($questions as &$question) {
        $ratingCount += $question['responses'];
        $ratingTotal += $question['total_score'];
        if ($question['responses'] < SS_MIN_RESPONSES) {
            $question['average'] = null;
            $question['counts'] = null;
        }
        unset($question['total_score'], $question['sort_order']);
    }
    unset($question);

    if ($hasSemester) {
        $commentCountStmt = $conn->prepare(
            'SELECT COUNT(*) AS n
             FROM tbl_service_survey_comments c
             INNER JOIN tbl_service_survey_periods p ON p.id = c.period_id
             WHERE p.semester_start = ? AND p.semester_end = ?'
        );
        if (!$commentCountStmt) {
            throw new RuntimeException('Could not prepare semester comment count: ' . $conn->error);
        }
        $commentCountStmt->bind_param('ss', $config['semester_start'], $config['semester_end']);
        if (!$commentCountStmt->execute()) {
            $error = $commentCountStmt->error;
            $commentCountStmt->close();
            throw new RuntimeException('Could not count semester comments: ' . $error);
        }
        $commentCount = (int)($commentCountStmt->get_result()->fetch_assoc()['n'] ?? 0);
        $commentCountStmt->close();
    } else {
        $commentCountResult = $conn->query('SELECT COUNT(*) AS n FROM tbl_service_survey_comments');
        if (!$commentCountResult) {
            throw new RuntimeException('Could not count service survey comments: ' . $conn->error);
        }
        $commentCount = (int)($commentCountResult->fetch_assoc()['n'] ?? 0);
        $commentCountResult->free();
    }

    // Reveal submitted comments once the same response threshold as rating summaries is met.
    $privacyResponses = $hasSemester ? $semester['responses'] : $allTimeResponses;
    $commentsVisible = $privacyResponses >= SS_MIN_RESPONSES;
    $comments = [];
    $visibleCommentCount = 0;
    if ($commentsVisible) {
        $visibleCommentCount = $commentCount;
        $stmt = $hasSemester
            ? $conn->prepare(
                "SELECT c.comment_text, DATE_FORMAT(c.created_at, '%Y-%m') AS month
                 FROM tbl_service_survey_comments c
                 INNER JOIN tbl_service_survey_periods p ON p.id = c.period_id
                 WHERE p.semester_start = ? AND p.semester_end = ?
                 ORDER BY c.created_at DESC, c.id DESC LIMIT 10 OFFSET ?"
            )
            : $conn->prepare(
                "SELECT comment_text, DATE_FORMAT(created_at, '%Y-%m') AS month
                 FROM tbl_service_survey_comments
                 ORDER BY created_at DESC, id DESC LIMIT 10 OFFSET ?"
            );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare service survey comments: ' . $conn->error);
        }
        if ($hasSemester) {
            $stmt->bind_param('ssi', $config['semester_start'], $config['semester_end'], $offset);
        } else {
            $stmt->bind_param('i', $offset);
        }
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Could not load service survey comments: ' . $error);
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
        'editor_questions' => $editorQuestions,
        'current_period' => $currentPeriod,
        'semester' => $semester,
        'comments' => $comments,
        'comment_total' => $visibleCommentCount,
        'comments_pending' => $commentCount > 0 && !$commentsVisible,
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
