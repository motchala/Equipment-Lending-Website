    <!-- MODAL: MANUAL RETURN (does not require the borrower's QR code) -->
    <div class="ps-modal-backdrop" id="ps-return-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment_return</span>
                </div>
                <h3>Manual Return</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-return-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form id="manual-return-form" method="post" action="equipment-booking/api/manual-return.php">
                    <?= csrf_field() ?>
                    <input type="hidden" id="manual-return-request-id" name="request_id">
                    <div class="ps-req-summary">
                        <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" style="font-weight:600">—</span></div>
                        <div class="ps-req-sum-row"><span class="ps-rsl">Student ID</span><span class="ps-rsv">—</span></div>
                        <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv">—</span></div>
                        <div class="ps-req-sum-row"><span class="ps-rsl">Due Date</span><span class="ps-rsv" style="color:var(--danger);font-weight:600">—</span></div>
                    </div>
                    <div class="ps-form-group">
                        <label class="ps-form-label">Return Condition</label>
                        <input type="hidden" id="manual-return-condition-value" name="return_condition" value="Good">
                        <select class="ps-form-control" id="manual-return-condition">
                            <option>Good — No visible damage</option>
                            <option>Fair — Minor wear</option>
                            <option>Damaged — Needs repair</option>
                            <option>Missing — Item not returned</option>
                        </select>
                    </div>
                    <div class="ps-form-group">
                        <label class="ps-form-label">Admin Notes <span style="font-size:11px;color:var(--text-light)">(optional)</span></label>
                        <textarea class="ps-form-control" id="manual-return-notes" name="admin_notes" rows="2" maxlength="2000" placeholder="Any notes about the return..."></textarea>
                    </div>
                    <div class="ps-alert ps-alert--info" style="margin:0">
                        <span class="material-symbols-outlined">info</span>
                        Recording this manual return updates the stock count and closes this request. No faculty QR code is required.
                    </div>
                </form>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-return-modal">Cancel</button>
                <button type="submit" form="manual-return-form" class="ps-btn ps-btn--primary" id="manual-return-confirm-btn">
                    <span class="material-symbols-outlined">check</span> Record Manual Return
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: SEND OVERDUE NOTICE -->
    <div class="ps-modal-backdrop" id="ps-notice-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--warning">
                    <span class="material-symbols-outlined">mail</span>
                </div>
                <h3>Send Overdue Notice</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-notice-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form id="overdue-notice-form" method="post" action="equipment-booking/api/send-overdue-notice.php">
                    <?= csrf_field() ?>
                    <input type="hidden" id="overdue-notice-request-id" name="request_id">
                </form>
                <div class="ps-req-summary" style="margin-bottom:1rem">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" style="font-weight:600">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Student ID</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Days Overdue</span><span class="ps-rsv" style="color:var(--danger);font-weight:600">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Message Preview</label>
                    <div class="ps-notice-preview" id="overdue-notice-preview">Dear [Borrower Name],

                        This is a reminder that the following item borrowed from the PUPSync Equipment Lending System is now overdue:

                        • Item: [Equipment Name]
                        • Due Date: [Due Date]
                        • Days Overdue: [N] days

                        Please return the item as soon as possible to avoid further penalties.

                        Thank you,
                        PUPSync Admin — Biñan Campus</div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Delivery</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin-top:6px">
                        <input type="checkbox" checked disabled> Send to the borrower’s PUPSync notification inbox
                    </label>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-notice-modal">Cancel</button>
                <button type="submit" form="overdue-notice-form" class="ps-btn ps-btn--primary" id="overdue-notice-send-btn">
                    <span class="material-symbols-outlined">send</span> Send Notice
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: REQUEST DETAIL -->
    <div class="ps-modal-backdrop" id="ps-req-detail-modal">
        <div class="ps-modal ps-modal--lg">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment</span>
                </div>
                <h3>Request Details</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-req-detail-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="detail-request-id" value="">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.25rem">
                    <span class="ps-badge ps-badge--waiting ps-badge--dot" id="detail-status-badge" style="font-size:12px;padding:4px 12px">Waiting for Approval</span>
                    <span style="font-size:11.5px;color:var(--text-light)">Submitted: <span id="detail-submitted">—</span></span>
                </div>
                <div class="ps-detail-grid" style="margin-bottom:1.25rem">
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Requester</div>
                        <div class="ps-detail-value" id="detail-requester" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Student / Faculty ID</div>
                        <div class="ps-detail-value" id="detail-id-number">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Requester Type</div>
                        <div class="ps-detail-value" id="detail-req-type">Faculty</div>
                    </div>
                    <div class="ps-detail-item" id="detail-authorizer-item" hidden>
                        <div class="ps-detail-label">Authorized by faculty</div>
                        <div class="ps-detail-value" id="detail-authorizer">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Facility</div>
                        <div class="ps-detail-value" id="detail-room" style="font-size:12px">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Equipment</div>
                        <div class="ps-detail-value" id="detail-equipment" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Quantity</div>
                        <div class="ps-detail-value">1</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Date Needed</div>
                        <div class="ps-detail-value" id="detail-date-needed" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Return By</div>
                        <div class="ps-detail-value" id="detail-return-by">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Condition</div>
                        <div class="ps-detail-value"><span class="ps-badge ps-badge--dot ps-badge--active" id="detail-condition-badge">Good</span></div>
                    </div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Instructor / Assigned To</label>
                    <div id="detail-instructor" style="background:var(--secondary-cream);border-radius:8px;padding:0.75rem;font-size:12.5px;color:var(--text-dark);border:1px solid var(--khaki-border)">—</div>
                </div>
                <div style="background:var(--secondary-cream);border-radius:10px;padding:0.85rem;font-size:12px;color:var(--text-light);border:1px solid var(--khaki-border)">
                    <strong style="color:var(--text-dark)">Arbitration Status:</strong> <span id="detail-arb-status">Pending review.</span>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-req-detail-modal">Close</button>
                <button class="ps-btn ps-btn--danger" id="ps-detail-decline-btn" data-action="ps-switch-modal" data-close="ps-req-detail-modal" data-open="ps-decline-modal">
                    <span class="material-symbols-outlined">close</span> Decline
                </button>
                <button class="ps-btn ps-btn--success" id="ps-detail-approve-btn" data-action="ps-switch-modal" data-close="ps-req-detail-modal" data-open="ps-approve-modal">
                    <span class="material-symbols-outlined">check</span> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- DRAWER: REQUEST REVIEW (Equipment > On Loan). Reuses the ps-modal-backdrop open/close
         mechanism, so psOpenModal/psCloseModal, backdrop click and Esc all work unchanged.
         Approve / Decline here hand off to the existing approve/decline modals. -->
    <div class="ps-modal-backdrop ps-drawer-backdrop" id="ps-appr-drawer">
        <aside class="ps-drawer" role="dialog" aria-modal="true" aria-labelledby="ps-appr-title">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">fact_check</span>
                </div>
                <h3 id="ps-appr-title">Review <span id="ps-appr-reqno">#0000</span></h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-appr-drawer" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-drawer-body">
                <div class="ps-drawer-status">
                    <span class="ps-badge ps-badge--dot ps-badge--waiting">Pending Review</span>
                    <span class="ps-drawer-muted">Submitted <span id="ps-appr-submitted">—</span></span>
                </div>

                <section class="ps-drawer-sec">
                    <h4>Supporting document</h4>
                    <div class="ps-doc-card" id="ps-appr-doc">
                        <div class="ps-doc-card__icon"><span class="material-symbols-outlined" id="ps-appr-doc-icon">draft</span></div>
                        <div class="ps-doc-card__meta">
                            <div class="ps-doc-card__name" id="ps-appr-doc-name">—</div>
                            <div class="ps-drawer-muted" id="ps-appr-doc-kind">—</div>
                        </div>
                        <div class="ps-doc-card__btns">
                            <a class="ps-btn ps-btn--primary ps-btn--sm" id="ps-appr-doc-open" href="#" target="_blank" rel="noopener noreferrer">
                                <span class="material-symbols-outlined">open_in_new</span> Open
                            </a>
                            <a class="ps-btn ps-btn--outline ps-btn--sm" id="ps-appr-doc-dl" href="#" download>
                                <span class="material-symbols-outlined">download</span>
                            </a>
                        </div>
                    </div>
                    <div class="ps-doc-preview" id="ps-appr-doc-preview" hidden>
                        <img id="ps-appr-doc-img" alt="Attached document preview">
                    </div>
                    <div class="ps-doc-none" id="ps-appr-doc-none" hidden>
                        <span class="material-symbols-outlined">warning</span>
                        <span>No supporting document is attached. Standard requests are handled automatically, so check why this one needs a manual decision.</span>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Requester</h4>
                    <div class="ps-kv">
                        <div><span>Name</span><strong id="ps-appr-who">—</strong></div>
                        <div><span>Type</span><strong id="ps-appr-type">—</strong></div>
                        <div><span>ID</span><strong id="ps-appr-idno">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Request</h4>
                    <div class="ps-kv">
                        <div><span>Item</span><strong id="ps-appr-item">—</strong></div>
                        <div><span>Quantity</span><strong id="ps-appr-qty">—</strong></div>
                        <div><span>Date needed</span><strong id="ps-appr-needed">—</strong></div>
                        <div><span>Return by</span><strong id="ps-appr-return">—</strong></div>
                        <div><span>Facility</span><strong id="ps-appr-room">—</strong></div>
                        <div><span>Instructor</span><strong id="ps-appr-instr">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec" id="ps-appr-note-sec">
                    <h4>Notes</h4>
                    <div class="ps-drawer-note" id="ps-appr-note">—</div>
                </section>
            </div>
            <div class="ps-drawer-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-appr-drawer">Close</button>
                <button class="ps-btn ps-btn--danger" type="button" data-appr-decide="decline">
                    <span class="material-symbols-outlined">close</span> Decline
                </button>
                <button class="ps-btn ps-btn--success" type="button" data-appr-decide="approve">
                    <span class="material-symbols-outlined">check</span> Approve
                </button>
            </div>
        </aside>
    </div>

    <!-- MODAL: APPROVE ALL PENDING ITEMS IN A BORROW BUNDLE -->
    <div class="ps-modal-backdrop" id="eq-bundle-approve-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--success">
                    <span class="material-symbols-outlined">done_all</span>
                </div>
                <h3>Approve Bundle</h3>
                <button class="ps-modal-close" type="button" data-action="ps-close-modal" data-modal="eq-bundle-approve-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="eq-bundle-approve-id" value="">
                <div id="eq-bundle-approve-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <p class="ps-form-hint" style="margin-top:0">This approves every request still pending in this bundle. Individual declines remain available from each row. The faculty member will receive one Claim Bundle QR.</p>
                <div class="ps-req-summary" id="eq-bundle-approve-items"></div>
                <div class="ps-form-group" style="margin-top:1rem">
                    <label class="ps-form-label" for="eq-bundle-approve-reason">Reason / Note <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters)</span></label>
                    <textarea class="ps-form-control" id="eq-bundle-approve-reason" rows="2" placeholder="e.g. Items available, request meets policy..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" type="button" data-action="ps-close-modal" data-modal="eq-bundle-approve-modal">Cancel</button>
                <button class="ps-btn ps-btn--success" type="button" id="eq-bundle-approve-confirm" disabled>
                    <span class="material-symbols-outlined">done_all</span> Approve Bundle
                </button>
            </div>
        </div>
    </div>
