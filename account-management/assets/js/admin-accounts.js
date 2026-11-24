        (function() {
            document.addEventListener('click', function(event) {
                var trigger = event.target.closest('.fac-info-trigger');
                if (trigger) {
                    event.preventDefault();
                    var info = trigger.closest('.fac-info');
                    var shouldOpen = !info.classList.contains('fac-info-open');
                    document.querySelectorAll('.fac-info.fac-info-open').forEach(function(openInfo) {
                        openInfo.classList.remove('fac-info-open');
                        openInfo.querySelector('.fac-info-trigger').setAttribute('aria-expanded', 'false');
                        openInfo.querySelector('.fac-info-trigger').blur();
                    });
                    if (shouldOpen) {
                        info.classList.add('fac-info-open');
                        trigger.setAttribute('aria-expanded', 'true');
                    } else {
                        trigger.blur();
                    }
                    return;
                }

                if (!event.target.closest('.fac-info')) {
                    document.querySelectorAll('.fac-info.fac-info-open').forEach(function(openInfo) {
                        openInfo.classList.remove('fac-info-open');
                        openInfo.querySelector('.fac-info-trigger').setAttribute('aria-expanded', 'false');
                        openInfo.querySelector('.fac-info-trigger').blur();
                    });
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key !== 'Escape') return;
                document.querySelectorAll('.fac-info.fac-info-open').forEach(function(openInfo) {
                    openInfo.classList.remove('fac-info-open');
                    openInfo.querySelector('.fac-info-trigger').setAttribute('aria-expanded', 'false');
                    openInfo.querySelector('.fac-info-trigger').blur();
                });
            });

            function openFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.add('ps-modal-open');
            }

            function closeFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.remove('ps-modal-open');
                if (id === 'fac-deactivate-modal') {
                    _facDeactCheckSeq++; /* ignore any check still in flight */
                    var pw = document.getElementById('fac-deactivate-password');
                    if (pw) {
                        pw.value = '';
                        pw.type = 'password';
                    }
                }
            }

            /* Search + 10-per-page pagination for this list live in admin-dashboard.js
               (setupFacultyList), together, so the two never fight over which rows show. */

            /* Edit modal: keep a reference to the row currently being edited,
               so Save Changes can identify + update it after a successful save. */
            var _facEditRow = null;

            function _showFacEditAlert(msg, isError) {
                var box = document.getElementById('fac-edit-alert');
                if (!box) return;
                box.className = 'alert-banner ' + (isError ? 'alert-danger' : 'alert-success');
                box.textContent = msg;
                box.classList.remove('hidden');
            }

            function _clearFacEditAlert() {
                var box = document.getElementById('fac-edit-alert');
                if (!box) return;
                box.classList.add('hidden');
                box.textContent = '';
            }

            /* Edit modal: open on row edit-button click */
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('#fac-list-table .fac-edit-btn');
                if (!btn) return;
                var row = btn.closest('tr');
                if (!row) return;

                _facEditRow = row;
                _clearFacEditAlert();

                var fullname = row.dataset.fullname || '';
                var email = row.dataset.email || '';
                var facId = row.dataset.facultyId || '';
                var init = row.dataset.init || (fullname.charAt(0).toUpperCase()) || 'F';
                var idUnset = !facId || facId.indexOf('NOTSET-') === 0;
                var isActive = row.dataset.active !== '0';
                var subLine = (idUnset ? 'Faculty ID not set yet' : facId) + (isActive ? ' \u00B7 Active Faculty' : ' \u00B7 Deactivated');

                var parts = fullname.trim().split(' ');
                var lastName = parts.length > 1 ? parts[parts.length - 1] : '';
                var firstName = parts.length > 1 ? parts.slice(0, -1).join(' ') : parts[0];

                document.getElementById('fac-edit-avatar').textContent = init;
                document.getElementById('fac-edit-name-display').textContent = fullname;
                document.getElementById('fac-edit-meta-display').textContent = subLine;
                document.getElementById('fac-edit-first').value = firstName;
                document.getElementById('fac-edit-last').value = lastName;
                document.getElementById('fac-edit-email').value = email;
                document.getElementById('fac-deactivate-name').textContent = fullname;
                _facSyncEditFooter(isActive);

                openFacModal('fac-edit-modal');
            });

            /* Edit modal footer: an active account offers Deactivate, a deactivated one offers Reactivate. */
            function _facSyncEditFooter(isActive) {
                var off = document.getElementById('fac-edit-deactivate-btn');
                var on = document.getElementById('fac-edit-reactivate-btn');
                if (off) off.classList.toggle('hidden', !isActive);
                if (on) on.classList.toggle('hidden', isActive);
            }

            /* Show a row as active or deactivated (badge + muted look). The row is never removed. */
            function _facSetRowActive(row, active) {
                if (!row || typeof window.psFacRender !== 'function') return;
                window.psFacRender(row, {
                    fullname: row.dataset.fullname || '',
                    email: row.dataset.email || '',
                    faculty_id: row.dataset.facultyId || '',
                    is_active: active ? 1 : 0
                });
            }

            /* Edit modal: close */
            ['fac-edit-close', 'fac-edit-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-edit-modal');
                });
            });

            /* ── Deactivate Account ──────────────────────────────────────
               Nothing is deleted: the account and all its data stay, the person just can't sign in
               until a Super Admin reactivates it.
               The confirm modal shows ONE state at a time:
                 checking -> confirm  (nothing blocking)
                          -> blocked  (open bookings: Super Admin can override
                                       by re-entering their own password) */
            var _facDeactView = 'checking';
            var _facDeactCheckSeq = 0;

            function _facDeactEl(id) {
                return document.getElementById(id);
            }

            function _facDeactClearError() {
                var box = _facDeactEl('fac-deactivate-error');
                if (box) {
                    box.classList.add('hidden');
                    box.textContent = '';
                }
                /* the plain note comes back once an error is gone */
                var note = _facDeactEl('fac-deactivate-view-confirm');
                if (note && _facDeactView === 'confirm') note.classList.remove('hidden');
            }

            function _facDeactShowError(msg) {
                var box = _facDeactEl('fac-deactivate-error');
                if (!box) return;
                /* only one message at a time: an error replaces the plain note */
                var note = _facDeactEl('fac-deactivate-view-confirm');
                if (note) note.classList.add('hidden');
                box.textContent = msg;
                box.classList.remove('hidden');
            }

            function _facDeactSetView(view) {
                _facDeactView = view;
                ['checking', 'confirm', 'blocked'].forEach(function(v) {
                    var el = _facDeactEl('fac-deactivate-view-' + v);
                    if (el) el.classList.toggle('hidden', v !== view);
                });
                var btn = _facDeactEl('fac-deactivate-confirm');
                var lbl = _facDeactEl('fac-deactivate-confirm-label');
                if (btn) btn.disabled = (view === 'checking');
                if (lbl) lbl.textContent = (view === 'blocked') ? 'Deactivate Anyway' : 'Deactivate Account';
                var box = _facDeactEl('fac-deactivate-error');
                if (box) {
                    box.classList.add('hidden');
                    box.textContent = '';
                }
                if (view === 'blocked') {
                    var pw = _facDeactEl('fac-deactivate-password');
                    if (pw) {
                        pw.value = '';
                        setTimeout(function() {
                            pw.focus();
                        }, 50);
                    }
                }
            }

            function _facDeactRenderBlocked(d) {
                var list = _facDeactEl('fac-deactivate-list');
                if (!list) return;
                list.innerHTML = '';
                var rows = [
                    [d.waiting, 'pending request', 'pending requests', 'Will be declined'],
                    [d.out, 'item still borrowed', 'items still borrowed', 'Can still be returned'],
                    [d.rooms, 'upcoming facility booking', 'upcoming facility bookings', 'Will be cancelled']
                ];
                rows.forEach(function(r) {
                    var n = parseInt(r[0], 10) || 0;
                    if (n < 1) return;
                    var li = document.createElement('li');
                    var c = document.createElement('span');
                    c.className = 'fac-del-count';
                    c.textContent = n;
                    var l = document.createElement('span');
                    l.className = 'fac-del-label';
                    l.textContent = (n === 1) ? r[1] : r[2];
                    var o = document.createElement('span');
                    o.className = 'fac-del-outcome';
                    o.textContent = r[3];
                    li.appendChild(c);
                    li.appendChild(l);
                    li.appendChild(o);
                    list.appendChild(li);
                });
                _facDeactSetView('blocked');
            }

            function _facCsrf() {
                var el = document.querySelector('input[name="csrf_token"]');
                return el ? el.value : '';
            }

            var facEditDeactBtn = document.getElementById('fac-edit-deactivate-btn');
            if (facEditDeactBtn) {
                facEditDeactBtn.addEventListener('click', function() {
                    if (!_facEditRow) return;
                    var facultyId = _facEditRow.dataset.facultyId || '';
                    var nameEl = _facDeactEl('fac-deactivate-name');
                    if (nameEl) nameEl.textContent = _facEditRow.dataset.fullname || 'this faculty member';

                    _facDeactSetView('checking');
                    closeFacModal('fac-edit-modal');
                    openFacModal('fac-deactivate-modal');

                    /* Dry run: find out up front whether anything blocks the deactivation,
                       so the right message is the first (and only) one shown. */
                    var seq = ++_facDeactCheckSeq;
                    fetch('account-management/api/deactivate-faculty-account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                csrf_token: _facCsrf(),
                                faculty_id: facultyId,
                                dry_run: '1'
                            }).toString()
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            if (seq !== _facDeactCheckSeq) return;
                            if (data && data.status === 'success' && data.blocked && data.details) {
                                _facDeactRenderBlocked(data.details);
                            } else {
                                /* clear, or the check itself failed: the real request
                                   re-checks on the server and reports any problem */
                                _facDeactSetView('confirm');
                            }
                        })
                        .catch(function() {
                            if (seq !== _facDeactCheckSeq) return;
                            _facDeactSetView('confirm');
                        });
                });
            }

            /* Keep the "Faculty List (N)" badge in step with the table when a row is added
               (create form). Deactivated accounts stay in the list, so they stay in the count. */
            var _facTbody = document.getElementById('faculty-list-tbody');

            function _facRefreshCount() {
                var badge = document.getElementById('fac-count-badge');
                if (!badge || !_facTbody) return;
                badge.textContent = '(' + _facTbody.querySelectorAll('tr[data-faculty-id]').length + ')';
            }
            if (_facTbody && window.MutationObserver) {
                new MutationObserver(_facRefreshCount).observe(_facTbody, {
                    childList: true
                });
            }

            /* Refresh the faculty list tbody from the server fragment endpoint.
               Called after in-place account creation so the new row appears without
               a full page reload. Exposed globally so admin-dashboard.js can call it. */
            window.adminRefreshFacultyList = function() {
                return fetch('admin-dashboard.php?fragment=faculty-tbody', {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status !== 'success') return;
                        var before = _facTbody ? Array.prototype.map.call(_facTbody.querySelectorAll('tr[data-faculty-id]'), function(r) {
                            return r.dataset.email;
                        }) : [];
                        if (_facTbody) _facTbody.innerHTML = json.faculty_html;
                        // Edit buttons are wired via delegated listener on #fac-list-table,
                        // so no per-row re-binding is needed after the innerHTML swap.
                        _facRefreshCount();
                        // The list is paged: show the page that holds the new account.
                        if (typeof window.psFacultyListRevealNew === 'function') window.psFacultyListRevealNew(before);
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Could not refresh the faculty list. Please reload the page.');
                    });
            };

            /* Save Changes */
            var facEditSaveBtn = document.getElementById('fac-edit-save');
            if (facEditSaveBtn) {
                facEditSaveBtn.addEventListener('click', function() {
                    _clearFacEditAlert();
                    if (!_facEditRow) return;

                    var facultyId = _facEditRow.dataset.facultyId || '';
                    var firstName = (document.getElementById('fac-edit-first').value || '').trim();
                    var lastName = (document.getElementById('fac-edit-last').value || '').trim();
                    var email = (document.getElementById('fac-edit-email').value || '').trim();

                    if (!firstName) {
                        _showFacEditAlert('First name is required.', true);
                        return;
                    }
                    if (!lastName) {
                        _showFacEditAlert('Last name is required.', true);
                        return;
                    }
                    if (!email) {
                        _showFacEditAlert('PUPSync email is required.', true);
                        return;
                    }
                    /* A new or changed email must end in @pupsync.edu (an unchanged older email is left alone). */
                    var originalEmail = (_facEditRow.dataset.email || '').toLowerCase();
                    if (email.toLowerCase() !== originalEmail && !/^[^\s@]+@pupsync\.edu$/i.test(email)) {
                        _showFacEditAlert('Use a PUPSync email ending in @pupsync.edu.', true);
                        return;
                    }
                    var savedLabel = facEditSaveBtn.innerHTML;
                    facEditSaveBtn.disabled = true;
                    facEditSaveBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Saving...';

                    var csrfEl = document.querySelector('input[name="csrf_token"]');
                    var body = new URLSearchParams({
                        csrf_token: csrfEl ? csrfEl.value : '',
                        faculty_id: facultyId,
                        pupsync_email: email,
                        first_name: firstName,
                        last_name: lastName
                    });

                    fetch('account-management/api/update-faculty-account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: body.toString()
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            if (data.status === 'success') {
                                var fullname = [firstName, lastName].join(' ').trim();
                                // Re-render the row (name, email) from the saved values
                                if (typeof window.psFacRender === 'function') {
                                    window.psFacRender(_facEditRow, {
                                        fullname: fullname,
                                        email: email,
                                        faculty_id: facultyId
                                    });
                                }

                                closeFacModal('fac-edit-modal');
                                if (typeof showToast === 'function') showToast('Faculty account updated successfully.');
                            } else {
                                _showFacEditAlert(data.message || 'Could not save changes.', true);
                            }
                        })
                        .catch(function() {
                            _showFacEditAlert('Network error. Please try again.', true);
                        })
                        .finally(function() {
                            facEditSaveBtn.disabled = false;
                            facEditSaveBtn.innerHTML = savedLabel;
                        });
                });
            }

            /* Deactivate modal: close */
            ['fac-deactivate-close', 'fac-deactivate-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-deactivate-modal');
                });
            });

            /* Deactivate modal: confirm */
            var facDeactConf = document.getElementById('fac-deactivate-confirm');
            var facDeactPw = document.getElementById('fac-deactivate-password');

            function _facDeactSubmit() {
                if (!_facEditRow || _facDeactView === 'checking') return;

                var override = (_facDeactView === 'blocked');
                var password = facDeactPw ? facDeactPw.value : '';
                if (override && !password) {
                    _facDeactShowError('Enter your password to continue.');
                    if (facDeactPw) facDeactPw.focus();
                    return;
                }
                _facDeactClearError();

                var rowToDeactivate = _facEditRow;
                var params = {
                    csrf_token: _facCsrf(),
                    faculty_id: rowToDeactivate.dataset.facultyId || ''
                };
                if (override) {
                    params.override = '1';
                    params.current_password = password;
                }

                var savedLabel = facDeactConf.innerHTML;
                facDeactConf.disabled = true;
                facDeactConf.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Deactivating...';

                fetch('account-management/api/deactivate-faculty-account.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams(params).toString()
                    })
                    .then(function(res) {
                        return res.json().catch(function() {
                            return {
                                status: 'error',
                                message: 'Unexpected response from the server.'
                            };
                        });
                    })
                    .then(function(data) {
                        if (data.status === 'success') {
                            /* The row stays: it is re-rendered with the "Deactivated" badge. */
                            _facSetRowActive(rowToDeactivate, false);
                            closeFacModal('fac-deactivate-modal');
                            if (typeof showToast === 'function') showToast('Faculty account deactivated.');
                        } else if (data.code === 'already_inactive') {
                            /* another Super Admin got there first: just show the current state */
                            _facSetRowActive(rowToDeactivate, false);
                            closeFacModal('fac-deactivate-modal');
                            if (typeof showToast === 'function') showToast(data.message || 'That account is already deactivated.');
                        } else if (data.code === 'blocked' && data.details) {
                            /* bookings appeared since the check: switch to the override view */
                            _facDeactRenderBlocked(data.details);
                        } else if (data.code === 'bad_password') {
                            _facDeactShowError(data.message || 'Incorrect password.');
                            if (facDeactPw) {
                                facDeactPw.focus();
                                facDeactPw.select();
                            }
                        } else {
                            _facDeactShowError(data.message || data.error || 'Could not deactivate this account.');
                        }
                    })
                    .catch(function() {
                        _facDeactShowError('Network error. Please try again.');
                    })
                    .finally(function() {
                        facDeactConf.innerHTML = savedLabel;
                        facDeactConf.disabled = (_facDeactView === 'checking');
                    });
            }

            if (facDeactConf) facDeactConf.addEventListener('click', _facDeactSubmit);
            if (facDeactPw) {
                facDeactPw.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        _facDeactSubmit();
                    }
                });
                facDeactPw.addEventListener('input', _facDeactClearError);
            }

            /* ── Reactivate Account ──────────────────────────────────────
               Undoes a deactivation straight away (nothing destructive to confirm). */
            var facEditReactBtn = document.getElementById('fac-edit-reactivate-btn');
            if (facEditReactBtn) {
                facEditReactBtn.addEventListener('click', function() {
                    if (!_facEditRow) return;
                    _clearFacEditAlert();
                    var row = _facEditRow;
                    var savedLabel = facEditReactBtn.innerHTML;
                    facEditReactBtn.disabled = true;
                    facEditReactBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Reactivating...';

                    fetch('account-management/api/reactivate-faculty-account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                csrf_token: _facCsrf(),
                                faculty_id: row.dataset.facultyId || ''
                            }).toString()
                        })
                        .then(function(res) {
                            return res.json().catch(function() {
                                return {
                                    status: 'error',
                                    message: 'Unexpected response from the server.'
                                };
                            });
                        })
                        .then(function(data) {
                            if (data.status === 'success' || data.code === 'already_active') {
                                _facSetRowActive(row, true);
                                closeFacModal('fac-edit-modal');
                                if (typeof showToast === 'function') {
                                    showToast(data.status === 'success' ? 'Faculty account reactivated.' : (data.message || 'That account is already active.'));
                                }
                            } else {
                                _showFacEditAlert(data.message || 'Could not reactivate this account.', true);
                            }
                        })
                        .catch(function() {
                            _showFacEditAlert('Network error. Please try again.', true);
                        })
                        .finally(function() {
                            facEditReactBtn.disabled = false;
                            facEditReactBtn.innerHTML = savedLabel;
                        });
                });
            }

            /* Backdrop click to close */
            ['fac-edit-modal', 'fac-deactivate-modal'].forEach(function(id) {
                var bd = document.getElementById(id);
                if (bd) bd.addEventListener('click', function(e) {
                    if (e.target === bd) closeFacModal(id);
                });
            });

        }());
