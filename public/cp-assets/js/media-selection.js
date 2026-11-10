/**
 * Checkboxes, select all, and bulk download or delete for the media library.
 *
 * Phones tick through a select mode instead, since a tap there opens the image.
 *
 * @param {object} deps root, list, phone, api, route, t, esc, humanSize, displayName, items, maxItems,
 *                      notify, onDeleted
 * @returns {{checkboxHtml: function(object): string, sync: function(): void, clear: function(): void,
 *            selecting: function(): boolean, toggle: function(string): void}}
 */
window.createMediaSelection = function (deps) {
    var t = deps.t;
    var esc = deps.esc;
    var root = deps.root;
    var row = root.querySelector('[data-media-selection]');
    var allBox = row.querySelector('[data-media-select-all]');
    var bar = root.querySelector('[data-media-bulk-bar]');
    var text = bar.querySelector('[data-media-selection-text]');
    var downloadButton = bar.querySelector('[data-media-download-selected]');
    var deleteButton = bar.querySelector('[data-media-delete-selected]');
    var selected = {};

    function chosenItems() {
        return deps.items().filter(function (item) { return selected[item.id]; });
    }

    function checkboxHtml(item) {
        return '<input type="checkbox" data-media-select class="size-4 rounded cursor-pointer border-slate-300 accent-custom-500 dark:border-zink-500"'
            + (selected[item.id] ? ' checked' : '')
            + ' aria-label="' + esc(t('select', { name: deps.displayName(item) })) + '">';
    }

    function sync() {
        var shown = deps.items();
        var present = {};
        shown.forEach(function (item) { present[item.id] = true; });
        Object.keys(selected).forEach(function (id) { if (!present[id]) delete selected[id]; });

        var chosen = chosenItems();
        var bytes = chosen.reduce(function (sum, item) { return sum + (item.size_bytes || 0); }, 0);

        row.classList.toggle('hidden', shown.length === 0);
        bar.classList.toggle('hidden', chosen.length === 0);
        text.textContent = t('selection', { count: chosen.length, shown: shown.length, size: deps.humanSize(bytes) });
        allBox.checked = chosen.length > 0 && chosen.length === shown.length;
        allBox.indeterminate = chosen.length > 0 && chosen.length < shown.length;

        deps.list.querySelectorAll('[data-media-id]').forEach(function (tile) {
            var on = !!selected[tile.getAttribute('data-media-id')];
            tile.toggleAttribute('data-selected', on);
            tile.querySelector('[data-media-select]').checked = on;
        });
    }

    function clear() {
        selected = {};
        sync();
    }

    function selecting() {
        return root.hasAttribute('data-selecting');
    }

    function toggle(id) {
        if (selected[id]) {
            delete selected[id];
        } else {
            selected[id] = true;
        }
        sync();
    }

    function leaveSelectMode() {
        root.removeAttribute('data-selecting');
        clear();
    }

    root.querySelector('[data-media-select-mode]').addEventListener('click', function () {
        root.setAttribute('data-selecting', '');
        allBox.focus();
    });
    row.querySelector('[data-media-select-done]').addEventListener('click', leaveSelectMode);
    deps.phone.addEventListener('change', function () {
        if (!deps.phone.matches && selecting()) leaveSelectMode();
    });

    deps.list.addEventListener('change', function (e) {
        var box = e.target.closest('[data-media-select]');
        if (!box) return;
        var id = box.closest('[data-media-id]').getAttribute('data-media-id');
        if (box.checked) {
            selected[id] = true;
        } else {
            delete selected[id];
        }
        sync();
    });

    allBox.addEventListener('change', function () {
        selected = {};
        if (allBox.checked) {
            deps.items().forEach(function (item) { selected[item.id] = true; });
        }
        sync();
    });

    // Browsers drop downloads started back to back, so they go out one by one
    downloadButton.addEventListener('click', function () {
        chosenItems().forEach(function (item, i) {
            setTimeout(function () {
                var link = document.createElement('a');
                link.href = item.url;
                link.download = deps.displayName(item);
                document.body.appendChild(link);
                link.click();
                link.remove();
            }, i * 300);
        });
    });

    function confirmMessage(used, count) {
        if (used.length === 0) return t('bulkDeleteConfirm', { count: count });

        var places = [];
        var inBody = false;
        used.forEach(function (entry) {
            entry.usages.forEach(function (use) {
                places.push('• ' + entry.name + ': ' + use.label + ' (' + use.context + ')');
                inBody = inBody || !!use.in_body;
            });
        });

        return t('bulkDeleteUsed') + '\n\n' + places.join('\n') + '\n\n' + t('deleteLeaves')
            + (inBody ? ' ' + t('deleteInBody') : '') + '\n\n' + t('bulkDeleteConfirm', { count: count });
    }

    deleteButton.addEventListener('click', function () {
        var ids = chosenItems().map(function (item) { return String(item.id); });
        if (ids.length > deps.maxItems) {
            deps.notify(t('tooMany', { max: deps.maxItems }), 'error');
            return;
        }

        var query = new URLSearchParams(ids.map(function (id) { return ['ids[]', id]; })).toString();
        deleteButton.disabled = true;

        deps.api(deps.route('bulkUsages') + '?' + query)
            .then(function (data) { return cpConfirm(confirmMessage(data.items || [], ids.length), { tone: 'danger' }); })
            .then(function (ok) {
                if (!ok) return null;
                return deps.api(deps.route('bulkDestroy'), { 'ids[]': ids }).then(function (data) {
                    var deleted = data.deleted || [];
                    deps.onDeleted(deleted);
                    root.removeAttribute('data-selecting');
                    deps.notify(t('bulkDeleted', { count: deleted.length }), 'ok');
                });
            })
            .catch(function (error) { deps.notify(error.message, 'error'); })
            .finally(function () {
                deleteButton.disabled = false;
                sync();
            });
    });

    return { checkboxHtml: checkboxHtml, sync: sync, clear: clear, selecting: selecting, toggle: toggle };
};
