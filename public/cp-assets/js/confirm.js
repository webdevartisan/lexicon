/**
 * window.cpConfirm(message, options) opens one of the shared modals from the
 * layout (components/confirm-modal, driven by modal.js like the other modals) and
 * resolves true on confirm, false on cancel, Escape or a click outside.
 *
 * Options: title, confirmLabel, cancelLabel and tone ('danger', 'warning' or
 * the default blue). Without a tone, a destructive-sounding message is red.
 */
(function () {
    var destructive = /delete|remove|permanently|cancel|reset|clear|warning/i;
    var ids = {danger: 'confirmModalDanger', warning: 'confirmModalWarning', 'default': 'confirmModalDefault'};

    function setText(modal, selector, text) {
        var el = modal.querySelector(selector);
        if (el) {
            el.textContent = text;
        }
    }

    window.cpConfirm = function (message, options) {
        options = options || {};

        var tone = options.tone || (destructive.test(message) ? 'danger' : 'default');
        var trigger = document.querySelector('[data-modal-target="' + (ids[tone] || ids['default']) + '"]');
        var modal = trigger ? document.getElementById(trigger.getAttribute('data-modal-target')) : null;

        if (!modal) {
            return Promise.resolve(window.confirm(message));
        }

        var confirmButton = modal.querySelector('[data-modal-confirm]');
        var cancelButton = modal.querySelector('[data-modal-cancel]');

        setText(modal, '[data-modal-title]', options.title || 'Are you sure?');
        setText(modal, '[data-modal-message]', message);
        modal.querySelector('[data-modal-message]').style.whiteSpace = 'pre-line';
        confirmButton.textContent = options.confirmLabel || 'Confirm';
        cancelButton.textContent = options.cancelLabel || 'Cancel';

        return new Promise(function (resolve) {
            var settled = false;
            var watcher;

            function finish(answer) {
                if (settled) {
                    return;
                }
                settled = true;
                watcher.disconnect();
                confirmButton.removeEventListener('click', onConfirm);
                modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
                    el.removeEventListener('click', onCancel);
                });
                resolve(answer);
            }

            function onConfirm() {
                finish(true);
                cancelButton.click();
            }

            function onCancel() {
                finish(false);
            }

            confirmButton.addEventListener('click', onConfirm);
            modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
                el.addEventListener('click', onCancel);
            });

            // Escape and a click on the backdrop close the modal without touching a button
            watcher = new MutationObserver(function () {
                if (modal.classList.contains('hidden')) {
                    finish(false);
                }
            });
            watcher.observe(modal, {attributes: true, attributeFilter: ['class']});

            trigger.click();
        });
    };
})();
