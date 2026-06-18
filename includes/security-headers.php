<?php
/**
 * Mitigates OWASP ZAP findings: "Server Leaks Version Information"
 * and "X-Powered-By" disclosure, by overriding both headers
 * at the application level instead of relying on server config
 * (since InfinityFree free-tier doesn't allow httpd.conf access).
 */
header_remove('X-Powered-By');
header('Server: Web Server');


$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($is_https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}