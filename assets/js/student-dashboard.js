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
            const container = document.getElementById('studentReceiptQr');
            if (!container) return;
            container.innerHTML = '';
            const canvasEl = document.createElement('canvas');
            canvasEl.style.cssText = 'display:block;margin:0 auto;';
            container.appendChild(canvasEl);
            QRCode.toCanvas(canvasEl, url, {
                width: 160, margin: 2,
                color: { dark: '#800000', light: '#ffffff' }
            });
        }
        if (window._qrStudentLoaded) { doRender(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js';
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

        fetch('api/verify-faculty-code.php', {
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

        fetch('api/submit-student-borrow.php', {
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
                ? `<img src="${escAttr(item.image_path)}" alt="${escAttr(item.item_name)}" class="eq-card-img-photo" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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

        fetch('api/submit-student-borrow.php', {
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
            target.innerHTML = '';
            const canvasEl = document.createElement('canvas');
            canvasEl.style.cssText = 'display:block;margin:0 auto;';
            target.appendChild(canvasEl);
            QRCode.toCanvas(canvasEl, url, {
                width: 160, margin: 2,
                color: { dark: '#a32020', light: '#ffffff' }
            });
        }
        if (window._sdQrLoaded) { doRender(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js';
        s.onload = () => { window._sdQrLoaded = true; doRender(); };
        document.head.appendChild(s);
    }

    // ── Init: if code already used, land on receipt tab ───────────
    if (_codeUsed) {
        switchPanel('panel-request');
    }

} // end initDashboard

/* ================================================================
   FACILITIES JS — merged from fcty-facilities.js
================================================================ */
/* ================================================================
   PUPSYNC — FACILITIES TAB  (fcty-facilities.js)
   Handles: campus selection, building carousel, building rooms view.
   Companion to fcty-facilities.php + fcty-facilities.css.

   To add a building: push a new object into CAMPUS_DATA[key].buildings
                      and add a matching entry in BUILDING_ROOMS.
   To add a campus:   add a new key to CAMPUS_DATA and a card in the PHP.
================================================================ */
(function () {
    'use strict';

    /* ══════════════════════════════════════════════════════════════
       CAMPUS + BUILDING DATA
       Each building entry references a key in BUILDING_ROOMS below.
    ══════════════════════════════════════════════════════════════ */
    var CAMPUS_DATA = {

        main: {
            label: 'PUP MAIN',
            buildings: [
                {
                    id: 'main-building-a',
                    wing: 'South Wing',
                    icon: 'domain',
                    name: 'Building A (Old)',
                    desc: 'Administrative offices, lecture halls, organization rooms, and specialized laboratories spread across 5 floors.',
                    rooms: 22,
                    floors: 5,
                    image: 'assets/images/faculty/pup-main-building-a-image.jpg'
                },
                {
                    id: 'main-building-b',
                    wing: 'North Wing',
                    icon: 'business',
                    name: 'Building B (New)',
                    desc: 'Modern laboratories, smart classrooms, and collaborative study spaces.',
                    rooms: 68,
                    floors: 6,
                    image: 'assets/images/faculty/pup-main-building-b-image.jpg'
                }
                /* ── Add Building C / D / etc. below when ready ──
                ,{
                    id:     'main-building-c',
                    wing:   'East Wing',
                    icon:   'school',
                    name:   'Building C',
                    desc:   'Description here.',
                    rooms:  0,
                    floors: 0,
                    image:  'assets/images/building-c.jpg'
                }
                */
            ]
        },

        cite: {
            label: 'PUP CITE',
            buildings: [
                {
                    id: 'cite-main',
                    wing: 'Main Block',
                    icon: 'engineering',
                    name: 'PUP CITE Building',
                    desc: 'Technical laboratories, computer labs, and specialized engineering facilities spread across 4 floors.',
                    rooms: 48,
                    floors: 4,
                    image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDtmIfzajhkJcIVcLVAYPDyA499CI1o5QU8mNgrJN3N_7x6y-FDAz_IJ3WceWqelnR3N-2SPabLr_roNxvEt7_x7jCp8oqrqfasU0yRX8YhaTPzRRt2t8Xi-mjIkGsK-MPBqrdlZNZQ33UKvUCyyU8iHiD4622bQ1BeGiA6wExvcX_QXyzJvP3HoqLK8pzmTphoc-ZFxnFIHbvO0HE17vuxuO3fgVq2xBhQ6f79V8NYMAZxwmt5icsYW71bVKYSZHOXLi2FLYpcYg'
                }
                /* ── Add more CITE buildings here when ready ──
                ,{
                    id:     'cite-annex',
                    wing:   'Annex',
                    icon:   'science',
                    name:   'CITE Annex',
                    desc:   'Description here.',
                    rooms:  0,
                    floors: 0,
                    image:  'assets/images/cite-annex.jpg'
                }
                */
            ]
        }

    };

    /* ══════════════════════════════════════════════════════════════
       BUILDING ROOMS DATA
       Keyed by building id (must match CAMPUS_DATA building.id).
       metrics.total  — total room count
       metrics.occupied   — rooms currently in use  (0 = none / TBD)
       metrics.maintenance — rooms under maintenance (0 = none / TBD)
       floors[].expanded  — true = accordion open on first load
    ══════════════════════════════════════════════════════════════ */
    var BUILDING_ROOMS = {

        'cite-main': {
            name: 'PUP CITE Building',
            metrics: {
                total: 48,
                occupied: 0,
                maintenance: 0
            },
            floors: [
                {
                    label: '1st Floor',
                    expanded: true,
                    rooms: [
                        'Prayer Room',
                        'Audiovisual Room',
                        'Testing Area',
                        'Student Organization Room',
                        'Clinic Room',
                        'Industrial Engineering Room',
                        "Director's Office",
                        'Basketball Court',
                        'Room 103',
                        'Room 105',
                        'Room 106',
                        'Room 116',
                        'Room 118',
                        'Room 119'
                    ]
                },
                {
                    label: '2nd Floor',
                    expanded: false,
                    rooms: [
                        'Admin Office',
                        'Faculty Lounge',
                        'AutoCAD & Multimedia Laboratory',
                        'Computer Laboratory 1',
                        'Computer Laboratory 2',
                        'Computer Laboratory 3',
                        'Ergonomics Room',
                        'Digital Laboratory Room',
                        'Dispensing Room',
                        'Microprocessing Laboratory Room',
                        'Room 203',
                        'Room 210',
                        'Room 212',
                        'Room 218'
                    ]
                },
                {
                    label: '3rd Floor',
                    expanded: false,
                    rooms: [
                        'Library Room',
                        'Library Extension Room',
                        'Physics Room',
                        'Room 301',
                        'Room 302',
                        'Room 303',
                        'Room 304',
                        'Room 305',
                        'Room 307',
                        'Room 308',
                        'Room 309',
                        'Room 310'
                    ]
                },
                {
                    label: '4th Floor',
                    expanded: false,
                    rooms: [
                        'Chemistry Laboratory Room',
                        'Student Lounge',
                        'Room 401',
                        'Room 402',
                        'Room 403',
                        'Room 405',
                        'Room 406',
                        'Room 415'
                    ]
                }
            ]
        }

        ,

        'main-building-a': {
            name: 'Building A (Old)',
            metrics: {
                total: 22,
                occupied: 0,
                maintenance: 0
            },
            floors: [
                {
                    label: '1st Floor',
                    expanded: true,
                    rooms: [
                        'Admin Office',
                        'Registration Office',
                        'OSAS Office',
                        'Office 1',
                        'Clinic',
                        'Staff Room'
                    ]
                },
                {
                    label: '2nd Floor',
                    expanded: false,
                    rooms: [
                        'Room 201',
                        'Room 202',
                        'Room 203',
                        'Room 204',
                        'Room 205'
                    ]
                },
                {
                    label: '3rd Floor',
                    expanded: false,
                    rooms: [
                        'Room 301',
                        'Room 302',
                        'Room 303',
                        'Room 304',
                        'Room 305'
                    ]
                },
                {
                    label: '4th Floor',
                    expanded: false,
                    rooms: [
                        'Org Room',
                        'CSC Room',
                        'AVR 2',
                        'Computer Laboratory 1',
                        'Computer Laboratory 2'
                    ]
                },
                {
                    label: '5th Floor',
                    expanded: false,
                    rooms: [
                        'Chemistry Laboratory'
                    ]
                }
            ]
        }

        ,

        'main-building-b': {
            name: 'Building A (Old)',
            metrics: {
                total: 17,
                occupied: 0,
                maintenance: 0
            },
            floors: [
                {
                    label: '1st Floor',
                    expanded: true,
                    rooms: [
                        'Library',
                        'Directors Office',
                        'Faculty Room',
                        'DO Office',
                        'Guidance Office'
                    ]
                },
                {
                    label: '2nd Floor',
                    expanded: false,
                    rooms: [
                        'Research Room',
                        'Room 202',
                        'Room 203',
                        'Room 204',
                        'Room 205'
                    ]
                },
                {
                    label: '3rd Floor',
                    expanded: false,
                    rooms: [
                        'Room 301',
                        'Room 302',
                        'Room 303',
                        'Room 304',
                        'Room 305'
                    ]
                },
                {
                    label: '4th Floor',
                    expanded: false,
                    rooms: [
                        'Room 401',
                        'Room 402',
                        'AVR 1',
                        'Room 405'
                    ]
                },
            ]
        }

    };

    /* ══════════════════════════════════════════════════════════════
       ROOM SCHEDULE CONSTANTS
       School operating hours used to fill "Vacant" gaps in the
       daily/weekly schedule views.
    ══════════════════════════════════════════════════════════════ */
    var SCHOOL_START_MIN = 7 * 60;   /* 7:00 AM */
    var SCHOOL_END_MIN = 20 * 60;   /* 8:00 PM */

    var DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    var DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /* ══════════════════════════════════════════════════════════════
       ROOM SCHEDULE DATA
       Keyed by the exact room name as it appears in BUILDING_ROOMS.

       capacity — seating capacity (use null until real data is set)
       image    — URL for the modal photo (null = show placeholder)
       week     — { mon:[...], tue:[...], wed:[...], thu:[...],
                     fri:[...], sat:[...], sun:[...] }
                  Each entry: { start: 'HH:MM', end: 'HH:MM', label: '...' }
                  Times are 24-hour "HH:MM", within 07:00–20:00.

       Leave a day's array empty ([]) — or omit the day/room entirely —
       and the schedule views will automatically show "Vacant" /
       "No schedule yet" placeholders, ready for real data later.
    ══════════════════════════════════════════════════════════════ */
    var ROOM_SCHEDULES = {

        'Room 203': {
            capacity: 45,
            image: null,
            week: {
                mon: [
                    { start: '08:30', end: '10:30', label: 'Sir Dennis &mdash; Data Structures' },
                    { start: '14:00', end: '17:00', label: "Ma&rsquo;am Marge &mdash; Networking" }
                ]
                /* tue, wed, thu, fri, sat, sun left blank — ready for weekly data */
            }
        }

        /* ── Add more rooms here as schedule data becomes available ──
        ,
        'Computer Laboratory 1': {
            capacity: 40,
            image:    'assets/images/comlab1.jpg',
            week: {
                mon: [ { start: '07:00', end: '09:00', label: 'Sir Reyes &mdash; CC 102' } ],
                tue: [],
                wed: [],
                thu: [],
                fri: [],
                sat: [],
                sun: []
            }
        }
        */
    };

    /* Fallback used for any room without an entry above */
    var DEFAULT_ROOM_DATA = { capacity: null, image: null, week: {} };

    /* ── Resolve a room's schedule data (with safe fallback) ──────── */
    function getRoomData(roomName) {
        var data = ROOM_SCHEDULES[roomName];
        if (!data) return DEFAULT_ROOM_DATA;
        if (!data.week) data.week = {};
        return data;
    }


    /* ══════════════════════════════════════════════════════════════
       DOM REFERENCES  (resolved in init())
    ══════════════════════════════════════════════════════════════ */

    /* VIEW 1 & 2 */
    var campusView, buildingView;
    var carouselInner, dotsWrap, prevBtn, nextBtn, carouselWrap;
    var breadcrumbBack, breadcrumbCampusLabel, buildingTitle;

    /* VIEW 3 */
    var roomsView;
    var roomsHeroTitle, roomsBreadcrumbBuilding;
    var roomsBackFacilities, roomsBackCampus;
    var roomsMetricsContainer, roomsFloorsContainer;

    /* ROOM DETAILS MODAL */
    var roomModalOverlay, roomModalClose;
    var modalLocation, modalTitle;
    var modalAvailabilityBadge, modalAvailabilityText;
    var modalCapacityValue;
    var modalDayLabel, modalDailyList, modalWeeklyGrid;
    var modalScheduleTabs, modalSchedulePanels;

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL STATE
    ══════════════════════════════════════════════════════════════ */
    var currentSlide = 0;
    var totalSlides = 0;
    var autoSlideTimer = null;
    var activeCampusKey = null;
    var AUTO_SLIDE_MS = 5000;

    /* ── Build slide HTML string ─────────────────────────────────── */
    function buildSlideHTML(building, index) {
        return [
            '<div class="fcty-carousel-slide">',
            '<img',
            ' src="' + building.image + '"',
            ' alt="' + building.name + '"',
            ' loading="' + (index === 0 ? 'eager' : 'lazy') + '"',
            '>',
            '<div class="fcty-slide-overlay"></div>',
            '<div class="fcty-slide-content">',
            '<div class="fcty-slide-badge">',
            '<span class="material-symbols-outlined">' + building.icon + '</span>',
            '<span class="fcty-slide-badge-text">' + building.wing + '</span>',
            '</div>',
            '<h3 class="fcty-slide-title">' + building.name + '</h3>',
            '<p class="fcty-slide-desc">' + building.desc + '</p>',
            '<div class="fcty-slide-meta">',
            '<span class="fcty-meta-chip">' + building.rooms + ' Rooms</span>',
            '<span class="fcty-meta-chip">' + building.floors + ' Floors</span>',
            '</div>',
            '<button class="fcty-slide-btn" data-building-id="' + building.id + '">',
            'Select Building',
            '<span class="material-symbols-outlined">arrow_forward</span>',
            '</button>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ── Build coming-soon placeholder slide ─────────────────────── */
    function buildPlaceholderSlide(campusLabel) {
        return [
            '<div class="fcty-carousel-slide">',
            '<div class="fcty-slide-placeholder">',
            '<span class="material-symbols-outlined">construction</span>',
            '<h3>Buildings Coming Soon</h3>',
            '<p>Floor plans and room reservations for <strong>',
            campusLabel,
            '</strong> are currently being prepared. Check back soon!</p>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ══════════════════════════════════════════════════════════════
       VIEW 3 — ROOMS VIEW RENDERING
    ══════════════════════════════════════════════════════════════ */

    /* ── Single metric card HTML ─────────────────────────────────── */
    function buildMetricCardHTML(icon, value, label) {
        return [
            '<div class="fcty-metric-card">',
            '<div class="fcty-metric-icon">',
            '<span class="material-symbols-outlined">' + icon + '</span>',
            '</div>',
            '<div>',
            '<div class="fcty-metric-value">' + value + '</div>',
            '<div class="fcty-metric-label">' + label + '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ── Single floor accordion HTML ────────────────────────────── */

    /* DEMO-ONLY status pattern — for visual reference only.
       Cycles through the five statuses from the Status Guide so every
       floor shows a mix of colors. Replace with real reservation data
       later by giving each room object in BUILDING_ROOMS a real
       `status` field and reading it here instead of the cycle. */
    var DEMO_ROOM_STATUS_CYCLE = [
        'status-available',
        'status-available',
        'status-unavailable',
        'status-pending',
        'status-maintenance',
        'status-static'
    ];

    function buildFloorAccordionHTML(floor) {
        var chipsHTML = floor.rooms.map(function (room, index) {
            var statusClass = DEMO_ROOM_STATUS_CYCLE[index % DEMO_ROOM_STATUS_CYCLE.length];
            return '<span class="fcty-room-chip ' + statusClass + '">' + room + '</span>';
        }).join('');

        var bodyClass = 'fcty-floor-body' + (floor.expanded ? ' open' : '');
        var chevronClass = 'material-symbols-outlined fcty-floor-chevron' + (floor.expanded ? ' open' : '');

        return [
            '<div class="fcty-floor-accordion" role="listitem">',
            '<button class="fcty-floor-toggle"',
            ' aria-expanded="' + (floor.expanded ? 'true' : 'false') + '"',
            ' aria-controls="fcty-floor-' + floor.label.replace(/\s+/g, '-').toLowerCase() + '">',
            '<span class="fcty-floor-label">' + floor.label + '</span>',
            '<span class="' + chevronClass + '">expand_more</span>',
            '</button>',
            '<div class="' + bodyClass + '"',
            ' id="fcty-floor-' + floor.label.replace(/\s+/g, '-').toLowerCase() + '">',
            '<div class="fcty-floor-body-inner">',
            chipsHTML,
            '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ── Render metrics into the metrics container ───────────────── */
    function renderMetrics(metrics) {
        roomsMetricsContainer.innerHTML = [
            buildMetricCardHTML('meeting_room', metrics.total, 'Total Rooms'),
            buildMetricCardHTML('group', metrics.occupied, 'Occupied'),
            buildMetricCardHTML('build', metrics.maintenance, 'Maintenance')
        ].join('');
    }

    /* ── Render floor accordions ─────────────────────────────────── */
    function renderFloors(floors) {
        roomsFloorsContainer.innerHTML = floors.map(buildFloorAccordionHTML).join('');
    }

    /* ══════════════════════════════════════════════════════════════
       ROOM DETAILS MODAL
    ══════════════════════════════════════════════════════════════ */

    /* ── Time helpers ─────────────────────────────────────────────
       "HH:MM" (24h) <-> minutes since midnight <-> "h:mm AM/PM"     */
    function timeToMinutes(timeStr) {
        var parts = timeStr.split(':');
        return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
    }

    function minutesToLabel(mins) {
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        var period = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) h12 = 12;
        var mm = (m < 10 ? '0' : '') + m;
        return h12 + ':' + mm + ' ' + period;
    }

    /* ── Fill a day's schedule with "Vacant" gaps across school hours ──
       Input:  [{ start:'08:30', end:'10:30', label:'...' }, ...]
       Output: full list of slots (vacant + occupied) covering
               SCHOOL_START_MIN → SCHOOL_END_MIN, sorted by time.      */
    function buildDaySlots(daySchedule) {
        var sorted = (daySchedule || []).slice().sort(function (a, b) {
            return timeToMinutes(a.start) - timeToMinutes(b.start);
        });

        var slots = [];
        var cursor = SCHOOL_START_MIN;

        sorted.forEach(function (entry) {
            var start = timeToMinutes(entry.start);
            var end = timeToMinutes(entry.end);

            if (start > cursor) {
                slots.push({ start: cursor, end: start, vacant: true });
            }
            slots.push({ start: start, end: end, vacant: false, label: entry.label });
            cursor = Math.max(cursor, end);
        });

        if (cursor < SCHOOL_END_MIN) {
            slots.push({ start: cursor, end: SCHOOL_END_MIN, vacant: true });
        }

        /* No entries at all for this day → single all-day vacant slot */
        if (slots.length === 0) {
            slots.push({ start: SCHOOL_START_MIN, end: SCHOOL_END_MIN, vacant: true });
        }

        return slots;
    }

    /* ── Build HTML for one schedule row (daily list) ─────────────── */
    function buildScheduleSlotHTML(slot) {
        var timeLabel = minutesToLabel(slot.start) + ' &ndash; ' + minutesToLabel(slot.end);
        var rowClass = 'fcty-schedule-slot ' + (slot.vacant ? 'vacant' : 'occupied');
        var occupant = slot.vacant ? 'Vacant' : slot.label;

        return [
            '<div class="' + rowClass + '">',
            '<span class="fcty-slot-time">' + timeLabel + '</span>',
            '<span class="fcty-slot-occupant">' + occupant + '</span>',
            '</div>'
        ].join('');
    }

    /* ── Render the "Today's Schedule" list ───────────────────────── */
    function renderDailySchedule(daySchedule) {
        return buildDaySlots(daySchedule).map(buildScheduleSlotHTML).join('');
    }

    /* ── Build one day-column for the weekly grid ─────────────────── */
    function buildWeeklyDayHTML(dayLabel, daySchedule) {
        var bodyHTML;

        if (daySchedule && daySchedule.length) {
            var occupiedSlots = buildDaySlots(daySchedule).filter(function (s) {
                return !s.vacant;
            });

            if (occupiedSlots.length) {
                bodyHTML = occupiedSlots.map(function (slot) {
                    return '<div class="fcty-weekly-slot">' +
                        minutesToLabel(slot.start) + '&ndash;' + minutesToLabel(slot.end) +
                        '<br>' + slot.label +
                        '</div>';
                }).join('');
            } else {
                bodyHTML = '<div class="fcty-weekly-empty">Vacant all day</div>';
            }
        } else {
            /* No data for this day yet — placeholder, ready for future data */
            bodyHTML = '<div class="fcty-weekly-empty">No schedule yet</div>';
        }

        return [
            '<div class="fcty-weekly-day">',
            '<div class="fcty-weekly-day-header">' + dayLabel + '</div>',
            '<div class="fcty-weekly-day-body">' + bodyHTML + '</div>',
            '</div>'
        ].join('');
    }

    /* ── Render the "Weekly Schedule" grid (Sun → Sat) ────────────── */
    function renderWeeklySchedule(weekData) {
        return DAY_KEYS.map(function (key, idx) {
            return buildWeeklyDayHTML(DAY_LABELS[idx].slice(0, 3), weekData[key]);
        }).join('');
    }

    /* ── Availability: is the room free right now? ────────────────
       Compares the current time against today's occupied slots.
       Outside school hours / no entries → always Available.        */
    function isRoomAvailableNow(daySchedule) {
        var now = new Date();
        var nowMin = (now.getHours() * 60) + now.getMinutes();

        var occupiedNow = (daySchedule || []).some(function (entry) {
            return nowMin >= timeToMinutes(entry.start) && nowMin < timeToMinutes(entry.end);
        });

        return !occupiedNow;
    }

    /* ── Switch between "Today" / "Weekly" tabs ───────────────────── */
    function setScheduleTab(tabName) {
        modalScheduleTabs.forEach(function (tab) {
            var isActive = tab.dataset.scheduleTab === tabName;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        modalSchedulePanels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.schedulePanel === tabName);
        });
    }

    /* ── Open the modal for a given room ──────────────────────────── */
    function openRoomModal(roomName, locationLabel) {
        var data = getRoomData(roomName);
        var today = new Date();
        var dayKey = DAY_KEYS[today.getDay()];
        var daySchedule = data.week[dayKey] || [];

        /* Header */
        modalLocation.textContent = locationLabel;
        modalTitle.textContent = roomName;

        /* Availability indicator */
        var available = isRoomAvailableNow(daySchedule);
        modalAvailabilityBadge.className = 'fcty-availability-badge ' + (available ? 'available' : 'occupied');
        modalAvailabilityText.textContent = available ? 'Available' : 'Occupied';

        /* Capacity */
        modalCapacityValue.textContent = (data.capacity !== null && data.capacity !== undefined)
            ? data.capacity
            : '\u2014'; /* em dash placeholder */

        /* Daily schedule */
        modalDayLabel.textContent = 'Today \u2014 ' + DAY_LABELS[today.getDay()];
        modalDailyList.innerHTML = renderDailySchedule(daySchedule);

        /* Weekly schedule (placeholder-ready) */
        modalWeeklyGrid.innerHTML = renderWeeklySchedule(data.week);

        /* Always open on the "Today" tab */
        setScheduleTab('daily');

        /* Show modal + lock everything else out */
        roomModalOverlay.classList.add('open');
        roomModalOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setupFocusTrap();
    }

    /* ── Close the modal ───────────────────────────────────────────── */
    function closeRoomModal() {
        if (!roomModalOverlay.classList.contains('open')) return;
        roomModalOverlay.classList.remove('open');
        roomModalOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        teardownFocusTrap();
    }

    /* ── Focus trap — keeps keyboard navigation inside the modal ───── */
    var _focusTrapHandler = null;
    var _focusTrapLastActiveEl = null; /* restore focus on close */

    var FOCUSABLE_SELECTORS = [
        'a[href]',
        'button:not([disabled])',
        'textarea:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(', ');

    function setupFocusTrap() {
        /* Remember what had focus before the modal opened */
        _focusTrapLastActiveEl = document.activeElement;

        _focusTrapHandler = function (e) {
            if (e.key !== 'Tab') return;

            var modal = roomModalOverlay.querySelector('.fcty-modal');
            var focusable = Array.prototype.slice.call(
                modal.querySelectorAll(FOCUSABLE_SELECTORS)
            ).filter(function (el) {
                return !el.closest('[hidden]') && el.offsetParent !== null;
            });

            if (!focusable.length) { e.preventDefault(); return; }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            var active = document.activeElement;

            if (e.shiftKey) {
                /* Shift+Tab — going backwards */
                if (active === first || !modal.contains(active)) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                /* Tab — going forwards */
                if (active === last || !modal.contains(active)) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        document.addEventListener('keydown', _focusTrapHandler);

        /* Move initial focus to the close button */
        roomModalClose.focus();
    }

    function teardownFocusTrap() {
        if (_focusTrapHandler) {
            document.removeEventListener('keydown', _focusTrapHandler);
            _focusTrapHandler = null;
        }
        /* Return focus to wherever the user was before */
        if (_focusTrapLastActiveEl && typeof _focusTrapLastActiveEl.focus === 'function') {
            _focusTrapLastActiveEl.focus();
            _focusTrapLastActiveEl = null;
        }
    }


    /* ══════════════════════════════════════════════════════════════
       CAROUSEL RENDERING
    ══════════════════════════════════════════════════════════════ */
    function renderCarousel(campusKey) {
        var campus = CAMPUS_DATA[campusKey];
        var buildings = campus.buildings;

        /* Clear previous slides + dots */
        carouselInner.innerHTML = '';
        dotsWrap.innerHTML = '';
        currentSlide = 0;

        /* Snap to position 0 without animation */
        carouselInner.style.transition = 'none';
        carouselInner.style.transform = 'translateX(0%)';
        /* Re-enable CSS transition after the style flush */
        requestAnimationFrame(function () {
            carouselInner.style.transition = '';
        });

        if (!buildings || buildings.length === 0) {
            /* No buildings yet — placeholder slide */
            carouselInner.innerHTML = buildPlaceholderSlide(campus.label);
            dotsWrap.innerHTML = '<button class="fcty-dot active" aria-label="Slide 1" role="tab"></button>';
            totalSlides = 1;
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }

        /* Render each building as a slide + matching dot */
        var slidesHTML = '';
        buildings.forEach(function (building, idx) {
            slidesHTML += buildSlideHTML(building, idx);

            var dot = document.createElement('button');
            dot.className = 'fcty-dot' + (idx === 0 ? ' active' : '');
            dot.setAttribute('aria-label', 'Slide ' + (idx + 1) + ': ' + building.name);
            dot.setAttribute('role', 'tab');
            dot.dataset.idx = idx;
            dot.addEventListener('click', function () {
                goToSlide(parseInt(this.dataset.idx, 10));
                resetAutoSlide();
            });
            dotsWrap.appendChild(dot);
        });

        carouselInner.innerHTML = slidesHTML;
        totalSlides = buildings.length;

        /* Show arrows only when there is more than one slide */
        prevBtn.style.display = totalSlides > 1 ? '' : 'none';
        nextBtn.style.display = totalSlides > 1 ? '' : 'none';
    }

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL NAVIGATION
    ══════════════════════════════════════════════════════════════ */
    function goToSlide(idx) {
        if (totalSlides <= 1) return;
        currentSlide = ((idx % totalSlides) + totalSlides) % totalSlides;
        carouselInner.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';

        dotsWrap.querySelectorAll('.fcty-dot').forEach(function (dot, i) {
            dot.classList.toggle('active', i === currentSlide);
            dot.setAttribute('aria-selected', i === currentSlide ? 'true' : 'false');
        });
    }

    function nextSlide() { goToSlide(currentSlide + 1); }
    function prevSlide() { goToSlide(currentSlide - 1); }

    function startAutoSlide() {
        if (totalSlides <= 1) return;
        clearInterval(autoSlideTimer);
        autoSlideTimer = setInterval(nextSlide, AUTO_SLIDE_MS);
    }

    function resetAutoSlide() {
        clearInterval(autoSlideTimer);
        startAutoSlide();
    }

    function stopAutoSlide() {
        clearInterval(autoSlideTimer);
        autoSlideTimer = null;
    }

    /* ══════════════════════════════════════════════════════════════
       VIEW SWITCHING  — helpers hide all three views first
    ══════════════════════════════════════════════════════════════ */
    function hideAllViews() {
        campusView.style.display = 'none';
        buildingView.style.display = 'none';
        roomsView.style.display = 'none';
    }

    /* VIEW 1 — Campus selection */
    function showCampusView() {
        stopAutoSlide();
        hideAllViews();
        campusView.style.display = '';
        activeCampusKey = null;
    }

    /* VIEW 2 — Building carousel */
    function showBuildingView(campusKey) {
        activeCampusKey = campusKey;
        var campus = CAMPUS_DATA[campusKey];

        /* Update breadcrumb + title */
        breadcrumbCampusLabel.textContent = campus.label;
        buildingTitle.innerHTML = campus.label + ' &mdash; Select Building';

        /* Populate carousel for this campus */
        renderCarousel(campusKey);

        /* Swap views */
        hideAllViews();
        buildingView.style.display = '';

        /* Start auto-advance */
        startAutoSlide();
    }

    /* VIEW 3 — Floor + rooms view */
    function showRoomsView(buildingId, campusKey) {
        var buildingData = BUILDING_ROOMS[buildingId];

        /* If no room data exists yet, fall back gracefully */
        if (!buildingData) {
            console.warn('[PUPSync Facilities] No room data for building:', buildingId);
            return;
        }

        /* Update breadcrumbs */
        roomsBackCampus.textContent = CAMPUS_DATA[campusKey].label;
        roomsBreadcrumbBuilding.textContent = buildingData.name;
        roomsHeroTitle.textContent = buildingData.name;

        /* Populate metrics + floors */
        renderMetrics(buildingData.metrics);
        renderFloors(buildingData.floors);

        /* Swap views */
        stopAutoSlide();
        hideAllViews();
        roomsView.style.display = '';

        /* Scroll the panel back to top smoothly */
        roomsView.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ══════════════════════════════════════════════════════════════
       FLOOR ACCORDION TOGGLE  (event delegation on the floors list)
    ══════════════════════════════════════════════════════════════ */
    function toggleFloorAccordion(toggleBtn) {
        var body = toggleBtn.nextElementSibling;
        var chevron = toggleBtn.querySelector('.fcty-floor-chevron');
        var isOpen = body.classList.contains('open');

        body.classList.toggle('open', !isOpen);
        chevron.classList.toggle('open', !isOpen);
        toggleBtn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    }

    /* ══════════════════════════════════════════════════════════════
       BUILDING SELECTION CALLBACK
       Called when the user clicks "Select Building" in the carousel.
    ══════════════════════════════════════════════════════════════ */
    function onBuildingSelected(buildingId, campusKey) {
        showRoomsView(buildingId, campusKey);
    }

    /* ══════════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════════ */
    function init() {
        /* ── Resolve VIEW 1 & 2 DOM refs ───────────────────────── */
        campusView = document.getElementById('fcty-campus-view');
        buildingView = document.getElementById('fcty-building-view');
        carouselInner = document.getElementById('fcty-carousel-inner');
        dotsWrap = document.getElementById('fcty-carousel-dots');
        prevBtn = document.getElementById('fcty-prev');
        nextBtn = document.getElementById('fcty-next');
        carouselWrap = document.getElementById('fcty-carousel-wrap');
        breadcrumbBack = document.getElementById('fcty-breadcrumb-back');
        breadcrumbCampusLabel = document.getElementById('fcty-breadcrumb-campus');
        buildingTitle = document.getElementById('fcty-building-title');

        /* ── Resolve VIEW 3 DOM refs ────────────────────────────── */
        roomsView = document.getElementById('fcty-rooms-view');
        roomsHeroTitle = document.getElementById('fcty-rooms-hero-title');
        roomsBreadcrumbBuilding = document.getElementById('fcty-rooms-breadcrumb-building');
        roomsBackFacilities = document.getElementById('fcty-rooms-back-facilities');
        roomsBackCampus = document.getElementById('fcty-rooms-back-campus');
        roomsMetricsContainer = document.getElementById('fcty-rooms-metrics');
        roomsFloorsContainer = document.getElementById('fcty-rooms-floors');

        /* ── Resolve ROOM DETAILS MODAL DOM refs ─────────────────── */
        roomModalOverlay = document.getElementById('fcty-room-modal');
        roomModalClose = document.getElementById('fcty-modal-close');
        modalLocation = document.getElementById('fcty-modal-location');
        modalTitle = document.getElementById('fcty-modal-room-name');
        modalAvailabilityBadge = document.getElementById('fcty-modal-availability');
        modalAvailabilityText = document.getElementById('fcty-modal-availability-text');
        modalCapacityValue = document.getElementById('fcty-modal-capacity');
        modalDayLabel = document.getElementById('fcty-modal-day-label');
        modalDailyList = document.getElementById('fcty-modal-daily-list');
        modalWeeklyGrid = document.getElementById('fcty-modal-weekly-grid');
        modalScheduleTabs = roomModalOverlay.querySelectorAll('.fcty-schedule-tab');
        modalSchedulePanels = roomModalOverlay.querySelectorAll('.fcty-schedule-panel');


        /* Guard — element not found means we're on a different page */
        if (!campusView) return;

        /* ── Campus card clicks → VIEW 2 ─────────────────────── */
        document.querySelectorAll('[data-fcty-campus]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.preventDefault();
                showBuildingView(this.dataset.fctyCampus);
            });
        });

        /* ── Breadcrumb back (VIEW 2) → VIEW 1 ──────────────── */
        breadcrumbBack.addEventListener('click', function (e) {
            e.preventDefault();
            showCampusView();
        });

        /* ── VIEW 3 breadcrumb: "Facilities" → VIEW 1 ─────────── */
        roomsBackFacilities.addEventListener('click', function (e) {
            e.preventDefault();
            showCampusView();
        });

        /* ── VIEW 3 breadcrumb: campus label → VIEW 2 ─────────── */
        roomsBackCampus.addEventListener('click', function (e) {
            e.preventDefault();
            if (activeCampusKey) {
                showBuildingView(activeCampusKey);
            } else {
                showCampusView();
            }
        });

        /* ── Carousel arrows ─────────────────────────────────── */
        prevBtn.addEventListener('click', function () {
            prevSlide();
            resetAutoSlide();
        });
        nextBtn.addEventListener('click', function () {
            nextSlide();
            resetAutoSlide();
        });

        /* ── Keyboard nav (arrow keys when carousel is focused) ── */
        carouselWrap.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { prevSlide(); resetAutoSlide(); }
            if (e.key === 'ArrowRight') { nextSlide(); resetAutoSlide(); }
        });

        /* ── Pause auto-slide on hover ───────────────────────── */
        carouselWrap.addEventListener('mouseenter', stopAutoSlide);
        carouselWrap.addEventListener('mouseleave', function () {
            if (activeCampusKey) startAutoSlide();
        });

        /* ── "Select Building" button clicks (event delegation) ─ */
        carouselInner.addEventListener('click', function (e) {
            var btn = e.target.closest('.fcty-slide-btn');
            if (!btn) return;
            onBuildingSelected(btn.dataset.buildingId, activeCampusKey);
        });

        /* ── Floor accordion toggles + room chip clicks (event delegation)
           Handles any click inside #fcty-rooms-floors                       */
        roomsFloorsContainer.addEventListener('click', function (e) {
            /* Room chip → open room details modal */
            var chip = e.target.closest('.fcty-room-chip');
            if (chip) {
                /* "Not Bookable" rooms aren't reservable — no modal */
                if (chip.classList.contains('status-static')) return;

                var floorEl = chip.closest('.fcty-floor-accordion');
                var floorLabel = floorEl ? floorEl.querySelector('.fcty-floor-label').textContent : '';
                var buildingName = roomsHeroTitle.textContent.trim();
                var locationLabel = buildingName + (floorLabel ? ' \u00b7 ' + floorLabel : '');

                openRoomModal(chip.textContent.trim(), locationLabel);
                return;
            }

            /* Otherwise, toggle floor accordion */
            var toggle = e.target.closest('.fcty-floor-toggle');
            if (!toggle) return;
            toggleFloorAccordion(toggle);
        });

        /* ── Room details modal: close interactions ─────────────────── */
        /* Backdrop click intentionally does NOT close the modal.
           The user must click ✕, Reserve, or Report to proceed.  */
        roomModalClose.addEventListener('click', closeRoomModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && roomModalOverlay.classList.contains('open')) {
                closeRoomModal();
            }
        });

        /* ── Room details modal: schedule tab switching ─────────────── */
        modalScheduleTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setScheduleTab(this.dataset.scheduleTab);
            });
        });

        /* ── Reserve / Report buttons — UI only, not wired up yet ────── */
        /* modalReserveBtn / modalReportBtn intentionally have no
           click handlers for now. */

        /* ── Reset to campus view when Facilities tab loses focus ── */
        var panelRooms = document.getElementById('panel-rooms');
        if (panelRooms && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.type === 'attributes' && m.attributeName === 'class') {
                        if (!panelRooms.classList.contains('active')) {
                            /* Panel deactivated — silently reset without animation */
                            stopAutoSlide();
                            hideAllViews();
                            closeRoomModal();
                            campusView.style.display = '';
                            activeCampusKey = null;
                        }
                    }
                });
            });
            observer.observe(panelRooms, { attributes: true });
        }
    }

    /* Run after DOM is ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());