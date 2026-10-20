/**
 * Drawers and centred modals for the control panel. Controls carrying
 * data-drawer-target / data-modal-target open them, data-drawer-close /
 * data-modal-close close them, and scripts use window.cpModal.open(id, trigger)
 * and window.cpModal.close(id). A closing modal fires `modal:close` on itself,
 * whichever way it was closed.
 *
 * Modals stack, so a confirmation opened from inside an editor closes back onto
 * the editor with the backdrop and scroll lock still in place.
 *
 * The layout and several pages both load this file, sometimes before the
 * layout's own modals exist. The state and document listeners are created on the
 * first load; every load wires the controls that are not wired yet.
 */
(function () {
    const api = window.cpModal || createModalApi();
    window.cpModal = api;
    wireControls(api);

    function createModalApi() {
        const focusable = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const body = document.body;
        const backdrop = document.getElementById('backDropDiv') || createBackdrop();
        const openModals = [];
        const hideTimers = new WeakMap();
        let openDrawerId = null;

        function createBackdrop() {
            const el = document.createElement('div');
            el.className = 'fixed inset-0 bg-slate-900/40 dark:bg-zink-800/70 z-[1049] backdrop-overlay hidden';
            el.id = 'backDropDiv';
            body.appendChild(el);

            return el;
        }

        function show(element, delay) {
            clearTimeout(hideTimers.get(element));
            element.classList.remove('hidden');
            setTimeout(() => {
                element.classList.remove('show');
                backdrop.classList.remove('hidden');
            }, delay);
        }

        // The slide-out transition needs the element visible for a moment before it hides
        function hide(element) {
            element.classList.add('show');
            hideTimers.set(element, setTimeout(() => element.classList.add('hidden'), 350));
        }

        function syncPage() {
            const anyOpen = openModals.length > 0 || openDrawerId !== null;
            if (!anyOpen) backdrop.classList.add('hidden');
            body.classList.toggle('overflow-hidden', anyOpen);
        }

        function topModal() {
            const top = openModals[openModals.length - 1];

            return top ? document.getElementById(top.id) : null;
        }

        // Keyboard and focus behaviour of a modal dialog (WAI-ARIA APG): focus
        // moves in on open, Tab stays inside, Escape closes, and focus goes
        // back to whatever opened it.
        function open(id, trigger) {
            const modal = document.getElementById(id);
            if (!modal || openModals.some(entry => entry.id === id)) return;
            openModals.push({ id: id, trigger: trigger || document.activeElement });
            show(modal, 200);
            syncPage();
            (modal.querySelector(focusable) || modal).focus();
        }

        function close(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            const index = openModals.findIndex(entry => entry.id === id);
            const entry = index === -1 ? null : openModals.splice(index, 1)[0];
            hide(modal);
            syncPage();
            if (entry && entry.trigger && body.contains(entry.trigger)) entry.trigger.focus();
            modal.dispatchEvent(new CustomEvent('modal:close'));
        }

        function openDrawer(id) {
            const drawer = document.getElementById(id);
            if (!drawer) return;
            openDrawerId = id;
            show(drawer, 0);
            syncPage();
        }

        function closeDrawer(id) {
            const drawer = document.getElementById(id);
            if (!drawer) return;
            if (openDrawerId === id) openDrawerId = null;
            hide(drawer);
            syncPage();
        }

        document.addEventListener('keydown', function (event) {
            const modal = topModal();
            if (!modal || !modal.hasAttribute('modal-center')) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                close(modal.id);
                return;
            }

            if (event.key !== 'Tab') return;
            const items = Array.prototype.filter.call(modal.querySelectorAll(focusable), el => el.offsetParent !== null);
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        backdrop.addEventListener('click', function () {
            const modal = topModal();
            if (modal && modal.hasAttribute('modal-center')) {
                close(modal.id);
            } else if (openDrawerId) {
                closeDrawer(openDrawerId);
            }
        });

        return { open: open, close: close, openDrawer: openDrawer, closeDrawer: closeDrawer };
    }

    function wireControls(modals) {
        const unbound = selector => Array.prototype.filter.call(document.querySelectorAll(selector), el => {
            if (el.dataset.modalBound) return false;
            el.dataset.modalBound = '1';
            return true;
        });
        const wire = (selector, attribute, action) => unbound(selector).forEach(el => {
            const id = el.getAttribute(attribute);
            if (id) el.addEventListener('click', () => action(id, el));
        });

        wire('[data-drawer-target]', 'data-drawer-target', modals.openDrawer);
        wire('[data-drawer-close]', 'data-drawer-close', modals.closeDrawer);
        wire('[data-modal-target]', 'data-modal-target', modals.open);
        wire('[data-modal-close]', 'data-modal-close', modals.close);

        // A form that failed validation reopens its modal so the errors are in view.
        const openOnLoad = document.querySelector('[data-modal-open-on-load]');
        if (openOnLoad && openOnLoad.id) {
            modals.open(openOnLoad.id, document.querySelector('[data-modal-target="' + openOnLoad.id + '"]'));
        }
    }
})();
