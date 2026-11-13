<?php

/**
 * faculty-setup.php - the one-time "first sign-in" screen for a brand-new faculty account.
 *
 * Step 1: replace the temporary password the Super Admin generated.
 * Step 2: the essential account details (department, mobile number, optional backup email).
 * Everything is saved together by account-management/api/first-login-setup.php, which also
 * unlocks the account. Until then faculty_session_guard() keeps the rest of the portal closed
 * (see account-management/core/faculty-first-login.php). After that this page just forwards
 * to the dashboard, so it is never shown again.
 */

require_once __DIR__ . '/../shared/core/site-paths.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Per-page CSP with a nonce, same as the other entry pages. No inline styles or handlers here.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}'; style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

require_once PS_ROOT . '/config/session.php';
require_once PS_ROOT . '/config/csrf.php';

if (!isset($_SESSION['faculty_id'])) {
    header('Location: ../landing-page.php');
    exit();
}

require_once PS_ROOT . '/config/db.php';
require_once PS_ROOT . '/settings/core/faculty-helpers.php';           // department list
require_once PS_ROOT . '/account-management/core/faculty-first-login.php';
$conn = getDB();
faculty_fl_ensure_schema($conn);

$faculty_id = (string)$_SESSION['faculty_id'];
$acct = null;
try {
    $st = $conn->prepare('SELECT fullname, email, must_change_password, department, backup_email FROM tbl_users WHERE faculty_id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $faculty_id);
        $st->execute();
        $res  = $st->get_result();
        $acct = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
        $st->close();
    }
} catch (Throwable $e) {
    error_log('[faculty-setup] ' . $e->getMessage());
}

// Nothing to set up (already done, or the account is gone): on to the portal.
if (!$acct || (int)$acct['must_change_password'] !== 1) {
    header('Location: ../faculty-dashboard.php');
    exit();
}

