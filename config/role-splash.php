<?php

/**
 * Role splash — post-login transition overlay for PUPSync.
 *
 * Purely presentational: it never touches the database or decides who is
 * allowed in. The only thing it stores is a one-shot flag in the session.
 *
 * WHEN IT SHOWS
 *   Only right after a successful sign-in. landing-page.php "arms" a
 *   one-shot flag in the session (role_splash_arm) just before it redirects
 *   to the dashboard; the dashboard takes that flag on its very first load.
 *   A refresh, a navigation or a re-opened tab finds no flag, so nothing
 *   shows. Logging out destroys the session, so the next login arms it again.
 *   Students don't authenticate: "Continue as Student" goes through
 *   landing-page.php?go=student, which arms it for them the same way.
 *
 * Files that make up the feature
 *   config/role-splash.php        this file (markup + one-shot flag)
 *   assets/css/role-splash.css    look + whole animation timeline
 *   assets/js/role-splash.js      cleanup / skip / fail-safe only
 *
 * Usage in a dashboard (3 lines; the session must already be started):
 *   require_once __DIR__ . '/config/role-splash.php';   // top of file
 *   <?php role_splash_head(); ?>                        // inside <head>
 *   <?php render_role_splash('Faculty'); ?>             // right after <body>
 * Both calls print nothing unless the flag was armed for that same role.
 *
 * Usage at the sign-in point (landing-page.php):
 *   role_splash_arm('Faculty');   // right before header('Location: ...')
 *
 * Accepted labels: 'Admin', 'Super Admin', 'Faculty', 'Student'.
 * Any other value is ignored (better no splash than a wrong one).
 *
 * CSP note: the letters carry an inline style="--i:N" (stagger index).
 * The dashboards already allow inline styles (style-src 'unsafe-inline');
 * if that is ever tightened, the letters still animate, just all at once.
 */

if (!function_exists('_rs_variants')) {
    /** Role label => CSS modifier class. Single source of truth for the accepted labels. */
    function _rs_variants(): array
    {
        return [
            'Admin'       => 'admin',
            'Super Admin' => 'super-admin',
            'Faculty'     => 'faculty',
            'Student'     => 'student',
        ];
    }
}

if (!function_exists('role_splash_arm')) {
    /** Flag "a sign-in just happened for this role". Consumed once by the dashboard's first load. */
    function role_splash_arm(string $role): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset(_rs_variants()[$role])) {
            $_SESSION['role_splash_pending'] = $role;
        }
    }
}

if (!function_exists('_rs_take_pending')) {
    /**
     * Read and clear the one-shot flag. The first call in a request consumes it;
     * later calls (head, then body) get the same cached answer, so both agree.
     */
    function _rs_take_pending(): ?string
    {
        static $taken = false, $value = null;
        if ($taken) {
            return $value;
        }
        $taken = true;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role_splash_pending'])) {
            $flag  = $_SESSION['role_splash_pending'];
            $value = is_string($flag) ? $flag : null;
            unset($_SESSION['role_splash_pending']);
        }
        return $value;
    }
}

if (!function_exists('_rs_asset_version')) {
    /** Cache-busting version for a file under the project root (same idea as the filemtime() links elsewhere). */
    function _rs_asset_version(string $relative_path): int
    {
        $mtime = @filemtime(dirname(__DIR__) . '/' . $relative_path);
        return $mtime ?: 1;
    }
}

if (!function_exists('_rs_letters')) {
    /**
     * Wrap every character of $text in its own animatable span.
     * $i is the running stagger index, shared across calls so a
     * multi-part word keeps one continuous cascade.
     */
    function _rs_letters(string $text, int &$i, string $extra_class = ''): string
    {
        $out   = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $out .= '<span class="rs-l ' . $extra_class . '" style="--i:' . $i++ . '">'
                . '<span class="rs-c">' . htmlspecialchars($ch, ENT_QUOTES, 'UTF-8') . '</span></span>';
        }
        return $out;
    }
}

if (!function_exists('role_splash_head')) {
    /** Print the <link> tags the splash needs. Call once inside <head>. Prints nothing unless a sign-in just happened. */
    function role_splash_head(): void
    {
        if (_rs_take_pending() === null) {
            return;
        }
        $v = _rs_asset_version('assets/css/role-splash.css');
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&amp;family=Outfit:wght@400;500;600&amp;display=swap">' . "\n";
        echo '    <link rel="stylesheet" href="assets/css/role-splash.css?v=' . $v . '">' . "\n";
    }
}

if (!function_exists('render_role_splash')) {
    /** Print the splash overlay for the given role label. Call once, right after <body>. Prints nothing unless armed for this role. */
    function render_role_splash(string $role): void
    {
        static $rendered = false;
        $variants = _rs_variants();
        if ($rendered || !isset($variants[$role]) || _rs_take_pending() !== $role) {
            return;
        }
        $rendered = true;

        // Wordmark: "PUP" + "Sync" (shown uppercase by CSS), one continuous cascade.
        $i     = 0;
        $brand = _rs_letters('PUP', $i, 'rs-pup') . _rs_letters('Sync', $i, 'rs-sync');

        // Role label: one .rs-word per word so "Super Admin" wraps between words, never mid-word.
        $j         = 0;
        $role_html = '';
        foreach (explode(' ', $role) as $word) {
            $role_html .= '<span class="rs-word">' . _rs_letters($word, $j) . '</span>';
        }

        $variant = $variants[$role];
        $label   = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $js_v    = _rs_asset_version('assets/js/role-splash.js');

        echo <<<HTML

    <div class="role-splash role-splash--{$variant}" id="roleSplash" role="status" aria-label="Loading PUPSync {$label}">
        <div class="rs-bg" aria-hidden="true">
            <span class="rs-grid"></span>
            <span class="rs-blob rs-blob--a"></span>
            <span class="rs-blob rs-blob--b"></span>
            <span class="rs-blob rs-blob--c"></span>
            <span class="rs-ring rs-ring--3"></span>
            <span class="rs-ring rs-ring--2"></span>
            <span class="rs-ring rs-ring--1"></span>
            <span class="rs-grain"></span>
            <span class="rs-corner rs-corner--tl"></span>
            <span class="rs-corner rs-corner--tr"></span>
            <span class="rs-corner rs-corner--bl"></span>
            <span class="rs-corner rs-corner--br"></span>
        </div>

        <div class="rs-stage" aria-hidden="true">
            <div class="rs-lockup">
                <div class="rs-mark">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                </div>
                <div class="rs-brand">{$brand}</div>
            </div>

            <div class="rs-role-wrap">
                <span class="rs-kicker">Entering as</span>
                <div class="rs-role">{$role_html}</div>
                <span class="rs-rule"></span>
            </div>
        </div>

        <div class="rs-caption" aria-hidden="true">Institutional Access Portal</div>
    </div>
    <script src="assets/js/role-splash.js?v={$js_v}" defer></script>

HTML;
    }
}
