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
     * Returns the correct src value for an equipment image.
     *
     * Handles three formats stored in tbl_inventory.image_path:
     *   1. Cloudinary URL  (https://res.cloudinary.com/…) — returned as-is; $root_url ignored
     *   2. Local path      (uploads/filename.jpg)         — prefixed with $root_url
     *   3. Empty / default                                — $root_url + 'uploads/default.png'
     *
     * The is_file() check is intentionally removed: Cloudinary URLs are always
     * valid remote URLs; for legacy local paths, the onerror fallback on each
     * <img> tag handles missing files gracefully.
     *
     * @param string|null $path     image_path as stored in tbl_inventory (may be null / empty)
     * @param string      $root_url site root URL with trailing slash, e.g. 'https://site.com/'
     *                              Pass '' when you need a bare path (rare).
     * @return string a URL or path, never empty
     */
    function equipment_image_path(?string $path, string $root_url = ''): string
    {
        $path = trim((string)$path);

        // Cloudinary URL — absolute, return as-is
        if (str_starts_with($path, 'https://')) {
            return $path;
        }

        // Empty, suspicious, or default placeholder
        if ($path === '' || str_contains($path, '..') || $path === EQUIPMENT_DEFAULT_IMAGE) {
            return $root_url . EQUIPMENT_DEFAULT_IMAGE;
        }

        return $root_url . $path;
    }
}
