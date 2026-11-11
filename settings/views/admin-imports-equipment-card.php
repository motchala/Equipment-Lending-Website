<?php
/* ============================================================
   Imports -> Equipment Inventory card  (Super Admin only; included by admin-imports-screen.php)

   Same card shell as Faculty Registry / Rooms Assigned (header, stats, last-import line,
   guide link, footer), but the equipment list is not uploaded as a file: it is read from a
   Google Sheet that is linked by URL.

     - Google Sheets link + Save link
     - Sync now (+ the last sync time)
     - Categories: choose a campus, see its categories, add a new one, delete an unused one
     - footer: Imported list (the items the sheet brought in) / Open Google Sheet

   Like the other cards it holds placeholders only while Imports is locked: the link, the
   numbers and the categories are fetched after unlock (settings/api/equipment-import.php).
   Script: settings/assets/js/admin-imports.js (initEquipmentCard)   Styles: admin-imports.css (.imp-eq-*)
   No inline styles or handlers (CSP).
============================================================ */
if (!$is_super_admin) { return; }
?>
                    <section class="imp-card imp-card--eq" data-kind="equipment">
                        <header class="imp-card-head">
                            <div class="imp-card-icon"><span class="material-symbols-outlined"><?= $c['icon'] ?></span></div>
                            <div class="imp-card-titles">
                                <h4><?= htmlspecialchars($c['title']) ?></h4>
                                <p><?= htmlspecialchars($c['sub']) ?></p>
                            </div>
                            <span class="imp-chip imp-chip--muted" data-role="chip">•••</span>
                        </header>

                        <div class="imp-card-body">
                            <p class="imp-what"><?= htmlspecialchars($c['what']) ?></p>
                            <button type="button" class="imp-guide-link" data-guide="equipment"><span class="material-symbols-outlined">menu_book</span>How to prepare this sheet</button>

                            <div class="imp-stats">
                                <div class="imp-stat"><span class="imp-stat-num" data-role="stat-count">•••</span><span class="imp-stat-label"><?= $c['stats'][0] ?></span></div>
                                <div class="imp-stat"><span class="imp-stat-num" data-role="stat-extra">••</span><span class="imp-stat-label"><?= $c['stats'][1] ?></span></div>
                                <div class="imp-stat"><span class="imp-stat-num" data-role="stat-rows">••</span><span class="imp-stat-label"><?= $c['stats'][2] ?></span></div>
                            </div>
                            <p class="imp-last" data-role="last"><span class="material-symbols-outlined">history</span><span data-role="last-text">Last sync •••••••••</span></p>

                            <!-- Google Sheets link -->
                            <div class="imp-eq-block">
                                <label class="imp-eq-label" for="imp-eq-url">Google Sheets link</label>
                                <div class="imp-eq-row">
                                    <input type="url" id="imp-eq-url" class="imp-eq-input" data-role="url" placeholder="https://docs.google.com/spreadsheets/d/..." maxlength="1000" autocomplete="off" spellcheck="false">
                                    <button type="button" class="ps-btn ps-btn--ghost" data-role="save-url" disabled>
                                        <span class="material-symbols-outlined">link</span> Save link
                                    </button>
                                </div>
                                <p class="imp-eq-help">Set the sheet to <strong>&ldquo;Anyone with the link can view&rdquo;</strong>. Each tab of the sheet becomes a category.</p>
                            </div>

                            <div class="imp-alert hidden" data-role="alert" role="alert"></div>

                            <button type="button" class="ps-btn ps-btn--primary imp-import-btn" data-role="sync" disabled>
                                <span class="material-symbols-outlined">sync</span> Sync now
                            </button>

                            <!-- Categories -->
                            <fieldset class="imp-mode imp-eq-cats" data-role="cats-box">
                                <legend>Categories</legend>
                                <div class="imp-eq-row imp-eq-row--campus">
                                    <label class="imp-eq-sublabel" for="imp-eq-campus">Campus</label>
                                    <select id="imp-eq-campus" class="imp-eq-input imp-eq-select" data-role="campus"></select>
                                </div>
                                <div class="imp-eq-catlist" data-role="cats" aria-live="polite"></div>
                                <div class="imp-eq-row">
                                    <input type="text" class="imp-eq-input" data-role="cat-input" placeholder="New category name" maxlength="100" autocomplete="off" aria-label="New category name">
                                    <button type="button" class="ps-btn ps-btn--primary" data-role="cat-add" disabled>
                                        <span class="material-symbols-outlined">add</span> Add
                                    </button>
                                </div>
                                <p class="imp-eq-note hidden" data-role="cat-msg" role="status"></p>
                            </fieldset>
                        </div>

                        <footer class="imp-card-foot">
                            <button type="button" class="imp-foot-btn" data-role="view-list" disabled>
                                <span class="material-symbols-outlined">table_view</span> Imported list
                            </button>
                            <button type="button" class="imp-foot-btn" data-role="open-sheet" disabled>
                                <span class="material-symbols-outlined">open_in_new</span> Open Google Sheet
                            </button>
                        </footer>
                    </section>
