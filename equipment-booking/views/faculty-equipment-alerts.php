
            <!-- Success Alert -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-banner alert-success" id="success-alert">
                    <span class="material-symbols-outlined">check_circle</span>
                    <strong>Success!</strong> Your borrow request has been submitted for approval.
                    <button class="alert-close" data-action="dismiss-alert" data-target="success-alert" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Borrow request could not be filed -->
            <?php if (!empty($_GET['borrow_error'])): ?>
                <div class="alert-banner alert-error" id="borrow-error-alert" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span><strong>Could not submit your request.</strong> <?php echo htmlspecialchars(mb_substr((string)$_GET['borrow_error'], 0, 300)); ?></span>
                    <button class="alert-close" data-action="dismiss-alert" data-target="borrow-error-alert" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Overdue Toast — bottom-center overlay, doesn't affect layout.
                 Shown once per login session (server-gated below); auto-
                 dismisses after 8s via JS (see checkOverdueState/showOverdueToast). -->
            <div class="overdue-toast" id="overdue-alert" data-should-show="<?php echo $show_overdue_toast ? '1' : '0'; ?>" role="alert" aria-live="assertive">
                <span class="material-symbols-outlined">warning</span>
                <div class="overdue-toast-text">
                    <strong>Borrowing Blocked:</strong> You have overdue equipment. Return it to the Admin Office before you can borrow again.
                </div>
                <button class="overdue-toast-close" data-action="dismiss-alert" data-target="overdue-alert" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

