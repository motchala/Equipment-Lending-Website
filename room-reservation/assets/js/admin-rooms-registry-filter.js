                            /* ── PUPSync Room Registry filter module ─────────────────
                               Search + Building/Floor/Status dropdowns. Depends on
                               the CSP-safe JSON data block emitted by the PHP view. */
                            (function() {
                                'use strict';

                                var boot = window.PS_ROOMS_REGISTRY_BOOT;
                                if (!boot) {
                                    var bootData = document.getElementById('psRoomsRegistryBootData');
                                    if (!bootData) {
                                        console.error('Room registry filter data is missing.');
                                        return;
                                    }
                                    try {
                                        boot = JSON.parse(bootData.textContent || '{}');
                                    } catch (error) {
                                        console.error('Unable to parse room registry filter data.', error);
                                        return;
                                    }
                                    window.PS_ROOMS_REGISTRY_BOOT = boot;
                                }
                                if (!boot.floorsByBuilding) {
                                    console.error('Room registry filter data has no floor mapping.');
                                    return;
                                }
                                let FLOORS_BY_BUILDING = boot.floorsByBuilding;
                                const DEFAULT_BUILDING = String(boot.defaultBuilding);

                                const table = document.getElementById('roomsRegistryTable');
                                const bldSel = document.getElementById('roomBuildingSelect');
                                const flrSel = document.getElementById('roomFloorSelect');
                                const stSel = document.getElementById('roomStatusSelect');
                                const search = document.getElementById('roomSearchInput');
                                if (!table || !bldSel || !flrSel || !stSel || !search) return;

                                function populateFloorSelect(buildingId) {
                                    flrSel.innerHTML = '';
                                    const allOpt = document.createElement('option');
                                    allOpt.value = 'all';
                                    allOpt.textContent = 'All Floors';
                                    flrSel.appendChild(allOpt);

                                    if (buildingId === 'all') {
                                        flrSel.disabled = true;
                                        return;
                                    }
                                    flrSel.disabled = false;
                                    (FLOORS_BY_BUILDING[buildingId] || []).forEach(function(f) {
                                        const opt = document.createElement('option');
                                        opt.value = String(f.num);
                                        opt.textContent = f.label;
                                        flrSel.appendChild(opt);
                                    });
                                }

                                function applyFilters() {
                                    const bId = bldSel.value;
                                    const fNum = flrSel.value;
                                    const status = stSel.value;
                                    const query = (search.value || '').trim().toLowerCase();
                                    const searching = query.length > 0;

                                    let anyVisible = false;
                                    let currentDivider = null;
                                    let dividerHasVisible = false;

                                    table.querySelectorAll('tbody tr').forEach(function(row) {
                                        if (row.classList.contains('pr-floor-divider')) {
                                            if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';
                                            const dB = row.dataset.buildingId;
                                            const dF = row.dataset.floorNum;
                                            const inScope = searching || ((bId === 'all' || dB === bId) && (fNum === 'all' || dF === fNum));
                                            currentDivider = inScope ? row : null;
                                            if (!inScope) row.style.display = 'none';
                                            dividerHasVisible = false;
                                            return;
                                        }
                                        if (row.classList.contains('pr-no-match-row')) return;
                                        if (!row.classList.contains('pr-room-row')) return;

                                        const rB = row.dataset.roomBuildingId;
                                        const rF = row.dataset.roomFloorNum;
                                        const inScope = searching || ((bId === 'all' || rB === bId) && (fNum === 'all' || rF === fNum));
                                        const matchesStatus = status === 'all' || row.dataset.roomStatus === status;
                                        const matchesQuery = !searching || (row.dataset.roomName || '').toLowerCase().indexOf(query) !== -1;
                                        const visible = inScope && matchesStatus && matchesQuery;

                                        row.style.display = visible ? '' : 'none';
                                        if (visible) {
                                            dividerHasVisible = true;
                                            anyVisible = true;
                                        }
                                    });
                                    if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';

                                    const noMatch = table.querySelector('.pr-no-match-row');
                                    if (noMatch) noMatch.style.display = anyVisible ? 'none' : '';
                                }

                                bldSel.addEventListener('change', function() {
                                    populateFloorSelect(this.value);
                                    applyFilters();
                                });
                                flrSel.addEventListener('change', applyFilters);
                                stSel.addEventListener('change', applyFilters);
                                search.addEventListener('input', applyFilters);

                                populateFloorSelect(DEFAULT_BUILDING);
                                applyFilters();

                                /* ── Exposed for the AJAX module below: after an add/
                                   update/archive succeeds, drop the fresh <tbody> HTML
                                   in, refresh the Floor dropdown's options for whichever
                                   Building is currently selected (a new floor number may
                                   now exist), and re-apply the current search/filter
                                   state so the admin's view doesn't reset. ──────────── */
                                window.psRefreshRoomsRegistry = function(html, freshFloorsByBuilding) {
                                    if (freshFloorsByBuilding) {
                                        FLOORS_BY_BUILDING = freshFloorsByBuilding;
                                    }
                                    if (typeof html === 'string') {
                                        const tbody = table.querySelector('tbody');
                                        if (tbody) tbody.innerHTML = html;
                                    }
                                    if (bldSel.value !== 'all') {
                                        const keepFloor = flrSel.value;
                                        populateFloorSelect(bldSel.value);
                                        if ([...flrSel.options].some(function(o) {
                                                return o.value === keepFloor;
                                            })) {
                                            flrSel.value = keepFloor;
                                        }
                                    }
                                    applyFilters();
                                };
                            })();
