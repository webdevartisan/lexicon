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
    document.querySelectorAll('[data-archive-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.submitter && event.submitter.getAttribute('form') === 'archive-cancel-form') {
                return;
            }
            markBusy(form);
        });
    });
})();
