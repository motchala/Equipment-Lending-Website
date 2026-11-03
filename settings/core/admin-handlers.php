<?php
// ================= AJAX CHANGE PASSWORD =================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'change_password') {
    header('Content-Type: application/json');
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $email   = $_SESSION['admin_email'] ?? '';

    // 1. Basic Validation
    if (empty($current) || empty($new) || empty($confirm)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit();
    }
    if ($new !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit();
    }
    if (strlen($new) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 4 characters.']);
        exit();
    }
    // tbl_accounts.password is varchar(16) — enforce so the UPDATE below
    // never silently truncates the new password.
    if (strlen($new) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 6 characters.']);
        exit();
    }
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit();
    }

    // 2/3. Verify current password and update it in tbl_accounts — the same
    // table landing-page.php authenticates admin logins against. Looked up
    // by the logged-in admin's own session email rather than a hardcoded
    // dev account, so this works for whatever email is on file.
    $stmt_acc = $conn->prepare("SELECT password FROM tbl_accounts WHERE email = ? LIMIT 1");
    $stmt_acc->bind_param("s", $email);
    $stmt_acc->execute();
    $res_acc = $stmt_acc->get_result();
    $acc_row = $res_acc->fetch_assoc();
    $stmt_acc->close();

    if (!$acc_row) {
        echo json_encode(['status' => 'error', 'message' => 'Account not found. Please log in again.']);
        exit();
    }

    if (!password_verify($current, $acc_row['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect current password.']);
        exit();
    }

    // Self-healing schema (same pattern already used for last_login in
    // landing-page.php): add the tracking column the first time it's needed.
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_password_change'");
    if ($col_check && mysqli_num_rows($col_check) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_accounts ADD COLUMN last_password_change DATETIME NULL");
    }

    $now_dt     = date('Y-m-d H:i:s');
    $hashed_new = password_hash($new, PASSWORD_BCRYPT);
    $update_acc = $conn->prepare("UPDATE tbl_accounts SET password = ?, last_password_change = ? WHERE email = ?");
    $update_acc->bind_param("sss", $hashed_new, $now_dt, $email);
    if ($update_acc->execute()) {
        $_SESSION['admin_last_pw_change'] = $now_dt;
        echo json_encode([
            'status'          => 'success',
            'message'         => 'Password updated successfully.',
            'last_pw_change'  => $now_dt,
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
    exit();
}

// ================= AJAX UPDATE PROFILE (Settings → My Account) =================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_profile') {
    header('Content-Type: application/json');
    csrf_verify();

    $new_name  = trim($_POST['admin_name'] ?? '');
    $new_email = trim($_POST['admin_email'] ?? '');
    $new_backup_email = trim($_POST['admin_backup_email'] ?? '');
    $old_email = $_SESSION['admin_email'] ?? '';

    if ($new_name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Display name cannot be empty.']);
        exit();
    }
    if (mb_strlen($new_name) > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Display name is too long (max 100 characters).']);
        exit();
    }
    // Same allowed-character rule already used for faculty names in update-profile.php.
    if (!preg_match("/^[a-zA-ZÀ-ÖØ-öø-ÿ\s.\-']+$/u", $new_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Display name contains invalid characters.']);
        exit();
    }
    if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
        exit();
    }
    if ($new_backup_email !== '' && !filter_var($new_backup_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid backup email address or leave it blank.']);
        exit();
    }
    if (empty($old_email)) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit();
    }

    // tbl_accounts.email carries a UNIQUE key — check for collisions ourselves
    // first so we can return a friendly message instead of a raw DB error.
    if (strcasecmp($new_email, $old_email) !== 0) {
        $stmt_dup = $conn->prepare("SELECT 1 FROM tbl_accounts WHERE email = ? LIMIT 1");
        $stmt_dup->bind_param("s", $new_email);
        $stmt_dup->execute();
        $dup_found = $stmt_dup->get_result()->num_rows > 0;
        $stmt_dup->close();
        if ($dup_found) {
            echo json_encode(['status' => 'error', 'message' => 'That email is already in use.']);
            exit();
        }
    }

    $stmt_upd = $conn->prepare("UPDATE tbl_accounts SET fullName = ?, email = ?, backup_email = ? WHERE email = ?");
    if (!$stmt_upd) {
        error_log('[admin-profile] Profile update prepare failed: ' . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Could not save changes. Please try again.']);
        exit();
    }
    $stmt_upd->bind_param("ssss", $new_name, $new_email, $new_backup_email, $old_email);

    if ($stmt_upd->execute()) {
        // Keep the session in sync so every part of the page (header, dropdown,
        // greeting, and subsequent requests like change-password) sees the
        // update immediately without requiring a fresh login.
        $_SESSION['admin_name']  = $new_name;
        $_SESSION['admin_email'] = $new_email;
        echo json_encode([
            'status'      => 'success',
            'message'     => 'Profile updated successfully.',
            'admin_name'  => $new_name,
            'admin_email' => $new_email,
            'admin_backup_email' => $new_backup_email,
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not save changes. Please try again.']);
    }
    exit();
}
