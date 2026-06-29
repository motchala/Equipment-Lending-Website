<?php

/**
 * Mitigates OWASP ZAP findings:
 *  - "Server Leaks Version Information" / "X-Powered-By" disclosure
 *  - "Content Security Policy (CSP) Header Not Set"
 *  - "Missing Anti-clickjacking Header" (X-Frame-Options + CSP frame-ancestors)
 *
 * This file is the PHP-level fallback for environments where .htaccess
 * mod_headers directives are unavailable (e.g. InfinityFree free tier).
 * Include it at the very top of any PHP file that does NOT already emit
 * its own per-page CSP (i.e. AJAX endpoints, utility includes, etc.).
 *
 * Main page entry-points (landing-page.php, faculty-dashboard.php,
 * admin-dashboard.php, student-dashboard.php, return_confirm.php) generate
 * their own nonce-bearing CSP headers and should NOT include this file,
 * since PHP only honours the first Content-Security-Policy header call.
 */

// ── Server / X-Powered-By disclosure ─────────────────────────────────────────
header_remove('X-Powered-By');
header('Server: Web Server');

// ── Anti-clickjacking ─────────────────────────────────────────────────────────
// X-Frame-Options covers older browsers; CSP frame-ancestors covers modern ones.
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");

// ── HSTS (HTTPS-only) ─────────────────────────────────────────────────────────
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($is_https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}