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

        p.toggle.addEventListener('click', function () {
            var opening = p.list.hidden;
            closeAll(menu);
            p.list.hidden = !opening;
            p.toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
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
