/**
 * Portal-wide CSRF heal for idle tabs and bfcache restores.
 * Loaded by every layout that ships a csrf-token meta tag.
 */
(function () {
    'use strict';

    var endpoint = '/csrf-token';
    var refreshing = null;
    var retried = new WeakSet();

    function metaEl() {
        return document.querySelector('meta[name="csrf-token"]');
    }

    function currentToken() {
        return metaEl()?.getAttribute('content') || '';
    }

    function applyToken(token) {
        if (!token) {
            return;
        }

        var meta = metaEl();
        if (meta) {
            meta.setAttribute('content', token);
        }

        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });

        window.dispatchEvent(new CustomEvent('csrf:refreshed', { detail: { token: token } }));
    }

    function refreshCsrf() {
        if (refreshing) {
            return refreshing;
        }

        refreshing = fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('csrf refresh failed');
                }
                return response.json();
            })
            .then(function (data) {
                applyToken(data.token);
                return data.token;
            })
            .finally(function () {
                refreshing = null;
            });

        return refreshing;
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshCsrf().catch(function () {});
        }
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            refreshCsrf().catch(function () {});
        }
    });

    var originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init ? Object.assign({}, init) : {};
        var requestKey = input;

        return originalFetch(input, init).then(function (response) {
            if (response.status !== 419 || retried.has(requestKey)) {
                return response;
            }

            var url = typeof input === 'string'
                ? input
                : (input && input.url) || '';

            if (url.indexOf('/csrf-token') !== -1) {
                return response;
            }

            retried.add(requestKey);

            return refreshCsrf()
                .then(function (token) {
                    var headers = new Headers(init.headers || undefined);
                    headers.set('X-CSRF-TOKEN', token);
                    headers.set('Accept', headers.get('Accept') || 'application/json');

                    var retryInit = Object.assign({}, init, { headers: headers });
                    return originalFetch(input, retryInit);
                })
                .catch(function () {
                    return response;
                });
        });
    };

    window.KhoddamCsrf = {
        refresh: refreshCsrf,
        apply: applyToken,
        token: currentToken,
    };
})();
