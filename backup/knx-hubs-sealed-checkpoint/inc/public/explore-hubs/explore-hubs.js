/**
 * Kingdom Nexus — Explore Hubs (Minimal Premium + Casino Loader)
 * FINAL BUILD — Feb 2025
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const root = document.querySelector('#olc-explore-hubs');
    if (!root) return;

    /* ============================================================
       STATE
    ============================================================ */
    const state = {
        categoryId: null,
        categoryName: null,
        query: '',
        vendors: [],
        spotlights: [],
    };

    /* ============================================================
       DOM REFS
    ============================================================ */
    const $ = {
        search: root.querySelector('#hub-search'),
        searchSticky: root.querySelector('.search-sticky'),
        spotBox: root.querySelector('#spotlights-container'),
        spotSection: root.querySelector('.spot-wrap'),
        surpSection: root.querySelector('.surp-wrap'),
        grid: root.querySelector('#vendors-grid'),
        surpOverlay: root.querySelector('#surprise-overlay'),
        surpWinner: root.querySelector('#surprise-winner'),
        surpRotatorText: root.querySelector('#surprise-rotator-text'),
        surpTrigger: root.querySelector('#surprise-trigger'),
    };

    /* ============================================================
       INIT
    ============================================================ */
    function init() {
        bindEvents();
        loadVendors();
        renderSpotlights();
        initTempClosedModal();
        window.openSurpriseModal = openSurpriseModal;
    }

    /* ============================================================
       LOAD VENDORS
    ============================================================ */
    async function loadVendors() {
        try {
            const res = await fetch('/wp-json/knx/v1/explore-hubs');
            const data = await res.json();
            state.vendors = (data && data.hubs) || data || [];
            renderVendors();
        } catch (err) {
            console.error('Failed to load vendors:', err);
            $.grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:#ef4444;">
                <i class="fas fa-exclamation-triangle" style="font-size:48px;opacity:.5;"></i>
                <h3 style="margin:10px 0 6px;">Unable to load vendors</h3>
                <p>Please try again later.</p>
            </div>`;
        }
    }

    /* ============================================================
       EVENTS
    ============================================================ */
    function bindEvents() {
        /* SEARCH */
        if ($.search) {
            let t;
            $.search.addEventListener('input', function (e) {
                clearTimeout(t);
                t = setTimeout(() => {
                    state.query = e.target.value.trim().toLowerCase();
                    renderVendors();
                }, 200);
            });
        }

        /* CATEGORY CHIPS */
        root.addEventListener('click', function (e) {
            const chip = e.target.closest('.knx-mood-chip');
            if (!chip) return;

            const id = chip.dataset.categoryId;
            const name = chip.dataset.categoryName;

            if (state.categoryId === id) {
                state.categoryId = null;
                state.categoryName = null;
                chip.classList.remove('active');
            } else {
                root.querySelectorAll('.knx-mood-chip').forEach(c => c.classList.remove('active'));
                state.categoryId = id;
                state.categoryName = name;
                chip.classList.add('active');
            }
            renderVendors();
        });

        /* SCROLL COMPACT SEARCH */
        if ($.searchSticky) {
            const onScroll = () => {
                if (window.scrollY > 12) $.searchSticky.classList.add('compact');
                else $.searchSticky.classList.remove('compact');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        /* SURPRISE TRIGGER */
        if ($.surpTrigger) {
            $.surpTrigger.addEventListener('click', openSurpriseModal);
            $.surpTrigger.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openSurpriseModal();
                }
            });
        }

        /* CLOSE SURPRISE OVERLAY */
        root.querySelectorAll('[data-surp-close]').forEach(el =>
            el.addEventListener('click', closeSurpriseModal)
        );

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSurpriseModal();
        });
    }

    /* ============================================================
       FILTERS
    ============================================================ */
    function matchesQuery(v) {
        const q = state.query;
        if (!q) return true;
        const txt = (
            v.name + ' ' + (v.category_name || '') + ' ' + (v.tagline || '') + ' ' + (v.address || '')
        ).toLowerCase();
        return txt.includes(q);
    }

    function matchesCategory(v) {
        if (!state.categoryId) return true;
        return String(v.category_id || v.category) === String(state.categoryId);
    }

    function updateSectionsVisibility() {
        const filtering = !!state.query || !!state.categoryId;
        if ($.spotSection) $.spotSection.style.display = filtering ? 'none' : '';
        if ($.surpSection) $.surpSection.style.display = filtering ? 'none' : '';
    }

    /* ============================================================
       RENDER VENDORS
    ============================================================ */
    function renderVendors() {
        updateSectionsVisibility();

        const filtered = state.vendors.filter(v =>
            matchesQuery(v) && matchesCategory(v)
        );

        if (!filtered.length && (state.query || state.categoryId)) {
            $.grid.innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:#999;">
                    <i class="fas fa-search" style="font-size:48px;opacity:.35;"></i>
                    <h3>No vendors found</h3>
                    <p>Try adjusting your search.</p>
                </div>`;
            return;
        }

        $.grid.innerHTML = '';
        filtered.forEach(v => $.grid.appendChild(hubCard(v)));
    }

    /* ============================================================
       SPOTLIGHTS
    ============================================================ */
    async function renderSpotlights() {
        try {
            const res = await fetch('/wp-json/knx/v1/explore-hubs?featured=1');
            const data = await res.json();
            state.spotlights = (data && data.hubs) || [];
        } catch {
            state.spotlights = [];
        }

        $.spotBox.innerHTML = '';

        if (!state.spotlights.length) {
            $.spotBox.innerHTML = `<p style="padding:20px;text-align:center;color:#999;">No featured hubs yet</p>`;
            return;
        }

        state.spotlights.forEach(h => {
            const el = hubCard(h, { spotlight: true });
            el.classList.add('spot-card');
            $.spotBox.appendChild(el);
        });
    }

    /* ============================================================
       HUB CARD
    ============================================================ */
    function hubCard(v, opts = {}) {
        const spotlight = !!opts.spotlight;
        let slug = v.slug || (v.name || '').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');

        const image =
            v.image || v.hero_img || v.logo_url ||
            'https://via.placeholder.com/700x450?text=' + encodeURIComponent(v.name);

        const statusText = v.is_open ? 'Open' : (v.is_temp_closed ? 'Temp' : 'Closed');
        const statusClass = v.is_open ? 'open' : (v.is_temp_closed ? 'temp-closed' : 'closed');

        const el = document.createElement('article');
        el.className = 'hub-card';
        el.innerHTML = `
            <div class="hub-img">
                <img src="${image}">
                ${spotlight 
                    ? `<div class="spot-heart"><i class="fas fa-heart"></i></div>`
                    : `<span class="hub-status-pill ${statusClass}">${statusText}</span>`
                }
            </div>
            <div class="hub-bottom">
                <div class="hub-main-name"><p class="hub-name">${v.name}</p></div>
                <div class="hub-main-hours">
                    <p class="hub-hours">${v.hours_today || 'Hours unavailable'}</p>
                </div>
            </div>
        `;

        el.addEventListener('click', function () {
            if (v.is_temp_closed) return showTempClosedModal(v);
            if (!v.is_open) return showClosedModal(v);
            window.location.href = '/' + slug;
        });

        return el;
    }

    /* ============================================================
       RANDOMIZER (CASINO LOADER)
    ============================================================ */
    function openSurpriseModal() {
        if (!$.surpOverlay) return;

        /* Animate helper text */
        const msgs = [
            'Rolling the dice...',
            'Picking something delicious...',
            'Let’s see what destiny tastes like...',
            'Good things take time...'
        ];

        if ($.surpRotatorText) {
            const msg = msgs[Math.floor(Math.random() * msgs.length)];
            $.surpRotatorText.textContent = msg;
            $.surpRotatorText.classList.add('surp-rotator-anim');
            setTimeout(() => $.surpRotatorText.classList.remove('surp-rotator-anim'), 350);
        }

        /* Show modal */
        $.surpOverlay.classList.remove('hidden');
        $.surpOverlay.setAttribute('aria-hidden', 'false');

        startCasinoLoading();
    }

    function closeSurpriseModal() {
        if (!$.surpOverlay) return;
        $.surpOverlay.classList.add('hidden');
        $.surpOverlay.setAttribute('aria-hidden', 'true');
    }

    /* ============================================================
       CASINO LOADING ANIMATION
    ============================================================ */
    function startCasinoLoading() {
        const winnerArea = $.surpWinner;
        if (!winnerArea) return;

        const box = winnerArea.querySelector('.aspect-16x9');
        const body = winnerArea.querySelector('.winner-body');

        box.innerHTML = `
            <div class="casino-loader">
                <span class="slot slot-1">🍔</span>
                <span class="slot slot-2">🍕</span>
                <span class="slot slot-3">🌮</span>
            </div>
        `;

        body.innerHTML = `
            <div style="margin-top:22px;font-size:18px;color:#555;">Finding a tasty winner...</div>
        `;

        /* Animate emojis like a slot machine */
        const emojis = ['🍔','🍕','🌮','🥗','🍜','🍣','🌯','🍩','🍤','🥙'];
        const s1 = box.querySelector('.slot-1');
        const s2 = box.querySelector('.slot-2');
        const s3 = box.querySelector('.slot-3');

        let interval1, interval2, interval3;

        interval1 = setInterval(() => s1.textContent = emojis[Math.floor(Math.random()*emojis.length)], 90);
        interval2 = setInterval(() => s2.textContent = emojis[Math.floor(Math.random()*emojis.length)], 75);
        interval3 = setInterval(() => s3.textContent = emojis[Math.floor(Math.random()*emojis.length)], 60);

        /* After delay → final winner */
        setTimeout(() => {
            clearInterval(interval1);
            clearInterval(interval2);
            clearInterval(interval3);
            showRandomWinner();
        }, 1500);
    }

    function showRandomWinner() {
        let pool = state.vendors.filter(v => v.is_open);
        if (!pool.length) pool = state.spotlights.filter(v => v.is_open);
        if (!pool.length) return showNoWinner();

        const pick = pool[Math.floor(Math.random()*pool.length)];
        const img = pick.image || pick.hero_img || pick.logo_url;
        const slug = pick.slug;

        const box = $.surpWinner.querySelector('.aspect-16x9');
        const body = $.surpWinner.querySelector('.winner-body');

        box.innerHTML = `<img src="${img}" style="width:100%;height:100%;object-fit:cover;">`;

        body.innerHTML = `
            <span class="win-medal">🎉</span>
            <h3>${pick.name}</h3>
            <p>${pick.tagline || 'Your lucky pick!'}</p>

            <div class="win-actions">
                <a class="btn btn-amber" href="/${slug}">
                    <i class="fas fa-utensils"></i> Open Menu
                </a>

                <button class="btn-outline-amber" id="try-random-again">Try Again</button>
            </div>
        `;

        body.querySelector('#try-random-again').addEventListener('click', () => {
            closeSurpriseModal();
            setTimeout(openSurpriseModal, 150);
        });
    }

    function showNoWinner() {
        const box = $.surpWinner.querySelector('.aspect-16x9');
        const body = $.surpWinner.querySelector('.winner-body');

        box.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:48px;">😴</div>`;
        body.innerHTML = `
            <h3>No open hubs</h3>
            <p>Try again later!</p>
            <div class="win-actions">
                <button class="btn-outline-amber" data-surp-close>Close</button>
            </div>
        `;

        body.querySelector('[data-surp-close]').addEventListener('click', closeSurpriseModal);
    }

    /* ============================================================
       TEMP CLOSED MODAL (unchanged)
    ============================================================ */
    function showTempClosedModal(hub) {
        const modal = document.getElementById('tempClosedModal');
        if (!modal) return;

        const body = modal.querySelector('.knx-temp-modal-content');
        body.innerHTML = `
            <div style="padding:30px;text-align:center;">
                <h3>${hub.name}</h3>
                <div style="font-size:48px;margin:10px 0;">⏰</div>
                <p style="font-size:18px;color:#f59e0b;">Currently Closed</p>
                <p style="margin-top:12px;">This hub will reopen later.</p>
                <button class="knx-temp-modal-close" style="margin-top:20px;background:#0b793a;color:#fff;padding:12px 20px;border-radius:8px;">Close</button>
            </div>
        `;

        modal.style.display = 'flex';

        body.querySelector('.knx-temp-modal-close').onclick = () => modal.style.display = 'none';
        modal.querySelector('.knx-temp-modal-backdrop').onclick = () => modal.style.display = 'none';
    }

    function showClosedModal(hub) {
        alert(hub.name + ' is currently closed.');
    }

    function initTempClosedModal() {
        const modal = document.getElementById('tempClosedModal');
        if (!modal) return;
        // no-op
    }

    /* ============================================================
       INIT
    ============================================================ */
    init();
});
