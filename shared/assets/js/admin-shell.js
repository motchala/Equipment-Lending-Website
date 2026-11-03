        /* ── Modal open / close ─────────────────────────────────────── */
        function psOpenModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('ps-modal-open');
        }

        function psCloseModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('ps-modal-open');
        }

        /* Close on backdrop click */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ps-modal-backdrop').forEach(function(bd) {
                bd.addEventListener('click', function(e) {
                    if (e.target === bd) bd.classList.remove('ps-modal-open');
                });
            });

        });
