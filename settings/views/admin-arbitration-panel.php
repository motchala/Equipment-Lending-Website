<?php
/**
 * settings/views/admin-arbitration-panel.php
 *
 * Settings → Arbitration (Super Admin only).
 * Included from settings/views/admin-screen.php as a direct child of #panel-settings,
 * so the existing Settings sub-tab switcher can show and hide it.
 *
 * LIVE cards (marked data-arb-live): "Current semester", "Borrowing rules" and "Booking window". They load their
 * values through ArbitrationConfig and save through settings/api/save-arbitration.php.
 * The other cards (room rules, room schedule, role weights, priority faculty) are still UI only
 * and carry a "Not connected yet" badge.
 *
 * Styles : settings/assets/css/admin-arbitration.css  (all classes are arb-*)
 * Script : settings/assets/js/admin-arbitration.js
 */

if (empty($is_super_admin)) {
    return;
}

/* ── Current values (read-only) ───────────────────────────────────────────── */
require_once __DIR__ . '/../../shared/core/arbitration-config.php';
$arb_live = ArbitrationConfig::all($conn, true);   // typed values for the cards that are connected
$arb_cfg = [];
if (isset($conn) && $conn instanceof mysqli) {
    $arb_res = @$conn->query('SELECT config_key, config_value FROM tbl_arbitration_config');
    if ($arb_res) {
        while ($arb_row = $arb_res->fetch_assoc()) {
            $arb_cfg[(string)$arb_row['config_key']] = (string)$arb_row['config_value'];
        }
    }
}
$arb_num = static function (string $key, int $default) use ($arb_cfg): int {
    return isset($arb_cfg[$key]) && is_numeric($arb_cfg[$key]) ? (int)$arb_cfg[$key] : $default;
};
$arb_bool = static function (string $key, bool $default) use ($arb_cfg): bool {
    return isset($arb_cfg[$key]) ? $arb_cfg[$key] === '1' : $default;
};

/* ── Faculty available to mark as Priority (read-only) ────────────────────── */
$arb_faculty = [];
if (isset($conn) && $conn instanceof mysqli) {
    $arb_fres = @$conn->query("SELECT faculty_id, fullname, department FROM tbl_users WHERE COALESCE(role, '') <> 'Student Representative' ORDER BY fullname ASC");
    if ($arb_fres) {
        while ($arb_frow = $arb_fres->fetch_assoc()) {
            $arb_faculty[] = [
                'id'   => (string)$arb_frow['faculty_id'],
                'name' => (string)$arb_frow['fullname'],
                'dept' => (string)($arb_frow['department'] ?? ''),
            ];
        }
    }
}

/* ── Small renderers (keep the markup below readable) ─────────────────────── */
$arb_e = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$arb_toggle = static function (string $key, string $title, string $sub, bool $on) use ($arb_e): void {
    echo '<div class="arb-row">';
    echo '<div class="arb-row__text"><div class="arb-row__title">' . $arb_e($title) . '</div>';
    echo '<div class="arb-row__sub">' . $arb_e($sub) . '</div></div>';
    echo '<div class="arb-row__ctl"><label class="toggle-sw">';
    echo '<input type="checkbox" id="arb-' . $arb_e($key) . '" data-arb-key="' . $arb_e($key) . '" aria-label="' . $arb_e($title) . '"' . ($on ? ' checked' : '') . '>';
    echo '<span class="toggle-track"></span></label></div>';
    echo '</div>';
};

