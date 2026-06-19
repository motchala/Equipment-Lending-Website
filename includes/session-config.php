<?php

/**
 * session-config.php
 *
 * Centralized session bootstrap. Configures the PHPSESSID cookie with
 * security flags BEFORE calling session_start(), so the Set-Cookie header
 * always carries:
 *   HttpOnly  — cookie is inaccessible to JavaScript (fixes ZAP alert
 *               "Cookie No HttpOnly Flag")
 *   SameSite  — mitigates CSRF via cookie
 *   Secure    — sent over HTTPS only (applied when the request is HTTPS)
 *
 * USAGE: replace every bare `session_start();` with:
 *   require_once __DIR__ . '/session-config.php';
 *   (or require_once '<path>/includes/session-config.php';)
 * Do NOT call session_start() separately afterwards; this file does it.
 */

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Layer 1: ini_set — forces cookie flags at the PHP engine level.
    // On shared hosting (e.g. InfinityFree), the server php.ini may ignore
    // session_set_cookie_params(), so ini_set is used as a stronger override.
    ini_set('session.cookie_httponly', '1');   // fixes "Cookie No HttpOnly Flag"
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_secure',   $is_https ? '1' : '0');
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.cookie_path',     '/');
    ini_set('session.use_strict_mode', '1');   // reject unrecognized session IDs

    // Layer 2: session_set_cookie_params — redundant safety net for hosts
    // that honour this function but not ini_set.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}
