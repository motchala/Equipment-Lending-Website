/* ================================================================
   student-dashboard.js
   Covers two screens rendered in student-dashboard.php:

     SCREEN A — Portal  (#screen-portal)
       Faculty-code modal → verify → store session → show dashboard

     SCREEN B — Dashboard  (#screen-dashboard)
       Sidebar shell, equipment grid, borrow modal, receipt panel
================================================================ */

/* ================================================================
   SHARED HELPERS
================================================================ */
function escHTML(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function escAttr(str) {
    return String(str ?? '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ================================================================
   SCREEN ROUTER
   Decides which screen is visible on page load.
================================================================ */
(function () {
    'use strict';

    // ── Read persisted state ─────────────────────────────────────
    let _session = null;
    let _receipt = null;
    try { const r = sessionStorage.getItem('pup_student_session'); if (r) _session = JSON.parse(r); } catch (e) { }
    try { const r = sessionStorage.getItem('pup_last_receipt'); if (r) _receipt = JSON.parse(r); } catch (e) { }

    const _hasSession = !!(_session && _session.valid);
    const _hasReceipt = !!_receipt;

    const portalEl = document.getElementById('screen-portal');
    const dashboardEl = document.getElementById('screen-dashboard');

    function showPortal() {
        if (portalEl) portalEl.style.display = '';
        if (dashboardEl) dashboardEl.style.display = 'none';
        document.body.classList.remove('dashboard-active');
        initPortal();
    }

    function showDashboard() {
        // Re-read sessionStorage fresh every time so a just-verified session
        // (including its inventory array) is always picked up correctly.
        let freshSession = null;
        let freshReceipt = null;
        try { const r = sessionStorage.getItem('pup_student_session'); if (r) freshSession = JSON.parse(r); } catch (e) { }
        try { const r = sessionStorage.getItem('pup_last_receipt'); if (r) freshReceipt = JSON.parse(r); } catch (e) { }

        if (portalEl) portalEl.style.display = 'none';
        if (dashboardEl) dashboardEl.style.display = '';
        document.body.classList.add('dashboard-active');
        initDashboard(freshSession, freshReceipt);
    }

    // Route: session or receipt → dashboard; nothing → portal
    if (_hasSession || _hasReceipt) {
        showDashboard();
    } else {
        showPortal();
    }

    // Expose so the portal can trigger dashboard after verify
    window._pupShowDashboard = showDashboard;
    window._pupShowPortal = showPortal;
})();


/* ================================================================
   SCREEN A — PORTAL
   Faculty-code modal, step flow, QR receipt
================================================================ */
function initPortal() {

    const facultyCodeModal = document.getElementById('facultyCodeModal');
    if (!facultyCodeModal) return;

    let _verified = null;
    let _lastReceipt = null;
    let _openingReceipt = false;

    // Restore receipt banner if one was saved earlier this session
    try {
        const saved = sessionStorage.getItem('pup_last_receipt');
        if (saved) { _lastReceipt = JSON.parse(saved); showReceiptBanner(_lastReceipt); }
    } catch (e) { }

    function showReceiptBanner(receipt) {
        const banner = document.getElementById('receiptBanner');
        const sub = document.getElementById('receiptBannerSub');
        if (!banner) return;
        if (sub) sub.textContent = receipt.equipment + ' · Return by ' + receipt.return_date;
        banner.style.display = 'flex';
        banner.onclick = function () {
            _openingReceipt = true;
            bootstrap.Modal.getOrCreateInstance(facultyCodeModal).show();
        };
    }

    // ── Error helpers ────────────────────────────────────────────
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

    // ── Modal show event ─────────────────────────────────────────
    facultyCodeModal.addEventListener('show.bs.modal', function (event) {
        if (_openingReceipt && _lastReceipt) {
            _openingReceipt = false;
            showStep3(_lastReceipt);
            return;
        }
        _openingReceipt = false;
        const card = event.relatedTarget;
        const action = card?.getAttribute('data-action');
        const hidden = document.getElementById('actionType');
        if (hidden) hidden.value = action || 'borrow';
        showStep1();
    });

    // ── Step 1: verify faculty code ──────────────────────────────
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

    // ── Step 2: borrow form ──────────────────────────────────────
    function showStep2(data) {
        clearPortalError();
        const inventoryOptions = data.inventory.length
            ? data.inventory.map(i => `<option value="${escAttr(i.item_name)}">${escHTML(i.item_name)} — ${escHTML(i.category)} (${i.quantity} available)</option>`).join('')
            : '<option disabled>No equipment available</option>';

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

    // ── Step 3: success / receipt ────────────────────────────────
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
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Student</span><strong>${escHTML(receipt.student_name)} &nbsp;·&nbsp; ${escHTML(receipt.student_id)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Authorized by</span><strong>${escHTML(receipt.faculty_name)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Equipment</span><strong>${escHTML(receipt.equipment)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Room</span><strong>${escHTML(receipt.room)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Borrow</span><strong>${escHTML(receipt.borrow_date)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Return by</span><strong>${escHTML(receipt.return_date)}</strong></div>
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
                text: url, width: 160, height: 160,
                colorDark: '#800000', colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });
        }
        if (window._qrStudentLoaded) { doRender(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        s.onload = () => { window._qrStudentLoaded = true; doRender(); };
        document.head.appendChild(s);
    }

    // ── Verify handler ───────────────────────────────────────────
    function handleVerify() {
        clearPortalError();
        const code = (document.getElementById('facultyCode')?.value || '').trim();
        const name = (document.getElementById('studentName')?.value || '').trim();
        const id = (document.getElementById('studentId')?.value || '').trim();

        if (!code) { showPortalError('Please enter the faculty code.'); return; }
        if (!name) { showPortalError('Please enter your name.'); return; }
        if (!id) { showPortalError('Please enter your Student ID.'); return; }

        const btn = document.getElementById('btnVerifyCode');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Verifying…';

        fetch('includes/verify-faculty-code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code, student_name: name, student_id: id }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>Verify & Continue';
                    showPortalError(data.error);
                    return;
                }
                // Store session and switch to dashboard (no redirect needed)
                try {
                    sessionStorage.setItem('pup_student_session', JSON.stringify({
                        ...data, student_name: name, student_id: id,
                    }));
                } catch (e) { }
                // Close modal, then flip screen
                bootstrap.Modal.getOrCreateInstance(facultyCodeModal).hide();
                window._pupShowDashboard();
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>Verify & Continue';
                showPortalError('Network error. Please try again.');
            });
    }

    // ── Submit borrow (portal flow, legacy path) ─────────────────
    function handleSubmit() {
        clearPortalError();
        if (!_verified) { showPortalError('Session expired. Please go back and verify again.'); return; }

        const equipment = (document.getElementById('equipmentName')?.value || '').trim();
        const room = (document.getElementById('borrowRoom')?.value || '').trim();
        const borrow = document.getElementById('borrowDate')?.value || '';
        const ret = document.getElementById('returnDate')?.value || '';

        if (!equipment) { showPortalError('Please select an equipment item.'); return; }
        if (!room) { showPortalError('Please enter the room / location.'); return; }
        if (!borrow) { showPortalError('Please select a borrow date.'); return; }
        if (!ret) { showPortalError('Please select a return date.'); return; }
        if (ret < borrow) { showPortalError('Return date cannot be before borrow date.'); return; }

        const btn = document.getElementById('btnSubmitBorrow');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Submitting…';

        fetch('includes/submit-student-borrow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code_db_id: _verified.code_db_id,
                faculty_id: _verified.faculty_id,
                faculty_name: _verified.faculty_name,
                student_name: _verified.student_name,
                student_id: _verified.student_id,
                equipment_name: equipment,
                room, borrow_date: borrow, return_date: ret,
            }),
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
                    student_name: _verified.student_name, student_id: _verified.student_id,
                    faculty_name: _verified.faculty_name, equipment, room,
                    borrow_date: borrow, return_date: ret,
                    request_id: data.request_id, return_token: data.return_token,
                };
                _lastReceipt = receipt;
                try { sessionStorage.setItem('pup_last_receipt', JSON.stringify(receipt)); } catch (e) { }
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
} // end initPortal


/* ================================================================
   SCREEN B — DASHBOARD
   Sidebar, equipment grid, borrow modal, receipt panel
================================================================ */
function initDashboard(sessionArg, receiptArg) {
    'use strict';

    let _session = sessionArg;
    let _receipt = receiptArg;
    let _codeUsed = !(_session && _session.valid) && !!_receipt;

    // ── Identity strings ─────────────────────────────────────────
    const _name = _session?.student_name || _receipt?.student_name || '';
    const _id = _session?.student_id || _receipt?.student_id || '';
    const _faculty = _session?.faculty_name || _receipt?.faculty_name || '—';
    const _initials = _name.trim().split(/\s+/).map(p => p[0] || '').slice(0, 2).join('').toUpperCase() || 'ST';

    document.getElementById('topBarInitials').textContent = _initials;
    document.getElementById('sidebarInitials').textContent = _initials;
    document.getElementById('sidebarName').textContent = _name || '—';
    document.getElementById('sidebarId').textContent = _id || '—';
    document.getElementById('ddName').textContent = _name || '—';
    document.getElementById('ddId').textContent = _id || '—';
    document.getElementById('authChipText').textContent = 'Authorized by ' + _faculty;

    // ── Toast ─────────────────────────────────────────────────────
    let _toastTimer;
    function showToast(msg, type) {
        const t = document.getElementById('sd-toast');
        t.textContent = msg;
        t.className = 'show' + (type ? ' ' + type : '');
        clearTimeout(_toastTimer);
        _toastTimer = setTimeout(() => { t.className = ''; }, 3200);
    }

    // ── Panel switching ───────────────────────────────────────────
    const _panelTitles = {
        'panel-borrow': 'Borrow Equipment',
        'panel-room': 'Reserve a Room',
        'panel-request': 'My Request',
    };

    function switchPanel(id) {
        document.querySelectorAll('.sd-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.side-nav-item[data-panel]').forEach(b => b.classList.remove('active'));
        const panel = document.getElementById(id);
        const navBtn = document.querySelector(`.side-nav-item[data-panel="${id}"]`);
        if (panel) panel.classList.add('active');
        if (navBtn) navBtn.classList.add('active');
        const titleEl = document.getElementById('topBarTitle');
        if (titleEl) titleEl.textContent = _panelTitles[id] || '';
        if (id === 'panel-request') renderReceiptPanel();
        closeMobileNav();
    }

    document.querySelectorAll('.side-nav-item[data-panel]').forEach(btn => {
        btn.addEventListener('click', () => switchPanel(btn.getAttribute('data-panel')));
    });

    // ── Mobile nav ────────────────────────────────────────────────
    const _sideNav = document.getElementById('sideNav');
    const _navBackdrop = document.getElementById('navBackdrop');
    const _mobileBtn = document.getElementById('mobileMenuBtn');

    function syncMobileBtn() {
        if (_mobileBtn) _mobileBtn.style.display = window.innerWidth <= 1024 ? 'flex' : 'none';
    }
    syncMobileBtn();
    window.addEventListener('resize', syncMobileBtn);

    if (_mobileBtn) _mobileBtn.addEventListener('click', () => { _sideNav.classList.toggle('open'); _navBackdrop.classList.toggle('open'); });
    if (_navBackdrop) _navBackdrop.addEventListener('click', closeMobileNav);

    function closeMobileNav() {
        _sideNav.classList.remove('open');
        _navBackdrop.classList.remove('open');
    }

    // ── Student dropdown ──────────────────────────────────────────
    const _avatarBtn = document.getElementById('avatarBtn');
    const _dropdown = document.getElementById('studentDropdown');

    _avatarBtn.addEventListener('click', e => {
        e.stopPropagation();
        const open = _dropdown.classList.contains('open');
        _dropdown.classList.toggle('open', !open);
        _avatarBtn.setAttribute('aria-expanded', String(!open));
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#studentDdWrap')) {
            _dropdown.classList.remove('open');
            _avatarBtn.setAttribute('aria-expanded', 'false');
        }
    });

    document.getElementById('endSessionBtn').addEventListener('click', () => {
        try { sessionStorage.removeItem('pup_student_session'); } catch (e) { }
        try { sessionStorage.removeItem('pup_last_receipt'); } catch (e) { }
        window._pupShowPortal();
    });

    // ── Equipment grid ────────────────────────────────────────────
    const _inventory = _session?.inventory || [];
    const _grid = document.getElementById('equipGrid');
    const _searchEl = document.getElementById('equipSearch');
    const _catFilter = document.getElementById('categoryFilter');
    const _bannerSlot = document.getElementById('panelBannerSlot');

    if (_codeUsed) {
        _bannerSlot.innerHTML = `
            <div class="code-used-banner">
                <span class="material-symbols-outlined">info</span>
                Your faculty code has already been used. You can still view your receipt in
                <strong>My Request</strong>.
            </div>`;
        document.getElementById('catalogFilters').style.display = 'none';
        _grid.innerHTML = `
            <div class="eq-empty">
                <span class="material-symbols-outlined">lock</span>
                <p>Equipment browsing is only available during an active authorized session.</p>
            </div>`;
    } else {
        _bannerSlot.innerHTML = `
            <div class="auth-banner">
                <div class="auth-banner-icon">
                    <span class="material-symbols-outlined">verified_user</span>
                </div>
                <div>
                    <div class="auth-banner-label">Authorized Session</div>
                    <div class="auth-banner-faculty">${escHTML(_faculty)}</div>
                    <div class="auth-banner-note">
                        One request is allowed per authorization code — the code is consumed on submission.
                    </div>
                </div>
            </div>`;

        const cats = [...new Set(_inventory.map(i => i.category).filter(Boolean))].sort();
        cats.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c; opt.textContent = c;
            _catFilter.appendChild(opt);
        });

        renderGrid();
        _searchEl.addEventListener('input', renderGrid);
        _catFilter.addEventListener('change', renderGrid);
    }

    function renderGrid() {
        const q = (_searchEl.value || '').toLowerCase().trim();
        const cat = _catFilter.value;

        const filtered = _inventory.filter(item =>
            (!cat || item.category === cat) &&
            (!q || item.item_name.toLowerCase().includes(q))
        );

        _grid.innerHTML = '';

        if (!filtered.length) {
            _grid.innerHTML = `
                <div class="eq-empty">
                    <span class="material-symbols-outlined">search_off</span>
                    <p>No equipment matched your search.</p>
                </div>`;
            return;
        }

        filtered.forEach(item => {
            const qty = parseInt(item.quantity) || 0;
            const avail = qty > 0;
            const low = avail && qty <= 2;

            const stockCls = low ? 'stock-low' : 'stock-avail';
            const stockIcon = low ? 'warning' : 'check_circle';
            const stockText = low ? qty + ' left — limited' : qty + ' available';

            const imgHtml = item.image_path
                ? `<img src="/Equipment-Lending-Website/${escAttr(item.image_path)}" alt="${escAttr(item.item_name)}" class="eq-card-img-photo" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                   <span class="material-symbols-outlined eq-card-img-fallback" style="display:none;">inventory_2</span>`
                : `<span class="material-symbols-outlined">inventory_2</span>`;

            const card = document.createElement('div');
            card.className = 'eq-card';
            card.innerHTML = `
                <div class="eq-card-img">
                    ${imgHtml}
                </div>
                <div class="eq-card-body">
                    <div class="eq-card-name">${escHTML(item.item_name)}</div>
                    <div class="eq-card-cat">
                        <span class="material-symbols-outlined">folder</span>
                        ${escHTML(item.category || 'General')}
                    </div>
                    <div class="stock-badge ${avail ? stockCls : ''}">
                        <span class="material-symbols-outlined">${avail ? stockIcon : 'cancel'}</span>
                        ${avail ? escHTML(stockText) : 'Out of stock'}
                    </div>
                    <button class="btn-borrow-card" data-name="${escAttr(item.item_name)}"
                        ${!avail ? 'disabled' : ''}>
                        <span class="material-symbols-outlined">${avail ? 'add_shopping_cart' : 'block'}</span>
                        ${avail ? 'Borrow' : 'Unavailable'}
                    </button>
                </div>`;

            if (avail) {
                card.querySelector('.btn-borrow-card')
                    .addEventListener('click', () => openBorrowModal(item.item_name));
            }
            _grid.appendChild(card);
        });
    }

    // ── Borrow modal ──────────────────────────────────────────────
    let _selectedEquip = '';
    const _borrowModalEl = document.getElementById('borrowModal');
    const _borrowModal = new bootstrap.Modal(_borrowModalEl);
    const _today = new Date().toISOString().split('T')[0];
    const _tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

    function openBorrowModal(equipName) {
        _selectedEquip = equipName;
        document.getElementById('borrowEquipDisplay').value = equipName;
        document.getElementById('borrowRoom').value = '';
        document.getElementById('borrowDateInput').value = _today;
        document.getElementById('borrowDateInput').min = _today;
        document.getElementById('returnDateInput').value = _tomorrow;
        document.getElementById('returnDateInput').min = _tomorrow;
        document.getElementById('borrowModalSubtitle').textContent = equipName;
        document.getElementById('borrowModalError').style.display = 'none';
        const btn = document.getElementById('borrowSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
        _borrowModal.show();
    }

    document.getElementById('borrowDateInput').addEventListener('change', function () {
        const retEl = document.getElementById('returnDateInput');
        const nextDay = new Date(new Date(this.value + 'T00:00:00').getTime() + 86400000).toISOString().split('T')[0];
        retEl.min = nextDay;
        if (retEl.value < nextDay) retEl.value = nextDay;
    });

    document.getElementById('borrowSubmitBtn').addEventListener('click', handleDashboardSubmit);

    function showBorrowError(msg) {
        const el = document.getElementById('borrowModalError');
        el.textContent = msg;
        el.style.display = 'block';
    }

    // ── Submit borrow (dashboard flow) ────────────────────────────
    function handleDashboardSubmit() {
        document.getElementById('borrowModalError').style.display = 'none';
        if (!_session) { showBorrowError('Session expired. Please go back and re-enter a faculty code.'); return; }

        const room = (document.getElementById('borrowRoom').value || '').trim();
        const borrow = document.getElementById('borrowDateInput').value || '';
        const ret = document.getElementById('returnDateInput').value || '';

        if (!room) { showBorrowError('Please enter the room or location.'); return; }
        if (!borrow) { showBorrowError('Please select a borrow date.'); return; }
        if (!ret) { showBorrowError('Please select a return date.'); return; }
        if (ret < borrow) { showBorrowError('Return date cannot be before the borrow date.'); return; }

        const btn = document.getElementById('borrowSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Submitting…';

        fetch('includes/submit-student-borrow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code_db_id: _session.code_db_id,
                faculty_id: _session.faculty_id,
                faculty_name: _session.faculty_name,
                student_name: _session.student_name,
                student_id: _session.student_id,
                equipment_name: _selectedEquip,
                room, borrow_date: borrow, return_date: ret,
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
                    showBorrowError(data.error);
                    return;
                }

                _receipt = {
                    student_name: _session.student_name, student_id: _session.student_id,
                    faculty_name: _session.faculty_name, equipment: _selectedEquip,
                    room, borrow_date: borrow, return_date: ret,
                    request_id: data.request_id, return_token: data.return_token,
                };
                try { sessionStorage.setItem('pup_last_receipt', JSON.stringify(_receipt)); } catch (e) { }
                try { sessionStorage.removeItem('pup_student_session'); } catch (e) { }
                _session = null;
                _codeUsed = true;

                _borrowModal.hide();
                showToast('Request submitted — approved!', 'success');

                // Lock all borrow buttons
                document.querySelectorAll('.btn-borrow-card:not(:disabled)').forEach(b => {
                    b.disabled = true;
                    b.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">lock</span> Code used';
                });

                // Update auth banner
                if (_bannerSlot) {
                    _bannerSlot.innerHTML = `
                    <div class="code-used-banner">
                        <span class="material-symbols-outlined">info</span>
                        Your faculty code has been used. View your receipt in <strong>My Request</strong>.
                    </div>`;
                }

                switchPanel('panel-request');
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
                showBorrowError('Network error. Please check your connection and try again.');
            });
    }

    // ── Receipt panel ─────────────────────────────────────────────
    function renderReceiptPanel() {
        const el = document.getElementById('requestPanelContent');

        if (!_receipt) {
            el.innerHTML = `
                <div class="no-request-wrap">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <div class="no-request-title">No active request</div>
                    <div class="no-request-sub">
                        Borrow an item from the equipment list and your receipt will appear here.
                    </div>
                </div>`;
            return;
        }

        const base = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
        const returnUrl = base + 'return_confirm.php?token=' + encodeURIComponent(_receipt.return_token || '');

        el.innerHTML = `
            <div class="receipt-card">
                <div class="receipt-head">
                    <span class="material-symbols-outlined check-icon">check_circle</span>
                    <h3>Request Approved</h3>
                    <p>Request #${escHTML(String(_receipt.request_id))} &nbsp;·&nbsp; Show QR when claiming &amp; returning</p>
                </div>
                <div class="receipt-body">
                    <div class="receipt-row"><span class="receipt-label">Student</span><span class="receipt-value">${escHTML(_receipt.student_name)}</span></div>
                    <div class="receipt-row"><span class="receipt-label">Student ID</span><span class="receipt-value">${escHTML(_receipt.student_id)}</span></div>
                    <div class="receipt-row"><span class="receipt-label">Authorized by</span><span class="receipt-value">${escHTML(_receipt.faculty_name)}</span></div>
                    <div class="receipt-row"><span class="receipt-label">Equipment</span><span class="receipt-value">${escHTML(_receipt.equipment)}</span></div>
                    <div class="receipt-row"><span class="receipt-label">Room</span><span class="receipt-value">${escHTML(_receipt.room)}</span></div>
                    <div class="receipt-row"><span class="receipt-label">Borrow Date</span><span class="receipt-value">${escHTML(_receipt.borrow_date)}</span></div>
                    <div class="receipt-row"><span class="receipt-label">Return by</span><span class="receipt-value">${escHTML(_receipt.return_date)}</span></div>
                    <div class="receipt-qr-section">
                        <p>
                            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">qr_code_2</span>
                            Show this QR to the admin to <strong>claim</strong> your item,
                            and again when <strong>returning</strong> it.
                        </p>
                        <div id="receiptQrTarget"
                            style="display:inline-block;padding:12px;background:#fff;
                                   border-radius:14px;border:2px solid var(--color-primary);">
                        </div>
                    </div>
                </div>
            </div>`;

        _renderQr(returnUrl);
    }

    function _renderQr(url) {
        function doRender() {
            const target = document.getElementById('receiptQrTarget');
            if (!target) return;
            new QRCode(target, {
                text: url, width: 160, height: 160,
                colorDark: '#a32020', colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });
        }
        if (window._sdQrLoaded) { doRender(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        s.onload = () => { window._sdQrLoaded = true; doRender(); };
        document.head.appendChild(s);
    }

    // ── Init: if code already used, land on receipt tab ───────────
    if (_codeUsed) {
        switchPanel('panel-request');
    }

} // end initDashboard