    <!-- ── Choose a Room Modal ─────────────────────────────────────────────
         Opened from the Room field of the Borrow Request modal. Shows only rooms the
         admin left Available; rooms another faculty has reserved for the chosen date and
         time stay visible but cannot be picked. The content is built by RoomPicker in
         faculty-dashboard.js. -->
    <div class="modal-backdrop rp-backdrop" id="roomPickModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="roomPickTitle">
        <div class="modal-box rp-box">
            <div class="modal-header bm-head">
                <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">meeting_room</span></span>
                <div class="bm-heading">
                    <h3 id="roomPickTitle">Choose a room</h3>
                    <p class="bm-sub" id="roomPickSub">Where will the equipment be used?</p>
                </div>
                <button type="button" class="modal-close-btn" data-rp-close aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="rp-tools">
                <label class="rp-search">
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <input type="search" id="roomPickSearch" placeholder="Search rooms" autocomplete="off" aria-label="Search rooms">
                </label>
                <div class="rp-tabs" id="roomPickTabs" role="tablist" aria-label="Buildings" hidden></div>
            </div>
            <div class="rp-scroll" id="roomPickBody" aria-live="polite"></div>
            <div class="rp-foot">
                <div class="rp-legend" aria-hidden="true">
                    <span class="rp-key"><i class="rp-dot rp-dot--free"></i>Free</span>
                    <span class="rp-key"><i class="rp-dot rp-dot--taken"></i>Reserved</span>
                </div>
                <span class="rp-count" id="roomPickCount"></span>
                <button type="button" class="btn-cancel-acc" data-rp-close>Cancel</button>
            </div>
        </div>
    </div><!-- /roomPickModal -->

