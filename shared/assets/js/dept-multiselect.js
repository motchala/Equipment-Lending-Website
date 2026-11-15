/**
 * shared/assets/js/dept-multiselect.js
 * The check-mark dropdown used to choose a faculty member's departments (1 to 3).
 * Markup comes from faculty_department_multiselect() in shared/core/faculty-departments.php:
 *
 *   .ps-multisel[data-max][data-delim]
 *     input[type=hidden]            <- the value that is saved: the picked names joined by data-delim,
 *                                      in the order they were picked
 *     button.ps-multisel-trigger    <- shows the picked names as chips; opens/closes the list
 *     .ps-multisel-panel[role=listbox][aria-multiselectable]
 *       .ps-multisel-opt[role=option][data-value][data-short][aria-selected]
 *
 * The hidden input gets 'input' + 'change' events (they bubble), so forms that watch their fields
 * for edits (Settings > Edit Profile) see a change the normal way. The list opens inline under the
 * trigger (it pushes the content below it down) instead of floating, so it can never be clipped by
 * a card with overflow:hidden and it behaves on phones.
 *
 * Public API  window.PSMultiSelect:
 *   syncAll(root)            re-read every hidden input inside root (after code set .value itself)
 *   setInvalid(input, bool)  red outline + aria-invalid on the trigger for the hidden input's dropdown
 *   focus(input)             focus that dropdown's trigger
 */
