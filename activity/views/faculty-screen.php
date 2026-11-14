            <!-- ============================================================
                 TAB: MY ACTIVITY
            ============================================================ -->
            <div class="tab-panel" id="panel-activity">

                <?php
                /* ── My Activity ─────────────────────────────────────────────
                   Three layers, top to bottom:

                     1. Next steps      what needs the faculty member right now, most urgent first: overdue
                                        returns, loans due today, approved items to collect, requests still
                                        waiting for a decision (the Home tab already shows the counts, so
                                        this screen does not repeat them). Built from the real requests by
                                        fact_eq_todo() / fact_room_todo(). Each entry points at the row
                                        below that has the button; when nothing needs action it says so
                                        and offers Borrow / Reserve a room.
                     2. Right now       Equipment card | Room reservations card
                                        each one: In progress / Upcoming (rooms also: Waiting for approval,
                                        Waitlist). Both cards are a fixed size and show 5 rows per page,
                                        with the page navigation in the card footer.
                     3. History         one table for both, filtered All / Equipment / Rooms
                                        (filters and search in the header, page navigation in the footer)

                   Where every button lives (so a button is only ever in one place):
                     page header                   title only (no buttons)
                     row action bar (Equipment     only the buttons the row really has, packed left to right:
                       and Room rows)                Claim QR · Return QR · Extend, then the way out
                                                     (Cancel / Withdraw / Leave waitlist) at the far right
                     history table, last column    Action: Join waitlist
                     card / table footer           page navigation

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
                $act_eq    = fact_eq_load($conn, (string) $_SESSION['faculty_id']);
                $act_rooms = fact_load_rooms($conn, (string) $_SESSION['faculty_id']);
                $act_todo  = array_merge(fact_eq_todo($act_eq), fact_room_todo($act_rooms));
                ?>

                <!-- ── Header ───────────────────────────────────────────── -->
                <header class="fact-head">
                    <div>
                        <h2 class="myact-title">My Activity</h2>
                        <p class="fact-sub">Your equipment loans and room reservations, all in one place.</p>
                    </div>
                </header>

                <!-- ── Next steps: what needs you right now (replaces the summary cards) ── -->
                <section class="fact-card fact-todo-card" id="factTodo" aria-labelledby="factTodoTitle">
                    <header class="fact-card-head">
                        <div class="fact-card-heading">
                            <h3 class="fact-card-title" id="factTodoTitle">Next steps</h3>
                            <p class="fact-card-sub">What needs you right now, most urgent first.</p>
                        </div>
                    </header>
                    <ul class="fact-todo-list" id="factTodoList" data-max="<?php echo (int) FACT_TODO_MAX; ?>"<?php echo $act_todo ? '' : ' hidden'; ?>><?php echo fact_todo_render($act_todo); ?></ul>
                    <!-- More entries than fit: the list shows the most urgent few, this reveals the rest -->
                    <footer class="fact-card-foot fact-todo-foot" id="factTodoMore"<?php echo count($act_todo) > FACT_TODO_MAX ? '' : ' hidden'; ?>>
                        <button type="button" class="fact-linkbtn" id="factTodoMoreBtn" aria-expanded="false">Show <?php echo max(0, count($act_todo) - FACT_TODO_MAX); ?> more</button>
                    </footer>
                    <!-- Shown by the page script whenever the list above is empty (also after a live refresh) -->
                    <div class="fact-todo-clear" id="factTodoClear"<?php echo $act_todo ? ' hidden' : ''; ?>>
                        <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                        <div class="fact-todo-clear-text">
                            <p class="fact-empty-title">You're all caught up</p>
                            <p class="fact-empty-sub">Nothing needs your action right now.</p>
                        </div>
                        <div class="fact-todo-clear-actions">
                            <button type="button" class="fact-btn fact-btn-ghost" data-icon="add_shopping_cart" data-action="go-tab" data-tab="lending" data-lending="browse">Borrow equipment</button>
                            <button type="button" class="fact-btn fact-btn-ghost" data-icon="meeting_room" data-action="go-tab" data-tab="rooms">Reserve a room</button>
                        </div>
                    </div>
                </section>

                <!-- ── Right now: Equipment | Room reservations ─────────── -->
                <div class="fact-grid">

                    <section class="fact-card fact-live" id="factEquipment" aria-labelledby="factEquipTitle">
                        <header class="fact-card-head">
                            <div class="fact-card-heading">
                                <h3 class="fact-card-title" id="factEquipTitle">Equipment</h3>
                                <p class="fact-card-sub" id="factEquipStatus"><?php echo fact_eq_render_status($act_eq); ?></p>
                            </div>
                        </header>
                        <!-- Filled here on first paint; swapped live by the My Activity block at the end of faculty-dashboard.js -->
                        <div class="fact-card-body" id="factEquipBody"><?php echo fact_eq_render_body($act_eq, $root_url); ?></div>
                        <footer class="fact-card-foot">
                            <nav class="fact-pager" id="factEquipPager" data-fact-card="equipment" data-noun="request" aria-label="Equipment pages"></nav>
                        </footer>
                    </section>

                    <section class="fact-card fact-live" id="factRooms" aria-labelledby="factRoomsTitle">
                        <header class="fact-card-head">
                            <div class="fact-card-heading">
                                <h3 class="fact-card-title" id="factRoomsTitle">Room reservations</h3>
                                <p class="fact-card-sub" id="factRoomsStatus"><?php echo fact_render_rooms_status($act_rooms); ?></p>
                            </div>
                        </header>
                        <div class="fact-card-body" id="factRoomsBody"><?php echo fact_render_rooms_body($act_rooms); ?></div>
                        <footer class="fact-card-foot">
                            <nav class="fact-pager" id="factRoomsPager" data-fact-card="rooms" data-noun="reservation" aria-label="Room reservation pages"></nav>
                        </footer>
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
                        </div>
                    </header>

                    <div class="fact-table-wrap">
                        <table class="fact-table">
                            <thead>
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">When</th>
                                    <th scope="col" class="fact-col-status">Status</th>
                                    <th scope="col" class="fact-col-action">Action</th>
                                </tr>
                            </thead>
                            <tbody id="factHistBody"><?php
                                                        echo fact_eq_render_history_rows($act_eq, $root_url);
                                                        echo fact_render_room_history_rows($act_rooms);
                                                        ?></tbody>
                        </table>
                    </div>

                    <p class="fact-noresults" id="factNoResults" hidden>No records match your search.</p>
                    <footer class="fact-card-foot" id="factHistFoot">
                        <nav class="fact-pager" id="factPager" aria-label="History pages" hidden>
                            <span class="fact-pager-info" id="factPagerInfo"></span>
                            <div class="fact-pager-ctl">
                                <button type="button" class="fact-pager-btn" id="factPrev" aria-label="Previous page"><span class="material-symbols-outlined">chevron_left</span></button>
                                <div class="fact-pager-nums" id="factNums"></div>
                                <button type="button" class="fact-pager-btn" id="factNext" aria-label="Next page"><span class="material-symbols-outlined">chevron_right</span></button>
                            </div>
                        </nav>
                    </footer>

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
                        <button type="button" class="act-ai-chat-head-btn" data-action="ai-chat-minimize"
                            title="Minimise" aria-label="Minimise chat">
                            <span class="material-symbols-outlined">remove</span>
                        </button>
                        <button type="button" class="act-ai-chat-head-btn" data-action="ai-chat-close"
                            title="Close" aria-label="Close chat">
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
                        <input type="text" id="actAiInput" placeholder="Ask about rooms or reservations..."
                            autocomplete="off" maxlength="500" aria-label="Message the room assistant">
                        <button type="button" class="act-ai-chat-send" id="actAiSendBtn"
                            data-action="ai-chat-send" title="Send" aria-label="Send message">
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
                data-action="ai-fab-toggle" aria-label="Ask about rooms">
                <span class="material-symbols-outlined">smart_toy</span>
                <span class="act-ai-fab-tooltip">Ask about rooms</span>
            </button>

            <!-- AI popover is a SIBLING of the FAB (a <button> may not contain another <button>) -->
            <div class="act-ai-fab-popover" id="actAiFabPopover">
                <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0;">smart_toy</span>
                <span>Need help finding or booking a room? Ask the AI assistant!</span>
                <button type="button" class="act-ai-fab-popover-close" id="actAiFabPopoverClose"
                    title="Dismiss" aria-label="Dismiss assistant hint">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>