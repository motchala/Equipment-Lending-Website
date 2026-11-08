<?php
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Super Admin only.']);
    exit;
}

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

// ── LIST: GET ?action=list&campus_id=1 ────────────────────────────────────
if ($action === 'list') {
    $campus_id = (int)($_GET['campus_id'] ?? 0);
    if ($campus_id <= 0) {
        echo json_encode(['status' => 'error', 'error' => 'Invalid campus.']);
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT category_id, name, sort_order
           FROM tbl_equipment_categories
          WHERE campus_id = ?
          ORDER BY sort_order ASC, name ASC"
    );
    $stmt->bind_param('i', $campus_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['status' => 'success', 'categories' => $rows]);
    exit;
}

// ── ADD: POST action=add ──────────────────────────────────────────────────
if ($action === 'add') {
    csrf_verify();
    $campus_id = (int)($_POST['campus_id'] ?? 0);
    $name      = trim((string)($_POST['name'] ?? ''));
    if ($campus_id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'error' => 'Campus and category name are required.']);
        exit;
    }
    if (mb_strlen($name) > 100) {
        echo json_encode(['status' => 'error', 'error' => 'Category name must be 100 characters or fewer.']);
        exit;
    }
    $stmt = $conn->prepare(
        "INSERT INTO tbl_equipment_categories (campus_id, name)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = name"  // silently ignore exact duplicate
    );
    $stmt->bind_param('is', $campus_id, $name);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        // Check if it already exists
        $chk = $conn->prepare("SELECT category_id FROM tbl_equipment_categories WHERE campus_id = ? AND name = ?");
        $chk->bind_param('is', $campus_id, $name);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $chk->close();
            echo json_encode(['status' => 'error', 'error' => 'That category already exists for this campus.']);
        } else {
            $chk->close();
            echo json_encode(['status' => 'error', 'error' => 'Could not save category. Please try again.']);
        }
        exit;
    }
    $new_id = (int)$conn->insert_id;
    $stmt->close();
    echo json_encode(['status' => 'success', 'category_id' => $new_id, 'name' => $name]);
    exit;
}

// ── DELETE: POST action=delete ────────────────────────────────────────────
if ($action === 'delete') {
    csrf_verify();
    $category_id = (int)($_POST['category_id'] ?? 0);
    if ($category_id <= 0) {
        echo json_encode(['status' => 'error', 'error' => 'Invalid category.']);
        exit;
    }
    // Block delete if any item still uses this category
    $chk = $conn->prepare(
        "SELECT COUNT(*) AS c FROM tbl_inventory i
          JOIN tbl_equipment_categories ec ON ec.name = i.category AND ec.campus_id = i.campus_id
         WHERE ec.category_id = ? AND i.is_archived = 0"
    );
    $chk->bind_param('i', $category_id);
    $chk->execute();
    $in_use = (int)$chk->get_result()->fetch_assoc()['c'];
    $chk->close();
    if ($in_use > 0) {
        echo json_encode(['status' => 'error', 'error' => 'Cannot delete — ' . $in_use . ' active item(s) use this category. Re-assign them first.']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM tbl_equipment_categories WHERE category_id = ?");
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'error' => 'Unknown action.']);
