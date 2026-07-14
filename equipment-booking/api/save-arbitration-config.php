<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/security-headers.php';

date_default_timezone_set('Asia/Manila');

// ── Output buffering + JSON header ───────────────────────────────────────────
ob_start();
header('Content-Type: application/json');

// ── Helper: send a JSON response and exit ────────────────────────────────────
function send_json(int $http_status, string $status, string $message): never
{
    ob_end_clean();
    http_response_code($http_status);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ── Session guard — require active admin session ──────────────────────────────
require_once __DIR__ . '/../../config/session.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    send_json(401, 'error', 'Unauthorized. Admin access required.');
}

// ── CSRF guard ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/csrf.php';
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    send_json(403, 'error', 'Invalid or expired session. Please refresh the page and try again.');
}

// ── Whitelist of valid config keys (exactly the eight seeded keys) ────────────
const VALID_CONFIG_KEYS = [
    'tie_break_window_seconds',
    'role_priority_director',
    'role_priority_adviser',
    'role_priority_faculty',
    'role_priority_student',
    'rule_overdue_block_enabled',
    'rule_duplicate_block_enabled',
    'rule_missing_doc_block_enabled',
];

// ── Parse POST payload ────────────────────────────────────────────────────────
$raw_config = $_POST['config'] ?? null;

if (!is_array($raw_config)) {
    send_json(400, 'error', 'Invalid request. config[] array is required.');
}

// ── Task 6.2: Validate that all submitted keys are whitelisted ────────────────
foreach (array_keys($raw_config) as $key) {
    if (!in_array($key, VALID_CONFIG_KEYS, true)) {
        send_json(400, 'error', "Invalid config key: {$key}.");
    }
}

// ── Database connection ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$conn->set_charset('utf8mb4');

// ── Task 6.3: Wrap all UPSERTs in a single transaction ───────────────────────
$conn->begin_transaction();

try {
    // ── Prepare the UPSERT statement for config key-value pairs ──────────────
    $upsert_sql = "INSERT INTO tbl_arbitration_config (config_key, config_value)
                        VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE
                        config_value = VALUES(config_value),
                        updated_at   = NOW()";

    $upsert_stmt = $conn->prepare($upsert_sql);

    if ($upsert_stmt === false) {
        throw new RuntimeException('Failed to prepare config UPSERT statement.');
    }

    // ── Execute one UPSERT per submitted config key ───────────────────────────
    foreach ($raw_config as $config_key => $config_value) {
        // Cast to string — config_value column is TEXT
        $config_key   = (string)$config_key;
        $config_value = (string)$config_value;

        $upsert_stmt->bind_param('ss', $config_key, $config_value);

        if (!$upsert_stmt->execute()) {
            throw new RuntimeException("Failed to save config key: {$config_key}.");
        }
    }

    $upsert_stmt->close();

    // ── Task 6.3: Commit the transaction ─────────────────────────────────────
    if (!$conn->commit()) {
        throw new RuntimeException('Transaction commit failed.');
    }
} catch (RuntimeException $e) {
    // ── Task 6.3: Rollback on any failure ────────────────────────────────────
    $conn->rollback();
    error_log('[save-arbitration-config] ' . $e->getMessage());
    send_json(500, 'error', 'Could not save settings. Please try again.');
}

$conn->close();

// ── Task 6.4: Return success response ────────────────────────────────────────
send_json(200, 'success', 'Arbitration settings saved.');