$arb_stepper = static function (string $key, string $title, string $sub, int $val, int $min, int $max, int $step, string $unit) use ($arb_e): void {
    echo '<div class="arb-row">';
    echo '<div class="arb-row__text"><div class="arb-row__title">' . $arb_e($title) . '</div>';
    echo '<div class="arb-row__sub">' . $arb_e($sub) . '</div></div>';
    echo '<div class="arb-row__ctl"><div class="arb-stepper">';
    echo '<button type="button" class="arb-stepper__btn" data-arb-step="-1" aria-label="Decrease ' . $arb_e($title) . '"><span class="material-symbols-outlined">remove</span></button>';
    echo '<label class="arb-stepper__mid">';
    echo '<input type="number" class="arb-stepper__input" id="arb-' . $arb_e($key) . '" data-arb-key="' . $arb_e($key) . '"'
        . ' value="' . $val . '" min="' . $min . '" max="' . $max . '" step="' . $step . '" inputmode="numeric" aria-label="' . $arb_e($title) . '">';
    echo '<span class="arb-stepper__unit">' . $arb_e($unit) . '</span></label>';
    echo '<button type="button" class="arb-stepper__btn" data-arb-step="1" aria-label="Increase ' . $arb_e($title) . '"><span class="material-symbols-outlined">add</span></button>';
    echo '</div></div>';
    echo '</div>';
};

$arb_time = static function (string $title, string $sub, string $key_from, string $from, string $key_to, string $to) use ($arb_e): void {
    echo '<div class="arb-row">';
    echo '<div class="arb-row__text"><div class="arb-row__title">' . $arb_e($title) . '</div>';
    echo '<div class="arb-row__sub">' . $arb_e($sub) . '</div></div>';
    echo '<div class="arb-row__ctl"><div class="arb-timepair">';
    echo '<input type="time" class="form-control-custom arb-time" step="3600" id="arb-' . $arb_e($key_from) . '" data-arb-key="' . $arb_e($key_from) . '" value="' . $arb_e($from) . '" aria-label="' . $arb_e($title) . ' from">';
    echo '<span class="arb-timepair__sep">to</span>';
    echo '<input type="time" class="form-control-custom arb-time" step="3600" id="arb-' . $arb_e($key_to) . '" data-arb-key="' . $arb_e($key_to) . '" value="' . $arb_e($to) . '" aria-label="' . $arb_e($title) . ' until">';
    echo '</div></div>';
    echo '</div>';
};

/** Row with a set of checkbox chips. $options = [value => label], $checked = [value, ...]. */
$arb_chips = static function (string $prefix, string $title, string $sub, array $options, array $checked) use ($arb_e): void {
    echo '<div class="arb-row arb-row--stack">';
    echo '<div class="arb-row__text"><div class="arb-row__title">' . $arb_e($title) . '</div>';
    echo '<div class="arb-row__sub">' . $arb_e($sub) . '</div></div>';
    echo '<div class="arb-chips" role="group" aria-label="' . $arb_e($title) . '">';
    foreach ($options as $value => $label) {
        $key = $prefix . '_' . $value;
        echo '<label class="arb-chip"><input type="checkbox" id="arb-' . $arb_e($key) . '" data-arb-key="' . $arb_e($key) . '"'
            . (in_array($value, $checked, true) ? ' checked' : '') . '><span>' . $arb_e((string)$label) . '</span></label>';
    }
    echo '</div></div>';
};

