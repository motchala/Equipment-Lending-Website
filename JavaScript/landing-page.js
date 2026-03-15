/* ================================================================
   HERO CAROUSEL
================================================================ */
(function () {
    const TOTAL = 7;
    const INTERVAL = 6000; // 6 s between transitions
    const IMG_BASE = 'images-design/';
    const IMG_SUFFIX = '-hero-page.jpg';

    const track = document.getElementById('carouselTrack');
    const slides = Array.from(track.querySelectorAll('.carousel-slide'));
    const dotsWrap = document.getElementById('carouselDots');
    const counter = document.getElementById('carouselCounter');
    const progress = document.getElementById('carouselProgress');

    let current = 6; // start at index 6 (image 7)
    let timer = null;
    let progTimer = null;

    /* Assign background images */
    slides.forEach((s, i) => {
        s.style.backgroundImage = `url('${IMG_BASE}${i + 1}${IMG_SUFFIX}')`;
    });

    /* Build dots */
    for (let i = 0; i < TOTAL; i++) {
        const d = document.createElement('button');
        d.className = 'carousel-dot' + (i === current ? ' active' : '');
        d.setAttribute('aria-label', `Go to slide ${i + 1}`);
        d.addEventListener('click', () => goTo(i));
        dotsWrap.appendChild(d);
    }

    function getDots() {
        return dotsWrap.querySelectorAll('.carousel-dot');
    }

    function updateUI() {
        slides.forEach((s, i) => s.classList.toggle('active', i === current));
        getDots().forEach((d, i) => d.classList.toggle('active', i === current));
        counter.textContent = `${current + 1} / ${TOTAL}`;
    }

    /* Animated progress bar */
    function startProgress() {
        clearInterval(progTimer);
        progress.style.transition = 'none';
        progress.style.width = '0%';
        /* Force reflow */
        void progress.offsetWidth;
        progress.style.transition = `width ${INTERVAL}ms linear`;
        progress.style.width = '100%';
    }

    function goTo(idx) {
        current = (idx + TOTAL) % TOTAL;
        updateUI();
        resetTimer();
        startProgress();
    }

    window.carouselGo = function (dir) {
        goTo(current + dir);
    };

    function resetTimer() {
        clearInterval(timer);
        timer = setInterval(() => goTo(current + 1), INTERVAL);
    }

    /* Init */
    updateUI();
    startProgress();
    resetTimer();

    /* Pause on hover */
    track.closest('.hero').addEventListener('mouseenter', () => {
        clearInterval(timer);
        clearInterval(progTimer);
        progress.style.transition = 'none';
    });
    track.closest('.hero').addEventListener('mouseleave', () => {
        startProgress();
        resetTimer();
    });

    /* Touch swipe support */
    let touchStartX = 0;
    track.addEventListener('touchstart', e => {
        touchStartX = e.touches[0].clientX;
    }, {
        passive: true
    });
    track.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - touchStartX;
        if (Math.abs(dx) > 40) goTo(current + (dx < 0 ? 1 : -1));
    }, {
        passive: true
    });
})();


/* ================================================================
   FOOTER SCROLL-REVEAL (IntersectionObserver)
================================================================ */
(function () {
    const footer = document.getElementById('site-footer');
    const obs = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                footer.classList.add('visible');
                obs.disconnect();
            }
        }, {
        threshold: 0.05
    }
    );
    obs.observe(footer);
})();


/* ================================================================
   MODAL
================================================================ */
const overlay = document.getElementById('authModal');
let isMin = false;

function openModal() {
    overlay.classList.remove('minimized');
    isMin = false;
    updateMinimizeIcon();
    overlay.classList.add('open');

    // Show role selector by default
    showRoleSelector();

    /* Only lock scroll when NOT in mobile-preview (frame handles its own scroll) */
    const frame = document.getElementById('phoneFrameWrap');
    if (!frame.classList.contains('mobile-preview')) {
        document.body.style.overflow = 'hidden';
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
   ROLE SELECTOR & NAVIGATION
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
   MOBILE VIEW TOGGLE
   Creates a phone-frame preview centered in the browser window.
   No browser resize needed — the UI itself scales to phone dimensions.
================================================================ */
let isMobilePreview = false;

function toggleMobileView() {
    isMobilePreview = !isMobilePreview;
    const btn = document.getElementById('mobileToggleBtn');
    const icon = document.getElementById('mobileToggleIcon');
    const label = document.getElementById('mobileToggleLabel');
    const frame = document.getElementById('phoneFrameWrap');
    const html = document.documentElement;

    if (isMobilePreview) {
        html.classList.add('mobile-preview-bg');
        frame.classList.add('mobile-preview');
        btn.setAttribute('aria-pressed', 'true');
        icon.className = 'fa-solid fa-desktop';
        label.textContent = 'Desktop Ready';
        btn.title = 'Exit mobile preview';
        closeModal();
        /* Scroll browser window to top so hero fills the phone frame */
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    } else {
        html.classList.remove('mobile-preview-bg');
        frame.classList.remove('mobile-preview');
        btn.setAttribute('aria-pressed', 'false');
        icon.className = 'fa-solid fa-mobile-screen-button';
        label.textContent = 'Mobile Ready';
        btn.title = 'Switch to mobile preview layout';
    }
}

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