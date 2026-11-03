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
            case 'adviser':
                return 'Org Adviser';
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
