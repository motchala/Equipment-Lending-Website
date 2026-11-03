        (function() {
            'use strict';

            /* ── Refresh inventory tbodies from the fragment endpoint ─────────── */
            function refreshInventoryTbody(onDone) {
                fetch('admin-dashboard.php?fragment=inventory-tbody', {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status !== 'success') return;

                        var activeBody = document.getElementById('inventory-body');
                        if (activeBody) activeBody.innerHTML = json.active_html;

                        var archBody = document.getElementById('inv-archive-body');
                        if (archBody) archBody.innerHTML = json.archive_html;

                        var badge = document.getElementById('inv-count-badge');
                        if (badge) badge.textContent = '(' + json.active_count + ')';

                        // Re-wire pagination/search since rows are brand new DOM nodes
                        if (typeof window._setupInventoryListFn === 'function') {
                            window._setupInventoryListFn();
                        }

                        if (typeof window.adminDoPoll === 'function') window.adminDoPoll();

                        if (typeof onDone === 'function') onDone();
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Could not refresh inventory. Please reload.');
                    });
            }

            /* ── Intercept itemForm (Add Equipment) ───────────────────────────── */
            var itemForm = document.getElementById('itemForm');
            if (itemForm) {
                itemForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var fd = new FormData(itemForm);
                    fd.set('ajax', '1');

                    var submitBtn = itemForm.querySelector('[name="add_item"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Saving\u2026';
                    }

                    fetch('admin-dashboard.php', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(json) {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Add Equipment';
                            }
                            if (json.status === 'success') {
                                if (typeof showToast === 'function') showToast(json.message || 'Saved.');
                                itemForm.reset();
                                var preview = document.getElementById('imagePreview');
                                if (preview) {
                                    preview.src = '';
                                    preview.style.display = 'none';
                                }
                                if (typeof window._exitEditModeFn === 'function') window._exitEditModeFn();
                                var fw = document.getElementById('item-form-wrap');
                                if (fw) fw.classList.add('hidden');
                                refreshInventoryTbody();
                            } else {
                                if (typeof showToast === 'function') showToast(json.message || 'Error saving equipment.');
                            }
                        })
                        .catch(function() {
                            if (submitBtn) submitBtn.disabled = false;
                            if (typeof showToast === 'function') showToast('Network error. Please try again.');
                        });
                });
            }

            /* ── Intercept eqEditForm (Edit Equipment modal) ──────────────────── */
            var eqEditForm = document.getElementById('eqEditForm');
            if (eqEditForm) {
                eqEditForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var fd = new FormData(eqEditForm);
                    fd.set('update_item', '1');
                    fd.set('ajax', '1');

                    var submitBtn = document.querySelector('button[form="eqEditForm"][name="update_item"]');
                    if (submitBtn) submitBtn.disabled = true;

                    fetch('admin-dashboard.php', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(json) {
                            if (submitBtn) submitBtn.disabled = false;
                            if (json.status === 'success') {
                                if (typeof psCloseModal === 'function') psCloseModal('eq-edit-modal');
                                if (typeof showToast === 'function') showToast(json.message || 'Equipment updated.');
                                refreshInventoryTbody();
                            } else {
                                if (typeof showToast === 'function') showToast(json.message || 'Could not update equipment.');
                            }
                        })
                        .catch(function() {
                            if (submitBtn) submitBtn.disabled = false;
                            if (typeof showToast === 'function') showToast('Network error. Please try again.');
                        });
                });
            }

            /* ── Intercept archive (delete_item) links ────────────────────────── */
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="delete_item="]');
                if (!link) return;
                e.preventDefault();
                var msg = 'Archive this item? It will no longer be available for lending, but borrow history is preserved.';
                if (!confirm(msg)) return;

                var href = link.getAttribute('href');
                var url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status === 'success') {
                            if (typeof psCloseModal === 'function') psCloseModal('eq-edit-modal');
                            if (typeof showToast === 'function') showToast(json.message || 'Item archived.');
                            refreshInventoryTbody();
                        } else {
                            if (typeof showToast === 'function') showToast(json.message || 'Could not archive item.');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.');
                    });
            });

            /* ── Intercept restore_item links ─────────────────────────────────── */
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="restore_item="]');
                if (!link) return;
                e.preventDefault();
                var href = link.getAttribute('href');
                var url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status === 'success') {
                            if (typeof showToast === 'function') showToast(json.message || 'Item restored.');
                            refreshInventoryTbody();
                        } else {
                            if (typeof showToast === 'function') showToast(json.message || 'Could not restore item.');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.');
                    });
            });

            /* ── Intercept force_delete links ─────────────────────────────────── */
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="force_delete="]');
                if (!link) return;
                e.preventDefault();
                if (!confirm('Permanently delete? This cannot be undone.')) return;
                var href = link.getAttribute('href');
                var url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status === 'success') {
                            if (typeof showToast === 'function') showToast(json.message || 'Item permanently deleted.');
                            refreshInventoryTbody();
                        } else {
                            if (typeof showToast === 'function') showToast(json.message || 'Could not delete item.');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.');
                    });
            });

        })();
