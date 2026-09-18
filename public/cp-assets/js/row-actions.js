/**
 * Row actions menus (views/components/row-actions.lex.php), following the WAI-ARIA
 * menu button pattern: Enter, Space or ArrowDown opens on the first item, ArrowUp on
 * the last, arrows and Home/End move, Escape closes and returns focus, Tab closes.
 *
 * The menu is positioned with Popper's fixed strategy so the table's overflow
 * wrapper cannot clip it, and it flips above the button near the viewport bottom.
 */
(function () {
    var open = null;

    function items(menu) {
        return Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]'));
    }

    function focusItem(menu, index) {
        var list = items(menu);
        if (list.length === 0) return;
        var next = (index + list.length) % list.length;
        list[next].focus({ preventScroll: true });
    }

    function close(returnFocus) {
        if (!open) return;
        var current = open;
        open = null;

        current.menu.hidden = true;
        current.toggle.setAttribute('aria-expanded', 'false');
        if (current.popper) current.popper.destroy();
        if (returnFocus) current.toggle.focus();
    }

    function show(root, focusLast) {
        var toggle = root.querySelector('[data-row-actions-toggle]');
        var menu = root.querySelector('[data-row-actions-menu]');
        if (!toggle || !menu) return;

        close(false);

        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');

        var popper = null;
        if (window.Popper && typeof window.Popper.createPopper === 'function') {
            popper = window.Popper.createPopper(toggle, menu, {
                strategy: 'fixed',
                placement: 'bottom-end',
                modifiers: [
                    { name: 'flip', options: { fallbackPlacements: ['top-end', 'bottom-start', 'top-start'] } },
                    { name: 'offset', options: { offset: [0, 4] } },
                ],
            });
            popper.forceUpdate();
        } else {
            console.error('row-actions: Popper is not loaded, so the menu may be clipped by its table.');
        }

        open = { root: root, toggle: toggle, menu: menu, popper: popper };
        focusItem(menu, focusLast ? -1 : 0);
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-row-actions-toggle]');
        if (toggle) {
            var root = toggle.closest('[data-row-actions]');
            if (open && open.root === root) {
                close(true);
            } else {
                show(root, false);
            }
            return;
        }

        if (open && !open.menu.contains(e.target)) close(false);
    });

    document.addEventListener('keydown', function (e) {
        var toggle = e.target.closest && e.target.closest('[data-row-actions-toggle]');
        if (toggle && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            show(toggle.closest('[data-row-actions]'), e.key === 'ArrowUp');
            return;
        }

        if (!open || !open.menu.contains(e.target)) return;

        var list = items(open.menu);
        var index = list.indexOf(document.activeElement);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                focusItem(open.menu, index + 1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                focusItem(open.menu, index - 1);
                break;
            case 'Home':
                e.preventDefault();
                focusItem(open.menu, 0);
                break;
            case 'End':
                e.preventDefault();
                focusItem(open.menu, -1);
                break;
            case 'Escape':
                e.preventDefault();
                close(true);
                break;
            case 'Tab':
                close(false);
                break;
            case ' ':
                // Space activates a link-shaped item the way Enter already does.
                if (document.activeElement && document.activeElement.tagName === 'A') {
                    e.preventDefault();
                    document.activeElement.click();
                }
                break;
        }
    });

    // A fixed menu would drift away from its row while the page scrolls under it.
    window.addEventListener('scroll', function () { close(false); }, true);
    window.addEventListener('resize', function () { close(false); });
})();
