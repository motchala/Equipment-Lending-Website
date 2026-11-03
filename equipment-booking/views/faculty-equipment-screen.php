            <!-- ============================================================
         TAB: EQUIPMENT (LENDING)
    ============================================================ -->
            <div class="tab-panel" id="panel-lending">


                <!-- ── Sub: Browse ─────────────────────────────────────── -->
                <div class="lending-sub active" id="lending-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Browse Equipment</h2>
                        <p class="page-subtitle">Browse the catalog, check availability and request equipment for your class.</p>
                    </div>

                    <!-- ── Featured Section ────────────────────────────────── -->
                    <?php
                    // Featured = the 2 most-borrowed items overall (that are still
                    // available to request). "Borrowed" counts requests that were
                    // actually granted — Approved, Overdue, or Returned — not ones
                    // still pending or that got declined.
                    mysqli_data_seek($inventory_result, 0);
                    $all_items = [];
                    while ($row = mysqli_fetch_assoc($inventory_result)) $all_items[] = $row;

                    $equip_borrow_count = [];
                    $borrow_count_res = mysqli_query(
                        $conn,
                        "SELECT equipment_name, COUNT(*) as cnt FROM tbl_requests
                         WHERE status IN ('Approved','Overdue','Returned')
                         GROUP BY equipment_name"
                    );
                    if ($borrow_count_res) {
                        while ($bcr = mysqli_fetch_assoc($borrow_count_res)) {
                            $equip_borrow_count[strtolower(trim($bcr['equipment_name']))] = (int)$bcr['cnt'];
                        }
                    }
                    function actBorrowCount(string $itemName): int
                    {
                        global $equip_borrow_count;
                        return $equip_borrow_count[strtolower(trim($itemName))] ?? 0;
                    }

                    $featured_avail = array_filter($all_items, fn($r) => $r['quantity'] > 0);
                    usort($featured_avail, function ($a, $b) {
                        $diff = actBorrowCount($b['item_name']) - actBorrowCount($a['item_name']);
                        return $diff !== 0 ? $diff : ($b['quantity'] - $a['quantity']);
                    });
                    $featured_hero = $featured_avail[0] ?? null;
                    $featured_sec  = $featured_avail[1] ?? null;
                    if ($featured_hero || $featured_sec):
                    ?>
                        <div class="featured-section">
                            <div class="featured-label">
                                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">local_fire_department</span>
                                Most Borrowed
                            </div>
                            <div class="featured-grid">
                                <!-- Hero Featured -->
                                <?php if ($featured_hero): ?>
                                    <div class="feat-hero">
                                        <div class="feat-hero-body">
                                            <div>
                                                <div class="feat-hero-meta">
                                                    <span class="stock-badge stock-avail" style="margin-bottom:0;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                        <?php echo (int)$featured_hero['quantity']; ?> available
                                                    </span>
                                                    <?php $heroBorrowCount = actBorrowCount($featured_hero['item_name']);
                                                    if ($heroBorrowCount > 0): ?>
                                                        <span class="stock-badge stock-popular" style="margin-bottom:0;">
                                                            <span class="material-symbols-outlined" style="font-size:12px;">local_fire_department</span>
                                                            Borrowed <?php echo $heroBorrowCount; ?>&times;
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="feat-hero-title" style="margin-top:12px;"><?php echo htmlspecialchars($featured_hero['item_name']); ?></div>
                                                <div class="feat-hero-desc" style="margin-top:6px;"><?php echo !empty($featured_hero['description']) ? htmlspecialchars(mb_substr($featured_hero['description'], 0, 110)) . (mb_strlen($featured_hero['description']) > 110 ? '…' : '') : 'Available for borrowing. Submit a request to reserve this item for your class or event.'; ?></div>
                                            </div>
                                            <div class="feat-hero-actions" style="margin-top:20px;">
                                                <?php if ($has_overdue_block): ?>
                                                    <button class="btn-borrow-primary" disabled>
                                                        <span class="material-symbols-outlined">block</span> Overdue Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-borrow-primary"
                                                        data-action="open-borrow-form"
                                                        data-item="<?php echo htmlspecialchars($featured_hero['item_name'], ENT_QUOTES); ?>">
                                                        <span class="material-symbols-outlined">add_circle</span> Borrow Now
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($featured_hero['image_path'])): ?>
                                            <div class="feat-hero-img">
                                                <img src="<?php echo $root_url . htmlspecialchars($featured_hero['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured_hero['item_name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="feat-hero-img-placeholder">
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Secondary Featured -->
                                <?php if ($featured_sec): ?>
                                    <div class="feat-secondary">
                                        <div class="feat-secondary-img" style="position:relative;">
                                            <?php if (!empty($featured_sec['image_path'])): ?>
                                                <img src="<?php echo $root_url . htmlspecialchars($featured_sec['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured_sec['item_name']); ?>">
                                            <?php else: ?>
                                                <span class="material-symbols-outlined">inventory_2</span>
                                            <?php endif; ?>
                                            <span class="stock-badge stock-avail"
                                                style="position:absolute;top:10px;left:10px;margin-bottom:0;z-index:2;background:rgba(209,250,229,.95);backdrop-filter:blur(4px);">
                                                <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                <?php echo (int)$featured_sec['quantity']; ?> available
                                            </span>
                                            <?php $secBorrowCount = actBorrowCount($featured_sec['item_name']);
                                            if ($secBorrowCount > 0): ?>
                                                <span class="stock-badge stock-popular"
                                                    style="position:absolute;top:10px;right:10px;margin-bottom:0;z-index:2;background:rgba(255,255,255,.85);backdrop-filter:blur(4px);">
                                                    <span class="material-symbols-outlined" style="font-size:12px;">local_fire_department</span>
                                                    <?php echo $secBorrowCount; ?>&times;
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="feat-secondary-body">
                                            <div class="feat-secondary-title"><?php echo htmlspecialchars($featured_sec['item_name']); ?></div>
                                            <div class="feat-secondary-cat">
                                                <span class="material-symbols-outlined">label</span>
                                                <?php echo htmlspecialchars($featured_sec['category']); ?>
                                            </div>
                                            <div style="margin-top:auto;">
                                                <?php if ($has_overdue_block): ?>
                                                    <button class="btn-borrow-primary" style="width:100%;" disabled>
                                                        <span class="material-symbols-outlined" aria-hidden="true">block</span> Overdue Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-borrow-primary" style="width:100%;justify-content:center;"
                                                        data-action="open-borrow-form"
                                                        data-item="<?php echo htmlspecialchars($featured_sec['item_name'], ENT_QUOTES); ?>">
                                                        <span class="material-symbols-outlined" aria-hidden="true">add_shopping_cart</span> Borrow
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ── All Equipment Catalog ───────────────────────────── -->
                    <div class="catalog-card">
                        <div class="catalog-section-header">
                            <span class="catalog-section-title">All Equipment</span>
                            <span class="catalog-count-chip" id="equipCountChip">
                                <?php echo count($all_items); ?> items
                            </span>
                        </div>
                        <div class="catalog-filters">
                            <div class="catalog-search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="equipmentSearch" placeholder="Search by equipment name…" autocomplete="off" aria-label="Search equipment by name">
                                <button type="button" class="catalog-search-clear" id="equipmentSearchClear" data-action="equip-search-clear" aria-label="Clear search" hidden>
                                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                </button>
                            </div>
                            <select id="categoryFilter" class="catalog-filter-select" aria-label="Filter by category">
                                <option value="">All Categories</option>
                                <?php
                                mysqli_data_seek($category_result, 0);
                                while ($cat = mysqli_fetch_assoc($category_result)) {
                                    if (strtolower($cat['category']) === 'others') continue;
                                    echo '<option value="' . htmlspecialchars($cat['category']) . '">' . htmlspecialchars($cat['category']) . '</option>';
                                }
                                ?>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="eq-grid" id="equipmentList">
                            <?php if (empty($all_items)): ?>
                                <div class="eq-empty">
                                    <span class="material-symbols-outlined">inventory_2</span>
                                    <p>No equipment available at the moment.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($all_items as $item): ?>
                                    <div class="eq-item-card item-node"
                                        data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>"
                                        data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>"
                                        data-item-id="<?php echo (int)$item['item_id']; ?>">
                                        <div class="eq-item-img-wrap">
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img class="eq-item-img"
                                                    src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <?php else: ?>
                                                <div class="eq-item-img-placeholder">
                                                    <span class="material-symbols-outlined">inventory_2</span>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Stock badge overlay on image -->
                                            <div class="eq-stock-overlay">
                                                <?php if ($item['quantity'] > 0): ?>
                                                    <span class="stock-badge stock-avail" style="margin-bottom:0;backdrop-filter:blur(4px);background:var(--color-success-container) !important;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                                                        <?php echo (int)$item['quantity']; ?> available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-badge stock-unavail" style="margin-bottom:0;backdrop-filter:blur(4px);background:var(--color-error-container) !important;">
                                                        <span class="material-symbols-outlined" style="font-size:12px;">cancel</span>
                                                        Out of stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="eq-item-body">
                                            <!-- Item Code chevron — top right of card body -->
                                            <button type="button"
                                                class="eq-ic-btn<?php echo ((int)$item['quantity'] <= 0) ? ' eq-ic-btn--disabled' : ''; ?>"
                                                data-action="toggle-item-code"
                                                data-item-id="<?php echo (int)$item['item_id']; ?>"
                                                data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                                                <?php if ((int)$item['quantity'] <= 0): ?>
                                                disabled
                                                title="No stock available — code cannot be used"
                                                <?php else: ?>
                                                title="Show item code for students"
                                                <?php endif; ?>
                                                aria-label="Show item code"
                                                aria-expanded="false">
                                                <span class="material-symbols-outlined eq-ic-chevron">expand_more</span>
                                            </button>

                                            <!-- Item code reveal panel (hidden by default) -->
                                            <div class="eq-ic-panel" id="eq-ic-panel-<?php echo (int)$item['item_id']; ?>" style="display:none">
                                                <div class="eq-ic-loading" style="display:none">
                                                    <span class="material-symbols-outlined" style="animation:spin .8s linear infinite;font-size:16px">progress_activity</span>
                                                    Loading…
                                                </div>
                                                <div class="eq-ic-content" style="display:none">
                                                    <span class="eq-ic-label">Item Code</span>
                                                    <div class="eq-ic-code-row">
                                                        <code class="eq-ic-code">••••••••••••</code>
                                                        <button type="button" class="eq-ic-reveal-btn" data-action="reveal-item-code" title="Show code">
                                                            <span class="material-symbols-outlined">visibility</span>
                                                        </button>
                                                        <button type="button" class="eq-ic-copy-btn" data-action="copy-item-code" title="Copy code">
                                                            <span class="material-symbols-outlined">content_copy</span>
                                                        </button>
                                                    </div>
                                                    <div class="eq-ic-used-notice" style="display:none">
                                                        <span class="material-symbols-outlined" style="font-size:13px;color:var(--color-warning,#e08000)">info</span>
                                                        Used — a new code is ready above
                                                    </div>
                                                    <button type="button" class="eq-ic-regen-btn" data-action="regen-item-code"
                                                        data-item-id="<?php echo (int)$item['item_id']; ?>">
                                                        <span class="material-symbols-outlined">refresh</span> New code
                                                    </button>
                                                </div>
                                                <div class="eq-ic-error" style="display:none;color:var(--color-error,#c62828);font-size:11px;padding:4px 0"></div>
                                            </div>

                                            <div class="eq-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="eq-item-cat">
                                                <span class="material-symbols-outlined">label</span>
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </div>

                                            <?php if ($has_overdue_block): ?>
                                                <button class="btn-borrow btn-borrow-blocked" disabled
                                                    title="You have an overdue item. Return it before borrowing again.">
                                                    <span class="material-symbols-outlined" aria-hidden="true">block</span>
                                                    <span class="btn-borrow-label">Overdue Block</span>
                                                </button>
                                            <?php else: ?>
                                                <?php
                                                $eq_shelf = (int)$item['quantity'];
                                                $eq_total = (int)(($bk_items[$item['item_name']]['total'] ?? $eq_shelf));
                                                $eq_ahead = ($eq_shelf <= 0 && $eq_total > 0);   // out now, but it exists: can be booked for later
                                                ?>
                                                <button class="btn-borrow<?php echo $eq_ahead ? ' btn-borrow-ahead' : ''; ?>"
                                                    <?php if ($eq_shelf <= 0 && !$eq_ahead) echo 'disabled'; ?>
                                                    data-action="open-borrow-form"
                                                    data-item="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>">
                                                    <span class="material-symbols-outlined" aria-hidden="true"><?php echo ($eq_shelf > 0) ? 'add_shopping_cart' : ($eq_ahead ? 'event' : 'block'); ?></span>
                                                    <span class="btn-borrow-label"><?php echo ($eq_shelf > 0) ? 'Borrow' : ($eq_ahead ? 'Book ahead' : 'Unavailable'); ?></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- No results (search/filter matched nothing) -->
                        <div class="eq-empty" id="equipNoResults" style="display:none;">
                            <span class="material-symbols-outlined">search_off</span>
                            <p>No equipment matches your search.</p>
                        </div>

                        <!-- ── Pagination (12 items per page; featured cards above are not counted) ── -->
                        <div class="eq-pagination" id="equipPagination" style="display:none;">
                            <div class="eq-pg-info" id="equipPageInfo"></div>
                            <div class="eq-pg-controls">
                                <button class="eq-pg-btn" id="equipPrevBtn" data-action="equip-page-prev"
                                    aria-label="Previous page">
                                    <span class="material-symbols-outlined">chevron_left</span>
                                </button>
                                <div class="eq-pg-numbers" id="equipPageNumbers"></div>
                                <button class="eq-pg-btn" id="equipNextBtn" data-action="equip-page-next"
                                    aria-label="Next page">
                                    <span class="material-symbols-outlined">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div><!-- /lending-browse -->

                <!-- ── Sub: Borrow Form (now a modal — this sub-panel is kept as an
                     empty shell so existing references to it don't break) ── -->
                <div class="lending-sub" id="lending-form"></div><!-- /lending-form -->


            </div><!-- /panel-lending -->

