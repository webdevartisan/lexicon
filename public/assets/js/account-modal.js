/**
 * The account dialog.
 *
 * Triggers are ordinary links to /account/{page} carrying data-account-open.
 * Without this script they navigate to the full page. With it, the link opens
 * the dialog and the panel is fetched from the same URL with X-Account-Panel,
 * so the page and the dialog render one partial.
 *
 * Forms inside the panel submit through fetch. fetch keeps the custom header
 * across the post-redirect GET, so a save lands on the panel again. A redirect
 * that leaves /account (sign-out, deletion) is followed by the whole window.
 */
(function () {
    'use strict';

    var dialog = document.querySelector('[data-account-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var body = dialog.querySelector('[data-account-body]');
    // two-factor and password are reached from Security and keep its menu item lit
    var pagePattern = /\/account\/(profile|public|social|preferences|notifications|security\/two-factor|security\/password|security|delete)(?:\/|$)/;
    var menuKey = { 'security/two-factor': 'security', 'security/password': 'security' };
    var scripts = {};
    var panelUrl = '';

    function loadScript(src) {
        if (!scripts[src]) {
            scripts[src] = new Promise(function (resolve, reject) {
                var tag = document.createElement('script');
                tag.src = src;
                tag.onload = resolve;
                tag.onerror = reject;
                document.head.appendChild(tag);
            });
        }

        return scripts[src];
    }

    function pageOf(url) {
        var match = pagePattern.exec(new URL(url, window.location.href).pathname);

        return match ? match[1] : null;
    }

    function isAccountUrl(url) {
        return new URL(url, window.location.href).pathname.indexOf('/account') !== -1;
    }

    function markCurrent(page) {
        dialog.querySelectorAll('[data-account-page]').forEach(function (button) {
            if (button.getAttribute('data-account-page') === (menuKey[page] || page)) {
                button.setAttribute('aria-current', 'page');
            } else {
                button.removeAttribute('aria-current');
            }
        });
    }

    function show(response, html) {
        // A redirect out of /account is not a panel: let the whole window go there.
        if (response.redirected && !isAccountUrl(response.url)) {
            window.location.href = response.url;
            return;
        }

        // Saving a new language redirects under the new locale prefix; the page behind
        // the dialog is still in the old one, so move it to the same path in the new one.
        var current = /^\/([a-z]{2,5})(?=\/|$)/.exec(window.location.pathname);
        var next = /^\/([a-z]{2,5})(?=\/|$)/.exec(new URL(response.url).pathname);
        if (response.redirected && current && next && current[1] !== next[1]) {
            window.location.href = '/' + next[1] + window.location.pathname.slice(current[0].length) + window.location.search;
            return;
        }

        if (!response.ok) {
            body.textContent = body.getAttribute('data-error') || 'Could not load this right now.';
            return;
        }

        body.innerHTML = html;
        panelUrl = response.url;
        var page = pageOf(response.url);
        markCurrent(page);
        body.scrollTop = 0;

        // The profile panel brings its own script, loaded once on first use.
        if (page === 'profile') {
            loadScript('/assets/js/account-profile.js').then(function () {
                window.AccountProfile.init(body);
            });
        }
    }

    function request(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = { 'X-Account-Panel': '1', 'X-Requested-With': 'XMLHttpRequest' };
        // A rejected form is redirected back to its Referer; naming the open panel keeps
        // the errors in the dialog instead of sending the page behind it somewhere else.
        if (panelUrl) {
            options.referrer = panelUrl;
        }
        body.setAttribute('aria-busy', 'true');

        return fetch(url, options)
            .then(function (response) {
                return response.text().then(function (html) {
                    show(response, html);
                });
            })
            .catch(function () {
                body.textContent = 'Could not load this right now.';
            })
            .then(function () {
                body.removeAttribute('aria-busy');
            });
    }

    function open(url) {
        if (!dialog.open) {
            dialog.showModal();
        }

        return request(url);
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest ? e.target.closest('[data-account-open]') : null;

        if (trigger && !(e.metaKey || e.ctrlKey || e.shiftKey || e.button)) {
            e.preventDefault();
            open(trigger.getAttribute('href'));
        }
    });

    dialog.addEventListener('click', function (e) {
        // The dialog element itself is only hit on the backdrop.
        if (e.target === dialog || e.target.closest('[data-account-close]')) {
            dialog.close();
            return;
        }

        var tab = e.target.closest('[data-account-page]');
        if (tab) {
            open(dialog.getAttribute('data-account-base') + tab.getAttribute('data-account-page'));
            return;
        }

        var link = e.target.closest('a[href]');
        if (link && body.contains(link) && pageOf(link.href) && !link.target && !(e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            request(link.href);
        }
    });

    body.addEventListener('submit', function (e) {
        var form = e.target;

        // A file download (the data export) has to post normally to keep its response.
        if (form.method.toLowerCase() !== 'post' || form.hasAttribute('data-account-native')) {
            return;
        }

        e.preventDefault();
        request(form.action, { method: 'POST', body: new FormData(form, e.submitter) });
    });

    // The first trigger's own address gives the base, so a locale prefix carries over.
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest ? e.target.closest('[data-account-open]') : null;

        if (trigger) {
            dialog.setAttribute('data-account-base', new URL(trigger.getAttribute('href'), window.location.href).pathname.replace(/[^/]+$/, ''));
        }
    }, true);
})();
