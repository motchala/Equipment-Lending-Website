<?php

declare(strict_types=1);

/**
 * shared/core/equipment-image.php
 *
 * One rule for "which picture does this equipment item show?":
 *   - its own photo, when it has one;
 *   - otherwise the default picture drawn for its CATEGORY (assets/images/equipment/*.svg);
 *   - and only when the caller does not know the category, the shared grey placeholder uploads/default.png.
 *
 * "No photo" = image_path empty / NULL / 'uploads/default.png'. Items that come from the Google Sheet
 * import have no photo, and the admin "Add item" form stores uploads/default.png for a new item (and
 * "Remove image" reverts to it). That stored value is never rewritten: the category picture is chosen
 * when the page is drawn, so changing an item's category (or later uploading a photo) just works.
 */

if (!defined('EQUIPMENT_DEFAULT_IMAGE')) {
    define('EQUIPMENT_DEFAULT_IMAGE', 'uploads/default.png');
}

if (!defined('EQUIPMENT_CATEGORY_IMAGE_DIR')) {
    define('EQUIPMENT_CATEGORY_IMAGE_DIR', 'assets/images/equipment/');
}

if (!function_exists('equipment_category_image')) {
    /**
     * The default picture for a category, as a path relative to the project root.
     *
     *   computer-laboratories.svg  Computer Laboratories (computers, network, printers)
     *   dispensing-room.svg        Dispensing Room, Power, Electronics (meters, power supplies, transformers)
     *   chem-lab.svg               Chem Lab, Lab Equipment
     *   audio-visual.svg           Audio-Visual Equipment, Audio/Visual (projectors, speakers)
     *   general.svg                any other / new category
     *
     * Categories are free text that admins can add, so the match is by keyword, not by exact name.
     * To give a new category its own picture: add the .svg to assets/images/equipment/ and one line here.
     */
    function equipment_category_image(?string $category): string
    {
        static $cache = [];
        $key = strtolower(trim((string)$category));
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $c = trim((string)preg_replace('/[^a-z0-9]+/', ' ', $key));
        $file = 'general';
        if ($c !== '') {
            if (preg_match('/audio|visual|projector|speaker|sound|video|\bav\b/', $c)) {
                $file = 'audio-visual';
            } elseif (preg_match('/comput|network|\bict\b|\bit\b|software|hardware|\bpc\b/', $c)) {
                $file = 'computer-laboratories';
            } elseif (preg_match('/chem|lab equipment|science|biolog|physic/', $c)) {
                $file = 'chem-lab';
            } elseif (preg_match('/dispens|electr|power|meter|volt|instrument|transformer/', $c)) {
                $file = 'dispensing-room';
            }
        }
        $rel = EQUIPMENT_CATEGORY_IMAGE_DIR . $file . '.svg';
        // Picture file missing on the server (partial upload)? Fall back to the old placeholder.
        if (!is_file(dirname(__DIR__, 2) . '/' . $rel)) {
            $rel = EQUIPMENT_DEFAULT_IMAGE;
        }
        return $cache[$key] = $rel;
    }
}

if (!function_exists('equipment_image_path')) {
    /**
     * Returns the correct src value for an equipment image.
     *
     * Handles three formats stored in tbl_inventory.image_path:
     *   1. Cloudinary URL  (https://res.cloudinary.com/…) — returned as-is; $root_url ignored
     *   2. Local path      (uploads/filename.jpg)         — prefixed with $root_url
     *   3. Empty / default (no photo)                     — the category picture when $category is given
     *                                                       (even ''), otherwise 'uploads/default.png'
     *
     * The is_file() check is intentionally removed: Cloudinary URLs are always
     * valid remote URLs; for legacy local paths, the onerror fallback on each
     * <img> tag handles missing files gracefully.
     *
     * @param string|null $path     image_path as stored in tbl_inventory (may be null / empty)
     * @param string      $root_url site root URL with trailing slash, e.g. 'https://site.com/'
     *                              Pass '' when you need a bare path (rare).
     * @param string|null $category the item's category. Pass it wherever a picture is DISPLAYED so an
     *                              item without a photo shows its category picture. Leave it out where
     *                              the caller needs to tell "has a photo" from "has none" (the admin
     *                              edit form does).
     * @return string a URL or path, never empty
     */
    function equipment_image_path(?string $path, string $root_url = '', ?string $category = null): string
    {
        $path = trim((string)$path);

        // Cloudinary URL — absolute, return as-is
        if (str_starts_with($path, 'https://')) {
            return $path;
        }

        // Empty, suspicious, or default placeholder
        if ($path === '' || str_contains($path, '..') || $path === EQUIPMENT_DEFAULT_IMAGE) {
            return $root_url . ($category === null ? EQUIPMENT_DEFAULT_IMAGE : equipment_category_image($category));
        }

        return $root_url . $path;
    }
}
