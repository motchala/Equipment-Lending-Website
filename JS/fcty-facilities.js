/* ================================================================
   PUPSYNC — FACILITIES TAB  (fcty-facilities.js)
   Handles: campus selection, building carousel, view switching.
   Companion to fcty-facilities.php + fcty-facilities.css.

   To add a building: push a new object into CAMPUS_DATA[key].buildings.
   To add a campus:   add a new key to CAMPUS_DATA and a card in the PHP.
================================================================ */
(function () {
    'use strict';

    /* ══════════════════════════════════════════════════════════════
       BUILDING DATA
       Add buildings here as floor plans become ready.
       Fields per building:
         id      — unique slug  (used for future floor-plan routing)
         wing    — location label shown in badge
         icon    — Material Symbol name for the badge icon
         name    — building display name
         desc    — short description shown on slide
         rooms   — room count chip
         floors  — floor count chip
         image   — path to building photo (relative or absolute)
    ══════════════════════════════════════════════════════════════ */
    var CAMPUS_DATA = {

        main: {
            label: 'PUP MAIN',
            buildings: [
                {
                    id:     'main-building-a',
                    wing:   'South Wing',
                    icon:   'domain',
                    name:   'Building A (Old)',
                    desc:   'Traditional lecture halls, administrative offices, and main library facilities.',
                    rooms:  42,
                    floors: 4,
                    image:  'https://lh3.googleusercontent.com/aida-public/AB6AXuA1Q7PML7NarrZ5kY4dzf1ZB2o_cTPc1Sp0zHFfSZyCqtBBRom0kx8VD0rVUxuHKVMyol48A2-_H3IrpvcF5KyUjR-jioqPPEKw47A8kD3_6yICVjnqNh4Mw82aAgVTA0L_H-F_bxCmHYhC8l5jopuFilQjs6Hyw1V377-N1_kTlgBopBXa2PymZsrvvqFOh-4xU7I8W9lviV0PBGWYDfhPmUPcO_BcnlvP2xrkVf_vxgUXOpFImYANaibkMHPT0I62xF38uYUswg'
                },
                {
                    id:     'main-building-b',
                    wing:   'North Wing',
                    icon:   'business',
                    name:   'Building B (New)',
                    desc:   'Modern laboratories, smart classrooms, and collaborative study spaces.',
                    rooms:  68,
                    floors: 6,
                    image:  'https://lh3.googleusercontent.com/aida-public/AB6AXuCzoEHIM42-lK4F0jeMBDW136WduHm-NScV1AjHax7lrxoyyU7B7pmKofA3M1KEbbFqGbaxjx11CRxhpmleFaWG8TpqvZtB8usbRQMbkjjdGN65hJFQQXVQ2VkT6WkbE4lM-Aa0soZvkUgIGtE4g_ugi7Amkkv0ewxqB7YmrQzFTHFr1czkU65hz5gPPmStnmvTr_Hy9Vbkzk2fMlpAyavG3vjfkKgsfERz-BWrkTvVJhXay_80cPS3CZdOBl3eVB9mv3b4Q8Qpnw'
                }
                /* ── Add Building C / D / etc. below when ready ──
                ,{
                    id:     'main-building-c',
                    wing:   'East Wing',
                    icon:   'school',
                    name:   'Building C',
                    desc:   'Description here.',
                    rooms:  0,
                    floors: 0,
                    image:  'assets/images/building-c.jpg'
                }
                */
            ]
        },

        cite: {
            label: 'PUP CITE',
            buildings: [
                /* ── Add CITE buildings below when floor plans are ready ──
                {
                    id:     'cite-engineering',
                    wing:   'Main Block',
                    icon:   'precision_manufacturing',
                    name:   'Engineering Building',
                    desc:   'Specialized labs and workshops for engineering disciplines.',
                    rooms:  30,
                    floors: 3,
                    image:  'assets/images/cite-engineering.jpg'
                }
                */
            ]
        }

    };

    /* ══════════════════════════════════════════════════════════════
       DOM REFERENCES  (resolved in init())
    ══════════════════════════════════════════════════════════════ */
    var campusView, buildingView;
    var carouselInner, dotsWrap, prevBtn, nextBtn, carouselWrap;
    var breadcrumbBack, breadcrumbCampusLabel, buildingTitle;

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL STATE
    ══════════════════════════════════════════════════════════════ */
    var currentSlide     = 0;
    var totalSlides      = 0;
    var autoSlideTimer   = null;
    var activeCampusKey  = null;
    var AUTO_SLIDE_MS    = 5000;

    /* ── Build slide HTML string ─────────────────────────────────── */
    function buildSlideHTML(building, index) {
        return [
            '<div class="fcty-carousel-slide">',
                '<img',
                    ' src="' + building.image + '"',
                    ' alt="' + building.name + '"',
                    ' loading="' + (index === 0 ? 'eager' : 'lazy') + '"',
                '>',
                '<div class="fcty-slide-overlay"></div>',
                '<div class="fcty-slide-content">',
                    '<div class="fcty-slide-badge">',
                        '<span class="material-symbols-outlined">' + building.icon + '</span>',
                        '<span class="fcty-slide-badge-text">' + building.wing + '</span>',
                    '</div>',
                    '<h3 class="fcty-slide-title">' + building.name + '</h3>',
                    '<p class="fcty-slide-desc">' + building.desc + '</p>',
                    '<div class="fcty-slide-meta">',
                        '<span class="fcty-meta-chip">' + building.rooms + ' Rooms</span>',
                        '<span class="fcty-meta-chip">' + building.floors + ' Floors</span>',
                    '</div>',
                    '<button class="fcty-slide-btn" data-building-id="' + building.id + '">',
                        'Select Building',
                        '<span class="material-symbols-outlined">arrow_forward</span>',
                    '</button>',
                '</div>',
            '</div>'
        ].join('');
    }

    /* ── Build coming-soon placeholder slide ─────────────────────── */
    function buildPlaceholderSlide(campusLabel) {
        return [
            '<div class="fcty-carousel-slide">',
                '<div class="fcty-slide-placeholder">',
                    '<span class="material-symbols-outlined">construction</span>',
                    '<h3>Buildings Coming Soon</h3>',
                    '<p>Floor plans and room reservations for <strong>',
                        campusLabel,
                    '</strong> are currently being prepared. Check back soon!</p>',
                '</div>',
            '</div>'
        ].join('');
    }

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL RENDERING
    ══════════════════════════════════════════════════════════════ */
    function renderCarousel(campusKey) {
        var campus    = CAMPUS_DATA[campusKey];
        var buildings = campus.buildings;

        /* Clear previous slides + dots */
        carouselInner.innerHTML = '';
        dotsWrap.innerHTML      = '';
        currentSlide            = 0;

        /* Snap to position 0 without animation */
        carouselInner.style.transition = 'none';
        carouselInner.style.transform  = 'translateX(0%)';
        /* Re-enable CSS transition after the style flush */
        requestAnimationFrame(function () {
            carouselInner.style.transition = '';
        });

        if (!buildings || buildings.length === 0) {
            /* No buildings yet — placeholder slide */
            carouselInner.innerHTML = buildPlaceholderSlide(campus.label);
            dotsWrap.innerHTML = '<button class="fcty-dot active" aria-label="Slide 1" role="tab"></button>';
            totalSlides = 1;
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }

        /* Render each building as a slide + matching dot */
        var slidesHTML = '';
        buildings.forEach(function (building, idx) {
            slidesHTML += buildSlideHTML(building, idx);

            var dot = document.createElement('button');
            dot.className = 'fcty-dot' + (idx === 0 ? ' active' : '');
            dot.setAttribute('aria-label', 'Slide ' + (idx + 1) + ': ' + building.name);
            dot.setAttribute('role', 'tab');
            dot.dataset.idx = String(idx);
            dot.addEventListener('click', function () {
                goToSlide(parseInt(this.dataset.idx, 10));
                resetAutoSlide();
            });
            dotsWrap.appendChild(dot);
        });

        carouselInner.innerHTML = slidesHTML;
        totalSlides = buildings.length;

        /* Show arrows only when there is more than one slide */
        prevBtn.style.display = totalSlides > 1 ? '' : 'none';
        nextBtn.style.display = totalSlides > 1 ? '' : 'none';
    }

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL NAVIGATION
    ══════════════════════════════════════════════════════════════ */
    function goToSlide(idx) {
        if (totalSlides <= 1) return;
        currentSlide = ((idx % totalSlides) + totalSlides) % totalSlides;
        carouselInner.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';

        dotsWrap.querySelectorAll('.fcty-dot').forEach(function (dot, i) {
            dot.classList.toggle('active', i === currentSlide);
            dot.setAttribute('aria-selected', i === currentSlide ? 'true' : 'false');
        });
    }

    function nextSlide() { goToSlide(currentSlide + 1); }
    function prevSlide()  { goToSlide(currentSlide - 1); }

    function startAutoSlide() {
        if (totalSlides <= 1) return;
        clearInterval(autoSlideTimer);
        autoSlideTimer = setInterval(nextSlide, AUTO_SLIDE_MS);
    }

    function resetAutoSlide() {
        clearInterval(autoSlideTimer);
        startAutoSlide();
    }

    function stopAutoSlide() {
        clearInterval(autoSlideTimer);
        autoSlideTimer = null;
    }

    /* ══════════════════════════════════════════════════════════════
       VIEW SWITCHING
    ══════════════════════════════════════════════════════════════ */
    function showBuildingView(campusKey) {
        activeCampusKey = campusKey;
        var campus = CAMPUS_DATA[campusKey];

        /* Update breadcrumb + title */
        breadcrumbCampusLabel.textContent = campus.label;
        buildingTitle.innerHTML = campus.label + ' &mdash; Select Building';

        /* Populate carousel for this campus */
        renderCarousel(campusKey);

        /* Swap views */
        campusView.style.display   = 'none';
        buildingView.style.display = '';

        /* Start auto-advance */
        startAutoSlide();
    }

    function showCampusView() {
        stopAutoSlide();
        buildingView.style.display = 'none';
        campusView.style.display   = '';
        activeCampusKey = null;
    }

    /* ══════════════════════════════════════════════════════════════
       BUILDING SELECTION CALLBACK
       Wire this to your floor-plan view when it is ready.
    ══════════════════════════════════════════════════════════════ */
    function onBuildingSelected(buildingId, campusKey) {
        /*
         * TODO: Replace this stub with your floor-plan panel logic.
         *
         * Examples:
         *   switchTab('floorplan');
         *   loadFloorPlan(campusKey, buildingId);
         *   window.location.href = 'floor-plan.php?campus=' + campusKey + '&building=' + buildingId;
         */
        console.log('[PUPSync Facilities] Building selected:', buildingId, '| Campus:', campusKey);
    }

    /* ══════════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════════ */
    function init() {
        /* Resolve DOM refs */
        campusView           = document.getElementById('fcty-campus-view');
        buildingView         = document.getElementById('fcty-building-view');
        carouselInner        = document.getElementById('fcty-carousel-inner');
        dotsWrap             = document.getElementById('fcty-carousel-dots');
        prevBtn              = document.getElementById('fcty-prev');
        nextBtn              = document.getElementById('fcty-next');
        carouselWrap         = document.getElementById('fcty-carousel-wrap');
        breadcrumbBack       = document.getElementById('fcty-breadcrumb-back');
        breadcrumbCampusLabel = document.getElementById('fcty-breadcrumb-campus');
        buildingTitle        = document.getElementById('fcty-building-title');

        /* Guard — element not found means we're on a different page */
        if (!campusView) return;

        /* ── Campus card clicks ─────────────────────────────────── */
        document.querySelectorAll('[data-fcty-campus]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.preventDefault();
                showBuildingView(this.dataset.fctyCampus);
            });
        });

        /* ── Breadcrumb / back ──────────────────────────────────── */
        breadcrumbBack.addEventListener('click', function (e) {
            e.preventDefault();
            showCampusView();
        });

        /* ── Carousel arrows ────────────────────────────────────── */
        prevBtn.addEventListener('click', function () {
            prevSlide();
            resetAutoSlide();
        });
        nextBtn.addEventListener('click', function () {
            nextSlide();
            resetAutoSlide();
        });

        /* ── Keyboard nav (arrow keys when carousel is focused) ─── */
        carouselWrap.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft')  { prevSlide(); resetAutoSlide(); }
            if (e.key === 'ArrowRight') { nextSlide(); resetAutoSlide(); }
        });

        /* ── Pause auto-slide on hover ──────────────────────────── */
        carouselWrap.addEventListener('mouseenter', stopAutoSlide);
        carouselWrap.addEventListener('mouseleave', function () {
            if (activeCampusKey) startAutoSlide();
        });

        /* ── "Select Building" button clicks (event delegation) ─── */
        carouselInner.addEventListener('click', function (e) {
            var btn = e.target.closest('.fcty-slide-btn');
            if (!btn) return;
            onBuildingSelected(btn.dataset.buildingId, activeCampusKey);
        });

        /* ── Reset to campus view when the Facilities tab loses focus ── */
        var panelRooms = document.getElementById('panel-rooms');
        if (panelRooms && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.type === 'attributes' && m.attributeName === 'class') {
                        if (!panelRooms.classList.contains('active')) {
                            /* Panel deactivated — silently reset without animation */
                            stopAutoSlide();
                            buildingView.style.display = 'none';
                            campusView.style.display   = '';
                            activeCampusKey = null;
                        }
                    }
                });
            });
            observer.observe(panelRooms, { attributes: true });
        }
    }

    /* Run after DOM is ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());