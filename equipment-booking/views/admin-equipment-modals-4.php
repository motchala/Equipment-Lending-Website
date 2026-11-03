    <!-- Override Modal -->
    <div class="modal-overlay" id="overrideModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="overrideModalBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:440px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Override Request
                </h2>
                <button type="button" class="btn-close-custom" id="closeOverrideModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="override-alert"
                    style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:0.85rem;font-weight:500;">
                </div>
                <p id="overrideDesc" style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <!-- Context info shown when direction is fixed (Approved→Declined or Declined→Approved) -->
                <div id="overrideContextInfo"
                    style="display:none;padding:10px 12px;border-radius:6px;margin-bottom:1rem;font-size:0.83rem;font-weight:500;">
                </div>
                <div class="form-group" id="overrideStatusGroup" style="display:none;">
                    <label>New Status</label>
                    <select id="overrideNewStatus" class="form-control-custom">
                        <option value="Approved">Approved</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Override Reason <span style="color:var(--danger);">*</span></label>
                    <textarea id="overrideReason" class="form-control-custom" rows="3"
                        placeholder="Enter mandatory reason for this override (min. 5 characters)..."
                        style="resize:vertical;"></textarea>
                    <small id="overrideReasonHint" style="color:var(--text-light);font-size:0.75rem;">Minimum 5
                        characters required.</small>
                </div>
                <input type="hidden" id="overrideRequestId">
                <input type="hidden" id="overrideCurrentStatus">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelOverrideBtn"
                        style="padding:8px 16px;width:auto;">Cancel</button>
                    <button type="button" class="btn-submit-form" id="submitOverrideBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;" disabled>Apply Override</button>
                </div>
            </div>
        </div>
    </div>

