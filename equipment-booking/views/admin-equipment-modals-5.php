    <!-- ── MODAL: EDIT EQUIPMENT ────────────────────────────────── -->
    <div class="ps-modal-backdrop" id="eq-edit-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                <h3>Edit Equipment</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="eq-edit-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form method="POST" enctype="multipart/form-data" id="eqEditForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" id="eqm-item-id">
                    <input type="hidden" name="old_image" id="eqm-old-image">
                    <input type="hidden" name="remove_image" id="eqm-remove-image-flag" value="0">

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Item Name <span class="inv-req">*</span></label>
                        <input type="text" name="item_name" id="eqm-item-name" class="form-control-custom"
                            placeholder="e.g. Extension Cord" required>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Category <span class="inv-req">*</span></label>
                        <select name="category" id="eqm-category" class="form-control-custom" required>
                            <option value="">Select category...</option>
                            <?php
                            foreach (['Audio/Visual', 'Cables & Connectors', 'Computing', 'Lab Equipment', 'Networking', 'Power', 'Tools', 'Others'] as $c) {
                                echo "<option value=\"$c\">$c</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Quantity <span class="inv-req">*</span></label>
                            <input type="number" name="quantity" id="eqm-quantity" class="form-control-custom" min="0" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Condition</label>
                            <select name="condition" id="eqm-condition" class="form-control-custom">
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="For Repair">For Repair</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Item Image</label>
                        <div class="drop-zone" id="eqm-dropZone">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"
                                style="color:var(--text-light)">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <p>Click to upload, drag &amp; drop, or paste an image</p>
                            <input type="file" name="item_image" id="eqm-itemImageInput" accept="image/jpeg,image/png" style="display:none;">
                            <img id="eqm-imagePreview" class="drop-zone-preview" style="display:none;">
                        </div>
                        <button type="button" id="eqm-removeImageBtn" class="hidden"
                            style="margin-top:6px;font-size:0.75rem;color:var(--danger);background:none;border:none;cursor:pointer;">
                            &#x2715; Remove image
                        </button>
                    </div>

                    <div style="border-top:1px solid var(--khaki-border);margin-top:1rem;padding-top:1rem">
                        <a href="#" id="eqm-archive-link" class="ps-btn ps-btn--danger" style="text-decoration:none;display:inline-flex;">
                            <span class="material-symbols-outlined">archive</span> Archive Item
                        </a>
                    </div>
                </form>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" type="button" data-action="ps-close-modal" data-modal="eq-edit-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" type="submit" form="eqEditForm" name="update_item">
                    <span class="material-symbols-outlined">save</span> Update Item
                </button>
            </div>
        </div>
    </div>

