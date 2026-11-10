/* ============================================================================
   letter-camera.js  -  "Take a photo of the request letter"

   Used by every request-letter field (faculty rooms, faculty equipment, student
   borrow, student rooms). Nothing to configure: put a button on the page with

        <button type="button" data-letter-camera="ID_OF_THE_FILE_INPUT">...</button>

   and this script opens the camera, lets the person take / retake a photo, and
   puts the result into that file input exactly as if they had chosen the file
   themselves (a "change" event is fired, so each page's own upload code runs
   unchanged). The normal "choose a file" option is never touched.

   - Live camera with a preview (rear camera first on phones, switch if there are
     several). Needs HTTPS or localhost, which is a browser rule for any camera.
   - If the live camera cannot be used (blocked, no camera, plain http), it falls
     back to the phone's own camera app / a photo picker via <input capture>.
   - The photo is saved as a JPEG, scaled down so it stays under the 5 MB limit.
   ========================================================================== */
(function () {
    'use strict';
    if (window.PSLetterCamera) return;

    var MAX_SIDE = 2200;                 /* longest side of the saved photo, in pixels */
    var MAX_BYTES = 4.5 * 1024 * 1024;   /* stay safely under the 5 MB upload limit */
    var QUALITIES = [0.9, 0.8, 0.7, 0.6];

    var ui = null;
    var s = { input: null, trigger: null, stream: null, facing: 'environment', blob: null, shotUrl: '', open: false };

    function icon(name) { return '<span class="material-symbols-outlined" aria-hidden="true">' + name + '</span>'; }
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }

    /* ---------- build the dialog once ------------------------------------- */
    function build() {
        if (ui) return;
        var o = document.createElement('div');
        o.className = 'pslc-overlay';
        o.id = 'pslcOverlay';
        o.hidden = true;
        o.setAttribute('role', 'dialog');
        o.setAttribute('aria-modal', 'true');
        o.setAttribute('aria-labelledby', 'pslcTitle');
        o.innerHTML =
            '<div class="pslc-card">' +
            '<header class="pslc-head">' +
            '<span class="pslc-tile" aria-hidden="true">' + icon('photo_camera') + '</span>' +
            '<div class="pslc-titles">' +
            '<h3 id="pslcTitle">Photograph your request letter</h3>' +
            '<p class="pslc-sub">Take a clear photo of the signed letter.</p>' +
            '</div>' +
            '<button type="button" class="pslc-x" data-pslc="close" aria-label="Close camera">' + icon('close') + '</button>' +
            '</header>' +
            '<div class="pslc-stage" id="pslcStage">' +
            '<video class="pslc-video" id="pslcVideo" playsinline muted autoplay></video>' +
            '<img class="pslc-shot" id="pslcShot" alt="Photo of your request letter" hidden>' +
            '<div class="pslc-flash" id="pslcFlash" aria-hidden="true"></div>' +
            '<div class="pslc-msg" id="pslcMsg" role="status" aria-live="polite" hidden></div>' +
            '</div>' +
            '<p class="pslc-tip" id="pslcTip"></p>' +
            '<footer class="pslc-foot">' +
            '<button type="button" class="pslc-btn pslc-btn--ghost" data-pslc="switch" id="pslcSwitch" hidden>' + icon('flip_camera_android') + 'Switch camera</button>' +
            '<button type="button" class="pslc-btn pslc-btn--ghost" data-pslc="native" id="pslcNative" hidden>' + icon('add_a_photo') + 'Use phone camera or choose a photo</button>' +
            '<button type="button" class="pslc-btn pslc-btn--ghost" data-pslc="retake" id="pslcRetake" hidden>' + icon('refresh') + 'Retake</button>' +
            '<button type="button" class="pslc-btn pslc-btn--primary" data-pslc="capture" id="pslcCapture">' + icon('photo_camera') + 'Take photo</button>' +
            '<button type="button" class="pslc-btn pslc-btn--primary" data-pslc="use" id="pslcUse" hidden>' + icon('check') + 'Use this photo</button>' +
            '</footer>' +
            '</div>';
        document.body.appendChild(o);
        ui = {
            overlay: o, stage: $('pslcStage'), video: $('pslcVideo'), shot: $('pslcShot'), flash: $('pslcFlash'), msg: $('pslcMsg'), tip: $('pslcTip'),
            sw: $('pslcSwitch'), native: $('pslcNative'), retake: $('pslcRetake'), capture: $('pslcCapture'), use: $('pslcUse')
        };
        o.addEventListener('click', function (e) {
            if (e.target === o) { close(); return; }
            var b = e.target.closest('[data-pslc]');
            if (!b || b.disabled) return;
            var a = b.getAttribute('data-pslc');
            if (a === 'close') close();
            else if (a === 'capture') capture();
            else if (a === 'retake') retake();
            else if (a === 'use') usePhoto();
            else if (a === 'switch') switchCamera();
            else if (a === 'native') { var inp = s.input; close(); nativePicker(inp); }
        });
    }

    /* ---------- screen states ---------------------------------------------- */
    function setMode(mode, message) {
        var live = mode === 'live', review = mode === 'review', error = mode === 'error';
        ui.stage.classList.toggle('is-live', live);
        ui.video.hidden = !live;
        ui.shot.hidden = !review;
        ui.capture.hidden = !live;
        ui.use.hidden = !review;
        ui.retake.hidden = !review;
        ui.native.hidden = !error;
        if (!live) ui.sw.hidden = true;
        ui.msg.hidden = !message;
        ui.msg.textContent = message || '';
        ui.tip.textContent = review
            ? 'Check that the whole letter and the signature are clear and readable. Retake it if not.'
            : (error ? '' : 'Lay the letter flat in good light and fill the frame, then press Take photo.');
        ui.tip.hidden = !ui.tip.textContent;
        var first = review ? ui.use : (live ? ui.capture : ui.native);
        if (first && !first.hidden) first.focus();
    }

    /* ---------- camera ------------------------------------------------------ */
    function canUseCamera() {
        return !!(window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
    function stopStream() {
        if (s.stream) { s.stream.getTracks().forEach(function (t) { t.stop(); }); s.stream = null; }
        if (ui && ui.video) { ui.video.srcObject = null; }
    }
    function startStream() {
        stopStream();
        setMode('error', 'Starting the camera\u2026');
        ui.native.hidden = true;
        var constraints = { audio: false, video: { facingMode: { ideal: s.facing }, width: { ideal: 1920 }, height: { ideal: 1080 } } };
        return navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
            if (!s.open) { stream.getTracks().forEach(function (t) { t.stop(); }); return; }
            s.stream = stream;
            ui.video.srcObject = stream;
            var p = ui.video.play(); if (p && p.catch) p.catch(function () { });
            setMode('live');
            return navigator.mediaDevices.enumerateDevices().then(function (list) {
                var cams = list.filter(function (d) { return d.kind === 'videoinput'; });
                ui.sw.hidden = cams.length < 2 || !ui.video || ui.video.hidden;
            }).catch(function () { });
        }).catch(function (err) {
            if (!s.open) return;
            var n = err && err.name;
            var text;
            if (n === 'NotAllowedError' || n === 'SecurityError' || n === 'PermissionDeniedError') {
                text = 'Camera access was blocked. Allow the camera for this site in your browser settings, or use your phone\u2019s camera / choose a photo instead.';
            } else if (n === 'NotFoundError' || n === 'DevicesNotFoundError' || n === 'OverconstrainedError') {
                text = 'No camera was found on this device. You can choose a photo or upload the letter as a file instead.';
            } else if (n === 'NotReadableError' || n === 'TrackStartError') {
                text = 'The camera is being used by another app. Close it and try again, or choose a photo instead.';
            } else {
                text = 'The camera could not be started. You can choose a photo or upload the letter as a file instead.';
            }
            setMode('error', text);
        });
    }
    function switchCamera() {
        s.facing = s.facing === 'environment' ? 'user' : 'environment';
        startStream();
    }

    /* ---------- take / retake ----------------------------------------------- */
    function toBlob(canvas, q) {
        return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', q); });
    }
    function capture() {
        var v = ui.video;
        if (!s.stream || !v.videoWidth || !v.videoHeight) return;
        ui.capture.disabled = true;
        var scale = Math.min(1, MAX_SIDE / Math.max(v.videoWidth, v.videoHeight));
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(v.videoWidth * scale);
        canvas.height = Math.round(v.videoHeight * scale);
        canvas.getContext('2d').drawImage(v, 0, 0, canvas.width, canvas.height);
        ui.flash.classList.remove('is-on'); void ui.flash.offsetWidth; ui.flash.classList.add('is-on');

        var i = 0;
        (function attempt() {
            toBlob(canvas, QUALITIES[i]).then(function (blob) {
                if (blob && blob.size > MAX_BYTES && i < QUALITIES.length - 1) { i++; return attempt(); }
                if (blob && blob.size > MAX_BYTES) {          /* still big: shrink once more */
                    var c2 = document.createElement('canvas');
                    c2.width = Math.round(canvas.width * 0.75); c2.height = Math.round(canvas.height * 0.75);
                    c2.getContext('2d').drawImage(canvas, 0, 0, c2.width, c2.height);
                    return toBlob(c2, 0.7).then(finish);
                }
                finish(blob);
            });
        })();

        function finish(blob) {
            ui.capture.disabled = false;
            if (!blob) { setMode('error', 'The photo could not be saved. Please try again or choose a photo instead.'); return; }
            if (s.shotUrl) URL.revokeObjectURL(s.shotUrl);
            s.blob = blob;
            s.shotUrl = URL.createObjectURL(blob);
            ui.shot.src = s.shotUrl;
            setMode('review');
        }
    }
    function retake() {
        s.blob = null;
        if (s.shotUrl) { URL.revokeObjectURL(s.shotUrl); s.shotUrl = ''; }
        ui.shot.removeAttribute('src');
        if (s.stream) setMode('live'); else startStream();
    }

    /* ---------- hand the photo to the file input ---------------------------- */
    function setFiles(input, file) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (e) { return false; }
        if (!input.files || !input.files.length) return false;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }
    function usePhoto() {
        if (!s.blob || !s.input) return;
        var d = new Date();
        var name = 'request-letter-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.jpg';
        var file = new File([s.blob], name, { type: 'image/jpeg', lastModified: Date.now() });
        var input = s.input;
        if (setFiles(input, file)) { close(); return; }
        setMode('error', 'This browser could not attach the photo automatically. Please choose a photo instead.');
    }

    /* ---------- fallback: the phone's own camera app / a photo picker -------- */
    function nativePicker(input) {
        if (!input) return;
        var f = document.createElement('input');
        f.type = 'file';
        f.accept = 'image/*';
        f.setAttribute('capture', 'environment');
        f.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0';
        f.addEventListener('change', function () {
            if (f.files && f.files[0]) setFiles(input, f.files[0]);
            if (f.parentNode) f.parentNode.removeChild(f);
        });
        document.body.appendChild(f);
        f.click();
    }

    /* ---------- open / close ------------------------------------------------- */
    function onKey(e) {
        if (!s.open) return;
        if (e.key === 'Escape') { e.stopPropagation(); e.preventDefault(); close(); return; }
        if (e.key === 'Tab') {            /* keep keyboard focus inside the dialog */
            var f = Array.prototype.filter.call(ui.overlay.querySelectorAll('button'), function (b) { return !b.hidden && !b.disabled && b.offsetParent !== null; });
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }
    function open(input, trigger) {
        if (!input || input.disabled) return;
        if (!canUseCamera()) { nativePicker(input); return; }   /* plain http, very old browser */
        build();
        s.input = input; s.trigger = trigger || null; s.blob = null; s.open = true;
        ui.shot.removeAttribute('src');
        ui.overlay.hidden = false;
        document.documentElement.classList.add('pslc-lock');
        document.addEventListener('keydown', onKey, true);
        startStream();
    }
    function close() {
        if (!s.open) return;
        s.open = false;
        stopStream();
        if (s.shotUrl) { URL.revokeObjectURL(s.shotUrl); s.shotUrl = ''; }
        s.blob = null;
        ui.overlay.hidden = true;
        document.documentElement.classList.remove('pslc-lock');
        document.removeEventListener('keydown', onKey, true);
        var t = s.trigger; s.input = null; s.trigger = null;
        if (t && t.focus && document.contains(t)) t.focus();
    }

    /* ---------- any [data-letter-camera] button opens it --------------------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-letter-camera]');
        if (!b || b.disabled) return;
        e.preventDefault();
        open(document.getElementById(b.getAttribute('data-letter-camera')), b);
    });
    window.addEventListener('pagehide', function () { if (s.open) close(); });

    window.PSLetterCamera = { open: open, close: close };
})();
