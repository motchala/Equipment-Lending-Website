    <!-- ================================================================
         REQUESTS PANEL MODALS — outside app-body for correct fixed positioning
    ================================================================ -->

    <!-- ================================================================
             MODALS — REQUESTS PANEL  (UI only — functions wired in Phase 4)
        ================================================================ -->

    <!-- MODAL: APPROVE REQUEST -->
    <div class="ps-modal-backdrop" id="ps-approve-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--success">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <h3>Approve Request</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-approve-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="approve-request-id" value="">
                <div id="ps-approve-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-approve-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="approve-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="approve-equipment">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Date Needed</span><span class="ps-rsv" id="approve-date-needed">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Return Due Date</label>
                    <input class="ps-form-control" type="date" id="approve-due-date" readonly disabled>
                    <div class="ps-form-hint">Set by the borrower when they submitted the request.</div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason / Note <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters — kept in the arbitration log)</span></label>
                    <textarea class="ps-form-control" id="approve-reason" rows="2" placeholder="e.g. Item available, request meets policy..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-approve-modal">Cancel</button>
                <button class="ps-btn ps-btn--success" id="ps-approve-confirm-btn">
                    <span class="material-symbols-outlined">check</span> Approve Request
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: DECLINE REQUEST -->
    <div class="ps-modal-backdrop" id="ps-decline-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">cancel</span>
                </div>
                <h3>Decline Request</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-decline-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="decline-request-id" value="">
                <div id="ps-decline-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-decline-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="decline-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="decline-equipment">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason for Declining <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters)</span></label>
                    <textarea class="ps-form-control" id="decline-reason" rows="3" placeholder="e.g. Item is currently in use by another borrower..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-decline-modal">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="ps-decline-confirm-btn">
                    <span class="material-symbols-outlined">close</span> Decline Request
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: CANCEL SCHEDULED BOOKING (Equipment › On Loan › Waiting) -->
    <div class="ps-modal-backdrop" id="ps-cancel-booking-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">event_busy</span>
                </div>
                <h3>Cancel Booking</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-cancel-booking-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="cancel-booking-id" value="">
                <div id="ps-cancel-booking-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-cancel-booking-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="cancel-booking-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="cancel-booking-equipment">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Starts</span><span class="ps-rsv" id="cancel-booking-start">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Return by</span><span class="ps-rsv" id="cancel-booking-due">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason for Cancelling <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters)</span></label>
                    <textarea class="ps-form-control" id="cancel-booking-reason" rows="3" placeholder="e.g. Equipment is reserved for a university event on that date..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-cancel-booking-modal">Keep Booking</button>
                <button class="ps-btn ps-btn--danger" id="ps-cancel-booking-confirm-btn" disabled>
                    <span class="material-symbols-outlined">event_busy</span> Cancel Booking
                </button>
            </div>
        </div>
    </div>

