    <!-- MODAL: EXPORT REQUESTS -->
    <div class="ps-modal-backdrop" id="ps-export-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--info">
                    <span class="material-symbols-outlined">download</span>
                </div>
                <h3>Export Requests</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-export-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <div class="ps-form-group">
                    <label class="ps-form-label">Date Range</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <div style="font-size:11px;color:var(--text-light);margin-bottom:3px">From</div>
                            <input class="ps-form-control" type="date" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-light);margin-bottom:3px">To</div>
                            <input class="ps-form-control" type="date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Filter by Status</label>
                    <select class="ps-form-control">
                        <option value="">All Statuses</option>
                        <option value="Waiting">Waiting</option>
                        <option value="Approved">Active Borrowings</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Returned">Returned</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Export Format</label>
                    <div style="display:flex;gap:10px;margin-top:4px">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="export-fmt" value="csv" checked> CSV
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="export-fmt" value="xlsx"> Excel (.xlsx)
                        </label>
                    </div>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-export-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" data-action="ps-close-modal" data-modal="ps-export-modal">
                    <span class="material-symbols-outlined">download</span> Export
                </button>
            </div>
        </div>
    </div>




