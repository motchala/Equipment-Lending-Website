<?php
// ── Avatar initials ────────────────────────────────────────────────────────
$name_parts = explode(' ', trim($fullname));
$firstname  = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

// ── Profile ────────────────────────────────────────────────────────────────
$profile_row = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT email, backup_email, profile_picture, dob, gender, nationality, 
     department, faculty_rank, phone, present_address, permanent_address, landline,
     emergency_name, emergency_relationship, emergency_phone, role
     FROM tbl_users WHERE faculty_id='$uid_safe' LIMIT 1"
)) ?: [];
$db_email             = $profile_row['email']             ?? '';
$db_backup_email      = $profile_row['backup_email']      ?? '';
$db_profile_pic       = $profile_row['profile_picture']   ?? '';
$db_dob               = $profile_row['dob']               ?? '';
$db_gender            = $profile_row['gender']            ?? '';
$db_nationality       = $profile_row['nationality']       ?? '';
$db_department        = $profile_row['department']        ?? '';
$db_faculty_rank      = $profile_row['faculty_rank']      ?? '';
$db_phone             = $profile_row['phone']             ?? '';
$db_present_address   = $profile_row['present_address']   ?? '';
$db_permanent_address = $profile_row['permanent_address'] ?? '';
$db_landline          = $profile_row['landline']          ?? '';
$db_emergency_name    = $profile_row['emergency_name']        ?? '';
$db_emergency_rel     = $profile_row['emergency_relationship'] ?? '';
$db_emergency_phone   = $profile_row['emergency_phone']       ?? '';
$is_org_adviser       = ($profile_row['role'] ?? '') === 'Organization Adviser';

// Dual-mode (personal + organization) borrowing is automatic for Organization Advisers.
$is_dual_mode = $is_org_adviser;

// Faculty ID: set by the faculty member themselves (once). Until then the account is stored
// under an internal placeholder that must never be shown (see shared/core/faculty-id.php).
$fid_unset = faculty_id_is_unset((string)$_SESSION['faculty_id']);
$fid_label = faculty_id_display((string)$_SESSION['faculty_id'], 'Not set');

// ── Adviser-mode inventory checklist — only loaded if dual mode active ────
$avail_items = [];
if ($is_dual_mode) {
    $avail_result = $conn->query(
        "SELECT item_id, item_name, category, quantity FROM tbl_inventory WHERE is_archived = 0 ORDER BY category, item_name"
    );
    if ($avail_result) {
        while ($av_row = $avail_result->fetch_assoc()) $avail_items[] = $av_row;
    }
}
