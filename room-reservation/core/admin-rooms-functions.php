<?php
require_once __DIR__ . '/../../shared/core/activity-logger.php'; // activity log helpers

/**
 * admin-rooms-functions.php
 * Room Registry — admin-side CRUD handlers for tbl_campuses, tbl_buildings, tbl_rooms.
 * Included by admin-dashboard.php immediately after admin-functions.php.
 *
 * DEPENDENCY: equipment-booking/core/admin-functions.php MUST be included first.
 *   That file sets up $conn (mysqli), $root_url, csrf_verify(), and the session guard.
 *   This file does NOT call session_start(), getDB(), or any config bootstrapping itself.
 *
 * Conventions match equipment-booking/core/admin-functions.php exactly:
 *   - ini_set / error_reporting set at top
 *   - Session guard already run by admin-functions.php (included first)
 *   - $conn already available from admin-functions.php
 *   - All POST handlers call csrf_verify() before touching input
 *   - All user-input DB access uses prepared statements with bind_param
 *   - Redirect after POST with ?room_added=1 / ?room_updated=1 etc.
 *   - Soft-delete (is_archived = 1) first; permanent delete is a separate,
 *     Super-Admin-only action that only works on rooms already archived
 *   - Room lock (is_locked): Admin + Super Admin; archive / permanent delete: Super Admin only
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// ── Dependency guard ─────────────────────────────────────────────────────
// $conn must already be set by equipment-booking/core/admin-functions.php.
// If it is not, the include order is wrong — fail loudly rather than
// producing a silent "Call to a member function prepare() on null" later.
if (empty($conn) || !($conn instanceof mysqli)) {
    error_log('[PUPSync] admin-rooms-functions.php included before admin-functions.php — $conn not available.');
    http_response_code(500);
    exit('Server configuration error. Please contact the administrator.');
}

// $conn and $root_url are already set by admin-functions.php


require __DIR__ . '/admin-rooms-helpers.php';

require __DIR__ . '/admin-rooms-handlers.php';

require __DIR__ . '/admin-rooms-data.php';
