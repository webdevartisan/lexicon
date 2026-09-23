/**
 * The platform's disclosure menus: the masthead account menu, and anything
 * else that opens the same dark dropdown.
 *
 * Deliberately not the WAI-ARIA menu button pattern. The contents are ordinary
 * navigation links, so they are reached with Tab in DOM order like any other
 * links. role="menu" would promise arrow-key navigation and a single tab stop,
 * which is the wrong contract for a list of links and the usual reason such
 * menus end up half implemented.
 *
 * One script for every theme and for the Lexicon front, so the menu behaves
 * identically wherever a reader meets it.
 *
 * A list carrying data-panel-url fills itself from that URL the first time it
 * opens. That is how the masthead bell shows notifications without every page
 * on the site paying for the query that builds them.
 */
(function () {
    'use strict';

    var menus = Array.prototype.slice.call(document.querySelectorAll('[data-platform-menu]'));
    if (menus.length === 0) {
        return;
    }

    function parts(menu) {
        return {
            toggle: menu.querySelector('[data-platform-menu-toggle]'),
            list: menu.querySelector('[data-platform-menu-list]')
        };
    }

    /**
     * Fill a lazy list from its own URL, once.
     *
     * A failure says so and offers the page the trigger already points at,
     * rather than leaving the reader looking at a panel that never fills.
     */
    function load(list) {
        var url = list.getAttribute('data-panel-url');
        if (!url || list.hasAttribute('data-panel-loaded')) {
            return;
        }

        list.setAttribute('data-panel-loaded', '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                list.innerHTML = html;
            })
            .catch(function () {
                // Allowed to try again: whatever went wrong may not still be wrong.
                list.removeAttribute('data-panel-loaded');
                list.innerHTML = '<p class="notif-panel-empty">' + (list.getAttribute('data-panel-error') || 'Could not load these right now.') + '</p>';
            });
    }

    function close(menu, returnFocus) {
        var p = parts(menu);
        if (!p.toggle || !p.list || p.list.hidden) {
            return;
        }

        p.list.hidden = true;
        p.toggle.setAttribute('aria-expanded', 'false');

        // Only on Escape. Pulling focus back when someone clicked elsewhere
        // would yank it out of wherever they just went.
        if (returnFocus) {
            p.toggle.focus();
        }
    }

    function closeAll(except) {
        menus.forEach(function (menu) {
            if (menu !== except) {
                close(menu, false);
            }
        });
    }

    menus.forEach(function (menu) {
        var p = parts(menu);
        if (!p.toggle || !p.list) {
            return;
        }

        p.toggle.addEventListener('click', function (event) {
            // The bell is a link so that it still reaches the inbox with no
            // scripting; with scripting it opens the panel instead.
            if (p.toggle.tagName === 'A') {
                event.preventDefault();
            }

            var opening = p.list.hidden;
            closeAll(menu);
            p.list.hidden = !opening;
            p.toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');

            if (opening) {
                load(p.list);
            }
        });

        menu.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.key === 'Esc') {
                close(menu, true);
            }
        });

        // focusout fires before focus lands, so the new target arrives as
        // relatedTarget. A null one means focus left the document entirely,
        // which is not a reason to close.
        menu.addEventListener('focusout', function (event) {
            if (event.relatedTarget && !menu.contains(event.relatedTarget)) {
                close(menu, false);
            }
        });
    });

    document.addEventListener('click', function (event) {
        menus.forEach(function (menu) {
            if (!menu.contains(event.target)) {
                close(menu, false);
            }
        });
    });
}());
