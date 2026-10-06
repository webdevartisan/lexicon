/**
 * Counts a page view on public pages, reports how long it was read and how fast
 * it loaded, and notes shares and clicks on links to other sites and on downloads.
 * The server resolves the page and decides whether anything counts.
 */
(function () {
    'use strict';

    // Automated browsers announce themselves here, whatever user agent they send.
    if (navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || navigator.webdriver === true) {
        return;
    }

    var IDLE_AFTER_MS = 30000;
    var TICK_MS = 1000;
    // Added to a related link as it is followed, read and removed by the page it opens, like a campaign tag.
    var VIA_RELATED = 'via-related';

    var SHARE_SITES = [
        { host: /(^|\.)(twitter|x)\.com$/, path: /^\/intent\//, network: 'x' },
        { host: /(^|\.)facebook\.com$/, path: /^\/sharer/, network: 'facebook' },
        { host: /(^|\.)linkedin\.com$/, path: /^\/sharing\//, network: 'linkedin' }
    ];

    var script = document.currentScript;
    var notFound = script !== null && script.hasAttribute('data-not-found');
    var searchResults = script !== null && script.hasAttribute('data-search-results')
        ? parseInt(script.getAttribute('data-search-results'), 10)
        : NaN;

    var viewId = null;
    var loadViewId = null;
    var engagedMs = 0;
    var maxDepth = 0;
    var lastActivity = 0;
    var timer = null;
    var vitals = {};

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
            if (!isNaN(searchResults)) {
                out.sr = searchResults;
            }
        }

        return out;
    }

    /**
     * Whether the reader came through another post's related links. The mark comes
     * off the address straight away, so it is never bookmarked or shared.
     */
    function takeVia() {
        if (location.hash !== '#' + VIA_RELATED) {
            return null;
        }

        history.replaceState(history.state, '', location.pathname + location.search);

        return 'related';
    }

    function sendView() {
        viewId = newViewId();
        engagedMs = 0;
        maxDepth = depth();
        lastActivity = Date.now();

        var body = Object.assign({ v: viewId, p: location.pathname, r: document.referrer }, campaign());
        var via = takeVia();
        if (via !== null) {
            body.via = via;
        }
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
            console.debug('Insights beacon not sent:', error);
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

        var body = { v: viewId, s: Math.round(engagedMs / 1000), d: maxDepth };

        // Page speed describes the load, so a page restored from the back button doesn't report it again.
        if (viewId === loadViewId) {
            Object.assign(body, vitals);
        }

        navigator.sendBeacon('/traffic/engage', new Blob([JSON.stringify(body)], { type: 'text/plain' }));
    }

    function sendEvent(name, props) {
        if (viewId === null || notFound) {
            return;
        }

        var body = JSON.stringify({ v: viewId, n: name, p: props });
        navigator.sendBeacon('/traffic/event', new Blob([body], { type: 'text/plain' }));
    }

    /**
     * Google's page speed measures, the way web-vitals reads them: the largest paint,
     * the worst interaction, the worst burst of layout shifts, and the first byte.
     */
    function watchPageSpeed() {
        if (typeof PerformanceObserver !== 'function') {
            return;
        }

        var navigation = performance.getEntriesByType('navigation')[0];
        var activated = navigation && navigation.activationStart ? navigation.activationStart : 0;
        if (navigation) {
            vitals.ttfb = Math.max(0, Math.round(navigation.responseStart - activated));
        }

        observe('largest-contentful-paint', function (entry) {
            vitals.lcp = Math.max(0, Math.round(entry.startTime - activated));
        });

        observe('event', function (entry) {
            if (entry.interactionId) {
                vitals.inp = Math.max(vitals.inp || 0, Math.round(entry.duration));
            }
        }, { durationThreshold: 40 });

        var burst = 0;
        var burstStart = 0;
        var lastShift = 0;
        observe('layout-shift', function (entry) {
            if (entry.hadRecentInput) {
                return;
            }

            if (burst > 0 && entry.startTime - lastShift < 1000 && entry.startTime - burstStart < 5000) {
                burst += entry.value;
            } else {
                burst = entry.value;
                burstStart = entry.startTime;
            }

            lastShift = entry.startTime;
            vitals.cls = Math.max(vitals.cls || 0, Math.round(burst * 10000) / 10000);
        });
    }

    function observe(type, onEntry, options) {
        // Browsers that don't know an entry type throw here, and simply report nothing for it.
        try {
            new PerformanceObserver(function (list) {
                list.getEntries().forEach(onEntry);
            }).observe(Object.assign({ type: type, buffered: true }, options || {}));
        } catch (error) {
            console.debug('Insights cannot measure ' + type + ' here:', error);
        }
    }

    function shareNetwork(url) {
        for (var i = 0; i < SHARE_SITES.length; i++) {
            if (SHARE_SITES[i].host.test(url.hostname) && SHARE_SITES[i].path.test(url.pathname)) {
                return SHARE_SITES[i].network;
            }
        }

        return null;
    }

    /**
     * What a link click means: a share, another site's host, or the file name of a
     * same-site file. Never the full address. A related link is marked on its way out.
     */
    function linkEvent(link) {
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
            var network = shareNetwork(url);

            return network !== null
                ? { n: 'share', p: { network: network } }
                : { n: 'outbound', p: { host: url.hostname } };
        }

        if (link.closest('[data-insights-related]')) {
            url.hash = VIA_RELATED;
            link.href = url.href;
        }

        var file = url.pathname.split('/').pop();
        if (link.hasAttribute('download') || (/\.[a-z0-9]{2,5}$/i.test(file) && !/\.(html?|php)$/i.test(file))) {
            return { n: 'download', p: { file: file } };
        }

        return null;
    }

    function noteClick(event) {
        if (viewId === null || notFound || !event.target.closest) {
            return;
        }

        if (event.type === 'click' && event.target.closest('[data-copy-link]')) {
            sendEvent('share', { network: 'copy' });

            return;
        }

        var link = event.target.closest('a[href]');
        var found = link ? linkEvent(link) : null;

        if (found !== null) {
            sendEvent(found.n, found.p);
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
        loadViewId = viewId;
        if (!notFound) {
            watchPageSpeed();
        }
    }

    // A prerendered page may never be shown, so it counts once it is.
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', start, { once: true });
    } else {
        start();
    }
})();
