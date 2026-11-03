<?php
// ── Inventory tbody fragment ────────────────────────────────────────────
// Returns JSON with rendered <tbody> HTML for active and archived inventory.
// Called by admin-dashboard.php inline scripts after any inventory AJAX mutation.
if (($_GET['fragment'] ?? '') === 'inventory-tbody') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $inv_rows = [];
    $inv_result = $conn->query("SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY created_at DESC");
    if ($inv_result) {
        while ($r = $inv_result->fetch_assoc()) $inv_rows[] = $r;
    }

    $arch_rows = [];
    $arch_result = $conn->query("SELECT * FROM tbl_inventory WHERE is_archived = 1 ORDER BY item_name ASC");
    if ($arch_result) {
        while ($r = $arch_result->fetch_assoc()) $arch_rows[] = $r;
    }

    // Render active tbody
    ob_start();
    if (empty($inv_rows)): ?>
        <tr id="inv-empty-row">
            <td colspan="5" class="inv-empty-cell">
                <span class="material-symbols-outlined">inventory_2</span>
                No equipment yet. Add your first item using the form.
            </td>
        </tr>
        <?php else:
        foreach ($inv_rows as $item):
            $inv_qty       = (int)$item['quantity'];
            $inv_cond      = $item['condition'] ?? 'Good';
            $inv_condClass = ($inv_cond === 'For Repair') ? 'repair' : (($inv_cond === 'Fair') ? 'fair' : 'good');
            if ($inv_qty === 0) {
                $inv_stockClass = 'none';
                $inv_stockLabel = 'No stock';
            } elseif ($inv_qty <= 2) {
                $inv_stockClass = 'low';
                $inv_stockLabel = 'Low stock';
            } else {
                $inv_stockClass = 'ok';
                $inv_stockLabel = 'In stock';
            }
            $inv_addedTs = !empty($item['created_at']) ? strtotime($item['created_at']) : false;
        ?>
            <tr class="inv-row-item"
                data-item-id="<?php echo (int)$item['item_id']; ?>"
                data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                data-item-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                data-item-quantity="<?php echo $inv_qty; ?>"
                data-item-condition="<?php echo htmlspecialchars($inv_cond, ENT_QUOTES); ?>"
                data-item-image="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES); ?>"
                data-item-image-full="<?php echo htmlspecialchars($root_url . $item['image_path'], ENT_QUOTES); ?>">
                <td>
                    <div class="fac-person">
                        <span class="inv-thumb">
                            <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                loading="lazy">
                            <span class="material-symbols-outlined inv-thumb-ph">inventory_2</span>
                        </span>
                        <div class="fac-person-text">
                            <div class="fac-person-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <?php if ($inv_addedTs): ?><div class="fac-person-sub">Added <?php echo date('M j, Y', $inv_addedTs); ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><span class="inv-pill inv-pill--cat"><?php echo htmlspecialchars($item['category']); ?></span></td>
                <td><span class="inv-pill inv-pill--<?php echo $inv_condClass; ?>"><?php echo htmlspecialchars($inv_cond); ?></span></td>
                <td><span class="inv-pill inv-pill--stock inv-pill--<?php echo $inv_stockClass; ?>"><strong><?php echo $inv_qty; ?></strong> <?php echo $inv_stockLabel; ?></span></td>
                <td class="fac-actions">
                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm inv-edit-btn"
                        data-action="eq-open-edit" aria-label="Edit equipment">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr id="inv-nomatch-row" class="inv-row-hidden">
            <td colspan="5" class="inv-empty-cell">
                <span class="material-symbols-outlined">search_off</span>
                No equipment matches your search.
            </td>
        </tr>
    <?php endif;
    $active_html = ob_get_clean();

    // Render archived tbody
    ob_start();
    if (empty($arch_rows)): ?>
        <tr>
            <td colspan="4" class="inv-empty-cell">No archived items.</td>
        </tr>
        <?php else:
        foreach ($arch_rows as $item): ?>
            <tr>
                <td><img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>" class="item-img" onerror="this.src='../uploads/default.png'"></td>
                <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo htmlspecialchars($item['category']); ?></td>
                <td class="action-cell">
                    <div class="action-btns">
                        <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>"
                            class="btn-action btn-restore" title="Restore">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="1 4 1 10 7 10" />
                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                            </svg>
                        </a>
                        <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>"
                            class="btn-action btn-force-del" title="Delete permanently">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14H6L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                                <path d="M9 6V4h6v2" />
                            </svg>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach;
    endif;
    $archive_html = ob_get_clean();

    echo json_encode([
        'status'        => 'success',
        'active_html'   => $active_html,
        'archive_html'  => $archive_html,
        'active_count'  => count($inv_rows),
        'archive_count' => count($arch_rows),
    ]);
    exit();
}
