/**
 * Progressive enhancement for the admin list tables.
 *
 * Sort headers, pagination links and filter controls are all plain HTML that
 * works on its own. This intercepts them, fetches the page the link already
 * points at, and swaps in just the table region.
 *
 * It deliberately re-fetches the whole page and picks the region out of the
 * response rather than calling a partial endpoint. That keeps one render path
 * per screen: whatever the server sends on a full load is exactly what shows up
 * here, so a sorted table can never drift from an unsorted one. The pages are
 * small and behind auth, so the extra bytes are cheaper than a second code path.
 */
(function () {
    'use strict';

    var REGION = '[data-table-region]';

    function region() {
        return document.querySelector(REGION);
    }

    /**
     * Re-run the icon and tooltip initialisers over freshly injected markup.
     * Injected `<i data-lucide>` placeholders and `[data-tooltip]` elements are
     * inert until these run. Both are safe to call again and both are optional:
     * tooltip.js is only loaded on some screens.
     */
    function reinit() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
        if (typeof window.initTooltip === 'function') {
            window.initTooltip();
        }
    }

    /**
     * Refresh the bits of chrome that live OUTSIDE the swapped region but still
     * depend on the query: the filter form's "Clear" button, mainly, which the
     * server only renders while a filter is active.
     *
     * Matched by name rather than document order so markup can move around.
     * Only innerHTML is replaced, so the surrounding element keeps its classes
     * and the filter selects themselves are never touched -- swapping those
     * would steal focus from the control the operator just used.
     */
    function syncOutsideRegion(doc) {
        document.querySelectorAll('[data-table-sync]').forEach(function (node) {
            var name = node.getAttribute('data-table-sync');
            var replacement = doc.querySelector('[data-table-sync="' + name + '"]');

            if (replacement) node.innerHTML = replacement.innerHTML;
        });
    }

    function load(url, push) {
        var target = region();
        if (!target) {
            window.location.href = url;
            return;
        }

        target.setAttribute('aria-busy', 'true');
        target.style.opacity = '0.45';

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelector(REGION);

                // A login redirect or an error page has no table region. Hand the
                // navigation back to the browser so the user sees what happened.
                if (!fresh) {
                    window.location.href = url;
                    return;
                }

                target.innerHTML = fresh.innerHTML;
                target.style.opacity = '';
                target.removeAttribute('aria-busy');

                syncOutsideRegion(doc);

                if (push) {
                    window.history.pushState({ tableUrl: url }, '', url);
                }

                reinit();
            })
            .catch(function () {
                // Never leave the table dimmed and stale: fall back to a real load.
                window.location.href = url;
            });
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-sort-link], [data-table-region] [data-page-link]');
        if (!link || !region()) return;

        // Let modified clicks open a new tab the way any other link would.
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

        e.preventDefault();
        load(link.href, true);
    });

    // Filter selects inside the table's own filter form: submit through fetch so
    // the page does not flash.
    document.addEventListener('change', function (e) {
        var control = e.target.closest('[data-table-filter] [data-auto-submit]');
        if (!control || !region()) return;

        var form = control.form;
        if (!form) return;

        // Stop blog-switcher.js from also firing a real form.submit() on this.
        e.stopImmediatePropagation();

        // Start from the current query rather than the form, so the active sort
        // survives a filter change. Then let the form's own fields win.
        var params = new URLSearchParams(window.location.search);
        var data = new FormData(form);

        Array.from(data.keys()).forEach(function (key) {
            params.delete(key);
        });

        data.forEach(function (value, key) {
            // Empty means "no filter"; keeping it makes for ugly, unshareable
            // URLs and defeats the "is any filter active?" checks server-side.
            if (value !== '') params.append(key, value);
        });

        params.delete('page');

        // Built from location.pathname, not form.action: the action attribute is
        // the unlocalised path, and pushing it would drop the /{locale} prefix.
        var query = params.toString();
        load(window.location.pathname + (query ? '?' + query : ''), true);
    }, true);

    window.addEventListener('popstate', function () {
        if (region()) load(window.location.href, false);
    });
})();
