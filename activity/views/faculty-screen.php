            <!-- ============================================================
                 TAB: MY ACTIVITY
            ============================================================ -->
            <div class="tab-panel" id="panel-activity">

                <?php
                /* ── My Activity ─────────────────────────────────────────────
                   Three layers, top to bottom:

                     1. Summary         four cards: Active loans · Overdue · Pending · Room reservations
                                        (same words and numbers as the Home tab's key figures)
                     2. Right now       Equipment card | Room reservations card
                                        each one: In progress / Upcoming (rooms also: Waitlist)
                     3. History         one table for both, filtered All / Equipment / Rooms,
                                        with its page navigation in the header next to the filters

                   Both halves are drawn by shared renderers, so the first paint and the live
                   refresh are always identical:
                     equipment  activity/core/faculty-data.php   fact_eq_*
                                refreshed by api/poll-requests.php?view=activity
                     rooms      room-reservation/core/room-quota.php  fact_*
                                refreshed by api/poll-reservations.php?view=activity

                   Status words and colours come from one place, fact_status_chip():
                     Pending = amber · Approved = green · Overdue / Declined = red
                     Returned / Completed / Cancelled = grey
                ─────────────────────────────────────────────────────────────── */
                $act_eq         = fact_eq_load($conn, (string) $_SESSION['faculty_id']);
                $act_eq_stats   = fact_eq_stats($act_eq);
                $act_rooms      = fact_load_rooms($conn, (string) $_SESSION['faculty_id']);
                $act_room_stats = fact_room_stats($act_rooms);
                ?>

                <!-- ── Header ───────────────────────────────────────────── -->
                <header class="fact-head">
                    <div>
                        <h2 class="myact-title">My Activity</h2>
                        <p class="fact-sub">Your equipment loans and room reservations, all in one place.</p>
                    </div>
                    <button type="button" class="fact-btn fact-btn-ghost" data-fact-print>
                        <span class="material-symbols-outlined" aria-hidden="true">download</span>Download Report
                    </button>
                </header>

                <!-- ── Summary: four cards ──────────────────────────────── -->
                <div class="fact-stats" role="group" aria-label="Activity summary">
                    <div class="fact-stat is-ok">
                        <span class="fact-stat-label">Active loans</span>
                        <span class="fact-stat-value" data-fact-stat="loans"><?php echo (int) $act_eq_stats['loans']; ?></span>
                        <span class="fact-stat-note">Approved equipment</span>
                    </div>
                    <div class="fact-stat is-bad<?php echo $act_eq_stats['overdue'] > 0 ? ' is-alert' : ''; ?>">
                        <span class="fact-stat-label">Overdue</span>
                        <span class="fact-stat-value" data-fact-stat="overdue"><?php echo (int) $act_eq_stats['overdue']; ?></span>
                        <span class="fact-stat-note">Past the return time</span>
                    </div>
                    <div class="fact-stat is-wait">
                        <span class="fact-stat-label">Pending</span>
                        <span class="fact-stat-value" data-fact-stat="pending"><?php echo (int) $act_eq_stats['pending']; ?></span>
                        <span class="fact-stat-note">Awaiting approval</span>
                    </div>
                    <div class="fact-stat">
                        <span class="fact-stat-label">Room reservations</span>
                        <span class="fact-stat-value" data-fact-stat="reserved"><?php echo fact_h($act_room_stats['reserved']); ?></span>
                        <span class="fact-stat-note">Active, out of the limit</span>
                    </div>
                </div>

                <!-- ── Right now: Equipment | Room reservations ─────────── -->
                <div class="fact-grid">

                    <section class="fact-card" id="factEquipment" aria-labelledby="factEquipTitle">
                        <header class="fact-card-head">
                            <h3 class="fact-card-title" id="factEquipTitle">Equipment</h3>
                        </header>
                        <!-- Filled here on first paint; swapped live by the My Activity block at the end of faculty-dashboard.js -->
                        <div class="fact-card-body" id="factEquipBody"><?php echo fact_eq_render_body($act_eq, $root_url); ?></div>
                    </section>

                    <section class="fact-card" id="factRooms" aria-labelledby="factRoomsTitle">
                        <header class="fact-card-head">
                            <h3 class="fact-card-title" id="factRoomsTitle">Room reservations</h3>
                        </header>
                        <div class="fact-card-body" id="factRoomsBody"><?php echo fact_render_rooms_body($act_rooms); ?></div>
                    </section>

                </div><!-- /fact-grid -->

                <!-- ── History (equipment + rooms) ──────────────────────── -->
                <section class="fact-card fact-history" id="factHistory" aria-labelledby="factHistTitle">
                    <header class="fact-card-head">
                        <h3 class="fact-card-title" id="factHistTitle">History</h3>
                        <div class="fact-tools">
                            <div class="fact-seg" id="factKindTabs" role="group" aria-label="Show">
                                <button type="button" class="fact-seg-btn is-active" data-fact-kind="all" aria-pressed="true">All</button>
                                <button type="button" class="fact-seg-btn" data-fact-kind="equipment" aria-pressed="false">Equipment</button>
                                <button type="button" class="fact-seg-btn" data-fact-kind="room" aria-pressed="false">Rooms</button>
                            </div>
                            <label class="fact-search">
                                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                                <input type="text" id="factSearch" placeholder="Search history…" autocomplete="off" aria-label="Search history">
                            </label>
                            <nav class="fact-pager" id="factPager" aria-label="History pages" hidden>
                                <span class="fact-pager-info" id="factPagerInfo"></span>
                                <div class="fact-pager-ctl">
                                    <button type="button" class="fact-pager-btn" id="factPrev" aria-label="Previous page"><span class="material-symbols-outlined">chevron_left</span></button>
                                    <div class="fact-pager-nums" id="factNums"></div>
                                    <button type="button" class="fact-pager-btn" id="factNext" aria-label="Next page"><span class="material-symbols-outlined">chevron_right</span></button>
                                </div>
                            </nav>
                        </div>
                    </header>

                    <div class="fact-table-wrap">
                        <table class="fact-table">
                            <thead>
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">When</th>
                                    <th scope="col" class="fact-col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody id="factHistBody"><?php
                                                        echo fact_eq_render_history_rows($act_eq, $root_url);
                                                        echo fact_render_room_history_rows($act_rooms);
                                                        ?></tbody>
                        </table>
                    </div>

                    <p class="fact-noresults" id="factNoResults" hidden>No records match your search.</p>
                    <div class="fact-empty" id="factHistEmpty" hidden>
                        <span class="material-symbols-outlined" aria-hidden="true">history</span>
                        <p class="fact-empty-title">No history yet</p>
                        <p class="fact-empty-sub">Returned equipment and past room reservations will show up here.</p>
                    </div>

                </section><!-- /factHistory -->


                <!-- ── Extend dialog (opened by the Extend button on a due loan; moved to <body> by the script) ── -->
                <div class="fact-modal" id="factExtendModal" hidden>
                    <div class="fact-modal-card" role="dialog" aria-modal="true" aria-labelledby="factExtendTitle">
                        <header class="fact-modal-head">
                            <h3 class="fact-modal-title" id="factExtendTitle">Extend borrowing</h3>
                            <button type="button" class="fact-modal-close" data-fact-extend-close aria-label="Close">
                                <span class="material-symbols-outlined" aria-hidden="true">close</span>
                            </button>
                        </header>
                        <div class="fact-modal-body" id="factExtendBody"></div>
                        <footer class="fact-modal-foot">
                            <button type="button" class="fact-modal-btn" data-fact-extend-close>Cancel</button>
                            <button type="button" class="fact-modal-btn is-primary" id="factExtendSubmit" disabled>Extend</button>
                        </footer>
                    </div>
                </div>

            </div><!-- /panel-activity -->

            <!-- ── AI Support Hub ──────────────────────────────────────────── -->
            <div class="act-ai-chat" id="actAiChat">

                <!-- Header -->
                <div class="act-ai-chat-head">
                    <div class="act-ai-chat-head-info">
                        <div class="act-ai-chat-avatar-wrap">
                            <div class="act-ai-chat-avatar">
                                <span class="material-symbols-outlined">smart_toy</span>
                            </div>
                            <span class="act-ai-online-dot"></span>
                        </div>
                        <div>
                            <div class="act-ai-chat-name">PUPSync Room Assistant</div>
                            <div class="act-ai-chat-status">Active now</div>
                        </div>
                    </div>
                    <div class="act-ai-chat-head-btns">
                        <button class="act-ai-chat-head-btn" data-action="ai-chat-minimize" title="Minimise">
                            <span class="material-symbols-outlined">remove</span>
                        </button>
                        <button class="act-ai-chat-head-btn" data-action="ai-chat-close" title="Close">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>

                <!-- Message body -->
                <div class="act-ai-chat-body" id="actAiChatBody">
                    <div class="act-chat-row act-chat-row-ai">
                        <div class="act-chat-ai-avatar">
                            <span class="material-symbols-outlined">smart_toy</span>
                        </div>
                        <div class="act-chat-col">
                            <div class="act-chat-bubble act-chat-bubble-ai">
                                Hi <?php echo htmlspecialchars($fullname); ?>! 👋 I'm your PUPSync Room Assistant. Ask me about room availability, how to reserve, or anything about the facilities.
                            </div>
                            <div class="act-chat-chips" id="actAiChips">
                                <button class="act-chat-chip" data-chip="How do I reserve a room?">How do I reserve a room?</button>
                                <button class="act-chat-chip" data-chip="What rooms are free today?">What rooms are free today?</button>
                                <button class="act-chat-chip" data-chip="How far in advance can I book?">How far in advance can I book?</button>
                                <button class="act-chat-chip" data-chip="Cancel my reservation">Cancel my reservation</button>
                                <button class="act-chat-chip" data-chip="Reservation rules">Reservation rules</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input bar -->
                <div class="act-ai-chat-input-wrap">
                    <div class="act-ai-chat-counter" id="actAiCounterBar">
                        <span id="actAiCounter">—</span> messages remaining this hour
                    </div>
                    <div class="act-ai-chat-input">
                        <input type="text" id="actAiInput" placeholder="Aa" autocomplete="off" maxlength="500">
                        <button class="act-ai-chat-send" id="actAiSendBtn" data-action="ai-chat-send" title="Send">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="act-ai-chat-footer">
                    <a href="#" data-action="ai-chat-report-room-issue">
                        <span class="material-symbols-outlined">flag</span>
                        Report a Room Issue
                    </a>
                </div>

            </div>

            <!-- FAB — hidden by default; JS shows it only on the Facilities tab -->
            <button class="act-ai-fab" id="actAiFab" style="display:none;"
                data-action="ai-fab-toggle" title="Ask about rooms">
                <span class="material-symbols-outlined">smart_toy</span>
                <span class="act-ai-fab-tooltip">Ask about rooms</span>
            </button>

            <!-- AI popover is a SIBLING of the FAB (a <button> may not contain another <button>) -->
            <div class="act-ai-fab-popover" id="actAiFabPopover">
                <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0;">smart_toy</span>
                <span>Need help finding or booking a room? Ask the AI assistant!</span>
                <button class="act-ai-fab-popover-close" id="actAiFabPopoverClose" title="Dismiss">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

