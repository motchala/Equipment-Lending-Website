            <!-- ============================================================
         TAB: INVENTORY
    ============================================================ -->
            <div class="tab-panel" id="panel-inventory">

                <!-- ── INVENTORY SCREEN (REDESIGNED) ─────────────────── -->
                <div id="lending-inventory">

                    <!-- Page Header -->
                    <div class="inv-redesign-header admin-screen-header">
                        <div>
                            <h1 class="inv-page-title">Equipment Inventory</h1>
                            <p class="inv-page-sub">Manage the equipment catalog, stock quantities, and item details.</p>
                        </div>
                    </div>

                    <?php
                    // ── On Loan tab data (read-only; does not touch the shared $approved_result / $waiting_result) ──
                    require_once PS_ROOT . '/equipment-booking/core/booking-schedule.php';
                    BookingSchedule::ensureSchema($conn);   // later-date booking columns (idempotent, same call the return handler uses)
                    $eq_today        = date('Y-m-d');
                    $eq_now          = date('Y-m-d H:i:s');   // Asia/Manila
                    $eq_loan_rows    = [];
                    $eq_loan_overdue = 0;
                    $eq_loan_q = mysqli_query($conn, "SELECT r.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = r.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests r
                        WHERE r.status IN ('Approved','Overdue')
                          AND NOT COALESCE((r.status = 'Approved'
                                AND COALESCE(r.booking_mode, '') = 'future'
                                AND TIMESTAMP(r.borrow_date, COALESCE(r.borrow_time, '00:00:00')) > '{$eq_now}'), 0)
                        ORDER BY r.return_date ASC, r.id ASC");
                    if ($eq_loan_q) {
                        while ($lr = mysqli_fetch_assoc($eq_loan_q)) {
                            $lr['_days']  = (int)floor((strtotime($eq_today) - strtotime($lr['return_date'])) / 86400);
                            $lr['_state'] = ($lr['status'] === 'Overdue' || $lr['_days'] > 0) ? 'overdue' : ($lr['_days'] === 0 ? 'due' : 'ok');
                            if ($lr['_state'] === 'overdue') $eq_loan_overdue++;
                            $eq_loan_rows[] = $lr;
                        }
                    }
                    $eq_loan_total  = count($eq_loan_rows);
                    $eq_loan_active = $eq_loan_total - $eq_loan_overdue;
                    // Returned history for the Returned / All tabs (newest first, last 100)
                    $eq_ret_rows = [];
                    $eq_ret_q = mysqli_query($conn, "SELECT r.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = r.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests r
                        WHERE r.status = 'Returned'
                        ORDER BY COALESCE(r.returned_at, r.return_date) DESC, r.id DESC
                        LIMIT 100");
                    if ($eq_ret_q) {
                        while ($rr = mysqli_fetch_assoc($eq_ret_q)) {
                            $rr['_days']  = 0;
                            $rr['_state'] = 'returned';
                            $eq_ret_rows[] = $rr;
                        }
                    }
                    $eq_ret_total  = count($eq_ret_rows);
                    // Waiting (Pending Review) rows — supporting-letter requests from
                    // Organization Advisers are visible to a Super Admin only. Rows with a document come first.
                    $eq_wait_sql = "SELECT tbl_requests.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = tbl_requests.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests
                        WHERE tbl_requests.status = 'Waiting'";
                    if (empty($can_view_sensitive_adviser_requests)) {
                        $eq_wait_sql .= " AND NOT {$sensitive_adviser_request_sql}";
                    }
                    $eq_wait_sql .= " ORDER BY tbl_requests.request_date DESC";
                    $eq_wait_with = [];
                    $eq_wait_without = [];
                    $eq_wait_q = mysqli_query($conn, $eq_wait_sql);
                    if ($eq_wait_q) {
                        while ($wr = mysqli_fetch_assoc($eq_wait_q)) {
                            $wr['_days']  = 0;
                            $wr['_state'] = 'waiting';
                            $wr['_doc']   = ps_doc_info($wr['document_path'] ?? '');
                            if ($wr['_doc']) $eq_wait_with[] = $wr;
                            else $eq_wait_without[] = $wr;
                        }
                    }
                    $eq_wait_rows  = array_merge($eq_wait_with, $eq_wait_without);
                    $eq_pend_total = count($eq_wait_rows);   // pending-review requests only
                    // Scheduled (later-date) bookings: approved by default but not started yet. They wait here
                    // until their start time (then they move to Active) and an admin can cancel / revoke them.
                    $eq_sched_rows = [];
                    $eq_sched_q = mysqli_query($conn, "SELECT tbl_requests.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = tbl_requests.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests
                        WHERE tbl_requests.status = 'Approved'
                          AND COALESCE(tbl_requests.booking_mode, '') = 'future'
                          AND TIMESTAMP(tbl_requests.borrow_date, COALESCE(tbl_requests.borrow_time, '00:00:00')) > '{$eq_now}'
                        ORDER BY tbl_requests.borrow_date ASC, tbl_requests.borrow_time ASC, tbl_requests.id ASC");
                    if ($eq_sched_q) {
                        while ($sr = mysqli_fetch_assoc($eq_sched_q)) {
                            $sr['_days']  = (int)floor((strtotime($sr['borrow_date']) - strtotime($eq_today)) / 86400);   // days until it starts
                            $sr['_state'] = 'scheduled';
                            $eq_sched_rows[] = $sr;
                        }
                    }
                    $eq_wait_rows  = array_merge($eq_wait_rows, $eq_sched_rows);
                    $eq_wait_total = count($eq_wait_rows);
                    $eq_view_rows  = array_merge($eq_wait_rows, $eq_loan_rows, $eq_ret_rows);
                    $eq_view_total = count($eq_view_rows);
                    $eq_loan_dec   = admin_request_decisions($conn, array_map(function ($x) {
                        return (int)$x['id'];
                    }, $eq_view_rows));
                    ?>

                    <!-- Equipment sub-tabs: Catalog (the existing screen, unchanged) | On Loan -->
                    <div class="rq-filter-chips eq-tabs" id="eqTabs">
                        <button type="button" class="rq-filter-chip active" data-eq-panel="eq-catalog">
                            <span class="material-symbols-outlined eq-tab-ico" aria-hidden="true">inventory_2</span> Catalog
                        </button>
                        <button type="button" class="rq-filter-chip" data-eq-panel="eq-onloan">
                            <span class="material-symbols-outlined eq-tab-ico" aria-hidden="true">assignment_return</span> Loans
                            <?php if ($eq_loan_total > 0): ?>
                                <span class="rq-chip-count<?php echo $eq_loan_overdue > 0 ? '' : ' rq-chip-count--ok'; ?>" id="eqLoanChipCount"><?php echo $eq_loan_total; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- ── CATALOG (existing form + list + archived, wrapped as-is) ── -->
                    <div class="eq-sub-panel active" id="eq-catalog">

                        <!-- Equipment layout: Add form (left) + All Equipment list (right).
                         Same two-column pattern as the Faculty tab (.faculty-layout). -->
                        <div class="faculty-layout inv-layout">

                            <!-- ADD EQUIPMENT FORM -->
                            <div class="eq-card inv-form-card" id="item-form-wrap">
                                <div class="eq-card-header">
                                    <h2>
                                        <span class="material-symbols-outlined inv-head-icon">add_box</span>
                                        <span id="form-title"><?php echo $edit_item ? 'Edit Equipment' : 'Add Equipment'; ?></span>
                                    </h2>
                                </div>
                                <div class="eq-card-body">
                                    <form method="POST" enctype="multipart/form-data" id="itemForm">
                                        <?= csrf_field() ?>
                                        <?php if ($edit_item): ?>
                                            <input type="hidden" name="item_id"
                                                value="<?php echo $edit_item['item_id']; ?>">
                                            <input type="hidden" name="old_image"
                                                value="<?php echo htmlspecialchars($edit_item['image_path']); ?>">
                                        <?php endif; ?>

                                        <div class="fac-edit-section inv-section--first">Details</div>

                                        <div class="form-group">
                                            <label for="inv-item-name">Item name <span class="req-star">*</span></label>
                                            <input type="text" id="inv-item-name" name="item_name"
                                                class="form-control-custom" maxlength="150"
                                                value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_name']) : ''; ?>"
                                                placeholder="e.g. Extension Cord" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="inv-category">Category <span class="req-star">*</span></label>
                                            <select id="inv-category" name="category"
                                                class="form-control-custom inv-category-select" required>
                                                <option value="">Select category...</option>
                                                <?php
                                                $cats = ['Audio/Visual', 'Cables & Connectors', 'Computing', 'Lab Equipment', 'Networking', 'Power', 'Tools', 'Others'];
                                                foreach ($cats as $c) {
                                                    $sel = ($edit_item && $edit_item['category'] === $c) ? 'selected' : '';
                                                    echo '<option value="' . htmlspecialchars($c) . '" ' . $sel . '>' . htmlspecialchars($c) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="fac-edit-section">Stock</div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="inv-quantity">Quantity <span class="req-star">*</span></label>
                                                <input type="number" id="inv-quantity" name="quantity"
                                                    class="form-control-custom" min="0"
                                                    value="<?php echo $edit_item ? (int)$edit_item['quantity'] : '1'; ?>"
                                                    required>
                                            </div>
                                            <div class="form-group">
                                                <label for="inv-condition">Condition</label>
                                                <select id="inv-condition" name="condition" class="form-control-custom">
                                                    <?php
                                                    $cur_condition = $edit_item['condition'] ?? 'Good';
                                                    foreach (['Good', 'Fair', 'For Repair'] as $condOpt) {
                                                        $sel = ($cur_condition === $condOpt) ? 'selected' : '';
                                                        echo "<option value=\"$condOpt\" $sel>$condOpt</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="fac-edit-section">Image <span class="inv-opt">(optional)</span></div>

                                        <div class="form-group">
                                            <div class="drop-zone inv-drop" id="dropZone">
                                                <span class="material-symbols-outlined">add_photo_alternate</span>
                                                <p>Click, drag &amp; drop, or paste an image</p>
                                                <p class="inv-drop-sub">JPG or PNG, up to 2 MB</p>
                                                <input type="file" name="item_image" id="itemImageInput"
                                                    accept="image/jpeg,image/png" hidden>
                                                <?php if ($edit_item && $edit_item['image_path'] !== 'uploads/default.png'): ?>
                                                    <img src="<?php echo $root_url . htmlspecialchars($edit_item['image_path']); ?>"
                                                        class="drop-zone-preview" id="imagePreview" style="display:block;" alt="">
                                                <?php else: ?>
                                                    <img id="imagePreview" class="drop-zone-preview" alt="">
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" id="removeImageBtn"
                                                class="inv-remove-img<?php echo ($edit_item && $edit_item['image_path'] !== 'uploads/default.png') ? '' : ' hidden'; ?>">
                                                &#x2715; Remove image
                                            </button>
                                        </div>

                                        <div class="inv-form-actions">
                                            <button type="submit"
                                                name="<?php echo $edit_item ? 'update_item' : 'add_item'; ?>"
                                                class="ps-btn ps-btn--primary inv-submit">
                                                <span class="material-symbols-outlined">save</span>
                                                <?php echo $edit_item ? 'Update Item' : 'Save Equipment'; ?>
                                            </button>
                                            <?php if ($edit_item): ?>
                                                <a href="admin-dashboard.php?delete_item=<?php echo $edit_item['item_id']; ?>"
                                                    class="btn-inv-delete" title="Archive item"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        width="15" height="15">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14H6L5 6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                        <path d="M9 6V4h6v2" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                    </form>
                                </div><!-- /eq-card-body -->
                            </div><!-- /inv-form-card -->


                            <!-- ALL EQUIPMENT LIST -->
                            <div class="eq-card inv-list-card" id="inv-table-card">
                                <div class="eq-card-header fac-list-head">
                                    <h2>
                                        <span class="material-symbols-outlined inv-head-icon">inventory_2</span>
                                        All Equipment
                                        <span class="fac-count-badge" id="inv-count-badge">(<?php echo (int)mysqli_num_rows($inventory_result); ?>)</span>
                                    </h2>
                                    <div class="fac-search">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="inventorySearch" class="form-control-custom"
                                            placeholder="Search name or category" autocomplete="off">
                                    </div>
                                </div>
                                <div class="tbl-wrap">
                                    <table class="admin-table fac-table inv-table" id="inv-list-table">
                                        <thead>
                                            <tr>
                                                <th>Equipment</th>
                                                <th>Category</th>
                                                <th>Condition</th>
                                                <th>Stock</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="inventory-body">
                                            <?php
                                            mysqli_data_seek($inventory_result, 0);
                                            if (mysqli_num_rows($inventory_result) === 0): ?>
                                                <tr id="inv-empty-row">
                                                    <td colspan="5" class="inv-empty-cell">
                                                        <span class="material-symbols-outlined">inventory_2</span>
                                                        No equipment yet. Add your first item using the form.
                                                    </td>
                                                </tr>
                                                <?php else:
                                                while ($item = mysqli_fetch_assoc($inventory_result)):
                                                    $inv_qty        = (int)$item['quantity'];
                                                    $inv_cond       = $item['condition'] ?? 'Good';
                                                    $inv_condClass  = ($inv_cond === 'For Repair') ? 'repair' : (($inv_cond === 'Fair') ? 'fair' : 'good');
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
                                                    $inv_addedTs    = !empty($item['created_at']) ? strtotime($item['created_at']) : false;
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
                                                                    <?php if ($inv_addedTs): ?>
                                                                        <div class="fac-person-sub">Added <?php echo date('M j, Y', $inv_addedTs); ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><span class="inv-pill inv-pill--cat"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                                        <td><span class="inv-pill inv-pill--<?php echo $inv_condClass; ?>"><?php echo htmlspecialchars($inv_cond); ?></span></td>
                                                        <td>
                                                            <span class="inv-pill inv-pill--stock inv-pill--<?php echo $inv_stockClass; ?>"><strong><?php echo $inv_qty; ?></strong> <?php echo $inv_stockLabel; ?></span>
                                                        </td>
                                                        <td class="fac-actions">
                                                            <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm inv-edit-btn"
                                                                data-action="eq-open-edit" aria-label="Edit equipment">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                                <tr id="inv-nomatch-row" class="inv-row-hidden">
                                                    <td colspan="5" class="inv-empty-cell">
                                                        <span class="material-symbols-outlined">search_off</span>
                                                        No equipment matches your search.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination (10 per page, client-side; works together with the search box) -->
                                <div class="inv-pager" id="inv-pager" hidden>
                                    <span class="inv-pager-info" id="inv-pager-info"></span>
                                    <div class="inv-pager-ctl" id="inv-pager-ctl">
                                        <button type="button" class="inv-pager-btn" id="inv-pg-prev" aria-label="Previous page">
                                            <span class="material-symbols-outlined">chevron_left</span>
                                        </button>
                                        <div class="inv-pager-nums" id="inv-pg-nums"></div>
                                        <button type="button" class="inv-pager-btn" id="inv-pg-next" aria-label="Next page">
                                            <span class="material-symbols-outlined">chevron_right</span>
                                        </button>
                                    </div>
                                </div>
                            </div><!-- /inv-list-card -->

                        </div><!-- /faculty-layout inv-layout -->

                        <!-- Archived Items -->
                        <div class="inv-archived-wrap">
                            <div class="history-toggle-wrap inv-arch-toggle" id="registry-toggle-wrap">
                                <button class="history-toggle-btn" data-history-tab="reg-archived">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" width="14" height="14">
                                        <polyline points="21 8 21 21 3 21 3 8" />
                                        <rect x="1" y="3" width="22" height="5" />
                                        <line x1="10" y1="12" x2="14" y2="12" />
                                    </svg>
                                    Archived Items
                                </button>
                            </div>
                            <div class="history-panel" id="history-reg-archived">
                                <div class="eq-card">
                                    <div class="tbl-wrap">
                                        <table class="admin-table">
                                            <thead>
                                                <tr>
                                                    <th>Image</th>
                                                    <th>Item Name</th>
                                                    <th>Category</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="inv-archive-body">
                                                <?php if (mysqli_num_rows($archive_result) === 0): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-muted"
                                                            style="text-align:center;padding:2.5rem;">
                                                            No archived items.
                                                        </td>
                                                    </tr>
                                                    <?php else: while ($item = mysqli_fetch_assoc($archive_result)): ?>
                                                        <tr>
                                                            <td>
                                                                <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                                    class="item-img"
                                                                    onerror="this.src='../uploads/default.png'">
                                                            </td>
                                                            <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                            <td class="action-cell">
                                                                <div class="action-btns">
                                                                    <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>"
                                                                        class="btn-action btn-restore" title="Restore">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                                            stroke-linecap="round" stroke-linejoin="round"
                                                                            width="14" height="14">
                                                                            <polyline points="1 4 1 10 7 10" />
                                                                            <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                                        </svg>
                                                                    </a>
                                                                    <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>"
                                                                        class="btn-action btn-force-del" title="Delete permanently"
                                                                        onclick="return confirm('Permanently delete? This cannot be undone.')">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                                            stroke-linecap="round" stroke-linejoin="round"
                                                                            width="14" height="14">
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
                                                <?php endwhile;
                                                endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div><!-- /history-reg-archived -->
                        </div><!-- /inv-archived-wrap -->

                    </div><!-- /eq-catalog -->

                    <!-- ── ON LOAN (equipment currently out: Approved + Overdue) ───────────── -->
                    <div class="eq-sub-panel" id="eq-onloan">
                        <!-- Overdue warning: no inline banner here any more. It pops up at the bottom-centre of the
                             screen when this tab is opened (announceOverdue() in admin-dashboard.js). -->
                        <div class="ps-card">
                            <div class="ps-card-header eq-loan-head">
                                <!-- Table title: text changes with the selected tab (see TITLES in admin-dashboard.js) -->
                                <h2 class="eq-loan-title" id="eqLoanTitle">All Loans</h2>
                                <!-- Loan status tabs live inside the table header -->
                                <div class="eq-loan-tabs" id="eqLoanFilter" role="tablist" aria-label="Filter loans by status">
                                    <button type="button" class="eq-loan-tab active" data-loan-filter="all" role="tab" aria-selected="true">All</button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="waiting" role="tab" aria-selected="false">Waiting <span class="eq-loan-tab-count<?php echo $eq_pend_total > 0 ? ' has' : ''; ?>"><?php echo (int)$eq_wait_total; ?></span></button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="active" role="tab" aria-selected="false">Active <span class="eq-loan-tab-count"><?php echo (int)$eq_loan_active; ?></span></button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="overdue" role="tab" aria-selected="false">Overdue <span class="eq-loan-tab-count<?php echo $eq_loan_overdue > 0 ? ' has' : ''; ?>"><?php echo (int)$eq_loan_overdue; ?></span></button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="returned" role="tab" aria-selected="false">Returned</button>
                                </div>
                                <div class="eq-loan-tools">
                                    <input class="ps-form-control" style="width:230px" placeholder="Search borrower or item..." id="eq-loan-search" autocomplete="off">
                                    <?php if ($is_super_admin): ?>
                                        <!-- Super Admin only: deletes the whole list after a password check (see the modal near the bottom of this file) -->
                                        <button type="button" class="ps-btn ps-btn--danger" id="eq-loan-wipe-btn"
                                            title="Permanently delete every record in this list" <?php echo $eq_view_total === 0 ? ' disabled' : ''; ?>>
                                            <span class="material-symbols-outlined">delete_forever</span> Delete All
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ps-table-wrap">
                                <table class="ps-table" id="eq-loan-table">
                                    <thead>
                                        <tr>
                                            <th>Borrower</th>
                                            <th>Equipment</th>
                                            <th>Issued</th>
                                            <th>Due</th>
                                            <th>Status</th>
                                            <th>Handled by</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($eq_view_total > 0):
                                            foreach ($eq_view_rows as $r):
                                                $lqty  = max(1, (int)($r['borrow_qty'] ?? 1));
                                                $ltype = ps_submitted_as_label($r['submitted_as'] ?? '');
                                                $ldays = (int)$r['_days'];
                                                $lst   = $r['_state'];
                                                $ldec  = $lst === 'returned'
                                                    ? ($eq_loan_dec[(int)$r['id']]['returned'] ?? null)
                                                    : ($eq_loan_dec[(int)$r['id']]['approved'] ?? null);
                                                $lret  = !empty($r['returned_at']) ? $r['returned_at'] : $r['return_date'];
                                                $ldoc  = $r['_doc'] ?? null;
                                                $lbt   = !empty($r['borrow_time']) ? date('g:i A', strtotime($r['borrow_time'])) : '';
                                                $lrt   = !empty($r['return_time']) ? date('g:i A', strtotime($r['return_time'])) : '';
                                                $lstart_lbl = date('M d, Y', strtotime($r['borrow_date'])) . ($lbt !== '' ? ' · ' . $lbt : '');
                                                $ldue_lbl   = date('M d, Y', strtotime($r['return_date'])) . ($lrt !== '' ? ' · ' . $lrt : '');
                                                // Same confidentiality rule as admin-override.php: adviser requests with a supporting
                                                // document can only be changed by a Super Admin.
                                                $lsens = (string)($r['submitted_as'] ?? '') === 'adviser' && trim((string)($r['document_path'] ?? '')) !== '';
                                                $lcan_cancel = !$lsens || !empty($can_view_sensitive_adviser_requests);
                                        ?>
                                                <tr class="eq-loan-row is-<?php echo $lst; ?>"
                                                    data-id="<?php echo (int)$r['id']; ?>"
                                                    data-status="<?php echo $lst === 'scheduled' ? 'Scheduled' : htmlspecialchars($r['status']); ?>"
                                                    data-loan-state="<?php echo $lst; ?>"
                                                    data-start-label="<?php echo htmlspecialchars($lstart_lbl); ?>"
                                                    data-due-label="<?php echo htmlspecialchars($ldue_lbl); ?>"
                                                    data-days-overdue="<?php echo max(0, $ldays); ?>"
                                                    data-condition="<?php echo htmlspecialchars($r['item_condition'] ?? 'Good'); ?>"
                                                    data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                    data-id-number="<?php echo htmlspecialchars(faculty_id_display($r['faculty_id'], 'Not set')); ?>"
                                                    data-req-type="<?php echo htmlspecialchars($ltype); ?>"
                                                    data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                    data-qty="<?php echo $lqty; ?>"
                                                    data-instructor="<?php echo htmlspecialchars($r['instructor']); ?>"
                                                    data-room="<?php echo htmlspecialchars($r['room']); ?>"
                                                    data-submitted="<?php echo date('M d, Y g:i A', strtotime($r['request_date'])); ?>"
                                                    data-date-needed="<?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>"
                                                    data-return-date="<?php echo htmlspecialchars($r['return_date']); ?>"
                                                    data-return-date-display="<?php echo date('M d, Y', strtotime($r['return_date'])); ?>"
                                                    data-arb-rule="<?php echo htmlspecialchars($r['arbitration_rule'] ?? ''); ?>"
                                                    data-note="<?php echo htmlspecialchars($r['reason'] ?? ''); ?>"
                                                    data-doc-url="<?php echo $ldoc ? htmlspecialchars($ldoc['url']) : ''; ?>"
                                                    data-doc-open-url="<?php echo $ldoc ? htmlspecialchars($ldoc['open_url']) : ''; ?>"
                                                    data-doc-name="<?php echo $ldoc ? htmlspecialchars($ldoc['name']) : ''; ?>"
                                                    data-doc-kind="<?php echo $ldoc ? $ldoc['kind'] : ''; ?>">
                                                    <td>
                                                        <div style="font-weight:600"><?php echo htmlspecialchars($r['faculty_name']); ?></div>
                                                        <div style="font-size:11px;color:var(--text-light)"><?php echo htmlspecialchars(faculty_id_display($r['faculty_id'], 'Not set')); ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="ps-appr-item">
                                                            <?php echo htmlspecialchars($r['equipment_name']); ?>
                                                            <?php if ($lqty > 1): ?><span class="ps-appr-qty">&times;<?php echo $lqty; ?></span><?php endif; ?>
                                                        </div>
                                                        <button type="button" class="ps-appr-id" data-action="ps-open-modal" data-modal="ps-req-detail-modal" title="View request details">#<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></button>
                                                        <?php if ($lst === 'waiting'): ?>
                                                            <?php if ($ldoc): ?>
                                                                <a class="ps-doc-chip ps-doc-chip--<?php echo $ldoc['kind']; ?>"
                                                                    href="<?php echo htmlspecialchars($ldoc['open_url']); ?>" target="_blank" rel="noopener noreferrer"
                                                                    title="Open <?php echo htmlspecialchars($ldoc['name']); ?> in a new tab">
                                                                    <span class="material-symbols-outlined"><?php echo ps_doc_icon($ldoc['kind']); ?></span>
                                                                    <span class="ps-doc-chip__name"><?php echo htmlspecialchars($ldoc['name']); ?></span>
                                                                    <span class="material-symbols-outlined ps-doc-chip__open">open_in_new</span>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="ps-doc-chip ps-doc-chip--none"><span class="material-symbols-outlined">block</span> No document</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M d', strtotime($r['borrow_date'])); ?>
                                                        <?php if ($lbt !== ''): ?><div class="ps-appr-sub"><?php echo $lbt; ?></div><?php endif; ?>
                                                    </td>
                                                    <td style="<?php echo $lst === 'overdue' ? 'color:var(--danger);font-weight:600' : ''; ?>">
                                                        <?php echo date('M d', strtotime($r['return_date'])); ?>
                                                        <?php if ($lrt !== ''): ?><div class="ps-appr-sub" style="font-weight:400"><?php echo $lrt; ?></div><?php endif; ?>
                                                        <?php if ((int)($r['extension_count'] ?? 0) > 0):
                                                            // Extended by the faculty member after it was due (equipment-booking/core/borrow-extension.php)
                                                            $lext  = (int)$r['extension_count'];
                                                            $lorig = !empty($r['original_return_date'])
                                                                ? date('M d, Y', strtotime($r['original_return_date'])) . (!empty($r['original_return_time']) ? ' · ' . date('g:i A', strtotime($r['original_return_time'])) : '')
                                                                : '';
                                                        ?>
                                                            <div class="ps-appr-sub" style="font-weight:400" title="<?php echo htmlspecialchars('Extended ' . $lext . ' time' . ($lext === 1 ? '' : 's') . ($lorig !== '' ? '. Originally due ' . $lorig : '')); ?>">Extended &times;<?php echo $lext; ?><?php if (!empty($r['original_return_date'])): ?> &middot; was <?php echo htmlspecialchars(date('M d', strtotime($r['original_return_date']))); ?><?php endif; ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($lst === 'waiting'): ?>
                                                            <span class="ps-badge ps-badge--dot ps-badge--waiting">Pending Review</span>
                                                        <?php elseif ($lst === 'scheduled'): ?>
                                                            <span class="ps-badge ps-badge--scheduled">Scheduled</span>
                                                            <div class="ps-appr-sub"><?php echo $ldays > 0 ? 'Starts in ' . $ldays . ' day' . ($ldays === 1 ? '' : 's') : 'Starts today'; ?></div>
                                                        <?php elseif ($lst === 'overdue'): ?>
                                                            <span class="ps-badge ps-badge--overdue"><?php echo max(1, $ldays); ?> day<?php echo max(1, $ldays) === 1 ? '' : 's'; ?> overdue</span>
                                                        <?php elseif ($lst === 'due'): ?>
                                                            <span class="ps-badge ps-badge--waiting">Due today</span>
                                                        <?php elseif ($lst === 'returned'): ?>
                                                            <span class="ps-badge ps-badge--returned">Returned</span>
                                                            <div class="ps-appr-sub"><?php echo date('M d, Y', strtotime($lret)); ?></div>
                                                        <?php else: ?>
                                                            <span class="ps-badge ps-badge--active">Due in <?php echo abs($ldays); ?> day<?php echo abs($ldays) === 1 ? '' : 's'; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo admin_decider_html($ldec); ?></td>
                                                    <td>
                                                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                                                            <?php if ($lst === 'waiting'): ?>
                                                                <button type="button" class="ps-btn ps-btn--success ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-approve-modal">
                                                                    <span class="material-symbols-outlined">check</span> Approve
                                                                </button>
                                                                <button type="button" class="ps-btn ps-btn--danger ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-decline-modal">
                                                                    <span class="material-symbols-outlined">close</span> Decline
                                                                </button>
                                                            <?php elseif ($lst === 'scheduled'): ?>
                                                                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-req-detail-modal">View</button>
                                                                <?php if ($lcan_cancel): ?>
                                                                    <button type="button" class="ps-btn ps-btn--danger ps-btn--sm"
                                                                        data-action="ps-open-modal" data-modal="ps-cancel-booking-modal">
                                                                        <span class="material-symbols-outlined">event_busy</span> Cancel Booking
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="ps-appr-sub" title="Organization Adviser requests with a supporting document can only be changed by a Super Admin">Super Admin only</span>
                                                                <?php endif; ?>
                                                            <?php elseif ($lst === 'returned'): ?>
                                                                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-req-detail-modal">View</button>
                                                            <?php else: ?>
                                                                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-return-modal">Manual Return</button>
                                                                <?php if ($lst === 'overdue'): ?>
                                                                    <button type="button" class="ps-btn ps-btn--outline ps-btn--sm"
                                                                        data-action="ps-open-modal" data-modal="ps-notice-modal">Send Notice</button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach;
                                        else: ?>
                                            <tr class="eq-loan-empty">
                                                <td colspan="7">
                                                    <div class="ps-empty-state">
                                                        <span class="material-symbols-outlined">inventory_2</span>
                                                        <p>No loan records yet.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="eq-loan-nomatch" hidden>
                                            <td colspan="7">
                                                <div class="ps-empty-state">
                                                    <span class="material-symbols-outlined">search_off</span>
                                                    <p>No items match your filter.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination (20 per page, client-side; works with the tabs and the search box) -->
                            <div class="inv-pager" id="eq-loan-pager" hidden>
                                <span class="inv-pager-info" id="eq-loan-pager-info"></span>
                                <div class="inv-pager-ctl" id="eq-loan-pager-ctl">
                                    <button type="button" class="inv-pager-btn" id="eq-loan-pg-prev" aria-label="Previous page">
                                        <span class="material-symbols-outlined">chevron_left</span>
                                    </button>
                                    <div class="inv-pager-nums" id="eq-loan-pg-nums"></div>
                                    <button type="button" class="inv-pager-btn" id="eq-loan-pg-next" aria-label="Next page">
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div><!-- /eq-onloan -->

                </div><!-- /lending-inventory -->

            </div><!-- /panel-inventory -->
