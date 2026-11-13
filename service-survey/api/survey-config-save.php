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
ss_set_exception_handler('survey-config-save');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    ss_fail(401, 'Admin sign-in is required.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ss_fail(405, 'Method not allowed.');
}

$body = ss_json_body();
ss_verify_csrf($body);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/core/admin-auth.php';
$conn = getDB();
$conn->set_charset('utf8mb4');
ss_ensure_schema($conn);
if (!admin_is_super_admin($conn)) {
    ss_fail(403, 'Only the Super Admin can change survey settings.');
}

if (
    !isset($body['enabled'], $body['comment_enabled'])
    || !is_array($body['questions'])
    || !array_key_exists('semester_start', $body)
    || !array_key_exists('semester_end', $body)
) {
    ss_fail(400, 'The survey settings are incomplete.');
}
$toBool = static function ($value): ?int {
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
        return (int)$value;
    }
    return null;
};
$enabled = $toBool($body['enabled']);
$commentEnabled = $toBool($body['comment_enabled']);
if ($enabled === null || $commentEnabled === null) {
    ss_fail(422, 'Survey switches must be on or off.');
}

$semesterStartValue = $body['semester_start'];
$semesterEndValue = $body['semester_end'];
if (!is_string($semesterStartValue) || !is_string($semesterEndValue)) {
    ss_fail(422, 'Semester dates must be valid dates.');
}
$semesterStartValue = trim($semesterStartValue);
$semesterEndValue = trim($semesterEndValue);
if (($semesterStartValue === '') !== ($semesterEndValue === '')) {
    ss_fail(422, 'Enter both semester dates, or leave both blank to disable period reporting.');
}
$semesterStart = null;
$semesterEnd = null;
if ($semesterStartValue !== '') {
    $startDate = ss_parse_date($semesterStartValue);
    $endDate = ss_parse_date($semesterEndValue);
    if (!$startDate || !$endDate || $startDate > $endDate) {
        ss_fail(422, 'Semester start and end dates are invalid or out of order.');
    }
    $semesterStart = $startDate->format('Y-m-d');
    $semesterEnd = $endDate->format('Y-m-d');
}

$submitted = $body['questions'];
if (count($submitted) < SS_MIN_QUESTIONS || count($submitted) > SS_MAX_QUESTIONS) {
    ss_fail(422, 'The survey must have between ' . SS_MIN_QUESTIONS . ' and ' . SS_MAX_QUESTIONS . ' questions.');
}

$clean = [];
$seenIds = [];
foreach ($submitted as $index => $item) {
    if (!is_array($item)) {
        ss_fail(422, 'Each survey question must be valid.');
    }
    if (isset($item['text']) && !is_string($item['text'])) {
        ss_fail(422, 'Each survey question must be plain text.');
    }
    $text = trim(preg_replace('/\s+/u', ' ', (string)($item['text'] ?? '')) ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length < SS_QUESTION_MIN || $length > SS_QUESTION_MAX) {
        ss_fail(422, 'Each question must be between ' . SS_QUESTION_MIN . ' and ' . SS_QUESTION_MAX . ' characters.');
    }
    $id = $item['id'] ?? null;
    if ($id !== null) {
        $validId = is_int($id);
        if (is_string($id)) {
            $validId = ctype_digit($id);
        }
        if (!$validId || (int)$id < 1 || isset($seenIds[(int)$id])) {
            ss_fail(422, 'A survey question ID is invalid or repeated.');
        }
        $id = (int)$id;
        $seenIds[$id] = true;
    }
    $clean[] = ['id' => $id, 'text' => $text, 'sort_order' => $index + 1];
}

$existing = [];
foreach (ss_questions($conn) as $question) {
    $existing[$question['id']] = $question;
}
foreach ($clean as $item) {
    if ($item['id'] !== null && !isset($existing[$item['id']])) {
        ss_fail(422, 'A question no longer exists. Refresh and try again.');
    }
}

