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

        // Attach click event listeners to modal buttons
        allModalButtons.forEach(element => {
            const modalId = element.getAttribute('data-modal-target');
            if (modalId) {
                element.addEventListener('click', function () {
                    toggleElementState(modalId, true, 200);
                });
            }
        });

        // Attach click event listeners to modal close buttons
        allModalCloseButtons.forEach(element => {
            const modalId = element.getAttribute('data-modal-close');
            if (modalId) {
                element.addEventListener('click', function () {
                    toggleElementState(modalId, false, 200);
                });
            }
        });

        // Attach click event listener to backdrop-overlay
        backDropOverlay?.addEventListener('click', function () {
            if (openDrawerId) {
                toggleElementState(openDrawerId, false, 0);
            }
            if (openModalId) {
                toggleElementState(openModalId, false, 200);
            }
        });
    }

    drawerSetting();