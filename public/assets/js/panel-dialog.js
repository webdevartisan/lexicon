/**
 * The site modal. A link with data-panel opens its page in it, and
 * data-panel="wide" gives the wider frame the account pages use. The page is
 * fetched from the link's own address with X-Panel, so the server answers with
 * the same page in its bare panel layout. Which pages can do that is decided by
 * the routes, not here.
 *
 * Inside the modal, links with data-panel-link and POST forms stay in it. An
 * answer without X-Panel (sign-out, a redirect away from the panel pages) is
 * followed by the whole window.
 */
(function () {
    'use strict';

    var dialog = document.querySelector('[data-panel-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    // It is printed inside the header's account menu; out there the header's styles would reach the panels.
    document.body.appendChild(dialog);

    var body = dialog.querySelector('[data-panel-body]');
    var panelUrl = '';
    var panelLang = '';

    function fail() {
        body.textContent = dialog.getAttribute('data-panel-error');
    }

    // Script tags put in with innerHTML never run. A panel's script runs the first
    // time it shows up and sets itself up then; after that it hears panel:loaded.
    function runNewScripts() {
        body.querySelectorAll('script[src]').forEach(function (tag) {
            var loaded = Array.prototype.some.call(document.scripts, function (script) {
                return script.src === tag.src && !dialog.contains(script);
            });

            if (!loaded) {
                var script = document.createElement('script');
                script.src = tag.src;
                document.head.appendChild(script);
            }
        });
    }

    function show(response, html) {
        if (!response.ok) {
            fail();
            return;
        }

        if (!response.headers.has('X-Panel')) {
            window.location.href = response.url;
            return;
        }

        body.innerHTML = html;

        // A saved language change: the page behind the modal is still in the old one.
        var lang = body.querySelector('.panel-page').lang;
        if (panelLang && lang !== panelLang) {
            window.location.reload();
            return;
        }

        panelLang = lang;
        panelUrl = response.url;
        body.scrollTop = 0;

        var heading = body.querySelector('h1, h2');
        if (heading) {
            dialog.setAttribute('aria-label', heading.textContent.trim());
        }

        document.dispatchEvent(new CustomEvent('panel:loaded', { detail: body }));
        runNewScripts();
    }

    function request(url, init) {
        init = init || {};
        init.credentials = 'same-origin';
        // Not X-Requested-With: that asks controllers for JSON, and a panel is HTML.
        init.headers = { 'X-Panel': '1' };
        // A rejected form is redirected back to its Referer, so name the open panel
        // to keep the errors in the modal instead of moving the page behind it.
        if (panelUrl) {
            init.referrer = panelUrl;
        }
        body.setAttribute('aria-busy', 'true');

        return fetch(url, init)
            .then(function (response) {
                return response.text().then(function (html) {
                    show(response, html);
                });
            })
            .catch(fail)
            .then(function () {
                body.removeAttribute('aria-busy');
            });
    }

    function open(link) {
        // A fresh opening must not flash whatever was showing when it last closed.
        if (!dialog.open) {
            body.textContent = '';
            panelUrl = '';
            dialog.classList.toggle('is-wide', link.getAttribute('data-panel') === 'wide');
            dialog.showModal();
        }

        request(link.href);
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || !e.target.closest) {
            return;
        }

        var link = e.target.closest('a[data-panel], a[data-panel-link]');
        if (!link || link.target) {
            return;
        }

        if (body.contains(link)) {
            e.preventDefault();
            request(link.href);
        } else if (link.hasAttribute('data-panel')) {
            e.preventDefault();
            open(link);
        }
    });

    dialog.addEventListener('click', function (e) {
        // The dialog element itself is only hit on the backdrop.
        if (e.target === dialog || e.target.closest('[data-panel-close]')) {
            dialog.close();
        }
    });

    body.addEventListener('submit', function (e) {
        var form = e.target;

        // A file download (the data export) has to post normally to keep its response.
        if (e.defaultPrevented || form.method.toLowerCase() !== 'post' || form.hasAttribute('data-panel-native')) {
            return;
        }

        e.preventDefault();
        request(form.action, { method: 'POST', body: new FormData(form, e.submitter) });
    });
})();