$affected = [];
$retainedIds = [];
$resetQuestionIds = [];
foreach ($clean as $item) {
    if ($item['id'] === null) {
        continue;
    }
    $retainedIds[$item['id']] = true;
    $old = $existing[$item['id']];
    if ($old['text'] !== $item['text'] && $old['responses'] > 0) {
        $affected[] = ['text' => $old['text'], 'responses' => $old['responses'], 'change' => 'wording'];
        $resetQuestionIds[$item['id']] = (int)$item['id'];
    }
}
foreach ($existing as $id => $old) {
    if (!isset($retainedIds[$id]) && $old['responses'] > 0) {
        $affected[] = ['text' => $old['text'], 'responses' => $old['responses'], 'change' => 'removed'];
        $resetQuestionIds[$id] = (int)$id;
    }
}
if ($affected && empty($body['confirm_reset'])) {
    ss_json(['status' => 'confirm', 'affected' => $affected]);
}

$conn->begin_transaction();
try {
    foreach ($resetQuestionIds as $questionId) {
        ss_reset_question_aggregates($conn, (int)$questionId);
    }
    $configUpdate = $conn->prepare(
        'UPDATE tbl_service_survey_config
         SET enabled = ?, comment_enabled = ?, semester_start = ?, semester_end = ?
         WHERE id = 1'
    );
    if (!$configUpdate) {
        throw new RuntimeException('Could not prepare survey settings update: ' . $conn->error);
    }
    $configUpdate->bind_param('iiss', $enabled, $commentEnabled, $semesterStart, $semesterEnd);
    if (!$configUpdate->execute()) {
        $configUpdate->close();
        throw new RuntimeException('Could not save survey settings: ' . $configUpdate->error);
    }
    $configUpdate->close();

    $zeroCounters = 'responses = 0, total_score = 0, rating_1 = 0, rating_2 = 0, rating_3 = 0, rating_4 = 0, rating_5 = 0';
    $update = $conn->prepare(
        'UPDATE tbl_service_survey_questions SET question_text = ?, sort_order = ?, active = 1 WHERE id = ?'
    );
    $reset = $conn->prepare(
        'UPDATE tbl_service_survey_questions SET ' . $zeroCounters . ', question_text = ?, sort_order = ?, active = 1 WHERE id = ?'
    );
    $insert = $conn->prepare(
        'INSERT INTO tbl_service_survey_questions (question_text, sort_order, active) VALUES (?, ?, 1)'
    );
    if (!$update || !$reset || !$insert) {
        throw new RuntimeException('Could not prepare survey question updates: ' . $conn->error);
    }
    foreach ($clean as $item) {
        if ($item['id'] === null) {
            $insert->bind_param('si', $item['text'], $item['sort_order']);
            if (!$insert->execute()) {
                throw new RuntimeException('Could not add a survey question: ' . $insert->error);
            }
            continue;
        }
        $id = $item['id'];
        $changed = $existing[$id]['text'] !== $item['text'];
        $target = $changed ? $reset : $update;
        $target->bind_param('sii', $item['text'], $item['sort_order'], $id);
        if (!$target->execute()) {
            throw new RuntimeException('Could not save a survey question: ' . $target->error);
        }
    }
    $update->close();
    $reset->close();
    $insert->close();

    foreach ($existing as $id => $old) {
        if (isset($retainedIds[$id])) {
            continue;
        }
        $delete = $conn->prepare(
            'UPDATE tbl_service_survey_questions SET active = 0, ' . $zeroCounters . ' WHERE id = ?'
        );
        if (!$delete) {
            throw new RuntimeException('Could not prepare removed question update: ' . $conn->error);
        }
        $delete->bind_param('i', $id);
        if (!$delete->execute()) {
            $delete->close();
            throw new RuntimeException('Could not remove a survey question: ' . $delete->error);
        }
        $delete->close();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

ss_json([
    'status' => 'success',
    'results' => ss_results($conn, 0),
]);