/** Row with a drop-down. $options = [value => label]. */
$arb_select = static function (string $key, string $title, string $sub, array $options, string $current) use ($arb_e): void {
    echo '<div class="arb-row">';
    echo '<div class="arb-row__text"><div class="arb-row__title">' . $arb_e($title) . '</div>';
    echo '<div class="arb-row__sub">' . $arb_e($sub) . '</div></div>';
    echo '<div class="arb-row__ctl"><select class="form-control-custom arb-select" id="arb-' . $arb_e($key) . '" data-arb-key="' . $arb_e($key) . '" aria-label="' . $arb_e($title) . '">';
    foreach ($options as $value => $label) {
        echo '<option value="' . $arb_e((string)$value) . '"' . ((string)$value === $current ? ' selected' : '') . '>' . $arb_e((string)$label) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';
};

/** Row with a date picker ($value is YYYY-MM-DD, or '' when not set). */
$arb_date = static function (string $key, string $title, string $sub, string $value) use ($arb_e): void {
    echo '<div class="arb-row">';
    echo '<div class="arb-row__text"><div class="arb-row__title">' . $arb_e($title) . '</div>';
    echo '<div class="arb-row__sub">' . $arb_e($sub) . '</div></div>';
    echo '<div class="arb-row__ctl"><input type="date" class="form-control-custom arb-select" id="arb-' . $arb_e($key) . '" data-arb-key="' . $arb_e($key) . '" value="' . $arb_e($value) . '" aria-label="' . $arb_e($title) . '"></div>';
    echo '</div>';
};
?>

<!-- ── ARBITRATION (Super Admin only) ─────────────────────────────────
     Rules for how borrow requests and room reservations are decided.
     UI only for now: nothing on this tab is saved. -->
<div class="rq-sub-panel" id="sett-arbitration">

    <!-- Intro -->
    <div class="arb-intro">
        <div class="arb-intro__icon"><span class="material-symbols-outlined">gavel</span></div>
        <div class="arb-intro__body">
            <h2 class="arb-intro__title">Arbitration Rules</h2>
            <p class="arb-intro__sub">
                Decide how the system accepts, declines and prioritises borrow requests and room reservations.
                Tweak these when the situation changes, such as exam week, events or semester breaks.
            </p>
        </div>
        <span class="arb-pill"><span class="material-symbols-outlined">shield_person</span> Super Admin only</span>
    </div>

    <div class="arb-note">
        <span class="material-symbols-outlined">info</span>
        <span>Changes apply to <strong>new requests only</strong>. Requests that are already decided or waiting are not re-evaluated.</span>
    </div>

    <!-- ═══ SEMESTER ════════════════════════════════════════════════ -->
    <div class="arb-section">
        <h3 class="arb-section__title"><span class="material-symbols-outlined">calendar_month</span> Semester</h3>
        <p class="arb-section__sub">Which semester is running. It shows as a small tag on every dashboard.</p>
    </div>

    <div class="arb-grid">
        <div class="sett-card arb-card" data-arb-live>
            <div class="sett-card-head">
                <span class="material-symbols-outlined">event</span>
                <h3>Current semester</h3>
            </div>
            <div class="sett-card-body">
                <?php
                $arb_select('semester_name', 'We are currently in', 'Shown on the student, faculty and admin dashboards (the name only, never the dates). Choose None between semesters.', [
                    'first'  => ArbitrationConfig::SEMESTER_LABELS['first'],
                    'second' => ArbitrationConfig::SEMESTER_LABELS['second'],
                    'summer' => ArbitrationConfig::SEMESTER_LABELS['summer'],
                    'none'   => 'None (semester break)',
                ], (string)$arb_live['semester_name']);
                $arb_date('semester_start', 'Semester starts', 'First day of the semester. The service survey\'s 14-day rating windows start counting from here.', (string)$arb_live['semester_start']);
                $arb_date('semester_end', 'Semester ends', 'Last day of the semester. Leave both dates blank to switch the survey\'s period reporting off.', (string)$arb_live['semester_end']);
                ?>
            </div>
        </div>
    </div>

    <!-- Quick presets -->
    <div class="arb-block">
        <div class="arb-block__head">
            <h3 class="arb-block__title"><span class="material-symbols-outlined">tune</span> Quick presets</h3>
            <p class="arb-block__sub">Fill in the settings below for a common situation. Review them, then save.</p>
        </div>
        <div class="arb-presets">
            <button type="button" class="arb-preset" data-arb-preset="standard">
                <span class="material-symbols-outlined">balance</span>
                <span class="arb-preset__title">Standard</span>
                <span class="arb-preset__sub">Everyday limits. Borrowers with an overdue item are blocked.</span>
            </button>
            <button type="button" class="arb-preset" data-arb-preset="busy">
                <span class="material-symbols-outlined">event_busy</span>
                <span class="arb-preset__title">Busy period</span>
                <span class="arb-preset__sub">Exams or events. Tighter limits and shorter booking window.</span>
            </button>
            <button type="button" class="arb-preset" data-arb-preset="relaxed">
                <span class="material-symbols-outlined">beach_access</span>
                <span class="arb-preset__title">Relaxed</span>
                <span class="arb-preset__sub">Semester break. Higher limits and longer loans.</span>
            </button>
        </div>
    </div>

    <!-- ═══ EQUIPMENT ═══════════════════════════════════════════════ -->
    <div class="arb-section">
        <h3 class="arb-section__title"><span class="material-symbols-outlined">inventory_2</span> Equipment borrowing</h3>
        <p class="arb-section__sub">How borrow requests are checked and decided.</p>
    </div>

    <div class="arb-grid">
        <div class="sett-card arb-card" data-arb-live>
            <div class="sett-card-head">
                <span class="material-symbols-outlined">rule</span>
                <h3>Borrowing rules</h3>
            </div>
            <div class="sett-card-body">
                <?php
                $arb_toggle('rule_overdue_block_enabled', 'Decline if the borrower has an overdue item', 'A faculty member with equipment past its return time cannot borrow more or generate student codes.', (bool)$arb_live['rule_overdue_block_enabled']);
                $arb_toggle('rule_missing_doc_block_enabled', 'Require a request letter', 'Faculty must attach a request letter when booking for a later date. Same-day requests never need one.', (bool)$arb_live['rule_missing_doc_block_enabled']);
                $arb_stepper('max_items_per_borrower', 'Items per borrower', 'Most items a borrower can hold or have requested at the same time.', (int)$arb_live['max_items_per_borrower'], 1, 20, 1, 'items');
                $arb_stepper('max_borrow_days', 'Longest loan', 'The most days a faculty member can pick in the Borrow form.', (int)$arb_live['max_borrow_days'], 1, 3, 1, 'days');
                $arb_stepper('max_extensions', 'Extensions per loan', 'How many times a borrower can extend. 0 turns extensions off.', (int)$arb_live['max_extensions'], 0, 5, 1, 'times');
                $arb_stepper('low_stock_threshold', 'Low-stock warning', 'Shows in Stock watch when an item has this many units or fewer.', (int)$arb_live['low_stock_threshold'], 0, 20, 1, 'units');
                ?>
            </div>
        </div>

        <div class="sett-card arb-card" data-arb-live>
            <div class="sett-card-head">
                <span class="material-symbols-outlined">schedule</span>
                <h3>Booking window</h3>
            </div>
            <div class="sett-card-body">
                <?php
                $arb_time('Booking hours', 'Earliest pickup and latest return on a booking day. Start times are on the hour.', 'equip_day_start', (string)$arb_live['equip_day_start'], 'equip_day_end', (string)$arb_live['equip_day_end']);
                $arb_stepper('equip_max_advance_days', 'Book ahead up to', 'How far in advance a later-date request can start.', (int)$arb_live['equip_max_advance_days'], 1, 60, 1, 'days');
                $arb_stepper('equip_immediate_grace_min', 'Same-day grace', 'A same-day booking starting within this time takes stock right away.', (int)$arb_live['equip_immediate_grace_min'], 0, 60, 5, 'min');
                $arb_stepper('equip_cancel_cutoff_min', 'Cancellation cutoff', 'Borrowers can cancel an approved booking until this long before it starts. 0 allows it until the start.', (int)$arb_live['equip_cancel_cutoff_min'], 0, 240, 15, 'min');
                $arb_chips('equip_dur', 'Booking lengths offered', 'Hours a borrower can pick, for today and for later dates. At least one is needed.', [1 => '1 hr', 2 => '2 hrs', 3 => '3 hrs', 4 => '4 hrs', 5 => '5 hrs', 6 => '6 hrs'], array_map('intval', (array)$arb_live['equip_durations']));
                ?>
            </div>
        </div>
    </div>

    <!-- ═══ ROOMS ═══════════════════════════════════════════════════ -->
    <div class="arb-section">
        <h3 class="arb-section__title"><span class="material-symbols-outlined">meeting_room</span> Room reservations</h3>
        <p class="arb-section__sub">How room reservations are limited and decided.</p>
    </div>

    <div class="arb-grid">
        <div class="sett-card arb-card">
            <div class="sett-card-head">
                <span class="material-symbols-outlined">rule</span>
                <h3>Reservation rules</h3>
                <span class="arb-badge">Not connected yet</span>
            </div>
            <div class="sett-card-body">
                <?php
                $arb_stepper('room_max_active', 'Active reservations per faculty', 'Reservations a faculty account can hold at the same time.', $arb_num('room_max_active', 2), 1, 10, 1, 'rooms');
                $arb_stepper('room_max_advance_weeks', 'Book ahead up to', 'How far in advance a room can be reserved.', $arb_num('room_max_advance_weeks', 3), 1, 8, 1, 'weeks');
                $arb_stepper('room_cancel_cutoff_min', 'Cancellation cutoff', 'Faculty can cancel until this long before the start time.', $arb_num('room_cancel_cutoff_min', 60), 0, 240, 15, 'min');
                $arb_toggle('room_same_day_auto', 'Confirm same-day reservations automatically', 'When the slot is free, nothing waits for an admin.', $arb_bool('room_same_day_auto', true));
                $arb_toggle('room_letter_required', 'Require a request letter for later dates', 'Later-date reservations without a letter cannot be submitted.', $arb_bool('room_letter_required', false));
                ?>
                <div class="arb-row">
                    <div class="arb-row__text">
                        <div class="arb-row__title">Undecided later-date requests</div>
                        <div class="arb-row__sub">What happens when nobody reviews a request in time.</div>
                    </div>
                    <div class="arb-row__ctl">
                        <select class="form-control-custom arb-select" id="arb-room_expiry_mode" data-arb-key="room_expiry_mode" aria-label="Undecided later-date requests">
                            <option value="day" selected>Decline at 12:01 AM on the date</option>
                            <option value="start">Decline at the start time</option>
                            <option value="never">Keep waiting for an admin</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="sett-card arb-card">
            <div class="sett-card-head">
                <span class="material-symbols-outlined">calendar_month</span>
                <h3>Room schedule</h3>
                <span class="arb-badge">Not connected yet</span>
            </div>
            <div class="sett-card-body">
                <?php
                $arb_time('Reservation hours', 'Earliest start and latest end of a reservation.', 'room_day_start', '07:00', 'room_day_end', '20:00');
                $arb_chips('room_day', 'Days rooms can be reserved', 'Unticked days are closed to new reservations.', ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'], ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
                $arb_stepper('room_max_hours', 'Longest single reservation', 'Longest a room can be held in one reservation.', $arb_num('room_max_hours', 13), 1, 13, 1, 'hours');
                ?>
            </div>
        </div>
    </div>

    <!-- ═══ PRIORITY ════════════════════════════════════════════════ -->
    <div class="arb-section">
        <h3 class="arb-section__title"><span class="material-symbols-outlined">swap_vert</span> When requests compete</h3>
        <p class="arb-section__sub">Who gets the item when several people ask for the last unit.</p>
    </div>

    <div class="arb-grid">
        <div class="sett-card arb-card">
            <div class="sett-card-head">
                <span class="material-symbols-outlined">groups</span>
                <h3>Role weights</h3>
                <span class="arb-badge">Not connected yet</span>
            </div>
            <div class="sett-card-body">
                <?php
                $arb_stepper('role_priority_student', 'Student representative', 'Higher number wins when requests compete.', $arb_num('role_priority_student', 1), 1, 10, 1, 'pts');
                $arb_stepper('role_priority_faculty', 'Regular faculty', 'Higher number wins when requests compete.', $arb_num('role_priority_faculty', 2), 1, 10, 1, 'pts');
                $arb_stepper('role_priority_director', 'Priority', 'Applies to the faculty you mark as priority. Higher number wins.', $arb_num('role_priority_director', 4), 1, 10, 1, 'pts');
                ?>
            </div>
        </div>

        <div class="sett-card arb-card" id="arbPriorityCard" data-arb-max="10">
            <div class="sett-card-head">
                <span class="material-symbols-outlined">star</span>
                <h3>Priority faculty</h3>
                <span class="arb-badge">Not connected yet</span>
            </div>
            <div class="sett-card-body">
                <div class="arb-prio">
                    <div class="arb-prio__top">
                        <p class="arb-prio__hint">Choose who counts as priority. You decide, up to 10 faculty.</p>
                        <span class="arb-prio__count"><span id="arbPrioCount">0</span> / 10</span>
                    </div>

                    <div class="arb-pick">
                        <input type="search" class="form-control-custom" id="arbPrioSearch" autocomplete="off"
                            placeholder="Search faculty by name, ID or department" aria-label="Search faculty to mark as priority">
                        <ul class="arb-pick__list" id="arbPrioOptions" hidden>
                            <?php foreach ($arb_faculty as $arb_f): ?>
                                <li>
                                    <button type="button" class="arb-pick__opt"
                                        data-id="<?php echo $arb_e($arb_f['id']); ?>"
                                        data-name="<?php echo $arb_e($arb_f['name']); ?>"
                                        data-dept="<?php echo $arb_e($arb_f['dept']); ?>"
                                        data-search="<?php echo $arb_e($arb_f['name'] . ' ' . $arb_f['id'] . ' ' . $arb_f['dept']); ?>">
                                        <span class="arb-pick__name"><?php echo $arb_e($arb_f['name']); ?></span>
                                        <span class="arb-pick__meta"><?php echo $arb_e(trim($arb_f['id'] . ($arb_f['dept'] !== '' ? ' · ' . $arb_f['dept'] : ''))); ?></span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                            <li class="arb-pick__none" id="arbPrioNone" hidden>No matching faculty.</li>
                        </ul>
                    </div>

                    <div class="arb-prio__limit" id="arbPrioLimit" hidden>
                        <span class="material-symbols-outlined">block</span> Limit reached. Remove someone to add another.
                    </div>

                    <ul class="arb-prio__list" id="arbPrioChosen"></ul>
                    <div class="arb-prio__empty" id="arbPrioEmpty">No priority faculty yet.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore defaults -->
    <div class="sett-reset-row">
        <div>
            <div class="sett-notif-title">Restore default rules</div>
            <div class="sett-notif-sub">Puts every setting on this tab back to its standard value. Nothing changes until you save.</div>
        </div>
        <button type="button" class="sett-reset-btn" id="arbRestoreBtn">
            <span class="material-symbols-outlined">restart_alt</span> Restore
        </button>
    </div>

    <!-- Unsaved-changes bar (shown by admin-arbitration.js once something changes) -->
    <div class="arb-savebar" id="arbSaveBar" role="status" aria-live="polite">
        <span class="arb-savebar__text" id="arbSaveText">
            <span class="material-symbols-outlined">edit_note</span> You have unsaved changes
        </span>
        <div class="arb-savebar__actions">
            <button type="button" class="ps-btn ps-btn--ghost" id="arbDiscardBtn">Discard</button>
            <button type="button" class="ps-btn ps-btn--primary" id="arbSaveBtn">
                <span class="material-symbols-outlined">save</span> Save changes
            </button>
        </div>
    </div>

</div><!-- /sett-arbitration -->
