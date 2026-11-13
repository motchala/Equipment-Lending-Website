<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../core/survey.php';
ss_set_exception_handler('survey-submit');

if (empty($_SESSION['faculty_id'])) {
    ss_fail(401, 'Faculty sign-in is required.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_fail(405, 'Method not allowed.');
}

$body = ss_json_body();
ss_verify_csrf($body);
$token = (string)($body['key'] ?? '');
if ($token === '') {
    ss_fail(400, 'The survey transaction is missing.');
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');
ss_ensure_schema($conn);
$config = ss_config($conn);
if (!$config['enabled']) {
    ss_fail(409, 'The survey is turned off.', 'disabled');
}

$facultyId = (string)$_SESSION['faculty_id'];
$parsed = ss_parse_token($token, $facultyId);
if ($parsed === null) {
    ss_fail(400, 'This survey is no longer available.', 'not_eligible');
}
$owned = ss_find_owned_transaction($conn, $parsed['type'], $parsed['transaction_key'], $facultyId);
if ($owned === null) {
    ss_fail(409, 'This transaction is no longer eligible for a survey.', 'not_eligible');
}

$skipped = !empty($body['skipped']);
$questions = ss_questions($conn);
$ratings = $body['ratings'] ?? null;
if (!$skipped) {
    if (!is_array($ratings) || count($ratings) !== count($questions)) {
        ss_fail(409, 'The survey was just updated. Please answer the new questions.', 'stale');
    }
    foreach ($questions as $question) {
        if (!array_key_exists((string)$question['id'], $ratings) && !array_key_exists($question['id'], $ratings)) {
            ss_fail(409, 'The survey was just updated. Please answer the new questions.', 'stale');
        }
    }
    foreach ($questions as $question) {
        $value = $ratings[(string)$question['id']] ?? $ratings[$question['id']] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            ss_fail(422, 'Please answer every question.');
        }
        $value = (int)$value;
        if ($value < 1 || $value > 5) {
            ss_fail(422, 'Ratings must be between 1 and 5.');
        }
        $ratings[(string)$question['id']] = $value;
    }
}

$comment = '';
if (!$skipped && $config['comment_enabled']) {
    if (isset($body['comment']) && !is_string($body['comment'])) {
        ss_fail(422, 'The comment must be plain text.');
    }
    $comment = trim((string)($body['comment'] ?? ''));
    $commentLength = function_exists('mb_strlen') ? mb_strlen($comment, 'UTF-8') : strlen($comment);
    if ($commentLength > SS_COMMENT_MAX) {
        ss_fail(422, 'Comments must be no more than ' . SS_COMMENT_MAX . ' characters.');
    }
}

$periodId = null;
if (!$skipped && ss_has_semester($config)) {
    $period = ss_period_for_date($config, date('Y-m-d'));
    if ($period !== null) {
        ss_ensure_semester_periods($conn, $config);
        $periodRecord = ss_period_record($conn, $period);
        if ($periodRecord === null) {
            throw new RuntimeException('The active survey period could not be created.');
        }
        $periodId = $periodRecord['id'];
    }
}

$conn->begin_transaction();
try {
    $insert = $conn->prepare(
        'INSERT IGNORE INTO tbl_service_survey_completions (source_type, transaction_key, answered)
         VALUES (?, ?, ?)'
    );
    if (!$insert) {
        throw new RuntimeException('Could not prepare survey completion write: ' . $conn->error);
    }
    $answered = $skipped ? 0 : 1;
    $insert->bind_param('ssi', $parsed['type'], $parsed['transaction_key'], $answered);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException('Could not save survey completion: ' . $insert->error);
    }
    $inserted = $insert->affected_rows === 1;
    $insert->close();
    if (!$inserted) {
        $existing = $conn->prepare(
            'SELECT answered FROM tbl_service_survey_completions
             WHERE source_type = ? AND transaction_key = ? FOR UPDATE'
        );
        if (!$existing) {
            throw new RuntimeException('Could not prepare survey completion check: ' . $conn->error);
        }
        $existing->bind_param('ss', $parsed['type'], $parsed['transaction_key']);
        if (!$existing->execute()) {
            $existing->close();
            throw new RuntimeException('Could not check prior survey response: ' . $existing->error);
        }
        $prior = $existing->get_result()->fetch_assoc();
        $existing->close();
        if (!$prior || (int)$prior['answered'] === 1) {
            $conn->rollback();
            ss_fail(409, 'This survey has already been completed.', 'already');
        }
        if ($skipped) {
            $conn->commit();
            ss_json(['status' => 'success']);
        }

        $markAnswered = $conn->prepare(
            'UPDATE tbl_service_survey_completions SET answered = 1
             WHERE source_type = ? AND transaction_key = ? AND answered = 0'
        );
        if (!$markAnswered) {
            throw new RuntimeException('Could not prepare survey response marker: ' . $conn->error);
        }
        $markAnswered->bind_param('ss', $parsed['type'], $parsed['transaction_key']);
        if (!$markAnswered->execute() || $markAnswered->affected_rows !== 1) {
            $markAnswered->close();
            $conn->rollback();
            ss_fail(409, 'This survey has already been completed.', 'already');
        }
        $markAnswered->close();
    }

    if (!$skipped) {
        $update = $conn->prepare(
            'UPDATE tbl_service_survey_questions
             SET responses = responses + 1,
                 total_score = total_score + ?,
                 rating_1 = rating_1 + ?,
                 rating_2 = rating_2 + ?,
                 rating_3 = rating_3 + ?,
                 rating_4 = rating_4 + ?,
                 rating_5 = rating_5 + ?
             WHERE id = ? AND active = 1'
        );
        if (!$update) {
            throw new RuntimeException('Could not prepare rating update: ' . $conn->error);
        }
        foreach ($questions as $question) {
            $rating = (int)$ratings[(string)$question['id']];
            $zero = 0;
            $stars = [$zero, $zero, $zero, $zero, $zero];
            $stars[$rating - 1] = 1;
            $questionId = (int)$question['id'];
            $update->bind_param(
                'iiiiiii',
                $rating,
                $stars[0],
                $stars[1],
                $stars[2],
                $stars[3],
                $stars[4],
                $questionId
            );
            if (!$update->execute() || $update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('Could not save a survey rating: ' . $update->error);
            }
        }
        $update->close();

        if ($periodId !== null) {
            $periodTotalScore = 0;
            foreach ($questions as $index => $question) {
                $rating = (int)$ratings[(string)$question['id']];
                $periodTotalScore += $rating;
                ss_record_period_answer(
                    $conn,
                    $periodId,
                    (int)$question['id'],
                    (string)$question['text'],
                    $index + 1,
                    $rating
                );
            }
            ss_record_period_response($conn, $periodId, count($questions), $periodTotalScore);
        }

        if ($comment !== '') {
            $commentInsert = $periodId === null
                ? $conn->prepare('INSERT INTO tbl_service_survey_comments (comment_text) VALUES (?)')
                : $conn->prepare('INSERT INTO tbl_service_survey_comments (period_id, comment_text) VALUES (?, ?)');
            if (!$commentInsert) {
                throw new RuntimeException('Could not prepare anonymous comment write: ' . $conn->error);
            }
            if ($periodId === null) {
                $commentInsert->bind_param('s', $comment);
            } else {
                $commentInsert->bind_param('is', $periodId, $comment);
            }
            if (!$commentInsert->execute()) {
                $commentInsert->close();
                throw new RuntimeException('Could not save anonymous comment: ' . $commentInsert->error);
            }
            $commentInsert->close();
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

ss_json(['status' => 'success']);
