/* ================================================================
   MODAL
================================================================ */
const overlay = document.getElementById('authModal');
let isMin = false;

/**
 * Opens the auth modal.
 * @param {string|null} role  'student' (faculty login/register — see legacy
 *                             id="studentSection"), 'admin', or null/undefined
 *                             to land on the generic role selector.
 */
function openModal(role) {
    overlay.classList.remove('minimized');
    isMin = false;
    updateMinimizeIcon();
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    if (role) {
        selectRole(role);
    } else {
        showRoleSelector();
    }
}

function closeModal() {
    overlay.classList.remove('open', 'minimized');
    isMin = false;
    document.body.style.overflow = '';

    // Reset to role selector when closing
    showRoleSelector();
}

function toggleMinimize() {
    isMin = !isMin;
    overlay.classList.toggle('minimized', isMin);
    updateMinimizeIcon();

    if (isMin) {
        document.body.style.overflow = ''; // restore scrolling
    } else {
        document.body.style.overflow = 'hidden'; // lock again when restored
    }
}

function updateMinimizeIcon() {
    const btn = document.getElementById('minimizeBtn');
    btn.querySelector('i').className = isMin ?
        'fa-solid fa-up-right-and-down-left-from-center' :
        'fa-solid fa-minus';
    btn.title = isMin ? 'Restore' : 'Minimize';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (isMin) {
            isMin = false;
            overlay.classList.remove('minimized');
            updateMinimizeIcon();
        } else closeModal();
    }
});


/* ================================================================
   ROLE SELECTOR & NAVIGATION (inside the modal)
================================================================ */
function showRoleSelector() {
    // Hide all auth sections
    document.querySelectorAll('.auth-section').forEach(section => {
        section.classList.remove('active');
    });

    // Show role selector
    const roleSelector = document.getElementById('roleSelector');
    if (roleSelector) {
        roleSelector.classList.add('active');
    }
}

function selectRole(role) {
    // Hide role selector
    const roleSelector = document.getElementById('roleSelector');
    if (roleSelector) {
        roleSelector.classList.remove('active');
    }

    // Show appropriate auth section
    const sections = {
        'student': 'studentSection',
        'faculty': 'facultySection',
        'admin': 'adminSection'
    };

    const sectionId = sections[role];
    if (sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.classList.add('active');
        }
    }

    // Keep the modal handle label in sync with the active portal
    const label = document.querySelector('.modal-handle-label');
    if (label) {
        label.textContent = role === 'admin' ? 'Admin Portal' : 'Faculty Portal';
    }
}

function backToRoleSelector() {
    // Hide all auth sections
    document.querySelectorAll('.auth-section').forEach(section => {
        section.classList.remove('active');
    });

    // Show role selector
    showRoleSelector();
}

