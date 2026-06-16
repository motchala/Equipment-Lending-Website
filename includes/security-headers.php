<?php
/**
 * Mitigates OWASP ZAP findings: "Server Leaks Version Information"
 * and "X-Powered-By" disclosure, by overriding both headers
 * at the application level instead of relying on server config
 * (since InfinityFree free-tier doesn't allow httpd.conf access).
 */
header_remove('X-Powered-By');
header('Server: Web Server');