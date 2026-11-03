        (function() {
            function openFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.add('ps-modal-open');
            }

            function closeFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.remove('ps-modal-open');
                if (id === 'fac-delete-modal') {
                    _facDelCheckSeq++; /* ignore any check still in flight */
                    var pw = document.getElementById('fac-delete-password');
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

            function _syncFacEditOrgVisibility() {
                var chk = document.getElementById('fac-edit-adviser');
                var grp = document.getElementById('fac-edit-org-group');
                var sel = document.getElementById('fac-edit-org');
                if (!chk || !grp) return;
                grp.style.display = chk.checked ? '' : 'none';

                /* The organization picker only exists for advisers. Hiding it also
                   clears it, so a regular faculty account is never saved with an
                   organization. (Org borrowing itself is automatic for advisers.) */
                if (!chk.checked && sel) sel.value = '';
            }

            var _facEditAdviserChk = document.getElementById('fac-edit-adviser');
            if (_facEditAdviserChk) {
                _facEditAdviserChk.addEventListener('change', _syncFacEditOrgVisibility);
            }

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

            /* One adviser per organization. Which organization has which adviser
               (organization id -> {faculty_id, name}) is seeded by the server and kept in
               step with the edits and deletes made on this page. It only drives the UI:
               the server re-checks every save, so a stale page can never break the rule. */
            var _facAdvisers = {};
            (function() {
                var s = document.getElementById('fac-edit-org');
                if (!s) return;
                try {
                    _facAdvisers = JSON.parse(s.getAttribute('data-advisers') || '{}') || {};
                } catch (e) {
                    _facAdvisers = {};
                }
            })();

            /* Disable the organizations that already have an adviser (except the one belonging
               to exceptFacultyId, so an adviser can keep their own organization). */
            function _facApplyOrgAvailability(sel, exceptFacultyId) {
                if (!sel) return;
                Array.prototype.forEach.call(sel.options, function(opt) {
                    if (!opt.value) return;
                    var holder = _facAdvisers[opt.value];
                    var taken = !!holder && holder.faculty_id !== exceptFacultyId;
                    var name = opt.getAttribute('data-name') || opt.textContent;
                    opt.disabled = taken;
                    opt.textContent = taken ? name + ' (taken)' : name;
                });
            }

            function _facAdvisersForget(facultyId) {
                Object.keys(_facAdvisers).forEach(function(k) {
                    if (_facAdvisers[k].faculty_id === facultyId) delete _facAdvisers[k];
                });
                _facApplyOrgAvailability(document.getElementById('fac-org'), '');
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
                var backupEmail = row.dataset.backupEmail || '';
                var facId = row.dataset.facultyId || '';
                var role = row.dataset.role || '';
                var org = row.dataset.org || '';
                var orgId = row.dataset.orgId || '';
                var init = row.dataset.init || (fullname.charAt(0).toUpperCase()) || 'F';
                var isAdviser = role === 'Organization Adviser';
                var idUnset = !facId || facId.indexOf('NOTSET-') === 0;
                var subLine = (idUnset ? 'Faculty ID not set yet' : facId) + ' \u00B7 ' + (isAdviser && org ? 'Org Adviser' : 'Active Faculty');

                var parts = fullname.trim().split(' ');
                var lastName = parts.length > 1 ? parts[parts.length - 1] : '';
                var firstName = parts.length > 1 ? parts.slice(0, -1).join(' ') : parts[0];

                document.getElementById('fac-edit-avatar').textContent = init;
                document.getElementById('fac-edit-name-display').textContent = fullname;
                document.getElementById('fac-edit-meta-display').textContent = subLine;
                document.getElementById('fac-edit-first').value = firstName;
                document.getElementById('fac-edit-last').value = lastName;
                document.getElementById('fac-edit-email').value = email;
                document.getElementById('fac-edit-backup').value = backupEmail;
                document.getElementById('fac-edit-adviser').checked = isAdviser;
                var orgSel = document.getElementById('fac-edit-org');
                _facApplyOrgAvailability(orgSel, facId);
                if (orgSel) orgSel.value = (isAdviser && orgId) ? orgId : '';
                document.getElementById('fac-delete-name').textContent = fullname;

                _syncFacEditOrgVisibility();
                openFacModal('fac-edit-modal');
            });

            /* Edit modal: close */
            ['fac-edit-close', 'fac-edit-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-edit-modal');
                });
            });

            /* ── Delete Account ──────────────────────────────────────────
               The confirm modal shows ONE state at a time:
                 checking -> confirm  (nothing blocking)
                          -> blocked  (open bookings: Super Admin can override
                                       by re-entering their own password) */
            var _facDelView = 'checking';
            var _facDelCheckSeq = 0;

            function _facDelEl(id) {
                return document.getElementById(id);
            }

            function _facDelClearError() {
                var box = _facDelEl('fac-delete-error');
                if (box) {
                    box.classList.add('hidden');
                    box.textContent = '';
                }
                /* the plain note comes back once an error is gone */
                var note = _facDelEl('fac-delete-view-confirm');
                if (note && _facDelView === 'confirm') note.classList.remove('hidden');
            }

            function _facDelShowError(msg) {
                var box = _facDelEl('fac-delete-error');
                if (!box) return;
                /* only one message at a time: an error replaces the plain note */
                var note = _facDelEl('fac-delete-view-confirm');
                if (note) note.classList.add('hidden');
                box.textContent = msg;
                box.classList.remove('hidden');
            }

            function _facDelSetView(view) {
                _facDelView = view;
                ['checking', 'confirm', 'blocked'].forEach(function(v) {
                    var el = _facDelEl('fac-delete-view-' + v);
                    if (el) el.classList.toggle('hidden', v !== view);
                });
                var btn = _facDelEl('fac-delete-confirm');
                var lbl = _facDelEl('fac-delete-confirm-label');
                if (btn) btn.disabled = (view === 'checking');
                if (lbl) lbl.textContent = (view === 'blocked') ? 'Delete Anyway' : 'Delete Account';
                var box = _facDelEl('fac-delete-error');
                if (box) {
                    box.classList.add('hidden');
                    box.textContent = '';
                }
                if (view === 'blocked') {
                    var pw = _facDelEl('fac-delete-password');
                    if (pw) {
                        pw.value = '';
                        setTimeout(function() {
                            pw.focus();
                        }, 50);
                    }
                }
            }

            function _facDelRenderBlocked(d) {
                var list = _facDelEl('fac-delete-list');
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
                _facDelSetView('blocked');
            }

            function _facCsrf() {
                var el = document.querySelector('input[name="csrf_token"]');
                return el ? el.value : '';
            }

            var facEditDelBtn = document.getElementById('fac-edit-delete-btn');
            if (facEditDelBtn) {
                facEditDelBtn.addEventListener('click', function() {
                    if (!_facEditRow) return;
                    var facultyId = _facEditRow.dataset.facultyId || '';
                    var nameEl = _facDelEl('fac-delete-name');
                    if (nameEl) nameEl.textContent = _facEditRow.dataset.fullname || 'this faculty member';

                    _facDelSetView('checking');
                    closeFacModal('fac-edit-modal');
                    openFacModal('fac-delete-modal');

                    /* Dry run: find out up front whether anything blocks the delete,
                       so the right message is the first (and only) one shown. */
                    var seq = ++_facDelCheckSeq;
                    fetch('account-management/api/delete-faculty-account.php', {
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
                            if (seq !== _facDelCheckSeq) return;
                            if (data && data.status === 'success' && data.blocked && data.details) {
                                _facDelRenderBlocked(data.details);
                            } else {
                                /* clear, or the check itself failed: the real delete
                                   re-checks on the server and reports any problem */
                                _facDelSetView('confirm');
                            }
                        })
                        .catch(function() {
                            if (seq !== _facDelCheckSeq) return;
                            _facDelSetView('confirm');
                        });
                });
            }

            /* Keep the "Faculty List (N)" badge in step with the table, whether a
               row is added (create form) or removed (delete). */
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
                fetch('admin-dashboard.php?fragment=faculty-tbody', {
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
                    var backup = (document.getElementById('fac-edit-backup').value || '').trim();
                    var isAdviser = document.getElementById('fac-edit-adviser').checked ? '1' : '0';
                    var orgId = document.getElementById('fac-edit-org') ? document.getElementById('fac-edit-org').value : '';

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
                    if (isAdviser === '1' && !orgId) {
                        _showFacEditAlert('An organization must be selected for an adviser.', true);
                        return;
                    }
                    /* A new or changed email must end in @pupsync.edu (an unchanged older email is left alone). */
                    var originalEmail = (_facEditRow.dataset.email || '').toLowerCase();
                    if (email.toLowerCase() !== originalEmail && !/^[^\s@]+@pupsync\.edu$/i.test(email)) {
                        _showFacEditAlert('Use a PUPSync email ending in @pupsync.edu.', true);
                        return;
                    }
                    /* One adviser per organization (only when the organization is new for this person). */
                    var holder = (isAdviser === '1') ? _facAdvisers[orgId] : null;
                    var orgUnchanged = _facEditRow.dataset.role === 'Organization Adviser' &&
                        String(_facEditRow.dataset.orgId || '') === String(orgId);
                    if (holder && holder.faculty_id !== facultyId && !orgUnchanged) {
                        var heldOpt = document.getElementById('fac-edit-org').querySelector('option[value="' + orgId + '"]');
                        _showFacEditAlert(((heldOpt && heldOpt.getAttribute('data-name')) || 'That organization') +
                            ' already has an adviser: ' + holder.name + '.', true);
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
                        backup_email: backup,
                        first_name: firstName,
                        last_name: lastName,
                        is_org_adviser: isAdviser,
                        organization_id: orgId
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
                                var role = isAdviser === '1' ? 'Organization Adviser' : 'Regular Faculty';
                                var orgSelEl = document.getElementById('fac-edit-org');
                                var selOpt = orgSelEl ? orgSelEl.options[orgSelEl.selectedIndex] : null;
                                var orgName = (isAdviser === '1' && selOpt) ?
                                    (selOpt.getAttribute('data-name') || selOpt.text || '') :
                                    '';
                                var orgNameSafe = (orgId && isAdviser === '1') ? orgName : '';

                                // Keep the "who advises what" map in step with this save
                                _facAdvisersForget(facultyId);
                                if (isAdviser === '1' && orgId) {
                                    _facAdvisers[orgId] = {
                                        faculty_id: facultyId,
                                        name: fullname
                                    };
                                    _facApplyOrgAvailability(document.getElementById('fac-org'), '');
                                }

                                // Re-render the row (name, email, role, organization) from the saved values
                                if (typeof window.psFacRender === 'function') {
                                    window.psFacRender(_facEditRow, {
                                        fullname: fullname,
                                        email: email,
                                        backup_email: backup,
                                        role: role,
                                        org_name: orgNameSafe,
                                        org_id: isAdviser === '1' ? orgId : '0',
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

            /* Delete modal: close */
            ['fac-delete-close', 'fac-delete-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-delete-modal');
                });
            });

            /* Delete modal: confirm */
            var facDelConf = document.getElementById('fac-delete-confirm');
            var facDelPw = document.getElementById('fac-delete-password');

            function _facDelSubmit() {
                if (!_facEditRow || _facDelView === 'checking') return;

                var override = (_facDelView === 'blocked');
                var password = facDelPw ? facDelPw.value : '';
                if (override && !password) {
                    _facDelShowError('Enter your password to continue.');
                    if (facDelPw) facDelPw.focus();
                    return;
                }
                _facDelClearError();

                var rowToRemove = _facEditRow;
                var params = {
                    csrf_token: _facCsrf(),
                    faculty_id: rowToRemove.dataset.facultyId || ''
                };
                if (override) {
                    params.override = '1';
                    params.current_password = password;
                }

                var savedLabel = facDelConf.innerHTML;
                facDelConf.disabled = true;
                facDelConf.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Deleting...';

                fetch('account-management/api/delete-faculty-account.php', {
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
                            rowToRemove.remove();
                            _facEditRow = null;
                            _facAdvisersForget(params.faculty_id);

                            /* Table is now empty -> show the empty state again */
                            if (_facTbody && !_facTbody.querySelector('tr[data-faculty-id]')) {
                                var tr = document.createElement('tr');
                                tr.id = 'fac-empty-row';
                                tr.innerHTML = '<td colspan="4" style="text-align:center;padding:3rem;color:var(--text-light)">' +
                                    '<span class="material-symbols-outlined" style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>' +
                                    'No faculty accounts yet.</td>';
                                _facTbody.appendChild(tr);
                            }

                            closeFacModal('fac-delete-modal');
                            if (typeof showToast === 'function') showToast('Faculty account deleted.', 't-danger');
                        } else if (data.code === 'blocked' && data.details) {
                            /* bookings appeared since the check: switch to the override view */
                            _facDelRenderBlocked(data.details);
                        } else if (data.code === 'bad_password') {
                            _facDelShowError(data.message || 'Incorrect password.');
                            if (facDelPw) {
                                facDelPw.focus();
                                facDelPw.select();
                            }
                        } else {
                            _facDelShowError(data.message || data.error || 'Could not delete this account.');
                        }
                    })
                    .catch(function() {
                        _facDelShowError('Network error. Please try again.');
                    })
                    .finally(function() {
                        facDelConf.innerHTML = savedLabel;
                        facDelConf.disabled = (_facDelView === 'checking');
                    });
            }

            if (facDelConf) facDelConf.addEventListener('click', _facDelSubmit);
            if (facDelPw) {
                facDelPw.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        _facDelSubmit();
                    }
                });
                facDelPw.addEventListener('input', _facDelClearError);
            }

            /* Backdrop click to close */
            ['fac-edit-modal', 'fac-delete-modal'].forEach(function(id) {
                var bd = document.getElementById(id);
                if (bd) bd.addEventListener('click', function(e) {
                    if (e.target === bd) closeFacModal(id);
                });
            });

        }());
