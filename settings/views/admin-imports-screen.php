<!-- ============================================================
     TAB: IMPORTS  (Super Admin only)  -  its own sidebar screen (was Settings -> Imports)
     Equipment Inventory (linked Google Sheet: Sync now + categories) + Rooms Assigned +
     Faculty Registry imports (no password lock: the screen is open to the Super Admin).
     The page holds placeholders: real numbers, lists and
     files are fetched when it opens (settings/api/imports.php).
     Rooms Assigned is its own import (settings/api/rooms-import.php): it never touches the Faculty Registry.
     Equipment Inventory is not a file upload: its card is settings/views/admin-imports-equipment-card.php and it is
     served by settings/api/equipment-import.php (the Google Sheets sync the Settings -> Equipment tab also drives).
     Script: settings/assets/js/admin-imports.js
     Styles: settings/assets/css/admin-imports.css
     Layout: page header (title + session pill) / [ import cards | sticky import guide ]
============================================================ -->
<?php if ($is_super_admin): ?>
<?php
$imp_cards = [
    'equipment' => [
        // Not a file upload: drawn by admin-imports-equipment-card.php (Google Sheets link, Sync now, categories)
        'title'   => 'Equipment Inventory',
        'icon'    => 'inventory_2',
        'sub'     => 'Equipment list synced from a Google Sheet',
        'stats'   => ['Items', 'Categories', 'Archived'],
        'what'    => 'Items added to equipment inventory for loan. They are read from the linked Google Sheet, and each tab of the sheet becomes a category.',
    ],
    'faculty' => [
        'title'  => 'Faculty Registry',
        'icon'   => 'badge',
        'sub'    => 'Faculty Identification Records',
        'types'  => 'PDF or XLSX',
        'accept' => '.pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'stats'  => ['Faculty', 'Newly hired', 'Rows in file'],
        'cols'   => ['#', 'Faculty ID', 'Name', 'Programs'],
        'what'   => 'Used to confirm a faculty member when their account is created. A Faculty Rooms workbook also brings in each faculty member\'s weekly classes and rooms, and a Room Timetables workbook adds the classes that have no instructor yet. Both block those rooms for reservations.',
        'replace' => 'Remove everyone listed first',
    ],
    'rooms' => [
        'title'   => 'Rooms Assigned',
        'icon'    => 'meeting_room',
        'sub'     => 'Rooms assigned to faculty and classes',
        'types'   => 'XLSX',
        'accept'  => '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'stats'   => ['Rooms', 'Faculty', 'Rows in file'],
        'cols'    => ['#', 'Room', 'Building', 'Assigned to', 'Class', 'Day & time'],
        'what'    => 'Each class in the file is saved with its room, day and time. Those rooms are blocked for reservations at those hours, every week. This list is separate from the Faculty Registry.',
        'replace' => 'Remove every saved class first',
    ],
];
?>

