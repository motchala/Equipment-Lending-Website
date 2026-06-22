<?php
require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/session-config.php';
session_unset();
session_destroy();
header("Location: ../landing-page.php");
exit();
