    <!-- Confirmation Modal -->
    <div class="modal-backdrop" id="confirmationModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">task_alt</span>Confirm Changes
                </h3>
                <button class="modal-close-btn" data-action="close-confirmation-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Please review the
                    changes you're about to make:</p>
                <div class="changes-summary" id="changesSummary"></div>
                <div class="warning-box">
                    <span class="material-symbols-outlined" style="color:var(--color-on-warning-container);flex-shrink:0;">warning</span>
                    <div><strong style="color:var(--color-on-warning-container);display:block;margin-bottom:4px;">Important Notice</strong>
                        <p style="color:var(--color-on-warning-container);margin:0;font-size:0.875rem;" id="warningMessage">Some changes cannot be
                            reversed once saved.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-confirmation-modal">Cancel</button>
                <button class="btn-save-acc" id="confirmChangesBtn" data-action="confirm-changes">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Confirm
                    &amp; Save
                </button>
            </div>
        </div>
    </div>

