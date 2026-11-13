            <!-- ============================================================
         TAB: EQUIPMENT (LENDING)
    ============================================================ -->
            <?php require_once dirname(__DIR__, 2) . '/shared/core/equipment-image.php'; // no photo -> uploads/default.png ?>
            <div class="tab-panel" id="panel-lending">


                <!-- ── Sub: Browse ─────────────────────────────────────── -->
                <div class="lending-sub active" id="lending-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Borrow Equipment</h2>
                        <p class="page-subtitle">Add what you need, then choose where and when to use it.</p>
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

                    // One "add to my request" control per item. The card is the same everywhere
                    // (Most Borrowed cards and the catalog): JS (faculty-borrow-flow.js) swaps the
                    // Add button for a quantity stepper once the item is picked, and keeps every
                    // copy of the same item in sync. The first paint below is what shows before JS runs.
                    if (!function_exists('fbAddSlot')) {
                        function fbAddSlot(array $item, bool $blocked, array $bk_items, string $variant = ''): string
                        {
                            $name  = (string)$item['item_name'];
                            $shelf = (int)$item['quantity'];
                            $total = (int)($bk_items[$name]['total'] ?? $shelf);
                            $ahead = ($shelf <= 0 && $total > 0);   // out now, but it exists: can be booked for later
                            $h     = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
                            $cls   = 'bf-slot' . ($variant !== '' ? ' bf-slot--' . $variant : '');
                            $btn   = $variant === 'hero' ? 'btn-borrow-primary' : 'btn-borrow';   // the big featured button keeps its own look
                            $attrs = ' data-bf-slot data-item-id="' . (int)$item['item_id'] . '" data-item-name="' . $h($name) . '"'
                                   . ($blocked ? ' data-blocked="1"' : '');
                            if ($blocked) {
                                return '<div class="' . $cls . '"' . $attrs . '><button type="button" class="' . $btn . ' btn-borrow-blocked bf-add" disabled'
                                     . ' title="You have an overdue item. Return it before borrowing again.">'
                                     . '<span class="material-symbols-outlined" aria-hidden="true">block</span>'
                                     . '<span class="btn-borrow-label">Overdue Block</span></button></div>';
                            }
                            if ($shelf <= 0 && !$ahead) {
                                return '<div class="' . $cls . '"' . $attrs . '><button type="button" class="' . $btn . ' bf-add" disabled>'
                                     . '<span class="material-symbols-outlined" aria-hidden="true">block</span>'
                                     . '<span class="btn-borrow-label">Unavailable</span></button></div>';
                            }
                            return '<div class="' . $cls . '"' . $attrs . '><button type="button" class="' . $btn . ' bf-add' . ($ahead ? ' btn-borrow-ahead' : '') . '" data-bf="add">'
                                 . '<span class="material-symbols-outlined" aria-hidden="true">' . ($ahead ? 'event' : 'add') . '</span>'
                                 . '<span class="btn-borrow-label">' . ($ahead ? 'Book ahead' : ($variant === 'hero' ? 'Add to request' : 'Add')) . '</span></button></div>';
                        }
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
                        <div class="featured-section" id="featuredSection">
                            <div class="featured-label">
                                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">local_fire_department</span>
                                Most Borrowed
                            </div>
                            <div class="featured-grid">
                                <!-- Hero Featured -->
                                <?php if ($featured_hero): ?>
                                    <div class="feat-hero" data-campus="<?php echo (int)($featured_hero['campus_id'] ?? 1); ?>">
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
                                                <?= fbAddSlot($featured_hero, (bool)$has_overdue_block, $bk_items, 'hero') ?>
                                            </div>
                                        </div>
                                        <div class="feat-hero-img">
                                            <img src="<?php echo $root_url . htmlspecialchars(equipment_image_path($featured_hero['image_path'] ?? '')); ?>"
                                                alt="<?php echo htmlspecialchars($featured_hero['item_name']); ?>">
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Secondary Featured -->
                                <?php if ($featured_sec): ?>
                                    <div class="feat-secondary" data-campus="<?php echo (int)($featured_sec['campus_id'] ?? 1); ?>">
                                        <div class="feat-secondary-img" style="position:relative;">
                                            <img src="<?php echo $root_url . htmlspecialchars(equipment_image_path($featured_sec['image_path'] ?? '')); ?>"
                                                alt="<?php echo htmlspecialchars($featured_sec['item_name']); ?>">
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
                                                <?= fbAddSlot($featured_sec, (bool)$has_overdue_block, $bk_items, 'block') ?>
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

                        <!-- Campus tabs -->
                        <div class="eq-campus-tabs" role="tablist" aria-label="Filter by campus">
                            <button type="button" class="eq-campus-tab active" data-campus="" role="tab" aria-selected="true">
                                <span class="material-symbols-outlined">layers</span> All Campuses
                            </button>
                            <?php foreach ($campuses_list as $campus): ?>
                                <button type="button" class="eq-campus-tab" data-campus="<?php echo (int)$campus['campus_id']; ?>" role="tab" aria-selected="false">
                                    <span class="material-symbols-outlined">location_city</span>
                                    <?php echo htmlspecialchars($campus['campus_name']); ?>
                                </button>
                            <?php endforeach; ?>
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
                                // Build campus-scoped category options so JS can show/hide them when a campus tab is selected
                                $cat_by_campus = [];
                                mysqli_data_seek($category_result, 0);
                                while ($cat = mysqli_fetch_assoc($category_result)) {
                                    $cat_by_campus[(int)$cat['campus_id']][] = $cat['category'];
                                }
                                // Emit one optgroup per campus so the JS filter can target data-campus-id
                                foreach ($campuses_list as $campus):
                                    $cid = (int)$campus['campus_id'];
                                    $cats_for_campus = $cat_by_campus[$cid] ?? [];
                                    if (empty($cats_for_campus)) continue;
                                    echo '<optgroup label="' . htmlspecialchars($campus['campus_name']) . '" data-campus-id="' . $cid . '">';
                                    foreach ($cats_for_campus as $cat_name) {
                                        if (strtolower($cat_name) === 'others') continue;
                                        echo '<option value="' . htmlspecialchars($cat_name) . '" data-campus-id="' . $cid . '">' . htmlspecialchars($cat_name) . '</option>';
                                    }
                                    // Others at the end of each campus group
                                    if (in_array('Others', $cats_for_campus, true)) {
                                        echo '<option value="Others" data-campus-id="' . $cid . '">Others</option>';
                                    }
                                    echo '</optgroup>';
                                endforeach;
                                ?>
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
                                        data-campus="<?php echo (int)$item['campus_id']; ?>"
                                        data-item-id="<?php echo (int)$item['item_id']; ?>">
                                        <div class="eq-item-img-wrap">
                                            <img class="eq-item-img"
                                                src="<?php echo $root_url . htmlspecialchars(equipment_image_path($item['image_path'] ?? '')); ?>"
                                                alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                loading="lazy" decoding="async">
                                            <!-- Shown when the item is part of the current request -->
                                            <span class="bf-tick" aria-hidden="true"><span class="material-symbols-outlined">check</span></span>

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

                                            <?= fbAddSlot($item, (bool)$has_overdue_block, $bk_items, 'card') ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- ── Selection bar: appears once at least one item is added ── -->
                        <div id="eqCartBar" class="eq-cart-bar" style="display:none" aria-live="polite" role="status">
                            <div class="eq-cart-bar-summary">
                                <span class="material-symbols-outlined">inventory_2</span>
                                <span><span id="eqCartBarLabel">0 items selected</span>
                                    <small id="eqCartBarSub" class="bf-bar-sub"></small></span>
                            </div>
                            <div class="eq-cart-bar-actions">
                                <button type="button" class="eq-cart-bar-clear" data-bf="clear">Clear</button>
                                <button type="button" class="eq-cart-bar-submit" data-bf="continue">
                                    Continue <span class="material-symbols-outlined">arrow_forward</span>
                                </button>
                            </div>
                        </div>

                        <!-- No results (search/filter matched nothing) -->
                        <div class="eq-empty" id="equipNoResults" style="display:none;">
                            <span class="material-symbols-outlined" id="equipNoResultsIcon">search_off</span>
                            <p id="equipNoResultsMsg">No equipment matches your search.</p>
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

