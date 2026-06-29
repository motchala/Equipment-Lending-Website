<?php
require_once __DIR__ . '/../config/security-headers.php';
require_once __DIR__ . '/../config/session.php';
session_unset();
session_destroy();
header("Location: ../landing-page.php");
exit();
