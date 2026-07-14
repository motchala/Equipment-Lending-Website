<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// ── Admin session guard — must run before Content-Type or any DB query ────────
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

header('Content-Type: application/json');

// ── CSRF guard ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

// ── Database connection ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$conn->set_charset('utf8mb4');

// ── Input collection ──────────────────────────────────────────────────────────
$faculty_id          = trim((string)($_POST['faculty_id'] ?? ''));
$allow_org_borrowing = (int)($_POST['allow_org_borrowing'] ?? -1);

// ── Validate allow_org_borrowing — must be exactly 0 or 1 ────────────────────
if ($allow_org_borrowing !== 0 && $allow_org_borrowing !== 1) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid value for allow_org_borrowing. Must be 0 or 1.']);
    exit;
}

// ── Validate faculty_id — must be non-empty ───────────────────────────────────
if ($faculty_id === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'faculty_id is required.']);
    exit;
}

// ── Verify faculty_id exists in tbl_users ─────────────────────────────────────
// Also guard against migration not yet run — if allow_org_borrowing column
// is absent the UPDATE below would fail. Return 503 so the toggle JS can show
// a meaningful error instead of a generic network failure.
$_col_check = $conn->query("SHOW COLUMNS FROM tbl_users LIKE 'allow_org_borrowing'");
if (!$_col_check || $_col_check->num_rows === 0) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Feature migration not yet applied to this database. Please run the dual-borrowing-mode migration SQL.']);
    exit;
}

$sel_stmt = $conn->prepare(
    "SELECT faculty_id FROM tbl_users WHERE faculty_id = ? LIMIT 1"
);

if ($sel_stmt === false) {
    error_log('[toggle-org-borrowing] Failed to prepare existence check: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

$sel_stmt->bind_param('s', $faculty_id);

if (!$sel_stmt->execute()) {
    error_log('[toggle-org-borrowing] Failed to execute existence check: ' . $sel_stmt->error);
    $sel_stmt->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

$sel_result = $sel_stmt->get_result();
$existing   = $sel_result->fetch_assoc();
$sel_stmt->close();

if ($existing === null || $existing === false) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Faculty account not found.']);
    exit;
}

// ── UPDATE allow_org_borrowing ─────────────────────────────────────────────────
$upd_stmt = $conn->prepare(
    "UPDATE tbl_users SET allow_org_borrowing = ? WHERE faculty_id = ?"
);

if ($upd_stmt === false) {
    error_log('[toggle-org-borrowing] Failed to prepare UPDATE: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

$upd_stmt->bind_param('is', $allow_org_borrowing, $faculty_id);

if (!$upd_stmt->execute()) {
    error_log('[toggle-org-borrowing] Failed to execute UPDATE: ' . $upd_stmt->error);
    $upd_stmt->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

$upd_stmt->close();
$conn->close();

// ── Success response ──────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['status' => 'success', 'allow_org_borrowing' => $allow_org_borrowing]);
