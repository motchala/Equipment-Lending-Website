    <!-- ══════════════════════════════════════════════════════
         ROOM SCHEDULE MODAL
         ══════════════════════════════════════════════════════ -->
    <div id="roomScheduleModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="rsm-title">
        <div class="rsm-card">
            <div class="rsm-head">
                <div class="rsm-head-icon">
                    <span class="material-symbols-outlined">calendar_month</span>
                </div>
                <h3 id="rsm-title">Facility Schedule</h3>
                <button class="rsm-close-btn" data-action="close-room-schedule" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="rsm-info-row">
                <span class="rsm-room-meta" id="rsm-meta"></span>
                <div class="rsm-week-ctrl">
                    <button type="button" class="rsm-today-btn" data-action="room-schedule-today">Today</button>
                    <button class="rsm-week-nav-btn" id="rsm-prev" data-action="room-schedule-nav" data-dir="-1" title="Previous week" aria-label="Previous week">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <span class="rsm-week-lbl" aria-live="polite">
                        <b id="rsm-week-lbl"></b>
                        <small id="rsm-week-sub"></small>
                    </span>
                    <button class="rsm-week-nav-btn" id="rsm-next" data-action="room-schedule-nav" data-dir="1" title="Next week" aria-label="Next week">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
            <div class="rsm-mobile-daytabs" id="rsm-daytabs" role="group" aria-label="Day of the week"></div>
            <div class="rsm-sync-note hidden" id="rsm-sync-note">Couldn't refresh just now — showing the reservations loaded with this page. Reopen the schedule or reload to retry.</div>
            <div class="rsm-body" id="rsm-body">
                <div class="rsm-grid" id="rsm-grid">
                    <div class="rsm-grid-head" id="rsm-grid-head"></div>
                    <div class="rsm-grid-body" id="rsm-grid-body"></div>
                </div>
            </div>
            <div class="rsm-foot">
                <div class="rsm-legend">
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot class"></div> Class
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot reserved"></div> Reserved
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot past"></div> Already finished
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot avail"></div> Available
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-line"></div> Now
                    </div>
                </div>
                <button type="button" class="btn-cancel-acc" data-action="close-room-schedule">Close</button>
            </div>
        </div><!-- /.rsm-card -->
    </div><!-- /#roomScheduleModal -->

