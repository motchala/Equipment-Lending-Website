<?php
// Defines PS_ROOT: the absolute path of the site root, so files that moved into module folders never depend on how deep they sit.
if (!defined('PS_ROOT')) {
    define('PS_ROOT', dirname(__DIR__, 2));
}
