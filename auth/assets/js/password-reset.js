/* ================================================================
   password-reset.js - the "Notify Super Admin" button on the sign-in
   form. The server decides everything: it already knows which account
   this is (from the session) and whether it has used all its attempts.
   This file only sends the request and shows the answer.
================================================================ */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var panel = document.getElementById('pwResetOffer');
        var btn = document.getElementById('pwResetNotifyBtn');
        if (!panel || !btn) return;

        var msg = document.getElementById('pwResetOfferMsg');
        var err = document.getElementById('pwResetOfferErr');
        var icon = btn.querySelector('i');
        var label = btn.querySelector('span');
        var csrf = document.querySelector('#loginForm input[name="csrf_token"]');
        var busy = false;

        function showSent(text) {
            panel.setAttribute('data-state', 'sent');
            if (msg) msg.textContent = text;
            if (err) err.hidden = true;
            btn.disabled = true;
            if (icon) { icon.className = 'fa-solid fa-circle-check'; }
            if (label) label.textContent = 'Super Admin notified';
        }

        function showError(text) {
            if (err) { err.textContent = text; err.hidden = false; }
            btn.disabled = false;
            if (icon) { icon.className = 'fa-solid fa-bell'; }
            if (label) label.textContent = 'Notify Super Admin';
        }

        btn.addEventListener('click', function () {
            if (busy || btn.disabled) return;
            busy = true;
            btn.disabled = true;
            if (err) err.hidden = true;
            if (icon) { icon.className = 'fa-solid fa-circle-notch is-spinning'; }
            if (label) label.textContent = 'Sending\u2026';

            var body = new URLSearchParams({ csrf_token: csrf ? csrf.value : '' });
            fetch(panel.getAttribute('data-endpoint'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
                .then(function (res) {
                    return res.json().catch(function () { return {}; });
                })
                .then(function (d) {
                    busy = false;
                    if (d && d.status === 'success') {
                        showSent(d.message || 'The Super Admin has been notified.');
                    } else {
                        showError((d && d.message) || 'We could not send your request. Please try again.');
                    }
                })
                .catch(function () {
                    busy = false;
                    showError('We could not reach the server. Check your connection and try again.');
                });
        });
    });
}());
