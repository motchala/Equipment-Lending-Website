<?php
// ── Faculty list tbody fragment ─────────────────────────────────────────────
// Returns JSON with rendered <tbody> HTML for the faculty accounts list.
// Called by admin-dashboard.php inline scripts after a faculty account is created.
if (($_GET['fragment'] ?? '') === 'faculty-tbody') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $fac_rows = [];
    $fac_result = $conn->query(
        "SELECT u.fullname, u.email, u.backup_email, u.faculty_id"
            . " FROM tbl_users u"
            . " ORDER BY u.fullname ASC"
    );
    if ($fac_result) {
        while ($r = $fac_result->fetch_assoc()) $fac_rows[] = $r;
    }

    ob_start();
    if (empty($fac_rows)): ?>
        <tr id="fac-empty-row">
            <td colspan="3" style="text-align:center;padding:3rem;color:var(--text-light)">
                <span class="material-symbols-outlined" style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>
                No faculty accounts yet.
            </td>
        </tr>
        <?php else:
        foreach ($fac_rows as $frow):
            $idUnset   = faculty_id_is_unset($frow['faculty_id']);
            $initFac   = strtoupper(substr(trim($frow['fullname'] ?? '') ?: 'F', 0, 1));
        ?>
            <tr
                data-fullname="<?= htmlspecialchars($frow['fullname']) ?>"
                data-email="<?= htmlspecialchars($frow['email']) ?>"
                data-backup-email="<?= htmlspecialchars($frow['backup_email'] ?? '') ?>"
                data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                data-init="<?= htmlspecialchars($initFac) ?>">
                <td>
                    <div class="fac-person">
                        <span class="fac-avatar"><?= htmlspecialchars($initFac) ?></span>
                        <div class="fac-person-text">
                            <div class="fac-person-name"><?= htmlspecialchars($frow['fullname']) ?></div>
                            <div class="fac-person-sub"><?= htmlspecialchars($frow['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($idUnset): ?>
                        <span class="fac-id-pending">Not set yet</span>
                    <?php else: ?>
                        <span class="fac-id"><?= htmlspecialchars($frow['faculty_id']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="fac-actions">
                    <button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn" aria-label="Edit account">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                </td>
            </tr>
<?php endforeach;
    endif;
    $faculty_html = ob_get_clean();

    echo json_encode([
        'status'        => 'success',
        'faculty_html'  => $faculty_html,
        'faculty_count' => count($fac_rows),
    ]);
    exit();
}
