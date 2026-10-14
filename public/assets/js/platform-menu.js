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
 * opens. That is how the notification bell shows notifications without every
 * page on the site paying for the query that builds them, and why a cached page
 * never carries anyone's notifications. The panel's tabs switch in place, so
 * they follow the ARIA tabs pattern: arrow keys, Home and End move between
 * them, and mark all read follows the tab that is showing.
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
                initTabs(list);
            })
            .catch(function () {
                // Allowed to try again: whatever went wrong may not still be wrong.
                list.removeAttribute('data-panel-loaded');
                var message = document.createElement('p');
                message.className = 'notif-panel-status';
                message.setAttribute('role', 'alert');
                message.textContent = list.getAttribute('data-panel-error') || 'Could not load these right now.';
                list.replaceChildren(message);
            });
    }

    function selectTab(tabs, tab, focus) {
        tabs.forEach(function (other) {
            var on = other === tab;
            other.setAttribute('aria-selected', on ? 'true' : 'false');
            other.tabIndex = on ? 0 : -1;
            var panel = document.getElementById(other.getAttribute('aria-controls'));
            if (panel) {
                panel.hidden = !on;
            }
        });

        // Mark all read applies to the tab on screen, as Discourse's Dismiss does.
        var field = tab.closest('[data-platform-menu-list]').querySelector('[data-notif-tab-field]');
        if (field) {
            field.value = tab.getAttribute('data-notif-tab');
        }

        if (focus) {
            tab.focus();
        }
    }

    function initTabs(list) {
        var tablist = list.querySelector('[data-notif-tabs]');
        if (!tablist) {
            return;
        }

        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
        var rtl = document.documentElement.getAttribute('dir') === 'rtl';

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                selectTab(tabs, tab, false);
            });
        });

        tablist.addEventListener('keydown', function (event) {
            var index = tabs.indexOf(document.activeElement);
            if (index === -1) {
                return;
            }

            var next = null;
            var forward = rtl ? 'ArrowLeft' : 'ArrowRight';
            var back = rtl ? 'ArrowRight' : 'ArrowLeft';

            if (event.key === forward) {
                next = tabs[(index + 1) % tabs.length];
            } else if (event.key === back) {
                next = tabs[(index - 1 + tabs.length) % tabs.length];
            } else if (event.key === 'Home') {
                next = tabs[0];
            } else if (event.key === 'End') {
                next = tabs[tabs.length - 1];
            }

            if (next) {
                event.preventDefault();
                selectTab(tabs, next, true);
            }
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

        // A link that opens a panel is a button while scripting runs, and a
        // button answers Space as well as Enter.
        if (p.toggle.tagName === 'A') {
            p.toggle.setAttribute('role', 'button');
            p.toggle.addEventListener('keydown', function (event) {
                if (event.key === ' ') {
                    event.preventDefault();
                    p.toggle.click();
                }
            });
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
