<?php
/* ================================================================
   fcty-facilities.php — Facilities tab (Browse Facilities) partial
   Included by faculty-dashboard.php inside #rooms-browse.

   Flow:  Campus  →  Building  →  Rooms (by floor)  →  Room details
                                                       →  Reserve / Report

   Icons: Material Symbols Outlined only (same set as the rest of the
   dashboard). Sizes are set in fcty-facilities.css — never inline.
================================================================ */
?>

<!-- ══════════════════════════════════════════════════════════════
     STEP 1 — Choose a campus
══════════════════════════════════════════════════════════════ -->
<section class="fcty-view" id="fcty-campus-view" aria-label="Choose a campus">

    <div class="fcty-load-error" id="fcty-load-error" role="alert" hidden>
        <span class="material-symbols-outlined" aria-hidden="true">error</span>
        <span>Couldn&rsquo;t load facilities.</span>
        <button type="button" id="fcty-load-retry">Try again</button>
    </div>

    <div class="fcty-campus-grid">

        <a class="fcty-campus-card" href="#" data-fcty-campus="main" role="button"
            aria-label="PUP Main campus">
            <div class="fcty-campus-media"
                style="background-image:url('assets/images/faculty/pup-main-image.jpg');"></div>
            <div class="fcty-campus-body">
                <span class="fcty-tile-icon" aria-hidden="true">
                    <span class="material-symbols-outlined">account_balance</span>
                </span>
                <div class="fcty-campus-text">
                    <h3 class="fcty-campus-name">PUP Main</h3>
                    <p class="fcty-meta" data-fcty-campus-stats="main">Main Campus</p>
                </div>
                <span class="fcty-go" aria-hidden="true">
                    <span class="material-symbols-outlined">arrow_forward</span>
                </span>
            </div>
        </a>

        <a class="fcty-campus-card" href="#" data-fcty-campus="cite" role="button"
            aria-label="PUP CITE campus">
            <div class="fcty-campus-media"
                style="background-image:url('assets/images/faculty/pup-cite-image.jpg');"></div>
            <div class="fcty-campus-body">
                <span class="fcty-tile-icon" aria-hidden="true">
                    <span class="material-symbols-outlined">engineering</span>
                </span>
                <div class="fcty-campus-text">
                    <h3 class="fcty-campus-name">PUP CITE</h3>
                    <p class="fcty-meta" data-fcty-campus-stats="cite">Engineering Campus</p>
                </div>
                <span class="fcty-go" aria-hidden="true">
                    <span class="material-symbols-outlined">arrow_forward</span>
                </span>
            </div>
        </a>

    </div>
</section><!-- /#fcty-campus-view -->


<!-- ══════════════════════════════════════════════════════════════
     STEP 2 — Choose a building
══════════════════════════════════════════════════════════════ -->
<section class="fcty-view" id="fcty-building-view" style="display:none;" aria-label="Choose a building">

    <header class="fcty-head">
        <nav class="fcty-crumbs" aria-label="Breadcrumb">
            <a href="#" id="fcty-breadcrumb-back">Facilities</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span id="fcty-breadcrumb-campus" aria-current="page">Campus</span>
        </nav>
        <div class="fcty-head-row">
            <h2 class="fcty-title" id="fcty-building-title">Select a building</h2>
            <span class="fcty-pill" id="fcty-building-count"></span>
        </div>
    </header>

    <div class="fcty-building-grid" id="fcty-building-grid"></div>

</section><!-- /#fcty-building-view -->


<!-- ══════════════════════════════════════════════════════════════
     STEP 3 — Rooms in a building (one floor at a time)
══════════════════════════════════════════════════════════════ -->
<section class="fcty-view" id="fcty-rooms-view" style="display:none;" aria-label="Rooms">

    <header class="fcty-head">
        <nav class="fcty-crumbs" aria-label="Breadcrumb">
            <a href="#" id="fcty-rooms-back-facilities">Facilities</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <a href="#" id="fcty-rooms-back-campus">Campus</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span id="fcty-rooms-breadcrumb-building" aria-current="page">Building</span>
        </nav>
        <div class="fcty-head-row">
            <h2 class="fcty-title" id="fcty-rooms-hero-title">Building</h2>
            <span class="fcty-pill" id="fcty-rooms-summary"></span>
        </div>
    </header>

    <div class="fcty-toolbar">
        <div class="fcty-floor-tabs" id="fcty-floor-tabs" role="tablist" aria-label="Floors"></div>
    </div>

    <div class="fcty-room-grid" id="fcty-rooms-floors" role="tabpanel" aria-label="Rooms on this floor"></div>

</section><!-- /#fcty-rooms-view -->


<!-- ══════════════════════════════════════════════════════════════
     Room details dialog
══════════════════════════════════════════════════════════════ -->
<div class="fcty-overlay" id="fcty-room-modal" aria-hidden="true">
    <div class="fcty-dialog" role="dialog" aria-modal="true" aria-labelledby="fcty-modal-room-name">

        <header class="fcty-dialog-head">
            <span class="fcty-tile-icon" aria-hidden="true">
                <span class="material-symbols-outlined">meeting_room</span>
            </span>
            <div class="fcty-dialog-heading">
                <h3 class="fcty-dialog-title" id="fcty-modal-room-name">Room</h3>
                <p class="fcty-dialog-sub" id="fcty-modal-location"></p>
            </div>
            <button class="fcty-icon-btn" id="fcty-modal-close" type="button" aria-label="Close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>

        <div class="fcty-dialog-body">

            <div class="fcty-facts">
                <span class="fcty-status" id="fcty-modal-availability">
                    <span class="fcty-dot"></span>
                    <span id="fcty-modal-availability-text">Checking&hellip;</span>
                </span>
                <span class="fcty-fact">
                    <span class="material-symbols-outlined" aria-hidden="true">chair</span>
                    <span><strong id="fcty-modal-capacity">&mdash;</strong> seats</span>
                </span>
            </div>

            <div class="fcty-seg" role="tablist" aria-label="Schedule range">
                <button class="fcty-seg-btn active" type="button" data-schedule-tab="daily"
                    role="tab" aria-selected="true">Today</button>
                <button class="fcty-seg-btn" type="button" data-schedule-tab="weekly"
                    role="tab" aria-selected="false">This week</button>
            </div>

            <div class="fcty-schedule-panel active" data-schedule-panel="daily">
                <p class="fcty-day-label" id="fcty-modal-day-label">Today</p>
                <div class="fcty-schedule-list" id="fcty-modal-daily-list"></div>
            </div>

            <div class="fcty-schedule-panel" data-schedule-panel="weekly">
                <div class="fcty-week-list" id="fcty-modal-weekly-grid"></div>
            </div>

        </div>

        <footer class="fcty-dialog-foot">
            <button class="fcty-btn fcty-btn-ghost" id="fcty-modal-report" type="button">
                <span class="material-symbols-outlined" aria-hidden="true">flag</span>
                Report issue
            </button>
            <button class="fcty-btn fcty-btn-primary" id="fcty-modal-reserve" type="button">
                <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                Reserve
            </button>
        </footer>

    </div>
</div><!-- /#fcty-room-modal -->


<!-- ══════════════════════════════════════════════════════════════
     Reserve / Report dialog — content is injected by fcty-facilities.js
══════════════════════════════════════════════════════════════ -->
<div class="fcty-overlay" id="fcty-reservation-panel" aria-hidden="true"></div>