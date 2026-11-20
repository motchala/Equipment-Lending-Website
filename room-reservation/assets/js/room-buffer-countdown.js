/*
   room-reservation/assets/js/room-buffer-countdown.js

   Live countdown for the 10-minute review buffer of same-day room requests (see core/room-buffer.php).

   Faculty side: every element with  data-buffer-until="<ISO time>"  (and data-buffer-id="<reservation id>")
   shows the time left in its  .rb-time  child, once a second. When it reaches zero the server is asked to
   settle the request (api/buffer-status.php); if the decision has been made the page is told to refresh
   through the existing "pupsync:reservation-submitted" event, which My Activity already listens to.

   Admin side: the reservations table draws its own countdown and uses  PSRoomBuffer.settle()  for the
   same server call (see admin-rooms-reservations-table.js).

   Exposes window.PSRoomBuffer = { fmt(seconds), settle(ids, callback) }.
*/
(function () {
    'use strict';
    if (window.PSRoomBuffer) return;

    var inflight = false;
    var lastCall = 0;

    /* 125 -> "2:05" */
    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    /* Ask the server to settle what is due and report the current status of these reservations. */
    function settle(ids, done) {
        var list = (ids || []).filter(Boolean).slice(0, 25);
        if (!list.length) { if (done) done([]); return; }
        fetch('room-reservation/api/buffer-status.php?ids=' + encodeURIComponent(list.join(',')), {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (done) done(Array.isArray(j.rows) ? j.rows : []); })
            .catch(function () { if (done) done([]); });
    }

    function tick() {
        if (document.hidden) return;
        var nodes = document.querySelectorAll('[data-buffer-until]');
        if (!nodes.length) return;
        var now = Date.now(), due = [];
        Array.prototype.forEach.call(nodes, function (n) {
            var until = Date.parse(n.getAttribute('data-buffer-until') || '');
            if (isNaN(until)) return;
            var left = Math.ceil((until - now) / 1000);
            var t = n.querySelector('.rb-time') || n;
            if (left > 0) {
                t.textContent = fmt(left);
                n.classList.remove('is-due');
            } else {
                t.textContent = 'deciding\u2026';
                n.classList.add('is-due');
                var id = n.getAttribute('data-buffer-id');
                if (id) due.push(id);
            }
        });
        if (due.length && !inflight && now - lastCall > 5000) {
            lastCall = now;
            inflight = true;
            settle(due, function (rows) {
                inflight = false;
                var decided = rows.some(function (r) { return r.status !== 'Waiting'; });
                if (decided) document.dispatchEvent(new CustomEvent('pupsync:reservation-submitted'));
            });
        }
    }

    setInterval(tick, 1000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });

    window.PSRoomBuffer = { fmt: fmt, settle: settle };
})();