(function () {
    'use strict';

    var instances = [];

    function Multi(root) {
        this.root = root;
        this.input = root.querySelector('input[type="hidden"]');
        this.trigger = root.querySelector('.ps-multisel-trigger');
        this.display = root.querySelector('.ps-multisel-display');
        this.panel = root.querySelector('.ps-multisel-panel');
        this.count = root.querySelector('.ps-multisel-count');
        this.note = root.querySelector('.ps-multisel-note');
        this.opts = Array.prototype.slice.call(root.querySelectorAll('.ps-multisel-opt'));
        this.max = parseInt(root.getAttribute('data-max'), 10) || 3;
        this.delim = root.getAttribute('data-delim') || '; ';
        this.placeholder = (root.getAttribute('data-placeholder') || 'Select\u2026').replace(/&hellip;/g, '\u2026');
        this.selected = [];
        this.open = false;
        this.active = -1;
        this.noteTimer = null;
        this.readValue();
        this.render();
        this.bind();
    }

    Multi.prototype.valid = function (v) {
        for (var i = 0; i < this.opts.length; i++) if (this.opts[i].getAttribute('data-value') === v) return true;
        return false;
    };

    /* the hidden input is the source of truth: picked names, in the order they were picked */
    Multi.prototype.readValue = function () {
        var out = [], parts = (this.input.value || '').split(';');
        for (var i = 0; i < parts.length; i++) {
            var v = parts[i].replace(/\s+/g, ' ').trim();
            if (v && out.indexOf(v) === -1 && this.valid(v)) out.push(v);
        }
        this.selected = out.slice(0, this.max);
    };

    Multi.prototype.shortOf = function (v) {
        for (var i = 0; i < this.opts.length; i++) {
            if (this.opts[i].getAttribute('data-value') === v) return this.opts[i].getAttribute('data-short') || v;
        }
        return v;
    };

    Multi.prototype.render = function () {
        var self = this, full = this.selected.length >= this.max;

        /* chips (or the placeholder) inside the trigger */
        this.display.textContent = '';
        if (this.selected.length) {
            var wrap = document.createElement('span');
            wrap.className = 'ps-multisel-chips';
            this.selected.forEach(function (v) {
                var chip = document.createElement('span');
                chip.className = 'ps-multisel-chip';
                chip.title = v;
                var t = document.createElement('span');
                t.textContent = self.shortOf(v);
                chip.appendChild(t);
                wrap.appendChild(chip);
            });
            this.display.appendChild(wrap);
        } else {
            var ph = document.createElement('span');
            ph.className = 'ps-multisel-placeholder';
            ph.textContent = this.placeholder;
            this.display.appendChild(ph);
        }

        /* the options */
        this.opts.forEach(function (o) {
            var on = self.selected.indexOf(o.getAttribute('data-value')) !== -1;
            o.setAttribute('aria-selected', on ? 'true' : 'false');
            if (full && !on) o.setAttribute('aria-disabled', 'true'); else o.removeAttribute('aria-disabled');
        });

        if (this.count) {
            this.count.textContent = this.selected.length + ' of ' + this.max + ' selected';
            this.count.classList.toggle('is-full', full);
        }
        if (!full) this.hideNote();
        this.applyDisabled();
    };

    Multi.prototype.applyDisabled = function () {
        var off = !!this.input.disabled;
        this.root.classList.toggle('is-disabled', off);
        this.trigger.disabled = off;
        if (off && this.open) this.close(false);
    };

    Multi.prototype.commit = function () {
        this.input.value = this.selected.join(this.delim);
        this.render();
        this.input.dispatchEvent(new Event('input', { bubbles: true }));
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    Multi.prototype.showNote = function () {
        var self = this;
        if (!this.note) return;
        this.note.hidden = false;
        clearTimeout(this.noteTimer);
        this.noteTimer = setTimeout(function () { self.hideNote(); }, 3500);
    };
    Multi.prototype.hideNote = function () {
        if (this.note) this.note.hidden = true;
        clearTimeout(this.noteTimer);
    };

    Multi.prototype.toggleValue = function (v) {
        var i = this.selected.indexOf(v);
        if (i !== -1) {
            this.selected.splice(i, 1);
        } else if (this.selected.length >= this.max) {
            this.showNote();
            return;
        } else {
            this.selected.push(v);
        }
        this.commit();
    };

    Multi.prototype.setActive = function (i, focus) {
        if (!this.opts.length) return;
        i = Math.max(0, Math.min(this.opts.length - 1, i));
        this.active = i;
        this.opts.forEach(function (o, k) { o.classList.toggle('is-active', k === i); });
        if (focus) this.opts[i].focus();
    };

    Multi.prototype.openList = function (focusOpt) {
        if (this.open || this.input.disabled) return;
        this.open = true;
        this.panel.hidden = false;
        this.root.classList.add('is-open');
        this.trigger.setAttribute('aria-expanded', 'true');
        if (focusOpt) {
            var first = 0;
            for (var i = 0; i < this.opts.length; i++) {
                if (this.opts[i].getAttribute('aria-selected') === 'true') { first = i; break; }
            }
            this.setActive(first, true);
        }
    };

    Multi.prototype.close = function (returnFocus) {
        if (!this.open) return;
        this.open = false;
        this.panel.hidden = true;
        this.root.classList.remove('is-open');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.hideNote();
        if (returnFocus) this.trigger.focus();
    };

    Multi.prototype.bind = function () {
        var self = this;

        this.trigger.addEventListener('click', function () {
            if (self.open) self.close(false); else self.openList(false);
        });

        this.trigger.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                self.openList(true);
            } else if (e.key === 'Escape' && self.open) {
                e.preventDefault();
                e.stopPropagation();          // closes the list only; does not leave Edit Profile
                self.close(true);
            }
        });

        this.panel.addEventListener('click', function (e) {
            var o = e.target.closest('.ps-multisel-opt');
            if (!o || !self.panel.contains(o)) return;
            self.setActive(self.opts.indexOf(o), false);
            self.toggleValue(o.getAttribute('data-value'));
        });

        this.panel.addEventListener('keydown', function (e) {
            var k = e.key;
            if (k === 'ArrowDown') { e.preventDefault(); self.setActive(self.active + 1, true); }
            else if (k === 'ArrowUp') { e.preventDefault(); self.setActive(self.active - 1, true); }
            else if (k === 'Home') { e.preventDefault(); self.setActive(0, true); }
            else if (k === 'End') { e.preventDefault(); self.setActive(self.opts.length - 1, true); }
            else if (k === ' ' || k === 'Enter') {
                e.preventDefault();
                e.stopPropagation();          // Enter here must never submit/save the surrounding form
                var o = self.opts[self.active];
                if (o) self.toggleValue(o.getAttribute('data-value'));
            } else if (k === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                self.close(true);
            } else if (k === 'Tab') {
                self.close(false);
            }
        });

        /* leaving the dropdown by Tab/click elsewhere closes it */
        this.root.addEventListener('focusout', function (e) {
            if (!self.open) return;
            var to = e.relatedTarget;
            if (to && self.root.contains(to)) return;
            if (!to) return;                  // a click inside the list: the click handler decides
            self.close(false);
        });

        /* the form turns the field on/off by toggling .disabled on the hidden input */
        if (window.MutationObserver) {
            new MutationObserver(function () { self.applyDisabled(); })
                .observe(this.input, { attributes: true, attributeFilter: ['disabled'] });
        }
    };

    function initAll(scope) {
        var roots = (scope || document).querySelectorAll('.ps-multisel[data-psms]');
        Array.prototype.forEach.call(roots, function (root) {
            if (root.__psms) return;
            root.__psms = new Multi(root);
            instances.push(root.__psms);
        });
    }

    document.addEventListener('click', function (e) {
        instances.forEach(function (m) {
            if (m.open && !m.root.contains(e.target)) m.close(false);
        });
    });

    function find(input) { return input && input.closest ? (input.closest('.ps-multisel') || {}).__psms : null; }

    window.PSMultiSelect = {
        init: initAll,
        syncAll: function (scope) {
            instances.forEach(function (m) {
                if (!scope || scope.contains(m.root)) { m.readValue(); m.render(); }
            });
        },
        setInvalid: function (input, bad) {
            var m = find(input);
            if (!m) return;
            m.root.classList.toggle('is-invalid', !!bad);
            if (bad) m.trigger.setAttribute('aria-invalid', 'true'); else m.trigger.removeAttribute('aria-invalid');
        },
        focus: function (input) { var m = find(input); if (m) m.trigger.focus(); },
        values: function (input) { var m = find(input); return m ? m.selected.slice() : []; }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { initAll(); });
    else initAll();
})();
