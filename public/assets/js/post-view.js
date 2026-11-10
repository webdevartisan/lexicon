/**
 * Grid or list layout for the Discover post results, remembered per browser.
 *
 * Loaded without defer just above the results, so a saved list choice is on
 * <html> before the cards paint and the grid never flashes first. The switch
 * stays hidden until this runs, since it does nothing without it.
 */
(function () {
    'use strict';

    var KEY = 'lx-post-view';
    var root = document.documentElement;

    function saved() {
        try {
            return localStorage.getItem(KEY) === 'list' ? 'list' : 'grid';
        } catch (error) {
            return 'grid';
        }
    }

    function apply(view) {
        root.setAttribute('data-post-view', view);
        document.querySelectorAll('[data-post-view-choice]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-post-view-choice') === view ? 'true' : 'false');
        });
    }

    apply(saved());

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-post-view-switch]').forEach(function (group) {
            group.hidden = false;
        });
        apply(saved());

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-post-view-choice]');
            if (!button) {
                return;
            }
            var view = button.getAttribute('data-post-view-choice') === 'list' ? 'list' : 'grid';
            try {
                localStorage.setItem(KEY, view);
            } catch (error) {
                // Private windows can refuse storage; the switch still works for this page
            }
            apply(view);
        });
    });
})();
