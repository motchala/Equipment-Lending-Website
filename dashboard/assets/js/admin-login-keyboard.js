/* ================================================================
   admin-login-keyboard.js
   Closes the on-screen keyboard right after a successful sign-in to the
   Super Admin / Admin portal.

   The sign-in form itself already lets go of the focused field when it is
   submitted (auth/assets/js/landing-page.js). This is the safety net on the
   admin side: some phone browsers (notably iPhone, and home-screen web apps)
   can carry the keyboard of the page you came from over to the next page.

   It only acts on the sign-in hand-off. The role splash (#roleSplash) is
   printed only on that first load after signing in, so refreshing the page
   or moving around the portal never runs any of this.

   Loaded with `defer` from shared/views/admin-shell-scripts.php.
================================================================ */
(function () {
    'use strict';

    var splash = document.getElementById('roleSplash');
    if (!splash) return; // not a sign-in hand-off: do nothing at all

    var D = document;

    function closeKeyboard() {
        // Still on the hand-off? (the splash removes itself when it finishes)
        if (!D.getElementById('roleSplash')) return;

        // 1) let go of whatever field still has focus
        var a = D.activeElement;
        if (a && a !== D.body && typeof a.blur === 'function') a.blur();

        // 2) if the browser still shows a keyboard that belonged to the previous
        //    page, move focus to a throw-away READ-ONLY field (it never opens a
        //    keyboard) and drop it again. That makes the browser close the keyboard.
        var t = D.createElement('input');
        t.type = 'text';
        t.readOnly = true;
        t.tabIndex = -1;
        t.setAttribute('aria-hidden', 'true');
        t.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;border:0;padding:0;' +
            'font-size:16px;pointer-events:none';
        D.body.appendChild(t);
        try { t.focus({ preventScroll: true }); } catch (e) { /* older browsers: nothing to do */ }
        t.blur();
        if (t.parentNode) t.parentNode.removeChild(t);
    }

    closeKeyboard();
    window.addEventListener('load', closeKeyboard); // once the page has finished loading
    setTimeout(closeKeyboard, 350);                 // and once more while the splash plays
})();
