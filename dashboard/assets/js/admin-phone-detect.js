/* ================================================================
   admin-phone-detect.js
   Decides whether the Super Admin / Admin portal shows the PHONE APP layout
   (class "rx-mobile" on <html>) or the responsive layout.

   Loaded in <head>, BEFORE first paint, as a normal same-origin file
   (not an inline script) so it runs on every host and phone.

   A device counts as a phone when ANY of these is true:
     1. the window is 600px wide or less (narrow desktop windows, emulators)
     2. the physical screen is phone-sized (shorter side <= 540 CSS px)
        AND the device is touch / reports itself as mobile.
   A browser that says \"not mobile\" (Chrome \"Desktop site\", some https
   browsers) does NOT veto rule 2: the screen size and touch decide.
   Rule 2 does NOT look at window.innerWidth on purpose: on a real phone the
   browser can report a wider "layout viewport" (page content that overflows,
   "Desktop site" mode, zoomed-out pages). That made real phones fall through
   to the responsive layout while a desktop emulator worked.

   Tablets and desktops (short side > 540) never get the class and keep the
   responsive layout.

   Same rule as the Faculty portal (faculty-phone-detect.js). It looks only at the
   device, never at the host name, so it behaves the same on localhost / XAMPP
   (http, LAN address) and on the deployed site (https).

   Debug: open the page with  ?rxdebug=1  to see what the device reports.
================================================================ */
(function () {
    'use strict';

    var d = document.documentElement;

    function uaMobile() {
        var u = navigator.userAgentData;
        if (u && typeof u.mobile === 'boolean' && u.mobile) return true;
        return /iPhone|iPod|Android.+Mobile|Windows Phone|IEMobile|BlackBerry|Opera Mini|Mobile.+Firefox/i.test(navigator.userAgent || '');
    }

    function coarsePointer() {
        try { return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches); } catch (e) { return false; }
    }

    function touchDevice() {
        return (navigator.maxTouchPoints || 0) > 0 || ('ontouchstart' in window);
    }

    function info() {
        var sw = screen.width || 0, sh = screen.height || 0;
        var shortSide = Math.min(sw || 9999, sh || 9999);
        var narrow = (window.innerWidth || 0) <= 600;
        var uam = uaMobile(), coarse = coarsePointer(), touch = touchDevice();
        var phoneScreen = shortSide <= 540 && (uam || coarse || touch);
        return {
            narrow: narrow, phoneScreen: phoneScreen, uaMobile: uam, coarse: coarse, touch: touch,
            innerW: window.innerWidth, screenW: sw, screenH: sh, dpr: window.devicePixelRatio,
            result: narrow || phoneScreen
        };
    }

    function apply() {
        var i = info();
        d.classList.toggle('rx-mobile', i.result);
        return i;
    }

    apply();
    window.addEventListener('resize', apply);
    window.addEventListener('orientationchange', apply);

    /* ---- optional on-screen read-out:  ?rxdebug=1 ---- */
    if (/[?&]rxdebug=1\b/.test(location.search)) {
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.createElement('pre');
            box.style.cssText = 'position:fixed;left:6px;right:6px;bottom:6px;z-index:2147483647;margin:0;padding:8px 10px;' +
                'font:11px/1.35 monospace;color:#fff;background:rgba(0,0,0,.85);border-radius:8px;white-space:pre-wrap;pointer-events:none';
            document.body.appendChild(box);
            function paint() {
                var i = info();
                box.textContent =
                    'rx-mobile class : ' + d.classList.contains('rx-mobile') + '\n' +
                    'decision        : ' + i.result + '  (narrow=' + i.narrow + ', phoneScreen=' + i.phoneScreen + ')\n' +
                    'innerWidth      : ' + i.innerW + '\n' +
                    'screen          : ' + i.screenW + ' x ' + i.screenH + '  dpr ' + i.dpr + '\n' +
                    'uaMobile/coarse/touch : ' + i.uaMobile + ' / ' + i.coarse + ' / ' + i.touch + '\n' +
                    (navigator.userAgent || '').slice(0, 110);
            }
            paint();
            window.addEventListener('resize', paint);
            setInterval(paint, 1500);
        });
    }
})();
