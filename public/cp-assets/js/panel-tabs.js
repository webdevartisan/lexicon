/**
 * A left menu that switches between panels inside one container.
 *
 * Buttons carry data-panel-tab="<name>", panels carry data-panel="<name>", and
 * both live inside an element marked data-panel-tabs.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var tab = e.target.closest ? e.target.closest('[data-panel-tab]') : null;

        if (!tab) {
            return;
        }

        var box = tab.closest('[data-panel-tabs]');
        var name = tab.getAttribute('data-panel-tab');

        box.querySelectorAll('[data-panel-tab]').forEach(function (button) {
            button.classList.toggle('active', button === tab);
        });

        box.querySelectorAll('[data-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== name;
        });
    });
})();

// A <details data-popover> closes when the click lands anywhere outside it.
document.addEventListener('click', function (e) {
    document.querySelectorAll('details[data-popover][open]').forEach(function (popover) {
        if (!popover.contains(e.target)) {
            popover.removeAttribute('open');
        }
    });
});

// A link or redirect ending in #name opens that panel.
document.addEventListener('DOMContentLoaded', function () {
    var name = window.location.hash.slice(1);
    var tab = name ? document.querySelector('[data-panel-tab="' + name + '"]') : null;

    if (tab) {
        tab.click();
    }
});

// Forms that submit through script lose the clicked button's name and value, so
// the section a save button belongs to is copied into a hidden field first.
document.addEventListener('click', function (e) {
    var button = e.target.closest ? e.target.closest('button[name="_section"]') : null;

    if (!button || !button.form) {
        return;
    }

    var field = button.form.querySelector('input[type="hidden"][name="_section"]');

    if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = '_section';
        button.form.appendChild(field);
    }

    field.value = button.value;
});
