/**
 * Confirmation dialog for the public blog pages, used in place of the
 * browser's confirm(). The blog themes load none of the site or control
 * panel stylesheets, so this brings its own (blog-confirm.css).
 *
 * window.blogConfirm(message) resolves true when the reader confirms and false
 * when they cancel, press Escape or click outside the box.
 */
(function () {
    window.blogConfirm = function (message) {
        return new Promise(function (resolve) {
            var dialog = document.createElement('dialog');
            dialog.className = 'blog-confirm';
            dialog.setAttribute('aria-label', 'Please confirm');

            var icon = document.createElement('div');
            icon.className = 'blog-confirm-icon';
            icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';

            var title = document.createElement('h2');
            title.className = 'blog-confirm-title';
            title.textContent = 'Are you sure?';

            var text = document.createElement('p');
            text.className = 'blog-confirm-message';
            text.textContent = message;

            var body = document.createElement('div');
            body.className = 'blog-confirm-body';
            body.appendChild(icon);
            body.appendChild(title);
            body.appendChild(text);

            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'blog-confirm-button';
            cancel.textContent = 'Cancel';

            var ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'blog-confirm-button blog-confirm-ok';
            ok.textContent = 'Confirm';

            var actions = document.createElement('div');
            actions.className = 'blog-confirm-actions';
            actions.appendChild(cancel);
            actions.appendChild(ok);

            dialog.appendChild(body);
            dialog.appendChild(actions);
            document.body.appendChild(dialog);

            var answer = false;
            ok.addEventListener('click', function () {
                answer = true;
                dialog.close();
            });
            cancel.addEventListener('click', function () {
                dialog.close();
            });
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) {
                    dialog.close();
                }
            });
            dialog.addEventListener('close', function () {
                dialog.remove();
                resolve(answer);
            });

            dialog.showModal();
            cancel.focus();
        });
    };
})();
