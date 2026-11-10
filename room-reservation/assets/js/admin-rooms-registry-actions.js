                            /* ── PUPSync Room Registry: AJAX add/update/archive ──────
                               Progressive enhancement — the <form> and the Archive
                               <a> links still have their real method="POST"/href, so
                               they keep working with JS disabled. When JS runs, this
                               intercepts both, sends an extra ajax=1 flag, and swaps
                               in the fresh table HTML the server sends back instead
                               of letting the browser navigate at all. */
                            (function() {
                                'use strict';
                                const roomForm = document.getElementById('roomForm');
                                if (!roomForm) return;

                                function closeRoomModal() {
                                    const rfw = document.getElementById('room-form-wrap');
                                    const addRoomBtn = document.getElementById('addRoomBtn');
                                    if (rfw) rfw.classList.add('hidden');
                                    if (addRoomBtn) addRoomBtn.style.display = '';
                                    if (window.psResetRoomForm) window.psResetRoomForm();
                                    // If the modal was opened via ?edit_room=ID, drop that
                                    // param now — otherwise a later refresh would silently
                                    // reopen the modal on a room that's already saved.
                                    const params = new URLSearchParams(window.location.search);
                                    if (params.get('edit_room')) {
                                        params.delete('edit_room');
                                        const qs = params.toString();
                                        window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
                                    }
                                }

                                function afterMutation(json) {
                                    json = normalizeReply(json);
                                    if (json && json.status === 'success') {
                                        if (window.psRefreshRoomsRegistry) {
                                            window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                        }
                                        refreshArchived(json);
                                        closeRoomModal();
                                        if (typeof showToast === 'function') showToast(json.message || 'Saved.');
                                    } else {
                                        if (typeof showToast === 'function') {
                                            showToast((json && json.message) || 'Something went wrong. Please try again.');
                                        }
                                    }
                                }

                                function onNetworkError() {
                                    if (typeof showToast === 'function') showToast('Network error — please try again.');
                                }

                                /* Server replies for a bad CSRF token use {error:...}; everything else {message:...}. */
                                function normalizeReply(json) {
                                    if (json && json.status === 'error' && !json.message && json.error) json.message = json.error;
                                    return json;
                                }

                                /* Keep the Archived tab (rows + count badge) in step with the server. */
                                function refreshArchived(json) {
                                    if (!json || typeof json.archived_html !== 'string') return;
                                    const body = document.getElementById('rooms-archived-body');
                                    if (body) body.innerHTML = json.archived_html;
                                    const badge = document.getElementById('roomsArchivedBadge');
                                    if (badge) {
                                        const n = parseInt(json.archived_count, 10) || 0;
                                        badge.textContent = n;
                                        badge.style.display = n > 0 ? '' : 'none';
                                    }
                                }

                                /* Lock button in the Edit modal: label + icon + next action. */
                                window.psSetLockButton = function(isLocked) {
                                    const btn = document.getElementById('room-lock-btn');
                                    if (!btn) return;
                                    btn.dataset.lock = isLocked ? '0' : '1';
                                    const ico = document.getElementById('room-lock-icon');
                                    const lbl = document.getElementById('room-lock-label');
                                    if (ico) ico.textContent = isLocked ? 'lock_open' : 'lock';
                                    if (lbl) lbl.textContent = isLocked ? 'Unlock Facility' : 'Lock Facility';
                                };

                                const submitBtns = ['room-form-submit-inner', 'room-form-submit-foot']
                                    .map(function(id) {
                                        return document.getElementById(id);
                                    })
                                    .filter(Boolean);

                                roomForm.addEventListener('submit', function(e) {
                                    e.preventDefault();

                                    const fd = new FormData(roomForm);
                                    // A submit event's .submitter is the exact button that
                                    // was clicked — needed here because FormData(form) alone
                                    // does not include a submit button's name/value (the
                                    // browser only adds that for a real, non-JS submit).
                                    // Both buttons carry name="add_room" or "update_room"
                                    // depending on the modal's current mode, so this is how
                                    // the server knows which action to run.
                                    const clicked = e.submitter || submitBtns[0];
                                    if (clicked && clicked.name) fd.set(clicked.name, clicked.value || '1');
                                    fd.set('ajax', '1');

                                    submitBtns.forEach(function(b) {
                                        b.disabled = true;
                                    });

                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            submitBtns.forEach(function(b) {
                                                b.disabled = false;
                                            });
                                            afterMutation(json);
                                        })
                                        .catch(function() {
                                            submitBtns.forEach(function(b) {
                                                b.disabled = false;
                                            });
                                            onNetworkError();
                                        });
                                });

                                /* Archive: both the per-row icon (in the table) and the
                                   modal's own Archive Room link share the same href
                                   pattern, so one delegated listener catches both. */
                                let pendingArchiveHref = '';

                                document.addEventListener('click', function(e) {
                                    const link = e.target.closest('a[href*="archive_room="]');
                                    if (!link) return;
                                    e.preventDefault();

                                    // Populate the confirm modal with the facility name + location
                                    // from the nearest row context (table row or edit form).
                                    let roomName = '';
                                    const tr = link.closest('tr.pr-room-row');
                                    if (tr) {
                                        roomName = tr.dataset.roomName || '';
                                    } else {
                                        // Opened from the Edit form — read the form's current values
                                        const nameEl = document.getElementById('rf-name');
                                        roomName = nameEl ? nameEl.value.trim() : '';
                                    }

                                    const nameEl2 = document.getElementById('ps-archive-room-name');
                                    if (nameEl2) nameEl2.textContent = roomName || '—';

                                    pendingArchiveHref = link.getAttribute('href');
                                    if (typeof psOpenModal === 'function') psOpenModal('ps-archive-room-modal');
                                });

                                /* Delegated — the confirm button lives in admin-rooms-modals.php which
                                   is appended after this inline script runs, so getElementById at load
                                   time returns null. A document-level delegated listener always works. */
                                document.addEventListener('click', function(e) {
                                    const btn = e.target.closest('#ps-archive-room-confirm-btn');
                                    if (!btn) return;
                                    if (!pendingArchiveHref) return;

                                    if (typeof psCloseModal === 'function') psCloseModal('ps-archive-room-modal');

                                    const href = pendingArchiveHref;
                                    pendingArchiveHref = '';
                                    btn.disabled = true;

                                    const url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                                    fetch(url, { credentials: 'same-origin' })
                                        .then(function(res) { return res.json(); })
                                        .then(function(json) {
                                            btn.disabled = false;
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                closeRoomModal();
                                                if (typeof switchRoomsTab === 'function') switchRoomsTab('rooms-archived');
                                                if (typeof showToast === 'function') showToast(json.message || 'Facility archived.');
                                            } else {
                                                if (typeof showToast === 'function') showToast((json && json.message) || 'Could not archive facility. Please try again.');
                                            }
                                        })
                                        .catch(function() {
                                            btn.disabled = false;
                                            onNetworkError();
                                        });
                                });

                                /* Restore: intercept restore_room= links in the Archived sub-panel. */
                                document.addEventListener('click', function(e) {
                                    const link = e.target.closest('a[href*="restore_room="]');
                                    if (!link) return;
                                    e.preventDefault();
                                    if (!confirm('Restore this facility? It will reappear in the active registry.')) return;

                                    const href = link.getAttribute('href');
                                    const url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';

                                    fetch(url, {
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                if (typeof showToast === 'function') showToast(json.message || 'Facility restored.');
                                                if (typeof switchRoomsTab === 'function') switchRoomsTab('rooms-active');
                                            } else {
                                                if (typeof showToast === 'function') showToast((json && json.message) || 'Could not restore facility.');
                                            }
                                        })
                                        .catch(onNetworkError);
                                });

                                /* Lock / Unlock (Edit modal). The modal stays open so unsaved edits
                                   are not lost; the tiles behind it refresh straight away. */
                                document.addEventListener('click', function(e) {
                                    const btn = e.target.closest('[data-action="toggle-room-lock"]');
                                    if (!btn) return;
                                    const idEl = document.getElementById('room-form-id');
                                    const roomId = idEl ? idEl.value : '';
                                    if (!roomId) return;
                                    const lock = btn.dataset.lock === '1' ? '1' : '0';
                                    const tok = roomForm.querySelector('input[name="csrf_token"]');

                                    const fd = new FormData();
                                    fd.set('toggle_room_lock', '1');
                                    fd.set('room_id', roomId);
                                    fd.set('lock', lock);
                                    fd.set('ajax', '1');
                                    if (tok) fd.set('csrf_token', tok.value);

                                    btn.disabled = true;
                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            btn.disabled = false;
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                window.psSetLockButton(lock === '1');
                                                if (typeof showToast === 'function') showToast(json.message || (lock === '1' ? 'Facility locked.' : 'Facility unlocked.'));
                                            } else if (typeof showToast === 'function') {
                                                showToast((json && json.message) || 'Could not change the lock. Please try again.');
                                            }
                                        })
                                        .catch(function() {
                                            btn.disabled = false;
                                            onNetworkError();
                                        });
                                });

                                /* Permanent delete (Archived tab, Super Admin). The button only opens
                                   a confirmation dialog; nothing is sent until the box is ticked and
                                   "Delete permanently" is pressed. The server re-checks everything. */
                                /* The dialog lives at the end of <body> (after this script runs), so it
                                   is looked up when needed, never once at load. */
                                let delRoomId = '';
                                const delEl = function(id) {
                                    return document.getElementById(id);
                                };

                                function closeDeleteModal() {
                                    const m = delEl('roomDeleteConfirm');
                                    if (m) m.classList.add('hidden');
                                    delRoomId = '';
                                }

                                document.addEventListener('click', function(e) {
                                    const m = delEl('roomDeleteConfirm');
                                    if (!m) return; // regular Admins never get the dialog

                                    const openBtn = e.target.closest('[data-action="delete-room-permanent"]');
                                    if (openBtn) {
                                        delRoomId = openBtn.dataset.roomId || '';
                                        const n = parseInt(openBtn.dataset.reservations, 10) || 0;
                                        delEl('rdc-room-name').textContent = openBtn.dataset.roomName || '';
                                        delEl('rdc-resv').textContent =
                                            n === 0 ? 'No reservation records' : (n === 1 ? '1 reservation record' : n + ' reservation records');
                                        delEl('rdc-ack').checked = false;
                                        delEl('rdc-confirm').disabled = true;
                                        m.classList.remove('hidden');
                                        delEl('rdc-ack').focus();
                                        return;
                                    }
                                    if (e.target.closest('[data-action="close-room-delete"]') || e.target === m) {
                                        closeDeleteModal();
                                        return;
                                    }

                                    /* Confirm: only acts once the box is ticked. */
                                    const go = e.target.closest('#rdc-confirm');
                                    if (!go) return;
                                    const ack = delEl('rdc-ack');
                                    if (!ack.checked || !delRoomId || go.disabled) return;

                                    const tok = roomForm.querySelector('input[name="csrf_token"]');
                                    const fd = new FormData();
                                    fd.set('delete_room_permanently', '1');
                                    fd.set('room_id', delRoomId);
                                    fd.set('confirm', 'yes');
                                    fd.set('ajax', '1');
                                    if (tok) fd.set('csrf_token', tok.value);

                                    go.disabled = true;
                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                closeDeleteModal();
                                                if (typeof showToast === 'function') showToast(json.message || 'Facility permanently deleted.');
                                            } else {
                                                go.disabled = !ack.checked;
                                                if (typeof showToast === 'function') showToast((json && json.message) || 'Could not delete the facility.');
                                            }
                                        })
                                        .catch(function() {
                                            go.disabled = !ack.checked;
                                            onNetworkError();
                                        });
                                });

                                document.addEventListener('keydown', function(e) {
                                    const m = delEl('roomDeleteConfirm');
                                    if (e.key === 'Escape' && m && !m.classList.contains('hidden')) closeDeleteModal();
                                });

                                document.addEventListener('change', function(e) {
                                    if (e.target && e.target.id === 'rdc-ack') {
                                        const go = delEl('rdc-confirm');
                                        if (go) go.disabled = !e.target.checked;
                                    }
                                });
                            })();
