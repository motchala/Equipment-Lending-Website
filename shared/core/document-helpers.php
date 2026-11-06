<?php
// ── APPROVALS UI HELPERS (display only — no DB access) ───────────────────────
// ps_doc_info(): turns a stored document_path into a safe, display-ready
// descriptor. Only paths inside the two known upload folders are accepted
// (no "..", no odd characters), so a bad value in the DB can never become a
// link to something else. Returns null when there is no usable document.
if (!function_exists('ps_doc_info')) {
    function ps_doc_info($path)
    {
        $path = trim((string)$path);
        if ($path === '' || strpos($path, '..') !== false) return null;
        if (!preg_match('#^uploads/(request_letters|room_documents)/([A-Za-z0-9._ \-]+)$#', $path, $m)) return null;
        $base = $m[2];
        $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        $kind = $ext === 'pdf' ? 'pdf' : (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? 'image' : 'file');
        return [
            'url'  => 'uploads/' . $m[1] . '/' . rawurlencode($base),
            'open_url' => 'shared/document-viewer.php?file=' . rawurlencode($path),
            // hide the upload timestamp prefix (e.g. "1790900630_" or "20261002_081929_")
            'name' => preg_replace('/^(\d{8}_\d{6}_|\d{9,}_)/', '', $base),
            'kind' => $kind,
            'ext'  => strtoupper($ext),
        ];
    }
}
// ps_submitted_as_label(): human label for tbl_requests / tbl_room_reservations.submitted_as
if (!function_exists('ps_submitted_as_label')) {
    function ps_submitted_as_label($v)
    {
        switch ((string)$v) {
            case 'student':
                return 'Student (via code)';
            default:
                return 'Faculty';
        }
    }
}
// ps_doc_icon(): Material Symbols icon name for a document kind
if (!function_exists('ps_doc_icon')) {
    function ps_doc_icon($kind)
    {
        return $kind === 'pdf' ? 'picture_as_pdf' : ($kind === 'image' ? 'image' : 'draft');
    }
}

// ps_store_request_letter(): validates and stores an optional request letter (PDF / JPG / PNG / WEBP,
// max 5 MB). Returns the relative path to save in document_path (under uploads/request_letters or
// uploads/room_documents), or null when no file was sent or the file was refused (then $error says why;
// it stays null when there was simply nothing to store).
if (!function_exists('ps_store_request_letter')) {
    function ps_store_request_letter($file, $rel_dir, $owner_tag, &$error = null)
    {
        $error = null;
        if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!in_array($rel_dir, ['uploads/request_letters', 'uploads/room_documents'], true)) {
            $error = 'The request letter could not be saved.';
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'The request letter could not be uploaded. Please try again.';
            return null;
        }
        if ((int)$file['size'] > 5 * 1024 * 1024) {
            $error = 'The request letter is too large. Maximum size is 5 MB.';
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);
        $ext_by_mime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($ext_by_mime[$mime])) {
            $error = 'Unsupported letter type. Please upload a PDF, JPG, PNG or WEBP file.';
            return null;
        }
        $dir_abs = dirname(__DIR__, 2) . '/' . $rel_dir . '/';
        if (!is_dir($dir_abs) && !@mkdir($dir_abs, 0755, true)) {
            $error = 'The request letter could not be saved. Please try again.';
            return null;
        }
        $tag  = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$owner_tag);
        $name = date('Ymd_His') . '_' . ($tag !== '' ? $tag . '_' : '') . 'letter_' . bin2hex(random_bytes(4)) . '.' . $ext_by_mime[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir_abs . $name)) {
            error_log('[PUPSync] ps_store_request_letter: move_uploaded_file failed for ' . $name);
            $error = 'The request letter could not be saved. Please try again.';
            return null;
        }
        return $rel_dir . '/' . $name;
    }
}
