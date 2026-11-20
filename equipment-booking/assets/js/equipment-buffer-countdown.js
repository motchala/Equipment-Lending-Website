/*
   equipment-booking/assets/js/equipment-buffer-countdown.js

   Live countdown for the 10-minute approval buffer of same-day equipment requests (see core/request-buffer.php).

   Every element with  data-eqbuf-until="<ISO time>"  shows the time left in its  .eqb-time  child, once a second.
   Put  data-eqbuf-id="<request id>"  on it as well (faculty and admin screens) and, when the time reaches zero,
   the server is asked to settle the request (api/buffer-status.php). When a decision has been made the page is told:
     - "pupsync:requests-changed" is fired (faculty My Activity already listens to it),
     - on the admin side the live poll and the Loans table are refreshed.
   Without data-eqbuf-id (the Student Portal has no login) the chip only counts down and shows "deciding..."; the
   portal's own status poll picks up the decision.

   Different attribute names from the room buffer (data-buffer-until) on purpose, so the two scripts never touch
   each other's chips. Exposes window.PSEquipBuffer = { fmt(seconds), settle(ids, callback) }.
*/
(function () {
    'use strict';
    if (window.PSEquipBuffer) return;

    var inflight = false;
    var lastCall = 0;

    /* 125 -> "2:05" */
    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    /* Ask the server to settle what is due and report the current status of these requests. */
    function settle(ids, done) {
        var list = (ids || []).filter(Boolean).slice(0, 25);
        if (!list.length) { if (done) done([]); return; }
        fetch('equipment-booking/api/buffer-status.php?ids=' + encodeURIComponent(list.join(',')), {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (done) done(Array.isArray(j.rows) ? j.rows : []); })
            .catch(function () { if (done) done([]); });
    }

    function announceDecided() {
        document.dispatchEvent(new CustomEvent('pupsync:requests-changed'));
        if (typeof window.adminDoPoll === 'function') window.adminDoPoll();
        if (typeof window.psRefreshLoansTable === 'function' && !document.hidden) window.psRefreshLoansTable();
    }

    function tick() {
        if (document.hidden) return;
        var nodes = document.querySelectorAll('[data-eqbuf-until]');
        if (!nodes.length) return;
        var now = Date.now(), due = [];
        Array.prototype.forEach.call(nodes, function (n) {
            var until = Date.parse(n.getAttribute('data-eqbuf-until') || '');
            if (isNaN(until)) return;
            var left = Math.ceil((until - now) / 1000);
            var t = n.querySelector('.eqb-time') || n;
            if (left > 0) {
                t.textContent = fmt(left);
                n.classList.remove('is-due');
            } else {
                t.textContent = 'deciding\u2026';
                n.classList.add('is-due');
                var id = n.getAttribute('data-eqbuf-id');
                if (id) due.push(id);
            }
        });
        if (due.length && !inflight && now - lastCall > 5000) {
            lastCall = now;
            inflight = true;
            settle(due, function (rows) {
                inflight = false;
                var decided = rows.some(function (r) { return r.status !== 'Waiting'; });
                if (decided) announceDecided();
            });
        }
    }

    setInterval(tick, 1000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });

    window.PSEquipBuffer = { fmt: fmt, settle: settle };
})();
