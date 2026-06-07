<?php
// student-portal.php — No session required, no login needed
// Student access point for borrowing equipment or reserving rooms
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSync | Student Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/student-portal.css">
</head>

<body>

    <!-- Top Bar — Minimal, no user account -->
    <header class="student-topbar">
        <div class="student-brand">
            <div class="student-brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div>
                <div class="student-brand-title"><strong>PUP</strong>SYNC</div>
                <div class="student-brand-sub">Student Services</div>
            </div>
        </div>
        <a href="landing-page.php" class="student-back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Portal
        </a>
    </header>

    <!-- Main Content — Centered Choice Cards -->
    <main class="student-main">

        <!-- Hero Text -->
        <div class="student-hero">
            <h1>What do you need today?</h1>
            <p>Select a service to get started. No account required.</p>
        </div>

        <!-- Choice Cards — Large, Visual, Distinct from Faculty UI -->
        <div class="student-choices">

            <!-- Borrow Equipment -->
            <div class="student-choice-card" data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                data-action="borrow" style="cursor:pointer;">
                <div class="choice-glow borrow-glow"></div>
                <div class="choice-icon-wrap borrow-icon">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                <h2>Borrow Equipment</h2>
                <p>Browse available laptops, projectors, lab equipment, and more. Submit a request for your class or
                    project.</p>
                <div class="choice-meta">
                    <span class="choice-badge">
                        <span class="material-symbols-outlined">check_circle</span>
                        Instant Request
                    </span>
                </div>
                <div class="choice-arrow">
                    <span class="material-symbols-outlined">arrow_forward</span>
                </div>
            </div>

            <!-- Reserve a Room -->
            <div class="student-choice-card" data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                data-action="room" style="cursor:pointer;">
                <div class="choice-glow room-glow"></div>
                <div class="choice-icon-wrap room-icon">
                    <span class="material-symbols-outlined">meeting_room</span>
                </div>
                <h2>Reserve a Room</h2>
                <p>Book lecture halls, computer labs, or study rooms for your group meetings and presentations.</p>
                <div class="choice-meta">
                    <span class="choice-badge">
                        <span class="material-symbols-outlined">event_available</span>
                        Real-time Availability
                    </span>
                </div>
                <div class="choice-arrow">
                    <span class="material-symbols-outlined">arrow_forward</span>
                </div>
            </div>

        </div>

        <!-- Receipt Recall Banner (hidden until a request is submitted) -->
        <div id="receiptBanner" style="display:none;max-width:560px;margin:20px auto 0;background:#fff8e1;border-radius:14px;padding:14px 20px;display:none;align-items:center;gap:12px;border:1px solid #ffe082;cursor:pointer;">
            <span class="material-symbols-outlined" style="color:#800000;font-size:24px;">receipt_long</span>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:.9rem;color:#333;">You have an active borrow request</div>
                <div style="font-size:.78rem;color:#666;" id="receiptBannerSub"></div>
            </div>
            <span class="material-symbols-outlined" style="color:#800000;">qr_code_2</span>
        </div>

        <!-- Quick Info Footer -->
        <div class="student-info-footer">
            <div class="info-item">
                <span class="material-symbols-outlined">schedule</span>
                <span>Mon – Fri · 7:00 AM – 5:00 PM</span>
            </div>
            <div class="info-divider"></div>
            <div class="info-item">
                <span class="material-symbols-outlined">location_on</span>
                <span>PUP Biñan Campus, Laguna</span>
            </div>
            <div class="info-divider"></div>
            <div class="info-item">
                <span class="material-symbols-outlined">help</span>
                <span>Visit the Admin Office for assistance</span>
            </div>
        </div>

    </main>

    <!-- Faculty Code Modal -->
    <div class="modal fade" id="facultyCodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius:20px; border:none; box-shadow:0 10px 40px rgba(128,0,0,0.2);">

                <!-- Modal Header -->
                <div class="modal-header"
                    style="background:linear-gradient(135deg, #800000 0%, #5a0000 100%); border-radius:20px 20px 0 0; border-bottom:none; padding:24px 28px;">
                    <div>
                        <h5 class="modal-title"
                            style="color:#fff; font-family:var(--font-display); font-weight:700; font-size:1.25rem; margin-bottom:4px;">
                            Faculty Authorization Required
                        </h5>
                        <p style="color:rgba(255,255,255,0.8); margin:0; font-size:0.875rem;" id="modalSubtitle">
                            Enter your faculty's code to proceed
                        </p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                        style="opacity:0.8;"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body" style="padding:32px 28px 24px; background:var(--color-surface);">

                    <!-- Faculty Code Input -->
                    <div class="mb-4">
                        <label for="facultyCode" class="form-label"
                            style="font-weight:600; color:var(--color-on-surface); font-size:0.875rem;">
                            Faculty Code
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"
                                style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-primary);">
                                <span class="material-symbols-outlined" style="font-size:20px;">key</span>
                            </span>
                            <input type="text" class="form-control form-control-lg" id="facultyCode"
                                placeholder="Enter 6-digit faculty code"
                                style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-on-surface); font-size:1rem; padding:12px 16px;"
                                autocomplete="off">
                        </div>
                        <div class="form-text" style="color:var(--color-secondary); font-size:0.8rem; margin-top:8px;">
                            Ask your faculty adviser or instructor for their code.
                        </div>
                    </div>

                    <!-- Student Name -->
                    <div class="mb-3">
                        <label for="studentName" class="form-label"
                            style="font-weight:600; color:var(--color-on-surface); font-size:0.875rem;">
                            Your Name
                        </label>
                        <input type="text" class="form-control" id="studentName" placeholder="Juan Dela Cruz"
                            style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-on-surface); padding:10px 14px;">
                    </div>

                    <!-- Student ID -->
                    <div class="mb-3">
                        <label for="studentId" class="form-label"
                            style="font-weight:600; color:var(--color-on-surface); font-size:0.875rem;">
                            Student ID
                        </label>
                        <input type="text" class="form-control" id="studentId" placeholder="20XX-XXXXX-BN-X"
                            style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-on-surface); padding:10px 14px;">
                    </div>

                    <!-- Hidden action type -->
                    <input type="hidden" id="actionType" value="">

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer"
                    style="border-top:1px solid var(--color-outline-variant); padding:20px 28px 28px; background:var(--color-surface); border-radius:0 0 20px 20px;">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                        style="padding:10px 24px; border-radius:12px; border:1px solid var(--color-outline-variant); color:var(--color-secondary); font-weight:600; background:transparent;">
                        Cancel
                    </button>
                    <button type="button" class="btn" id="btnVerifyCode"
                        style="padding:10px 28px; border-radius:12px; background:linear-gradient(135deg, #800000 0%, #5a0000 100%); color:#fff; font-weight:700; border:none;">
                        <span class="material-symbols-outlined"
                            style="font-size:18px; vertical-align:middle; margin-right:6px;">verified</span>
                        Verify & Continue
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Decorative Background Elements -->
    <div class="student-bg-pattern"></div>
    <div class="student-bg-blob blob-1"></div>
    <div class="student-bg-blob blob-2"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const facultyCodeModal = document.getElementById('facultyCodeModal');
        const actionTypeInput = document.getElementById('actionType');
        const modalSubtitle = document.getElementById('modalSubtitle');

        // Track verified state
        let _verified = null;

        // Track last submitted receipt so the student can re-open it
        let _lastReceipt = null;
        let _openingReceipt = false;

        // Restore receipt from sessionStorage on page load
        (function() {
            try {
                const saved = sessionStorage.getItem('pup_last_receipt');
                if (saved) {
                    _lastReceipt = JSON.parse(saved);
                    showReceiptBanner(_lastReceipt);
                }
            } catch (e) {}
        })();

        function showReceiptBanner(receipt) {
            const banner = document.getElementById('receiptBanner');
            const sub = document.getElementById('receiptBannerSub');
            if (!banner) return;
            if (sub) sub.textContent = receipt.equipment + ' · Return by ' + receipt.return_date;
            banner.style.display = 'flex';
            banner.onclick = function() {
                _openingReceipt = true;
                bootstrap.Modal.getOrCreateInstance(facultyCodeModal).show();
            };
        }

        // ── Helpers ────────────────────────────────────────────────────────────
        function showPortalError(msg) {
            let el = document.getElementById('portalError');
            if (!el) {
                el = document.createElement('div');
                el.id = 'portalError';
                el.style.cssText = 'color:#c62828;background:#fce4ec;border-radius:10px;padding:10px 14px;font-size:.85rem;margin-top:12px;display:none;';
                document.querySelector('.modal-body').appendChild(el);
            }
            el.textContent = msg;
            el.style.display = 'block';
        }

        function clearPortalError() {
            const el = document.getElementById('portalError');
            if (el) el.style.display = 'none';
        }

        // ── Update modal subtitle on card click ─────────────────────────────────
        facultyCodeModal.addEventListener('show.bs.modal', function(event) {
            if (_openingReceipt && _lastReceipt) {
                _openingReceipt = false;
                showStep3(_lastReceipt);
                return;
            }
            _openingReceipt = false;
            const card = event.relatedTarget;
            const action = card?.getAttribute('data-action');
            if (actionTypeInput) actionTypeInput.value = action || 'borrow';
            showStep1();
        });

        // ── Step 1: verification view ───────────────────────────────────────────
        function showStep1() {
            clearPortalError();
            document.querySelector('.modal-header').innerHTML = `
            <div>
                <h5 class="modal-title" style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.25rem;margin-bottom:4px;">Faculty Authorization</h5>
                <p style="color:rgba(255,255,255,.8);margin:0;font-size:.875rem;">Enter your faculty's one-time code to proceed</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>`;

            document.querySelector('.modal-body').innerHTML = `
            <div class="mb-4">
                <label class="form-label" style="font-weight:600;color:var(--color-on-surface);font-size:.875rem;">Faculty Code</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-primary);">
                        <span class="material-symbols-outlined" style="font-size:20px;">key</span>
                    </span>
                    <input type="text" class="form-control form-control-lg" id="facultyCode" placeholder="abc-123-xy4"
                        style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);font-size:1rem;padding:12px 16px;letter-spacing:2px;font-family:monospace;"
                        autocomplete="off" autocapitalize="none">
                </div>
                <div class="form-text" style="color:var(--color-secondary);font-size:.8rem;margin-top:8px;">Ask your faculty for their one-time access code.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-weight:600;color:var(--color-on-surface);font-size:.875rem;">Your Name</label>
                <input type="text" class="form-control" id="studentName" placeholder="Juan Dela Cruz"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-weight:600;color:var(--color-on-surface);font-size:.875rem;">Student ID</label>
                <input type="text" class="form-control" id="studentId" placeholder="20XX-XXXXX-BN-X"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            </div>
            <input type="hidden" id="actionType" value="borrow">`;

            document.querySelector('.modal-footer').innerHTML = `
            <button type="button" class="btn" data-bs-dismiss="modal"
                style="padding:10px 24px;border-radius:12px;border:1px solid var(--color-outline-variant);color:var(--color-secondary);font-weight:600;background:transparent;">
                Cancel
            </button>
            <button type="button" class="btn" id="btnVerifyCode"
                style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>
                Verify & Continue
            </button>`;

            document.getElementById('btnVerifyCode').addEventListener('click', handleVerify);
        }

        // ── Step 2: borrow form view ────────────────────────────────────────────
        function showStep2(data) {
            clearPortalError();
            const inventoryOptions = data.inventory.length ?
                data.inventory.map(i => `<option value="${escAttr(i.item_name)}">${escHTML(i.item_name)} — ${escHTML(i.category)} (${i.quantity} available)</option>`).join('') :
                '<option disabled>No equipment available</option>';

            const today = new Date().toISOString().split('T')[0];
            const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

            document.querySelector('.modal-header').innerHTML = `
            <div>
                <h5 class="modal-title" style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.2rem;margin-bottom:4px;">Borrow Equipment</h5>
                <p style="color:rgba(255,255,255,.8);margin:0;font-size:.85rem;">
                    Authorized by: <strong>${escHTML(data.faculty_name)}</strong>
                </p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>`;

            document.querySelector('.modal-body').innerHTML = `
            <div class="mb-3">
                <label class="form-label" style="font-weight:600;font-size:.875rem;">Select Equipment <span style="color:#c62828;">*</span></label>
                <select class="form-select" id="equipmentName"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                    <option value="" disabled selected>Choose equipment…</option>
                    ${inventoryOptions}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-weight:600;font-size:.875rem;">Room / Location <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-control" id="borrowRoom" placeholder="e.g. B-205"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            </div>
            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label" style="font-weight:600;font-size:.875rem;">Borrow Date <span style="color:#c62828;">*</span></label>
                    <input type="date" class="form-control" id="borrowDate" min="${today}" value="${today}"
                        style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                </div>
                <div class="col">
                    <label class="form-label" style="font-weight:600;font-size:.875rem;">Return Date <span style="color:#c62828;">*</span></label>
                    <input type="date" class="form-control" id="returnDate" min="${tomorrow}" value="${tomorrow}"
                        style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                </div>
            </div>
            <div style="background:#fff8e1;border-radius:10px;padding:10px 14px;font-size:.8rem;color:#795548;">
                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:middle;margin-right:4px;">info</span>
                This request will be reviewed by the admin. Your faculty will be notified.
            </div>`;

            document.querySelector('.modal-footer').innerHTML = `
            <button type="button" id="btnBackToStep1"
                style="padding:10px 20px;border-radius:12px;border:1px solid var(--color-outline-variant);color:var(--color-secondary);font-weight:600;background:transparent;cursor:pointer;">
                ← Back
            </button>
            <button type="button" id="btnSubmitBorrow"
                style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;cursor:pointer;">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>
                Submit Request
            </button>`;

            document.getElementById('btnBackToStep1').addEventListener('click', showStep1);
            document.getElementById('btnSubmitBorrow').addEventListener('click', handleSubmit);
        }

        // ── Step 3: success view ────────────────────────────────────────────────
        function showStep3(receipt) {
            const base = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
            const returnUrl = base + 'return_confirm.php?token=' + receipt.return_token;

            document.querySelector('.modal-header').innerHTML = `
            <div>
                <h5 class="modal-title" style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.2rem;margin-bottom:4px;">Request Approved!</h5>
                <p style="color:rgba(255,255,255,.8);margin:0;font-size:.85rem;">Show this receipt to the admin to claim your equipment</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>`;

            document.querySelector('.modal-body').innerHTML = `
            <div style="text-align:center;padding:12px 0 16px;">
                <span class="material-symbols-outlined" style="font-size:44px;color:#2e7d32;display:block;margin-bottom:8px;">check_circle</span>
                <p style="font-size:.95rem;font-weight:700;margin-bottom:2px;">Request #${escHTML(String(receipt.request_id))}</p>
                <p style="font-size:.78rem;color:#888;margin-bottom:0;">Auto-approved via faculty authorization</p>
            </div>

            <div style="background:#f9f5f5;border-radius:14px;padding:14px 16px;font-size:.82rem;margin-bottom:16px;line-height:2;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Student</span>
                    <strong>${escHTML(receipt.student_name)} &nbsp;·&nbsp; ${escHTML(receipt.student_id)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Authorized by</span>
                    <strong>${escHTML(receipt.faculty_name)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Equipment</span>
                    <strong>${escHTML(receipt.equipment)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Room</span>
                    <strong>${escHTML(receipt.room)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Borrow</span>
                    <strong>${escHTML(receipt.borrow_date)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Return by</span>
                    <strong>${escHTML(receipt.return_date)}</strong>
                </div>
            </div>

            <div style="text-align:center;">
                <p style="font-size:.78rem;color:#666;margin-bottom:10px;">
                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">qr_code_2</span>
                    Show this QR to the admin to <strong>claim</strong> and again when <strong>returning</strong> the equipment.
                </p>
                <div id="studentReceiptQr" style="display:inline-block;padding:10px;background:#fff;border-radius:14px;border:2px solid #800000;"></div>
            </div>`;

            document.querySelector('.modal-footer').innerHTML = `
            <button type="button" class="btn" data-bs-dismiss="modal"
                style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;width:100%;">
                Done
            </button>`;

            _renderStudentQr(returnUrl);
        }

        function _renderStudentQr(url) {
            function doRender() {
                new QRCode(document.getElementById('studentReceiptQr'), {
                    text: url,
                    width: 160,
                    height: 160,
                    colorDark: '#800000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
            if (window._qrStudentLoaded) {
                doRender();
            } else {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                s.onload = () => {
                    window._qrStudentLoaded = true;
                    doRender();
                };
                document.head.appendChild(s);
            }
        }

        // ── Handlers ────────────────────────────────────────────────────────────
        function handleVerify() {
            clearPortalError();
            const code = (document.getElementById('facultyCode')?.value || '').trim();
            const name = (document.getElementById('studentName')?.value || '').trim();
            const id = (document.getElementById('studentId')?.value || '').trim();

            if (!code) {
                showPortalError('Please enter the faculty code.');
                return;
            }
            if (!name) {
                showPortalError('Please enter your name.');
                return;
            }
            if (!id) {
                showPortalError('Please enter your Student ID.');
                return;
            }

            const btn = document.getElementById('btnVerifyCode');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Verifying…';

            fetch('includes/verify-faculty-code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        code,
                        student_name: name,
                        student_id: id
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>Verify & Continue';
                        showPortalError(data.error);
                        return;
                    }
                    // NEW — stores session and redirects to the dashboard
                    try {
                        sessionStorage.setItem('pup_student_session', JSON.stringify({
                            ...data,
                            student_name: name,
                            student_id: id
                        }));
                    } catch (e) {}
                    window.location.href = 'student-dashboard.php';
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>Verify & Continue';
                    showPortalError('Network error. Please try again.');
                });
        }

        function handleSubmit() {
            clearPortalError();
            if (!_verified) {
                showPortalError('Session expired. Please go back and verify again.');
                return;
            }

            const equipment = (document.getElementById('equipmentName')?.value || '').trim();
            const room = (document.getElementById('borrowRoom')?.value || '').trim();
            const borrow = document.getElementById('borrowDate')?.value || '';
            const ret = document.getElementById('returnDate')?.value || '';

            if (!equipment) {
                showPortalError('Please select an equipment item.');
                return;
            }
            if (!room) {
                showPortalError('Please enter the room / location.');
                return;
            }
            if (!borrow) {
                showPortalError('Please select a borrow date.');
                return;
            }
            if (!ret) {
                showPortalError('Please select a return date.');
                return;
            }
            if (ret < borrow) {
                showPortalError('Return date cannot be before borrow date.');
                return;
            }

            const btn = document.getElementById('btnSubmitBorrow');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Submitting…';

            fetch('includes/submit-student-borrow.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        code_db_id: _verified.code_db_id,
                        faculty_id: _verified.faculty_id,
                        faculty_name: _verified.faculty_name,
                        student_name: _verified.student_name,
                        student_id: _verified.student_id,
                        equipment_name: equipment,
                        room,
                        borrow_date: borrow,
                        return_date: ret,
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
                        showPortalError(data.error);
                        return;
                    }
                    const receipt = {
                        student_name: _verified.student_name,
                        student_id: _verified.student_id,
                        faculty_name: _verified.faculty_name,
                        equipment: equipment,
                        room,
                        borrow_date: borrow,
                        return_date: ret,
                        request_id: data.request_id,
                        return_token: data.return_token,
                    };
                    _lastReceipt = receipt;
                    try {
                        sessionStorage.setItem('pup_last_receipt', JSON.stringify(receipt));
                    } catch (e) {}
                    _verified = null;
                    showReceiptBanner(receipt);
                    showStep3(receipt);
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
                    showPortalError('Network error. Please try again.');
                });
        }

        // ── Escape helpers ──────────────────────────────────────────────────────
        function escHTML(str) {
            return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function escAttr(str) {
            return String(str ?? '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
    </script>
</body>

</html>