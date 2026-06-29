/* ==========================================================================
   admin-live-render.js
   Real-time re-rendering for the admin dashboard.
   Polls ajax/poll-requests-data.php every 8 s and surgically updates
   only the DOM nodes that changed — zero page reloads, zero tab resets.
   
   HOW TO USE:
   Add this script tag at the bottom of admin-dashboard.php, AFTER
   admin-dashboard.js:
       <script src="admin-live-render.js"></script>
========================================================================== */

(function () {
    'use strict';

    const INTERVAL = 8000; // poll every 8 seconds
    let lastReturnTimestamp = null; // tracks last seen returned_at value

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    function esc(str) {
        // Safely escape HTML — mirrors PHP htmlspecialchars
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtDate(dateStr) {
        // "2025-06-01" → "Jun 01, 2025"
        if (!dateStr) return '—';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    function isPast(dateStr, today) {
        return dateStr < today;
    }

    function showAdminToast(msg) {
        // Reuse the existing app-toast element already in the page
        const t = document.getElementById('app-toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3500);
    }

    /* ── Row builders — produce the exact same HTML as admin-dashboard.php ─ */

    function buildOverrideBtn(id, status, equipment, borrower) {
        return `
        <button class="btn-action btn-override-req" data-action="open-override"
            data-request-id="${esc(id)}"
            data-request-status="${esc(status)}"
            data-equipment="${esc(equipment)}"
            data-borrower="${esc(borrower)}"
            title="Override this request">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round" width="14" height="14">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
        </button>`;
    }

    function buildEmptyRow(colspan, message, icon) {
        const svg = icon === 'clock'
            ? `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="40" height="40" style="display:block;margin:0 auto 10px;opacity:0.3;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`
            : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="40" height="40" style="display:block;margin:0 auto 10px;opacity:0.3;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>`;
        return `<tr><td colspan="${colspan}" class="text-muted" style="text-align:center;padding:3rem;">${svg}${esc(message)}</td></tr>`;
    }

    /* ── waiting-body ────────────────────────────────────────────────────── */
    function renderWaitingBody(rows, today) {
        if (!rows.length) return buildEmptyRow(7, 'No pending requests.', 'clock');

        return rows.map(r => {
            const past = isPast(r.borrow_date, today);
            const dateStyle = past ? 'color:var(--danger);font-weight:600;' : '';
            const pastLabel = past ? '<br><small style="font-size:0.68rem;">(Date Passed)</small>' : '';
            return `
            <tr>
                <td>${esc(r.faculty_id)}</td>
                <td class="fw-bold">${esc(r.faculty_name)}</td>
                <td>${esc(r.equipment_name)}</td>
                <td style="${dateStyle}">${fmtDate(r.borrow_date)}${pastLabel}</td>
                <td>${fmtDate(r.return_date)}</td>
                <td><span class="status-pill pill-waiting">Pending</span></td>
                <td>${buildOverrideBtn(r.id, 'Waiting', r.equipment_name, r.faculty_name)}</td>
            </tr>`;
        }).join('');
    }

    /* ── return-body (Approved + Overdue items awaiting return) ─────────── */
    function renderReturnBody(rows, today) {
        if (!rows.length) return buildEmptyRow(8, 'No items awaiting return confirmation.', 'return');

        return rows.map(r => {
            const overdue = isPast(r.return_date, today);
            const actualStatus = overdue ? 'Overdue' : 'Approved';
            const dueDateStyle = overdue ? 'color:var(--danger);font-weight:600;' : '';
            const overdueLabel = overdue ? '<br><small style="font-size:0.68rem;">(Overdue)</small>' : '';
            const pillClass = overdue ? 'pill-overdue' : 'pill-approved';
            const pillText = overdue ? 'Overdue' : 'Out on Loan';
            const confirmMsg = `Confirm that ${r.faculty_name.replace(/'/g, "\\'")} has returned the ${r.equipment_name.replace(/'/g, "\\'")}?`;

            return `
            <tr>
                <td>${esc(r.faculty_id)}</td>
                <td class="fw-bold">${esc(r.faculty_name)}</td>
                <td>${esc(r.equipment_name)}</td>
                <td>${fmtDate(r.borrow_date)}</td>
                <td style="${dueDateStyle}">${fmtDate(r.return_date)}${overdueLabel}</td>
                <td><span class="status-pill ${pillClass}">${pillText}</span></td>
                <td class="action-cell">
                    <div class="action-btns">
                        <a href="admin-dashboard.php?action=return_confirm&id=${esc(r.id)}"
                            class="btn-return-confirm"
                            title="Confirm item has been returned"
                            onclick="return confirm('${confirmMsg}')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                stroke-linejoin="round" width="13" height="13">
                                <polyline points="1 4 1 10 7 10"/>
                                <path d="M3.51 15a9 9 0 1 0 .49-3.51"/>
                            </svg>
                            Returned
                        </a>
                    </div>
                </td>
                <td>${buildOverrideBtn(r.id, actualStatus, r.equipment_name, r.faculty_name)}</td>
            </tr>`;
        }).join('');
    }

    /* ── approved-list (Borrow History > Approved tab) ───────────────────── */
    function renderApprovedList(rows, today) {
        if (!rows.length) return buildEmptyRow(7, 'No approved requests.', 'return');

        return rows.map(r => `
            <tr>
                <td>${esc(r.faculty_id)}</td>
                <td class="fw-bold">${esc(r.faculty_name)}</td>
                <td>${esc(r.equipment_name)}</td>
                <td>${fmtDate(r.borrow_date)}</td>
                <td>${fmtDate(r.return_date)}</td>
                <td><span class="status-pill pill-approved">Approved</span></td>
                <td>${buildOverrideBtn(r.id, 'Approved', r.equipment_name, r.faculty_name)}</td>
            </tr>`
        ).join('');
    }

    /* ── declined-list ───────────────────────────────────────────────────── */
    function renderDeclinedList(rows) {
        if (!rows.length) return buildEmptyRow(8, 'No declined requests.', 'return');

        return rows.map(r => `
            <tr>
                <td>${esc(r.faculty_id)}</td>
                <td class="fw-bold">${esc(r.faculty_name)}</td>
                <td>${esc(r.equipment_name)}</td>
                <td>${fmtDate(r.borrow_date)}</td>
                <td>${fmtDate(r.return_date)}</td>
                <td><span class="status-pill pill-declined">Declined</span></td>
                <td class="text-muted" style="font-size:0.78rem;">${esc(r.reason ?? '—')}</td>
                <td>${buildOverrideBtn(r.id, 'Declined', r.equipment_name, r.faculty_name)}</td>
            </tr>`
        ).join('');
    }

    /* ── stat card values ────────────────────────────────────────────────── */
    function renderStats(stats) {
        // Stat values are plain text inside .stat-value divs.
        // We query them by their position within .stat-card and by the
        // adjacent .stat-label text — no IDs needed.
        document.querySelectorAll('.stat-card').forEach(card => {
            const label = (card.querySelector('.stat-label') || {}).textContent?.trim();
            const val = card.querySelector('.stat-value');
            if (!val) return;

            const map = {
                'Pending': stats.waiting,
                'Approved': stats.approved,
                'Overdue': stats.overdue,
                'Inventory Items': stats.inv_total,
                'Low Stock': stats.inv_low,
                'Total Requests': stats.total_req,
            };

            if (label in map && val.textContent.trim() !== String(map[label])) {
                // Briefly highlight the card to show it updated
                val.textContent = map[label];
                card.style.transition = 'box-shadow 0.3s';
                card.style.boxShadow = '0 0 0 2px var(--accent-maroon)';
                setTimeout(() => card.style.boxShadow = '', 1200);
            }
        });

        // Also update the "Pending Requests" badge in the Quick Actions panel
        const qaBadge = document.querySelector('.qa-btn[data-lending="waiting"] span');
        if (qaBadge) qaBadge.textContent = stats.waiting;
    }

    /* ── recent activity feed ────────────────────────────────────────────── */
    function renderActivity(activity) {
        const container = document.querySelector('.activity-container');
        if (!container) return;

        const dotMap = {
            Approved: 'dot-approved',
            Declined: 'dot-declined',
            Overdue: 'dot-overdue',
            Waiting: 'dot-waiting',
            Returned: 'dot-returned',
        };

        const items = activity.map(r => {
            const dotClass = dotMap[r.status] || 'dot-waiting';
            const dateLabel = new Date(r.request_date + 'T00:00:00')
                .toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            return `
            <div class="activity-item">
                <div class="activity-dot ${dotClass}"></div>
                <div class="activity-info">
                    <h4>${esc(r.faculty_name)}</h4>
                    <p>${esc(r.equipment_name)} &mdash; ${esc(r.status)}</p>
                </div>
                <span class="activity-time">${dateLabel}</span>
            </div>`;
        }).join('');

        // Replace only the activity items, keep the heading
        const heading = container.querySelector('h3');
        container.innerHTML = '';
        if (heading) container.appendChild(heading);
        container.insertAdjacentHTML('beforeend', items || '<p style="color:var(--text-light);font-size:0.83rem;text-align:center;padding:1rem;">No activity yet.</p>');
    }

    /* ── Master render — called every poll cycle if data changed ─────────── */
    function applyUpdate(data) {
        const today = data.today;

        // 1. Update all four table bodies
        const waitingBody = document.getElementById('waiting-body');
        const returnBody = document.getElementById('return-body');
        const approvedList = document.getElementById('approved-list');
        const declinedList = document.getElementById('declined-list');

        if (waitingBody) waitingBody.innerHTML = renderWaitingBody(data.waiting, today);
        if (returnBody) returnBody.innerHTML = renderReturnBody(data.approved, today);
        if (approvedList) approvedList.innerHTML = renderApprovedList(data.approved, today);
        if (declinedList) declinedList.innerHTML = renderDeclinedList(data.declined);

        // 2. Update stat cards and quick-action badge
        renderStats(data.stats);

        // 3. Update the recent activity feed on the dashboard panel
        renderActivity(data.activity);
    }

    /* ── Polling loop ────────────────────────────────────────────────────── */
    function startPolling() {
        function doPoll() {
            fetch('api/poll-requests-data.php', {
                method: 'GET',
                credentials: 'same-origin'
            })
                .then(function (r) {
                    if (r.status === 401) return null;
                    if (!r.ok) return null;
                    return r.json();
                })
                .then(function (data) {
                    if (!data || data.error) return;

                    const prev = window._adminLastStats || {};
                    const curr = data.stats;

                    applyUpdate(data);

                    if (prev.waiting !== undefined && curr.waiting > prev.waiting) {
                        showAdminToast('🔔 A new borrow request was submitted.');
                    }
                    if (prev.approved !== undefined && curr.approved < prev.approved) {
                        showAdminToast('✅ An item was returned. Tables updated.');
                    }

                    window._adminLastStats = curr;
                })
                .catch(function () {});
        }

        doPoll();              // ← fire immediately on page load
        setInterval(doPoll, INTERVAL);  // ← then every 8 seconds
    }

    // Kick off after the page is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }

})();