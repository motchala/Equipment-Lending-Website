<?php
/* ================================================================
   fcty-facilities.php — Facilities tab panel partial
   Include in faculty-dashboard.php with:
       <?php include 'fcty-facilities.php'; ?>

   Replaces the entire:
       <!-- TAB: FACILITIES (ROOMS) --> ... <!-- /panel-rooms -->
   block in the main dashboard.
================================================================ */
?>

<!-- ============================================================
     TAB: FACILITIES (ROOMS)  — fcty-facilities partial
============================================================ -->
<div class="tab-panel" id="panel-rooms">

    <!-- ══════════════════════════════════════════════════════════
         VIEW 1 — Campus Selection (shown by default)
    ══════════════════════════════════════════════════════════════ -->
    <div class="fcty-view" id="fcty-campus-view">
        <div class="fcty-split">

            <!-- ── PUP MAIN ──────────────────────────────────── -->
            <a class="fcty-campus-card"
                href="#"
                data-fcty-campus="main"
                role="button"
                aria-label="Select PUP Main Campus — manage buildings and rooms">

                <!-- Background image — swap src for a real PUP MAIN photo -->
                <div class="fcty-card-bg"
                    style="background-image: url('css/images-design-faculty/pup-main-image.jpg');">
                </div>
                <div class="fcty-card-overlay"></div>

                <div class="fcty-card-content">
                    <div class="fcty-card-badge">
                        <span class="material-symbols-outlined">account_balance</span>
                        <span class="fcty-badge-text">Main Campus</span>
                    </div>
                    <h2 class="fcty-card-title">PUP MAIN</h2>
                    <p class="fcty-card-desc">
                        Manage academic buildings, administrative offices,
                        and central university facilities.
                    </p>
                    <div class="fcty-card-cta">
                        <span>Manage Facilities</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </div>
                </div>
            </a>

            <!-- ── PUP CITE ───────────────────────────────────── -->
            <a class="fcty-campus-card"
                href="#"
                data-fcty-campus="cite"
                role="button"
                aria-label="Select PUP CITE Engineering Campus — manage buildings and rooms">

                <!-- Background image — swap src for a real PUP CITE photo -->
                <div class="fcty-card-bg"
                    style="background-image: url('css/images-design-faculty/pup-cite-image.jpg');">
                </div>
                <div class="fcty-card-overlay"></div>

                <div class="fcty-card-content">
                    <div class="fcty-card-badge">
                        <span class="material-symbols-outlined">engineering</span>
                        <span class="fcty-badge-text">Engineering Campus</span>
                    </div>
                    <h2 class="fcty-card-title">PUP CITE</h2>
                    <p class="fcty-card-desc">
                        Manage technical laboratories, engineering workshops,
                        and specialized equipment facilities.
                    </p>
                    <div class="fcty-card-cta">
                        <span>Manage Facilities</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </div>
                </div>
            </a>

        </div>
    </div><!-- /#fcty-campus-view -->


    <!-- ══════════════════════════════════════════════════════════
         VIEW 2 — Building Selection (hidden; JS shows on campus click)
    ══════════════════════════════════════════════════════════════ -->
    <div class="fcty-building-view" id="fcty-building-view" style="display: none;">

        <!-- Breadcrumb + page header -->
        <div class="fcty-building-header">
            <nav class="fcty-breadcrumb" aria-label="Breadcrumb">
                <a href="#" id="fcty-breadcrumb-back">Facilities</a>
                <span class="material-symbols-outlined">chevron_right</span>
                <span id="fcty-breadcrumb-campus">PUP MAIN</span>
            </nav>
            <h1 class="fcty-building-title" id="fcty-building-title">
                PUP MAIN &mdash; Select Building
            </h1>
            <p class="fcty-building-subtitle">
                Select a building to view available rooms and facilities.
            </p>
        </div>

        <!-- Building Carousel -->
        <div class="fcty-carousel-wrap" id="fcty-carousel-wrap" tabindex="0"
            aria-label="Building carousel — use arrow keys to navigate">

            <!-- Slide track — populated by fcty-facilities.js -->
            <div class="fcty-carousel-inner" id="fcty-carousel-inner"></div>

            <!-- Arrow nav buttons -->
            <button class="fcty-carousel-arrow fcty-prev" id="fcty-prev"
                aria-label="Previous building">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <button class="fcty-carousel-arrow fcty-next" id="fcty-next"
                aria-label="Next building">
                <span class="material-symbols-outlined">chevron_right</span>
            </button>

            <!-- Pagination dots — populated by fcty-facilities.js -->
            <div class="fcty-carousel-dots" id="fcty-carousel-dots"
                role="tablist" aria-label="Carousel pagination"></div>

        </div><!-- /#fcty-carousel-wrap -->

    </div><!-- /#fcty-building-view -->


    <!-- ══════════════════════════════════════════════════════════
         VIEW 3 — Building Rooms View (hidden; JS shows on building select)
    ══════════════════════════════════════════════════════════════ -->
    <div class="fcty-rooms-view" id="fcty-rooms-view" style="display: none;">

        <!-- Breadcrumb — sits above the red hero banner -->
        <nav class="fcty-breadcrumb fcty-rooms-breadcrumb" aria-label="Breadcrumb">
            <a href="#" id="fcty-rooms-back-facilities">Facilities</a>
            <span class="material-symbols-outlined">chevron_right</span>
            <a href="#" id="fcty-rooms-back-campus">PUP CITE</a>
            <span class="material-symbols-outlined">chevron_right</span>
            <span id="fcty-rooms-breadcrumb-building">Building</span>
        </nav>

        <!-- ── Hero banner ──────────────────────────────────── -->
        <div class="fcty-rooms-hero">
            <!-- Subtle noise/texture overlay -->
            <div class="fcty-rooms-hero-noise" aria-hidden="true"></div>

            <!-- Building name -->
            <h1 class="fcty-rooms-hero-title" id="fcty-rooms-hero-title">
                PUP CITE Building
            </h1>
        </div><!-- /.fcty-rooms-hero -->

        <!-- ── Content (metrics + floor accordions) ─────────── -->
        <!-- Pulls up over hero bottom via negative top margin   -->
        <div class="fcty-rooms-content">

            <!-- Metric summary cards — populated by fcty-facilities.js -->
            <div class="fcty-rooms-metrics" id="fcty-rooms-metrics"
                aria-label="Building metrics"></div>

            <!-- Room status legend — explains the color coding used on
                 room chips below. Colors are global indicators reused
                 across the system once live reservation data is wired in:
                   green  = Available
                   red    = Booked
                   blue   = Booking Pending
                   yellow = Under Maintenance
                   gray   = Not for Reservation -->
            <div class="fcty-status-legend" aria-label="Room status color guide">
                <div class="fcty-legend-heading">
                    <span class="material-symbols-outlined">info</span>
                    <span>Status Guide</span>
                </div>
                <div class="fcty-legend-items">
                    <span class="fcty-legend-item">
                        <span class="fcty-legend-dot fcty-legend-available" aria-hidden="true"></span>
                        <span class="fcty-legend-text">
                            <span class="fcty-legend-label">Available</span>
                            <span class="fcty-legend-desc">Open for booking</span>
                        </span>
                    </span>
                    <span class="fcty-legend-item">
                        <span class="fcty-legend-dot fcty-legend-unavailable" aria-hidden="true"></span>
                        <span class="fcty-legend-text">
                            <span class="fcty-legend-label">Booked</span>
                            <span class="fcty-legend-desc">Already reserved</span>
                        </span>
                    </span>
                    <span class="fcty-legend-item">
                        <span class="fcty-legend-dot fcty-legend-pending" aria-hidden="true"></span>
                        <span class="fcty-legend-text">
                            <span class="fcty-legend-label">Pending</span>
                            <span class="fcty-legend-desc">Booking request awaiting approval</span>
                        </span>
                    </span>
                    <span class="fcty-legend-item">
                        <span class="fcty-legend-dot fcty-legend-maintenance" aria-hidden="true"></span>
                        <span class="fcty-legend-text">
                            <span class="fcty-legend-label">Maintenance</span>
                            <span class="fcty-legend-desc">Temporarily closed</span>
                        </span>
                    </span>
                    <span class="fcty-legend-item">
                        <span class="fcty-legend-dot fcty-legend-static" aria-hidden="true"></span>
                        <span class="fcty-legend-text">
                            <span class="fcty-legend-label">Not Bookable</span>
                            <span class="fcty-legend-desc">Cannot be reserved</span>
                        </span>
                    </span>
                </div>
            </div>

            <!-- Floor accordion list — populated by fcty-facilities.js -->
            <div class="fcty-rooms-floors" id="fcty-rooms-floors"
                role="list" aria-label="Building floors"></div>

        </div><!-- /.fcty-rooms-content -->

    </div><!-- /#fcty-rooms-view -->


    <!-- ══════════════════════════════════════════════════════════
         ROOM DETAILS MODAL (hidden; JS shows on room chip click)
    ══════════════════════════════════════════════════════════════ -->
    <div class="fcty-modal-overlay" id="fcty-room-modal" aria-hidden="true">
        <div class="fcty-modal" role="dialog" aria-modal="true" aria-labelledby="fcty-modal-room-name">

            <!-- Close button -->
            <button class="fcty-modal-close" id="fcty-modal-close" type="button" aria-label="Close room details">
                <span class="material-symbols-outlined">close</span>
            </button>

            <!-- Image placeholder — swap for a real room photo later -->
            <div class="fcty-modal-image">
                <div class="fcty-modal-image-placeholder">
                    <span class="material-symbols-outlined">image</span>
                    <span>Room photo coming soon</span>
                </div>
            </div>

            <!-- Scrollable body -->
            <div class="fcty-modal-body">

                <!-- Header: room name + availability -->
                <div class="fcty-modal-header">
                    <div class="fcty-modal-heading">
                        <span class="fcty-modal-location" id="fcty-modal-location">PUP CITE &middot; 2nd Floor</span>
                        <h2 class="fcty-modal-title" id="fcty-modal-room-name">Room 201</h2>
                    </div>
                    <div class="fcty-availability-badge available" id="fcty-modal-availability">
                        <span class="fcty-availability-dot"></span>
                        <span class="fcty-availability-text" id="fcty-modal-availability-text">Available</span>
                    </div>
                </div>

                <!-- Capacity -->
                <div class="fcty-modal-info-row">
                    <div class="fcty-info-chip">
                        <div class="fcty-info-icon">
                            <span class="material-symbols-outlined">chair</span>
                        </div>
                        <div>
                            <div class="fcty-info-value" id="fcty-modal-capacity">&mdash;</div>
                            <div class="fcty-info-label">Seating Capacity</div>
                        </div>
                    </div>
                </div>

                <!-- Schedule tabs -->
                <div class="fcty-schedule-tabs" role="tablist" aria-label="Room schedule range">
                    <button class="fcty-schedule-tab active" type="button"
                        data-schedule-tab="daily" role="tab" aria-selected="true">
                        Today&rsquo;s Schedule
                    </button>
                    <button class="fcty-schedule-tab" type="button"
                        data-schedule-tab="weekly" role="tab" aria-selected="false">
                        Weekly Schedule
                    </button>
                </div>

                <!-- Daily schedule panel -->
                <div class="fcty-schedule-panel active" data-schedule-panel="daily">
                    <div class="fcty-schedule-day-label" id="fcty-modal-day-label">Today</div>

                    <!-- Slot list — populated by fcty-facilities.js -->
                    <div class="fcty-schedule-list" id="fcty-modal-daily-list"></div>

                    <p class="fcty-schedule-note">
                        Hours shown reflect school operating time, 7:00 AM&ndash;8:00 PM.
                        Schedule updates automatically based on faculty room assignments for the day.
                    </p>
                </div>

                <!-- Weekly schedule panel -->
                <div class="fcty-schedule-panel" data-schedule-panel="weekly">
                    <!-- Day grid — populated by fcty-facilities.js -->
                    <div class="fcty-weekly-grid" id="fcty-modal-weekly-grid"></div>

                    <p class="fcty-schedule-note">
                        Weekly faculty assignments are not yet available. This view will populate
                        automatically once the weekly schedule data is added.
                    </p>
                </div>

            </div><!-- /.fcty-modal-body -->

            <!-- Actions -->
            <div class="fcty-modal-actions">
                <button class="fcty-modal-btn fcty-btn-report" id="fcty-modal-report" type="button">
                    <span class="material-symbols-outlined">flag</span>
                    Report Issue
                </button>
                <button class="fcty-modal-btn fcty-btn-reserve" id="fcty-modal-reserve" type="button">
                    <span class="material-symbols-outlined">event_available</span>
                    Reserve Room
                </button>
            </div>

        </div><!-- /.fcty-modal -->
    </div><!-- /#fcty-room-modal -->

</div><!-- /#panel-rooms -->