/**
 * Suspend form (views/areas/admin/User/suspend.lex.php).
 *
 * Only tidies the form: hides the length controls for a permanent suspension
 * and the date field unless a custom end was chosen. With scripting off every
 * control stays visible and the server ignores the ones that do not apply.
 *
 * Hidden controls are disabled as well as hidden, so they drop out of the tab
 * order and a screen reader does not announce fields that do nothing.
 */
(function () {
    var form = document.querySelector('[data-suspend-form]');
    if (!form) return;

    var duration = form.querySelector('[data-suspend-duration]');
    var length = form.querySelector('[data-suspend-length]');
    var custom = form.querySelector('[data-suspend-custom]');

    function setShown(el, shown) {
        if (!el) return;
        el.hidden = !shown;
        Array.prototype.forEach.call(el.querySelectorAll('input, select'), function (control) {
            control.disabled = !shown;
        });
    }

    function sync() {
        var checked = form.querySelector('[data-suspend-type]:checked');
        var temporary = !checked || checked.value === 'temporary';

        setShown(duration, temporary);
        setShown(custom, temporary && length && length.value === 'custom');
    }

    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-suspend-type], [data-suspend-length]')) sync();
    });

    sync();
})();
