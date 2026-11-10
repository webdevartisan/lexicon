/**
 * Backup & restore page: the import form (source switch and drop area), the post
 * picker on the review step, and the busy state of both forms.
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
        var text = button.querySelector('span');
        if (label && text) {
            text.textContent = label;
        }
    }

    function selectedSource(form) {
        return Array.prototype.filter.call(form.querySelectorAll('[data-archive-source]'), function (radio) {
            return radio.checked;
        })[0];
    }

    function extension(form) {
        return '.' + selectedSource(form).getAttribute('data-ext');
    }

    // Empty string when the file is fine, otherwise the message to show
    function fileProblem(form, file) {
        var ext = extension(form);
        if (!file || file.name.slice(-ext.length).toLowerCase() !== ext) {
            return form.getAttribute('data-msg-type').replace('{type}', ext);
        }
        var maxBytes = parseInt(form.getAttribute('data-max-bytes'), 10) || 0;
        return maxBytes && file.size > maxBytes ? form.getAttribute('data-msg-size') : '';
    }

    // The chosen-file row and the drop area take turns on screen
    function initFilePicker(form) {
        var input = form.querySelector('[data-archive-input]');
        var drop = form.querySelector('[data-archive-drop]');
        var row = form.querySelector('[data-archive-file]');
        var error = form.querySelector('[data-archive-error]');

        function showError(message) {
            error.textContent = message;
            error.hidden = !message;
        }

        // The class, not the attribute: both elements are flex boxes, and a Tailwind display class beats [hidden]
        function showFile(shown) {
            row.classList.toggle('hidden', !shown);
            drop.classList.toggle('hidden', shown);
        }

        function clear() {
            input.value = '';
            showFile(false);
        }

        function choose() {
            var file = input.files && input.files[0];
            var problem = file ? fileProblem(form, file) : '';
            showError(problem);
            if (!file || problem) {
                clear();
                return;
            }
            row.querySelector('[data-archive-name]').textContent = file.name;
            row.querySelector('[data-archive-size]').textContent = formatSize(file.size);
            showFile(true);
        }

        input.addEventListener('change', choose);
        form.querySelector('[data-archive-clear]').addEventListener('click', function () {
            clear();
            showError('');
            input.focus();
        });

        return { input: input, drop: drop, choose: choose, clear: clear, showError: showError };
    }

    function initDropTarget(picker) {
        var drop = picker.drop;

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
                picker.input.files = event.dataTransfer.files;
                picker.choose();
            }
        });
    }

    // Each source accepts a different file type and shows its own help and limits
    function initSourceSwitch(form, picker) {
        var site = form.querySelector('[data-archive-site]');
        var siteInput = site.querySelector('input');
        var help = document.querySelector('[data-archive-help]');

        function apply() {
            var current = selectedSource(form);
            var needsSite = current.getAttribute('data-site') === '1';
            help.textContent = current.getAttribute('data-help');
            picker.drop.querySelector('[data-archive-drop-text]').textContent =
                form.getAttribute('data-drop-template').replace('{type}', extension(form));
            picker.drop.querySelector('[data-archive-limits]').textContent = current.getAttribute('data-limits');
            picker.input.setAttribute('accept', extension(form) + (extension(form) === '.zip' ? ',application/zip' : ''));
            site.hidden = !needsSite;
            // A hidden address field must not post a value the chosen source never asked for
            siteInput.disabled = !needsSite;
            picker.clear();
            picker.showError('');
        }

        form.querySelectorAll('[data-archive-source]').forEach(function (radio) {
            radio.addEventListener('change', apply);
        });
    }

    function initUpload(form) {
        var picker = initFilePicker(form);
        initDropTarget(picker);
        initSourceSwitch(form, picker);

        form.addEventListener('submit', function (event) {
            var problem = fileProblem(form, picker.input.files && picker.input.files[0]);
            if (problem) {
                event.preventDefault();
                picker.showError(problem);
                return;
            }
            markBusy(form);
        });
    }

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

    document.querySelectorAll('[data-archive-form]').forEach(initUpload);

    document.querySelectorAll('[data-archive-confirm]').forEach(function (form) {
        initPostPicker(form);
        form.addEventListener('submit', function (event) {
            // The cancel button lives in this form's header but posts to its own form
            if (event.submitter && event.submitter.getAttribute('form') === 'archive-cancel-form') {
                return;
            }
            markBusy(form);
        });
    });
})();
