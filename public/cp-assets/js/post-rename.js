/**
 * Rename a post in place on the Posts list. The pencil next to a title turns it
 * into a text box: Enter or leaving the box saves, Escape puts the old title back.
 *
 * Markup: a [data-post-title] wrapper with data-rename-url and data-title holds the
 * [data-post-title-text] element and the [data-post-rename] button. The CSRF token
 * and the messages come from [data-post-tools] on the page.
 */
(function () {
    var tools = document.querySelector('[data-post-tools]');
    if (!tools) return;

    var MIN = 2;
    var MAX = 100;
    var tokenInput = tools.querySelector('input[name="_token"]');

    function message(root, text) {
        var note = root.querySelector('[data-post-rename-note]');
        if (!note) {
            note = document.createElement('p');
            note.setAttribute('data-post-rename-note', '');
            note.setAttribute('role', 'status');
            note.className = 'w-full mt-1 text-xs font-normal whitespace-normal text-red-600 dark:text-red-400';
            root.appendChild(note);
        }
        note.textContent = text;
        note.hidden = text === '';
    }

    // An inline style, because display classes such as line-clamp beat the hidden attribute.
    function visible(el, on) {
        el.style.display = on ? '' : 'none';
    }

    function showTitle(root, title) {
        var text = root.querySelector('[data-post-title-text]');
        var target = text.querySelector('a') || text;
        target.textContent = title;
        root.dataset.title = title;
    }

    function start(root) {
        if (root.querySelector('[data-post-rename-input]')) return;

        var text = root.querySelector('[data-post-title-text]');
        var button = root.querySelector('[data-post-rename]');
        var original = root.dataset.title || '';
        var done = false;

        var input = document.createElement('input');
        input.type = 'text';
        input.value = original;
        input.maxLength = MAX;
        input.setAttribute('data-post-rename-input', '');
        input.setAttribute('aria-label', tools.dataset.renameLabel || 'Post title');
        input.className = 'form-input flex-1 min-w-0 px-2 py-1 text-sm border-slate-200 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 focus:outline-none focus:border-custom-500';

        visible(text, false);
        visible(button, false);
        message(root, '');
        root.insertBefore(input, text);
        input.focus();
        input.select();

        function finish(title) {
            done = true;
            input.remove();
            visible(text, true);
            visible(button, true);
            if (title !== null) showTitle(root, title);
        }

        function save() {
            var title = input.value.trim();
            if (title === original) {
                finish(null);
                button.focus();
                return;
            }

            var body = new FormData();
            body.append('_token', tokenInput ? tokenInput.value : '');
            body.append('title', title);
            input.disabled = true;

            fetch(root.dataset.renameUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
                .then(function (r) {
                    return r.json().then(function (data) { return { ok: r.ok, data: data }; });
                })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.data && res.data.error ? res.data.error : '');
                    finish(res.data.title);
                })
                .catch(function (err) {
                    finish(null);
                    message(root, err.message || tools.dataset.renameFailed || 'The title could not be saved.');
                })
                .then(function () { button.focus(); });
        }

        function valid() {
            var length = input.value.trim().length;
            return length >= MIN && length <= MAX;
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                finish(null);
                button.focus();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (valid()) {
                    save();
                } else {
                    message(root, tools.dataset.renameLength || 'Use 2 to 100 characters for the title.');
                }
            }
        });

        // Leaving the box keeps a usable title and quietly drops one that is too short.
        input.addEventListener('blur', function () {
            if (done || input.disabled) return;
            if (valid()) {
                save();
            } else {
                finish(null);
                message(root, '');
            }
        });
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-post-rename]');
        if (!button) return;
        e.preventDefault();
        start(button.closest('[data-post-title]'));
    });
})();
