    <script nonce="<?php echo $csp_nonce; ?>">
        /* Activate the restored panel before revealing the content area.
           The new state is committed while the page is still hidden AND transitions are off
           (html.admin-tab-pending), then the page is revealed. Otherwise the sidebar highlight would
           visibly fade from the Dashboard to the real tab, and the screen would slide in again. */
        (function() {
            var root = document.documentElement;
            var tab = window._preJumpTab;
            if (tab) {
                var target = document.getElementById('panel-' + tab);
                if (target) {
                    var current = document.querySelector('.tab-panel.active');
                    if (current) current.classList.remove('active');
                    target.classList.add('active');

                    document.querySelectorAll('.nav-item[data-tab]').forEach(function(item) {
                        item.classList.toggle('active', item.dataset.tab === tab);
                    });
                    var accountToggle = document.getElementById('navAccountToggle');
                    if (accountToggle) accountToggle.classList.toggle('active', tab === 'settings');

                    root.classList.add('ps-restored');   // the restored screen shows in place, no slide-in
                    void document.body.offsetWidth;      // commit the new state now, while transitions are still off
                }
            }
            root.classList.remove('admin-tab-pending');
        })();
    </script>
