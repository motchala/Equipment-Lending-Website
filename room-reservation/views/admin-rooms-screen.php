            <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
            <div class="tab-panel" id="panel-rooms">

                <!-- ── Page header + Add Room button ──────────────────── -->
                <div class="pr-page-header-row">
                    <div>
                        <h1 class="pr-page-title">Facilities Management</h1>
                        <p class="pr-page-subtitle">Browse facilities, reservations and reported issues.</p>
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

                        <!-- Body wrapper — matches equipment form padding -->
                        <div class="form-card-body">
                            <form method="POST" id="roomForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="room_id" id="room-form-id"
                                    value="<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>">

                                <!-- Building | Room name -->
                                <div class="form-row">
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
                                </div>

                                <!-- Floor | Status -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="rf-floor">Floor <span class="req-star" aria-label="required">*</span></label>
                                        <input type="number" name="floor_number" id="rf-floor" class="form-control-custom" min="1" max="20"
                                            value="<?php echo $edit_room ? (int)$edit_room['floor_number'] : 1; ?>" required>
                                        <!-- Filled by psSyncRoomFloorLimit(): the max follows the selected building's floor count -->
                                        <p class="rf-hint rf-floor-hint" id="rf-floor-hint" aria-live="polite">Select a building to see how many floors it has.</p>
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
                                <p class="rf-hint rf-hint-flush">Maintenance and Not Bookable facilities can&rsquo;t be reserved.</p>

                                <!-- Features -->
                                <?php
                                $preset_amenities = ['WiFi', 'A/C', 'Projector', 'PA System', 'Running Water', 'Safety Kit', 'Whiteboard', 'Smart TV'];
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

                                <!-- Rarely needed -->
                                <details class="rf-more" id="room-more-opts"
                                    <?php echo ($edit_room && (!empty($edit_room['floor_label']) || (int)$edit_room['sort_order'] > 0)) ? 'open' : ''; ?>>
                                    <summary>More options</summary>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="rf-floor-label">Floor name</label>
                                            <input type="text" name="floor_label" id="rf-floor-label" class="form-control-custom"
                                                value="<?php echo $edit_room ? htmlspecialchars($edit_room['floor_label'] ?? '') : ''; ?>"
                                                placeholder="e.g. Ground Floor">
                                        </div>
                                        <div class="form-group">
                                            <label for="rf-sort">Display order</label>
                                            <input type="number" name="sort_order" id="rf-sort" class="form-control-custom" min="0"
                                                value="<?php echo $edit_room ? (int)$edit_room['sort_order'] : 0; ?>">
                                        </div>
                                    </div>
                                    <p class="rf-hint">Floor name replaces the number in lists. Lower display order appears first.</p>
                                </details>

                                <!-- Row 6: Submit button -->
                                <button type="submit" id="room-form-submit-inner"
                                    name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                    class="btn-submit-form">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
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

                <!-- Tabs: main tabs on the left, Archived in its own container on the far right -->
                <div class="rm-tabbar">
                    <div class="pr-sub-tabs" id="rooms-toggle-wrap">
                        <button type="button" class="pr-sub-tab active" data-rooms-tab="rooms-active">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">meeting_room</span> Facilities
                        </button>
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-reservations">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">event_available</span> Reservations
                            <?php
                            $rm_resv_approved = count(array_filter($admin_room_reservations ?? [], function ($r) {
                                return ($r['status'] ?? '') === 'Approved';
                            }));
                            if ($rm_resv_approved > 0): ?>
                                <span class="pr-tab-badge" style="background:var(--text-light);"><?php echo $rm_resv_approved; ?></span>
                            <?php endif; ?>
                        </button>
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-issues">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">report_problem</span> Issues
                            <?php if (!empty($admin_room_issues_open)): ?>
                                <span class="pr-tab-badge"><?php echo (int)$admin_room_issues_open; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <div class="pr-sub-tabs rm-tabs-solo" id="rooms-archived-tab-wrap">
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-archived">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">inventory_2</span> Archived
                            <span class="pr-tab-badge" id="roomsArchivedBadge" style="background:var(--text-light);<?php echo empty($rooms_archived) ? 'display:none;' : ''; ?>"><?php echo count($rooms_archived); ?></span>
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

                        <script nonce="<?php echo $csp_nonce; ?>">
    window.PS_ROOMS_REGISTRY_BOOT = {
        floorsByBuilding: <?php echo json_encode($floors_by_building_js); ?>,
        defaultBuilding: <?php echo (int)$first_building_id; ?>
    };
