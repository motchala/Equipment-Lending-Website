         (function() {
            var TABS = ['activity', 'lending', 'rooms'];
            var root = document.documentElement;

            var nav = (window.performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation')[0] : null;
            var isReload = nav ? nav.type === 'reload' : !!(performance.navigation && performance.navigation.type === 1);

            // Remember a hard-refresh shortcut so the page that loads next knows about it.
            window.addEventListener('keydown', function(e) {
                var k = (e.key || '').toLowerCase();
                if (((e.ctrlKey || e.metaKey) && ((e.shiftKey && k === 'r') || k === 'f5')) || (e.shiftKey && k === 'f5')) {
                    try { sessionStorage.setItem('facultyHardRefresh', String(Date.now())); } catch (_) {}
                }
            }, true);

            var hard = false;
            try {
                var stamp = parseInt(sessionStorage.getItem('facultyHardRefresh'), 10);
                hard = isReload && stamp > 0 && (Date.now() - stamp) < 15000;
                sessionStorage.removeItem('facultyHardRefresh');
            } catch (_) {}

            // The screen you were on is kept in sessionStorage as a backup for the #tab- part of the URL.
            // Only a refresh may use it; opening the page any other way starts on the Dashboard.
            var stored = null;
            try {
                stored = sessionStorage.getItem('facultyCurrentTab');
                if (!isReload || hard) sessionStorage.removeItem('facultyCurrentTab');
            } catch (_) {}
            if (hard) return;

            var hash = window.location.hash;
            var hashTab = hash.indexOf('#tab-') === 0 ? hash.slice(5).split('-')[0] : null;
            var tab = isReload ? (hashTab || stored) : null;
            if (TABS.indexOf(tab) === -1) return;

            window._preJumpTab = tab;
            root.classList.add('fac-tab-pending');
            // Safety net: never leave the page hidden if the reveal in faculty-dashboard.js fails to run.
            // It waits for the page to finish loading (a slow connection can take longer than any fixed
            // timeout, and lifting early would flash the Dashboard), then lifts after a short grace.
            var lift = function() { root.classList.remove('fac-tab-pending'); };
            window.addEventListener('load', function() { setTimeout(lift, 1500); });
            setTimeout(lift, 20000);
        })();
