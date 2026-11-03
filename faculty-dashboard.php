<?php

require __DIR__ . '/shared/core/faculty-bootstrap.php';

require __DIR__ . '/equipment-booking/core/faculty-equipment-handlers.php';

require __DIR__ . '/dashboard/core/faculty-data.php';

require __DIR__ . '/activity/core/faculty-reservations-data.php';

// ── Notifications (live feed for the bell / modal) ─────────────────────────
// Built from this faculty member's own requests, reservations, waitlist,
// room issues and account activity — see faculty-notif-functions.php.
// Read/deleted state lives in tbl_faculty_notif_state; the modal is rendered
// client-side from this JSON and kept in sync via api/faculty-notif.php.
require_once __DIR__ . '/notifications/core/faculty-data.php';
$notifications = fnotif_build_list($conn, $_SESSION['faculty_id']);
$notif_count   = fnotif_count_unread($notifications);
$notif_json    = json_encode(
    ['notifications' => $notifications, 'unread_count' => $notif_count],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
);

require __DIR__ . '/settings/core/faculty-data.php';

require __DIR__ . '/equipment-booking/core/faculty-equipment-data.php';

$masked_email       = maskEmail($db_email);
$masked_backup      = maskEmail($db_backup_email);
$dob_display        = $db_dob ? date('F j, Y', strtotime($db_dob)) : '';
$dob_locked         = !empty($db_dob);
$gender_locked      = !empty($db_gender);
$nationality_locked = !empty($db_nationality);
$backup_locked      = !empty($db_backup_email);
$department_locked  = !empty($db_department);
$profile_pic_file = basename(str_replace('\\', '/', trim($db_profile_pic)));
$profile_pic_path = __DIR__ . '/uploads/profile_pictures/' . $profile_pic_file;
$profile_pic_url  = ($profile_pic_file !== '' && is_file($profile_pic_path))
    ? $uploads_url . 'profile_pictures/' . rawurlencode($profile_pic_file)
    : '';
?>
<?php require __DIR__ . '/shared/views/faculty-shell-top.php'; ?>
<?php require __DIR__ . '/shared/views/faculty-shell-styles.php'; ?>
<?php require __DIR__ . '/shared/views/faculty-shell-nav.php'; ?>
        <main class="app-main" id="appMain">
<?php require __DIR__ . '/equipment-booking/views/faculty-equipment-alerts.php'; ?>
<?php require __DIR__ . '/dashboard/views/faculty-screen.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/faculty-equipment-screen.php'; ?>
            <!-- ============================================================
         TAB: FACILITIES (ROOMS)
    ============================================================ -->
            <div class="tab-panel" id="panel-rooms">


                <!-- ── Sub: Browse Facilities ─────────────────────────────── -->
                <div class="lending-sub active" id="rooms-browse">
                    <div class="page-header-block">
                        <h2 class="page-title-sm">Browse Facilities</h2>
                        <p class="page-subtitle">Find a room, check its weekly schedule and reserve it for your class or event.</p>
                    </div>
                    <?php include __DIR__ . '/room-reservation/views/faculty-rooms-screen.php'; ?>
                </div><!-- /rooms-browse -->


            </div><!-- /panel-rooms -->

<?php require __DIR__ . '/activity/views/faculty-screen.php'; ?>
        </main><!-- /app-main -->
    </div><!-- /main-wrapper -->

<?php require __DIR__ . '/settings/views/faculty-screen.php'; ?>
<?php require __DIR__ . '/notifications/views/faculty-modals.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/faculty-equipment-modals.php'; ?>
<?php require __DIR__ . '/room-reservation/views/faculty-rooms-modals.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/faculty-equipment-modals-2.php'; ?>
<?php require __DIR__ . '/settings/views/faculty-modals.php'; ?>
<?php require __DIR__ . '/settings/views/faculty-scripts.php'; ?>
<?php require __DIR__ . '/shared/views/faculty-shell-overlays.php'; ?>
<?php require __DIR__ . '/shared/views/faculty-shell-scripts.php'; ?>
</body>

</html>