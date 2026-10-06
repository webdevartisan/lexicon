/**
 * Moving between Insights pages keeps the chosen range, comparison and filters.
 * The sidebar is cached without them, so its links to the other pages are
 * given the current query here.
 */
(function () {
    'use strict';

    var query = window.location.search;
    var match = window.location.pathname.match(/^(.*\/insights)(\/|$)/);
    if (!query || !match) {
        return;
    }

    var root = match[1];

    document.querySelectorAll('[data-nav-group] a[href], [data-insights-page-link]').forEach(function (link) {
        var url = new URL(link.getAttribute('href'), window.location.origin);
        var samePages = url.pathname === root || url.pathname.indexOf(root + '/') === 0;
        // Posts and exports take their own query; only the pages themselves follow along.
        var page = url.pathname.slice(root.length).replace(/^\//, '');
        if (!samePages || page.indexOf('/') !== -1 || page === 'export' || page === 'settings') {
            return;
        }

        url.search = query;
        link.setAttribute('href', url.pathname + url.search);
    });
})();
