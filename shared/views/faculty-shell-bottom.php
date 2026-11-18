    <script nonce="<?php echo $csp_nonce; ?>">
        /* Reveal step for a page refresh (see the head script in faculty-shell-top.php).
           Only needed when the server's first guess was wrong (it printed a different screen as active than the one
           you were really on). The right screen is put in place while the page is still hidden AND transitions are
           off (html.fac-tab-pending), then the page is revealed. Otherwise the sidebar highlight would visibly fade
           from the Dashboard to the real screen, and the screen would slide in again. */
        (function() {
            var root = document.documentElement;
            var tab = window._facRestoreTab;
            if (tab && root.classList.contains('fac-tab-pending')) {
                var panelTab = tab === 'settings' ? 'home' : tab;   // Settings is an overlay on top of the Dashboard
                var target = document.getElementById('panel-' + panelTab);
                if (target) {
                    document.querySelectorAll('.tab-panel.active').forEach(function(p) { p.classList.remove('active'); });
                    target.classList.add('active');

                    document.querySelectorAll('.overlay-page.active').forEach(function(o) { o.classList.remove('active'); });
                    var overlay = document.getElementById('settingsOverlay');
                    if (tab === 'settings' && overlay) overlay.classList.add('active');

                    document.querySelectorAll('.side-nav-item').forEach(function(item) { item.classList.remove('active'); });
                    var navItem = document.getElementById(tab === 'settings' ? 'nav-settings' : 'nav-' + tab);
                    if (navItem) navItem.classList.add('active');

                    root.classList.add('fac-restored');   // the screen shows in place, no slide-in
                    void document.body.offsetWidth;       // commit the new state now, while transitions are still off
                }
            }
            root.classList.remove('fac-tab-pending');
        })();
    </script>
