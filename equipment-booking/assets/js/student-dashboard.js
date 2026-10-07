/* ================================================================
   student-dashboard.js
   Portal-only version: Screen A only.

   Flow:
     Choice card → facultyCodeModal → Step 1 (verify code)
       → Step 2 Borrow (equipment form)   → Step 3 (receipt + QR)
       → Step 2 Room   (reservation form) → Step 3 (result)

   No Screen B dashboard. No sidebar. No equipment grid.
================================================================ */

/* ── Shared HTML escape helpers ──────────────────────────────── */
function escHTML(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function escAttr(str) {
    return String(str ?? '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ================================================================
   INIT — runs on DOMContentLoaded
   Always shows Screen A and initialises the portal.
================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    initPortal();
});

/* ================================================================
   PORTAL  (#screen-portal)
================================================================ */
function initPortal() {

    const facultyCodeModal = document.getElementById('facultyCodeModal');
    if (!facultyCodeModal) return;

    let _verified = null;
    let _lastReceipt = null;
    let _openingReceipt = false;

    /* Restore receipt banner if one was saved earlier this session */
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

    /* ── Error helpers ─────────────────────────────────────────── */
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

    /* ── Modal show event: read data-action from the card ──────── */
    facultyCodeModal.addEventListener('show.bs.modal', function (event) {
        if (_openingReceipt && _lastReceipt) {
            _openingReceipt = false;
            showStep3(_lastReceipt);
            return;
        }
        _openingReceipt = false;
        const card = event.relatedTarget;
        const action = card?.getAttribute('data-action') || 'borrow';
        showStep1(action);
    });

    /* ── STEP 1: verify faculty code ───────────────────────────── */
    function showStep1(actionType) {
        // Default to 'borrow' when called without an argument (e.g. Back button from step 2)
        // but preserve the existing actionType when it's already set.
        if (actionType === undefined) {
            const existing = document.getElementById('actionType');
            actionType = existing?.value || 'borrow';
        }
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
            <input type="hidden" id="actionType" value="${escAttr(actionType)}">`;

        document.querySelector('.modal-footer').innerHTML = `
            <button type="button" class="btn" data-bs-dismiss="modal"
                style="padding:10px 24px;border-radius:12px;border:1px solid var(--color-outline-variant);color:var(--color-secondary);font-weight:600;background:transparent;">
                Cancel
            </button>
            <button type="button" class="btn" id="btnVerifyCode"
                style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>
                Verify &amp; Continue
            </button>`;

        document.getElementById('btnVerifyCode').addEventListener('click', handleVerify);
    }


    /* ── STEP 2 (Borrow): equipment form ────────────────────────── */
    function showStep2(data) {
        clearPortalError();
        const inventoryOptions = data.inventory && data.inventory.length
            ? data.inventory.map(i =>
                `<option value="${escAttr(i.item_name)}">${escHTML(i.item_name)} — ${escHTML(i.category)} (${i.quantity} available)</option>`
            ).join('')
            : '<option disabled>No equipment available</option>';

        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

        document.querySelector('.modal-header').innerHTML = `
            <div>
                <h5 class="modal-title" style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.2rem;margin-bottom:4px;">Borrow Equipment</h5>
                <p style="color:rgba(255,255,255,.8);margin:0;font-size:.85rem;">Authorized by: <strong>${escHTML(data.faculty_name)}</strong></p>
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


    /* ── STEP 3 (Borrow): receipt + QR ─────────────────────────── */
    function showStep3(receipt) {
        const base = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
        const returnUrl = base + 'return_confirm.php?token=' + encodeURIComponent(receipt.return_token || '');

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
                    Show this QR to the admin to <strong>claim</strong> and again when <strong>returning</strong>.
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
            QRCode.toCanvas(canvasEl, url, { width: 160, margin: 2, color: { dark: '#800000', light: '#ffffff' } });
        }
        if (window._qrStudentLoaded) { doRender(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js';
        s.onload = () => { window._qrStudentLoaded = true; doRender(); };
        document.head.appendChild(s);
    }


    /* ── Verify handler ─────────────────────────────────────────── */
    function handleVerify() {
        clearPortalError();
        const code = (document.getElementById('facultyCode')?.value || '').trim();
        const name = (document.getElementById('studentName')?.value || '').trim();
        const id = (document.getElementById('studentId')?.value || '').trim();
        const actionType = (document.getElementById('actionType')?.value || 'borrow').trim();

        if (!code) { showPortalError('Please enter the faculty code.'); return; }
        if (!name) { showPortalError('Please enter your name.'); return; }
        if (!id) { showPortalError('Please enter your Student ID.'); return; }

        const btn = document.getElementById('btnVerifyCode');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Verifying…';

        const verifyUrl = actionType === 'room'
            ? 'room-reservation/api/verify-room-code.php'
            : 'equipment-booking/api/verify-faculty-code.php';

        fetch(verifyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code, student_name: name, student_id: id }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>Verify &amp; Continue';
                    showPortalError(data.error);
                    return;
                }
                _verified = { ...data, student_name: name, student_id: id };
                if (actionType === 'room') {
                    showStep2Room(_verified);
                } else {
                    showStep2(_verified);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>Verify &amp; Continue';
                showPortalError('Network error. Please try again.');
            });
    }

    /* ── Submit borrow ──────────────────────────────────────────── */
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
        if (ret <= borrow) { showPortalError('Return date must be after the borrow date.'); return; }

        const btn = document.getElementById('btnSubmitBorrow');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Submitting…';

        fetch('equipment-booking/api/submit-student-borrow.php', {
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


    /* ── STEP 2 (Room): reservation form ───────────────────────── */
    function showStep2Room(data) {
        clearPortalError();
        const today = new Date().toISOString().split('T')[0];

        document.querySelector('.modal-header').innerHTML = `
        <div>
            <h5 class="modal-title" style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.2rem;margin-bottom:4px;">Reserve a Room</h5>
            <p style="color:rgba(255,255,255,.8);margin:0;font-size:.85rem;">Authorized by: <strong>${escHTML(data.faculty_name)}</strong></p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>`;

        document.querySelector('.modal-body').innerHTML = `
        <div class="mb-3" style="position:relative;">
            <label class="form-label" style="font-weight:600;font-size:.875rem;">Room <span style="color:#c62828;">*</span></label>
            <input type="text" id="reserveRoomSearch" autocomplete="off" placeholder="Type to search rooms…"
                class="form-control"
                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            <input type="hidden" id="reserveRoomId">
            <div id="reserveRoomDropdown"
                style="display:none;position:absolute;left:0;right:0;max-height:220px;overflow-y:auto;
                       background:var(--color-surface-container,#fff);border:1px solid var(--color-outline-variant,#ccc);
                       border-radius:0 0 10px 10px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.12);">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" style="font-weight:600;font-size:.875rem;">Date <span style="color:#c62828;">*</span></label>
            <input type="date" class="form-control" id="reserveDate" min="${today}"
                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
        </div>
        <div class="row g-2 mb-3">
            <div class="col">
                <label class="form-label" style="font-weight:600;font-size:.875rem;">Start Time <span style="color:#c62828;">*</span></label>
                <input type="time" class="form-control" id="reserveStart" min="07:00" max="20:00"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            </div>
            <div class="col">
                <label class="form-label" style="font-weight:600;font-size:.875rem;">End Time <span style="color:#c62828;">*</span></label>
                <input type="time" class="form-control" id="reserveEnd" min="07:00" max="20:00"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" style="font-weight:600;font-size:.875rem;">Purpose <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-control" id="reservePurpose" placeholder="e.g. Club meeting, review session"
                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
        </div>
        <div class="row g-2 mb-3">
            <div class="col-5">
                <label class="form-label" style="font-weight:600;font-size:.875rem;">Attendees</label>
                <input type="number" class="form-control" id="reserveAttendees" value="1" min="1"
                    style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
            </div>
        </div>`;

        document.querySelector('.modal-footer').innerHTML = `
        <button type="button" id="btnBackToStep1"
            style="padding:10px 20px;border-radius:12px;border:1px solid var(--color-outline-variant);color:var(--color-secondary);font-weight:600;background:transparent;cursor:pointer;">
            ← Back
        </button>
        <button type="button" id="btnSubmitRoom"
            style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;cursor:pointer;">
            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">event_available</span>
            Reserve Room
        </button>`;

        document.getElementById('btnBackToStep1').addEventListener('click', showStep1);
        document.getElementById('btnSubmitRoom').addEventListener('click', handleSubmitRoom);

        // Wire up the combobox
        setupRoomCombobox(data.rooms || []);
    }

    function setupRoomCombobox(rooms) {
        const searchEl = document.getElementById('reserveRoomSearch');
        const hiddenEl = document.getElementById('reserveRoomId');
        const dropdownEl = document.getElementById('reserveRoomDropdown');
        if (!searchEl || !hiddenEl || !dropdownEl) return;

        function renderOptions(query) {
            const q = query.trim().toLowerCase();
            const filtered = q
                ? rooms.filter(r =>
                    r.room_name.toLowerCase().includes(q) ||
                    r.building_name.toLowerCase().includes(q) ||
                    r.floor_label.toLowerCase().includes(q) ||
                    r.campus_name.toLowerCase().includes(q))
                : rooms;

            if (!filtered.length) {
                dropdownEl.innerHTML = `<div style="padding:10px 14px;font-size:.85rem;color:#999;">No rooms match "${escHTML(query)}"</div>`;
            } else {
                dropdownEl.innerHTML = filtered.map(r => `
                <div class="room-option" data-id="${escAttr(String(r.room_id))}"
                    data-label="${escAttr(r.campus_name + ' › ' + r.building_name + ' — ' + r.room_name + ' (' + r.floor_label + ')')}"
                    style="padding:9px 14px;cursor:pointer;font-size:.85rem;border-bottom:1px solid var(--color-outline-variant,#eee);line-height:1.4;">
                    <strong>${escHTML(r.room_name)}</strong>
                    <span style="color:#888;font-size:.78rem;margin-left:6px;">${escHTML(r.building_name)} · ${escHTML(r.floor_label)}</span>
                </div>`).join('');
            }
            dropdownEl.style.display = 'block';
        }

        searchEl.addEventListener('input', function () {
            hiddenEl.value = '';   // clear selection when user types
            renderOptions(this.value);
        });

        searchEl.addEventListener('focus', function () {
            renderOptions(this.value);
        });

        dropdownEl.addEventListener('mousedown', function (e) {
            const opt = e.target.closest('.room-option');
            if (!opt) return;
            e.preventDefault();   // prevent input blur from hiding dropdown first
            hiddenEl.value = opt.dataset.id;
            searchEl.value = opt.dataset.label;
            dropdownEl.style.display = 'none';
        });

        document.addEventListener('click', function hideOnOutsideClick(e) {
            if (!searchEl.contains(e.target) && !dropdownEl.contains(e.target)) {
                dropdownEl.style.display = 'none';
                // if the user left the field without selecting a valid option, clear both
                if (!hiddenEl.value) searchEl.value = '';
            }
        }, { capture: true, once: false });
    }



    /* ── STEP 3 (Room): result ──────────────────────────────────── */
    function showStep3Room(result, formData) {
        const isApproved = result.status === 'Approved';
        const iconColor = isApproved ? '#2e7d32' : '#c62828';
        const iconName = isApproved ? 'check_circle' : 'cancel';
        const statusText = isApproved ? 'Reservation Approved!' : 'Reservation Declined';

        document.querySelector('.modal-header').innerHTML = `
            <div>
                <h5 class="modal-title" style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.2rem;margin-bottom:4px;">${escHTML(statusText)}</h5>
                <p style="color:rgba(255,255,255,.8);margin:0;font-size:.85rem;">Room reservation #${escHTML(String(result.reservation_id))}</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>`;

        document.querySelector('.modal-body').innerHTML = `
            <div style="text-align:center;padding:12px 0 16px;">
                <span class="material-symbols-outlined" style="font-size:44px;color:${iconColor};display:block;margin-bottom:8px;">${iconName}</span>
                <p style="font-size:.95rem;font-weight:700;margin-bottom:2px;">${escHTML(statusText)}</p>
                ${result.reason ? `<p style="font-size:.82rem;color:#888;margin-bottom:0;">${escHTML(result.reason)}</p>` : ''}
            </div>
            <div style="background:#f9f5f5;border-radius:14px;padding:14px 16px;font-size:.82rem;line-height:2;">
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Student</span><strong>${escHTML(formData.student_name)} · ${escHTML(formData.student_id)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Authorized by</span><strong>${escHTML(formData.faculty_name)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Room</span><strong>${escHTML(result.room_name)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Date</span><strong>${escHTML(formData.reservation_date)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Time</span><strong>${escHTML(formData.start_time)} – ${escHTML(formData.end_time)}</strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Purpose</span><strong>${escHTML(formData.purpose)}</strong></div>
            </div>`;

        document.querySelector('.modal-footer').innerHTML = `
            <button type="button" class="btn" data-bs-dismiss="modal"
                style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;width:100%;">
                Done
            </button>`;
    }

    /* ── Submit room reservation ────────────────────────────────── */
    function handleSubmitRoom(verifiedData) {
        clearPortalError();
        if (!_verified) { showPortalError('Session expired. Please go back and verify again.'); return; }

        const roomIdStr = (document.getElementById('reserveRoomId')?.value || '').trim();
        const date = (document.getElementById('reserveDate')?.value || '').trim();
        const start = (document.getElementById('reserveStart')?.value || '').trim();
        const end = (document.getElementById('reserveEnd')?.value || '').trim();
        const purpose = (document.getElementById('reservePurpose')?.value || '').trim();
        const attendees = parseInt(document.getElementById('reserveAttendees')?.value || '1', 10);

        if (!roomIdStr) { showPortalError('Please select a room.'); return; }
        if (!date) { showPortalError('Please select a date.'); return; }
        if (!start) { showPortalError('Please select a start time.'); return; }
        if (!end) { showPortalError('Please select an end time.'); return; }
        if (end <= start) { showPortalError('End time must be after start time.'); return; }
        if (!purpose) { showPortalError('Please enter the purpose.'); return; }

        const btn = document.getElementById('btnSubmitRoom');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Submitting…'; }

        /* Capture student info before nulling _verified */
        const studentName = _verified.student_name;
        const studentId = _verified.student_id;
        const facultyName = _verified.faculty_name;

        fetch('room-reservation/api/submit-student-reserve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code_db_id: _verified.code_db_id,
                faculty_id: _verified.faculty_id,
                faculty_name: _verified.faculty_name,
                student_name: _verified.student_name,
                student_id: _verified.student_id,
                room_id: parseInt(roomIdStr, 10),
                reservation_date: date,
                start_time: start,
                end_time: end,
                purpose,
                attendees,
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">event_available</span>Reserve Room'; }
                if (data.error) { showPortalError(data.error); return; }
                _verified = null;
                showStep3Room(data, {
                    student_name: studentName,
                    student_id: studentId,
                    faculty_name: facultyName,
                    reservation_date: date,
                    start_time: start,
                    end_time: end,
                    purpose,
                });
            })
            .catch(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">event_available</span>Reserve Room'; }
                showPortalError('Network error. Please try again.');
            });
    }

} // end initPortal
