/**
 * Counts a page view on public pages, reports how long it was read, and notes
 * clicks on links to other sites and on downloads. The server resolves the page
 * and decides whether anything counts.
 */
(function () {
    'use strict';

    // Automated browsers announce themselves here, whatever user agent they send.
    if (navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || navigator.webdriver === true) {
        return;
    }

    var IDLE_AFTER_MS = 30000;
    var TICK_MS = 1000;

    var script = document.currentScript;
    var notFound = script !== null && script.hasAttribute('data-not-found');

    var viewId = null;
    var engagedMs = 0;
    var maxDepth = 0;
    var lastActivity = 0;
    var timer = null;

    function newViewId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID().replace(/-/g, '');
        }

        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);

        return Array.prototype.map.call(bytes, function (b) {
            return ('0' + b.toString(16)).slice(-2);
        }).join('');
    }

    function campaign() {
        var query = new URLSearchParams(location.search);
        var fields = { us: 'utm_source', um: 'utm_medium', uc: 'utm_campaign' };
        var out = {};

        Object.keys(fields).forEach(function (key) {
            var value = query.get(fields[key]);
            if (value) {
                out[key] = value.slice(0, 100);
            }
        });

        // Discover is the only page whose search is worth knowing about.
        var search = query.get('q');
        if (search && /(^|\/)discover\/?$/.test(location.pathname)) {
            out.q = search.slice(0, 100);
        }

        return out;
    }

    function sendView() {
        viewId = newViewId();
        engagedMs = 0;
        maxDepth = depth();
        lastActivity = Date.now();

        var body = Object.assign({ v: viewId, p: location.pathname, r: document.referrer }, campaign());
        if (notFound) {
            body.nf = 1;
        }

        // fetch rather than sendBeacon so the analytics cookie in the answer is kept.
        fetch('/traffic/hit', {
            method: 'POST',
            body: JSON.stringify(body),
            headers: { 'Content-Type': 'text/plain' },
            credentials: 'same-origin',
            keepalive: true
        }).catch(function (error) {
            console.debug('Traffic beacon not sent:', error);
        });

        if (!notFound) {
            startClock();
        }
    }

    function depth() {
        var doc = document.documentElement;
        var seen = window.scrollY + window.innerHeight;

        return doc.scrollHeight > 0 ? Math.min(100, Math.round(seen / doc.scrollHeight * 100)) : 100;
    }

    function markActive() {
        lastActivity = Date.now();
        maxDepth = Math.max(maxDepth, depth());
    }

    function startClock() {
        if (timer !== null) {
            return;
        }

        timer = window.setInterval(function () {
            if (document.visibilityState === 'visible' && Date.now() - lastActivity < IDLE_AFTER_MS) {
                engagedMs += TICK_MS;
            }
        }, TICK_MS);
    }

    function sendEngagement() {
        if (viewId === null || notFound) {
            return;
        }

        var body = JSON.stringify({ v: viewId, s: Math.round(engagedMs / 1000), d: maxDepth });
        navigator.sendBeacon('/traffic/engage', new Blob([body], { type: 'text/plain' }));
    }

    /**
     * Another site's host, or the file name of a same-site file. Never the full address.
     */
    function clickTarget(link) {
        var url;

        try {
            url = new URL(link.href, location.href);
        } catch (error) {
            return null;
        }

        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return null;
        }

        if (url.host !== location.host) {
            return { k: 'outbound', t: url.hostname };
        }

        var file = url.pathname.split('/').pop();
        if (link.hasAttribute('download') || (/\.[a-z0-9]{2,5}$/i.test(file) && !/\.(html?|php)$/i.test(file))) {
            return { k: 'download', t: file };
        }

        return null;
    }

    function noteClick(event) {
        if (viewId === null || notFound || !event.target.closest) {
            return;
        }

        var link = event.target.closest('a[href]');
        var target = link ? clickTarget(link) : null;

        if (target !== null) {
            target.v = viewId;
            navigator.sendBeacon('/traffic/click', new Blob([JSON.stringify(target)], { type: 'text/plain' }));
        }
    }

    function start() {
        ['scroll', 'keydown', 'pointerdown', 'pointermove', 'touchstart'].forEach(function (type) {
            window.addEventListener(type, markActive, { passive: true });
        });

        // Capture, so a link that stops the click from bubbling still counts. auxclick is the middle button.
        document.addEventListener('click', noteClick, true);
        document.addEventListener('auxclick', noteClick, true);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                sendEngagement();
            } else {
                lastActivity = Date.now();
            }
        });

        window.addEventListener('pagehide', sendEngagement);

        // Back and forward restore the page without rerunning this file. That is a fresh view.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                sendView();
            }
        });

        sendView();
    }

    // A prerendered page may never be shown, so it counts once it is.
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', start, { once: true });
    } else {
        start();
    }
})();
