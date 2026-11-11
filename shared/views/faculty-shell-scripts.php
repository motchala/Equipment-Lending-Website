    <script nonce="<?php echo $csp_nonce; ?>">
        window.REQUESTS_DATA = <?php echo $requests_json; ?>;
        window.BOOKING_META = <?php echo $bk_meta_json; ?>;
        window.FNOTIF_DATA = <?php echo $notif_json; ?>;
        window.USER_SLUG = '<?php echo $user_slug; ?>';
        window.OVERDUE_COUNT = <?php echo (int)$stat_overdue; ?>;
        window.SERVER_BASE_URL = '<?php
                                    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";

                                    // Get the real network IP of the laptop (not loopback 127.0.0.1)
                                    $server_addr = $_SERVER["SERVER_ADDR"] ?? "127.0.0.1";
                                    if ($server_addr === "127.0.0.1" || $server_addr === "::1") {
                                        $server_addr = gethostbyname(gethostname());
                                    }

                                    $port    = $_SERVER["SERVER_PORT"] ?? "80";
                                    $portStr = ($port == "80" || $port == "443") ? "" : ":" . $port;
                                    $path    = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
                                    echo $scheme . "://" . $server_addr . $portStr . $path . "/";
                                    ?>';
    </script>
    <!-- Mobile Nav Backdrop -->
    <div class="nav-backdrop" id="navBackdrop"></div>

    <script src="shared/assets/js/logout-modal.js?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/js/logout-modal.js'); ?>"></script>
    <script src="shared/assets/js/letter-camera.js?v=<?php echo @filemtime(PS_ROOT . '/shared/assets/js/letter-camera.js'); ?>"></script>
    <script src="dashboard/assets/js/faculty-dashboard.js?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/js/faculty-dashboard.js'); ?>"></script>
    <script src="equipment-booking/assets/js/faculty-borrow-flow.js?v=<?php echo @filemtime(PS_ROOT . '/equipment-booking/assets/js/faculty-borrow-flow.js'); ?>"></script>
    <script src="room-reservation/assets/js/fcty-facilities.js"></script>
    <script src="room-reservation/assets/js/fcty-class-schedule.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/fcty-class-schedule.js'); ?>"></script>
    <script src="dashboard/assets/js/faculty-dashboard-responsive.js?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/js/faculty-dashboard-responsive.js'); ?>" defer></script>
    <script src="dashboard/assets/js/faculty-dashboard-mobile.js?v=<?php echo @filemtime(PS_ROOT . '/dashboard/assets/js/faculty-dashboard-mobile.js'); ?>" defer></script>
