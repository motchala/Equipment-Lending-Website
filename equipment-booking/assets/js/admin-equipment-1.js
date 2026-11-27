        (function() {
            /* Dropdown "My Account" / "Settings" buttons are bound in
               admin-dashboard.js (routes to the correct Settings sub-tab). */

            /* Override initView() for ?edit_item= and ?view=inventory.
               admin-dashboard.js calls _switchTabDOM('lending') for those,
               but inventory is now its own #panel-inventory tab.

               IMPORTANT: admin-dashboard.js's own init() runs as soon as its
               <script> tag is reached — if document.readyState is no longer
               'loading' by then (typical, since that script sits near the
               end of the page), init() runs synchronously immediately,
               BEFORE any 'DOMContentLoaded' listener registered here would
               ever fire (the event has already passed). That's why this
               used to silently fail to land on the Equipment tab after a
               save. Matching admin-dashboard.js's own readyState check
               instead of blindly waiting for DOMContentLoaded fixes it. */
            function fixInventoryTab() {
                var params = new URLSearchParams(window.location.search);
                if (params.get('edit_item') || params.get('view') === 'inventory') {
                    document.querySelectorAll('.tab-panel').forEach(function(p) {
                        p.classList.remove('active');
                    });
                    var pInv = document.getElementById('panel-inventory');
                    if (pInv) pInv.classList.add('active');
                    document.querySelectorAll('.nav-item').forEach(function(n) {
                        n.classList.remove('active');
                    });
                    var snavInv = document.getElementById('snav-inventory');
                    if (snavInv) snavInv.classList.add('active');
                }

                /* Show a toast for the add/update/error redirects from
                   admin-functions.php, then strip the param so refreshing
                   or navigating away doesn't re-show it. */
                var msg = null;
                if (params.get('added') === '1') msg = 'Equipment added successfully.';
                else if (params.get('updated') === '1') msg = 'Equipment updated successfully.';
                else if (params.get('error') === 'filetype') msg = 'Only JPG and PNG images are allowed.';
                else if (params.get('error') === 'filesize') msg = 'Image too large. Maximum size is 2MB.';
                else if (params.get('error') === 'dberror') msg = 'Error saving to database. Please try again.';

                if (msg && typeof showToast === 'function') {
                    showToast(msg);
                    params.delete('added');
                    params.delete('updated');
                    params.delete('error');
                    var qs = params.toString();
                    var newUrl = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
                    window.history.replaceState({}, '', newUrl);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(fixInventoryTab, 0);
                });
            } else {
                setTimeout(fixInventoryTab, 0);
            }
        })();

        /* ── Edit Equipment modal: populate from the clicked row's
           data-* attributes, then open it. (ps-open-modal/close-modal
           are already handled by the main delegation in
           admin-dashboard.js — no need to duplicate that here.) ── */
        function conditionFromQty(qty) {
            if (qty === 0)  return 'For Repair';
            if (qty <= 3)   return 'Fair';
            return 'Good';
        }

        // Sidebar add/edit form — update condition display when quantity changes
        var invQty = document.getElementById('inv-quantity');
        var invCondDisplay = document.getElementById('inv-condition-display');
        if (invQty && invCondDisplay) {
            invQty.addEventListener('input', function () {
                invCondDisplay.textContent = conditionFromQty(parseInt(this.value, 10) || 0);
            });
        }

        // Live duplicate-name hint (sidebar form)
        var invNameInput = document.getElementById('inv-item-name');
        var invNameHint  = document.getElementById('inv-name-hint');
        if (invNameInput && invNameHint) {
            var _nameTimer = null;
            invNameInput.addEventListener('input', function () {
                clearTimeout(_nameTimer);
                var val = this.value.replace(/\s/g, '').toLowerCase();
                if (val.length < 2) {
                    invNameHint.style.display = 'none';
                    var submitBtn = document.querySelector('#itemForm [type="submit"]');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }
                _nameTimer = setTimeout(function () {
                    var rows = document.querySelectorAll('#inventory-body .inv-row-item');
                    var editId = (document.querySelector('[name="item_id"]') || {}).value || '0';
                    var duplicate = false;
                    rows.forEach(function (r) {
                        if (r.dataset.itemId === editId) return;
                        var n = (r.dataset.itemName || '').replace(/\s/g, '').toLowerCase();
                        if (n === val) duplicate = true;
                    });
                    invNameHint.textContent = duplicate
                        ? 'An item with this name already exists. Check the Equipment Inventory List.'
                        : '';
                    invNameHint.style.display = duplicate ? 'block' : 'none';
                    var submitBtn = document.querySelector('#itemForm [type="submit"]');
                    if (submitBtn) submitBtn.disabled = duplicate;
                }, 300);
            });
        }

        // Live duplicate-name hint (Edit modal)
        var eqmNameInput = document.getElementById('eqm-item-name');
        var eqmNameHint  = document.getElementById('eqm-name-hint');
        if (eqmNameInput && eqmNameHint) {
            var _eqmNameTimer = null;
            eqmNameInput.addEventListener('input', function () {
                clearTimeout(_eqmNameTimer);
                var val = this.value.replace(/\s/g, '').toLowerCase();
                var editId = (document.getElementById('eqm-item-id') || {}).value || '0';
                if (val.length < 2) { eqmNameHint.style.display = 'none'; return; }
                _eqmNameTimer = setTimeout(function () {
                    var rows = document.querySelectorAll('#inventory-body .inv-row-item');
                    var duplicate = false;
                    rows.forEach(function (r) {
                        if (r.dataset.itemId === editId) return;
                        var n = (r.dataset.itemName || '').replace(/\s/g, '').toLowerCase();
                        if (n === val) duplicate = true;
                    });
                    eqmNameHint.textContent = duplicate
                        ? 'Another item with this name already exists.'
                        : '';
                    eqmNameHint.style.display = duplicate ? 'block' : 'none';
                }, 300);
            });
        }

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="eq-open-edit"]');
            if (!btn) return;
            var row = btn.closest('.inv-row-item');
            if (!row) return;
            var d = row.dataset;

            document.getElementById('eqm-item-id').value = d.itemId;
            document.getElementById('eqm-old-image').value = d.itemImage;
            document.getElementById('eqm-remove-image-flag').value = '0';
            document.getElementById('eqm-item-name').value = d.itemName;
            document.getElementById('eqm-category').value = d.itemCategory;

            var eqmQty = parseInt(d.itemQuantity, 10) || 0;
            document.getElementById('eqm-quantity').value = eqmQty;
            var eqmDisplay = document.getElementById('eqm-condition-display');
            if (eqmDisplay) eqmDisplay.textContent = conditionFromQty(eqmQty);
            var eqmRemarks = document.getElementById('eqm-remarks');
            if (eqmRemarks) eqmRemarks.value = d.itemDescription || '';
            var eqmHint = document.getElementById('eqm-name-hint');
            if (eqmHint) eqmHint.style.display = 'none';

            var preview = document.getElementById('eqm-imagePreview');
            var removeBtn = document.getElementById('eqm-removeImageBtn');
            var fileInput = document.getElementById('eqm-itemImageInput');
            if (fileInput) fileInput.value = '';
            if (d.itemImage && d.itemImage.indexOf('default.png') === -1) {
                preview.src = d.itemImageFull;
                preview.style.display = 'block';
                removeBtn.classList.remove('hidden');
            } else {
                preview.src = '';
                preview.style.display = 'none';
                removeBtn.classList.add('hidden');
            }

            var archiveLink = document.getElementById('eqm-archive-link');
            archiveLink.href = 'admin-dashboard.php?delete_item=' + d.itemId;
            archiveLink.onclick = null; // confirmation handled by delegated listener in inventory AJAX block

            psOpenModal('eq-edit-modal');
        });
