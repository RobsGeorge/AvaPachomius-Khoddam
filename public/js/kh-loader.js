/*!
 * kh-loader.js — Khoddam page navigation loader (Orbit).
 * Shows the full-screen overlay the moment an internal navigation or form
 * submit starts, so no page transition is a blank white flash. The overlay
 * is markup in layouts/partials/page-loader.blade.php.
 *
 * Public API: window.khLoader.show() / .hide()  (also usable for AJAX flows).
 * Opt out per element with the `data-no-loader` attribute.
 */
(function () {
    'use strict';

    var el = document.getElementById('kh-page-loader');
    if (!el) return;

    var safety = null;

    function show() {
        if (el.classList.contains('is-active')) return;
        el.classList.add('is-active');
        el.setAttribute('aria-hidden', 'false');
        clearTimeout(safety);
        // If the navigation is cancelled (download response, blocked unload,
        // confirm dialog declined) nothing hides us — so self-heal.
        safety = setTimeout(hide, 12000);
    }

    function hide() {
        el.classList.remove('is-active');
        el.setAttribute('aria-hidden', 'true');
        clearTimeout(safety);
    }

    window.khLoader = { show: show, hide: hide };

    var SKIP_PROTO = /^(mailto:|tel:|sms:|javascript:|blob:|data:)/i;

    document.addEventListener('click', function (e) {
        // Bubble phase: respect anything a prior handler already cancelled
        // (Bootstrap dropdowns, Alpine toggles, SweetAlert confirms, etc.).
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var a = e.target.closest('a[href]');
        if (!a || a.hasAttribute('data-no-loader') || a.hasAttribute('download')) return;
        if (a.target && a.target !== '_self') return;

        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || SKIP_PROTO.test(href)) return;

        var url;
        try { url = new URL(a.href, location.href); } catch (_) { return; }
        if (url.origin !== location.origin) return; // external host
        // Same-document hash jump — no navigation.
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;

        show();
    }, false);

    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        var form = e.target;
        if (!form || form.hasAttribute('data-no-loader')) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'dialog') return;
        if (form.target && form.target !== '_self') return;
        show();
    }, false);

    // A fresh document loads with the overlay inactive by default; these hide
    // it when returning via the bfcache (back/forward) where it was left active.
    window.addEventListener('pageshow', hide);
    window.addEventListener('pagehide', hide);
})();
