<?php require_once __DIR__ . '/shared/core/site-paths.php'; ?>
<?php require_once __DIR__ . '/equipment-booking/core/admin-functions.php'; ?>
<?php require_once __DIR__ . '/room-reservation/core/admin-rooms-functions.php'; ?>
<?php require_once __DIR__ . '/notifications/core/admin-data.php'; ?>
<?php require_once __DIR__ . '/auth/core/role-splash.php'; ?>
<?php
require __DIR__ . '/dashboard/core/admin-data.php';
require __DIR__ . '/dashboard/views/admin-render.php';

require __DIR__ . '/dashboard/core/admin-handlers.php';

require __DIR__ . '/equipment-booking/core/admin-equipment-fragment.php';

require __DIR__ . '/account-management/core/handlers.php';
?>

<?php
// ================= NOTIFICATIONS (live feed) =================
// Computed fresh on every load from tbl_requests / tbl_room_issues /
// tbl_inventory — see notif-functions.php. $notif_unread drives every
// badge on the page (bell, dropdown item, sidebar) so they always agree
// with what's inside the modal.
$notifications = notif_build_list($conn);
$notif_unread  = notif_count_unread($notifications);

// Live count per category — drives which filter chips are worth
// showing at all (no point offering a "Rooms" filter with 0 items).
$notif_cat_counts = ['request' => 0, 'overdue' => 0, 'room' => 0, 'system' => 0];
foreach ($notifications as $n) {
    if (isset($notif_cat_counts[$n['cat']])) $notif_cat_counts[$n['cat']]++;
}
?>
<?php
require __DIR__ . '/shared/core/document-helpers.php';
?>
<?php
require __DIR__ . '/equipment-booking/core/admin-equipment-return.php';

?>
<?php require __DIR__ . '/shared/views/admin-shell-top.php'; ?>
        <main id="app-main">
<?php require __DIR__ . '/dashboard/views/admin-screen.php'; ?>
<?php require __DIR__ . '/room-reservation/views/admin-rooms-screen.php'; ?>
<?php require __DIR__ . '/account-management/views/screen.php'; ?>
<?php require __DIR__ . '/activity/views/admin-screen.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-screen.php'; ?>
<?php require __DIR__ . '/settings/views/admin-screen.php'; ?>
<?php require __DIR__ . '/settings/views/admin-imports-screen.php'; ?>
        </main><!-- /app-main -->

    </div><!-- /app-body -->

<?php require __DIR__ . '/equipment-booking/views/admin-equipment-modals.php'; ?>
<?php require __DIR__ . '/account-management/views/modals.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-modals-2.php'; ?>
<?php require __DIR__ . '/room-reservation/views/admin-rooms-modals.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-modals-3.php'; ?>
<?php require __DIR__ . '/notifications/views/admin-modals.php'; ?>
<?php require __DIR__ . '/shared/views/admin-shell-overlays.php'; ?>
<?php require __DIR__ . '/settings/views/admin-modals.php'; ?>
<?php require __DIR__ . '/room-reservation/views/admin-rooms-modals-2.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-modals-4.php'; ?>
<?php require __DIR__ . '/shared/views/admin-shell-scripts.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-modals-5.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-scripts.php'; ?>
<?php require __DIR__ . '/room-reservation/views/admin-rooms-modals-3.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-modals-6.php'; ?>
<?php require __DIR__ . '/account-management/views/modals-2.php'; ?>
<?php require __DIR__ . '/account-management/views/scripts.php'; ?>
<?php require __DIR__ . '/equipment-booking/views/admin-equipment-scripts-2.php'; ?>
<?php require __DIR__ . '/shared/views/admin-shell-bottom.php'; ?>
</body>

</html>