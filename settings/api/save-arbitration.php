<?php

declare(strict_types=1);

/**
 * settings/api/save-arbitration.php
 *
 * Saves the rules on Settings → Arbitration (Super Admin only).
 *
 * POST, JSON body, CSRF token in the X-CSRF-Token header:
 *   { "settings": { "max_items_per_borrower": 3, "equip_day_start": "07:00",
 *                   "equip_durations": [1,2,3,6], "rule_overdue_block_enabled": true, ... } }
 *
 * Only the keys listed in ArbitrationConfig::SCHEMA are accepted. Each value is checked
 * (type, range, whole numbers, times on the hour) and the combination is checked too
 * (for example the latest return has to be after the earliest pickup).
 *
 * Success: { status: "success", changed: [keys], values: { every setting, typed } }
 * Failure: { status: "error", error: "first problem", errors: { key: "problem" } }
 *
 * New requests use the saved rules straight away. Requests already decided are not re-checked.
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[save-arbitration] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'error' => 'Something went wrong. Please try again.']);
});

function sa_fail(int $code, string $message, array $errors = []): void
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'error' => $message, 'errors' => (object)$errors]);
    exit;
}

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    sa_fail(401, 'Unauthorized.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sa_fail(405, 'Method not allowed.');
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
if (!function_exists('admin_is_super_admin')) {
    require_once __DIR__ . '/../../auth/core/admin-auth.php';
}
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    sa_fail(403, 'Only the Super Admin can change the arbitration rules.');
}

require_once __DIR__ . '/../../shared/core/arbitration-config.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['settings']) || !is_array($body['settings'])) {
    sa_fail(400, 'Nothing to save.');
}

// ── Check the values ──────────────────────────────────────────────────────
$checked = ArbitrationConfig::validate($body['settings']);
$errors  = $checked['errors'];
$clean   = $checked['clean'];

if (!$clean && !$errors) {
    sa_fail(400, 'Nothing to save.');
}

if (!$errors) {
    // The combination that would be stored must make sense too
    $merged = array_merge(ArbitrationConfig::all($conn, true), $clean);
    $errors = ArbitrationConfig::crossCheck($merged);
}
if ($errors) {
    sa_fail(422, (string)reset($errors), $errors);
}

// ── Save ──────────────────────────────────────────────────────────────────
try {
    $changed = ArbitrationConfig::save($conn, $clean);
} catch (Throwable $e) {
    error_log('[save-arbitration] ' . $e->getMessage());
    sa_fail(500, 'The settings could not be saved. Please try again.');
}

if ($changed) {
    $labels = [
        'rule_overdue_block_enabled'     => 'overdue block',
        'rule_missing_doc_block_enabled' => 'request letter requirement',
        'max_items_per_borrower'         => 'items per borrower',
        'max_borrow_days'                => 'longest loan',
        'max_extensions'                 => 'extensions per loan',
        'low_stock_threshold'            => 'low-stock warning',
        'equip_day_start'                => 'booking hours',
        'equip_day_end'                  => 'booking hours',
        'equip_max_advance_days'         => 'booking ahead limit',
        'equip_immediate_grace_min'      => 'same-day grace',
        'equip_cancel_cutoff_min'        => 'cancellation cutoff',
        'equip_durations'                => 'booking lengths',
        'room_max_active'                => 'room reservations per faculty',
        'room_max_advance_weeks'         => 'room booking-ahead limit',
        'room_cancel_cutoff_min'         => 'room cancellation cutoff',
        'room_same_day_auto'             => 'same-day room confirmation',
        'room_letter_required'           => 'room request letter requirement',
        'room_expiry_mode'               => 'undecided room requests',
    ];
    $names = [];
    foreach ($changed as $key) {
        $names[$labels[$key] ?? $key] = true;
    }
    admin_log_activity(
        $conn,
        'settings',
        'Updated arbitration rules: ' . implode(', ', array_keys($names)),
        null,
        ['action' => 'settings']
    );
}

echo json_encode([
    'status'  => 'success',
    'changed' => $changed,
    'values'  => ArbitrationConfig::all($conn, true),
]);
