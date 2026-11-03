    <!-- ================================================================
     MODALS
================================================================ -->

    <!-- ── Borrow Request Modal ───────────────────────────────────────── -->
    <?php if (!$is_dual_mode): ?>
        <!-- SINGLE-MODE: existing form unchanged (Requirement 4.1, 11.4) -->
        <div class="modal-backdrop" id="borrowModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
            <div class="modal-box borrow-modal-box">
                <div class="modal-header bm-head">
                    <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">inventory_2</span></span>
                    <div class="bm-heading">
                        <h3 id="borrowModalTitle">Borrow Request</h3>
                        <p class="bm-sub">Tell us where and when you need the equipment.</p>
                    </div>
                    <button class="modal-close-btn" data-action="close-borrow-modal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="bm-item" id="selectedItemBanner">
                        <span class="bm-item-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                        <div class="bm-item-text">
                            <span class="bm-item-cap">Selected equipment</span>
                            <span class="bm-item-name" id="selectedItemLabel">No item selected</span>
                        </div>
                    </div>
                    <form id="borrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="equipment_name" id="selectedItem">
                        <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                        <?= BookingSchedule::renderSchedule('bm1', false) ?>
                        <?= BookingSchedule::renderRoomPicker('borrow_room') ?>
                        <?php if ($is_org_adviser): ?>
                            <div class="form-group">
                                <span class="form-label">Request Letter <span class="bm-opt">Optional</span></span>
                                <label class="bm-drop" for="request_document">
                                    <span class="material-symbols-outlined bm-drop-icon" aria-hidden="true">upload_file</span>
                                    <span class="bm-drop-text">
                                        <strong data-bm-drop-title>Choose a file or drag it here</strong>
                                        <small>PDF, JPG, PNG or WEBP &middot; max 5 MB</small>
                                    </span>
                                    <input type="file" id="request_document" name="request_document"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp">
                                </label>
                                <p class="bm-hint">A signed letter may help prioritise your request.</p>
                            </div>
                        <?php endif; ?>
                        <div class="bm-actions">
                            <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                            <button type="submit" class="btn-save-acc">
                                <span class="material-symbols-outlined">send</span> Submit Borrow Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div><!-- /borrowModal -->
    <?php else: ?>
        <!-- DUAL-MODE: two-tab layout (Requirement 4.2, 4.3, 4.4) -->
        <link rel="stylesheet" href="equipment-booking/assets/css/faculty-equipment-modals.css?v=<?php echo @filemtime(PS_ROOT . '/equipment-booking/assets/css/faculty-equipment-modals.css'); ?>">
        <div class="modal-backdrop" id="borrowModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
            <div class="modal-box borrow-modal-box">
                <div class="modal-header bm-head">
                    <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">inventory_2</span></span>
                    <div class="bm-heading">
                        <h3 id="borrowModalTitle">Borrow Request</h3>
                        <p class="bm-sub">Tell us where and when you need the equipment.</p>
                    </div>
                    <button class="modal-close-btn" data-action="close-borrow-modal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Sub-tab controls -->
                    <div class="borrow-subtab-bar" role="tablist" aria-label="Borrowing mode">
                        <button class="borrow-subtab-btn active" id="subtab-personal-btn" role="tab"
                            aria-selected="true" aria-controls="subtab-personal" type="button"
                            data-action="borrow-subtab" data-tab="personal">
                            <span class="material-symbols-outlined" style="font-size:16px;">person</span>
                            Personal
                        </button>
                        <button class="borrow-subtab-btn" id="subtab-adviser-btn" role="tab"
                            aria-selected="false" aria-controls="subtab-adviser" type="button"
                            data-action="borrow-subtab" data-tab="adviser">
                            <span class="material-symbols-outlined" style="font-size:16px;">groups</span>
                            Adviser
                        </button>
                    </div>

                    <!-- ── Personal Tab ──────────────────────────────────── -->
                    <div class="borrow-subtab-panel active" id="subtab-personal" role="tabpanel" aria-labelledby="subtab-personal-btn">
                        <div class="bm-item" id="selectedItemBanner">
                            <span class="bm-item-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                            <div class="bm-item-text">
                                <span class="bm-item-cap">Selected equipment</span>
                                <span class="bm-item-name" id="selectedItemLabel">No item selected</span>
                            </div>
                        </div>
                        <form id="borrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="equipment_name" id="selectedItem">
                            <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                            <input type="hidden" name="submitted_as" value="personal">
                            <?= BookingSchedule::renderSchedule('bm2', false) ?>
                            <?= BookingSchedule::renderRoomPicker('bm2Room') ?>
                            <!-- No document upload in personal tab for dual-mode accounts (Requirement 4.3) -->
                            <div class="bm-actions">
                                <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                                <button type="submit" class="btn-save-acc">
                                    <span class="material-symbols-outlined">send</span> Submit Borrow Request
                                </button>
                            </div>
                        </form>
                    </div><!-- /subtab-personal -->

                    <!-- ── Adviser Tab ───────────────────────────────────── -->
                    <div class="borrow-subtab-panel" id="subtab-adviser" role="tabpanel" aria-labelledby="subtab-adviser-btn">
                        <form id="adviserBorrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="submitted_as" value="adviser">

                            <!-- Multi-item checklist (Requirement 4.4, 5.1) -->
                            <div class="form-group">
                                <span class="form-label">Select Equipment Items</span>
                                <p id="advChecklistValidation" class="adv-validation-msg" role="alert">
                                    Please select at least one item before submitting.
                                </p>
                                <div class="adv-checklist-wrap" role="group" aria-label="Available equipment">
                                    <?php if (empty($avail_items)): ?>
                                        <p style="font-size:0.85rem;color:var(--color-secondary);margin:0;padding:8px;">No equipment has been set up yet.</p>
                                        <?php else:
                                        $current_category = null;
                                        foreach ($avail_items as $av_item):
                                            if ($av_item['category'] !== $current_category):
                                                $current_category = $av_item['category'];
                                        ?>
                                                <div class="adv-checklist-category"><?= htmlspecialchars($current_category) ?></div>
                                            <?php endif;
                                            $av_meta = $bk_items[$av_item['item_name']] ?? ['shelf' => (int)$av_item['quantity'], 'total' => (int)$av_item['quantity']];
                                            ?>
                                            <label class="adv-checklist-item" data-bm-item="<?= htmlspecialchars($av_item['item_name'], ENT_QUOTES) ?>"
                                                data-shelf="<?= (int)$av_meta['shelf'] ?>" data-total="<?= (int)$av_meta['total'] ?>">
                                                <input type="checkbox"
                                                    name="items[]"
                                                    value="<?= htmlspecialchars($av_item['item_name']) ?>"
                                                    data-category="<?= htmlspecialchars($av_item['category']) ?>">
                                                <span class="adv-checklist-name"><?= htmlspecialchars($av_item['item_name']) ?></span>
                                                <span class="adv-checklist-qty" data-bm-stock>
                                                    <?= (int)$av_meta['shelf'] > 0 ? '(' . (int)$av_meta['shelf'] . ' available)' : '(out now)' ?>
                                                </span>
                                                <span class="bm-mini-stepper" data-bm-item-qty hidden>
                                                    <button type="button" data-bm-iq-dec aria-label="Decrease quantity"><span class="material-symbols-outlined">remove</span></button>
                                                    <input type="number" name="qty[<?= htmlspecialchars($av_item['item_name'], ENT_QUOTES) ?>]" value="1" min="1" max="<?= max(1, (int)$av_meta['total']) ?>" inputmode="numeric" disabled aria-label="Quantity for <?= htmlspecialchars($av_item['item_name'], ENT_QUOTES) ?>">
                                                    <button type="button" data-bm-iq-inc aria-label="Increase quantity"><span class="material-symbols-outlined">add</span></button>
                                                </span>
                                                <span class="bm-item-status" data-bm-item-status></span>
                                            </label>
                                    <?php endforeach;
                                    endif; ?>
                                </div>
                            </div>

                            <!-- Shared fields -->
                            <?= BookingSchedule::renderSchedule('bm3', true) ?>
                            <?= BookingSchedule::renderRoomPicker('adv_room') ?>

                            <!-- Document upload — optional -->
                            <div class="form-group">
                                <span class="form-label">Request Letter <span class="bm-opt">Optional</span></span>
                                <label class="bm-drop" for="adv_request_document">
                                    <span class="material-symbols-outlined bm-drop-icon" aria-hidden="true">upload_file</span>
                                    <span class="bm-drop-text">
                                        <strong data-bm-drop-title>Choose a file or drag it here</strong>
                                        <small>PDF, JPG, PNG or WEBP &middot; max 5 MB</small>
                                    </span>
                                    <input type="file" id="adv_request_document" name="request_document"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp">
                                </label>
                                <p class="bm-hint">A signed letter may help prioritise your request.</p>
                            </div>

                            <div class="bm-actions">
                                <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                                <button type="submit" class="btn-save-acc">
                                    <span class="material-symbols-outlined">send</span> Submit Adviser Request
                                </button>
                            </div>
                        </form>
                    </div><!-- /subtab-adviser -->

                </div>
            </div>
        </div><!-- /borrowModal -->

        <script src="equipment-booking/assets/js/faculty-equipment-modals.js?v=<?php echo @filemtime(PS_ROOT . '/equipment-booking/assets/js/faculty-equipment-modals.js'); ?>"></script>
    <?php endif; ?><!-- /dual-mode borrowModal -->

