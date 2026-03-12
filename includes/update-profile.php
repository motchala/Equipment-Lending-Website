<?php

/**
 * update-profile.php
 * AJAX endpoint — handles profile updates, password changes, backup email, profile pictures,
 * academic info, contact details, and emergency contact.
 * Place this file inside: includes/update-profile.php
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized. Please log in again.']);
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    echo json_encode(['success' => false, 'msg' => 'Database connection failed.']);
    exit;
}

$uid = mysqli_real_escape_string($conn, $_SESSION['user_id']);
$action = trim($_POST['action'] ?? '');

/* ══════════════════════════════════════════════════════════════════
   ACTION: save_profile
   Updates fullname (always), and dob / gender / nationality only
   if they have NOT been set before (one-time-lock rule).
══════════════════════════════════════════════════════════════════ */
if ($action === 'save_profile') {
    $fullname = trim($_POST['fullname'] ?? '');

    if (empty($fullname)) {
        echo json_encode(['success' => false, 'msg' => 'Full name cannot be empty.']);
        exit;
    }
    if (strlen($fullname) > 120) {
        echo json_encode(['success' => false, 'msg' => 'Name is too long (max 120 chars).']);
        exit;
    }
    // Strip anything that isn't letters, spaces, dots, hyphens, or apostrophes
    if (!preg_match("/^[a-zA-ZÀ-ÖØ-öø-ÿ\s.\-']+$/u", $fullname)) {
        echo json_encode(['success' => false, 'msg' => 'Name contains invalid characters.']);
        exit;
    }

    $fn_esc = mysqli_real_escape_string($conn, $fullname);

    // Fetch current locked-field values
    $cur_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT fullname, dob, gender, nationality FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $updates = [];
    $changes = [];

    // Fullname - always update
    if ($cur_row['fullname'] !== $fullname) {
        $updates[] = "fullname='$fn_esc'";
        $changes[] = [
            'field' => 'Full Name',
            'from' => $cur_row['fullname'],
            'to' => $fullname,
            'locked' => false
        ];
    }

    // DOB — only save if currently null/empty
    if (empty($cur_row['dob']) && !empty($_POST['dob'])) {
        $dob = mysqli_real_escape_string($conn, $_POST['dob']);
        // Validate format
        $d = DateTime::createFromFormat('Y-m-d', $dob);
        if ($d && $d->format('Y-m-d') === $dob) {
            $updates[] = "dob='$dob'";
            $changes[] = [
                'field' => 'Date of Birth',
                'from' => '—',
                'to' => date('F j, Y', strtotime($dob)),
                'locked' => true
            ];
        }
    }

    // Gender — only save if currently null/empty
    $allowed_genders = ['Male', 'Female', 'Prefer not to say'];
    if (empty($cur_row['gender']) && !empty($_POST['gender'])) {
        $gender = $_POST['gender'];
        if (in_array($gender, $allowed_genders)) {
            $gender_esc = mysqli_real_escape_string($conn, $gender);
            $updates[] = "gender='$gender_esc'";
            $changes[] = [
                'field' => 'Gender',
                'from' => '—',
                'to' => $gender,
                'locked' => true
            ];
        }
    }

    // Nationality — only save if currently null/empty
    if (empty($cur_row['nationality']) && !empty($_POST['nationality'])) {
        $nationality = trim($_POST['nationality']);
        if (strlen($nationality) <= 80) {
            $nat_esc = mysqli_real_escape_string($conn, $nationality);
            $updates[] = "nationality='$nat_esc'";
            $changes[] = [
                'field' => 'Nationality',
                'from' => '—',
                'to' => $nationality,
                'locked' => true
            ];
        }
    }

    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'msg' => 'No changes to save.',
            'changes' => []
        ]);
        exit;
    }

    $sql = "UPDATE tbl_users SET " . implode(', ', $updates) . " WHERE student_id='$uid'";

    // Return changes for confirmation modal (don't execute yet)
    echo json_encode([
        'success' => true,
        'msg' => 'Changes prepared for confirmation',
        'changes' => $changes,
        'sql' => $sql,
        'needsConfirmation' => true
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: confirm_save_profile
   Actually executes the profile update after user confirms
══════════════════════════════════════════════════════════════════ */
if ($action === 'confirm_save_profile') {
    $fullname = trim($_POST['fullname'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');

    $fn_esc = mysqli_real_escape_string($conn, $fullname);

    $cur_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT dob, gender, nationality FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $updates = ["fullname='$fn_esc'"];

    if (empty($cur_row['dob']) && !empty($dob)) {
        $dob_esc = mysqli_real_escape_string($conn, $dob);
        $updates[] = "dob='$dob_esc'";
    }

    if (empty($cur_row['gender']) && !empty($gender)) {
        $gender_esc = mysqli_real_escape_string($conn, $gender);
        $updates[] = "gender='$gender_esc'";
    }

    if (empty($cur_row['nationality']) && !empty($nationality)) {
        $nat_esc = mysqli_real_escape_string($conn, $nationality);
        $updates[] = "nationality='$nat_esc'";
    }

    $sql = "UPDATE tbl_users SET " . implode(', ', $updates) . " WHERE student_id='$uid'";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['fullname'] = $fullname;
        $updated = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT fullname, dob, gender, nationality FROM tbl_users WHERE student_id='$uid' LIMIT 1"
        ));
        echo json_encode([
            'success' => true,
            'msg' => 'Profile updated successfully!',
            'fullname' => $updated['fullname'],
            'dob' => $updated['dob'],
            'gender' => $updated['gender'],
            'nationality' => $updated['nationality'],
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: save_academic
   Updates academic information (program is locked after first save)
══════════════════════════════════════════════════════════════════ */
if ($action === 'save_academic') {
    $program = trim($_POST['program'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');

    // Validate program from allowed list
    $allowed_programs = ['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'];
    if (!empty($program) && !in_array($program, $allowed_programs)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid program selected.']);
        exit;
    }

    // Validate year level
    $allowed_years = ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Ladderized'];
    if (!empty($year_level) && !in_array($year_level, $allowed_years)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid year level selected.']);
        exit;
    }

    $cur_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT program, year_level FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $updates = [];
    $changes = [];

    // Program - only save if currently empty (one-time lock)
    if (empty($cur_row['program']) && !empty($program)) {
        $program_esc = mysqli_real_escape_string($conn, $program);
        $updates[] = "program='$program_esc'";
        $changes[] = [
            'field' => 'Program',
            'from' => '—',
            'to' => $program,
            'locked' => true
        ];
    }

    // Year level - always editable
    if ($cur_row['year_level'] !== $year_level && !empty($year_level)) {
        $year_esc = mysqli_real_escape_string($conn, $year_level);
        $updates[] = "year_level='$year_esc'";
        $changes[] = [
            'field' => 'Year Level',
            'from' => $cur_row['year_level'] ?: '—',
            'to' => $year_level,
            'locked' => false
        ];
    }

    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'msg' => 'No changes to save.',
            'changes' => []
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'msg' => 'Changes prepared for confirmation',
        'changes' => $changes,
        'needsConfirmation' => true,
        'data' => ['program' => $program, 'year_level' => $year_level]
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: confirm_save_academic
══════════════════════════════════════════════════════════════════ */
if ($action === 'confirm_save_academic') {
    $program = trim($_POST['program'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');

    $cur_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT program FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $updates = [];

    if (empty($cur_row['program']) && !empty($program)) {
        $program_esc = mysqli_real_escape_string($conn, $program);
        $updates[] = "program='$program_esc'";
    }

    if (!empty($year_level)) {
        $year_esc = mysqli_real_escape_string($conn, $year_level);
        $updates[] = "year_level='$year_esc'";
    }

    if (!empty($updates)) {
        $sql = "UPDATE tbl_users SET " . implode(', ', $updates) . " WHERE student_id='$uid'";
        if (mysqli_query($conn, $sql)) {
            $updated = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT program, year_level FROM tbl_users WHERE student_id='$uid' LIMIT 1"
            ));
            echo json_encode([
                'success' => true,
                'msg' => 'Academic information updated successfully!',
                'program' => $updated['program'],
                'year_level' => $updated['year_level']
            ]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'msg' => 'No changes to save.']);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: save_contact
   Updates contact details (all fields are editable)
══════════════════════════════════════════════════════════════════ */
if ($action === 'save_contact') {
    $phone = trim($_POST['phone'] ?? '');
    $present_address = trim($_POST['present_address'] ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $landline = trim($_POST['landline'] ?? '');

    // Validate phone format (basic)
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid phone number format.']);
        exit;
    }
    if (!empty($landline) && !preg_match('/^[0-9+\-\s()]+$/', $landline)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid landline format.']);
        exit;
    }

    $cur_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT phone, present_address, permanent_address, landline FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $updates = [];
    $changes = [];

    if ($cur_row['phone'] !== $phone) {
        $phone_esc = mysqli_real_escape_string($conn, $phone);
        $updates[] = "phone='$phone_esc'";
        $changes[] = [
            'field' => 'Mobile Number',
            'from' => $cur_row['phone'] ?: '—',
            'to' => $phone ?: '—',
            'locked' => false
        ];
    }

    if ($cur_row['present_address'] !== $present_address) {
        $present_esc = mysqli_real_escape_string($conn, $present_address);
        $updates[] = "present_address='$present_esc'";
        $changes[] = [
            'field' => 'Present Address',
            'from' => $cur_row['present_address'] ?: '—',
            'to' => $present_address ?: '—',
            'locked' => false
        ];
    }

    if ($cur_row['permanent_address'] !== $permanent_address) {
        $perm_esc = mysqli_real_escape_string($conn, $permanent_address);
        $updates[] = "permanent_address='$perm_esc'";
        $changes[] = [
            'field' => 'Permanent Address',
            'from' => $cur_row['permanent_address'] ?: '—',
            'to' => $permanent_address ?: '—',
            'locked' => false
        ];
    }

    if ($cur_row['landline'] !== $landline) {
        $landline_esc = mysqli_real_escape_string($conn, $landline);
        $updates[] = "landline='$landline_esc'";
        $changes[] = [
            'field' => 'Landline',
            'from' => $cur_row['landline'] ?: '—',
            'to' => $landline ?: '—',
            'locked' => false
        ];
    }

    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'msg' => 'No changes to save.',
            'changes' => []
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'msg' => 'Changes prepared for confirmation',
        'changes' => $changes,
        'needsConfirmation' => true,
        'data' => [
            'phone' => $phone,
            'present_address' => $present_address,
            'permanent_address' => $permanent_address,
            'landline' => $landline
        ]
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: confirm_save_contact
══════════════════════════════════════════════════════════════════ */
if ($action === 'confirm_save_contact') {
    $phone = trim($_POST['phone'] ?? '');
    $present_address = trim($_POST['present_address'] ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $landline = trim($_POST['landline'] ?? '');

    $phone_esc = mysqli_real_escape_string($conn, $phone);
    $present_esc = mysqli_real_escape_string($conn, $present_address);
    $perm_esc = mysqli_real_escape_string($conn, $permanent_address);
    $landline_esc = mysqli_real_escape_string($conn, $landline);

    $sql = "UPDATE tbl_users SET phone='$phone_esc', present_address='$present_esc', 
            permanent_address='$perm_esc', landline='$landline_esc' WHERE student_id='$uid'";

    if (mysqli_query($conn, $sql)) {
        $updated = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT phone, present_address, permanent_address, landline FROM tbl_users WHERE student_id='$uid' LIMIT 1"
        ));
        echo json_encode([
            'success' => true,
            'msg' => 'Contact details updated successfully!',
            'phone' => $updated['phone'],
            'present_address' => $updated['present_address'],
            'permanent_address' => $updated['permanent_address'],
            'landline' => $updated['landline']
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: save_emergency
   Updates emergency contact (all fields are editable)
══════════════════════════════════════════════════════════════════ */
if ($action === 'save_emergency') {
    $name = trim($_POST['emergency_name'] ?? '');
    $relationship = trim($_POST['emergency_relationship'] ?? '');
    $phone = trim($_POST['emergency_phone'] ?? '');

    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid phone number format.']);
        exit;
    }

    $cur_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT emergency_name, emergency_relationship, emergency_phone FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $updates = [];
    $changes = [];

    if ($cur_row['emergency_name'] !== $name) {
        $name_esc = mysqli_real_escape_string($conn, $name);
        $updates[] = "emergency_name='$name_esc'";
        $changes[] = [
            'field' => 'Emergency Contact Name',
            'from' => $cur_row['emergency_name'] ?: '—',
            'to' => $name ?: '—',
            'locked' => false
        ];
    }

    if ($cur_row['emergency_relationship'] !== $relationship) {
        $rel_esc = mysqli_real_escape_string($conn, $relationship);
        $updates[] = "emergency_relationship='$rel_esc'";
        $changes[] = [
            'field' => 'Relationship',
            'from' => $cur_row['emergency_relationship'] ?: '—',
            'to' => $relationship ?: '—',
            'locked' => false
        ];
    }

    if ($cur_row['emergency_phone'] !== $phone) {
        $phone_esc = mysqli_real_escape_string($conn, $phone);
        $updates[] = "emergency_phone='$phone_esc'";
        $changes[] = [
            'field' => 'Emergency Contact Phone',
            'from' => $cur_row['emergency_phone'] ?: '—',
            'to' => $phone ?: '—',
            'locked' => false
        ];
    }

    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'msg' => 'No changes to save.',
            'changes' => []
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'msg' => 'Changes prepared for confirmation',
        'changes' => $changes,
        'needsConfirmation' => true,
        'data' => [
            'emergency_name' => $name,
            'emergency_relationship' => $relationship,
            'emergency_phone' => $phone
        ]
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: confirm_save_emergency
══════════════════════════════════════════════════════════════════ */
if ($action === 'confirm_save_emergency') {
    $name = trim($_POST['emergency_name'] ?? '');
    $relationship = trim($_POST['emergency_relationship'] ?? '');
    $phone = trim($_POST['emergency_phone'] ?? '');

    $name_esc = mysqli_real_escape_string($conn, $name);
    $rel_esc = mysqli_real_escape_string($conn, $relationship);
    $phone_esc = mysqli_real_escape_string($conn, $phone);

    $sql = "UPDATE tbl_users SET emergency_name='$name_esc', emergency_relationship='$rel_esc', 
            emergency_phone='$phone_esc' WHERE student_id='$uid'";

    if (mysqli_query($conn, $sql)) {
        $updated = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT emergency_name, emergency_relationship, emergency_phone FROM tbl_users WHERE student_id='$uid' LIMIT 1"
        ));
        echo json_encode([
            'success' => true,
            'msg' => 'Emergency contact updated successfully!',
            'emergency_name' => $updated['emergency_name'],
            'emergency_relationship' => $updated['emergency_relationship'],
            'emergency_phone' => $updated['emergency_phone']
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: verify_email_for_password
   Verifies user's email before allowing password change
══════════════════════════════════════════════════════════════════ */
if ($action === 'verify_email_for_password') {
    $entered_email = trim($_POST['email'] ?? '');

    if (empty($entered_email)) {
        echo json_encode(['success' => false, 'msg' => 'Email is required.']);
        exit;
    }

    // Fetch user's actual email
    $user = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT email FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    if (!$user || empty($user['email'])) {
        echo json_encode(['success' => false, 'msg' => 'No email on file. Please contact admin to set up your email first.']);
        exit;
    }

    if (strtolower($entered_email) !== strtolower($user['email'])) {
        echo json_encode(['success' => false, 'msg' => 'Email does not match our records.']);
        exit;
    }

    echo json_encode(['success' => true, 'msg' => 'Email verified successfully.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: change_password
   Verifies current password, checks 7-day restriction, then updates
══════════════════════════════════════════════════════════════════ */
if ($action === 'change_password') {
    $current  = $_POST['current_password']  ?? '';
    $new_pw   = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (empty($current) || empty($new_pw) || empty($confirm)) {
        echo json_encode(['success' => false, 'msg' => 'All password fields are required.']);
        exit;
    }
    if (strlen($new_pw) < 6) {
        echo json_encode(['success' => false, 'msg' => 'New password must be at least 6 characters.']);
        exit;
    }
    if ($new_pw !== $confirm) {
        echo json_encode(['success' => false, 'msg' => 'New passwords do not match.']);
        exit;
    }
    if ($new_pw === $current) {
        echo json_encode(['success' => false, 'msg' => 'New password cannot be the same as your current password.']);
        exit;
    }

    $user = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT password, last_password_change FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));
    if (!$user) {
        echo json_encode(['success' => false, 'msg' => 'User not found.']);
        exit;
    }

    // Check 7-day restriction
    if (!empty($user['last_password_change'])) {
        $last_change = new DateTime($user['last_password_change']);
        $now = new DateTime();
        $diff = $last_change->diff($now);
        $days_since = $diff->days;

        if ($days_since < 7) {
            $days_left = 7 - $days_since;
            echo json_encode([
                'success' => false,
                'msg' => "You must wait {$days_left} more day" . ($days_left > 1 ? 's' : '') . " before changing your password again. Last changed on " . $last_change->format('F j, Y') . "."
            ]);
            exit;
        }
    }

    // Support both bcrypt-hashed and legacy plain-text passwords
    $valid = false;
    if (password_verify($current, $user['password'])) {
        $valid = true;
    } elseif ($user['password'] === $current) {
        // Legacy plain-text (will be upgraded to hash on change)
        $valid = true;
    } elseif ($user['password'] === md5($current)) {
        // Legacy MD5
        $valid = true;
    }

    if (!$valid) {
        echo json_encode(['success' => false, 'msg' => 'Current password is incorrect.']);
        exit;
    }

    $hashed = mysqli_real_escape_string($conn, password_hash($new_pw, PASSWORD_DEFAULT));
    $now_timestamp = date('Y-m-d H:i:s');

    if (mysqli_query($conn, "UPDATE tbl_users SET password='$hashed', last_password_change='$now_timestamp' WHERE student_id='$uid'")) {
        echo json_encode(['success' => true, 'msg' => 'Password changed successfully! Keep it safe.']);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to update password.']);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: update_backup_email
   Adds backup email ONCE - cannot be updated after first save
══════════════════════════════════════════════════════════════════ */
if ($action === 'update_backup_email') {
    $backup_email = trim($_POST['backup_email'] ?? '');

    // Check if backup email already exists
    $existing = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT backup_email FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    if (!empty($existing['backup_email'])) {
        echo json_encode([
            'success' => false,
            'msg' => 'Backup email cannot be changed once set. Please contact admin if you need to update it.'
        ]);
        exit;
    }

    if (empty($backup_email)) {
        echo json_encode(['success' => false, 'msg' => 'Please enter a backup email address.']);
        exit;
    }

    if (!filter_var($backup_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid email format.']);
        exit;
    }

    $backup_esc = mysqli_real_escape_string($conn, $backup_email);

    if (mysqli_query($conn, "UPDATE tbl_users SET backup_email='$backup_esc' WHERE student_id='$uid'")) {
        echo json_encode([
            'success' => true,
            'msg' => 'Backup email added successfully! This cannot be changed later.',
            'backup_email' => $backup_email
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to update backup email.']);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: upload_profile_picture
   Handles profile picture upload with validation
══════════════════════════════════════════════════════════════════ */
if ($action === 'upload_profile_picture') {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'msg' => 'No file uploaded or upload error occurred.']);
        exit;
    }

    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid file type. Only JPG, PNG, and WEBP are allowed.']);
        exit;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'msg' => 'File too large. Maximum size is 5MB.']);
        exit;
    }

    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/profile_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = $uid . '_' . time() . '.' . $extension;
    $upload_path = $upload_dir . $new_filename;

    // Delete old profile picture if exists
    $old_pic = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT profile_picture FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ))['profile_picture'] ?? '';

    if (!empty($old_pic) && file_exists($upload_dir . $old_pic)) {
        unlink($upload_dir . $old_pic);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $filename_esc = mysqli_real_escape_string($conn, $new_filename);

        if (mysqli_query($conn, "UPDATE tbl_users SET profile_picture='$filename_esc' WHERE student_id='$uid'")) {
            echo json_encode([
                'success' => true,
                'msg' => 'Profile picture updated successfully!',
                'profile_picture' => 'uploads/profile_pictures/' . $new_filename
            ]);
        } else {
            // Cleanup file if DB update fails
            unlink($upload_path);
            echo json_encode(['success' => false, 'msg' => 'Failed to update database.']);
        }
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to upload file.']);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: remove_profile_picture
   Removes profile picture and reverts to initials
══════════════════════════════════════════════════════════════════ */
if ($action === 'remove_profile_picture') {
    $user = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT profile_picture FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    if (!empty($user['profile_picture'])) {
        $file_path = '../uploads/profile_pictures/' . $user['profile_picture'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    if (mysqli_query($conn, "UPDATE tbl_users SET profile_picture=NULL WHERE student_id='$uid'")) {
        echo json_encode([
            'success' => true,
            'msg' => 'Profile picture removed successfully!',
            'profile_picture' => null
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to remove profile picture.']);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ACTION: get_completion_status
   Returns account completion percentage
══════════════════════════════════════════════════════════════════ */
if ($action === 'get_completion_status') {
    $user = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT fullname, dob, gender, nationality, email, backup_email, program, year_level, 
         phone, present_address, permanent_address, emergency_name, emergency_relationship, emergency_phone 
         FROM tbl_users WHERE student_id='$uid' LIMIT 1"
    ));

    $total_fields = 13;
    $completed_fields = 0;

    $fields = [
        'fullname',
        'dob',
        'gender',
        'nationality',
        'email',
        'backup_email',
        'program',
        'year_level',
        'phone',
        'present_address',
        'permanent_address',
        'emergency_name',
        'emergency_relationship',
        'emergency_phone'
    ];

    foreach ($fields as $field) {
        if (!empty($user[$field])) {
            $completed_fields++;
        }
    }

    $percentage = round(($completed_fields / $total_fields) * 100);

    echo json_encode([
        'success' => true,
        'percentage' => $percentage,
        'completed' => $completed_fields,
        'total' => $total_fields
    ]);
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Unknown action.']);