<div class="tab-panel" id="panel-imports">

    <!-- Page header: same pattern as every other screen. -->
    <div class="imp-page-head admin-screen-header">
        <div class="imp-page-head-text">
            <h1 class="sett-page-title">Imports</h1>
            <p class="sett-page-sub">Bring in the equipment inventory, the room assignments and the official faculty list. The original file of the latest import is kept on record.</p>
        </div>
    </div>

    <div class="imp-shell is-unlocked" id="imp-shell">

        <div class="imp-content" id="imp-content">
            <?= csrf_field() ?>

            <div class="imp-layout">
                <!-- Left: one card per import -->
                <div class="imp-main">
                <?php foreach ($imp_cards as $kind => $c): $soon = !empty($c['pending']);
                    if ($kind === 'equipment') { require __DIR__ . '/admin-imports-equipment-card.php'; continue; } ?>
                    <section class="imp-card<?= $soon ? ' imp-card--soon' : '' ?>" data-kind="<?= $kind ?>">
                        <header class="imp-card-head">
                            <div class="imp-card-icon"><span class="material-symbols-outlined"><?= $c['icon'] ?></span></div>
                            <div class="imp-card-titles">
                                <h4><?= htmlspecialchars($c['title']) ?></h4>
                                <p><?= htmlspecialchars($c['sub']) ?></p>
                            </div>
                            <span class="imp-chip imp-chip--muted" data-role="chip"><?= $soon ? 'Coming soon' : '•••' ?></span>
                        </header>

                        <div class="imp-card-body">
                            <p class="imp-what"><?= htmlspecialchars($c['what']) ?></p>
                            <button type="button" class="imp-guide-link" data-guide="<?= $kind ?>"><span class="material-symbols-outlined">menu_book</span>How to prepare this file</button>

                            <div class="imp-stats">
                                <div class="imp-stat"><span class="imp-stat-num" data-role="stat-count"><?= $soon ? '—' : '•••' ?></span><span class="imp-stat-label"><?= $c['stats'][0] ?></span></div>
                                <div class="imp-stat"><span class="imp-stat-num" data-role="stat-extra"><?= $soon ? '—' : '••' ?></span><span class="imp-stat-label"><?= $c['stats'][1] ?></span></div>
                                <div class="imp-stat"><span class="imp-stat-num" data-role="stat-rows"><?= $soon ? '—' : '••' ?></span><span class="imp-stat-label"><?= $c['stats'][2] ?></span></div>
                            </div>
                            <p class="imp-last" data-role="last"><span class="material-symbols-outlined">history</span><span data-role="last-text"><?= $soon ? 'Nothing imported yet' : 'Last import •••••••••' ?></span></p>

                            <div class="imp-drop" data-role="drop" tabindex="0" role="button" aria-label="Choose the <?= htmlspecialchars($c['title']) ?> file">
                                <input type="file" class="imp-file-input" data-role="input" accept="<?= $c['accept'] ?>">
                                <span class="material-symbols-outlined imp-drop-icon">upload_file</span>
                                <strong>Drop the file here</strong>
                                <span class="imp-drop-hint">or click to browse &middot; <?= $c['types'] ?> &middot; up to 10 MB</span>
                            </div>

                            <div class="imp-picked hidden" data-role="picked">
                                <span class="material-symbols-outlined imp-picked-icon" data-role="picked-icon">description</span>
                                <div class="imp-picked-info">
                                    <strong data-role="picked-name"></strong>
                                    <span data-role="picked-size"></span>
                                </div>
                                <button type="button" class="imp-icon-btn" data-role="clear" aria-label="Remove file"><span class="material-symbols-outlined">close</span></button>
                            </div>

                            <div class="imp-alert hidden" data-role="alert" role="alert"></div>

                            <div class="imp-preview hidden" data-role="preview">
                                <div class="imp-preview-head"><span data-role="preview-count"></span></div>
                                <div class="imp-table-wrap">
                                    <table class="imp-table">
                                        <thead>
                                            <tr><?php foreach ($c['cols'] as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
                                        </thead>
                                        <tbody data-role="preview-body"></tbody>
                                    </table>
                                </div>
                            </div>

                            <fieldset class="imp-mode" data-role="mode">
                                <legend>When it is imported</legend>
                                <div class="imp-mode-row">
                                    <label class="imp-mode-opt">
                                        <input type="radio" name="imp-mode-<?= $kind ?>" value="merge" checked>
                                        <span class="imp-mode-box">
                                            <span class="material-symbols-outlined">merge_type</span>
                                            <span><strong>Merge</strong><small>Add new entries, update existing ones</small></span>
                                        </span>
                                    </label>
                                    <label class="imp-mode-opt">
                                        <input type="radio" name="imp-mode-<?= $kind ?>" value="replace">
                                        <span class="imp-mode-box imp-mode-box--warn">
                                            <span class="material-symbols-outlined">sync_alt</span>
                                            <span><strong>Replace</strong><small><?= htmlspecialchars($c['replace']) ?></small></span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

                            <button type="button" class="ps-btn ps-btn--primary imp-import-btn" data-role="import" disabled>
                                <span class="material-symbols-outlined">cloud_upload</span> Import
                            </button>
                        </div>

                        <footer class="imp-card-foot">
                            <button type="button" class="imp-foot-btn" data-role="view-list">
                                <span class="material-symbols-outlined">table_view</span> Imported list
                            </button>
                            <button type="button" class="imp-foot-btn" data-role="view-files">
                                <span class="material-symbols-outlined">folder_open</span> Original file
                            </button>
                        </footer>
                    </section>
                <?php endforeach; ?>
                </div><!-- /imp-main -->

                <!-- Right: import guide (sticky on wide screens) -->

                <?php
                // ── Import guide: what each file has to look like, column by column ──
                // Faculty Registry is the real, working import (rules below come from its parser).
                // Rooms Assigned is the Faculty Rooms workbook (rules below come from room-reservation/core/class-schedule.php).
                // Equipment Inventory is the Google Sheet layout (rules below come from equipment-booking/api/sync-inventory.php).
                $gh = function ($s) {
                    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
                };
                $imp_guide_default = 'rooms';
                $imp_guide = [
                    'equipment' => [
                        'label'   => 'Equipment Inventory',
                        'planned' => false,
                        'formats' => [['Google Sheets', 'Required', 'ok'], ['XLSX / CSV file', 'Not used here', 'no'], ['Private sheet', 'Will not sync', 'no']],
                        'why'     => 'Nothing is uploaded. PUPSync reads the sheet straight from Google, so it has to be shared as "Anyone with the link can view". Press Sync now to bring it in; PUPSync also checks the sheet for changes by itself while an admin page is open.',
                        'cols'    => [
                            ['Name (or Description)', true,  'The item name people see when they borrow it. It is read from the column headed Name, or Description when there is no Name column. A row with no name, or only a number, is skipped.', 'HDMI Cable'],
                            ['Status',                true,  'The working state. OK or Working counts as Good. NG, Not working or For repair counts as For Repair. Anything else counts as Fair.', 'OK'],
                            ['Quantity',              false, 'Use it when one row stands for several identical units. A whole number, 0 or more. Leave the Control No. column out of a sheet that uses Quantity.', '5'],
                            ['Control No.',           false, 'Use it instead of Quantity when each row is one physical unit. Units with the same name are counted for you, and only OK or Working units are counted as available.', 'CITE-0042'],
                        ],
                        'rows'    => [['HDMI Cable', 'OK', '5', ''], ['Extension Cord', 'NG', '2', '']],
                        'tips'    => [
                            'Each tab of the sheet is one category, named after the tab. Tabs named Offices Inventory, Software List, Sports & Development Equipment, Ventilation and Fire Extinguisher are skipped.',
                            'The header row has to contain Name (or Description) and Status. Rows above it can hold titles. Columns are found by their header names, so their order does not matter.',
                            'To say which campus a tab belongs to, type Campus: in one cell and Canlalay (PUP CITE) or Zapote (PUP MAIN) in the cell next to it, above the header. With nothing there the tab is read as PUP CITE.',
                            'Items are matched by name within a campus: existing ones are updated and new ones are added. Units that are out on loan right now are taken off the quantity, so a sync never wipes an active borrow.',
                            'An item that is no longer in the sheet is archived, not deleted. It leaves the borrowing list but stays on record.',
                            'Photos cannot come from the sheet. Add them afterwards on the Equipment page (JPG or PNG, up to 2 MB each).',
                        ],
                    ],
                    'rooms' => [
                        'label'   => 'Rooms Assigned',
                        'planned' => false,
                        'formats' => [['XLSX', 'Best choice', 'ok'], ['CSV', 'Not accepted', 'no'], ['PDF', 'Not for this list', 'no']],
                        'why'     => 'Each class sits on its own row, with its room and meeting times in separate cells, so nothing is guessed from a layout. For a CSV, open it in Excel and save it as .xlsx first.',
                        'cols'    => [
                            ['Faculty',         true,  'The instructor\'s full name. One class per row, so a faculty member appears on as many rows as they have classes.', 'Maria L. Santos'],
                            ['Faculty ID',      false, 'Leave it blank or type Newly hired (no ID yet) if the person has no ID yet. Their classes attach to their account as soon as it is created.', 'FA0006BN2024'],
                            ['Status',          false, 'Permanent, Part-Time and so on. For reference only.', 'Permanent'],
                            ['Room',            true,  'The room code: building letter + room number (A402, B203, C101). A computer lab is CL + number (CL207). A room that is not under Facilities yet is added for you.', 'A402'],
                            ['Subject Code',    false, 'The course code.', 'COMP 017'],
                            ['Subject',         false, 'The course title.', 'Multimedia'],
                            ['Section / Block', false, 'The class or section that uses the room.', 'BSIT 3-2'],
                            ['Component',       false, 'Lecture or Laboratory. Blank counts as Lecture.', 'Laboratory'],
                            ['Hours / week',    false, 'For reference only. The hours are worked out from the meeting times.', '3'],
                            ['Meeting times',   true,  'Day, then start and end time, with a for AM and p for PM. Days are Mon to Sat. Two meetings in one cell are separated by a semicolon.', 'Tue 1:00p–4:00p; Fri 8:00a–11:00a'],
                        ],
                        'rows'    => [
                            ['Maria L. Santos', 'FA0006BN2024', 'Permanent', 'C101', 'COMP 017', 'Multimedia', 'BSIT 3-2', 'Laboratory', '3', 'Wed 1:00p–4:00p'],
                            ['Ana B. Cruz', 'Newly hired (no ID yet)', 'Part-Time', 'A203', 'BUMA 012', 'Strategic Management', 'BSBA-HRM 3-1', 'Lecture', '3', 'Sat 2:00p–5:00p'],
                        ],
                        'tips'    => [
                            'Put the column names in row 1 and start the classes in row 2. Only the first worksheet is read. No title rows, blank rows or merged cells.',
                            'Faculty, Room and Meeting times are the columns the import needs. The other columns can be left out.',
                            'Write each meeting as Day start–end, for example Mon 8:00a–11:00a. A group can follow in brackets, for example Fri 8:00a–11:00a (Grp A). Sunday is closed.',
                            'Every class repeats each week until the next import replaces it. It blocks its room for reservations the same way an approved reservation does.',
                            'Merge replaces the classes of the faculty listed in the file and leaves everyone else as they are. Replace removes every saved class first. Classes with no instructor yet are kept either way.',
                            'This import does not change the Faculty Registry. A Room Timetables workbook (one grid per room) also works: it only adds the classes that have no instructor yet.',
                            'Up to 20,000 rows and 10 MB per file.',
                        ],
                    ],
                    'faculty' => [
                        'label'   => 'Faculty Registry',
                        'planned' => false,
                        'formats' => [['XLSX', 'Best choice', 'ok'], ['PDF', 'Official document only', 'alt'], ['CSV', 'Not accepted', 'no']],
                        'why'     => 'XLSX is read exactly as typed. The PDF must be the official Faculty Identification Records document, and it has to be a text PDF (you can select the words), not a scan or a photo. For a CSV, open it in Excel and save it as .xlsx first.',
                        'cols'    => [
                            ['Faculty ID',      true,  'Leave it blank or type NEWLY HIRED if the person has no ID yet. Otherwise 5 to 30 letters and numbers (hyphens allowed) with at least one digit.', 'FA104BN021'],
                            ['Professor Name',  true,  'Full name. A row with no name is skipped.', 'Santos, Maria'],
                            ['Programs Handled', false, 'The programs or departments, separated by commas.', 'BSIT, DIT'],
                        ],
                        'rows'    => [['FA104BN021', 'Santos, Maria', 'BSIT, DIT'], ['NEWLY HIRED', 'Cruz, Ana', 'BSBA']],
                        'tips'    => [
                            'Only the first worksheet is read. Extra sheets are ignored.',
                            'A header row is optional. If you add one, make it row 1 and use Faculty ID, Professor Name and Programs Handled (ID, Name and Program also work). No title or logo rows above it.',
                            'Without a header row, the columns are read in this order: A = ID, B = name, C = programs.',
                            'Format the ID column as Text so Excel does not change the IDs, and avoid merged cells.',
                            'Up to 5,000 rows and 10 MB per file. Rows with an invalid ID are skipped, and you are shown which ones after the import.',
                            'Merge updates the people already listed (matched by Faculty ID, new hires by name) and adds the rest. Replace removes everyone first.',
                        ],
                    ],
                ];
                $imp_guide_order = ['equipment', 'faculty', 'rooms'];
                ?>
                <aside class="imp-guide" id="imp-guide" aria-labelledby="imp-guide-title">
                    <header class="imp-guide-head">
                        <span class="imp-card-icon"><span class="material-symbols-outlined">menu_book</span></span>
                        <div class="imp-card-titles">
                            <h4 id="imp-guide-title">Import guide</h4>
                            <p>How to prepare each file before you import it</p>
                        </div>
                    </header>

                    <div class="imp-guide-tabs" role="tablist" aria-label="Choose an import">
                        <?php foreach ($imp_guide_order as $gk): $g = $imp_guide[$gk];
                            $on = ($gk === $imp_guide_default); ?>
                            <button type="button" class="imp-gtab" role="tab" id="imp-gtab-<?= $gk ?>" aria-controls="imp-gpanel-<?= $gk ?>" aria-selected="<?= $on ? 'true' : 'false' ?>" tabindex="<?= $on ? '0' : '-1' ?>" data-kind="<?= $gk ?>">
                                <span class="imp-gdot"></span><?= $gh($g['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($imp_guide_order as $gk): $g = $imp_guide[$gk]; ?>
                        <div class="imp-guide-panel" role="tabpanel" id="imp-gpanel-<?= $gk ?>" aria-labelledby="imp-gtab-<?= $gk ?>" data-kind="<?= $gk ?>" <?= $gk === $imp_guide_default ? '' : ' hidden' ?>>
                            <?php if (!empty($g['planned'])): ?>
                                <div class="imp-g-planned">
                                    <span class="material-symbols-outlined">construction</span>
                                    <span><strong>Planned layout.</strong> This import is not built yet. Prepare your file this way so it is ready when the import is switched on.</span>
                                </div>
                            <?php endif; ?>

                            <section class="imp-g-sec">
                                <h5>Best file type</h5>
                                <div class="imp-g-formats">
                                    <?php foreach ($g['formats'] as $f): ?>
                                        <span class="imp-fmt imp-fmt--<?= $gh($f[2]) ?>"><span class="material-symbols-outlined"><?= $f[2] === 'no' ? 'close' : 'check' ?></span><b><?= $gh($f[0]) ?></b><?= $gh($f[1]) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <p class="imp-g-why"><?= $gh($g['why']) ?></p>
                            </section>

                            <section class="imp-g-sec">
                                <h5>Columns, left to right</h5>
                                <ol class="imp-g-cols">
                                    <?php foreach ($g['cols'] as $ci => $col): ?>
                                        <li>
                                            <span class="imp-g-letter" aria-hidden="true"><?= chr(65 + $ci) ?></span>
                                            <div class="imp-g-col">
                                                <div class="imp-g-colhead">
                                                    <strong><?= $gh($col[0]) ?></strong>
                                                    <span class="imp-req imp-req--<?= $col[1] ? 'yes' : 'no' ?>"><?= $col[1] ? 'Required' : 'Optional' ?></span>
                                                </div>
                                                <p><?= $gh($col[2]) ?></p>
                                                <p class="imp-g-ex">e.g. <code><?= $gh($col[3]) ?></code></p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>

                            <section class="imp-g-sec">
                                <h5>What your sheet should look like</h5>
                                <div class="imp-sheet-wrap">
                                    <table class="imp-sheet">
                                        <thead>
                                            <tr class="is-letters">
                                                <th></th><?php foreach ($g['cols'] as $ci => $col): ?><th><?= chr(65 + $ci) ?></th><?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="is-head">
                                                <td class="is-gutter">1</td><?php foreach ($g['cols'] as $col): ?><td><?= $gh($col[0]) ?></td><?php endforeach; ?>
                                            </tr>
                                            <?php foreach ($g['rows'] as $ri => $row): ?>
                                                <tr>
                                                    <td class="is-gutter"><?= $ri + 2 ?></td><?php foreach ($row as $cell): ?><td><?= $gh($cell) ?></td><?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="imp-g-sec">
                                <h5>Before you import</h5>
                                <ul class="imp-g-tips">
                                    <?php foreach ($g['tips'] as $tip): ?>
                                        <li><span class="material-symbols-outlined">task_alt</span><span><?= $gh($tip) ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        </div>
                    <?php endforeach; ?>
                </aside>
            </div><!-- /imp-layout -->
        </div><!-- /imp-content -->

    </div><!-- /imp-shell -->
</div><!-- /panel-imports -->
<?php endif; ?>
