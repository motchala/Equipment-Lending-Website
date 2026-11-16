<?php
/**
 * settings/views/admin-clear-history-panel.php
 *
 * Settings -> Data (Super Admin only): clear the equipment loan history and/or the room reservation
 * history in one sweep. Included from settings/views/admin-screen.php as a direct child of #panel-settings
 * so the existing Settings sub-tab switcher can show and hide it.
 *
 * The button only OPENS the confirmation dialog (settings/views/admin-modals.php, #ch-modal). Nothing is deleted
 * until the Super Admin types their own password there, and settings/api/clear-history.php checks the role and the
 * password again on the server.
 *
 * Styles : settings/assets/css/admin-clear-history.css   (all classes are ch-*)
 * Script : settings/assets/js/admin-clear-history.js
 */

if (empty($is_super_admin)) {
    return;
}
?>
<div class="rq-sub-panel" id="sett-data" data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="sett-grid">

        <div class="sett-card ch-card">
            <div class="sett-card-head">
                <span class="material-symbols-outlined">delete_sweep</span>
                <h3>Clear History</h3>
            </div>
            <div class="sett-card-body">

                <p class="ch-lead">
                    Permanently delete saved records in one sweep, for example to start a new semester with a clean slate.
                    <strong>This cannot be undone.</strong> You will be asked for your password before anything is deleted.
                </p>

                <div class="ch-result hidden" id="ch-result" role="status"></div>

                <fieldset class="ch-scope">
                    <legend class="sett-field-lbl">What do you want to clear?</legend>

                    <label class="ch-opt">
                        <input type="radio" name="ch-scope" value="equipment">
                        <span class="ch-opt-ico"><span class="material-symbols-outlined">inventory_2</span></span>
                        <span class="ch-opt-txt">
                            <strong>Equipment loans only</strong>
                            <small>Every borrow request: pending, scheduled, on loan, overdue, returned, declined and cancelled.
                                Units that are still out go back to the Catalog stock.</small>
                        </span>
                    </label>

                    <label class="ch-opt">
                        <input type="radio" name="ch-scope" value="rooms">
                        <span class="ch-opt-ico"><span class="material-symbols-outlined">meeting_room</span></span>
                        <span class="ch-opt-txt">
                            <strong>Room reservations only</strong>
                            <small>Every reservation: upcoming, past, declined and cancelled.</small>
                        </span>
                    </label>

                    <label class="ch-opt">
                        <input type="radio" name="ch-scope" value="all">
                        <span class="ch-opt-ico"><span class="material-symbols-outlined">delete_forever</span></span>
                        <span class="ch-opt-txt">
                            <strong>Everything</strong>
                            <small>Both of the above: all equipment loans and all room reservations.</small>
                        </span>
                    </label>
                </fieldset>

                <p class="ch-keep">
                    <span class="material-symbols-outlined">info</span>
                    <span>Not affected: the equipment Catalog, rooms and buildings, accounts, class schedules, room issue reports,
                        and the Activity history (this action adds its own line there).</span>
                </p>

                <div class="ch-actions">
                    <button type="button" class="ps-btn ps-btn--danger" id="ch-open" disabled>
                        <span class="material-symbols-outlined">delete_forever</span> Clear history&hellip;
                    </button>
                    <span class="ch-actions-hint" id="ch-open-hint">Choose what to clear first.</span>
                </div>

            </div>
        </div>

    </div>
</div><!-- /sett-data -->
