    <!-- ── Choose a Room Modal ─────────────────────────────────────────────
         Opened from the Room field of the Borrow modal. Same steps as the student portal:
         pick a building (with its photo), then a floor, then a room. Only rooms the admin
         left Available are listed; a room another faculty has reserved for the chosen date
         and time stays visible but cannot be picked. "Outside" and the faculty's own room
         reservations are offered on the first step. Content is built by faculty-borrow-flow.js. -->
    <div class="modal-backdrop bf-rp-backdrop" id="bfRoomPicker" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="bfRpTitle">
        <div class="modal-box bf-rp-box">
            <div class="modal-header bm-head">
                <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">meeting_room</span></span>
                <div class="bm-heading">
                    <h3 id="bfRpTitle">Choose a room</h3>
                    <p class="bm-sub" id="bfRpSub">Select a building, floor and room.</p>
                </div>
                <button type="button" class="modal-close-btn" data-bf="rp-close" aria-label="Close room selector">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="bf-rp-body" id="bfRpBody" aria-live="polite"></div>
            <div class="bf-rp-foot">
                <div class="bf-rp-legend" aria-hidden="true">
                    <span><i class="bf-dot bf-dot--free"></i>Free</span>
                    <span><i class="bf-dot bf-dot--taken"></i>Reserved</span>
                </div>
                <button type="button" class="btn-cancel-acc" data-bf="rp-close">Cancel</button>
            </div>
        </div>
    </div><!-- /bfRoomPicker -->
