<?php
// student-dashboard.php
// Single entry-point for all student-facing pages.
//
// SCREEN A (#screen-portal)  — landing / choice cards / faculty-code modal
//   Shown when no valid session or receipt exists in sessionStorage.
//   After a successful faculty-code verify, JS switches to Screen B.
//
// SCREEN B (#screen-dashboard) — sidebar dashboard shell
//   Shown when a valid session OR a saved receipt exists.
//   Contains: equipment catalog, room reservation stub, My Request receipt.
//
// All state lives in JS sessionStorage — no PHP session required.
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSync | Student Portal</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- PUPSync Student Styles -->
    <link rel="stylesheet" href="CSS/student-dashboard.css">
</head>
<body>

<!-- ============================================================
     SCREEN A — PORTAL (landing / choice cards)
     Visibility toggled by student-dashboard.js
============================================================ -->
<div id="screen-portal">

    <!-- Background decorations -->
    <div class="student-bg-pattern"></div>
    <div class="student-bg-blob blob-1"></div>
    <div class="student-bg-blob blob-2"></div>

    <!-- Top Bar -->
    <header class="student-topbar">
        <div class="student-brand">
            <div class="student-brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                    <polyline points="2 17 12 22 22 17"/>
                    <polyline points="2 12 12 17 22 12"/>
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

    <!-- Main Content — Choice Cards -->
    <main class="student-main">

        <div class="student-hero">
            <h1>What do you need today?</h1>
            <p>Select a service to get started. No account required.</p>
        </div>

        <div class="student-choices">

            <!-- Borrow Equipment -->
            <div class="student-choice-card"
                data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                data-action="borrow">
                <div class="choice-glow borrow-glow"></div>
                <div class="choice-icon-wrap borrow-icon">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                <h2>Borrow Equipment</h2>
                <p>Browse available laptops, projectors, lab equipment, and more. Submit a request for your class or project.</p>
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
            <div class="student-choice-card"
                data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                data-action="room">
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

        <!-- Receipt recall banner — shown by JS if a receipt exists in sessionStorage -->
        <div id="receiptBanner"
            style="display:none;max-width:560px;margin:20px auto 0;background:#fff8e1;
                   border-radius:14px;padding:14px 20px;align-items:center;gap:12px;
                   border:1px solid #ffe082;cursor:pointer;">
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
                style="border-radius:20px;border:none;box-shadow:0 10px 40px rgba(128,0,0,.2);">

                <!-- Header (rewritten by JS per step) -->
                <div class="modal-header"
                    style="background:linear-gradient(135deg,#800000 0%,#5a0000 100%);
                           border-radius:20px 20px 0 0;border-bottom:none;padding:24px 28px;">
                    <div>
                        <h5 class="modal-title"
                            style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.25rem;margin-bottom:4px;">
                            Faculty Authorization Required
                        </h5>
                        <p style="color:rgba(255,255,255,.8);margin:0;font-size:.875rem;">
                            Enter your faculty's code to proceed
                        </p>
                    </div>
                    <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>
                </div>

                <!-- Body (rewritten by JS per step) -->
                <div class="modal-body"
                    style="padding:32px 28px 24px;background:var(--color-surface);">
                    <div class="mb-4">
                        <label for="facultyCode" class="form-label"
                            style="font-weight:600;color:var(--color-on-surface);font-size:.875rem;">
                            Faculty Code
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"
                                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-primary);">
                                <span class="material-symbols-outlined" style="font-size:20px;">key</span>
                            </span>
                            <input type="text" class="form-control form-control-lg" id="facultyCode"
                                placeholder="Enter 6-digit faculty code"
                                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);font-size:1rem;padding:12px 16px;"
                                autocomplete="off">
                        </div>
                        <div class="form-text" style="color:var(--color-secondary);font-size:.8rem;margin-top:8px;">
                            Ask your faculty adviser or instructor for their code.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="studentName" class="form-label"
                            style="font-weight:600;color:var(--color-on-surface);font-size:.875rem;">Your Name</label>
                        <input type="text" class="form-control" id="studentName" placeholder="Juan Dela Cruz"
                            style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                    </div>
                    <div class="mb-3">
                        <label for="studentId" class="form-label"
                            style="font-weight:600;color:var(--color-on-surface);font-size:.875rem;">Student ID</label>
                        <input type="text" class="form-control" id="studentId" placeholder="20XX-XXXXX-BN-X"
                            style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                    </div>
                    <input type="hidden" id="actionType" value="">
                </div>

                <!-- Footer (rewritten by JS per step) -->
                <div class="modal-footer"
                    style="border-top:1px solid var(--color-outline-variant);padding:20px 28px 28px;
                           background:var(--color-surface);border-radius:0 0 20px 20px;">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                        style="padding:10px 24px;border-radius:12px;border:1px solid var(--color-outline-variant);color:var(--color-secondary);font-weight:600;background:transparent;">
                        Cancel
                    </button>
                    <button type="button" class="btn" id="btnVerifyCode"
                        style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#800000 0%,#5a0000 100%);color:#fff;font-weight:700;border:none;">
                        <span class="material-symbols-outlined"
                            style="font-size:18px;vertical-align:middle;margin-right:6px;">verified</span>
                        Verify & Continue
                    </button>
                </div>

            </div>
        </div>
    </div>