</script>
<script src="room-reservation/assets/js/admin-rooms-registry-filter.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/admin-rooms-registry-filter.js'); ?>"></script>

                        <script src="room-reservation/assets/js/admin-rooms-registry-actions.js?v=<?php echo @filemtime(PS_ROOT . '/room-reservation/assets/js/admin-rooms-registry-actions.js'); ?>"></script>
                    <?php endif; ?>
                </div><!-- /#rooms-active-panel -->

                <!-- RESERVATIONS -->
                <div class="rooms-sub-panel" id="rooms-reservations-panel">
                    <?php
                    // Table rows start in "most recent request first" order (the sort dropdown re-orders them live).
                    $rr_rows = $admin_room_reservations ?? [];
                    usort($rr_rows, function ($a, $b) {
                        $ta = !empty($a['request_date']) ? (int)strtotime($a['request_date']) : 0;
                        $tb = !empty($b['request_date']) ? (int)strtotime($b['request_date']) : 0;
                        return ($tb <=> $ta) ?: ((int)$b['id'] <=> (int)$a['id']);
                    });
                    // Waiting = reservations for a later date (any admin decides them) plus adviser requests with an
                    // attached letter (Super Admin only - the query above never sends those to other admins).
                    $rr_is_super = !empty($ps_rooms_can_view_sensitive_adviser_requests);
                    ?>
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">calendar_month</span> Reservations</h3>
                            <div class="pr-search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="rrSearch" placeholder="Search facility or name" autocomplete="off" aria-label="Search reservations">
                            </div>
                        </div>
                        <div class="rr-status-bar">
                            <div class="rr-tabs" id="rrTabs" role="group" aria-label="Filter reservations by status">
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="all" aria-pressed="true">
                                    All <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab rr-active-chip" data-rr-tab="active" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">schedule</span> Active <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Waiting" aria-pressed="false">
                                    Waiting <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Approved" aria-pressed="false">
                                    Approved <span class="rr-tab-count"></span>
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
                                            $ar_badge = 'ps-badge--active';
                                            if ($ar['status'] === 'Declined')  $ar_badge = 'ps-badge--overdue';
                                            if ($ar['status'] === 'Cancelled') $ar_badge = 'ps-badge--returned';
                                            if ($ar['status'] === 'Waiting')   $ar_badge = 'ps-badge--waiting';
                                            // Every Waiting row this admin can see is theirs to decide: letter-based adviser
                                            // requests are only ever loaded for a Super Admin, and the API enforces the same rule.
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
                                        ?>
                                            <tr class="ps-rr-row<?php echo $ar_doc ? ' has-doc' : ''; ?>"
                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                data-room="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                data-where="<?php echo htmlspecialchars($ar_where); ?>"
                                                data-date="<?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?>"
                                                data-time="<?php echo htmlspecialchars($ar_time); ?>"
                                                data-who="<?php echo htmlspecialchars($ar_name); ?>"
                                                data-who-sub="<?php echo htmlspecialchars($ar_sub); ?>"
                                                data-type="<?php echo htmlspecialchars($ar_type); ?>"
                                                data-purpose="<?php echo htmlspecialchars($ar['purpose'] ?? ''); ?>"
                                                data-attendees="<?php echo (int)($ar['attendees'] ?? 0); ?>"
                                                data-status="<?php echo htmlspecialchars($ar['status']); ?>"
                                                data-reason="<?php echo htmlspecialchars($ar['reason'] ?? ''); ?>"
                                                data-notes="<?php echo htmlspecialchars($ar['notes'] ?? ''); ?>"
                                                data-submitted="<?php echo !empty($ar['request_date']) ? date('M d, Y g:i A', strtotime($ar['request_date'])) : ''; ?>"
                                                data-doc-url="<?php echo $ar_doc ? htmlspecialchars($ar_doc['url']) : ''; ?>"
                                                data-doc-open-url="<?php echo $ar_doc ? htmlspecialchars($ar_doc['open_url']) : ''; ?>"
                                                data-doc-name="<?php echo $ar_doc ? htmlspecialchars($ar_doc['name']) : ''; ?>"
                                                data-doc-kind="<?php echo $ar_doc ? $ar_doc['kind'] : ''; ?>"
                                                data-iso-date="<?php echo htmlspecialchars($ar['reservation_date']); ?>"
                                                data-start-time="<?php echo htmlspecialchars($ar['start_time']); ?>"
                                                data-end-time="<?php echo htmlspecialchars($ar['end_time']); ?>"
                                                data-arb-rule="<?php echo htmlspecialchars($ar['arb_rule'] ?? ''); ?>"
                                                data-arb-reason="<?php echo htmlspecialchars($ar['arb_reason'] ?? ''); ?>"
                                                data-submitted-ts="<?php echo !empty($ar['request_date']) ? (int)strtotime($ar['request_date']) : 0; ?>"
                                                data-can-review="<?php echo $ar_reviewable ? '1' : '0'; ?>"
                                                data-cancellable="<?php echo $ar_cancellable ? '1' : '0'; ?>">
                                                <td>
                                                    <div class="rm-main"><?php echo htmlspecialchars($ar['room_name']); ?></div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($ar_where); ?></div>
                                                </td>
                                                <td>
                                                    <div class="rm-main"><?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?></div>
                                                    <div class="rm-subline rm-nowrap"><?php echo htmlspecialchars($ar_time); ?></div>
                                                </td>
                                                <td>
                                                    <div class="rm-main">
                                                        <?php echo htmlspecialchars($ar_name); ?>
                                                        <?php if ($ar_doc): ?>
                                                            <a class="rm-clip" href="<?php echo htmlspecialchars($ar_doc['open_url']); ?>" target="_blank" rel="noopener noreferrer"
                                                                title="Open attachment: <?php echo htmlspecialchars($ar_doc['name']); ?>">
                                                                <span class="material-symbols-outlined">attach_file</span>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($ar_sub); ?></div>
                                                </td>
                                                <td><span class="ps-badge ps-badge--dot <?php echo $ar_badge; ?>"><?php echo htmlspecialchars($ar['status']); ?></span></td>
                                                <td>
                                                    <div class="rm-actions">
                                                        <?php if ($ar_cancellable): ?>
                                                            <button type="button" class="pr-tbl-btn cancel-r btn-cancel-rr-admin"
                                                                data-action="admin-cancel-reservation"
                                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                                data-room-name="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                                data-faculty-name="<?php echo htmlspecialchars($ar['faculty_name']); ?>"
                                                                title="Cancel this reservation">Cancel</button>
                                                        <?php endif; ?>
                                                        <button type="button" class="pr-tbl-btn review" data-rr-detail><?php echo $ar_reviewable ? 'Review' : 'View'; ?></button>
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
                            <div class="inv-pager-ctl" id="rrPagerCtl">
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

                <!-- ISSUES -->
                <div class="rooms-sub-panel" id="rooms-issues-panel">
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">report_problem</span> Facility Issues</h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th>Report</th>
                                        <th>Status</th>
                                        <th><span class="rm-sr">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($admin_room_issues)): ?>
                                        <tr>
                                            <td colspan="4" class="rm-empty-cell">No issue reports yet.</td>
                                        </tr>
                                        <?php else: foreach ($admin_room_issues as $issue):
                                            $iss_pill = 'pr-pill-open';
                                            if ($issue['status'] === 'Resolved')  $iss_pill = 'pr-pill-resolved';
                                            if ($issue['status'] === 'Dismissed') $iss_pill = 'pr-pill-dismissed';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="rm-main"><?php echo htmlspecialchars($issue['room_name']); ?></div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($issue['floor_label'] . ', ' . $issue['building_name']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="rm-clamp" title="<?php echo htmlspecialchars($issue['description']); ?>"><?php echo htmlspecialchars($issue['description']); ?></div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($issue['reported_by_name']); ?> &middot; <?php echo date('M d, Y', strtotime($issue['created_at'])); ?></div>
                                                    <?php if (!empty($issue['admin_notes'])): ?>
                                                        <div class="rm-subline rm-note" title="<?php echo htmlspecialchars($issue['admin_notes']); ?>">Note: <?php echo htmlspecialchars(mb_substr($issue['admin_notes'], 0, 60)); ?><?php echo mb_strlen($issue['admin_notes']) > 60 ? '&hellip;' : ''; ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="pr-pill <?php echo $iss_pill; ?>"><?php echo htmlspecialchars($issue['status']); ?></span></td>
                                                <td>
                                                    <?php if ($issue['status'] === 'Open'): ?>
                                                        <div class="rm-actions">
                                                            <button type="button" class="pr-tbl-btn review"
                                                                data-action="open-issue-review"
                                                                data-issue-id="<?php echo (int)$issue['id']; ?>"
                                                                data-room-name="<?php echo htmlspecialchars($issue['room_name']); ?>"
                                                                data-reporter="<?php echo htmlspecialchars($issue['reported_by_name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($issue['description']); ?>"
                                                                title="Review this issue report">Review</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-issues-panel -->

                <!-- ARCHIVED -->
                <div class="rooms-sub-panel" id="rooms-archived-panel">
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">archive</span> Archived Facilities</h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th><span class="rm-sr">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody id="rooms-archived-body">
                                    <?php echo ps_render_archived_tbody($rooms_archived, $is_super_admin); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-archived-panel -->

                <!-- /#rooms-issues-panel (end of sub-panels) -->




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


