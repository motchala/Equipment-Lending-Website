/* ================================================================
   ROLE SPLASH — housekeeping only
   ----------------------------------------------------------------
   The animation itself is pure CSS (assets/css/role-splash.css), so
   nothing here is required for the overlay to dismiss. This script:
     • removes the overlay from the DOM once its exit animation ends
     • lets the user skip it (click / Escape)
     • acts as a fail-safe removal if animationend never fires
================================================================ */
(function () {
    'use strict';

    var splash = document.getElementById('roleSplash');
    if (!splash) return;

    var exiting = false;
    var removed = false;

    function remove() {
        if (removed) return;
        removed = true;
        if (splash.parentNode) splash.parentNode.removeChild(splash);
    }

    splash.addEventListener('animationstart', function (e) {
        if (e.target === splash && (e.animationName === 'rsExit' || e.animationName === 'rsExitSoft')) {
            exiting = true;
        }
    });

    splash.addEventListener('animationend', function (e) {
        if (e.target === splash && (e.animationName === 'rsExit' || e.animationName === 'rsExitSoft')) {
            remove();
        }
    });

    function skip() {
        if (exiting || removed) return;
        exiting = true;
        splash.style.animation = 'none';
        void splash.offsetWidth; // flush so the transition below runs from opacity 1
        splash.style.transition = 'opacity 0.3s ease';
        splash.style.opacity = '0';
        setTimeout(remove, 340);
    }

    splash.addEventListener('click', skip);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') skip();
    });

    // Fail-safe: the CSS timeline finishes well before this.
    setTimeout(remove, 6000);
}());
