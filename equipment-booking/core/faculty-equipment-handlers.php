<?php
// ── Handle Borrow Request ──────────────────────────────────────────────────
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name']) || isset($_POST['submitted_as'])) {
    if (!isset($_SESSION['faculty_id'])) die("Unauthorized access");

    $submitted_as_post = $_POST['submitted_as'] ?? '';

    // ── ADVISER BRANCH ────────────────────────────────────────────────────
    if ($submitted_as_post === 'adviser') {

        // Org privileges come from the adviser role itself (read fresh from the DB here,
        // never from a form field): only an Organization Adviser may submit for an organization.
        $stmt_gate = $conn->prepare("SELECT role FROM tbl_users WHERE faculty_id = ? LIMIT 1");
        $stmt_gate->bind_param('s', $_SESSION['faculty_id']);
        $stmt_gate->execute();
        $gate_row = $stmt_gate->get_result()->fetch_assoc();
        $stmt_gate->close();
        if (($gate_row['role'] ?? '') !== 'Organization Adviser') {
            die("Error: Organisation borrowing not permitted for this account.");
        }

        // CSRF check before reading any $_POST / $_FILES (Requirement 10.2)
        csrf_verify();

        // Collect faculty info via prepared statement
        $user_id = $_SESSION['faculty_id'];
        $stmt_usr = $conn->prepare("SELECT fullname, faculty_id FROM tbl_users WHERE faculty_id = ? LIMIT 1");
        $stmt_usr->bind_param('s', $user_id);
        $stmt_usr->execute();
        $adv_user = $stmt_usr->get_result()->fetch_assoc();
        $stmt_usr->close();
        if (!$adv_user) die("User profile not found.");
        $faculty_name = $adv_user['fullname'];
        $faculty_id   = $adv_user['faculty_id'];

        // Where + when + which items. Everything is validated BEFORE anything is saved
        // (including the optional letter), so one bad item never leaves a half-filed batch.
        $instructor = $faculty_name; // auto-filled from account name
        $rr = BookingSchedule::resolveRoom($conn, (string)($_POST['room_id'] ?? ''));
        if (!$rr['ok']) bm_fail($rr['error']);
        $room    = $rr['label'];
        $room_id = $rr['id'];            // null = "Outside"

        $raw_items = $_POST['items'] ?? [];
        if (!is_array($raw_items) || empty($raw_items)) bm_fail('Please select at least one item.');
        $qty_post = (isset($_POST['qty']) && is_array($_POST['qty'])) ? $_POST['qty'] : [];
        $plan = [];
        $seen = [];
        foreach ($raw_items as $raw) {
            $name = trim((string)$raw);
            if ($name === '' || isset($seen[$name])) continue;
            $seen[$name] = true;
            $chk = BookingSchedule::parse($conn, $_POST, true, $name, max(1, (int)($qty_post[$name] ?? 1)));
            if (!$chk['ok']) bm_fail($name . ': ' . $chk['error']);
            $bk = $chk['data'];
            if (BookingSchedule::freeUnits($conn, $name, $bk['start_ts'], $bk['end_ts']) < $bk['qty']) {
                $next = $bk['mode'] === 'future'
                    ? BookingSchedule::earliestFreeDate(
                        $conn,
                        $name,
                        $bk['qty'],
                        substr($bk['borrow_time'], 0, 5),
                        (int)($_POST['duration'] ?? 1),
                        date('Y-m-d', strtotime($bk['borrow_date'] . ' +1 day'))
                    )
                    : null;
                bm_fail('"' . $name . '" is already booked for that schedule.'
                    . ($next ? ' The next free start is ' . date('D, M j', strtotime($next)) . '.' : ''));
            }
            $plan[] = ['name' => $name, 'qty' => $bk['qty'], 'bk' => $bk];
        }
        if (empty($plan)) bm_fail('Please select at least one item.');
        // Equipment cannot go to a room that another faculty has reserved for that time
        if ($room_id !== null) {
            $room_msg = BookingSchedule::roomConflictMessage($conn, $room_id, $plan[0]['bk']['start_ts'], $plan[0]['bk']['end_ts'], (string)$faculty_id);
            if ($room_msg) bm_fail($room_msg);
        }
        $borrow_date = $plan[0]['bk']['borrow_date'];
        $return_date = $plan[0]['bk']['return_date'];

        // The request letter is OPTIONAL for organization requests. If one is attached it is
        // validated and stored; without one the request is filed with no document (it just
        // gets no letter-based priority from the arbitration engine).
        $rel_path = null;
        if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['request_document']['error'] !== UPLOAD_ERR_OK) {
                die("File upload error. Please try again.");
            }
            if ($_FILES['request_document']['size'] > 5 * 1024 * 1024) {
                die("File too large. Maximum size is 5 MB.");
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['request_document']['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed_mimes, true)) {
                die("Unsupported file type. Please upload a PDF, JPG, PNG, or WEBP file.");
            }

            $orig_name = basename($_FILES['request_document']['name']);
            $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
            $dest_name = time() . '_' . $faculty_id . '_' . $safe_name;
            $dest_dir  = PS_ROOT . '/uploads/request_letters/';
            $dest_path = $dest_dir . $dest_name;
            if (!move_uploaded_file($_FILES['request_document']['tmp_name'], $dest_path)) {
                die("Error: Could not save uploaded document.");
            }
            $rel_path = 'uploads/request_letters/' . $dest_name;
        }

        // Items were validated above ($plan)
        $items = array_column($plan, 'name');

        // Generate batch UUID (Requirement 4.5)
        $batch_id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        // Loop: one INSERT per selected item (Requirement 4.5, 3.3)
        $declined_items = [];
        $kept_items     = 0;
        foreach ($plan as $pl) {
            $item_name = $pl['name'];
            $bk        = $pl['bk'];

            $stmt_ins = $conn->prepare(
                "INSERT INTO tbl_requests
                 (faculty_name, faculty_id, equipment_name, instructor, room,
                  borrow_date, return_date, borrow_time, return_time, borrow_qty, room_id,
                  booking_mode, stock_applied, status, request_date,
                  submitted_as, batch_id, document_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'adviser', ?, ?)"
            );
            $stmt_ins->bind_param(
                'sssssssssiisss',
                $faculty_name,
                $faculty_id,
                $item_name,
                $instructor,
                $room,
                $borrow_date,
                $return_date,
                $bk['borrow_time'],
                $bk['return_time'],
                $bk['qty'],
                $room_id,
                $bk['mode'],
                $batch_id,
                $rel_path
            );
            $stmt_ins->execute();
            $new_request_id = $conn->insert_id;
            $stmt_ins->close();
            // An Organization Adviser request with a supporting letter is a
            // Super-Admin review item.  It must not be auto-decided by the
            // arbitration engine before that review happens.
            if ($rel_path !== null) {
                $hold = $conn->prepare("UPDATE tbl_requests SET reason = 'Awaiting Super Admin review.' WHERE id = ? AND status = 'Waiting'");
                if ($hold) {
                    $hold->bind_param('i', $new_request_id);
                    $hold->execute();
                    $hold->close();
                }
                $kept_items++;
                continue;
            }

            ArbitrationEngine::process($conn, $new_request_id);
            // A declined request is never kept: drop it and report the reason on the spot.
            $why_declined = ArbitrationEngine::discardIfDeclined($conn, $new_request_id);
            if ($why_declined !== null) {
                $declined_items[] = '"' . $item_name . '": ' . $why_declined;
                continue;
            }
            $kept_items++;

            // ── Generate return token if engine approved this item ────────────
            $chk = $conn->prepare(
                "SELECT id FROM tbl_requests WHERE id = ? AND status = 'Approved' AND return_token IS NULL LIMIT 1"
            );
            if ($chk) {
                $chk->bind_param('i', $new_request_id);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows > 0) {
                    $auto_token = bin2hex(random_bytes(32));
                    $tok = $conn->prepare(
                        "UPDATE tbl_requests SET return_token = ? WHERE id = ? AND status = 'Approved' AND return_token IS NULL"
                    );
                    if ($tok) {
                        $tok->bind_param('si', $auto_token, $new_request_id);
                        $tok->execute();
                        $tok->close();
                    }
                }
                $chk->close();
            }
        }

        if ($declined_items) {
            // Nothing is logged for the declined items: say why, right here.
            bm_fail('Not booked – ' . implode(' | ', $declined_items)
                . ($kept_items > 0 ? ' (The other items in your request were submitted.)' : ''));
        }
        header("Location: faculty-dashboard.php?success=1");
        exit();
    } else {
        // ── PERSONAL BRANCH (submitted_as = 'personal' OR absent — backwards-compat) ──

        csrf_verify();

        $user_id  = $_SESSION['faculty_id'];
        $stmt_usr = $conn->prepare("SELECT fullname, faculty_id, role FROM tbl_users WHERE faculty_id = ? LIMIT 1");
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
        // Limits follow the account's role, read from the DB, never from the form.
        $rr = BookingSchedule::resolveRoom($conn, (string)($_POST['room_id'] ?? ''));
        if (!$rr['ok']) bm_fail($rr['error']);
        $room    = $rr['label'];
        $room_id = $rr['id'];            // null = "Outside"

        $chk = BookingSchedule::parse(
            $conn,
            $_POST,
            BookingSchedule::isAdviserRole((string)($user['role'] ?? '')),
            $equipment_name,
            max(1, (int)($_POST['qty'] ?? 1))
        );
        if (!$chk['ok']) bm_fail($chk['error']);
        $bk = $chk['data'];
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

        // ── Document upload validation ─────────────────────────────────────────
        $document_path = null;
        if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['request_document']['error'] !== UPLOAD_ERR_OK) {
                die("File upload error. Please try again.");
            }
            if ($_FILES['request_document']['size'] > 5 * 1024 * 1024) {
                die("File too large. Maximum size is 5 MB.");
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['request_document']['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed_mimes, true)) {
                die("Unsupported file type. Please upload a PDF, JPG, PNG, or WEBP file.");
            }
            // $document_path will be set after INSERT
        }

        // Prepared INSERT — includes submitted_as = 'personal' (Requirement 3.2)
        $stmt_ins_p = $conn->prepare(
            "INSERT INTO tbl_requests
             (faculty_name, faculty_id, equipment_name, instructor, room,
              borrow_date, return_date, borrow_time, return_time, borrow_qty, room_id,
              booking_mode, stock_applied, status, request_date, submitted_as)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Waiting', NOW(), 'personal')"
        );
        $stmt_ins_p->bind_param(
            'sssssssssiis',
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
            $bk['mode']
        );
        if ($stmt_ins_p->execute()) {
            $new_request_id = $conn->insert_id;
            $stmt_ins_p->close();

            // ── Move uploaded document and update document_path ────────────────
            if (isset($_FILES['request_document']) && $_FILES['request_document']['error'] === UPLOAD_ERR_OK) {
                $orig_name = basename($_FILES['request_document']['name']);
                $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
                $dest_name = time() . '_' . $faculty_id . '_' . $safe_name;
                $dest_dir  = PS_ROOT . '/uploads/request_letters/';
                $dest_path = $dest_dir . $dest_name;
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

            ArbitrationEngine::process($conn, $new_request_id);

            // A declined request is never kept: drop it and report the reason on the spot.
            $why_declined = ArbitrationEngine::discardIfDeclined($conn, $new_request_id);
            if ($why_declined !== null) {
                bm_fail($why_declined);
            }

            // ── Generate return token if engine approved the request ──────────
            $check_approved = $conn->prepare(
                "SELECT id FROM tbl_requests WHERE id = ? AND status = 'Approved' AND return_token IS NULL LIMIT 1"
            );
            if ($check_approved) {
                $check_approved->bind_param('i', $new_request_id);
                $check_approved->execute();
                $check_approved->get_result(); // consume result
                if ($check_approved->affected_rows > 0 || $conn->affected_rows > 0) {
                    // NULL guard in the UPDATE prevents double-write
                    $auto_token = bin2hex(random_bytes(32));
                    $tok = $conn->prepare(
                        "UPDATE tbl_requests SET return_token = ? WHERE id = ? AND status = 'Approved' AND return_token IS NULL"
                    );
                    if ($tok) {
                        $tok->bind_param('si', $auto_token, $new_request_id);
                        $tok->execute();
                        $tok->close();
                    }
                }
                $check_approved->close();
            }

            header("Location: faculty-dashboard.php?success=1");
            exit();
        } else {
            error_log('[PUPSync] faculty-dashboard borrow insert failed: ' . $conn->error);
            $stmt_ins_p->close();
            die("Error processing request. Please try again later.");
        }
    }
}
