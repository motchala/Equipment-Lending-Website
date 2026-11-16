<?php
/* ================================================================
   DECLINE REASON PICKER  (shared by the equipment and room decline forms)

   One tap on a reason chip picks the reason; an optional line of details can be added to it, and
   "Other" lets the admin write their own. The chosen text is what is saved and shown to the borrower.
   Behaviour: shared/assets/js/admin-decline-reasons.js (window.PSReasonPicker).

   Set before including this file:
     $picker_id       unique id of the picker (also used for its textarea: "<id>-detail")
     $picker_reasons  list of [short chip label, full sentence that is saved]
     $picker_label    optional heading (default "Reason for declining")
   ================================================================ */
$picker_label = $picker_label ?? 'Reason for declining';
?>
<div class="ps-reason-picker" id="<?php echo htmlspecialchars($picker_id, ENT_QUOTES); ?>" data-reason-picker>
    <span class="ps-form-label" id="<?php echo htmlspecialchars($picker_id, ENT_QUOTES); ?>-lbl"><?php echo htmlspecialchars($picker_label); ?></span>
    <div class="ps-reason-chips" role="radiogroup" aria-labelledby="<?php echo htmlspecialchars($picker_id, ENT_QUOTES); ?>-lbl">
        <?php foreach ($picker_reasons as $pr): ?>
            <button type="button" class="ps-reason-chip" role="radio" aria-checked="false" data-reason="<?php echo htmlspecialchars($pr[1], ENT_QUOTES); ?>"><?php echo htmlspecialchars($pr[0]); ?></button>
        <?php endforeach; ?>
        <button type="button" class="ps-reason-chip ps-reason-chip--other" role="radio" aria-checked="false" data-reason-other>Other</button>
    </div>
    <div class="ps-reason-detail" data-reason-detail hidden>
        <label class="ps-form-label" for="<?php echo htmlspecialchars($picker_id, ENT_QUOTES); ?>-detail" data-reason-detail-label>Add details <span class="ps-reason-opt">(optional)</span></label>
        <textarea class="ps-form-control" id="<?php echo htmlspecialchars($picker_id, ENT_QUOTES); ?>-detail" rows="2" maxlength="160"></textarea>
    </div>
</div>
