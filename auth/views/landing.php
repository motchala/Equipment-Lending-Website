<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4a0f15">
    <title>PUPSYNC — Institutional Access Portal</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
    <link rel="icon" href="shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="shared/assets/images/apple-touch-icon.png">
    <!-- Performance: preconnect to font origins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Preload the leftmost gateway photo for faster LCP -->
    <link rel="preload" as="image" href="auth/assets/images/landing-page/1-hero-page.jpg">
    <!-- Fonts with display=swap to avoid FOIT -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="auth/assets/css/landing-page.css">
</head>

<body>

    <!-- ================================================================
     GATEWAY — split-screen institutional access portal
================================================================ -->
    <main class="gateway" id="gateway">

        <!-- LEFT: campus photo strip + headline -->
        <section class="gateway-visual" aria-hidden="false">
            <div class="visual-strip">
                <div class="strip-photo strip-photo-1"></div>
                <div class="strip-photo strip-photo-2"></div>
                <div class="strip-photo strip-photo-3"></div>
            </div>
            <div class="visual-overlay"></div>

            <a class="visual-brand" href="https://www.pup.edu.ph/binan/" target="_blank" rel="noopener">
                <i class="fa-solid fa-school"></i> PUP Biñan Campus
            </a>

            <div class="visual-content">
                <h1 class="visual-heading">
                    What matters,<br>
                    <em>made easy.</em>
                </h1>
                <p class="visual-sub">
                    A secure, centralized platform for borrowing equipment and reserving rooms across campus — tracked, trusted, and always ready.
                </p>
            </div>
        </section>

        <!-- RIGHT: role access panel -->
        <section class="gateway-panel">

            <div class="panel-logo">
                <div class="panel-logo-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                </div>
                <div class="panel-logo-text">
                    <span class="panel-logo-name">
                        <span class="brand-pup">PUP</span><span class="brand-sync">Sync</span>
                    </span>
                    <span class="panel-logo-sub">Institutional Access Portal</span>
                </div>
            </div>

            <!-- Single unified login form — the backend (landing-page.php
                 POST handler) determines from the submitted email whether
                 this is an Admin or Faculty account and redirects to the
                 matching dashboard. No role picker, no view-switching. -->
            <div class="panel-stage" id="panelStage">

                <div class="panel-view panel-view-auth active" id="loginView">

                    <div class="auth-head">
                        <p class="pane-title">Sign In</p>
                        <p class="pane-subtitle">Faculty &amp; Admin access</p>
                    </div>

                    <?php if (!empty($login_error)): ?>
                        <div class="auth-alert error">
                            <i class="fa-solid fa-<?= $lockout_seconds > 0 ? 'lock' : 'circle-exclamation' ?>"></i>
                            <?php if ($lockout_seconds > 0): ?>
                                <?= strpos($login_error, 'location') !== false ? 'Too many failed attempts from this location.' : 'Too many failed attempts.' ?>
                                Try again in <span id="lockout-countdown"><?= gmdate('i:s', $lockout_seconds) ?></span>.
                            <?php else: ?>
                                <?= htmlspecialchars($login_error) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="" id="loginForm">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <div class="input-wrap">
                                <input class="form-field" type="email" id="login-email" name="email"
                                    placeholder="you@pupsync.edu"
                                    value="<?= htmlspecialchars($login_email_val) ?>"
                                    autocomplete="email" required>
                                <i class="fa-solid fa-envelope input-icon-left"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="login-pass">Password</label>
                            <div class="input-wrap">
                                <input class="form-field" type="password" id="login-pass" name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password" required>
                                <i class="fa-solid fa-lock input-icon-left"></i>
                                <button type="button" class="eye-toggle" data-target="login-pass" tabindex="-1" aria-label="Show password">
                                    <i class="fa-regular fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <label class="remember-option" for="remember-me">
                            <input type="checkbox" id="remember-me" name="remember_me" value="1"
                                <?= $remember_me ? 'checked' : '' ?>>
                            <span>Stay logged in for 30 days</span>
                        </label>
                        <button type="submit" name="login" class="btn-auth"
                            id="loginSubmitBtn"
                            <?php if ($lockout_seconds > 0): ?>
                            disabled
                            data-lockout-seconds="<?= (int)$lockout_seconds ?>"
                            <?php endif; ?>>
                            <i class="fa-solid fa-arrow-right-to-bracket" id="loginSubmitIcon"></i>
                            <span id="loginSubmitLabel">Sign In</span>
                        </button>
                    </form>
                </div>

            </div><!-- /panel-stage -->

            <div class="panel-bottom">
                <div class="panel-links">
                    <a href="landing-page.php?go=student" id="studentLink">Continue as Student</a>
                </div>
                <div class="panel-badges">
                    <span><i class="fa-solid fa-circle-check"></i> Secure Auth</span>
                    <span><i class="fa-solid fa-lock"></i> Encrypted</span>
                </div>
            </div>

        </section>

    </main><!-- /gateway -->


    <script src="auth/assets/js/landing-page.js"></script>


</body>

</html>