    <script nonce="<?php echo $csp_nonce; ?>">
        /* Activate the restored panel before revealing the content area. */
        (function() {
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
                }
            }
            document.documentElement.classList.remove('admin-tab-pending');
        })();
    </script>
