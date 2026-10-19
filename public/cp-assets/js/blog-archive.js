/**
 * Backup & restore page: the archive drop area and the busy state of both forms.
 *
 * The server re-checks everything; these checks only save an upload that would
 * be refused anyway. Without scripting the plain file input still works.
 */
(function () {
    'use strict';

    function formatSize(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(1) + ' MB';
        }
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function markBusy(form) {
        var button = form.querySelector('[data-archive-submit]');
        if (!button) {
            return;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        var label = form.getAttribute('data-msg-busy');
        if (label) {
            button.lastChild.textContent = ' ' + label;
        }
    }

    function initUpload(form) {
        var input = form.querySelector('[data-archive-input]');
        var drop = form.querySelector('[data-archive-drop]');
        var row = form.querySelector('[data-archive-file]');
        var error = form.querySelector('[data-archive-error]');
        var maxBytes = parseInt(form.getAttribute('data-max-bytes'), 10) || 0;

        function showError(message) {
            error.textContent = message;
            error.hidden = !message;
        }

        function clear() {
            input.value = '';
            row.hidden = true;
            drop.hidden = false;
        }

        function check(file) {
            if (!/\.zip$/i.test(file.name)) {
                return form.getAttribute('data-msg-type');
            }
            if (maxBytes && file.size > maxBytes) {
                return form.getAttribute('data-msg-size');
            }
            return '';
        }

        function show() {
            var file = input.files && input.files[0];
            if (!file) {
                clear();
                return;
            }
            var problem = check(file);
            showError(problem);
            if (problem) {
                clear();
                return;
            }
            row.querySelector('[data-archive-name]').textContent = file.name;
            row.querySelector('[data-archive-size]').textContent = formatSize(file.size);
            row.hidden = false;
            drop.hidden = true;
        }

        input.addEventListener('change', show);
        form.querySelector('[data-archive-clear]').addEventListener('click', function () {
            clear();
            showError('');
            input.focus();
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            drop.addEventListener(name, function (event) {
                event.preventDefault();
                drop.classList.add('border-custom-500');
            });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            drop.addEventListener(name, function () {
                drop.classList.remove('border-custom-500');
            });
        });
        drop.addEventListener('drop', function (event) {
            event.preventDefault();
            if (event.dataTransfer && event.dataTransfer.files.length) {
                input.files = event.dataTransfer.files;
                show();
            }
        });

        form.addEventListener('submit', function (event) {
            var file = input.files && input.files[0];
            var problem = file ? check(file) : form.getAttribute('data-msg-type');
            if (problem) {
                event.preventDefault();
                showError(problem);
                return;
            }
            markBusy(form);
        });
    }

    document.querySelectorAll('[data-archive-form]').forEach(initUpload);
    function initPostPicker(form) {
        var all = form.querySelector('[data-post-all]');
        var picks = Array.prototype.slice.call(form.querySelectorAll('[data-post-pick]'));
        var count = form.querySelector('[data-post-count]');
        var submit = form.querySelector('[data-archive-submit]');
        if (!all || !picks.length) {
            return;
        }

        function update() {
            var chosen = picks.filter(function (box) { return box.checked; }).length;
            all.checked = chosen === picks.length;
            all.indeterminate = chosen > 0 && chosen < picks.length;
            count.textContent = count.getAttribute('data-template')
                .replace('{count}', chosen).replace('{total}', picks.length);
            if (submit) {
                submit.disabled = chosen === 0;
            }
        }

        all.addEventListener('change', function () {
            picks.forEach(function (box) { box.checked = all.checked; });
            update();
        });
        picks.forEach(function (box) { box.addEventListener('change', update); });
        update();
    }

    document.querySelectorAll('[data-archive-confirm]').forEach(initPostPicker);
    document.querySelectorAll('[data-archive-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.submitter && event.submitter.getAttribute('form') === 'archive-cancel-form') {
                return;
            }
            markBusy(form);
        });
    });

    document.querySelectorAll('[data-foreign-form]').forEach(function (form) {
        var select = form.querySelector('[data-foreign-source]');
        var file = form.querySelector('[data-foreign-file]');
        var site = form.querySelector('[data-foreign-site]');
        var types = { wordpress: '.xml', ghost: '.json' };

        function sync() {
            var kind = select.value;
            file.setAttribute('accept', types[kind]);
            file.value = '';
            site.hidden = kind !== 'ghost';
            form.querySelectorAll('[data-foreign-help]').forEach(function (help) {
                help.hidden = help.getAttribute('data-foreign-help') !== kind;
            });
        }

        select.addEventListener('change', sync);
        form.addEventListener('submit', function () {
            markBusy(form);
        });
    });
})();
