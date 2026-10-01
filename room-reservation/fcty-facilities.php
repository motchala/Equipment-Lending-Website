<?php
/* ================================================================
   fcty-facilities.php — Facilities tab (Browse Facilities) partial
   Included by faculty-dashboard.php inside #rooms-browse.

   Flow:  Buildings (A · B · C)  →  Rooms by floor  →  Room details
                                                     →  Reserve / Report

   Icons: Material Symbols Outlined only (same set as the rest of the
   dashboard). Sizes are set in fcty-facilities.css — never inline.
================================================================ */
?>

<!-- ══════════════════════════════════════════════════════════════
     STEP 1 — Pick one of the three PUP buildings
     The three panels are rendered by fcty-facilities.js; the
     skeletons below show while the data loads.
══════════════════════════════════════════════════════════════ -->
<section class="fcty-view" id="fcty-buildings-view" aria-label="Choose a building">

    <div class="fcty-head-row fcty-landing-head">
        <h2 class="fcty-title">Choose a building</h2>
        <span class="fcty-pill is-live" id="fcty-landing-summary"></span>
    </div>

    <div class="fcty-load-error" id="fcty-load-error" role="alert" hidden>
        <span class="material-symbols-outlined" aria-hidden="true">error</span>
        <span>Couldn&rsquo;t load facilities.</span>
        <button type="button" id="fcty-load-retry">Try again</button>
    </div>

    <div class="fcty-panels" id="fcty-panels">
        <div class="fcty-panel is-skeleton" aria-hidden="true"></div>
        <div class="fcty-panel is-skeleton" aria-hidden="true"></div>
        <div class="fcty-panel is-skeleton" aria-hidden="true"></div>
    </div>

</section><!-- /#fcty-buildings-view -->


<!-- ══════════════════════════════════════════════════════════════
     STEP 2 — Rooms in the chosen building (one floor at a time)
══════════════════════════════════════════════════════════════ -->
<section class="fcty-view" id="fcty-rooms-view" style="display:none;" aria-label="Rooms">

    <nav class="fcty-crumbs" aria-label="Breadcrumb">
        <a href="#" id="fcty-rooms-back-facilities">Facilities</a>
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        <span id="fcty-rooms-breadcrumb-building" aria-current="page">Building</span>
    </nav>

    <header class="fcty-hero" id="fcty-hero">
        <div class="fcty-hero-img" id="fcty-hero-img"></div>
        <div class="fcty-hero-shade"></div>
        <div class="fcty-hero-row">
            <div class="fcty-hero-text">
                <h2 class="fcty-hero-title" id="fcty-rooms-hero-title">Building</h2>
                <p class="fcty-hero-sub" id="fcty-rooms-summary"></p>
            </div>
            <div class="fcty-switcher" id="fcty-switcher" role="tablist" aria-label="Switch building"></div>
        </div>
    </header>

    <div class="fcty-toolbar">
        <div class="fcty-floor-tabs" id="fcty-floor-tabs" role="tablist" aria-label="Floors"></div>
        <label class="fcty-toggle">
            <input type="checkbox" id="fcty-only-free">
            <span class="fcty-toggle-track" aria-hidden="true"></span>
            <span>Available only</span>
        </label>
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
                <span class="fcty-fact" id="fcty-modal-hint" hidden>
                    <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                    <span id="fcty-modal-hint-text"></span>
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