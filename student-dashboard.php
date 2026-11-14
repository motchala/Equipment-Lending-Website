<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

$csp_nonce = base64_encode(random_bytes(16));
// The student portal no longer uses Bootstrap / Font Awesome, so only Google Fonts (CSS + font files)
// and the QR library (jsDelivr) are allowed beyond 'self'.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

require_once __DIR__ . '/config/session.php';      // needed to read the one-shot role-splash flag
require_once __DIR__ . '/auth/core/role-splash.php';
require_once __DIR__ . '/shared/core/semester-label.php';   // current semester name (no DB connection needed here)
$st_semester = ps_semester_label();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSync | Student Portal</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
    <link rel="icon" href="shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="shared/assets/images/apple-touch-icon.png">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    <!-- PUPSync Student Styles -->
    <link rel="stylesheet" href="dashboard/assets/css/student-dashboard.css">
    <link rel="stylesheet" href="shared/assets/css/letter-camera.css">
    <link rel="stylesheet" href="shared/assets/css/semester-tag.css?v=<?php echo @filemtime(__DIR__ . '/shared/assets/css/semester-tag.css'); ?>">
    <?php role_splash_head(); ?>
</head>

<body>

    <?php render_role_splash('Student'); ?>

    <header class="st-top">
        <div class="st-top-in">
            <a class="st-brand" href="student-dashboard.php" aria-label="PUPSync Student Services">
                <span class="st-brand-mark" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                </span>
                <span class="st-brand-text">
                    <span class="st-brand-name"><strong>PUP</strong>SYNC</span>
                    <span class="st-brand-sub">Student Services</span>
                </span>
            </a>
            <a href="landing-page.php" class="st-back-link">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                <span>Back to Portal</span>
            </a>
        </div>
    </header>

    <main class="st-main" id="main">

        <noscript>
            <div class="st-alert is-error" style="display:flex">JavaScript is required to use the student portal.</div>
        </noscript>

        <!-- Progress (hidden on the home screen) -->
        <ol class="st-steps" id="steps" aria-label="Progress" hidden>
            <li class="st-step"><span class="st-step-dot">1</span><span class="st-step-label">Your details</span></li>
            <li class="st-step"><span class="st-step-dot">2</span><span class="st-step-label" id="stepChooseLabel">Choose</span></li>
            <li class="st-step"><span class="st-step-dot">3</span><span class="st-step-label">Done</span></li>
        </ol>

        <!-- ═══ HOME — pick a service ═══════════════════════════════════ -->
        <section class="st-view" id="view-home" aria-labelledby="homeTitle">
            <div class="st-hero">
                <h1 id="homeTitle" tabindex="-1">What do you need today?</h1>
                <p>Choose a service. You don&rsquo;t need an account &mdash; just a code from your faculty.</p>
                <p><span class="ps-semester-tag<?php echo $st_semester['active'] ? '' : ' is-none'; ?>" id="psSemesterTag"><?php echo htmlspecialchars($st_semester['label'], ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>

            <!-- Shown by JS when a borrow receipt was saved earlier in this browser session -->
            <button type="button" class="st-banner" id="receiptBanner" data-action="open-receipt" hidden>
                <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
                <span class="st-banner-text">
                    <strong>You have an active borrow request</strong>
                    <span id="receiptBannerSub"></span>
                </span>
                <span class="material-symbols-outlined" aria-hidden="true">qr_code_2</span>
            </button>

            <div class="st-services">
                <button type="button" class="st-service" data-action="service" data-service="borrow">
                    <span class="st-service-icon"><span class="material-symbols-outlined" aria-hidden="true">inventory_2</span></span>
                    <span class="st-service-body">
                        <span class="st-service-title">Borrow equipment</span>
                        <span class="st-service-text">Browse the equipment list &mdash; with photos &mdash; and pick what you need for your class or project.</span>
                    </span>
                    <span class="st-service-go" aria-hidden="true"><span class="material-symbols-outlined">arrow_forward</span></span>
                </button>

                <button type="button" class="st-service" data-action="service" data-service="facility">
                    <span class="st-service-icon"><span class="material-symbols-outlined" aria-hidden="true">meeting_room</span></span>
                    <span class="st-service-body">
                        <span class="st-service-title">Reserve a facility</span>
                        <span class="st-service-text">Book a lecture hall, computer lab or study room for your group.</span>
                    </span>
                    <span class="st-service-go" aria-hidden="true"><span class="material-symbols-outlined">arrow_forward</span></span>
                </button>
            </div>

            <ul class="st-info">
                <li><span class="material-symbols-outlined" aria-hidden="true">schedule</span>Mon &ndash; Fri &middot; 7:00 AM &ndash; 5:00 PM</li>
                <li><span class="material-symbols-outlined" aria-hidden="true">location_on</span>PUP Bi&ntilde;an Campus, Laguna</li>
                <li><span class="material-symbols-outlined" aria-hidden="true">help</span>Visit the Admin Office for assistance</li>
            </ul>
        </section>

        <!-- ═══ DETAILS — code + who you are ════════════════════════════ -->
        <section class="st-view" id="view-details" aria-labelledby="detailsTitle" hidden>
            <form class="st-card st-narrow" id="detailsForm" novalidate>
                <div class="st-card-head">
                    <span class="st-tile" aria-hidden="true"><span class="material-symbols-outlined" id="detailsIcon">inventory_2</span></span>
                    <div>
                        <h2 class="st-h2" id="detailsTitle" tabindex="-1">Borrow equipment</h2>
                        <p class="st-sub">Enter the code from your faculty, then tell us who you are.</p>
                    </div>
                </div>

                <div class="st-alert is-error" id="detailsError" role="alert" hidden></div>

                <div class="st-field">
                    <label for="facultyCode" id="codeLabel">Faculty or item code</label>
                    <div class="st-input-icon">
                        <span class="material-symbols-outlined" aria-hidden="true">key</span>
                        <input type="text" id="facultyCode" class="st-input st-mono" placeholder="Enter code"
                            autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false">
                    </div>
                    <p class="st-hint" id="codeHint">Ask your faculty for their code.</p>
                </div>

                <div class="st-field">
                    <label for="studentName">Your name</label>
                    <input type="text" id="studentName" class="st-input" placeholder="Juan Dela Cruz" autocomplete="name">
                </div>

                <div class="st-field">
                    <label for="studentCourse">Course</label>
                    <select id="studentCourse" class="st-input">
                        <option value="" selected disabled>Select your course</option>
                    </select>
                </div>

                <div class="st-actions">
                    <button type="button" class="st-btn st-btn-ghost" data-action="home">Cancel</button>
                    <button type="submit" class="st-btn st-btn-primary" id="btnVerifyCode">
                        <span class="material-symbols-outlined" aria-hidden="true">verified</span>Verify &amp; continue
                    </button>
                </div>
            </form>
        </section>

        <!-- ═══ FLOW — equipment / facility / receipt (rendered by JS) ══ -->
        <section class="st-view" id="view-flow" hidden></section>

    </main>

    <footer class="st-foot">PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus</footer>

    <!-- PUPSync Student JS -->
    <script src="shared/assets/js/letter-camera.js"></script>
    <script src="dashboard/assets/js/student-dashboard.js"></script>

</body>

</html>