<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");
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
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/all.min.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- PUPSync Student Styles -->
    <link rel="stylesheet" href="equipment-booking/assets/css/student-dashboard.css">
</head>

<body>

    <!-- ============================================================
         SCREEN A — PORTAL (landing / choice cards / faculty-code modal)
         This is the only screen. All student flows run through the
         faculty code modal steps (verify → form → result).
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
                    <div class="choice-arrow"
                        data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                        data-action="borrow"
                        role="button" aria-label="Proceed to borrow equipment">
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
                    <h2>Facilities</h2>
                    <p>Book lecture halls, computer labs, or study rooms for your group meetings and presentations.</p>
                    <div class="choice-meta">
                        <span class="choice-badge">
                            <span class="material-symbols-outlined">event_available</span>
                            Real-time Availability
                        </span>
                    </div>
                    <div class="choice-arrow"
                        data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                        data-action="room"
                        role="button" aria-label="Proceed to reserve a facility">
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

        <!-- Faculty Code Modal — steps rewritten by JS -->
        <div class="modal fade" id="facultyCodeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content"
                    style="border-radius:20px;border:none;box-shadow:0 10px 40px rgba(128,0,0,.2);">

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
                                    placeholder="Enter faculty code"
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <!-- PUPSync Student JS -->
    <script src="equipment-booking/assets/js/student-dashboard.js"></script>

</body>

</html>
