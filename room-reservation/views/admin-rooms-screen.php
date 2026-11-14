            <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
            <div class="tab-panel" id="panel-rooms">

                <!-- ── Page header + Add Room button ──────────────────── -->
                <div class="pr-page-header-row admin-screen-header">
                    <div>
                        <h1 class="pr-page-title">Facilities Management</h1>
                        <p class="pr-page-subtitle">Browse facilities and reservations.</p>
                    </div>
                    <?php if ($is_super_admin): ?>
                        <button type="button" class="pr-btn pr-btn-primary" data-action="show-room-form" id="addRoomBtn">
                            <span class="material-symbols-outlined">add</span>
                            Add Facility
                        </button>
                    <?php endif; ?>
                </div>

                <!-- ── Add / Edit Room form (hidden by default) ────────── -->
                <!-- Shared by both Add and Edit — only hiding the Add Room
                     button above for regular Admins, not this whole block,
                     since Admins can still edit existing rooms via each
                     row's Edit icon, which reuses this same form. -->
                <div id="room-form-wrap" class="<?php echo $edit_room ? '' : 'hidden'; ?>">
                    <div class="eq-card form-card">

                        <!-- Modal header -->
                        <div class="form-card-header">
                            <div class="rmod-head-icon">
                                <span class="material-symbols-outlined"><?php echo $edit_room ? 'edit' : 'add_home_work'; ?></span>
                            </div>
                            <h2 id="room-form-title">
                                <?php echo $edit_room
                                    ? 'Edit Facility &mdash; ' . htmlspecialchars($edit_room['room_name'])
                                    : 'Add New Facility'; ?>
                            </h2>
                            <button type="button" class="rmod-close-btn" data-action="hide-room-form" aria-label="Close">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <!-- Body wrapper -->
                        <div class="form-card-body">
                            <form method="POST" id="roomForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="room_id" id="room-form-id"
                                    value="<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>">
                                <!-- sort_order preserved as hidden so the server doesn't clear it on update -->
                                <input type="hidden" name="sort_order" id="rf-sort"
                                    value="<?php echo $edit_room ? (int)$edit_room['sort_order'] : 0; ?>">
                                <!-- floor_label preserved as hidden so existing labels aren't wiped on update -->
                                <input type="hidden" name="floor_label" id="rf-floor-label"
                                    value="<?php echo $edit_room ? htmlspecialchars($edit_room['floor_label'] ?? '') : ''; ?>">

                                <div class="form-group">
                                    <label for="rf-building">Building <span class="req-star" aria-label="required">*</span></label>
                                    <select name="building_id" id="rf-building" class="form-control-custom" required>
                                        <option value="">Select building</option>
                                        <?php foreach ($rooms_buildings as $bid => $b): ?>
                                            <option value="<?php echo (int)$bid; ?>"
                                                data-floors="<?php echo max(1, (int)$b['floor_count']); ?>"
                                                <?php echo ($edit_room && (int)$edit_room['building_id'] === $bid) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($b['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="rf-name">Facility name <span class="req-star" aria-label="required">*</span></label>
                                    <input type="text" name="room_name" id="rf-name" class="form-control-custom"
                                        value="<?php echo $edit_room ? htmlspecialchars($edit_room['room_name']) : ''; ?>"
                                        placeholder="e.g. Computer Lab 1" required>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="rf-floor">Floor <span class="req-star" aria-label="required">*</span></label>
                                        <input type="number" name="floor_number" id="rf-floor" class="form-control-custom" min="1" max="20"
                                            value="<?php echo $edit_room ? (int)$edit_room['floor_number'] : 1; ?>" required>
                                        <p class="rf-hint rf-floor-hint" id="rf-floor-hint" aria-live="polite">Select a building first.</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="rf-status">Status</label>
                                        <select name="status" id="rf-status" class="form-control-custom">
                                            <?php foreach (['Available', 'Maintenance', 'Not Bookable'] as $s): ?>
                                                <option value="<?php echo $s; ?>"
                                                    <?php echo ($edit_room && $edit_room['status'] === $s) ? 'selected' : (!$edit_room && $s === 'Available' ? 'selected' : ''); ?>>
                                                    <?php echo htmlspecialchars($s); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <?php
                                $preset_amenities  = ['WiFi', 'A/C', 'Projector', 'PA System', 'Running Water', 'Safety Kit', 'Whiteboard', 'Smart TV'];
                                $checked_amenities = [];
                                if ($edit_room && !empty($edit_room['amenities'])) {
                                    $decoded = json_decode($edit_room['amenities'], true);
                                    $checked_amenities = is_array($decoded) ? $decoded : [];
                                }
                                ?>
                                <div class="form-group">
                                    <label>Features <small class="field-optional">(optional)</small></label>
                                    <div class="rf-chips">
                                        <?php foreach ($preset_amenities as $am): ?>
                                            <label class="rf-chip">
                                                <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($am); ?>"
                                                    <?php echo in_array($am, $checked_amenities, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($am); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- hidden inner submit — footer button is the real one -->
                                <button type="submit" id="room-form-submit-inner"
                                    name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                    class="btn-submit-form">
                                    <span id="room-submit-inner-label"><?php echo $edit_room ? 'Update Facility' : 'Add Facility'; ?></span>
                                </button>
                            </form>

                            <!-- Room actions zone (edit mode only): always in DOM, shown/hidden by JS.
                                 Lock / Unlock: Admin + Super Admin.  Archive: Super Admin only
                                 (the link is not even rendered for a regular Admin; the server checks too). -->
                            <?php $edit_locked = $edit_room && !empty($edit_room['is_locked']); ?>
                            <div class="rmod-archive-zone<?php echo $edit_room ? '' : ' hidden'; ?>" id="room-archive-zone">
                                <button type="button" id="room-lock-btn"
                                    class="pr-btn pr-btn-ghost pr-btn-sm"
                                    data-action="toggle-room-lock"
                                    data-lock="<?php echo $edit_locked ? '0' : '1'; ?>">
                                    <span class="material-symbols-outlined" id="room-lock-icon" style="font-size:15px;"><?php echo $edit_locked ? 'lock_open' : 'lock'; ?></span>
                                    <span id="room-lock-label"><?php echo $edit_locked ? 'Unlock Facility' : 'Lock Facility'; ?></span>
                                </button>
                                <?php if ($is_super_admin): ?>
                                    <a href="admin-dashboard.php?archive_room=<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>"
                                        id="room-archive-link"
                                        class="pr-btn pr-btn-danger pr-btn-sm">
                                        <span class="material-symbols-outlined" style="font-size:15px;">archive</span>
                                        Archive Facility
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div><!-- /.form-card-body -->

                        <!-- Modal footer -->
                        <div class="rmod-footer">
                            <button type="button" class="pr-btn pr-btn-ghost" data-action="hide-room-form">Cancel</button>
                            <button type="submit" form="roomForm" id="room-form-submit-foot"
                                name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                class="pr-btn pr-btn-primary">
                                <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                                <span id="room-submit-foot-label"><?php echo $edit_room ? 'Save Changes' : 'Add Facility'; ?></span>
                            </button>
                        </div>

                    </div><!-- /.eq-card.form-card -->
                </div><!-- /#room-form-wrap -->

                <!-- Tabs -->
                <div class="rm-tabbar">
                    <div class="pr-sub-tabs" id="rooms-toggle-wrap">
                        <button type="button" class="pr-sub-tab active" data-rooms-tab="rooms-active">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">meeting_room</span> Facilities
                        </button>
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-reservations">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">event_available</span> Reservations
                            <?php
                            // The badge is the review queue: reservations waiting for an admin decision.
                            // (It used to count every Approved reservation ever made, which only ever grew.)
                            $rm_resv_pending = count(array_filter($admin_room_reservations ?? [], function ($r) {
                                return ($r['status'] ?? '') === 'Waiting';
                            }));
                            ?>
                            <span class="pr-tab-badge" id="rrNavBadge" title="Reservations waiting for review"<?php echo $rm_resv_pending > 0 ? '' : ' hidden'; ?>><?php echo $rm_resv_pending; ?></span>
                        </button>
                    </div>
                    <!-- Archived tab — pushed to the right by the flex space-between on .rm-tabbar -->
                    <div class="pr-sub-tabs">
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-archived"
                            title="Facilities hidden from faculty and students">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">inventory_2</span>
                            Archived
                            <?php if (!empty($rooms_archived)): ?>
                                <span class="pr-tab-badge" id="roomsArchivedBadge"
                                    style="background:var(--text-light);"><?php echo count($rooms_archived); ?></span>
                            <?php else: ?>
                                <span class="pr-tab-badge" id="roomsArchivedBadge" style="display:none;background:var(--text-light);">0</span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- ROOMS -->
                <div class="rooms-sub-panel active" id="rooms-active-panel">
                    <?php
                    // Group active rooms by building -> floor. Shared with the AJAX responses in
                    // admin-rooms-functions.php via ps_prepare_rooms_registry_view().
                    $rv = ps_prepare_rooms_registry_view($rooms_buildings, $rooms_list);
                    $rooms_grouped         = $rv['grouped'];
                    $floors_by_building_js = $rv['floors_by_building'];
                    $first_building_id     = $rv['first_building_id'];
                    $building_accent       = $rv['accent'];
                    ?>

                    <?php if (empty($rooms_buildings)): ?>
                        <div class="pr-card rm-rooms-card">
                            <div class="pr-empty">No facilities yet. Click <strong>Add Facility</strong> to get started.</div>
                        </div>
                    <?php else: ?>
                        <div class="pr-card rm-rooms-card">
                            <div class="pr-card-header">
                                <h3><span class="material-symbols-outlined">meeting_room</span> All Facilities</h3>
                                <div class="rm-head-tools">
                                    <select id="roomStatusSelect" class="pr-filter-select" aria-label="Status">
                                        <option value="all">All statuses</option>
                                        <option value="Available">Available</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Not Bookable">Not bookable</option>
                                    </select>
                                    <div class="pr-search-wrap">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="roomSearchInput" placeholder="Search facilities" autocomplete="off" aria-label="Search facilities">
                                    </div>
                                </div>
                            </div>
                            <!-- Filters: building + floor pills (status and search live in the header) -->
                            <div class="rm-toolbar" id="rmToolbar">
                                <div class="rm-tb-row">
                                    <div class="rm-bld-pills" id="rmBuildingPills" role="group" aria-label="Building"></div>
                                </div>
                                <div class="rm-tb-row" id="rmFloorRow">
                                    <div class="rm-floor-chips" id="rmFloorChips" role="group" aria-label="Floor"></div>
                                </div>
                                <!-- Plain dropdowns: the pills above read and write these. Hidden once JS runs. -->
                                <div class="rm-native-filters">
                                    <select id="roomBuildingSelect" class="pr-filter-select" aria-label="Building">
                                        <option value="all">All buildings</option>
                                        <?php foreach ($rooms_buildings as $bid => $b): ?>
                                            <option value="<?php echo (int)$bid; ?>" <?php echo $bid === $first_building_id ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars($b['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select id="roomFloorSelect" class="pr-filter-select" aria-label="Floor">
                                        <option value="all">All floors</option>
                                        <?php foreach (($floors_by_building_js[$first_building_id] ?? []) as $f): ?>
                                            <option value="<?php echo (int)$f['num']; ?>"><?php echo htmlspecialchars($f['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- One floor-grouped list for every building (drawn as tiles) -->
                            <div class="pr-tbl-wrap">
                                <table class="pr-rooms-table rm-grid" id="roomsRegistryTable" aria-label="Facilities">
                                    <colgroup>
                                        <col>
                                        <col style="width:150px;">
                                        <col style="width:132px;">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Facility</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php echo ps_render_rooms_tbody($rooms_buildings, $rooms_grouped, $building_accent); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <script type="application/json" id="psRoomsRegistryBootData"><?php
                            echo json_encode(
                                [
                                    'floorsByBuilding' => $floors_by_building_js,
                                    'defaultBuilding' => (int)$first_building_id,
                                ],
                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
                            );
                        ?></script>
<script src="room-reservation/assets/js/admin-rooms-registry-filter.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/admin-rooms-registry-filter.js'); ?>"></script>

                        <script src="room-reservation/assets/js/admin-rooms-registry-actions.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/admin-rooms-registry-actions.js'); ?>"></script>
                    <?php endif; ?>
                </div><!-- /#rooms-active-panel -->

                <!-- RESERVATIONS -->
                <div class="rooms-sub-panel" id="rooms-reservations-panel">
                    <link rel="stylesheet" href="room-reservation/assets/css/admin-rooms-reservations.css?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/css/admin-rooms-reservations.css'); ?>">
                    <?php
                    // Table rows start in "most recent request first" order (the sort dropdown re-orders them live).
                    $rr_rows = $admin_room_reservations ?? [];
                    usort($rr_rows, function ($a, $b) {
                        $ta = !empty($a['request_date']) ? (int)strtotime($a['request_date']) : 0;
                        $tb = !empty($b['request_date']) ? (int)strtotime($b['request_date']) : 0;
                        return ($tb <=> $ta) ?: ((int)$b['id'] <=> (int)$a['id']);
                    });
                    // Every reservation sits in exactly ONE tab, following its life:
                    //   Pending (Waiting, later date)  ->  Upcoming  ->  In use now  ->  Completed
                    //   ...or it ends early as Cancelled / Declined.
                    // The tab (phase) is worked out in admin-rooms-reservations-table.js from the status plus the
                    // Manila clock, so a reservation moves between tabs by itself as time passes.
                    ?>

                    <?php if (!empty($rooms_expired_count) && $rooms_expired_count > 0): ?>
                    <div class="rr-expiry-banner" id="rrExpiryBanner" role="alert"
                         style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;margin-bottom:12px;background:#fff8e1;border:1px solid #f59e0b;font-size:.85rem;color:#78350f;">
                        <span class="material-symbols-outlined" style="font-size:18px;color:#f59e0b;flex-shrink:0;">schedule</span>
                        <span>
                            <?php echo $rooms_expired_count === 1
                                ? '1 reservation was automatically declined this session because it was not reviewed in time.'
                                : $rooms_expired_count . ' reservations were automatically declined this session because they were not reviewed in time.'; ?>
                            They appear in the <strong>Declined</strong> tab.
                        </span>
                        <button type="button" id="rrExpiryDismiss" aria-label="Dismiss"
                            style="margin-left:auto;background:none;border:none;cursor:pointer;color:#92400e;padding:0;line-height:1;">
                            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                        </button>
                    </div>
                    <script nonce="<?php echo $csp_nonce; ?>">
                        (function () {
                            var b = document.getElementById('rrExpiryDismiss');
                            if (b) b.addEventListener('click', function () { b.parentElement.style.display = 'none'; });
                        })();
                    </script>
                    <?php endif; ?>
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">calendar_month</span> Reservations</h3>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="pr-search-wrap">
                                    <span class="material-symbols-outlined">search</span>
                                    <input type="text" id="rrSearch" placeholder="Search facility or name" autocomplete="off" aria-label="Search reservations">
                                </div><?php if ($is_super_admin): ?><button type="button" class="ps-btn ps-btn--danger" id="rr-wipe-btn" title="Permanently delete every reservation record"<?php echo empty($rr_rows) ? ' disabled' : ''; ?>><span class="material-symbols-outlined">delete_forever</span> Delete All</button><?php endif; ?>
                            </div>
                        </div>
                        <div class="rr-status-bar">
                            <div class="rr-tabs" id="rrTabs" role="group" aria-label="Filter reservations by stage">
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="all" aria-pressed="false">
                                    All <span class="rr-tab-count"></span>
                                </button>
                                <span class="rr-tab-sep" aria-hidden="true"></span>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Waiting" aria-pressed="false">
                                    Pending <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="upcoming" aria-pressed="false">
                                    Upcoming <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab rr-active-chip" data-rr-tab="active" aria-pressed="false">
                                    In use now <span class="rr-tab-count"></span>
                                </button>
                                <span class="rr-tab-sep" aria-hidden="true"></span>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="completed" aria-pressed="false">
                                    Completed <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Cancelled" aria-pressed="false">
                                    Cancelled <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Declined" aria-pressed="false">
                                    Declined <span class="rr-tab-count"></span>
                                </button>
                            </div>
                            <div class="rr-sort">
                                <select id="rrSort" class="pr-filter-select" aria-label="Sort reservations by">
                                    <option value="recent" selected>Recent requests</option>
                                    <option value="date">Reservation date</option>
                                </select>
                                <button type="button" class="rr-sort-dir" id="rrSortDir" data-dir="desc"
                                    title="Newest requests first (click to reverse)"
                                    aria-label="Sort direction: newest requests first. Click to reverse.">
                                    <span class="material-symbols-outlined" aria-hidden="true">arrow_downward</span>
                                </button>
                            </div>
                        </div>
                        <p class="rr-hint" id="rrHint" aria-live="polite"></p>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table" id="rrTable">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th>When</th>
                                        <th>Requested by</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rr_rows)): ?>
                                        <tr>
                                            <td colspan="5" class="rm-empty-cell">No reservations yet.</td>
                                        </tr>
                                        <?php else: foreach ($rr_rows as $ar):
                                            // First-paint badge. The table script re-draws the whole Status cell from the
                                            // row's data-* attributes, so this only matters for the instant before it runs.
                                            $ar_badge = 'ps-badge--active';
                                            if ($ar['status'] === 'Declined')  $ar_badge = 'ps-badge--overdue';
                                            if ($ar['status'] === 'Cancelled') $ar_badge = 'ps-badge--cancelled';
                                            if ($ar['status'] === 'Waiting')   $ar_badge = 'ps-badge--waiting';
                                            // Every Waiting row is the admin's to decide.
                                            $ar_reviewable = $ar['status'] === 'Waiting';

                                            $ar_type    = ps_submitted_as_label($ar['submitted_as']);
                                            $ar_is_stud = $ar['submitted_as'] === 'student' && !empty($ar['submitted_by_name']);
                                            $ar_name    = $ar_is_stud ? $ar['submitted_by_name'] : $ar['faculty_name'];
                                            $ar_sub     = $ar_is_stud ? 'Student via ' . $ar['faculty_name'] : $ar_type;
                                            $ar_doc     = ps_doc_info($ar['document_path'] ?? '');
                                            $ar_where   = $ar['floor_label'] . ', ' . $ar['building_name'];
                                            $ar_time    = $ar['start_fmt'] . ' - ' . $ar['end_fmt'];
                                            // Cancellable: Approved AND the reservation end hasn't passed yet
                                            $ar_end_ts  = !empty($ar['end_time']) ? strtotime($ar['reservation_date'] . ' ' . $ar['end_time']) : 0;
                                            $ar_cancellable = $ar['status'] === 'Approved' && $ar_end_ts > time();

                                            // Cancelled rows keep the OLD approval text in `reason` (cancelling never writes one),
                                            // so it is not offered as if it were a cancellation reason.
                                            $ar_is_cancelled   = $ar['status'] === 'Cancelled';
                                            $ar_reason_out     = $ar_is_cancelled ? '' : (string)($ar['reason'] ?? '');
                                            $ar_arb_reason_out = $ar_is_cancelled ? '' : (string)($ar['arb_reason'] ?? '');
                                            $ar_cancel_by      = $ar_is_cancelled ? (!empty($ar['cancelled_by_requester']) ? 'requester' : 'admin') : '';
                                            $ar_cancel_at      = ($ar_is_cancelled && !empty($ar['cancelled_at'])) ? date('M d, Y g:i A', strtotime($ar['cancelled_at'])) : '';

                                            // Second line under the requester: what the room is for (decision-ready at a glance).
                                            $ar_line2 = [];
                                            if (trim((string)($ar['purpose'] ?? '')) !== '') $ar_line2[] = trim((string)$ar['purpose']);
                                            if ((int)($ar['attendees'] ?? 0) > 1)            $ar_line2[] = (int)$ar['attendees'] . ' attendees';
                                            if ($ar_is_stud)                                  $ar_line2[] = 'via ' . $ar['faculty_name'];
                                            $ar_line2_txt = $ar_line2 ? implode(' · ', $ar_line2) : '—';
                                        ?>
                                            <tr class="ps-rr-row<?php echo $ar_doc ? ' has-doc' : ''; ?>"
                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                data-room="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                data-where="<?php echo htmlspecialchars($ar_where); ?>"
                                                data-date="<?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?>"
                                                data-time="<?php echo htmlspecialchars($ar_time); ?>"
                                                data-who="<?php echo htmlspecialchars($ar_name); ?>"
                                                data-who-sub="<?php echo htmlspecialchars($ar_sub); ?>"
                                                data-who-id="<?php echo $ar_is_stud ? htmlspecialchars((string)($ar['submitted_by_id'] ?? '')) : ''; ?>"
                                                data-type="<?php echo htmlspecialchars($ar_type); ?>"
                                                data-purpose="<?php echo htmlspecialchars($ar['purpose'] ?? ''); ?>"
                                                data-attendees="<?php echo (int)($ar['attendees'] ?? 0); ?>"
                                                data-status="<?php echo htmlspecialchars($ar['status']); ?>"
                                                data-reason="<?php echo htmlspecialchars($ar_reason_out); ?>"
                                                data-notes="<?php echo htmlspecialchars($ar['notes'] ?? ''); ?>"
                                                data-submitted="<?php echo !empty($ar['request_date']) ? date('M d, Y g:i A', strtotime($ar['request_date'])) : ''; ?>"
                                                data-submitted-raw="<?php echo htmlspecialchars((string)($ar['request_date'] ?? '')); ?>"
                                                data-cancelled-at="<?php echo htmlspecialchars($ar_cancel_at); ?>"
                                                data-cancelled-by="<?php echo $ar_cancel_by; ?>"
                                                data-doc-url="<?php echo $ar_doc ? htmlspecialchars($ar_doc['url']) : ''; ?>"
                                                data-doc-open-url="<?php echo $ar_doc ? htmlspecialchars($ar_doc['open_url']) : ''; ?>"
                                                data-doc-name="<?php echo $ar_doc ? htmlspecialchars($ar_doc['name']) : ''; ?>"
                                                data-doc-kind="<?php echo $ar_doc ? $ar_doc['kind'] : ''; ?>"
                                                data-iso-date="<?php echo htmlspecialchars($ar['reservation_date']); ?>"
                                                data-start-time="<?php echo htmlspecialchars($ar['start_time']); ?>"
                                                data-end-time="<?php echo htmlspecialchars($ar['end_time']); ?>"
                                                data-arb-rule="<?php echo htmlspecialchars($ar['arb_rule'] ?? ''); ?>"
                                                data-arb-reason="<?php echo htmlspecialchars($ar_arb_reason_out); ?>"
                                                data-submitted-ts="<?php echo !empty($ar['request_date']) ? (int)strtotime($ar['request_date']) : 0; ?>"
                                                data-can-review="<?php echo $ar_reviewable ? '1' : '0'; ?>"
                                                data-cancellable="<?php echo $ar_cancellable ? '1' : '0'; ?>"
                                                data-room-id="<?php echo (int)$ar['room_id']; ?>"
                                                data-waitlist-count="<?php
                                                    $ar_wl_key = ($ar['room_id'] ?? '') . '|' . $ar['reservation_date'] . '|' . $ar['start_time'] . '|' . $ar['end_time'];
                                                    echo (int)($admin_room_waitlist_counts[$ar_wl_key] ?? 0);
                                                ?>">
                                                <td data-col="facility">
                                                    <div class="eq-cell-name"><?php echo htmlspecialchars($ar['room_name']); ?></div>
                                                    <div class="eq-cell-sub"><?php echo htmlspecialchars($ar_where); ?></div>
                                                </td>
                                                <td data-col="datetime">
                                                    <div class="eq-cell-name"><?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?></div>
                                                    <div class="eq-cell-sub rm-nowrap"><?php echo htmlspecialchars($ar_time); ?></div>
                                                </td>
                                                <td data-col="who">
                                                    <div class="eq-cell-name"><?php echo htmlspecialchars($ar_name); ?><?php if ($ar_is_stud): ?> <span class="rr-tag" title="Reserved by a student through a faculty account">Student</span><?php endif; ?></div>
                                                    <div class="eq-cell-sub rr-ellip" title="<?php echo htmlspecialchars($ar_line2_txt); ?>"><?php echo htmlspecialchars($ar_line2_txt); ?></div>
                                                </td>
                                                <td data-col="status">
                                                    <span class="ps-badge ps-badge--dot <?php echo $ar_badge; ?>"><?php echo htmlspecialchars($ar['status']); ?></span>
                                                </td>
                                                <td data-col="actions">
                                                    <div class="eq-act-wrap">
                                                        <?php if ($ar_cancellable): ?>
                                                            <button type="button" class="pr-tbl-btn btn-reschedule-rr-admin"
                                                                style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db;"
                                                                data-action="admin-reschedule-reservation"
                                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                                data-room-id="<?php echo (int)$ar['room_id']; ?>"
                                                                data-room-name="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                                data-faculty-name="<?php echo htmlspecialchars($ar['faculty_name']); ?>"
                                                                data-date="<?php echo htmlspecialchars($ar['reservation_date']); ?>"
                                                                data-start-time="<?php echo htmlspecialchars($ar['start_time']); ?>"
                                                                data-end-time="<?php echo htmlspecialchars($ar['end_time']); ?>"
                                                                data-notes="<?php echo htmlspecialchars($ar['notes'] ?? ''); ?>">Reschedule</button>
                                                            <button type="button" class="pr-tbl-btn cancel-r btn-cancel-rr-admin"
                                                                data-action="admin-cancel-reservation"
                                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                                data-room-name="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                                data-faculty-name="<?php echo htmlspecialchars($ar['faculty_name']); ?>">Cancel</button>
                                                        <?php endif; ?>
                                                        <button type="button" class="pr-tbl-btn review<?php echo $ar_reviewable ? ' is-primary' : ''; ?>" data-rr-detail><?php echo $ar_reviewable ? 'Review' : 'View'; ?></button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                    <tr id="rrNoMatch" style="display:none;">
                                        <td colspan="5" class="rm-empty-cell">No reservations match.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="inv-pager" id="rrPager" hidden>
                            <span class="inv-pager-info" id="rrPagerInfo"></span>
                            <div class="inv-pager-ctl" id="rrPagerCtl" role="navigation" aria-label="Reservation pages">
                                <button type="button" class="inv-pager-btn" id="rrPgPrev" aria-label="Previous page">
                                    <span class="material-symbols-outlined">chevron_left</span>
                                </button>
                                <div class="inv-pager-nums" id="rrPgNums"></div>
                                <button type="button" class="inv-pager-btn" id="rrPgNext" aria-label="Next page">
                                    <span class="material-symbols-outlined">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <script src="room-reservation/assets/js/admin-rooms-reservations-table.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/admin-rooms-reservations-table.js'); ?>"></script>
                </div><!-- /#rooms-reservations-panel -->

                <!-- ARCHIVED FACILITIES -->
                <div class="rooms-sub-panel" id="rooms-archived-panel">
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">inventory_2</span> Archived Facilities</h3>
                        </div>
                        <div style="padding: .75rem 1.25rem 0; font-size: .82rem; color: var(--text-light); border-bottom: 1px solid var(--khaki-border); padding-bottom: .75rem;">
                            Archived facilities are completely hidden from faculty and students — they cannot be browsed, booked, or found in any listing.
                            They can be restored at any time.<?php if ($is_super_admin): ?> Super admins can also permanently delete them once archived.<?php endif; ?>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table" id="roomsArchivedTable">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th style="width:220px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="rooms-archived-body">
                                    <?php echo ps_render_archived_tbody($rooms_archived, $is_super_admin); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-archived-panel -->

                




                <!-- ── Rooms: JSON data + schedule JS ──────────────────── -->
                <?php
                // Encode rooms list for schedule JS
                $ps_rooms_json = array_map(function ($r) {
                    return [
                        'room_id'    => (int)$r['room_id'],
                        'room_name'  => $r['room_name'],
                        'campus'     => $r['campus_name'],
                        'building'   => $r['building_name'],
                        'floor'      => !empty($r['floor_label']) ? $r['floor_label'] : ($r['floor_number'] . 'F'),
                    ];
                }, $rooms_list ?? []);

                // Approved reservations only (these appear on the schedule grid)
                $ps_rsvp_json = array_values(array_filter(
                    array_map(function ($ar) {
                        return [
                            'id'           => (int)$ar['id'],
                            'room_id'      => isset($ar['room_id']) ? (int)$ar['room_id'] : null,
                            'room_name'    => $ar['room_name'],
                            'date'         => $ar['reservation_date'],
                            'start_fmt'    => $ar['start_fmt'],
                            'end_fmt'      => $ar['end_fmt'],
                            'label'        => !empty($ar['purpose']) ? $ar['purpose'] : $ar['faculty_name'],
                            'purpose'      => (string)($ar['purpose'] ?? ''),
                            'faculty'      => $ar['faculty_name'],
                            'submitted_as' => (string)($ar['submitted_as'] ?? ''),
                            'submitted_by' => (string)($ar['submitted_by_name'] ?? ''),
                            'status'       => $ar['status'],
                        ];
                    }, $admin_room_reservations ?? []),
                    function ($ar) {
                        return $ar['status'] === 'Approved';
                    }
                ));
                ?>
                <script nonce="<?php echo $csp_nonce; ?>">
    window.PS_ROOMS_SCHEDULE_BOOT = {
        rooms: <?php echo json_encode(array_values($ps_rooms_json)); ?>,
        reservations: <?php echo json_encode($ps_rsvp_json); ?>
    };
</script>
<script src="room-reservation/assets/js/admin-rooms-schedule.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/admin-rooms-schedule.js'); ?>"></script>

            </div><!-- /panel-rooms -->
