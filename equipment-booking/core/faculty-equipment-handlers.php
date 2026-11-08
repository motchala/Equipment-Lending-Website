<?php
// ── Handle Borrow Request ──────────────────────────────────────────────────
//   • Today      : no request letter. The system decides on the spot (the arbitration engine checks
//                  availability and the borrowing rules): the faculty sees "approved" at once, or is
//                  told why it cannot be accepted. Nothing is left for an admin to do.
//   • Later date : a request letter (PDF / JPG / PNG / WEBP, 5 MB) is recommended but optional. The
//                  request is checked against the schedule when it is filed, then waits for an admin.
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name'])) {
    if (!isset($_SESSION['faculty_id'])) die("Unauthorized access");

    csrf_verify();

    $user_id  = $_SESSION['faculty_id'];
    $stmt_usr = $conn->prepare("SELECT fullname, faculty_id FROM tbl_users WHERE faculty_id = ? LIMIT 1");
    $stmt_usr->bind_param('s', $user_id);
    $stmt_usr->execute();
    $user = $stmt_usr->get_result()->fetch_assoc();
    $stmt_usr->close();
    if (!$user) die("User profile not found.");
    $faculty_name   = $user['fullname'];
    $faculty_id     = $user['faculty_id'];

    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $instructor     = $faculty_name; // auto-filled from account name

    // Where (live room record, same tables as Facilities) and when (Today / Later rules).
    $rr = BookingSchedule::resolveRoom($conn, (string)($_POST['room_id'] ?? ''));
    if (!$rr['ok']) bm_fail($rr['error']);
    $room    = $rr['label'];
    $room_id = $rr['id'];            // null = "Outside"

    $chk = BookingSchedule::parse(
        $conn,
        $_POST,
        $equipment_name,
        max(1, (int)($_POST['qty'] ?? 1))
    );
    if (!$chk['ok']) bm_fail($chk['error']);
    $bk = $chk['data'];
    $is_today = ($bk['mode'] === 'today');

    // Equipment cannot go to a room that another faculty has reserved for that time
    if ($room_id !== null) {
        $room_msg = BookingSchedule::roomConflictMessage($conn, $room_id, $bk['start_ts'], $bk['end_ts'], (string)$faculty_id);
        if ($room_msg) bm_fail($room_msg);
    }
    if (BookingSchedule::freeUnits($conn, $equipment_name, $bk['start_ts'], $bk['end_ts']) < $bk['qty']) {
        $next = $bk['mode'] === 'future'
            ? BookingSchedule::earliestFreeDate(
                $conn,
                $equipment_name,
                $bk['qty'],
                substr($bk['borrow_time'], 0, 5),
                (int)($_POST['duration'] ?? 1),
                date('Y-m-d', strtotime($bk['borrow_date'] . ' +1 day'))
            )
            : null;
        bm_fail('"' . $equipment_name . '" is already booked for that schedule.'
            . ($next ? ' The next free start is ' . date('D, M j', strtotime($next)) . '.' : ''));
    }
    $borrow_date = $bk['borrow_date'];
    $return_date = $bk['return_date'];

    // ── Optional request letter (Later date only; ignored for a same-day request) ──
    $has_letter = false;
    if (!$is_today && isset($_FILES['request_document']) && $_FILES['request_document']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['request_document']['error'] !== UPLOAD_ERR_OK) {
            bm_fail('The request letter could not be uploaded. Please try again.');
        }
        if ($_FILES['request_document']['size'] > 5 * 1024 * 1024) {
            bm_fail('The request letter is too large. Maximum size is 5 MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['request_document']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed_mimes, true)) {
            bm_fail('Unsupported letter type. Please upload a PDF, JPG, PNG, or WEBP file.');
        }
        $has_letter = true;   // saved after the request is filed
    }

    // Collector info from the form
    $collector_type_p = (string)($_POST['collector_type'] ?? 'faculty');
    $collector_name_p = trim((string)($_POST['submitted_by_name'] ?? ''));
    if ($collector_type_p !== 'student') {
        $collector_name_p = '';
    }
    if ($collector_type_p === 'student' && $collector_name_p === '') {
        bm_fail('Please enter the student\'s name.');
    }
    $collector_id_p = ''; // ID field removed from this form

    $stmt_ins_p = $conn->prepare(
        "INSERT INTO tbl_requests
         (faculty_name, faculty_id, equipment_name, instructor, room,
          borrow_date, return_date, borrow_time, return_time, borrow_qty, room_id,
          booking_mode, stock_applied, status, request_date, submitted_as,
          submitted_by_name, submitted_by_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'personal', ?, ?)"
    );
    $stmt_ins_p->bind_param(
        'sssssssssiisss',
        $faculty_name,
        $faculty_id,
        $equipment_name,
        $instructor,
        $room,
        $borrow_date,
        $return_date,
        $bk['borrow_time'],
        $bk['return_time'],
        $bk['qty'],
        $room_id,
        $bk['mode'],
        $collector_name_p,
        $collector_id_p
    );
    if (!$stmt_ins_p->execute()) {
        error_log('[PUPSync] faculty-dashboard borrow insert failed: ' . $conn->error);
        $stmt_ins_p->close();
        die("Error processing request. Please try again later.");
    }
    $new_request_id = $conn->insert_id;
    $stmt_ins_p->close();

    // ── Save the request letter and link it to the request ────────────────
    if ($has_letter) {
        $orig_name = basename($_FILES['request_document']['name']);
        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
        $dest_name = time() . '_' . $faculty_id . '_' . $safe_name;
        $dest_path = PS_ROOT . '/uploads/request_letters/' . $dest_name;
        $rel_path  = 'uploads/request_letters/' . $dest_name;

        if (move_uploaded_file($_FILES['request_document']['tmp_name'], $dest_path)) {
            $stmt_doc = $conn->prepare('UPDATE tbl_requests SET document_path = ? WHERE id = ?');
            if ($stmt_doc) {
                $stmt_doc->bind_param('si', $rel_path, $new_request_id);
                $stmt_doc->execute();
                $stmt_doc->close();
            }
        }
    }

    // ── Same day: the system decides now ──────────────────────────────────
    if ($is_today) {
        require_once __DIR__ . '/arbitration-engine.php';
        ArbitrationEngine::process($conn, $new_request_id);
        $declined_reason = ArbitrationEngine::discardIfDeclined($conn, $new_request_id);
        if ($declined_reason !== null) {
            bm_fail($declined_reason);   // a declined request is never kept; tell the faculty why
        }
        $st = $conn->prepare('SELECT status FROM tbl_requests WHERE id = ? LIMIT 1');
        $final_status = '';
        if ($st) {
            $st->bind_param('i', $new_request_id);
            $st->execute();
            $final_status = (string)(($st->get_result()->fetch_assoc())['status'] ?? '');
            $st->close();
        }
        if ($final_status === 'Approved') {
            // Same as an admin approval: the faculty needs their Claim / Return QR tokens.
            $return_token = bin2hex(random_bytes(32));
            $claim_token  = bin2hex(random_bytes(32));
            $tok = $conn->prepare('UPDATE tbl_requests SET return_token = ?, claim_token = ?, bundle_claim_token = NULL WHERE id = ? AND claim_token IS NULL');
            if ($tok) {
                $tok->bind_param('ssi', $return_token, $claim_token, $new_request_id);
                $tok->execute();
                $tok->close();
            }
        }
        header('Location: faculty-dashboard.php?success=' . ($final_status === 'Approved' ? 'approved' : '1'));
        exit();
    }

    // ── Later date: stays Waiting — an admin reviews and approves ─────────
    header("Location: faculty-dashboard.php?success=1");
    exit();
}
