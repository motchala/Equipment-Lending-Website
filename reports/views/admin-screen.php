<!-- ============================================================
     TAB: REPORTS
     Admin and Super Admin can generate and download predefined
     reports as PDF or XLSX. No intermediate preview — click a
     report card, set a date range, hit Download.
     Script : reports/assets/js/reports.js
     Styles : reports/assets/css/reports.css
============================================================ -->
<div class="tab-panel" id="panel-reports">

    <div class="rpt-page-header admin-screen-header">
        <h1 class="rpt-page-title">Reports</h1>
        <p class="rpt-page-sub">Generate and download equipment and room data as PDF or Excel.</p>
    </div>

    <form id="rptForm" class="rpt-shell" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <!-- ── Step 1: Choose a report ──────────────────────────────────── -->
        <section class="rpt-section">
            <div class="rpt-section-title-row">
                <h2 class="rpt-section-title">
                    <span class="rpt-step-num">1</span> Choose a report
                </h2>
                <label class="rpt-select-all-label" id="rptSelectAllLabel">
                    <input type="checkbox" id="rptSelectAll">
                    <span class="rpt-select-all-box" aria-hidden="true"></span>
                    <span class="rpt-select-all-text">Select All</span>
                </label>
            </div>
            <div class="rpt-cards" role="group" aria-label="Report type">

                <label class="rpt-card">
                    <input type="checkbox" name="report_type[]" value="most_borrowed">
                    <span class="rpt-card-check" aria-hidden="true">
                        <span class="material-symbols-outlined">check</span>
                    </span>
                    <span class="material-symbols-outlined rpt-card-icon">bar_chart</span>
                    <span class="rpt-card-label">Most Borrowed Equipment</span>
                    <span class="rpt-card-desc">Which items are borrowed most often and by how many people.</span>
                </label>

                <label class="rpt-card">
                    <input type="checkbox" name="report_type[]" value="inventory_status">
                    <span class="rpt-card-check" aria-hidden="true">
                        <span class="material-symbols-outlined">check</span>
                    </span>
                    <span class="material-symbols-outlined rpt-card-icon">inventory_2</span>
                    <span class="rpt-card-label">Inventory Status</span>
                    <span class="rpt-card-desc">Current stock, condition, and archived status of every item.</span>
                </label>

                <label class="rpt-card">
                    <input type="checkbox" name="report_type[]" value="borrowing_by_faculty">
                    <span class="rpt-card-check" aria-hidden="true">
                        <span class="material-symbols-outlined">check</span>
                    </span>
                    <span class="material-symbols-outlined rpt-card-icon">group</span>
                    <span class="rpt-card-label">Borrowing Activity by Faculty</span>
                    <span class="rpt-card-desc">How many times each faculty member borrowed, returned, or went overdue.</span>
                </label>

                <label class="rpt-card">
                    <input type="checkbox" name="report_type[]" value="damage_reports">
                    <span class="rpt-card-check" aria-hidden="true">
                        <span class="material-symbols-outlined">check</span>
                    </span>
                    <span class="material-symbols-outlined rpt-card-icon">report</span>
                    <span class="rpt-card-label">Damage & Missing Reports</span>
                    <span class="rpt-card-desc">Returns where equipment was reported as damaged or missing.</span>
                </label>

                <label class="rpt-card">
                    <input type="checkbox" name="report_type[]" value="room_summary">
                    <span class="rpt-card-check" aria-hidden="true">
                        <span class="material-symbols-outlined">check</span>
                    </span>
                    <span class="material-symbols-outlined rpt-card-icon">meeting_room</span>
                    <span class="rpt-card-label">Room Reservation Summary</span>
                    <span class="rpt-card-desc">Approved, declined, and cancelled reservation counts per room.</span>
                </label>

                <label class="rpt-card">
                    <input type="checkbox" name="report_type[]" value="overdue_history">
                    <span class="rpt-card-check" aria-hidden="true">
                        <span class="material-symbols-outlined">check</span>
                    </span>
                    <span class="material-symbols-outlined rpt-card-icon">schedule</span>
                    <span class="rpt-card-label">Overdue History</span>
                    <span class="rpt-card-desc">Every overdue return: who, what item, and how many days late.</span>
                </label>

            </div>
        </section>

        <!-- ── Step 2: Date range ────────────────────────────────────────── -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">
                <span class="rpt-step-num">2</span> Set a date range
                <span class="rpt-hint">(leave blank for all time)</span>
            </h2>
            <div class="rpt-dates">
                <?php
                    $rpt_today       = date('Y-m-d');
                    $rpt_month_start = date('Y-m-01');

                    // Earliest real record across both modules — this becomes the min date.
                    // Falls back to the first day of the current month if no data exists yet.
                    $rpt_min = $rpt_month_start;
                    $rpt_min_res = $conn->query(
                        "SELECT MIN(dt) AS earliest FROM (
                            SELECT MIN(request_date) AS dt FROM tbl_requests
                            UNION ALL
                            SELECT MIN(request_date) AS dt FROM tbl_room_reservations
                         ) AS combined"
                    );
                    if ($rpt_min_res) {
                        $rpt_min_row = $rpt_min_res->fetch_assoc();
                        if (!empty($rpt_min_row['earliest'])) {
                            $rpt_min = date('Y-m-d', strtotime($rpt_min_row['earliest']));
                        }
                    }
                ?>
                <div class="rpt-date-field">
                    <label for="rptFrom">From</label>
                    <input type="date" id="rptFrom" name="date_from"
                           value="<?php echo $rpt_month_start; ?>"
                           min="<?php echo $rpt_min; ?>"
                           max="<?php echo $rpt_today; ?>">
                </div>
                <div class="rpt-date-sep" aria-hidden="true">—</div>
                <div class="rpt-date-field">
                    <label for="rptTo">To</label>
                    <input type="date" id="rptTo" name="date_to"
                           value="<?php echo $rpt_today; ?>"
                           min="<?php echo $rpt_min; ?>"
                           max="<?php echo $rpt_today; ?>">
                </div>
                <button type="button" class="rpt-clear-dates" id="rptClearDates" title="Clear dates">
                    Clear
                </button>
            </div>
        </section>

        <!-- ── Step 3: Format + Download ────────────────────────────────── -->
        <section class="rpt-section rpt-section--action">
            <h2 class="rpt-section-title">
                <span class="rpt-step-num">3</span> Download
            </h2>
            <div class="rpt-action-row">
                <div class="rpt-format-toggle" role="group" aria-label="File format">
                    <button type="button" class="rpt-fmt-btn active" data-fmt="pdf">
                        <span class="material-symbols-outlined">picture_as_pdf</span> PDF
                    </button>
                    <button type="button" class="rpt-fmt-btn" data-fmt="xlsx">
                        <span class="material-symbols-outlined">table_view</span> Excel
                    </button>
                    <input type="hidden" name="format" id="rptFormat" value="pdf">
                </div>
                <button type="submit" class="rpt-submit-btn" id="rptSubmitBtn">
                    <span class="material-symbols-outlined rpt-btn-icon">download</span>
                    <span class="rpt-btn-label">Download Report</span>
                    <span class="rpt-btn-spinner" aria-hidden="true"></span>
                </button>
            </div>
            <p class="rpt-error" id="rptError" role="alert" aria-live="polite"></p>
        </section>

    </form>

</div>
<!-- /panel-reports -->