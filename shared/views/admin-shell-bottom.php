    <script nonce="<?php echo $csp_nonce; ?>">
        /* Reveal the restored screen (after a refresh).
           1. Put the restored screen in place now, while the page is still hidden (html.admin-tab-pending).
           2. Reveal only AFTER the page's own start-up has run (admin-dashboard.js init: theme, accent colour,
              open sub-tabs...). That start-up waits for the deferred scripts, which on a slow host can take seconds;
              revealing before it showed the Dashboard / a default sub-tab / the wrong theme for a moment. */
        (function() {
            var root = document.documentElement;
            if (typeof window.psActivateRestoredScreen === 'function') window.psActivateRestoredScreen();
            var reveal = function() { root.classList.remove('admin-tab-pending'); };
            if (window._preJumpTab && document.readyState === 'loading') {
                // Registered after admin-dashboard.js's own DOMContentLoaded handlers, so it runs after them.
                document.addEventListener('DOMContentLoaded', reveal);
            } else {
                reveal();
            }
        })();
    </script>
