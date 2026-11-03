<?php
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
if (!empty($_COOKIE[REMEMBER_ME_COOKIE])) {
    require_once __DIR__ . '/../../config/db.php';
    remember_me_remove_current(getDB());
}
session_unset();
session_destroy();
header("Location: ../../landing-page.php");
exit();
