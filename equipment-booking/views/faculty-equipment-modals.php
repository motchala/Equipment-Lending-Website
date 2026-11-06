    <!-- ================================================================
     MODALS
================================================================ -->

    <!-- ── Borrow Request Modal ───────────────────────────────────────── -->
    <div class="modal-backdrop" id="borrowModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
        <div class="modal-box borrow-modal-box">
            <div class="modal-header bm-head">
                <span class="bm-tile" aria-hidden="true"><span class="material-symbols-outlined">inventory_2</span></span>
                <div class="bm-heading">
                    <h3 id="borrowModalTitle">Borrow Request</h3>
                    <p class="bm-sub">Tell us where and when you need the equipment.</p>
                </div>
                <button class="modal-close-btn" data-action="close-borrow-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="bm-item" id="selectedItemBanner">
                    <span class="bm-item-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                    <div class="bm-item-text">
                        <span class="bm-item-cap">Selected equipment</span>
                        <span class="bm-item-name" id="selectedItemLabel">No item selected</span>
                    </div>
                </div>
                <form id="borrowForm" class="bm-form" method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="equipment_name" id="selectedItem">
                    <input type="hidden" name="instructor" value="<?php echo htmlspecialchars($fullname); ?>">
                    <?= BookingSchedule::renderSchedule('bm1') ?>
                    <?= BookingSchedule::renderRoomPicker('borrow_room') ?>
                    <div class="form-group">
                        <span class="form-label">Who will collect the equipment?</span>
                        <div class="bm-collector-toggle" role="radiogroup" aria-label="Collector">
                            <label class="bm-collector-opt">
                                <input type="radio" name="collector_type" value="faculty" checked> Faculty (me)
                            </label>
                            <label class="bm-collector-opt">
                                <input type="radio" name="collector_type" value="student"> Student
                            </label>
                        </div>
                        <div class="bm-collector-student" id="collectorStudentFields" style="display:none;margin-top:10px;">
                            <input type="hidden" name="submitted_by_name" id="bm_collector_name" value="">
                            <input type="text" class="form-control-custom" id="bm_collector_name_input"
                                placeholder="Student full name" autocomplete="off"
                                style="width:100%;">
                            <p class="bm-hint">The student will show the Claim QR to collect.</p>
                        </div>
                    </div>
                    <div class="bm-actions">
                        <button type="button" class="btn-cancel-acc" data-action="close-borrow-modal">Cancel</button>
                        <button type="submit" class="btn-save-acc">
                            <span class="material-symbols-outlined">send</span> Submit Borrow Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- /borrowModal -->

    <!-- ── Cart Checkout Modal ───────────────────────────────────────── -->
    <div class="modal-backdrop" id="cartCheckoutModal" style="display:none;" role="dialog"
         aria-modal="true" aria-labelledby="cartCheckoutTitle">
        <div class="modal-box borrow-modal-box">
            <div class="modal-header bm-head">
                <span class="bm-tile" aria-hidden="true">
                    <span class="material-symbols-outlined">shopping_cart_checkout</span>
                </span>
                <div class="bm-heading">
                    <h3 id="cartCheckoutTitle">Borrow Multiple Items</h3>
                    <p class="bm-sub">All selected items will be borrowed today.</p>
                </div>
                <button class="modal-close-btn" data-action="cart-checkout-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Items with quantity per row -->
                <div class="form-group">
                    <span class="form-label">Items to borrow</span>
                    <div id="cartCheckoutList" class="eq-cart-checkout-list"></div>
                </div>
                <!-- Shared room field -->
                <input type="hidden" id="cartRoomId" value="">
                <div class="form-group">
                    <label class="form-label" for="cartRoom">
                        Room / Location <span style="color:var(--color-error)">*</span>
                    </label>
                    <input type="text" id="cartRoom" class="form-control-custom"
                        placeholder="e.g. B-205"
                        autocomplete="off">
                </div>
                <!-- Who will collect -->
                <div class="form-group">
                    <span class="form-label">Who will collect the equipment?</span>
                    <div class="bm-collector-toggle" role="radiogroup" aria-label="Collector">
                        <label class="bm-collector-opt">
                            <input type="radio" name="cart_collector_type" id="cartCollectorFaculty" value="faculty" checked> Faculty (me)
                        </label>
                        <label class="bm-collector-opt">
                            <input type="radio" name="cart_collector_type" id="cartCollectorStudent" value="student"> Student
                        </label>
                    </div>
                    <div id="cartCollectorStudentFields" style="display:none;margin-top:10px;">
                        <input type="text" class="form-control-custom" id="cartCollectorName"
                            placeholder="Student full name" autocomplete="off"
                            style="width:100%;">
                        <p class="bm-hint">The student will show the Claim QR to collect.</p>
                    </div>
                </div>
                <!-- Error message area -->
                <p id="cartCheckoutError" class="bm-error" style="display:none"></p>
                <div class="bm-actions">
                    <button type="button" class="btn-cancel-acc" data-action="cart-checkout-close">Cancel</button>
                    <button type="button" class="btn-save-acc" id="cartCheckoutSubmit">
                        <span class="material-symbols-outlined">send</span> Submit Borrow Requests
                    </button>
                </div>
            </div>
        </div>
    </div><!-- /cartCheckoutModal -->

