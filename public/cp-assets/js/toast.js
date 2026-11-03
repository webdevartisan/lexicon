/**
 * Shows a toast in the dashboard's corner stack, the same one flash messages use.
 *
 * @param {string} message The text to show
 * @param {string} type "success" or "error"
 */
window.cpToast = function (message, type) {
    var stack = document.getElementById('toast-stack');
    var template = document.querySelector('[data-toast-template="' + (type === 'error' ? 'error' : 'success') + '"]');
    if (!stack || !template) return;

    var toast = template.content.firstElementChild.cloneNode(true);
    toast.querySelector('[data-toast-text]').textContent = message;
    stack.appendChild(toast);
};
