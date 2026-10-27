<?php
declare(strict_types=1);
/**
 * lookup-faculty-registry.php
 *
 * Super Admin types a first + last name in the Create Faculty form.
 * Returns the matching registry row(s) so the form can auto-fill
 * Faculty ID and generate the PUPSync email preview.
 *
 * The client shows the preview; the server re-derives everything on
 * final submit — the preview values are NEVER trusted as POST input.
 *
 * POST fields: csrf_token, first_name, last_name
 * Returns JSON: { status, matches: [...] } or { status, error }
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function lr_respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lr_respond(405, ['status' => 'error', 'error' => 'Method not allowed.']);
}

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    lr_respond(401, ['status' => 'error', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/faculty-id.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    lr_respond(403, ['status' => 'error', 'error' => 'Super Admin only.']);
}

$first_name = trim((string)($_POST['first_name'] ?? ''));
$last_name  = trim((string)($_POST['last_name']  ?? ''));

if ($first_name === '' || $last_name === '') {
    lr_respond(422, ['status' => 'error', 'error' => 'First name and last name are required.']);
}

// Check if registry has any entries at all
$count_res = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_faculty_registry");
if (!$count_res || (int)$count_res->fetch_assoc()['cnt'] === 0) {
    lr_respond(200, ['status' => 'no_registry', 'matches' => []]);
}

$matches = faculty_registry_lookup($conn, $first_name, $last_name);

if (empty($matches)) {
    // 'hints' = "did you mean...?" entries (e.g. typed "Cruz" for "Edmundo B. Dela Cruz")
    lr_respond(200, [
        'status'  => 'not_found',
        'matches' => [],
        'hints'   => faculty_registry_hints($conn, $first_name, $last_name),
    ]);
}

// For each match, generate the email preview
$result = [];
foreach ($matches as $row) {
    // Name parts as the registry reads them: the surname is everything after the middle
    // initial ("Edmundo B. Dela Cruz" -> "Dela Cruz"), or the last word when there is none.
    $parts      = faculty_registry_name_parts((string)$row['full_name']);
    $middle     = $parts['middle'];            // used to resolve email conflicts
    $base_email = faculty_email_generate($parts['first'], $parts['last']);
    $email      = faculty_email_resolve_conflict($conn, $base_email, $middle);
    $result[] = [
        'registry_id'    => (int)$row['id'],
        'faculty_id'     => (string)($row['faculty_id'] ?? ''),
        'full_name'      => (string)$row['full_name'],
        'department'     => (string)($row['department'] ?? ''),
        'is_newly_hired' => (bool)$row['is_newly_hired'],
        'email_preview'  => $email,
    ];
}

lr_respond(200, ['status' => 'found', 'matches' => $result]);
