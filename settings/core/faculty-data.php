<?php
// ── Avatar initials ────────────────────────────────────────────────────────
$name_parts = explode(' ', trim($fullname));
$firstname  = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

// ── Profile ────────────────────────────────────────────────────────────────
$profile_row = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT email, backup_email, profile_picture, last_password_change, department, role
     FROM tbl_users WHERE faculty_id='$uid_safe' LIMIT 1"
)) ?: [];
$db_email             = $profile_row['email']             ?? '';
$db_backup_email      = $profile_row['backup_email']      ?? '';
$db_last_pw_change    = $profile_row['last_password_change'] ?? null;
$db_profile_pic       = $profile_row['profile_picture']   ?? '';
$db_department        = $profile_row['department']        ?? '';

// Faculty ID: set by the faculty member themselves (once). Until then the account is stored
// under an internal placeholder that must never be shown (see shared/core/faculty-id.php).
$fid_unset = faculty_id_is_unset((string)$_SESSION['faculty_id']);
$fid_label = faculty_id_display((string)$_SESSION['faculty_id'], 'Not set');

