<?php
/**
 * ajax/reprocess-request.php
 *
 * AJAX endpoint — Document upload + re-evaluation for a held Waiting request.
 *
 * Accepts:
 *   POST request_id  (int)   — PK of the tbl_requests row to re-evaluate
 *   POST document    (file)  — Signed request letter (PDF, JPG, PNG, WEBP; max 5 MB)
 *
 * Response: JSON { status: 'success'|'error', decision: string, message: string }
 *
 * Requirement 8.4 — When a required document is subsequently uploaded for a held
 * Waiting request, the Arbitration Engine SHALL immediately and automatically
 * re-evaluate the request using the full priority scoring rules.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

// ── Output buffering + JSON header ───────────────────────────────────────────
ob_start();
header('Content-Type: application/json');

// ── Helper: send a JSON error response and exit ───────────────────────────────
function send_error(int $http_status, string $message): never
{
    ob_end_clean();
    http_response_code($http_status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// ── Session guard — require active faculty session ────────────────────────────
session_start();

if (empty($_SESSION['faculty_id'])) {
    send_error(401, 'Unauthorized. Please log in.');
}

$logged_in_faculty_id = (string)$_SESSION['faculty_id'];

// ── Require ArbitrationEngine ─────────────────────────────────────────────────
require_once __DIR__ . '/../includes/arbitration-engine.php';

// ── Database connection ───────────────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '', 'lending_db');

if ($conn->connect_error) {
    send_error(500, 'Database connection failed.');
}

$conn->set_charset('utf8mb4');

// ── Task 5.1: Validate request_id ────────────────────────────────────────────
$raw_request_id = $_POST['request_id'] ?? '';

if (!is_numeric($raw_request_id) || (int)$raw_request_id <= 0) {
    send_error(400, 'Invalid request ID.');
}

$request_id = (int)$raw_request_id;

// ── Task 5.2: Validate uploaded document ─────────────────────────────────────

// Must be present
if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    send_error(400, 'No document was uploaded.');
}

$file = $_FILES['document'];

// Surface-level PHP upload errors (disk full, partial upload, etc.)
if ($file['error'] !== UPLOAD_ERR_OK) {
    send_error(500, 'Upload failed. Please try again.');
}

// Size check — max 5 MB (5 * 1024 * 1024 = 5,242,880 bytes)
// Files exactly 5 MB are accepted (Requirement 10.3)
$max_bytes = 5 * 1024 * 1024;

if ($file['size'] > $max_bytes) {
    send_error(400, 'File too large. Maximum size is 5 MB.');
}

// MIME type check via finfo_file() against actual file bytes (prevents spoofing)
$allowed_mime_types = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
];

$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mime_type === false || !in_array($mime_type, $allowed_mime_types, true)) {
    send_error(400, 'Unsupported file type. Please upload a PDF, JPG, PNG, or WEBP file.');
}

// ── Verify request exists and belongs to the logged-in faculty member ─────────
$stmt = $conn->prepare(
    "SELECT id, student_id, status
       FROM tbl_requests
      WHERE id = ?
      LIMIT 1"
);

if ($stmt === false) {
    send_error(500, 'Database error. Please try again.');
}

$stmt->bind_param('i', $request_id);

if (!$stmt->execute()) {
    $stmt->close();
    send_error(500, 'Database error. Please try again.');
}

$result  = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if ($request === null || $request === false) {
    send_error(404, 'Request not found.');
}

// Ownership check — the request must belong to the logged-in faculty member
if ((string)$request['student_id'] !== $logged_in_faculty_id) {
    send_error(403, 'You do not have permission to modify this request.');
}

// ── Task 5.3: Store file and update document_path ─────────────────────────────

// Build destination filename: {timestamp}_{student_id}_{safe_original_name}
$timestamp      = time();
$student_id     = (string)$request['student_id'];
$original_name  = basename($file['name']);

// Sanitise the original filename — keep only alphanumerics, dots, dashes, underscores
$safe_name      = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original_name);
$dest_filename  = $timestamp . '_' . $student_id . '_' . $safe_name;

$upload_dir     = __DIR__ . '/../uploads/request_letters/';
$dest_path      = $upload_dir . $dest_filename;

// Relative path stored in the DB (relative to project root)
$document_path  = 'uploads/request_letters/' . $dest_filename;

if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
    send_error(500, 'Upload failed. Please try again.');
}

// UPDATE tbl_requests.document_path
$upd = $conn->prepare(
    'UPDATE tbl_requests SET document_path = ? WHERE id = ?'
);

if ($upd === false) {
    send_error(500, 'Database error. Please try again.');
}

$upd->bind_param('si', $document_path, $request_id);

if (!$upd->execute()) {
    $upd->close();
    send_error(500, 'Database error. Please try again.');
}

$upd->close();

// ── Task 5.4: Re-evaluate via ArbitrationEngine ───────────────────────────────
ArbitrationEngine::process($conn, $request_id);

// ── Task 5.5: Fetch updated status and return JSON response ───────────────────
$sel = $conn->prepare(
    'SELECT status FROM tbl_requests WHERE id = ? LIMIT 1'
);

if ($sel === false) {
    send_error(500, 'Database error. Please try again.');
}

$sel->bind_param('i', $request_id);

if (!$sel->execute()) {
    $sel->close();
    send_error(500, 'Database error. Please try again.');
}

$sel_result = $sel->get_result();
$updated    = $sel_result->fetch_assoc();
$sel->close();

if ($updated === null || $updated === false) {
    send_error(500, 'Could not retrieve updated request status.');
}

$decision = (string)$updated['status'];

ob_end_clean();
http_response_code(200);
echo json_encode([
    'status'   => 'success',
    'decision' => $decision,
    'message'  => 'Request re-evaluated.',
]);
exit;
