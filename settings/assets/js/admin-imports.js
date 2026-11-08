/* ================================================================
   admin-imports.js
   Settings -> Imports (Super Admin only).

   - The tab is locked (blurred, inert) until the Super Admin enters their
     password. The unlock lives in the SERVER session (imports.php); this file
     only mirrors it. Every request is also checked on the server, so hiding the
     UI is never the only protection.
   - While locked the page holds placeholders only. Counts, lists and files are
     fetched after unlock and dropped again when the tab locks.
   - Equipment Inventory and Rooms Assigned: placeholder cards. They are clickable (open the file
     chooser, show the picked file's name and size) but never read, upload or import anything
     (see initPlaceholderCard).
     Faculty Registry: Faculty Identification Records, PDF (PDF.js) or XLSX
     (read by parse-faculty-registry-xlsx.php).
   - Every import first stores the ORIGINAL file, then the rows read from it. Only the
     file of the latest import is kept (a new import replaces it).

   No inline handlers / styles (CSP). The PDF parser is exposed on
   window.PUPSyncImports so it can be tested without a browser.
================================================================ */
(function () {
    'use strict';

    var API = 'settings/api/imports.php';
    var MAX_FILE_BYTES = 10 * 1024 * 1024;
    var PREVIEW_LIMIT = 200;       // rows drawn in a preview
    var LIST_LIMIT = 200;          // rows drawn in the list modal (search narrows the rest)
    var KEEPALIVE_MS = 30000;      // at most one "still here" ping per 30 s of activity

    var KINDS = {
        equipment: {
            pending: true,   // placeholder: interactive, but no endpoints and no file reading
            label: 'Equipment Inventory', icon: 'inventory_2', exts: ['xlsx', 'csv'], noun: ['item', 'items'],
            cols: ['#', 'Item name', 'Category', 'Quantity', 'Condition'],
            search: 'Search by item name or category\u2026'
        },
        rooms: {
            pending: true,   // placeholder: interactive, but no endpoints and no file reading
            label: 'Rooms Assigned', icon: 'meeting_room', exts: ['xlsx', 'csv'], noun: ['assignment', 'assignments'],
            cols: ['#', 'Room', 'Building', 'Floor', 'Assigned to'],
            search: 'Search by room or faculty\u2026'
        },
        faculty: {
            label: 'Faculty Registry', icon: 'badge', exts: ['pdf', 'xlsx'], noun: ['entry', 'entries'],
            fileEp: 'account-management/api/upload-faculty-registry-file.php',
            rowsEp: 'account-management/api/upload-faculty-registry.php',
            xlsxEp: 'account-management/api/parse-faculty-registry-xlsx.php',
            cols: ['#', 'Faculty ID', 'Name', 'Programs'],
            search: 'Search by name, ID or program…'
        }
    };

    /* ================================================================
       PARSERS (pure)  -  pages: array of pages, each an array of
       { x, y, text } text items as PDF.js returns them.
    ================================================================ */

    /* ── Faculty Registry: Faculty Identification Records ───────────
       Col 0 Faculty ID (or "NEWLY HIRED"), col 1 Professor Name, rest = Programs.
       Same row rules as the original Faculty tab importer.            */
    function parseFacultyPages(pages) {
        var allRows = [];
        pages.forEach(function (items) {
            var lineMap = {};
            items.forEach(function (item) {
                if (!item.text || !item.text.trim()) return;
                var y = Math.round(item.y);
                if (!lineMap[y]) lineMap[y] = [];
                lineMap[y].push({ x: item.x, text: item.text.trim() });
            });
            Object.keys(lineMap).map(Number).sort(function (a, b) { return b - a; }).forEach(function (y) {
                var cells = lineMap[y].sort(function (a, b) { return a.x - b.x; });
                var col0 = (cells[0] && cells[0].text) || '';
                var col1 = (cells[1] && cells[1].text) || '';
                var col2 = cells.slice(2).map(function (c) { return c.text; }).join(' ');

                var c0up = col0.toUpperCase();
                if (!col0 || c0up === 'FACULTY ID' || c0up === 'PROFESSOR NAME') return;

                var isFacultyId = /^FA\d+[A-Z]{2}\d+$/i.test(col0.replace(/\s/g, ''));
                var isNewlyHired = c0up.replace(/\s+/g, ' ') === 'NEWLY HIRED';
                if (!isFacultyId && !isNewlyHired) return;
                if (!col1) return;

                allRows.push({
                    faculty_id: isNewlyHired ? 'NEWLY HIRED' : col0.replace(/\s/g, '').toUpperCase(),
                    full_name: col1,
                    department: col2
                });
            });
        });
        return { rows: allRows, unreadable: 0, duplicates: 0 };
    }

    window.PUPSyncImports = { parseFacultyPages: parseFacultyPages };

    /* ================================================================
       UI
    ================================================================ */
    function $(id) { return document.getElementById(id); }
    function csrf() { var el = document.querySelector('#imp-content input[name="csrf_token"]'); return el ? el.value : ''; }
    function num(n) { return Number(n || 0).toLocaleString(); }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

    function fmtSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1024 * 1024) return (b / 1024).toFixed(b < 10240 ? 1 : 0) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    function fmtDate(s) {                          // "2026-10-28 15:14:00" -> "Oct 28, 2026 · 3:14 PM"
        var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || '');
        if (!m) return '';
        var h = +m[4], ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return MONTHS[+m[2] - 1] + ' ' + (+m[3]) + ', ' + m[1] + ' \u00b7 ' + h + ':' + m[5] + ' ' + ap;
    }
    function fmtClock(sec) { sec = Math.max(0, sec); return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2); }

    function openModal(id) { var m = $(id); if (m) m.classList.add('ps-modal-open'); }
    function closeModal(id) { var m = $(id); if (m) m.classList.remove('ps-modal-open'); }

    /* ── Server calls ───────────────────────────────────────────── */
    function post(action, data) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrf());
        Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
        return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body });
    }
    function postJson(action, data) {
        return post(action, data).then(function (r) { return r.json().catch(function () { return null; }); });
    }

    /* ── PDF.js (self-hosted; reused if another script already loaded it) ── */
    var pdfReady = null;
    function loadPdfJs() {
        if (pdfReady) return pdfReady;
        pdfReady = new Promise(function (resolve, reject) {
            function ready() {
                var lib = window.pdfjsLib || window._pdfJsLib;
                if (!lib) { reject(new Error('The PDF reader could not be loaded.')); return; }
                if (!lib.GlobalWorkerOptions.workerSrc) {
                    var base = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/');
                    lib.GlobalWorkerOptions.workerSrc = base + 'assets/js/vendor/pdf.worker.min.js';
                }
                resolve(lib);
            }
            if (window.pdfjsLib || window._pdfJsLib) { ready(); return; }
            var s = document.createElement('script');
            s.src = 'assets/js/vendor/pdf.min.js';
            s.onload = ready;
            s.onerror = function () { reject(new Error('The PDF reader could not be loaded. Check your connection and try again.')); };
            document.head.appendChild(s);
        });
        pdfReady.catch(function () { pdfReady = null; });
        return pdfReady;
    }
    function readPdfPages(file) {
        return loadPdfJs().then(function (lib) {
            return new Promise(function (resolve, reject) {
                var fr = new FileReader();
                fr.onload = function () { resolve(new Uint8Array(fr.result)); };
                fr.onerror = function () { reject(new Error('Could not read the file.')); };
                fr.readAsArrayBuffer(file);
            }).then(function (data) {
                return lib.getDocument({ data: data }).promise;
            }).then(function (pdf) {
                var jobs = [];
                for (var p = 1; p <= pdf.numPages; p++) {
                    jobs.push(pdf.getPage(p).then(function (page) { return page.getTextContent(); }).then(function (tc) {
                        return tc.items.map(function (it) { return { x: it.transform[4], y: it.transform[5], text: it.str }; });
                    }));
                }
                return Promise.all(jobs);
            });
        });
    }

    function init() {
        var shell = $('imp-shell');
        if (!shell) return;                       // not a Super Admin

        var content = $('imp-content');
        var lockBtn = $('imp-lock-btn');
        var lockNow = $('imp-lock-now');
        var sessionText = $('imp-session-text');
        var lockMsg = $('imp-lock-msg');
        var cards = Array.prototype.slice.call(shell.querySelectorAll('.imp-card'));

        var unlocked = false;
        var expiresAt = 0;
        var clock = null;
        var lastPing = 0;

        /* ── Lock state ───────────────────────────────────────────── */
        function setExpiry(remaining) { if (typeof remaining === 'number') expiresAt = Date.now() + remaining * 1000; }

        function tick() {
            var left = Math.round((expiresAt - Date.now()) / 1000);
            if (left <= 0) { relock('Locked after 10 minutes without use.'); post('lock').catch(function () { }); return; }
            sessionText.textContent = 'Unlocked \u00b7 auto-locks in ' + fmtClock(left);
        }

        function setLocked(locked, message) {
            unlocked = !locked;
            shell.classList.toggle('is-locked', locked);
            shell.classList.toggle('is-unlocked', !locked);
            content.toggleAttribute('inert', locked);
            content.setAttribute('aria-hidden', locked ? 'true' : 'false');
            if (lockMsg) {
                lockMsg.textContent = message || '';
                lockMsg.classList.toggle('hidden', !(locked && message));
            }
            if (locked) {
                if (clock) { clearInterval(clock); clock = null; }
                resetAll();
                closeModal('imp-list-modal'); closeModal('imp-files-modal');
                listRows = []; fileRows = [];
                $('imp-list-tbody').textContent = ''; $('imp-files-list').textContent = '';
            } else {
                tick();
                if (!clock) clock = setInterval(tick, 1000);
            }
        }
        function relock(message) { if (unlocked || message) setLocked(true, message); }

        // Any gated call that comes back "locked" means the server session is no longer unlocked
        function guard(d) {
            if (d && d.code === 'locked') { relock('Imports were locked. Unlock them again to continue.'); return true; }
            return false;
        }
        function sync(d) { if (d && typeof d.remaining === 'number') setExpiry(d.remaining); }

        // Keep an open unlock alive while the tab is actually being used
        function keepAlive() {
            if (!unlocked || Date.now() - lastPing < KEEPALIVE_MS) return;
            lastPing = Date.now();
            postJson('status', { touch: 1 }).then(function (d) {
                if (!d) return;
                if (d.unlocked === false) relock('Imports were locked. Unlock them again to continue.');
                else sync(d);
            });
        }
        document.addEventListener('pointerdown', keepAlive, true);
        document.addEventListener('keydown', keepAlive, true);

        lockNow.addEventListener('click', function () {
            post('lock').catch(function () { }).then(function () { setLocked(true, ''); });
        });

        /* ── Password step ────────────────────────────────────────── */
        var pwInput = $('imp-auth-password');
        var pwError = $('imp-auth-error');
        var pwOk = $('imp-auth-confirm');
        var pwOkHtml = pwOk.innerHTML;

        function showPwError(msg) { pwError.textContent = msg; pwError.classList.remove('hidden'); }
        function openAuth() {
            pwInput.value = '';
            pwInput.type = 'password';
            pwError.classList.add('hidden');
            pwOk.disabled = false; pwOk.innerHTML = pwOkHtml;
            openModal('imp-auth-modal');
            setTimeout(function () { pwInput.focus(); }, 60);
        }
        lockBtn.addEventListener('click', openAuth);
        $('imp-auth-close').addEventListener('click', function () { closeModal('imp-auth-modal'); });
        $('imp-auth-cancel').addEventListener('click', function () { closeModal('imp-auth-modal'); });
        pwInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); pwOk.click(); } });

        pwOk.addEventListener('click', function () {
            var pw = pwInput.value;
            if (!pw) { showPwError('Enter your password to continue.'); return; }
            pwError.classList.add('hidden');
            pwOk.disabled = true;
            pwOk.innerHTML = '<span class="material-symbols-outlined imp-spin">progress_activity</span> Checking\u2026';
            postJson('unlock', { current_password: pw }).then(function (d) {
                pwInput.value = '';
                if (d && d.status === 'success') {
                    closeModal('imp-auth-modal');
                    setExpiry(d.remaining);
                    setLocked(false);
                    loadOverview();
                    return;
                }
                showPwError((d && d.error) || 'Could not unlock. Please try again.');
                pwOk.disabled = false; pwOk.innerHTML = pwOkHtml;
                pwInput.focus();
            }).catch(function () {
                showPwError('Network error. Please try again.');
                pwOk.disabled = false; pwOk.innerHTML = pwOkHtml;
            });
        });

        /* ── Overview (numbers on the cards) ──────────────────────── */
        function cardOf(kind) { return shell.querySelector('.imp-card[data-kind="' + kind + '"]'); }
        function role(card, r) { return card.querySelector('[data-role="' + r + '"]'); }

        function paintOverview(kind, o) {
            var card = cardOf(kind), cfg = KINDS[kind];
            role(card, 'stat-count').textContent = num(o.count);
            role(card, 'stat-extra').textContent = num(o.extra);
            role(card, 'stat-rows').textContent = num(o.rows);
            var chip = role(card, 'chip');
            if (o.count > 0) {
                chip.textContent = num(o.count) + ' ' + cfg.noun[o.count === 1 ? 0 : 1];
                chip.className = 'imp-chip imp-chip--ok';
            } else {
                chip.textContent = 'Not imported yet';
                chip.className = 'imp-chip imp-chip--empty';
            }
            var parts = [];
            if (o.count > 0 && o.last_at) {
                parts.push('Last import ' + fmtDate(o.last_at));
                if (o.last_by) parts.push(o.last_by);
                if (o.last_file) parts.push(o.last_file);
            }
            role(card, 'last-text').textContent = parts.length ? parts.join(' \u00b7 ') : 'Nothing imported yet';
            role(card, 'view-list').disabled = o.count === 0;
            role(card, 'view-files').disabled = o.files === 0;
        }
        function loadOverview() {
            return postJson('overview').then(function (d) {
                if (guard(d)) return;
                if (!d || d.status !== 'success') return;
                sync(d);
                paintOverview('faculty', d.faculty);
            });
        }

        /* ── Import cards ─────────────────────────────────────────── */
        var controllers = {};
        cards.forEach(function (card) {
            var k = card.dataset.kind;
            /* placeholder cards (Equipment Inventory) are interactive but do no real work */
            controllers[k] = (KINDS[k] && KINDS[k].pending) ? initPlaceholderCard(card) : initCard(card);
        });
        initGuide();
        function resetAll() {
            Object.keys(controllers).forEach(function (k) { controllers[k].reset(true); });
        }

        function initCard(card) {
            var kind = card.dataset.kind, cfg = KINDS[kind];
            var drop = role(card, 'drop'), input = role(card, 'input');
            var picked = role(card, 'picked'), alertEl = role(card, 'alert');
            var preview = role(card, 'preview'), previewBody = role(card, 'preview-body'), previewCount = role(card, 'preview-count');
            var importBtn = role(card, 'import');
            var importHtml = importBtn.innerHTML;
            var rows = [], file = null, busy = false, schedule = null;

            function alertShow(msg, kind2, details) {
                alertEl.className = 'imp-alert imp-alert--' + kind2;
                alertEl.textContent = '';
                alertEl.appendChild(el('span', 'material-symbols-outlined', kind2 === 'ok' ? 'check_circle' : (kind2 === 'err' ? 'error' : 'info')));
                var box = el('div', 'imp-alert-text');
                box.appendChild(el('span', null, msg));
                if (details && details.length) {
                    var ul = el('ul', 'imp-alert-list');
                    details.forEach(function (d) { ul.appendChild(el('li', null, d)); });
                    box.appendChild(ul);
                }
                alertEl.appendChild(box);
            }
            function alertHide() { alertEl.className = 'imp-alert hidden'; alertEl.textContent = ''; }

            function reset(full) {
                rows = []; file = null; schedule = null; input.value = '';
                picked.classList.add('hidden');
                preview.classList.add('hidden'); previewBody.textContent = '';
                drop.classList.remove('hidden', 'is-over');
                importBtn.disabled = true; importBtn.innerHTML = importHtml; busy = false;
                alertHide();
                if (full) {
                    ['stat-count', 'stat-extra', 'stat-rows'].forEach(function (r) { role(card, r).textContent = r === 'stat-count' ? '\u2022\u2022\u2022' : '\u2022\u2022'; });
                    role(card, 'last-text').textContent = 'Last import \u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
                    var chip = role(card, 'chip'); chip.textContent = '\u2022\u2022\u2022'; chip.className = 'imp-chip imp-chip--muted';
                    role(card, 'view-list').disabled = false; role(card, 'view-files').disabled = false;
                    var merge = card.querySelector('input[value="merge"]'); if (merge) merge.checked = true;
                }
            }

            function renderPreview(list) {
                previewBody.textContent = '';
                var newly = 0;
                list.forEach(function (r, i) {
                    if (i >= PREVIEW_LIMIT) return;
                    var tr = document.createElement('tr');
                    tr.appendChild(el('td', 'imp-td-n', String(i + 1)));
                    var isNew = r.faculty_id === '' || String(r.faculty_id).toUpperCase() === 'NEWLY HIRED';
                    var idTd = el('td', 'imp-td-mono');
                    if (isNew) idTd.appendChild(el('span', 'imp-pill imp-pill--gold', 'Newly hired'));
                    else idTd.textContent = r.faculty_id;
                    tr.appendChild(idTd);
                    tr.appendChild(el('td', null, r.full_name));
                    tr.appendChild(el('td', null, r.department));
                    previewBody.appendChild(tr);
                });
                list.forEach(function (r) { if (kind === 'faculty' && (r.faculty_id === '' || String(r.faculty_id).toUpperCase() === 'NEWLY HIRED')) newly++; });
                var txt = num(list.length) + ' rows read' + (newly ? ' \u00b7 ' + newly + ' newly hired' : '');
                if (list.length > PREVIEW_LIMIT) txt += ' \u00b7 showing the first ' + PREVIEW_LIMIT;
                previewCount.textContent = txt;
                preview.classList.remove('hidden');
                importBtn.disabled = list.length === 0;
            }

            function showPicked(f) {
                role(card, 'picked-name').textContent = f.name;
                role(card, 'picked-size').textContent = fmtSize(f.size);
                role(card, 'picked-icon').textContent = /\.xlsx$/i.test(f.name) ? 'table_chart' : 'picture_as_pdf';
                picked.classList.remove('hidden');
                drop.classList.add('hidden');
            }

            /* Class-schedule workbooks (Faculty Rooms / Room Timetables): what was found, what to know, what happened */
            function scheduleSummary(s) {
                if (s.kind === 'faculty_schedule') {
                    return num(s.meetings) + ' weekly class meetings \u00b7 ' + num(s.rooms) + ' rooms \u00b7 ' + num(s.hours) + ' class hours a week';
                }
                return 'Room timetable: ' + num(s.rooms) + ' rooms \u00b7 ' + num(s.unassigned) + ' classes with no instructor yet' + (s.term ? ' \u00b7 ' + s.term : '');
            }
            function scheduleNotes(s) {
                var n = [];
                if (s.kind === 'faculty_schedule') {
                    n.push('This file also carries each faculty member\u2019s weekly classes. They are saved with the faculty list, shown in each faculty member\u2019s class schedule, and block those rooms for reservations.');
                    if (s.no_id) n.push(num(s.no_id) + ' faculty have no ID yet. Their classes are kept and attach as soon as their account is created.');
                } else {
                    n.push('These classes have no instructor yet, but they still block their rooms for reservations.');
                }
                (s.warnings || []).slice(0, 5).forEach(function (w) { n.push(w); });
                return n;
            }
            function scheduleOutcome(s, details) {
                if (s.error) { details.unshift(s.error); return ''; }
                var line;
                if (s.kind === 'faculty_schedule') {
                    line = ' Class schedule: ' + num(s.meetings) + ' weekly meetings saved for ' + num(s.faculty) + ' faculty (' + num(s.accounts_linked) + ' faculty accounts already have their classes).';
                } else {
                    line = ' ' + num(s.unassigned_classes) + ' classes with no instructor yet now block their rooms.';
                }
                var made = s.rooms_created || [];
                if (made.length) details.push(num(made.length) + ' rooms were not in the system yet and were added: ' + made.slice(0, 6).join(', ') + (made.length > 6 ? ', \u2026' : '') + '.');
                if ((s.rooms_unresolved || []).length) details.push('These rooms could not be placed in a building: ' + s.rooms_unresolved.join(', ') + '.');
                var ov = s.reservations_overlapping || [];
                if (ov.length) {
                    var ex = ov.slice(0, 3).map(function (o) { return o.room_name + ' on ' + o.reservation_date + ' ' + String(o.start_time).slice(0, 5) + '\u2013' + String(o.end_time).slice(0, 5); }).join('; ');
                    details.push(num(ov.length) + ' approved reservation(s) now overlap a class (' + ex + (ov.length > 3 ? '; \u2026' : '') + '). They were left as they are. Please review them.');
                }
                (s.file_overlaps || []).slice(0, 3).forEach(function (w) { details.push(w); });
                return line;
            }

            function parseXlsx(f) {
                var form = new FormData();
                form.append('csrf_token', csrf());
                form.append('file', f);
                return fetch(cfg.xlsxEp, { method: 'POST', credentials: 'same-origin', body: form }).then(function (r) {
                    return r.json().catch(function () { throw new Error('Unexpected response from the server. Please try again.'); }).then(function (d) {
                        if (guard(d)) throw new Error('');
                        if (!r.ok || !d || d.status !== 'success' || !Array.isArray(d.rows)) throw new Error((d && d.error) || 'Could not read the XLSX file. Please try again.');
                        return { rows: d.rows, schedule: d.schedule || null };
                    });
                });
            }

            function handleFile(f) {
                if (!f || busy) return;
                reset(false);
                var ext = (f.name.split('.').pop() || '').toLowerCase();
                if (cfg.exts.indexOf(ext) === -1) {
                    alertShow('Unsupported file type. Please choose a ' + cfg.exts.join(' or ').toUpperCase() + ' file.', 'err');
                    return;
                }
                if (f.size > MAX_FILE_BYTES) { alertShow('File is too large. Maximum size is 10 MB.', 'err'); return; }

                file = f;
                showPicked(f);
                alertShow(ext === 'pdf' ? 'Reading the PDF\u2026' : 'Reading the spreadsheet\u2026', 'info');

                var job = ext === 'pdf'
                    ? readPdfPages(f).then(function (pages) {
                        var res = parseFacultyPages(pages);
                        if (!res.rows.length) {
                            throw new Error('No faculty rows were found in this PDF. Make sure it is the Faculty Identification Records document and not a scanned image.');
                        }
                        return res;
                    })
                    : parseXlsx(f).then(function (r) { return { rows: r.rows, schedule: r.schedule, unreadable: 0, duplicates: 0 }; });

                job.then(function (res) {
                    var sched = res.schedule || null;
                    var timetableOnly = !!(sched && sched.kind === 'room_timetable');   // no faculty rows, only rooms + classes
                    if (!res.rows.length && !timetableOnly) throw new Error('No rows were found in this file.');
                    rows = res.rows;
                    schedule = sched;
                    renderPreview(rows);
                    var notes = [];
                    if (sched) {
                        previewCount.textContent = timetableOnly ? scheduleSummary(sched) : previewCount.textContent + ' \u00b7 ' + scheduleSummary(sched);
                        if (timetableOnly) importBtn.disabled = false;
                        scheduleNotes(sched).forEach(function (n) { notes.push(n); });
                    }
                    if (res.unreadable) notes.push(res.unreadable + ' row(s) could not be read and were left out.');
                    if (notes.length) alertShow('Check the preview before importing.', 'info', notes); else alertHide();
                }).catch(function (err) {
                    var keep = !unlocked;                        // locked while reading: reset() already ran
                    if (keep) return;
                    reset(false);
                    if (err && err.message) alertShow(err.message, 'err');
                });
            }

            /* pick / drop */
            input.addEventListener('click', function (e) { e.stopPropagation(); });
            drop.addEventListener('click', function () { if (!busy) input.click(); });
            drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (!busy) input.click(); } });
            input.addEventListener('change', function () { handleFile(this.files && this.files[0]); });
            ['dragenter', 'dragover'].forEach(function (t) {
                drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (t) {
                drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
            });
            drop.addEventListener('drop', function (e) {
                var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                if (f) handleFile(f);
            });
            role(card, 'clear').addEventListener('click', function () { if (!busy) reset(false); });

            /* import: 1) keep the ORIGINAL file, 2) import the rows read from it */
            importBtn.addEventListener('click', function () {
                var timetableOnly = !!(schedule && schedule.kind === 'room_timetable');
                if ((!rows.length && !timetableOnly) || !file || busy) return;
                var mode = card.querySelector('input[name="imp-mode-' + kind + '"]:checked');
                var replaceAll = !!(mode && mode.value === 'replace') && !timetableOnly;
                if (replaceAll && !window.confirm('Replace the whole ' + cfg.label + '? Everyone currently listed will be removed first.')) return;
                var token = csrf();
                if (!token) { alertShow('Session expired. Please refresh the page.', 'err'); return; }

                busy = true;
                importBtn.disabled = true;
                importBtn.innerHTML = '<span class="material-symbols-outlined imp-spin">progress_activity</span> Importing\u2026';
                alertShow('Saving the original file and importing\u2026', 'info');

                var fd = new FormData();
                fd.append('csrf_token', token);
                fd.append('file', file);
                fetch(cfg.fileEp, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json().catch(function () { return null; }); })
                    .then(function (stored) {
                        if (guard(stored)) throw { handled: true };
                        if (!stored || stored.status !== 'success') {
                            throw { message: (stored && stored.error) || 'The original file could not be saved, so nothing was imported.' };
                        }
                        return fetch(cfg.rowsEp, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ csrf_token: token, rows: rows, replace_all: replaceAll, file_id: stored.file_id })
                        });
                    })
                    .then(function (r) { return r.json().catch(function () { return null; }); })
                    .then(function (d) {
                        if (guard(d)) throw { handled: true };
                        if (!d || d.status !== 'success') throw { message: (d && d.error) || 'Import failed. Please try again.', details: d && d.errors };
                        var s = d.schedule || null;
                        var msg = (s && s.kind === 'room_timetable')
                            ? 'Room timetable saved.'
                            : 'Import complete: ' + num(d.inserted) + ' added, ' + num(d.updated) + ' updated' + (d.skipped ? ', ' + num(d.skipped) + ' skipped' : '') + '.';
                        var details = (d.errors || []).slice();
                        if (s) msg += scheduleOutcome(s, details);
                        reset(false);
                        alertShow(msg, s && s.error ? 'info' : 'ok', details);
                        loadOverview();
                    })
                    .catch(function (err) {
                        busy = false;
                        if (err && err.handled) return;
                        importBtn.disabled = false; importBtn.innerHTML = importHtml;
                        alertShow((err && err.message) || 'Network error. Please try again.', 'err', err && err.details);
                    });
            });

            role(card, 'view-list').addEventListener('click', function () { openList(kind); });
            role(card, 'view-files').addEventListener('click', function () { openFiles(kind); });

            return { reset: reset };
        }

        /* Import guide: the tabs, and the "How to prepare this file" link on each card. */
        function initGuide() {
            var guide = $('imp-guide');
            if (!guide) return;
            var tabs = Array.prototype.slice.call(guide.querySelectorAll('[role="tab"]'));
            var panels = Array.prototype.slice.call(guide.querySelectorAll('[role="tabpanel"]'));

            function select(kind, focus) {
                tabs.forEach(function (tab) {
                    var on = tab.dataset.kind === kind;
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                    tab.tabIndex = on ? 0 : -1;
                    if (on && focus) tab.focus();
                });
                panels.forEach(function (panel) { panel.hidden = panel.dataset.kind !== kind; });
            }
            tabs.forEach(function (tab, i) {
                tab.addEventListener('click', function () { select(tab.dataset.kind); });
                tab.addEventListener('keydown', function (e) {
                    var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1
                        : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : -1;
                    if (n < 0) return;
                    e.preventDefault();
                    select(tabs[(n + tabs.length) % tabs.length].dataset.kind, true);
                });
            });
            Array.prototype.forEach.call(document.querySelectorAll('.imp-guide-link'), function (link) {
                link.addEventListener('click', function () {
                    select(link.dataset.guide);
                    guide.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
        }

        /* Equipment Inventory / Rooms Assigned: a clickable placeholder.
           It opens the file chooser and shows the picked file's NAME and SIZE only. The file is never
           read (no FileReader, no parsing), never uploaded, and nothing is imported or saved: every
           button just explains that this import is not available yet. */
        function initPlaceholderCard(card) {
            var drop = role(card, 'drop'), input = role(card, 'input');
            var picked = role(card, 'picked'), alertEl = role(card, 'alert');
            var importBtn = role(card, 'import');
            var what = (KINDS[card.dataset.kind] && KINDS[card.dataset.kind].label) || 'This';

            function note(msg) {
                alertEl.className = 'imp-alert imp-alert--info';
                alertEl.textContent = '';
                alertEl.appendChild(el('span', 'material-symbols-outlined', 'info'));
                var box = el('div', 'imp-alert-text');
                box.appendChild(el('span', null, msg));
                alertEl.appendChild(box);
            }
            function reset() {
                input.value = '';
                picked.classList.add('hidden');
                drop.classList.remove('hidden', 'is-over');
                importBtn.disabled = true;
                alertEl.className = 'imp-alert hidden';
                alertEl.textContent = '';
            }
            function pick(f) {
                if (!f) return;
                role(card, 'picked-name').textContent = f.name;
                role(card, 'picked-size').textContent = fmtSize(f.size);
                role(card, 'picked-icon').textContent = /\.(xlsx|csv)$/i.test(f.name) ? 'table_chart' : 'description';
                picked.classList.remove('hidden');
                drop.classList.add('hidden');
                importBtn.disabled = false;
                note(what + ' import is not available yet. The file was not read or uploaded.');
            }

            input.addEventListener('click', function (e) { e.stopPropagation(); });
            drop.addEventListener('click', function () { input.click(); });
            drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
            input.addEventListener('change', function () { pick(this.files && this.files[0]); });
            ['dragenter', 'dragover'].forEach(function (t) {
                drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (t) {
                drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
            });
            drop.addEventListener('drop', function (e) {
                pick(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]);
            });
            role(card, 'clear').addEventListener('click', reset);
            importBtn.addEventListener('click', function () {
                note(what + ' import is not available yet. Nothing was uploaded or changed.');
            });
            role(card, 'view-list').addEventListener('click', function () {
                note('Nothing to show yet. ' + what + ' import is not available yet.');
            });
            role(card, 'view-files').addEventListener('click', function () {
                note('Nothing to show yet. ' + what + ' import is not available yet.');
            });

            return { reset: reset };
        }

        /* ── Imported list modal ──────────────────────────────────── */
        var listRows = [], listKind = '';
        var listBody = $('imp-list-tbody'), listEmpty = $('imp-list-empty'), listMeta = $('imp-list-meta');
        var listSearch = $('imp-list-search');

        function listCells(kind, r, i) {
            return [String(i + 1), r.is_newly_hired ? 'Newly hired' : r.faculty_id, r.full_name, r.department];
        }
        function drawList() {
            var q = listSearch.value.trim().toLowerCase();
            var shown = 0, matched = 0;
            listBody.textContent = '';
            listRows.forEach(function (r) {
                if (q) {
                    var hay = Object.keys(r).map(function (k) { return String(r[k]); }).join(' ').toLowerCase();
                    if (hay.indexOf(q) === -1) return;
                }
                matched++;
                if (shown >= LIST_LIMIT) return;
                var tr = document.createElement('tr');
                listCells(listKind, r, matched - 1).forEach(function (t, ci) {
                    var td = el('td', ci === 0 ? 'imp-td-n' : (ci === 1 ? 'imp-td-mono' : null));
                    if (listKind === 'faculty' && ci === 1 && r.is_newly_hired) td.appendChild(el('span', 'imp-pill imp-pill--gold', t));
                    else td.textContent = t;
                    tr.appendChild(td);
                });
                listBody.appendChild(tr);
                shown++;
            });
            listEmpty.classList.toggle('hidden', matched > 0);
            listMeta.textContent = matched > shown
                ? 'Showing ' + num(shown) + ' of ' + num(matched) + (q ? ' matches' : '') + ' \u2014 use search to narrow it down'
                : num(matched) + (q ? ' matching' : ' total');
        }
        function openList(kind) {
            var cfg = KINDS[kind];
            listKind = kind; listRows = []; listSearch.value = '';
            $('imp-list-title').textContent = cfg.label;
            $('imp-list-icon').textContent = cfg.icon;
            $('imp-list-count').textContent = '';
            listSearch.placeholder = cfg.search;
            var head = $('imp-list-thead'); head.textContent = '';
            var tr = document.createElement('tr');
            cfg.cols.forEach(function (c) { tr.appendChild(el('th', null, c)); });
            head.appendChild(tr);
            listBody.textContent = '';
            listEmpty.textContent = 'Loading\u2026'; listEmpty.classList.remove('hidden');
            listMeta.textContent = '';
            openModal('imp-list-modal');
            postJson('list', { kind: kind }).then(function (d) {
                if (guard(d)) return;
                if (!d || d.status !== 'success') { listEmpty.textContent = (d && d.error) || 'Could not load the list.'; return; }
                sync(d);
                listRows = d.entries || [];
                $('imp-list-count').textContent = num(d.total);
                listEmpty.textContent = 'No matching entries.';
                drawList();
            }).catch(function () { listEmpty.textContent = 'Network error. Please try again.'; });
        }
        listSearch.addEventListener('input', drawList);
        function closeList() { closeModal('imp-list-modal'); listRows = []; listBody.textContent = ''; }
        $('imp-list-close').addEventListener('click', closeList);
        $('imp-list-done').addEventListener('click', closeList);

        /* ── Original files modal ─────────────────────────────────── */
        var fileRows = [], filesKind = '';
        var filesList = $('imp-files-list'), filesEmpty = $('imp-files-empty'), filesAlert = $('imp-files-alert');

        function filesAlertShow(msg) { filesAlert.textContent = msg; filesAlert.classList.remove('hidden'); }

        function fetchFile(f, disposition) {
            return post('file', { kind: filesKind, id: f.id, disposition: disposition }).then(function (r) {
                var type = r.headers.get('Content-Type') || '';
                if (type.indexOf('application/json') !== -1) {
                    return r.json().then(function (d) { throw d; });
                }
                if (!r.ok) throw { error: 'Could not open the file.' };
                return r.blob();
            });
        }
        function saveBlob(blob, name) {
            var url = URL.createObjectURL(blob), a = document.createElement('a');
            a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
        }
        function fileFailed(d) { if (!guard(d)) filesAlertShow((d && d.error) || 'Could not open the file. Please try again.'); }

        function viewFile(f, btn) {
            filesAlert.classList.add('hidden');
            var w = window.open('', '_blank');           // opened inside the click so it is not blocked
            if (w) { try { w.opener = null; w.document.title = f.name; w.document.body.textContent = 'Opening file\u2026'; } catch (e) { /* ignore */ } }
            btn.disabled = true;
            fetchFile(f, 'inline').then(function (blob) {
                var url = URL.createObjectURL(blob);
                if (w && !w.closed) w.location.href = url; else saveBlob(blob, f.name);
                setTimeout(function () { URL.revokeObjectURL(url); }, 600000);
            }).catch(function (d) { if (w && !w.closed) w.close(); fileFailed(d); })
                .then(function () { btn.disabled = false; });
        }
        function downloadFile(f, btn) {
            filesAlert.classList.add('hidden');
            btn.disabled = true;
            fetchFile(f, 'attachment').then(function (blob) { saveBlob(blob, f.name); })
                .catch(fileFailed).then(function () { btn.disabled = false; });
        }

        function fileButton(label, icon, kind2, disabled, fn) {
            var b = el('button', 'ps-btn ps-btn--sm ' + kind2);
            b.type = 'button'; b.disabled = !!disabled;
            b.appendChild(el('span', 'material-symbols-outlined', icon));
            b.appendChild(document.createTextNode(' ' + label));
            b.addEventListener('click', function () { fn(b); });
            return b;
        }
        function drawFiles() {
            filesList.textContent = '';
            fileRows.forEach(function (f) {
                var li = el('li', 'imp-file-item');
                var isPdf = f.mime === 'application/pdf';
                var ic = el('div', 'imp-file-icon'); ic.appendChild(el('span', 'material-symbols-outlined', isPdf ? 'picture_as_pdf' : 'table_chart'));
                var info = el('div', 'imp-file-info');
                var top = el('div', 'imp-file-top');
                top.appendChild(el('strong', 'imp-file-name', f.name));
                top.appendChild(el('span', 'imp-pill ' + (f.status === 'imported' ? 'imp-pill--ok' : 'imp-pill--warn'), f.status === 'imported' ? 'Imported' : 'Not imported'));
                info.appendChild(top);
                var meta = [fmtSize(f.size), f.uploaded_by, fmtDate(f.uploaded_at)].filter(Boolean).join(' \u00b7 ');
                info.appendChild(el('div', 'imp-file-meta', meta));
                if (f.status === 'imported' && f.rows !== null) {
                    var res = num(f.rows) + ' rows \u00b7 ' + num(f.inserted) + ' added \u00b7 ' + num(f.updated) + ' updated' + (f.skipped ? ' \u00b7 ' + num(f.skipped) + ' skipped' : '') + ' \u00b7 ' + (f.import_mode === 'replace' ? 'replaced the list' : 'merged');
                    info.appendChild(el('div', 'imp-file-meta imp-file-meta--res', res));
                }
                if (!f.available) info.appendChild(el('div', 'imp-file-meta imp-file-meta--bad', 'The stored copy is missing from the server.'));
                var act = el('div', 'imp-file-actions');
                if (isPdf) act.appendChild(fileButton('View', 'visibility', 'ps-btn--outline', !f.available, function (b) { viewFile(f, b); }));
                act.appendChild(fileButton('Download', 'download', 'ps-btn--ghost', !f.available, function (b) { downloadFile(f, b); }));
                li.appendChild(ic); li.appendChild(info); li.appendChild(act);
                filesList.appendChild(li);
            });
            filesEmpty.classList.toggle('hidden', fileRows.length > 0);
        }
        function openFiles(kind) {
            filesKind = kind; fileRows = [];
            $('imp-files-title').textContent = KINDS[kind].label + ' \u2014 original file';
            $('imp-files-total').textContent = '';
            filesAlert.classList.add('hidden'); filesList.textContent = '';
            filesEmpty.classList.add('hidden'); $('imp-files-meta').textContent = '';
            openModal('imp-files-modal');
            postJson('files', { kind: kind }).then(function (d) {
                if (guard(d)) return;
                if (!d || d.status !== 'success') { filesAlertShow((d && d.error) || 'Could not load the files.'); return; }
                sync(d);
                fileRows = d.files || [];
                $('imp-files-total').textContent = '';
                $('imp-files-meta').textContent = d.total ? 'Latest import only' : '';
                drawFiles();
            }).catch(function () { filesAlertShow('Network error. Please try again.'); });
        }
        function closeFiles() { closeModal('imp-files-modal'); fileRows = []; filesList.textContent = ''; }
        $('imp-files-close').addEventListener('click', closeFiles);
        $('imp-files-done').addEventListener('click', closeFiles);

        /* Esc closes the top-most Imports dialog */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            ['imp-auth-modal', 'imp-files-modal', 'imp-list-modal'].some(function (id) {
                var m = $(id);
                if (m && m.classList.contains('ps-modal-open')) {
                    if (id === 'imp-list-modal') closeList(); else if (id === 'imp-files-modal') closeFiles(); else closeModal(id);
                    return true;
                }
                return false;
            });
        });

        function restoreUnlock() {
            postJson('status', { touch: 1 }).then(function (d) {
                if (!d || d.status !== 'success') return;
                if (!d.unlocked) {
                    if (unlocked) relock('Imports were locked. Unlock them again to continue.');
                    return;
                }
                setExpiry(d.remaining);
                if (!unlocked) {
                    setLocked(false);
                    loadOverview();
                }
            }).catch(function () { /* keep the current state if the status check fails */ });
        }

        /* Re-check the server session on reload and whenever Imports is revisited. */
        restoreUnlock();
        var settingsTabs = $('settMainTabs');
        if (settingsTabs) {
            settingsTabs.addEventListener('click', function (e) {
                if (e.target.closest('[data-sett-panel="sett-imports"]')) restoreUnlock();
            });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();