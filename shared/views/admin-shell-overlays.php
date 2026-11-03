    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top:1rem;font-weight:600;color:var(--text-dark);font-size:0.9rem;">Processing...</p>
    </div>

    <!-- QR Scanner Modal -->
    <div id="qrScannerModal" class="qr-scanner-backdrop">
        <div class="qr-scanner-card">
            <button id="closeQrScanner" class="qr-scanner-close" title="Close scanner">✕</button>
            <h3>Scan Return QR Code</h3>
            <p>Point the camera at the faculty member's QR code.</p>
            <div class="qr-video-wrap">
                <video id="qrVideo" playsinline autoplay></video>
                <!-- Scan guide overlay -->
                <div class="qr-scan-guide">
                    <div class="qr-scan-guide-box"></div>
                </div>
            </div>
            <p id="qrScanStatus">
                Initializing camera...
            </p>
        </div>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>

    <?php
    // Toast queue: messages the server wants shown once this page has loaded, in order.
    //   • After a real sign-in (landing-page.php arms the flag): a welcome line, then an overdue alert
    //     if anything is overdue. Refreshing / switching tabs finds no flag, so nothing repeats.
    //   • After a room save / archive / restore redirect (?room_added=1 …): that confirmation or error.
    //     (Equipment added/updated/error already toast through fixInventoryTab() further down.)
    $toast_queue = [];

    $show_login_toast = !empty($_SESSION['admin_login_toast']);
    unset($_SESSION['admin_login_toast']);
    if ($show_login_toast) {
        $lt_first = explode(' ', trim((string)$admin_name))[0] ?? '';
        $lt_msg   = 'Welcome back' . ($lt_first !== '' ? ', ' . $lt_first : '') . '.';
        $lt_n     = (int)$notif_unread;
        if ($lt_n > 0) {
            $lt_msg .= ' You have ' . $lt_n . ' unread notification' . ($lt_n === 1 ? '' : 's') . '.';
        }
        $toast_queue[] = $lt_msg;

        $ov_n = (int)($dash_data['overdue'] ?? 0);
        if ($ov_n > 0) {
            $toast_queue[] = 'Overdue alert: ' . $ov_n . ($ov_n === 1 ? ' item is' : ' items are')
                . ' currently overdue and need immediate attention.';
        }
    }

    $flash_toasts = [
        'notice_sent'   => 'Overdue notice sent to the borrower.',
        'loans_cleared' => 'The On Loan list was cleared. All loan records were deleted.',
        'room_added'    => 'Facility added to the registry.',
        'room_updated'  => 'Facility updated. Changes saved.',
        'room_archived' => 'Facility archived. It has been removed from the active registry.',
        'room_restored' => 'Facility restored. It is now back in the active registry.',
        'room_error'    => 'Error: Could not save facility. Please check required fields and try again.',
    ];
    $flash_strip = [];
    foreach ($flash_toasts as $flash_key => $flash_msg) {
        if (isset($_GET[$flash_key])) {
            $toast_queue[] = $flash_msg;
            $flash_strip[] = $flash_key;
        }
    }

    if ($toast_queue):
    ?>
        <div id="toast-queue" hidden data-strip="<?php echo htmlspecialchars(implode(',', $flash_strip), ENT_QUOTES); ?>">
            <?php foreach ($toast_queue as $tq_msg): ?>
                <span data-msg="<?php echo htmlspecialchars($tq_msg, ENT_QUOTES); ?>"></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


