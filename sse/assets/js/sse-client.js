/**
 * sse-client.js
 * PUPSync SSE client manager.
 *
 * Replaces fixed-interval setInterval polling with Server-Sent Events.
 * Exposes window.PupSyncSSE = { init(config), active, fallback }.
 *
 * Falls back automatically to the existing setInterval polling if:
 *   - EventSource is not supported by the browser
 *   - 3 consecutive connection failures occur across ALL streams
 *
 * Usage (called by dashboard JS):
 *   PupSyncSSE.init({
 *     streams: [
 *       { url: 'sse/api/admin-equipment.php', onUpdate: someFn }
 *     ]
 *   });
 */
(function (w) {
    'use strict';

    // Public state
    var active   = false;
    var fallback = false;

    // Per-stream internal state: keyed by url
    var streams  = {};   // { url: { es, failCount, retryTimer } }
    var allUrls  = [];

    var BACKOFF   = [2000, 4000, 30000];   // ms after failure 1, 2, 3+
    var MAX_FAILS = 3;

    // ── Fallback ──────────────────────────────────────────────────────────────
    function triggerFallback() {
        if (fallback) return;
        fallback = true;
        active   = false;

        // Close all open EventSources
        allUrls.forEach(function (url) {
            var s = streams[url];
            if (s && s.es) {
                try { s.es.close(); } catch (e) {}
                s.es = null;
            }
            if (s && s.retryTimer) {
                clearTimeout(s.retryTimer);
                s.retryTimer = null;
            }
        });

        console.warn('[PupSync SSE] falling back to polling');
        try {
            w.document.dispatchEvent(new CustomEvent('pupsync:sse-fallback'));
        } catch (e) {}
    }

    function checkAllFailed() {
        if (allUrls.length === 0) return false;
        return allUrls.every(function (url) {
            return streams[url] && streams[url].failCount >= MAX_FAILS;
        });
    }

    // ── Open a single stream ──────────────────────────────────────────────────
    function openStream(url, onUpdate) {
        if (fallback) return;
        if (!streams[url]) {
            streams[url] = { es: null, failCount: 0, retryTimer: null };
        }

        var state = streams[url];
        if (state.es) {
            try { state.es.close(); } catch (e) {}
        }

        var es = new EventSource(url);
        state.es = es;

        es.onopen = function () {
            state.failCount = 0;
            active = true;
            try {
                w.document.dispatchEvent(new CustomEvent('pupsync:sse-active'));
            } catch (e) {}
        };

        es.addEventListener('update', function (evt) {
            try {
                var data = JSON.parse(evt.data);
                if (data && data.action === 'reload' && typeof onUpdate === 'function') {
                    onUpdate();
                }
            } catch (e) {
                console.error('[PupSync SSE] parse error on update event:', e);
            }
        });

        es.addEventListener('close', function () {
            // Clean server timeout — not a failure, reconnect after 1 s
            try { es.close(); } catch (e) {}
            state.es = null;
            if (!fallback) {
                state.retryTimer = setTimeout(function () {
                    openStream(url, onUpdate);
                }, 1000);
            }
        });

        es.addEventListener('auth_error', function () {
            // Session expired — reload the page
            allUrls.forEach(function (u) {
                var s = streams[u];
                if (s && s.es) { try { s.es.close(); } catch (e) {} }
            });
            w.location.reload();
        });

        es.onerror = function () {
            state.failCount++;
            try { es.close(); } catch (e) {}
            state.es = null;

            if (checkAllFailed()) {
                triggerFallback();
                return;
            }

            if (!fallback) {
                var delay = BACKOFF[Math.min(state.failCount - 1, BACKOFF.length - 1)];
                state.retryTimer = setTimeout(function () {
                    openStream(url, onUpdate);
                }, delay);
            }
        };
    }

    // ── Page visibility: pause when hidden, resume when visible ──────────────
    w.document.addEventListener('visibilitychange', function () {
        if (w.document.hidden) {
            allUrls.forEach(function (url) {
                var s = streams[url];
                if (s && s.es) {
                    try { s.es.close(); } catch (e) {}
                    s.es = null;
                    if (s.retryTimer) { clearTimeout(s.retryTimer); s.retryTimer = null; }
                }
            });
        } else {
            if (!fallback) {
                allUrls.forEach(function (url) {
                    var s = streams[url];
                    if (s && !s.es) {
                        openStream(url, s.onUpdate);
                    }
                });
            }
        }
    });

    // ── Public API ────────────────────────────────────────────────────────────
    function init(config) {
        // If EventSource is not supported, leave active=false and return
        // The dashboard JS checks active before calling init and falls back to setInterval
        if (typeof w.EventSource === 'undefined') {
            console.warn('[PupSync SSE] EventSource not supported — polling will be used');
            return;
        }

        if (!config || !Array.isArray(config.streams)) return;

        config.streams.forEach(function (streamCfg) {
            if (!streamCfg.url || typeof streamCfg.onUpdate !== 'function') return;
            if (allUrls.indexOf(streamCfg.url) === -1) {
                allUrls.push(streamCfg.url);
            }
            // Store onUpdate on the stream state for visibility reconnect
            if (!streams[streamCfg.url]) {
                streams[streamCfg.url] = { es: null, failCount: 0, retryTimer: null };
            }
            streams[streamCfg.url].onUpdate = streamCfg.onUpdate;
            openStream(streamCfg.url, streamCfg.onUpdate);
        });
    }

    // Expose public API
    w.PupSyncSSE = {
        init:     init,
        get active()   { return active; },
        get fallback() { return fallback; }
    };

}(window));
