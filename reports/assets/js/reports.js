/**
 * reports/assets/js/reports.js
 *
 * Handles the Reports tab form:
 *   - format toggle (PDF / Excel pill buttons)
 *   - form submit → fetch → blob download
 *   - loading state on the submit button
 *   - error display
 */

(function () {
    'use strict';

    function init() {
        const form      = document.getElementById('rptForm');
        const submitBtn = document.getElementById('rptSubmitBtn');
        const label     = submitBtn && submitBtn.querySelector('.rpt-btn-label');
        const errorEl   = document.getElementById('rptError');
        const formatIn  = document.getElementById('rptFormat');
        const fmtBtns   = document.querySelectorAll('.rpt-fmt-btn');
        const clearBtn  = document.getElementById('rptClearDates');

        if (!form || !submitBtn) return;

        // ── Select All ────────────────────────────────────────────────────
        var selectAllCb   = document.getElementById('rptSelectAll');
        var selectAllLbl  = document.getElementById('rptSelectAllLabel');
        var cardCheckboxes = form.querySelectorAll('input[name="report_type[]"]');

        function updateSelectAllState() {
            var total   = cardCheckboxes.length;
            var checked = form.querySelectorAll('input[name="report_type[]"]:checked').length;
            if (selectAllCb) {
                selectAllCb.checked       = checked === total;
                selectAllCb.indeterminate = checked > 0 && checked < total;
            }
            if (selectAllLbl) {
                selectAllLbl.classList.toggle('is-indeterminate', checked > 0 && checked < total);
            }
        }

        if (selectAllCb) {
            selectAllCb.addEventListener('change', function () {
                cardCheckboxes.forEach(function (cb) { cb.checked = selectAllCb.checked; });
                if (selectAllLbl) selectAllLbl.classList.remove('is-indeterminate');
            });
        }

        cardCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', updateSelectAllState);
        });

        // ── Format toggle ─────────────────────────────────────────────────
        fmtBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                fmtBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                formatIn.value = btn.dataset.fmt || 'pdf';
                if (label) {
                    label.textContent = btn.dataset.fmt === 'xlsx'
                        ? 'Download Excel' : 'Download Report';
                }
            });
        });

        // ── Keep From/To in sync so invalid ranges cannot be set ──────────
        var fromInput = document.getElementById('rptFrom');
        var toInput   = document.getElementById('rptTo');

        if (fromInput && toInput) {
            fromInput.addEventListener('change', function () {
                // To can never be before From
                if (fromInput.value) {
                    toInput.min = fromInput.value;
                    if (toInput.value && toInput.value < fromInput.value) {
                        toInput.value = fromInput.value;
                    }
                } else {
                    toInput.min = toInput.dataset.absMin || '';
                }
            });

            toInput.addEventListener('change', function () {
                // From can never be after To
                if (toInput.value) {
                    fromInput.max = toInput.value;
                    if (fromInput.value && fromInput.value > toInput.value) {
                        fromInput.value = toInput.value;
                    }
                } else {
                    fromInput.max = fromInput.dataset.absMax || '';
                }
            });

            // Store the absolute bounds set by PHP so we can restore them after a clear
            toInput.dataset.absMin   = toInput.min   || '';
            fromInput.dataset.absMax = fromInput.max || '';
        }

        // ── Clear dates ───────────────────────────────────────────────────
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (fromInput) {
                    fromInput.value = '';
                    fromInput.max   = fromInput.dataset.absMax || '';
                }
                if (toInput) {
                    toInput.value = '';
                    toInput.min   = toInput.dataset.absMin || '';
                }
            });
        }

        // ── Form submit ───────────────────────────────────────────────────
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (submitBtn.disabled) return;

            // Clear previous error
            if (errorEl) errorEl.textContent = '';

            // Validate: at least one report type must be selected
            var checkedBoxes = form.querySelectorAll('input[name="report_type[]"]:checked');
            if (checkedBoxes.length === 0) {
                if (errorEl) errorEl.textContent = 'Please choose at least one report first.';
                return;
            }

            // Validate: From cannot be after To
            var fromVal = fromInput ? fromInput.value : '';
            var toVal   = toInput   ? toInput.value   : '';
            if (fromVal && toVal && fromVal > toVal) {
                if (errorEl) errorEl.textContent = 'The "From" date cannot be later than the "To" date.';
                return;
            }

            // Loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('is-loading');

            var body = new FormData(form);

            fetch('reports/api/generate.php', {
                method: 'POST',
                body: body,
            })
            .then(function (res) {
                if (!res.ok) {
                    return res.json().then(function (j) {
                        throw new Error(j.message || 'Server error (' + res.status + ').');
                    });
                }
                return res.blob();
            })
            .then(function (blob) {
                var url  = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                var fmt  = formatIn.value === 'xlsx' ? 'xlsx' : 'pdf';
                var date = new Date().toISOString().slice(0, 10);
                var allSelected = checkedBoxes.length === cardCheckboxes.length;
                var type = allSelected
                    ? 'all-reports'
                    : checkedBoxes[0].value.replace(/_/g, '-')
                      + (checkedBoxes.length > 1 ? '-and-more' : '');
                link.download = 'pupsync-' + type + '-' + date + '.' + fmt;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            })
            .catch(function (err) {
                if (errorEl) {
                    errorEl.textContent = err.message || 'Could not generate the report. Please try again.';
                }
            })
            .finally(function () {
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-loading');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
