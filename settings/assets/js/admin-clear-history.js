/* ═══════════════════════════════════════════════════════════════════
   SETTINGS › DATA › "Clear History"  (Super Admin only)
   1) pick what to clear (equipment loans / room reservations / everything), then press "Clear history…"
   2) the dialog opens and the server counts exactly what is in the chosen scope
      (settings/api/clear-history.php, dry_run=1) - nothing is deleted yet
   3) the Super Admin types their OWN password and confirms. The same endpoint re-checks role + password,
      puts back the stock of loans still out, deletes the records, and the page reloads.
   The password is wiped from the field as soon as it is sent or the dialog closes.
   ═══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var API = 'settings/api/clear-history.php';
    var FLASH_KEY = 'psHistoryCleared';

    function $(id) { return document.getElementById(id); }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

    function init() {
        var panel = $('sett-data');
        var openBtn = $('ch-open');
        var modal = $('ch-modal');
        if (!panel || !openBtn || !modal) return;   // not a Super Admin

        var hint = $('ch-open-hint');
        var result = $('ch-result');
        var title = $('ch-title');
        var lead = $('ch-lead');
        var summary = $('ch-summary');
        var note = $('ch-note');
        var pw = $('ch-pw');
        var toggle = $('ch-pw-toggle');
        var eye = toggle.querySelector('.ch-pw-eye');
        var errBox = $('ch-error');
        var okBtn = $('ch-confirm');
        var okHtml = okBtn.innerHTML;

        var scope = '';
        var counted = false;      // the server has told us there is something to delete
        var busy = false;
        var leaving = false;      // success: the page is about to reload
        var loadSeq = 0;          // ignores a late "count" reply after the dialog was closed/reopened

        var TEXT = {
            equipment: { title: 'Clear all equipment loans?', lead: 'Every equipment loan record will be permanently deleted. This cannot be undone.' },
            rooms: { title: 'Clear all room reservations?', lead: 'Every room reservation will be permanently deleted. This cannot be undone.' },
            all: { title: 'Clear everything?', lead: 'Every equipment loan record and every room reservation will be permanently deleted. This cannot be undone.' }
        };

        function csrf() {
            var t = panel.getAttribute('data-csrf');
            if (t) return t;
            var el = document.querySelector('input[name="csrf_token"]');
            return el ? el.value : '';
        }
        function chosen() {
            var r = panel.querySelector('input[name="ch-scope"]:checked');
            return r ? r.value : '';
        }
        function isOpen() { return modal.classList.contains('ps-modal-open'); }
        function closeModal() { modal.classList.remove('ps-modal-open'); }
        function setError(msg) { errBox.textContent = msg; errBox.classList.remove('hidden'); }
        function clearError() { errBox.textContent = ''; errBox.classList.add('hidden'); }

        function setBusyLabel(on) {
            if (on) {
                okBtn.innerHTML = '<span class="material-symbols-outlined ch-spin">progress_activity</span> Deleting\u2026';
            } else {
                okBtn.innerHTML = okHtml;
            }
        }

        function resetForm() {
            pw.value = '';
            pw.type = 'password';
            eye.textContent = 'visibility';
            clearError();
            note.textContent = '';
            note.classList.add('hidden');
            counted = false;
            busy = false;
            okBtn.disabled = true;
            setBusyLabel(false);
        }

        /* ── the scope picker ── */
        function syncPicker() {
            scope = chosen();
            openBtn.disabled = scope === '';
            if (hint) hint.hidden = scope !== '';
        }

        /* ── the count list ── */
        function line(text, num, cls) {
            var li = document.createElement('li');
            if (cls) li.className = cls;
            var a = document.createElement('span');
            a.textContent = text;
            var b = document.createElement('span');
            b.className = 'eq-wipe-num';
            b.textContent = String(num);
            li.appendChild(a);
            li.appendChild(b);
            return li;
        }
        function head(text) {
            var li = document.createElement('li');
            li.className = 'ch-sum-head';
            li.textContent = text;
            return li;
        }
        function message(text) {
            summary.textContent = '';
            var li = document.createElement('li');
            li.className = 'eq-wipe-loading';
            li.textContent = text;
            summary.appendChild(li);
        }

        function renderCounts(counts) {
            var eq = counts && counts.equipment ? counts.equipment : null;
            var rm = counts && counts.rooms ? counts.rooms : null;
            var eqTotal = eq ? eq.total : 0;
            var rmTotal = rm ? rm.total : 0;

            if (eqTotal + rmTotal === 0) {
                message('Nothing to delete \u2014 the selected history is already empty.');
                counted = false;
                okBtn.disabled = true;
                return;
            }

            summary.textContent = '';
            var both = !!(eq && rm);

            if (eq) {
                if (both) summary.appendChild(head('Equipment loans'));
                if (eq.waiting) summary.appendChild(line('Pending requests (waiting for review)', eq.waiting));
                if (eq.scheduled) summary.appendChild(line('Scheduled (later-date) bookings', eq.scheduled));
                if (eq.out) summary.appendChild(line('Loans currently out (active or overdue)', eq.out));
                if (eq.returned) summary.appendChild(line('Returned loans (history)', eq.returned));
                if (eq.other) summary.appendChild(line('Declined or cancelled requests', eq.other));
                if (!eq.total) summary.appendChild(line('No equipment records', 0));
                else summary.appendChild(line('Equipment records to delete', eq.total, 'is-total'));
            }
            if (rm) {
                if (both) summary.appendChild(head('Room reservations'));
                if (rm.upcoming) summary.appendChild(line('Upcoming reservations', rm.upcoming));
                if (rm.completed) summary.appendChild(line('Past reservations (history)', rm.completed));
                if (rm.declined) summary.appendChild(line('Declined reservations', rm.declined));
                if (rm.cancelled) summary.appendChild(line('Cancelled reservations', rm.cancelled));
                if (!rm.total) summary.appendChild(line('No room reservations', 0));
                else summary.appendChild(line('Room reservations to delete', rm.total, 'is-total'));
            }

            if (eq && eq.restore_units > 0) {
                note.textContent = plural(eq.restore_units, 'unit', 'units') +
                    ' of equipment that are still out will be added back to the Catalog stock.';
                note.classList.remove('hidden');
            }
            counted = true;
            okBtn.disabled = false;
        }

        function open() {
            scope = chosen();
            if (!scope) return;
            resetForm();
            title.textContent = TEXT[scope].title;
            lead.textContent = TEXT[scope].lead;
            message('Counting records\u2026');
            modal.classList.add('ps-modal-open');

            var seq = ++loadSeq;
            fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrf(), scope: scope, dry_run: '1' }).toString()
            })
                .then(function (res) {
                    return res.json().catch(function () { return { status: 'error', message: 'Unexpected response from the server.' }; });
                })
                .then(function (data) {
                    if (seq !== loadSeq || !isOpen()) return;
                    if (data && data.status === 'success' && data.counts) {
                        renderCounts(data.counts);
                    } else {
                        summary.textContent = '';
                        setError((data && data.message) || 'Could not count the records. Please try again.');
                    }
                })
                .catch(function () {
                    if (seq !== loadSeq || !isOpen()) return;
                    summary.textContent = '';
                    setError('Network error. Please try again.');
                });

            setTimeout(function () { pw.focus(); }, 50);
        }

        function confirmDelete() {
            if (busy || !counted || !scope) return;
            var pass = pw.value;
            if (pass === '') { setError('Enter your password to continue.'); pw.focus(); return; }

            busy = true;
            okBtn.disabled = true;
            setBusyLabel(true);
            clearError();

            fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrf(), scope: scope, current_password: pass }).toString()
            })
                .then(function (res) {
                    return res.json().catch(function () { return { status: 'error', message: 'Unexpected response from the server. Please try again.' }; });
                })
                .then(function (data) {
                    if (data && data.status === 'success') {
                        leaving = true;
                        pw.value = '';                // wipe the password straight away
                        closeModal();
                        try { sessionStorage.setItem(FLASH_KEY, data.message || 'History cleared.'); } catch (_) { }
                        // Reload so the tables, tab counts, stock and dashboard figures all come from the server.
                        window.location.reload();
                        return;
                    }
                    setError((data && data.message) || 'Could not clear the history. Please try again.');
                    if (data && data.code === 'bad_password') { pw.value = ''; pw.focus(); }
                })
                .catch(function () {
                    setError('Network error. Nothing was deleted. Please try again.');
                })
                .finally(function () {
                    if (leaving) return;
                    busy = false;
                    setBusyLabel(false);
                    okBtn.disabled = !counted;
                });
        }

        /* ── wiring ── */
        Array.prototype.forEach.call(panel.querySelectorAll('input[name="ch-scope"]'), function (r) {
            r.addEventListener('change', syncPicker);
        });
        openBtn.addEventListener('click', open);
        okBtn.addEventListener('click', confirmDelete);
        [$('ch-close'), $('ch-cancel')].forEach(function (el) {
            if (el) el.addEventListener('click', function () { if (!busy) closeModal(); });
        });
        pw.addEventListener('input', clearError);
        pw.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); okBtn.click(); }
        });
        toggle.addEventListener('click', function () {
            var hidden = pw.type === 'password';
            pw.type = hidden ? 'text' : 'password';
            eye.textContent = hidden ? 'visibility_off' : 'visibility';
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen() && !busy) closeModal();
        });

        // However the dialog gets closed (buttons, Esc, backdrop click) nothing stays in the field.
        new MutationObserver(function () {
            if (!isOpen()) { loadSeq++; pw.value = ''; pw.type = 'password'; eye.textContent = 'visibility'; }
        }).observe(modal, { attributes: true, attributeFilter: ['class'] });

        syncPicker();

        // After the reload: confirm what happened (the Settings sub-tab is restored by the page itself).
        try {
            var flash = sessionStorage.getItem(FLASH_KEY);
            if (flash) {
                sessionStorage.removeItem(FLASH_KEY);
                result.textContent = flash;
                result.classList.remove('hidden');
            }
        } catch (_) { }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
