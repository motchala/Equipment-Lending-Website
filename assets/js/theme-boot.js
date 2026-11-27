/* ================================================================
   theme-boot.js
   Applies the saved Dark theme BEFORE the first paint, so a refresh in
   dark mode never flashes the (default) light theme.

   Why: the page markup ships with data-theme="light" and the theme the
   user picked is only re-applied by admin-dashboard.js / faculty-dashboard.js
   after the page has started to paint. This tiny file runs synchronously in
   <head> (same-origin file, like the phone-detect script, so the CSP allows it)
   and sets the same attribute earlier.

   It reads the very same localStorage key the dashboards already use
   (admin: "adm_theme", faculty: "eq_theme", passed in data-key) and mirrors
   their rule: only "dark" is applied; anything else stays Light. Nothing is
   written - the dashboard scripts still own saving and the Theme picker.
   ================================================================ */
(function () {
    try {
        var s = document.currentScript;
        var key = s && s.getAttribute('data-key');
        if (key && window.localStorage.getItem(key) === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) { /* storage blocked: stay on the default Light theme */ }
})();
