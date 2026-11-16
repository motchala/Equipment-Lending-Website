/* ================================================================
   DECLINE REASON PICKER  --  behaviour for every [data-reason-picker]
   (markup: shared/views/decline-reason-picker.php; used by the equipment and the room decline forms)

   Tap a chip to pick the reason. A small box then opens for optional details; "Other" asks for the
   reason in the admin's own words (at least 5 characters). Nothing is sent from here: the form that
   owns the picker reads the result and sends it.

     PSReasonPicker.read(root)   -> { ok, reason, error }   reason = chip sentence (+ the details)
     PSReasonPicker.reset(root)  -> clears the pick and the details
   Every change fires "psreason:change" on the picker (detail = what read() returns), so the form can
   enable / disable its confirm button.
   ================================================================ */
(function () {
    'use strict';

    // The reason is stored in a VARCHAR(255) column (requests and room reservations), so never go past that
    var MAX = 255;
    var DETAIL_MAX = 160;   // details added to a picked reason; "Other" may use the whole length

    function chipsOf(root) { return root.querySelectorAll('.ps-reason-chip'); }
    function selectedChip(root) { return root.querySelector('.ps-reason-chip.is-selected'); }
    function detailWrap(root) { return root.querySelector('[data-reason-detail]'); }
    function detailBox(root) { return root.querySelector('textarea'); }

    function read(root) {
        var chip = selectedChip(root);
        var box = detailBox(root);
        var extra = box ? box.value.trim() : '';
        if (!chip) return { ok: false, reason: '', error: 'Please pick a reason.' };

        if (chip.hasAttribute('data-reason-other')) {
            if (extra.length < 5) return { ok: false, reason: extra, error: 'Please write the reason (at least 5 characters).' };
            return { ok: true, reason: extra.slice(0, MAX), error: '' };
        }
        var base = chip.getAttribute('data-reason') || chip.textContent.trim();
        var full = extra ? base + ' ' + extra + (/[.!?]$/.test(extra) ? '' : '.') : base;
        return { ok: full.length >= 5, reason: full.slice(0, MAX), error: '' };
    }

    function notify(root) {
        root.dispatchEvent(new CustomEvent('psreason:change', { bubbles: true, detail: read(root) }));
    }

    function reset(root) {
        if (!root) return;
        Array.prototype.forEach.call(chipsOf(root), function (c) {
            c.classList.remove('is-selected');
            c.setAttribute('aria-checked', 'false');
        });
        var wrap = detailWrap(root);
        if (wrap) wrap.hidden = true;
        var box = detailBox(root);
        if (box) box.value = '';
        notify(root);
    }

    function choose(root, chip) {
        Array.prototype.forEach.call(chipsOf(root), function (c) {
            var on = c === chip;
            c.classList.toggle('is-selected', on);
            c.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        var other = chip.hasAttribute('data-reason-other');
        var wrap = detailWrap(root);
        var box = detailBox(root);
        if (wrap) {
            wrap.hidden = false;
            var label = wrap.querySelector('[data-reason-detail-label]');
            if (label) {
                label.innerHTML = other
                    ? 'Write the reason <span class="ps-reason-opt">(required, at least 5 characters)</span>'
                    : 'Add details <span class="ps-reason-opt">(optional)</span>';
            }
        }
        if (box) {
            box.maxLength = other ? MAX : DETAIL_MAX;
            box.placeholder = other ? 'Explain why this request is declined...' : 'e.g. anything the borrower should know...';
            if (other) box.focus();
        }
        notify(root);
    }

    document.addEventListener('click', function (e) {
        var chip = e.target.closest ? e.target.closest('.ps-reason-chip') : null;
        if (!chip) return;
        var root = chip.closest('[data-reason-picker]');
        if (root) choose(root, chip);
    });

    document.addEventListener('input', function (e) {
        var root = e.target.closest ? e.target.closest('[data-reason-picker]') : null;
        if (root && e.target.tagName === 'TEXTAREA') notify(root);
    });

    window.PSReasonPicker = { read: read, reset: reset };
})();
