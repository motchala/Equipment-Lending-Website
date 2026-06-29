<?php
require_once __DIR__ . '/../config/security-headers.php';

/**
 * ajax/save-arbitration-config.php
 *
 * AJAX endpoint — Persist Config Panel changes to tbl_arbitration_config.
 *
 * Accepts:
 *   POST config[]          (array)  — Associative array of config_key => config_value pairs.
 *                                     Keys must be one of the eight whitelisted config keys.
 *   POST config[high_value_items][] (array, optional) — List of item IDs to mark as
 *                                     high-value. All other items will be set to is_high_value = 0.
 *
 * Response: JSON { status: 'success'|'error', message: string }
 *
 * Requirements:
 *   11.5 — Persist updated configuration to tbl_arbitration_config.
 *   11.6 — Display confirmation message "Arbitration settings saved."
 *   14.5 — tbl_arbitration_config schema.
 *   14.6 — Seeded default config keys.
 */

declare(strict_types=1);

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
require_once __DIR__ . '/../config/session-config.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    send_json(401, 'error', 'Unauthorized. Admin access required.');
}

// ── CSRF guard ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/csrf.php';
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

// Separate high_value_items from the regular config keys
$high_value_item_ids = null;

if (array_key_exists('high_value_items', $raw_config)) {
    $raw_high_value = $raw_config['high_value_items'];
    // Normalise: may be an array of IDs or an empty string when nothing is selected
    if (is_array($raw_high_value)) {
        $high_value_item_ids = array_map('intval', $raw_high_value);
        // Filter out any non-positive IDs
        $high_value_item_ids = array_values(array_filter($high_value_item_ids, fn(int $id) => $id > 0));
    } else {
        // Submitted as empty / scalar — treat as "no items selected"
        $high_value_item_ids = [];
    }
    unset($raw_config['high_value_items']);
}

// ── Task 6.2: Validate that all submitted keys are whitelisted ────────────────
foreach (array_keys($raw_config) as $key) {
    if (!in_array($key, VALID_CONFIG_KEYS, true)) {
        send_json(400, 'error', "Invalid config key: {$key}.");
    }
}

// ── Database connection ───────────────────────────────────────────────────────
require_once __DIR__ . '/../config/db.php';
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

    // ── Handle high_value_items if submitted ──────────────────────────────────
    if ($high_value_item_ids !== null) {
        if (count($high_value_item_ids) > 0) {
            // Mark selected items as high-value
            $mark_stmt = $conn->prepare(
                'UPDATE tbl_inventory SET is_high_value = 1 WHERE item_id = ?'
            );

            if ($mark_stmt === false) {
                throw new RuntimeException('Failed to prepare high-value mark statement.');
            }

            foreach ($high_value_item_ids as $item_id) {
                $mark_stmt->bind_param('i', $item_id);

                if (!$mark_stmt->execute()) {
                    throw new RuntimeException("Failed to mark item {$item_id} as high-value.");
                }
            }

            $mark_stmt->close();

            // Clear high-value flag for all items NOT in the submitted list
            // Build a parameterised NOT IN clause
            $placeholders = implode(',', array_fill(0, count($high_value_item_ids), '?'));
            $clear_sql    = "UPDATE tbl_inventory
                                SET is_high_value = 0
                              WHERE item_id NOT IN ({$placeholders})";

            $clear_stmt = $conn->prepare($clear_sql);

            if ($clear_stmt === false) {
                throw new RuntimeException('Failed to prepare high-value clear statement.');
            }

            $types = str_repeat('i', count($high_value_item_ids));
            $clear_stmt->bind_param($types, ...$high_value_item_ids);

            if (!$clear_stmt->execute()) {
                throw new RuntimeException('Failed to clear high-value flags.');
            }

            $clear_stmt->close();
        } else {
            // No items selected — clear the flag on all inventory items
            $clear_all_stmt = $conn->prepare(
                'UPDATE tbl_inventory SET is_high_value = 0'
            );

            if ($clear_all_stmt === false) {
                throw new RuntimeException('Failed to prepare clear-all high-value statement.');
            }

            if (!$clear_all_stmt->execute()) {
                throw new RuntimeException('Failed to clear all high-value flags.');
            }

            $clear_all_stmt->close();
        }
    }

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
