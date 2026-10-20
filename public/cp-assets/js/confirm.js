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
        var modal = document.getElementById(ids[tone] || ids['default']);

        if (!modal || !window.cpModal) {
            return Promise.resolve(window.confirm(message));
        }

        var confirmButton = modal.querySelector('[data-modal-confirm]');

        setText(modal, '[data-modal-title]', options.title || 'Are you sure?');
        setText(modal, '[data-modal-message]', message);
        modal.querySelector('[data-modal-message]').style.whiteSpace = 'pre-line';
        confirmButton.textContent = options.confirmLabel || 'Confirm';
        setText(modal, '[data-modal-cancel]', options.cancelLabel || 'Cancel');

        return new Promise(function (resolve) {
            var confirmed = false;

            function onConfirm() {
                confirmed = true;
                window.cpModal.close(modal.id);
            }

            // Every way out (the buttons, Escape, a click outside) ends here
            function onClose() {
                confirmButton.removeEventListener('click', onConfirm);
                modal.removeEventListener('modal:close', onClose);
                resolve(confirmed);
            }

            confirmButton.addEventListener('click', onConfirm);
            modal.addEventListener('modal:close', onClose);
            window.cpModal.open(modal.id);
        });
    };
})();
