    // drawer setting offCanvas
    function drawerSetting() {
        
        const allDrawerButtons = document.querySelectorAll('[data-drawer-target]');
        const allDrawerCloseButtons = document.querySelectorAll('[data-drawer-close]');
        const allModalButtons = document.querySelectorAll('[data-modal-target]');
        const allModalCloseButtons = document.querySelectorAll('[data-modal-close]');
        const bodyElement = document.body;       
        
        let openDrawerId = null;
        let openModalId = null;
        if(document.getElementById("backDropDiv")) {
            var backDropOverlay = document.getElementById("backDropDiv");
        } else {
            var backDropOverlay = document.createElement('div');
            backDropOverlay.className = 'fixed inset-0 bg-slate-900/40 dark:bg-zink-800/70 z-[1049] backdrop-overlay hidden';
            backDropOverlay.id = 'backDropDiv';
        }
        if (allModalButtons.length > 0 || allDrawerButtons.length > 0)
            document.body.appendChild(backDropOverlay);

        // Function to toggle the state of drawers and modals
        function toggleElementState(elementId, show, delay) {
            const element = document.getElementById(elementId);
            if (element) {
                if (!show) {
                    element.classList.add('show');
                    backDropOverlay.classList.add('hidden');
                    setTimeout(() => {
                        element.classList.add("hidden");
                    }, 350);
                } else {
                    element.classList.remove("hidden");
                    setTimeout(() => {
                        element.classList.remove('show');
                        backDropOverlay.classList.remove('hidden');
                    }, delay);
                }
                bodyElement.classList.toggle('overflow-hidden', show);
                if (show) {
                    openDrawerId = elementId;
                    openModalId = elementId;
                } else {
                    openDrawerId = null;
                    openModalId = null;
                }
            }
        }

        // Attach click event listeners to drawer buttons
        allDrawerButtons.forEach(element => {
            const drawerId = element.getAttribute('data-drawer-target');
            if (drawerId) {
                element.addEventListener('click', function () {
                    toggleElementState(drawerId, true, 0);
                });
            }
        });

        // Attach click event listeners to drawer close buttons
        allDrawerCloseButtons.forEach(element => {
            const drawerId = element.getAttribute('data-drawer-close');
            if (drawerId) {
                element.addEventListener('click', function () {
                    toggleElementState(drawerId, false, 0);
                });
            }
        });

        // Keyboard and focus behaviour of a modal dialog (WAI-ARIA APG): focus
        // moves in on open, Tab stays inside, Escape closes, and focus goes
        // back to whatever opened it.
        const focusable = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        let modalTrigger = null;

        function openModal(modalId, trigger) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modalTrigger = trigger || document.activeElement;
            toggleElementState(modalId, true, 200);
            const first = modal.querySelector(focusable);
            (first || modal).focus();
        }

        function closeModal(modalId) {
            toggleElementState(modalId, false, 200);
            if (modalTrigger && document.body.contains(modalTrigger)) {
                modalTrigger.focus();
            }
            modalTrigger = null;
        }

        document.addEventListener('keydown', function (event) {
            if (!openModalId) return;
            const modal = document.getElementById(openModalId);
            if (!modal || !modal.hasAttribute('modal-center')) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(openModalId);
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

        // Attach click event listeners to modal buttons
        allModalButtons.forEach(element => {
            const modalId = element.getAttribute('data-modal-target');
            if (modalId) {
                element.addEventListener('click', function () {
                    openModal(modalId, element);
                });
            }
        });

        // Attach click event listeners to modal close buttons
        allModalCloseButtons.forEach(element => {
            const modalId = element.getAttribute('data-modal-close');
            if (modalId) {
                element.addEventListener('click', function () {
                    closeModal(modalId);
                });
            }
        });

        // A form that failed validation reopens its modal so the errors are in view.
        const openOnLoad = document.querySelector('[data-modal-open-on-load]');
        if (openOnLoad && openOnLoad.id) {
            if (allModalButtons.length === 0 && allDrawerButtons.length === 0) {
                document.body.appendChild(backDropOverlay);
            }
            openModal(openOnLoad.id, document.querySelector('[data-modal-target="' + openOnLoad.id + '"]'));
        }

        // Attach click event listener to backdrop-overlay
        backDropOverlay?.addEventListener('click', function () {
            const open = openModalId ? document.getElementById(openModalId) : null;
            if (open && open.hasAttribute('modal-center')) {
                closeModal(openModalId);
            } else if (openDrawerId) {
                toggleElementState(openDrawerId, false, 0);
            }
        });
    }

    drawerSetting();