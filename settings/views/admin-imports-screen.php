<!-- ============================================================
     TAB: IMPORTS  (Super Admin only)  -  its own sidebar screen (was Settings -> Imports)
     Equipment Inventory + Rooms Assigned (placeholders: those imports are not built yet) +
     Faculty Registry imports behind ONE password unlock.
     The page only ever contains placeholders: real numbers, lists and
     files are fetched after unlock (settings/api/imports.php).
     Script: settings/assets/js/admin-imports.js
     Styles: settings/assets/css/admin-imports.css
     Layout: page header (title + session pill) / [ import cards | sticky import guide ]
============================================================ -->
<?php if ($is_super_admin): ?>
<?php
$imp_cards = [
    'equipment' => [
        'pending' => true,   // clickable, but nothing is read, uploaded or imported (see initPlaceholderCard)
        'title'   => 'Equipment Inventory',
        'icon'    => 'inventory_2',
        'sub'     => 'Equipment lists with item names and stock counts',
        'types'   => 'XLSX or CSV',
        'accept'  => '.xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'stats'   => ['Items', 'Categories', 'Rows in file'],
        'cols'    => ['#', 'Item name', 'Category', 'Quantity', 'Condition'],
        'what'    => 'Items added to equipment inventory for loan.',
        'replace' => 'Remove the current items first',
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
        'pending' => true,   // clickable, but nothing is read, uploaded or imported (see initPlaceholderCard)
        'title'   => 'Rooms Assigned',
        'icon'    => 'meeting_room',
        'sub'     => 'Rooms assigned to faculty and classes',
        'types'   => 'XLSX or CSV',
        'accept'  => '.xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'stats'   => ['Rooms', 'Faculty', 'Rows in file'],
        'cols'    => ['#', 'Room', 'Building', 'Floor', 'Assigned to'],
        'what'    => 'Each room on this list is assigned to the faculty or class that uses it.',
        'replace' => 'Remove the current assignments first',
    ],
];
?>

<div class="tab-panel" id="panel-imports">

    <!-- Page header: same pattern as every other screen. The session pill only shows while unlocked. -->
    <div class="imp-page-head admin-screen-header">
        <div class="imp-page-head-text">
            <h1 class="sett-page-title">Imports</h1>
            <p class="sett-page-sub">Bring in the equipment inventory, the room assignments and the official faculty list. The original file of the latest import is kept on record.</p>
        </div>
        <div class="imp-session" id="imp-session" hidden>
            <span class="imp-session-dot"></span>
            <span class="imp-session-text" id="imp-session-text">Unlocked</span>
            <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" id="imp-lock-now">
                <span class="material-symbols-outlined">lock</span> Lock
            </button>
        </div>
    </div>

    <div class="imp-shell is-locked" id="imp-shell">

        <div class="imp-content" id="imp-content" inert aria-hidden="true">
            <?= csrf_field() ?>

            <div class="imp-layout">
                <!-- Left: one card per import -->
                <div class="imp-main">
                <?php foreach ($imp_cards as $kind => $c): $soon = !empty($c['pending']); ?>
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
                // Equipment Inventory and Rooms Assigned are the planned layouts for when those imports are built.
                $gh = function ($s) {
                    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
                };
                $imp_guide_default = 'rooms';
                $imp_guide = [
                    'equipment' => [
                        'label'   => 'Equipment Inventory',
                        'planned' => true,
                        'formats' => [['XLSX', 'Best choice', 'ok'], ['CSV', 'Works too', 'alt'], ['PDF', 'Not for this list', 'no']],
                        'why'     => 'Excel keeps every value in its own cell, so quantities stay numbers and a comma in a description never shifts the columns. If you use CSV, save it as "CSV UTF-8" so special characters survive.',
                        'cols'    => [
                            ['Item name',   true,  'The name students see when they borrow it. One row for each kind of item.', 'HDMI Cable'],
                            ['Category',    true,  'Pick one: Audio/Visual, Cables & Connectors, Computing, Lab Equipment, Networking, Power, Tools or Others.', 'Cables & Connectors'],
                            ['Quantity',    true,  'Total units you own. A whole number, 0 or more.', '5'],
                            ['Condition',   false, 'Good, Fair or For Repair. Leave it blank for Good.', 'Good'],
                            ['Description', false, 'A short note, up to 500 characters.', '1.5 m, braided'],
                        ],
                        'rows'    => [['HDMI Cable', 'Cables & Connectors', '5', 'Good', '1.5 m, braided'], ['Projector', 'Audio/Visual', '2', 'Fair', 'Acer, HDMI + VGA']],
                        'tips'    => [
                            'Put the column names in row 1 and start your data in row 2. Nothing above the headers, no blank rows, no merged cells, no totals row.',
                            'One row per kind of item. Put the total in Quantity instead of repeating the row for every unit.',
                            'Spell each item the same way every time, because Merge will match items by name.',
                            'Photos cannot come from the file. Add them afterwards on the Equipment page (JPG or PNG, up to 2 MB each).',
                            'Keep each file under 5,000 rows and 10 MB, the same limits as the faculty import.',
                        ],
                    ],
                    'rooms' => [
                        'label'   => 'Rooms Assigned',
                        'planned' => true,
                        'formats' => [['XLSX', 'Best choice', 'ok'], ['CSV', 'Works too', 'alt'], ['PDF', 'Not for this list', 'no']],
                        'why'     => 'A spreadsheet keeps room names, floors and faculty names in separate cells, so nothing is guessed from a layout. If you use CSV, save it as "CSV UTF-8".',
                        'cols'    => [
                            ['Room',            true,  'Exactly as it is named under Facilities.', 'Room 203'],
                            ['Building',        true,  'Building A (Old), Building B (New) or Building C (CITE).', 'Building B (New)'],
                            ['Floor',           true,  'The floor label, or just the floor number.', '2nd Floor'],
                            ['Assigned to',     true,  'The faculty member, as written in the Faculty Registry (Faculty ID or full name).', 'Santos, Maria'],
                            ['Section / class', false, 'The class or section that uses the room.', 'BSIT 3-1'],
                        ],
                        'rows'    => [['Room 203', 'Building B (New)', '2nd Floor', 'Santos, Maria', 'BSIT 3-1'], ['Audiovisual Room', 'Building C (CITE)', '1st Floor', 'Reyes, Jose', '']],
                        'tips'    => [
                            'Room and building names must match Facilities exactly, spaces and spelling included ("Room 203", not "203"). A name that does not match cannot be linked to a room.',
                            'Import the Faculty Registry first, so every name in "Assigned to" already exists.',
                            'One assignment per row. If two faculty share a room, give each of them their own row.',
                            'Put the column names in row 1 and start your data in row 2. No blank rows or merged cells.',
                            'Keep each file under 5,000 rows and 10 MB.',
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

        <div class="imp-lock" id="imp-lock">
            <div class="imp-lock-inner">
                <button type="button" class="imp-lock-btn" id="imp-lock-btn" aria-label="Unlock Imports">
                    <span class="material-symbols-outlined">lock</span>
                </button>
                <h4>Imports are locked</h4>
                <p>Click the lock and enter your password to view or make imports, or to open the files that were imported.</p>
                <p class="imp-lock-msg hidden" id="imp-lock-msg"></p>
                <span class="imp-lock-note"><span class="material-symbols-outlined">lock</span> Password required to unlock</span>
            </div>
        </div>

    </div><!-- /imp-shell -->
</div><!-- /panel-imports -->
<?php endif; ?>
