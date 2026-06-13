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
                     style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDwZKiIIcwzNLtaM7oulOefPS6Bo2F1X0fRlmePI5W6J3v40-m8IJog2Sd89VsZQz1pTp3IFCGIf9v3SBeeJFTG7Z-OIW8dD7ceIU2KcfHC3TvFTQnqxkfFCN1hSNqTUjBk5XOtWX_V_8o1y5u6RTKXMkspSudbtVkr-AesBw1_w8T9ieefDxc03pGRD89S2fDawvDDeAwmocnGfigfs4dCPjLzsQWPmCPbl89jCaofp7mByStPnKC23CSs_HrdSM2tfyVfIw--cQ');">
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
                     style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDtmIfzajhkJcIVcLVAYPDyA499CI1o5QU8mNgrJN3N_7x6y-FDAz_IJ3WceWqelnR3N-2SPabLr_roNxvEt7_x7jCp8oqrqfasU0yRX8YhaTPzRRt2t8Xi-mjIkGsK-MPBqrdlZNZQ33UKvUCyyU8iHiD4622bQ1BeGiA6wExvcX_QXyzJvP3HoqLK8pzmTphoc-ZFxnFIHbvO0HE17vuxuO3fgVq2xBhQ6f79V8NYMAZxwmt5icsYW71bVKYSZHOXLi2FLYpcYg');">
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

</div><!-- /#panel-rooms -->