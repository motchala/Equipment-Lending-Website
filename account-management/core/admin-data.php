<?php
// ================= ADMIN INFO =================

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$name_parts = explode(' ', trim($admin_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

$admin_email = $_SESSION['admin_email'] ?? '';
$admin_backup_email = '';
$admin_profile_stmt = $conn->prepare('SELECT backup_email FROM tbl_accounts WHERE email = ? LIMIT 1');
if ($admin_profile_stmt) {
    $admin_profile_stmt->bind_param('s', $admin_email);
    $admin_profile_stmt->execute();
    $admin_profile_row = $admin_profile_stmt->get_result()->fetch_assoc();
    $admin_backup_email = (string)($admin_profile_row['backup_email'] ?? '');
    $admin_profile_stmt->close();
} else {
    error_log('[admin-profile] Could not prepare backup email lookup: ' . $conn->error);
}

// Fail closed: anything other than an explicit 'Super Admin' session value
// is treated as the more restrictive 'Admin' role.
$admin_role     = ($_SESSION['admin_role'] ?? '') === 'Super Admin' ? 'Super Admin' : 'Admin';
$is_super_admin = ($admin_role === 'Super Admin');

// How many admin accounts exist and how many slots remain (max 5).
// Graceful fallback in case the schema migration hasn't run yet.
$_ar = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_accounts"));
$admin_accounts_count     = (int)($_ar['c'] ?? 0);
$admin_accounts_remaining = max(0, 5 - $admin_accounts_count);

// Fetch the list of all admin accounts (for the Manage Admins panel).
// Columns: fullName, email, role, created_at — id added by migration.
$_admin_list_result = @mysqli_query(
    $conn,
    "SELECT id, fullName, email, role, created_at, last_login, dormant_until FROM tbl_accounts ORDER BY created_at ASC, email ASC"
);
if (!$_admin_list_result) {
    // last_login column not migrated yet — fall back so the panel still lists everyone.
    $_admin_list_result = @mysqli_query(
        $conn,
        "SELECT id, fullName, email, role, created_at, dormant_until FROM tbl_accounts ORDER BY created_at ASC, email ASC"
    );
}
$admin_accounts_list = [];
if ($_admin_list_result) {
    while ($ar = mysqli_fetch_assoc($_admin_list_result)) {
        $admin_accounts_list[] = $ar;
    }
}

// Ensure we have the admin's previous last_login, and their last
// password-change date, available for display. If not already cached in
// session (e.g., first load after this feature was added, or right after
// a fresh login), read them from tbl_accounts. Generalized to work for
// whichever email is on file rather than a single hardcoded account.
$admin_last_pw_change = $_SESSION['admin_last_pw_change'] ?? null;
if (!empty($admin_email) && (empty($_SESSION['admin_last_login']) || $admin_last_pw_change === null)) {
    $col_login = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_login'");
    $has_login_col = $col_login && mysqli_num_rows($col_login) > 0;

    $col_pw = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_password_change'");
    $has_pw_col = $col_pw && mysqli_num_rows($col_pw) > 0;

    $select_cols = [];
    if ($has_login_col) $select_cols[] = 'last_login';
    if ($has_pw_col) $select_cols[] = 'last_password_change';

    if (!empty($select_cols)) {
        $stmt = $conn->prepare("SELECT " . implode(', ', $select_cols) . " FROM tbl_accounts WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $admin_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                if ($has_login_col && empty($_SESSION['admin_last_login'])) {
                    $_SESSION['admin_last_login'] = $row['last_login'] ?? null;
                }
                if ($has_pw_col && $admin_last_pw_change === null) {
                    $admin_last_pw_change = $row['last_password_change'] ?? null;
                    $_SESSION['admin_last_pw_change'] = $admin_last_pw_change;
                }
            }
            $stmt->close();
        }
    }
}