function switchStudentTab(tab) {
    // Remove active from all student tabs
    document.querySelectorAll('#studentSection .auth-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Remove active from all student panes
    document.querySelectorAll('#studentSection .auth-pane').forEach(pane => {
        pane.classList.remove('active');
    });

    // Activate selected tab and pane
    if (tab === 'login') {
        document.getElementById('student-tab-login').classList.add('active');
        document.getElementById('studentLogin').classList.add('active');
    } else if (tab === 'register') {
        document.getElementById('student-tab-register').classList.add('active');
        document.getElementById('studentRegister').classList.add('active');
    }
}

function showContactAdminModal() {
    alert('To request a faculty account, please contact:\n\nEmail: admin@pup.edu.ph\nOffice: PUP Biñan Admin Office\n\nPlease include:\n- Your full name\n- Department\n- Contact information');
}


/* ================================================================
   TAB SWITCHER (LEGACY - kept for compatibility)
================================================================ */
function switchTab(tab) {
    // This is kept for backwards compatibility but now redirects to student section
    selectRole('student');
    if (tab === 'login') {
        switchStudentTab('login');
    } else if (tab === 'register') {
        switchStudentTab('register');
    }
}


/* ================================================================
   INPUT VALIDATORS
================================================================ */
function validateLettersName(input) {
    if (input.value.length > 0 && !/^[a-zA-Z]/.test(input.value)) {
        input.value = '';
        return;
    }
    input.value = input.value.replace(/[^a-zA-Z\s.']/g, '');
}

function validateLettersStudentID(input) {
    let val = input.value.toUpperCase().replace(/[^0-9A-Z-]/g, '');
    let result = '';
    for (let i = 0; i < val.length && i < 17; i++) {
        let c = val[i];
        if (i < 4) {
            if (!/[0-9]/.test(c)) continue;
            if (i === 0 && c !== '2') continue;
            if (i === 1 && result[0] === '2' && c !== '0') continue;
            if (i === 2 && result === '20' && !/[0-3]/.test(c)) continue;
            if (i === 3 && result === '203' && c !== '0') continue;
            result += c;
        } else if (i === 4) {
            if (c === '-') result += c;
        } else if (i < 10) {
            if (/[0-9]/.test(c)) result += c;
        } else if (i === 10) {
            if (c === '-') result += c;
        } else if (i === 11) {
            if (c === 'B') result += c;
        } else if (i === 12) {
            if (c === 'N') result += c;
        } else if (i === 13) {
            if (c === '-') result += c;
        } else if (i === 14) {
            if (/[0-9]/.test(c)) result += c;
        }
    }
    input.value = result;
}

function validateLettersEmail(input) {
    if (input.value.length > 0 && !/^[a-zA-Z0-9]/.test(input.value)) {
        input.value = '';
        return;
    }
    input.value = input.value.replace(/[^a-zA-Z0-9.@_-]/g, '');
}


/* ================================================================
   AUTO-DISMISS ALERTS
================================================================ */
setTimeout(() => {
    document.querySelectorAll('.auth-alert').forEach(el => {
        el.style.transition = 'opacity 0.5s, max-height 0.4s, margin 0.4s';
        el.style.opacity = '0';
        el.style.maxHeight = '0';
        el.style.overflow = 'hidden';
        el.style.marginBottom = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);


/* ================================================================
   PASSWORD VISIBILITY TOGGLE
================================================================ */
function toggleEye(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        /* Currently hidden → reveal */
        input.type = 'text';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.setAttribute('aria-label', 'Hide password');
    } else {
        /* Currently visible → hide */
        input.type = 'password';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.setAttribute('aria-label', 'Show password');
    }
}

/* ================================================================
   EVENT LISTENER WIRING
   Replaces all inline onclick/oninput removed from HTML to comply
   with Content-Security-Policy (no unsafe-inline).
================================================================ */
document.addEventListener('DOMContentLoaded', function () {

    // Gateway — role access cards
    document.getElementById('gwStudentBtn')?.addEventListener('click', () => {
        window.location.href = 'student-dashboard.php';
    });
    document.getElementById('gwFacultyBtn')?.addEventListener('click', () => openModal('student'));
    document.getElementById('gwAdminBtn')?.addEventListener('click', () => openModal('admin'));

    // Modal — backdrop closes modal
    document.getElementById('modalBackdrop')?.addEventListener('click', () => closeModal());

    // Modal — handle toggles minimize; stop propagation on action buttons area
    document.getElementById('modalHandle')?.addEventListener('click', () => toggleMinimize());
    document.getElementById('modalHandle')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') toggleMinimize();
    });
    document.getElementById('modalHandleActions')?.addEventListener('click', (e) => e.stopPropagation());

    // Modal — minimize and close buttons
    document.getElementById('minimizeBtn')?.addEventListener('click', () => toggleMinimize());
    document.getElementById('minimizeBtn')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') toggleMinimize();
    });
    document.getElementById('modalCloseBtn')?.addEventListener('click', () => closeModal());
    document.getElementById('modalCloseBtn')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') closeModal();
    });

    // Role selector cards (legacy fallback path inside the modal itself)
    document.getElementById('roleFacultyBtn')?.addEventListener('click', () => selectRole('student'));
    document.getElementById('roleStudentBtn')?.addEventListener('click', () => {
        window.location.href = 'student-dashboard.php';
    });
    document.getElementById('roleAdminBtn')?.addEventListener('click', () => selectRole('admin'));

    // Back buttons — return to the gateway screen by closing the modal
    document.getElementById('studentBackBtn')?.addEventListener('click', () => closeModal());
    document.getElementById('adminBackBtn')?.addEventListener('click', () => closeModal());

    // Auth tabs
    document.getElementById('student-tab-login')?.addEventListener('click', () => switchStudentTab('login'));
    document.getElementById('student-tab-register')?.addEventListener('click', () => switchStudentTab('register'));

    // Password eye-toggles — wired via data-target attribute
    document.querySelectorAll('.eye-toggle[data-target]').forEach(btn => {
        btn.addEventListener('click', function () {
            toggleEye(this.dataset.target, this);
        });
    });

    // Input validators
    document.getElementById('reg-name')?.addEventListener('input', function () { validateLettersName(this); });
    document.getElementById('reg-sid')?.addEventListener('input', function () { validateLettersStudentID(this); });
    document.getElementById('reg-email')?.addEventListener('input', function () { validateLettersEmail(this); });
});