$full_name   = (string)$acct['fullname'];
$email       = (string)$acct['email'];
$name_parts  = preg_split('/\s+/u', trim($full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$first_name  = $name_parts[0] ?? 'there';
$initials    = mb_strtoupper(mb_substr($name_parts[0] ?? 'F', 0, 1));
if (count($name_parts) > 1) {
    $initials .= mb_strtoupper(mb_substr(end($name_parts), 0, 1));
}
$id_label    = faculty_id_display($faculty_id, '—');
$dept_locked = trim((string)$acct['department']) !== '';
$dept_value  = trim((string)$acct['department']);
$backup_value = trim((string)$acct['backup_email']);

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
// $rel is a path from the project root. This page lives one folder down (account-management/), so the URL
// needs the leading ../ (without it the browser looks in account-management/account-management/ and the
// page's CSS and JavaScript never load).
$asset = static function (string $rel): string {
    return '../' . $rel . '?v=' . (int)@filemtime(PS_ROOT . '/' . $rel);
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#800000">
    <meta name="robots" content="noindex">
    <title>PUPSync | Set up your account</title>
    <link rel="icon" href="../shared/assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="../shared/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="../shared/assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="../shared/assets/images/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $h($asset('account-management/assets/css/faculty-first-login.css')); ?>">
</head>

<body class="fs-body">
    <div class="fs-shell">

        <!-- ═══ Brand panel (left on desktop, a slim banner on phones) ═══ -->
        <aside class="fs-brand" aria-label="PUPSync">
            <div class="fs-brand-top">
                <div class="fs-logo" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                </div>
                <div class="fs-wordmark">
                    <span class="fs-wordmark-title"><strong>PUP</strong><span>SYNC</span></span>
                    <span class="fs-wordmark-sub">Faculty Portal</span>
                </div>
            </div>

            <div class="fs-brand-body">
                <p class="fs-eyebrow">Welcome aboard</p>
                <h1 class="fs-hello">Hi, <?php echo $h($first_name); ?>. <br>Let&rsquo;s get your account ready.</h1>
                <p class="fs-lead">Your account was created by an administrator with a temporary password. Two quick steps and you&rsquo;re in.</p>

                <ol class="fs-steps" id="fsSteps">
                    <li class="fs-steps-item is-current" data-step-indicator="1">
                        <span class="fs-steps-dot" aria-hidden="true">
                            <span class="fs-steps-num">1</span>
                            <span class="material-symbols-outlined fs-steps-check">check</span>
                        </span>
                        <span class="fs-steps-text">
                            <span class="fs-steps-title">Secure your account</span>
                            <span class="fs-steps-desc">Replace the temporary password</span>
                        </span>
                    </li>
                    <li class="fs-steps-item" data-step-indicator="2">
                        <span class="fs-steps-dot" aria-hidden="true">
                            <span class="fs-steps-num">2</span>
                            <span class="material-symbols-outlined fs-steps-check">check</span>
                        </span>
                        <span class="fs-steps-text">
                            <span class="fs-steps-title">Your details</span>
                            <span class="fs-steps-desc">Department and contact details</span>
                        </span>
                    </li>
                </ol>
            </div>

            <p class="fs-brand-foot">
                <span class="material-symbols-outlined" aria-hidden="true">verified_user</span>
                You only do this once. The portal opens as soon as you&rsquo;re done.
            </p>
        </aside>

        <!-- ═══ Form panel ═══ -->
        <main class="fs-panel">
            <div class="fs-card" id="fsCard">

                <div class="fs-progress" id="fsProgress">
                    <div class="fs-progress-top">
                        <span class="fs-progress-label" id="fsProgressLabel">Step 1 of 2</span>
                        <span class="fs-progress-name" id="fsProgressName">Secure your account</span>
                    </div>
                    <div class="fs-progress-track" aria-hidden="true">
                        <span class="fs-progress-fill" id="fsProgressFill" data-step="1"></span>
                    </div>
                </div>

                <form id="fsForm" class="fs-form" method="post" novalidate autocomplete="off"
                    data-endpoint="api/first-login-setup.php">
                    <?php echo csrf_field(); ?>

                    <!-- ── Step 1: password ── -->
                    <section class="fs-step is-active" data-step="1" aria-labelledby="fsStep1Title">
                        <header class="fs-step-head">
                            <span class="fs-step-icon" aria-hidden="true"><span class="material-symbols-outlined">lock_reset</span></span>
                            <div>
                                <h2 class="fs-step-title" id="fsStep1Title" tabindex="-1">Choose your own password</h2>
                                <p class="fs-step-sub">The temporary password only works for this first sign-in. Pick one only you know.</p>
                            </div>
                        </header>

                        <div class="fs-field" data-field="new_password">
                            <label class="fs-label" for="fsNewPassword">New password <span class="fs-req">*</span></label>
                            <div class="fs-input-wrap">
                                <input class="fs-input fs-input--pw" id="fsNewPassword" name="new_password" type="password"
                                    maxlength="72" autocomplete="new-password" spellcheck="false" aria-describedby="fsPwHelp fsNewPasswordErr">
                                <button type="button" class="fs-eye" data-eye-for="fsNewPassword" aria-label="Show password" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                            <div class="fs-meter" id="fsMeter" data-level="-1">
                                <div class="fs-meter-bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                                <span class="fs-meter-label" id="fsMeterLabel" aria-live="polite">Enter at least 8 characters</span>
                            </div>
                            <ul class="fs-rules" id="fsPwHelp">
                                <li data-rule="len"><span class="material-symbols-outlined" aria-hidden="true">radio_button_unchecked</span>8 or more characters</li>
                                <li data-rule="mix"><span class="material-symbols-outlined" aria-hidden="true">radio_button_unchecked</span>A mix of letters and numbers or symbols</li>
                                <li data-rule="easy"><span class="material-symbols-outlined" aria-hidden="true">radio_button_unchecked</span>No common words, repeats or sequences (like 1234)</li>
                            </ul>
                            <p class="fs-err" id="fsNewPasswordErr" role="alert" hidden></p>
                        </div>

                        <div class="fs-field" data-field="confirm_password">
                            <label class="fs-label" for="fsConfirmPassword">Confirm password <span class="fs-req">*</span></label>
                            <div class="fs-input-wrap">
                                <input class="fs-input fs-input--pw" id="fsConfirmPassword" name="confirm_password" type="password"
                                    maxlength="72" autocomplete="new-password" spellcheck="false" aria-describedby="fsConfirmHint fsConfirmPasswordErr">
                                <button type="button" class="fs-eye" data-eye-for="fsConfirmPassword" aria-label="Show password" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                            <p class="fs-hint" id="fsConfirmHint" aria-live="polite"></p>
                            <p class="fs-err" id="fsConfirmPasswordErr" role="alert" hidden></p>
                        </div>
                    </section>

                    <!-- ── Step 2: details ── -->
                    <section class="fs-step" data-step="2" aria-labelledby="fsStep2Title" hidden>
                        <header class="fs-step-head">
                            <span class="fs-step-icon" aria-hidden="true"><span class="material-symbols-outlined">badge</span></span>
                            <div>
                                <h2 class="fs-step-title" id="fsStep2Title" tabindex="-1">Tell us a little about you</h2>
                                <p class="fs-step-sub">Just the essentials. You can add the rest later in Settings.</p>
                            </div>
                        </header>

                        <div class="fs-identity">
                            <span class="fs-identity-avatar" aria-hidden="true"><?php echo $h($initials); ?></span>
                            <div class="fs-identity-text">
                                <span class="fs-identity-name"><?php echo $h($full_name); ?></span>
                                <span class="fs-identity-meta"><?php echo $h($email); ?><?php echo $id_label !== '—' ? ' &middot; ID ' . $h($id_label) : ''; ?></span>
                            </div>
                            <span class="material-symbols-outlined fs-identity-lock" title="Managed by your administrator" aria-hidden="true">lock</span>
                        </div>

                        <div class="fs-field" data-field="department">
                            <label class="fs-label" for="fsDepartment">Department <span class="fs-req">*</span></label>
                            <?php if ($dept_locked): ?>
                                <div class="fs-input-wrap">
                                    <input class="fs-input" id="fsDepartment" name="department" type="text" value="<?php echo $h($dept_value); ?>" readonly aria-readonly="true">
                                    <span class="material-symbols-outlined fs-input-icon" aria-hidden="true">lock</span>
                                </div>
                            <?php else: ?>
                                <div class="fs-select-wrap">
                                    <select class="fs-input fs-select" id="fsDepartment" name="department" aria-describedby="fsDepartmentHint fsDepartmentErr">
                                        <option value="">Select your department&hellip;</option>
                                        <?php foreach (faculty_departments() as $dept): ?>
                                            <option value="<?php echo $h($dept); ?>"><?php echo $h($dept); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="material-symbols-outlined fs-select-caret" aria-hidden="true">expand_more</span>
                                </div>
                                <p class="fs-hint" id="fsDepartmentHint"><span class="material-symbols-outlined" aria-hidden="true">info</span>You can choose this once. It can&rsquo;t be changed afterwards.</p>
                            <?php endif; ?>
                            <p class="fs-err" id="fsDepartmentErr" role="alert" hidden></p>
                        </div>



                        <div class="fs-field" data-field="backup_email">
                            <label class="fs-label" for="fsBackupEmail">Backup email <span class="fs-opt">Optional</span></label>
                            <div class="fs-input-wrap fs-input-wrap--prefix">
                                <span class="material-symbols-outlined fs-input-lead" aria-hidden="true">alternate_email</span>
                                <input class="fs-input fs-input--lead" id="fsBackupEmail" name="backup_email" type="email" inputmode="email"
                                    autocomplete="email" maxlength="254" spellcheck="false" placeholder="name@example.com"
                                    value="<?php echo $h($backup_value); ?>" aria-describedby="fsBackupHint fsBackupEmailErr">
                            </div>
                            <p class="fs-hint" id="fsBackupHint"><span class="material-symbols-outlined" aria-hidden="true">info</span>A personal address for account recovery. You can change it any time in Settings.</p>
                            <p class="fs-err" id="fsBackupEmailErr" role="alert" hidden></p>
                        </div>
                    </section>

                    <div class="fs-alert" id="fsAlert" role="alert" hidden></div>

                    <div class="fs-actions">
                        <button type="button" class="fs-btn fs-btn--ghost" id="fsBack" hidden>
                            <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back
                        </button>
                        <button type="button" class="fs-btn fs-btn--primary" id="fsNext">
                            Continue<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                        </button>
                        <button type="submit" class="fs-btn fs-btn--primary" id="fsSubmit" hidden>
                            <span class="material-symbols-outlined fs-btn-icon" aria-hidden="true">check_circle</span>
                            <span class="fs-btn-label">Save &amp; open my portal</span>
                        </button>
                    </div>
                </form>

                <!-- ── Success ── -->
                <div class="fs-done" id="fsDone" role="status" aria-live="polite" hidden>
                    <span class="fs-done-badge" aria-hidden="true"><span class="material-symbols-outlined">check</span></span>
                    <h2 class="fs-done-title">You&rsquo;re all set, <?php echo $h($first_name); ?>!</h2>
                    <p class="fs-done-sub">Taking you to your dashboard&hellip;</p>
                    <span class="fs-done-bar" aria-hidden="true"></span>
                    <a class="fs-done-link" id="fsDoneLink" href="../faculty-dashboard.php">Go now</a>
                </div>
            </div>

            <footer class="fs-foot">
                <span>Signed in as <strong><?php echo $h($email); ?></strong></span>
                <a class="fs-signout" href="../auth/api/logout.php">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>Sign out
                </a>
            </footer>
        </main>
    </div>

    <script nonce="<?php echo $h($csp_nonce); ?>" src="<?php echo $h($asset('account-management/assets/js/faculty-first-login.js')); ?>"></script>
</body>

</html>