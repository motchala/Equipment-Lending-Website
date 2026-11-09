    <!-- Admin Cancel Reservation Modal -->
    <div class="modal-overlay" id="adminCancelRrModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="adminCancelRrBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:420px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                    Cancel Reservation
                </h2>
                <button type="button" class="btn-close-custom" id="closeAdminCancelRrModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="admin-cancel-rr-alert" style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:.85rem;font-weight:500;"></div>
                <p id="adminCancelRrDesc" style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <p id="adminCancelRrWlNote" style="display:none;font-size:.82rem;padding:8px 12px;border-radius:6px;background:#f0f4ff;color:#374151;margin-bottom:1rem;border-left:3px solid #6366f1;"></p>
                <p style="font-size:.83rem;color:var(--text-light);margin-bottom:1rem;">
                    The faculty member will be notified by email. All faculty on the waitlist for this slot will also be notified that the slot is now available.
                </p>
                <input type="hidden" id="adminCancelRrId">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelAdminCancelRrBtn" style="padding:8px 16px;width:auto;">Go Back</button>
                    <button type="button" class="btn-submit-form" id="submitAdminCancelRrBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;background:var(--danger);">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Issue Review Modal -->
    <div class="modal-overlay" id="issueReviewModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="issueReviewModalBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:460px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Review Issue Report
                </h2>
                <button type="button" class="btn-close-custom" id="closeIssueReviewModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="issue-review-alert" style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:.85rem;font-weight:500;"></div>
                <p id="issueReviewDesc" style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <div class="form-group">
                    <label>Admin Notes <span style="font-size:.75rem;font-weight:400;">(optional)</span></label>
                    <textarea id="issueAdminNotes" class="form-control-custom" rows="3"
                        placeholder="Internal notes about this report or action taken…"
                        style="resize:vertical;"></textarea>
                </div>
                <div class="form-group" style="margin-top:.75rem;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
                        <input type="checkbox" id="issueSetMaintenance" style="width:16px;height:16px;">
                        Set facility to <strong>Maintenance</strong> mode
                    </label>
                    <small style="color:var(--text-light);font-size:.75rem;margin-top:4px;display:block;">
                        Only applies when resolving (not dismissing). You can change the facility status back any time from the Facilities registry.
                    </small>
                </div>
                <input type="hidden" id="issueReviewId">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1.25rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelIssueReviewBtn" style="padding:8px 16px;width:auto;">Cancel</button>
                    <button type="button" class="btn-submit-form" id="dismissIssueBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;background:#6b7280;">Dismiss</button>
                    <button type="button" class="btn-submit-form" id="resolveIssueBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;">Mark Resolved</button>
                </div>
            </div>
        </div>
    </div>

