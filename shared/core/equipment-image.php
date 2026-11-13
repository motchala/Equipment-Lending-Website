<?php

declare(strict_types=1);

/**
 * shared/core/equipment-image.php
 *
 * One rule for "which picture does this equipment item show?":
 *   - its own photo, when it has one and the file is really on the server;
 *   - otherwise the shared default picture, uploads/default.png.
 *
 * Items that come from the Google Sheet import have no photo (their image_path is empty), and an
 * item's file can also go missing from /uploads. Both end up with the default picture instead of an
 * icon placeholder or a broken image. uploads/default.png is the same picture the admin "Add item"
 * form already gives a new item, and the one "Remove image" reverts to.
 */

if (!defined('EQUIPMENT_DEFAULT_IMAGE')) {
    define('EQUIPMENT_DEFAULT_IMAGE', 'uploads/default.png');
}

if (!function_exists('equipment_image_path')) {
    /**
     * @param string|null $path image_path as stored in tbl_inventory (may be null / empty)
     * @return string a path relative to the project root, never empty
     */
    function equipment_image_path(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '' || str_contains($path, '..')) {
            return EQUIPMENT_DEFAULT_IMAGE;
        }
        // A path that points at a file that is no longer there would be a broken image.
        if (!is_file(dirname(__DIR__, 2) . '/' . ltrim($path, '/'))) {
            return EQUIPMENT_DEFAULT_IMAGE;
        }
        return $path;
    }
}
