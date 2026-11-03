            (function() {
                'use strict';

                /* ── Sub-tab switching ──────────────────────────────────────── */
                var btnPersonal = document.getElementById('subtab-personal-btn');
                var btnAdviser = document.getElementById('subtab-adviser-btn');
                var panelPersonal = document.getElementById('subtab-personal');
                var panelAdviser = document.getElementById('subtab-adviser');

                function activateTab(tab) {
                    if (tab === 'personal') {
                        btnPersonal.classList.add('active');
                        btnPersonal.setAttribute('aria-selected', 'true');
                        btnAdviser.classList.remove('active');
                        btnAdviser.setAttribute('aria-selected', 'false');
                        panelPersonal.classList.add('active');
                        panelAdviser.classList.remove('active');
                    } else {
                        btnAdviser.classList.add('active');
                        btnAdviser.setAttribute('aria-selected', 'true');
                        btnPersonal.classList.remove('active');
                        btnPersonal.setAttribute('aria-selected', 'false');
                        panelAdviser.classList.add('active');
                        panelPersonal.classList.remove('active');
                    }
                }

                // NOTE: click switching is handled via data-action="borrow-subtab" in faculty-dashboard.js
                // activateTab() below is used only by the MutationObserver reset on modal close.

                /* ── Adviser form: client-side "at least one item" guard ────── */
                var advForm = document.getElementById('adviserBorrowForm');
                var advValidationMsg = document.getElementById('advChecklistValidation');

                if (advForm) {
                    advForm.addEventListener('submit', function(e) {
                        var checked = advForm.querySelectorAll('input[type="checkbox"][name="items[]"]:checked');
                        if (checked.length === 0) {
                            e.preventDefault();
                            if (advValidationMsg) {
                                advValidationMsg.style.display = 'block';
                            }
                        } else {
                            if (advValidationMsg) {
                                advValidationMsg.style.display = 'none';
                            }
                        }
                    });
                }

                /* ── Reset adviser form fields on modal close / re-open ─────── */
                function resetAdviserForm() {
                    if (!advForm) return;
                    // Uncheck all checkboxes
                    advForm.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                    });
                    // Clear text/date/file inputs
                    var roomInp = document.getElementById('adv_room');
                    var borrowInp = document.getElementById('adv_borrow_date');
                    var returnInp = document.getElementById('adv_return_date');
                    var fileInp = document.getElementById('adv_request_document');
                    if (roomInp) roomInp.value = '';
                    if (borrowInp) borrowInp.value = '';
                    if (returnInp) returnInp.value = '';
                    if (fileInp) fileInp.value = '';
                    if (window.syncBorrowDrops) window.syncBorrowDrops();
                    if (window.BorrowSchedule) window.BorrowSchedule.resetAll();
                    if (advValidationMsg) advValidationMsg.style.display = 'none';
                }

                /* Hook into the existing closeBorrowModal / openBorrowForm cycle.
                   The modal close button and backdrop click already call
                   closeBorrowModal() in faculty-dashboard.js which sets
                   display:none on #borrowModal — we just need to reset
                   state when the modal is hidden. We observe the modal
                   element for style changes. */
                var borrowModal = document.getElementById('borrowModal');
                if (borrowModal && window.MutationObserver) {
                    var _modalObserver = new MutationObserver(function(mutations) {
                        mutations.forEach(function(m) {
                            if (m.attributeName === 'style') {
                                var hidden = borrowModal.style.display === 'none' || borrowModal.style.display === '';
                                if (hidden) {
                                    resetAdviserForm();
                                    // Reset to Personal tab on close
                                    activateTab('personal');
                                }
                            }
                        });
                    });
                    _modalObserver.observe(borrowModal, {
                        attributes: true
                    });
                }

            }());
