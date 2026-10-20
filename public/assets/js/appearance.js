/**
 * Light, dark or auto (follow the device), chosen in the user menu.
 *
 * Loaded in the head of every layout without defer, so data-mode is on <html>
 * before anything paints. The dashboard recolours the whole page from it; the
 * front and blog themes only recolour the platform pieces (menus, the bell, the
 * site modal, confirm dialogs).
 */
(function () {
    'use strict';

    var KEY = 'theme-pref';
    var CHOICES = ['light', 'dark', 'auto'];
    var root = document.documentElement;
    var darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function getPreference() {
        try {
            var saved = localStorage.getItem(KEY);
            return CHOICES.indexOf(saved) !== -1 ? saved : 'auto';
        } catch (error) {
            return 'auto';
        }
    }

    function apply() {
        var pref = getPreference();
        var mode = pref === 'auto' ? (darkQuery.matches ? 'dark' : 'light') : pref;

        root.setAttribute('data-mode', mode);
        root.setAttribute('data-theme-pref', pref);

        // The dashboard's sidebar and topbar take their colours from these.
        if (root.hasAttribute('data-sidebar')) {
            root.setAttribute('data-sidebar', mode);
            root.setAttribute('data-topbar', mode);
        }

        document.querySelectorAll('[data-theme-choice]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-theme-choice') === pref ? 'true' : 'false');
        });
    }

    function setPreference(pref) {
        if (CHOICES.indexOf(pref) === -1) {
            return;
        }

        try {
            localStorage.setItem(KEY, pref);
        } catch (error) {
            // Private browsing: the choice still applies to this page.
        }

        apply();
    }

    apply();
    darkQuery.addEventListener('change', apply);

    // Other open tabs pick up a change made in this one.
    window.addEventListener('storage', function (event) {
        if (event.key === KEY) {
            apply();
        }
    });

    // The buttons are not in the document yet while this runs in the head, and
    // the Preferences tab brings its own when the site modal loads it.
    document.addEventListener('DOMContentLoaded', apply);
    document.addEventListener('panel:loaded', apply);

    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('[data-theme-choice]');
        if (button) {
            setPreference(button.getAttribute('data-theme-choice'));
        }
    });

    window.lexiconTheme = { getPreference: getPreference, setPreference: setPreference };
})();
