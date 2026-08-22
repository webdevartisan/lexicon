// Front flash toasts: click-to-dismiss plus an auto-close timer, mirroring the
// dashboard's alert.js without pulling that whole file onto the front. Each
// toast carries data-auto-close (ms); 0 or missing means it stays until closed.
(function () {
    'use strict';

    function hide(toast) {
        if (!toast || toast.classList.contains('is-hiding')) {
            return;
        }
        toast.classList.add('is-hiding');
        toast.addEventListener('transitionend', function () {
            toast.remove();
        }, { once: true });
        // Fallback for reduced-motion / no transition: remove after a beat.
        setTimeout(function () {
            if (toast.isConnected) {
                toast.remove();
            }
        }, 400);
    }

    document.querySelectorAll('[data-lx-toast]').forEach(function (toast) {
        var closeBtn = toast.querySelector('[data-lx-toast-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                hide(toast);
            });
        }

        var delay = parseInt(toast.getAttribute('data-auto-close'), 10);
        if (delay > 0) {
            setTimeout(function () {
                hide(toast);
            }, delay);
        }
    });
})();
