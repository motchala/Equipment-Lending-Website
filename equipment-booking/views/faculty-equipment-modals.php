    <!-- ================================================================
     BORROW MODAL
     Opened from the selection bar on the Equipment tab ("Continue") once at least one
     item has been added. One form for one item or many: where (building > floor > room),
     when (date, start time, duration), an optional request letter for a later date, and
     who will collect. Built and driven by faculty-borrow-flow.js.
================================================================ -->
    <div class="modal-backdrop" id="bfModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="bfTitle">
        <div class="modal-box borrow-modal-box bf-box">
            <div class="modal-header bm-head">
                <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">inventory_2</span></span>
                <div class="bm-heading">
                    <h3 id="bfTitle">Borrow equipment</h3>
                    <p class="bm-sub">Tell us where and when you need it.</p>
                </div>
                <button type="button" class="modal-close-btn" data-bf="close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bfForm" class="bm-form" novalidate>
                    <!-- Items picked on the screen (quantities can still be changed here) -->
                    <div class="form-group">
                        <span class="form-label">Items to borrow</span>
                        <div class="bf-items" id="bfItems"></div>
                    </div>

                    <!-- Where: building > floor > room -->
                    <div class="form-group">
                        <span class="form-label" id="bfRoomCap">Room / location <span class="bf-star">*</span></span>
                        <input type="hidden" id="bfRoomId" value="">
                        <button type="button" class="bf-room-choice" id="bfRoomBtn" data-bf="open-room" aria-labelledby="bfRoomCap bfRoomLabel">
                            <span class="bf-room-ico" aria-hidden="true"><span class="material-symbols-outlined">location_on</span></span>
                            <span class="bf-room-text" id="bfRoomLabel"><span class="bf-room-ph">Choose a building and room</span></span>
                            <span class="material-symbols-outlined bf-room-chev" aria-hidden="true">chevron_right</span>
                        </button>
                        <p class="bm-hint" id="bfRoomHint" hidden></p>
                    </div>

                    <!-- When -->
                    <div class="bf-when">
                        <div class="form-group">
                            <label class="form-label" for="bfDate">Borrow date <span class="bf-star">*</span>
                                <span class="material-symbols-outlined bm-mode-info" id="bfTodayInfo" title="" aria-label="" tabindex="0" hidden>error</span>
                            </label>
                            <select id="bfDate" class="form-input bm-select"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="bfTime">Start time <span class="bf-star">*</span></label>
                            <select id="bfTime" class="form-input bm-select"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="bfDuration">Duration <span class="bf-star">*</span></label>
                            <select id="bfDuration" class="form-input bm-select"></select>
                        </div>
                    </div>
                    <div class="bm-summary" id="bfSummary" aria-live="polite"></div>
                    <p class="bm-avail" id="bfAvail" aria-live="polite"></p>

                    <!-- Request letter: only for a later date, recommended, never required -->
                    <div class="form-group bm-letter" id="bfLetter" hidden>
                        <span class="form-label">Request letter <span class="bm-opt">Recommended</span></span>
                        <label class="bm-drop" for="request_document">
                            <span class="material-symbols-outlined bm-drop-icon" aria-hidden="true">upload_file</span>
                            <span class="bm-drop-text">
                                <strong data-bm-drop-title>Choose a file or drag it here</strong>
                                <small>PDF, JPG, PNG or WEBP &middot; max 5 MB</small>
                            </span>
                            <input type="file" id="request_document" name="request_document"
                                accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </label>
                        <button type="button" class="pslc-trigger" data-letter-camera="request_document">
                            <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span> Take a photo of the letter
                        </button>
                        <p class="bm-hint">Recommended for bookings on a later date. You can still submit without one.</p>
                    </div>

                    <!-- Who will collect -->
                    <section class="form-group bm-collector-panel" aria-labelledby="bfCollectorTitle">
                        <div class="bm-collector-heading">
                            <span class="bm-collector-icon material-symbols-outlined" aria-hidden="true">person_pin_circle</span>
                            <div>
                                <span class="form-label" id="bfCollectorTitle">Who will collect the equipment?</span>
                                <p class="bm-collector-caption">Choose who will pick up this request.</p>
                            </div>
                        </div>
                        <div class="bm-collector-toggle" role="radiogroup" aria-labelledby="bfCollectorTitle">
                            <label class="bm-collector-opt">
                                <input type="radio" name="bf_collector_type" value="faculty" checked>
                                <span><strong>Faculty (me)</strong><small>I'll collect the equipment</small></span>
                            </label>
                            <label class="bm-collector-opt">
                                <input type="radio" name="bf_collector_type" value="student">
                                <span><strong>Student</strong><small>A student will collect it</small></span>
                            </label>
                        </div>
                        <div class="bm-collector-student" id="bfStudentFields" hidden>
                            <label class="form-label" for="bfStudentName">Student name</label>
                            <input type="text" class="form-input" id="bfStudentName"
                                placeholder="Last name, First name (e.g. Dela Cruz, Juan)" autocomplete="off">
                            <p class="bm-hint">The student will show the Claim QR to collect.</p>
                        </div>
                    </section>

                    <p class="bf-alert" id="bfError" role="alert" hidden></p>

                    <div class="bm-actions">
                        <button type="button" class="btn-cancel-acc" data-bf="close">Cancel</button>
                        <button type="submit" class="btn-save-acc" id="bfSubmit">
                            <span class="material-symbols-outlined">send</span> Submit Borrow Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- /bfModal -->