</div><!-- /#screen-portal -->


<!-- ============================================================
     SCREEN B — DASHBOARD (sidebar shell)
     Visibility toggled by student-dashboard.js
============================================================ -->
<div id="screen-dashboard" style="display:none;">

    <!-- Side Navigation -->
    <nav class="side-nav" id="sideNav">
        <div class="side-nav-brand">
            <div class="side-nav-logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                    <polyline points="2 17 12 22 22 17"/>
                    <polyline points="2 12 12 17 22 12"/>
                </svg>
            </div>
            <div class="side-nav-brand-text">
                <div class="side-nav-title"><strong>PUP</strong>SYNC</div>
                <div class="side-nav-sub">Student Portal</div>
                <div class="student-mode-badge">
                    <span class="material-symbols-outlined">school</span>
                    Student Mode
                </div>
            </div>
        </div>

        <div class="side-nav-links">
            <div class="side-nav-section-label">Services</div>
            <button class="side-nav-item active" data-panel="panel-borrow" id="nav-borrow">
                <span class="material-symbols-outlined">inventory_2</span>
                Borrow Equipment
            </button>
            <button class="side-nav-item" data-panel="panel-room" id="nav-room">
                <span class="material-symbols-outlined">meeting_room</span>
                Reserve a Room
                <span class="nav-badge">Soon</span>
            </button>
            <button class="side-nav-item" data-panel="panel-request" id="nav-request">
                <span class="material-symbols-outlined">receipt_long</span>
                My Request
            </button>
        </div>

        <div class="side-nav-footer">
            <div class="student-chip">
                <div class="student-chip-avatar" id="sidebarInitials">ST</div>
                <div style="min-width:0;">
                    <div class="student-chip-name" id="sidebarName">—</div>
                    <div class="student-chip-id"   id="sidebarId">—</div>
                </div>
            </div>
            <!-- Back to Portal re-shows Screen A -->
            <button class="side-nav-item" id="backToPortalBtn">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Portal
            </button>
        </div>
    </nav>

    <!-- Main Wrapper -->
    <div class="main-wrapper">

        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-left">
                <button class="top-bar-icon-btn" id="mobileMenuBtn"
                    style="display:none;" aria-label="Open navigation">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <span class="top-bar-page-title" id="topBarTitle">Borrow Equipment</span>
                <div class="auth-chip" id="authChip">
                    <span class="material-symbols-outlined">verified_user</span>
                    <span id="authChipText">Authorized session</span>
                </div>
            </div>
            <div class="top-bar-actions">
                <div class="top-bar-divider"></div>
                <div class="student-dd-wrap" id="studentDdWrap">
                    <button class="top-bar-avatar" id="avatarBtn"
                        aria-label="Student menu" aria-expanded="false">
                        <span id="topBarInitials">ST</span>
                    </button>
                    <div class="student-dropdown" id="studentDropdown">
                        <div class="student-dropdown-header">
                            <div class="student-dropdown-name" id="ddName">—</div>
                            <div class="student-dropdown-id"   id="ddId">—</div>
                        </div>
                        <!-- Back to Portal re-shows Screen A -->
                        <button class="student-dropdown-item" id="ddBackToPortalBtn">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Back to Portal
                        </button>
                        <button class="student-dropdown-item danger" id="endSessionBtn">
                            <span class="material-symbols-outlined">logout</span>
                            End Session
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- App Main -->
        <main class="app-main" id="appMain">

            <!-- Panel: Borrow Equipment -->
            <div class="sd-panel active" id="panel-borrow">
                <div class="sd-page-header">
                    <div class="sd-page-title">Borrow Equipment</div>
                    <div class="sd-page-subtitle">Browse available items and submit a borrow request</div>
                </div>

                <!-- Auth / code-used banner injected by JS -->
                <div id="panelBannerSlot"></div>

                <!-- Filters -->
                <div class="catalog-filters" id="catalogFilters">
                    <div class="catalog-search-wrap">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="equipSearch" placeholder="Search equipment…">
                    </div>
                    <select class="catalog-filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                    </select>
                </div>

                <!-- Grid -->
                <div class="eq-grid" id="equipGrid">
                    <div class="eq-empty">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <p>Loading equipment…</p>
                    </div>
                </div>
            </div>

            <!-- Panel: Reserve a Room -->
            <div class="sd-panel" id="panel-room">
                <div class="sd-page-header">
                    <div class="sd-page-title">Reserve a Room</div>
                    <div class="sd-page-subtitle">Book lecture halls, labs, or study rooms</div>
                </div>
                <div class="coming-soon-wrap">
                    <div class="coming-soon-icon">
                        <span class="material-symbols-outlined">meeting_room</span>
                    </div>
                    <div class="coming-soon-title">Coming Soon</div>
                    <div class="coming-soon-desc">
                        Room reservation is currently being set up. Once live, you'll be able to book
                        lecture halls, computer labs, and study rooms right here with the same
                        faculty code process.
                    </div>
                    <div class="coming-soon-pill">
                        <span class="material-symbols-outlined">hourglass_top</span>
                        In Development
                    </div>
                </div>
            </div>

            <!-- Panel: My Request -->
            <div class="sd-panel" id="panel-request">
                <div class="sd-page-header">
                    <div class="sd-page-title">My Request</div>
                    <div class="sd-page-subtitle">Your borrow receipt and QR code</div>
                </div>
                <div id="requestPanelContent">
                    <!-- Populated by JS -->
                </div>
            </div>

        </main>
    </div><!-- /.main-wrapper -->

    <!-- Borrow Form Modal -->
    <div class="modal fade" id="borrowModal" tabindex="-1" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius:20px;border:none;box-shadow:0 10px 40px rgba(163,32,32,.22);">

                <div class="modal-header"
                    style="background:linear-gradient(135deg,#a32020 0%,#7c1616 100%);
                           border-radius:20px 20px 0 0;border-bottom:none;padding:22px 28px;">
                    <div>
                        <h5 class="modal-title"
                            style="color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.1rem;margin-bottom:3px;">
                            Borrow Equipment
                        </h5>
                        <p id="borrowModalSubtitle"
                            style="color:rgba(255,255,255,.8);margin:0;font-size:.83rem;">
                            Complete the details below
                        </p>
                    </div>
                    <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>
                </div>

                <div class="modal-body"
                    style="padding:26px 28px 20px;background:var(--color-surface);">

                    <div class="mb-3">
                        <label class="form-label"
                            style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">Equipment</label>
                        <input type="text" class="form-control" id="borrowEquipDisplay" readonly
                            style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;font-weight:600;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"
                            style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                            Room / Location <span style="color:#c62828;">*</span>
                        </label>
                        <input type="text" class="form-control" id="borrowRoom"
                            placeholder="e.g. B-205 or AVR 1"
                            style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label"
                                style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                                Borrow Date <span style="color:#c62828;">*</span>
                            </label>
                            <input type="date" class="form-control" id="borrowDateInput"
                                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                        </div>
                        <div class="col">
                            <label class="form-label"
                                style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                                Return Date <span style="color:#c62828;">*</span>
                            </label>
                            <input type="date" class="form-control" id="returnDateInput"
                                style="background:var(--color-surface-container);border-color:var(--color-outline-variant);color:var(--color-on-surface);padding:10px 14px;">
                        </div>
                    </div>

                    <div style="background:#fff8e1;border-radius:10px;padding:10px 14px;font-size:.8rem;color:#795548;">
                        <span class="material-symbols-outlined"
                            style="font-size:15px;vertical-align:middle;margin-right:4px;">info</span>
                        Your request is <strong>auto-approved</strong> via faculty authorization.
                        The code will be consumed after submission.
                    </div>

                    <div id="borrowModalError"
                        style="display:none;color:#c62828;background:#fce4ec;border-radius:10px;padding:10px 14px;font-size:.85rem;margin-top:12px;">
                    </div>
                </div>

                <div class="modal-footer"
                    style="border-top:1px solid var(--color-outline-variant);padding:16px 28px 24px;
                           background:var(--color-surface);border-radius:0 0 20px 20px;">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                        style="padding:10px 24px;border-radius:12px;border:1px solid var(--color-outline-variant);color:var(--color-secondary);font-weight:600;background:transparent;">
                        Cancel
                    </button>
                    <button type="button" id="borrowSubmitBtn"
                        style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#a32020 0%,#7c1616 100%);color:#fff;font-weight:700;border:none;cursor:pointer;">
                        <span class="material-symbols-outlined"
                            style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile nav backdrop -->
    <div class="nav-backdrop" id="navBackdrop"></div>

    <!-- Toast -->
    <div id="sd-toast"></div>

</div><!-- /#screen-dashboard -->


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Back-to-portal buttons wired after Bootstrap loads -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        ['backToPortalBtn', 'ddBackToPortalBtn'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.addEventListener('click', function () {
                if (typeof window._pupShowPortal === 'function') window._pupShowPortal();
            });
        });
    });
</script>

<!-- PUPSync Student JS (portal + dashboard logic) -->
<script src="JS/student-dashboard.js"></script>

</body>
</html>