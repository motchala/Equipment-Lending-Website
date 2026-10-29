/* ================================================================
   admin-student-registry.js
   Settings -> Student List (Super Admin only).

   Reads the official AIMS "Class List" PDFs in the browser (PDF.js, self-hosted),
   shows a preview, then sends the rows to
   equipment-booking/api/upload-student-registry.php.

   One PDF may hold several class lists (a merged file); several PDFs can also be
   picked at once. Each list's Section / School Year / Semester heading is carried
   onto its students.

   No inline handlers, no inline styles (CSP). The parser is exposed as
   window.PUPSyncStudentList.parsePages so it can be tested without a browser.
================================================================ */
(function () {
    'use strict';

    var STUDENT_NO_RE = /^\d{4}-\d{5}-[A-Z]{2}-\d$/;
    var YEAR_LEVEL_RE = /^(.+)\s+(\S+)\s+((?:First|Second|Third|Fourth|Fifth|Sixth)\s+Year)$/i;
    var LINE_TOLERANCE = 3;          // PDF points: items this close in y belong to one line
    var PREVIEW_LIMIT = 200;         // rows drawn in the preview table
    var MAX_FILE_BYTES = 10 * 1024 * 1024;

    /* ── Parser (pure) ─────────────────────────────────────────────
       pages: array of pages, each an array of { x, y, text } text items.
       Returns { rows, unreadable, duplicates }.                       */
    function clusterLines(items) {
        var sorted = items.filter(function (it) { return it.text && it.text.trim(); })
            .sort(function (a, b) { return (b.y - a.y) || (a.x - b.x); });
        var lines = [];
        sorted.forEach(function (it) {
            var last = lines[lines.length - 1];
            if (last && Math.abs(last.y - it.y) <= LINE_TOLERANCE) last.cells.push(it);
            else lines.push({ y: it.y, cells: [it] });
        });
        lines.forEach(function (l) { l.cells.sort(function (a, b) { return a.x - b.x; }); });
        return lines;
    }

    function lineText(line) {
        return line.cells.map(function (c) { return c.text.trim(); }).join(' ').replace(/\s+/g, ' ').trim();
    }

    // "2627" -> "2026-2027"
    function formatSchoolYear(raw) {
        var m = /^(\d{2})(\d{2})$/.exec(raw);
        return m ? '20' + m[1] + '-20' + m[2] : raw;
    }

    // "BSIT-BN - BACHELOR OF SCIENCE IN ..." -> "BSIT-BN";  "BSIT-BN 3-1" stays as is
    function formatSection(raw) {
        return raw.split(/\s+-\s+/)[0].trim();
    }

    function parsePages(pages) {
        var rows = [];
        var byNo = {};
        var unreadable = 0;
        var duplicates = 0;
        var section = '', schoolYear = '', semester = '';

        pages.forEach(function (items) {
            clusterLines(items).forEach(function (line) {
                var text = lineText(line);
                var m;

                if ((m = /^Section\s+(.+)$/i.exec(text))) { section = formatSection(m[1]); return; }
                if (/^School Year\b/i.test(text)) {
                    if ((m = /School Year\s+(\d{4})/i.exec(text))) schoolYear = formatSchoolYear(m[1]);
                    if ((m = /Semester\s+([A-Za-z]+)/i.exec(text))) semester = m[1];
                    return;
                }

                // A student row: find the Student No. cell, everything after it is Name / Course / Year Level
                var idx = -1;
                for (var i = 0; i < line.cells.length; i++) {
                    if (STUDENT_NO_RE.test(line.cells[i].text.replace(/\s/g, '').toUpperCase())) { idx = i; break; }
                }
                if (idx === -1) return;

                var no = line.cells[idx].text.replace(/\s/g, '').toUpperCase();
                var rest = line.cells.slice(idx + 1).map(function (c) { return c.text.trim(); }).join(' ').replace(/\s+/g, ' ').trim();
                var name = '', course = '', year = '';

                if ((m = YEAR_LEVEL_RE.exec(rest))) {
                    name = m[1].trim(); course = m[2].trim(); year = m[3].trim();
                } else if (line.cells.length >= idx + 3) {
                    name = line.cells[idx + 1].text.trim();      // fallback: cell order
                    course = line.cells[idx + 2].text.trim();
                    year = line.cells.slice(idx + 3).map(function (c) { return c.text.trim(); }).join(' ');
                }
                if (!name || name.indexOf(',') === -1) { unreadable++; return; }

                var row = {
                    student_no: no, full_name: name, section: section, course: course,
                    year_level: year, school_year: schoolYear, semester: semester
                };
                if (byNo[no] !== undefined) { rows[byNo[no]] = row; duplicates++; }
                else { byNo[no] = rows.length; rows.push(row); }
            });
        });

        return { rows: rows, unreadable: unreadable, duplicates: duplicates };
    }

    window.PUPSyncStudentList = { parsePages: parsePages };

    /* ── UI ───────────────────────────────────────────────────── */
    function $(id) { return document.getElementById(id); }

    function init() {
        var card = $('stu-reg-card');
        if (!card) return;                         // not a Super Admin, or Settings not rendered

        var fileInput = $('stu-reg-file-input');
        var fileNameEl = $('stu-reg-file-name');
        var importBtn = $('stu-reg-import-btn');
        var replaceChk = $('stu-reg-replace-all');
        var alertEl = $('stu-reg-alert');
        var previewWrap = $('stu-reg-preview');
        var previewCount = $('stu-reg-preview-count');
        var previewBody = $('stu-reg-preview-tbody');
        var clearBtn = $('stu-reg-clear-btn');
        var badge = $('stu-reg-badge');
        var statusEl = $('stu-reg-status');
        var enforceEl = $('stu-reg-enforce');
        var tokenEl = card.querySelector('input[name="csrf_token"]');

        var CHOOSE_TEXT = 'Choose class list PDF(s)\u2026';
        var IMPORT_HTML = '<span class="material-symbols-outlined">cloud_upload</span> Import';
        var parsedRows = [];

        function showAlert(msg, kind, details) {
            alertEl.className = 'alert-banner alert-' + kind;
            alertEl.textContent = msg;
            if (details && details.length) {
                var ul = document.createElement('ul');
                ul.className = 'stu-reg-alert-list';
                details.forEach(function (d) { var li = document.createElement('li'); li.textContent = d; ul.appendChild(li); });
                alertEl.appendChild(ul);
            }
            alertEl.classList.remove('hidden');
        }
        function clearAlert() { alertEl.classList.add('hidden'); alertEl.textContent = ''; }

        function reset() {
            parsedRows = [];
            fileInput.value = '';
            fileNameEl.textContent = CHOOSE_TEXT;
            previewWrap.classList.add('hidden');
            previewBody.textContent = '';
            importBtn.disabled = true;
            clearAlert();
        }
        clearBtn.addEventListener('click', reset);

        function td(text) { var c = document.createElement('td'); c.textContent = text; return c; }

        function renderPreview(rows) {
            previewBody.textContent = '';
            var sections = {};
            rows.forEach(function (r, i) {
                sections[r.section || '(no section)'] = true;
                if (i >= PREVIEW_LIMIT) return;
                var tr = document.createElement('tr');
                [String(i + 1), r.student_no, r.full_name, r.section, r.year_level].forEach(function (t) { tr.appendChild(td(t)); });
                previewBody.appendChild(tr);
            });
            var nSec = Object.keys(sections).length;
            var txt = rows.length + ' students \u00b7 ' + nSec + ' section' + (nSec === 1 ? '' : 's');
            if (rows.length > PREVIEW_LIMIT) txt += ' (showing the first ' + PREVIEW_LIMIT + ')';
            previewCount.textContent = txt;
            previewWrap.classList.remove('hidden');
            importBtn.disabled = rows.length === 0;
        }

        /* PDF.js — self-hosted; reuse it if the Faculty Registry already loaded it */
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

        function readFile(file) {
            return new Promise(function (resolve, reject) {
                var fr = new FileReader();
                fr.onload = function () { resolve(new Uint8Array(fr.result)); };
                fr.onerror = function () { reject(new Error('Could not read "' + file.name + '".')); };
                fr.readAsArrayBuffer(file);
            });
        }

        function pagesOf(lib, file) {
            return readFile(file).then(function (data) {
                return lib.getDocument({ data: data }).promise;
            }).then(function (pdf) {
                var jobs = [];
                for (var p = 1; p <= pdf.numPages; p++) {
                    jobs.push(pdf.getPage(p).then(function (page) {
                        return page.getTextContent();
                    }).then(function (tc) {
                        return tc.items.map(function (it) { return { x: it.transform[4], y: it.transform[5], text: it.str }; });
                    }));
                }
                return Promise.all(jobs);
            });
        }

        fileInput.addEventListener('change', function () {
            var files = Array.prototype.slice.call(this.files || []);
            if (!files.length) return;
            parsedRows = [];
            previewWrap.classList.add('hidden');
            importBtn.disabled = true;
            fileNameEl.textContent = files.length === 1 ? files[0].name : files.length + ' files selected';

            for (var i = 0; i < files.length; i++) {
                if (!/\.pdf$/i.test(files[i].name)) { showAlert('"' + files[i].name + '" is not a PDF. Choose the class list PDF(s) exported from AIMS.', 'danger'); fileInput.value = ''; fileNameEl.textContent = CHOOSE_TEXT; return; }
                if (files[i].size > MAX_FILE_BYTES) { showAlert('"' + files[i].name + '" is too large. Maximum size is 10 MB per file.', 'danger'); fileInput.value = ''; fileNameEl.textContent = CHOOSE_TEXT; return; }
            }

            showAlert('Reading the class list\u2026', 'info');
            loadPdfJs().then(function (lib) {
                var all = [];
                return files.reduce(function (chain, f) {
                    return chain.then(function () {
                        return pagesOf(lib, f).then(function (pages) { all = all.concat(pages); });
                    });
                }, Promise.resolve()).then(function () { return all; });
            }).then(function (pages) {
                var res = parsePages(pages);
                if (!res.rows.length) {
                    showAlert('No students were found. Make sure this is a class list PDF exported from AIMS (not a scanned image).', 'danger');
                    fileInput.value = ''; fileNameEl.textContent = CHOOSE_TEXT;
                    return;
                }
                parsedRows = res.rows;
                renderPreview(res.rows);
                var notes = [];
                if (res.unreadable) notes.push(res.unreadable + ' row(s) could not be read and were left out.');
                if (res.duplicates) notes.push(res.duplicates + ' repeated Student No. found; the later entry was kept.');
                if (notes.length) showAlert('Check the preview before importing.', 'info', notes); else clearAlert();
            }).catch(function (err) {
                showAlert((err && err.message) || 'Could not read the PDF.', 'danger');
                fileInput.value = ''; fileNameEl.textContent = CHOOSE_TEXT;
            });
        });

        importBtn.addEventListener('click', function () {
            if (!parsedRows.length) { showAlert('Choose a class list first.', 'danger'); return; }
            var token = tokenEl ? tokenEl.value : '';
            if (!token) { showAlert('Session expired. Please refresh the page.', 'danger'); return; }
            if (replaceChk.checked && !window.confirm('Replace the whole student list? Every student currently listed will be removed first.')) return;

            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="material-symbols-outlined stu-reg-spin">progress_activity</span> Importing\u2026';

            fetch('equipment-booking/api/upload-student-registry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: token, rows: parsedRows, replace_all: replaceChk.checked })
            }).then(function (r) {
                return r.json().catch(function () { return null; });
            }).then(function (data) {
                if (!data || data.status !== 'success') {
                    showAlert((data && data.error) || 'Import failed. Please try again.', 'danger', data && data.errors);
                    importBtn.disabled = false;
                    return;
                }
                var msg = 'Import complete: ' + data.inserted + ' added, ' + data.updated + ' updated' +
                    (data.skipped ? ', ' + data.skipped + ' skipped' : '') + '.';
                showAlert(msg, 'success', data.errors);

                badge.textContent = data.total_after + ' student' + (data.total_after === 1 ? '' : 's');
                badge.className = 'fac-reg-badge fac-reg-badge--active';
                statusEl.textContent = data.total_after + ' students across ' + data.sections_after + ' section' +
                    (data.sections_after === 1 ? '' : 's') + (data.last_label ? ' \u00b7 last imported ' + data.last_label : '') +
                    (data.last_by ? ' by ' + data.last_by : '');
                enforceEl.textContent = 'Active \u2014 students must match this list to continue.';
                enforceEl.className = 'stu-reg-enforce stu-reg-enforce--on';

                parsedRows = [];
                fileInput.value = '';
                fileNameEl.textContent = CHOOSE_TEXT;
                replaceChk.checked = false;
                previewWrap.classList.add('hidden');
                previewBody.textContent = '';
            }).catch(function () {
                showAlert('Network error. Please try again.', 'danger');
                importBtn.disabled = false;
            }).then(function () {
                importBtn.innerHTML = IMPORT_HTML;
                if (!parsedRows.length) importBtn.disabled = true;
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
