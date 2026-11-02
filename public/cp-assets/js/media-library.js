/**
 * The per-blog media library page. `items` holds what is on screen and every change
 * goes through render(), so the list and grid views always show the same thing.
 */
(function () {
    var root = document.querySelector('[data-media-library]');
    if (!root) return;

    var csrfToken = root.querySelector('input[name="_token"]').value;
    var initial = JSON.parse(root.querySelector('[data-media-initial]').textContent);
    var text = initial.text || {};
    var sources = initial.sources || {};
    var routes = initial.routes;

    function t(key, vars) {
        var phrase = text[key] || key;
        return phrase.replace(/\{(\w+)\}/g, function (match, name) {
            return vars && vars[name] !== undefined ? vars[name] : match;
        });
    }

    function route(name, id) {
        return routes[name].replace('{id}', encodeURIComponent(id));
    }

    function api(url, fields) {
        var options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
        if (fields) {
            var body = new FormData();
            body.append('_token', csrfToken);
            Object.keys(fields).forEach(function (key) {
                var value = fields[key];
                if (value === null || value === undefined) return;
                [].concat(value).forEach(function (part) { body.append(key, part); });
            });
            options.method = 'POST';
            options.headers['X-CSRF-TOKEN'] = csrfToken;
            options.body = body;
        }

        return fetch(url, options)
            .then(function (response) { return response.json().catch(function () { return null; }); })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.error) || t('error'));
                }
                return payload.data;
            });
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function humanSize(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
        var n = bytes / Math.pow(1024, i);
        return (i === 0 ? n.toFixed(0) : n.toFixed(1)) + ' ' + units[i];
    }

    function versioned(url, stamp) {
        if (!stamp) return url;
        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(stamp);
    }

    function displayName(item) {
        return item.original_name || item.filename || '';
    }

    function typeLabel(item) {
        var ext = String(item.extension || '').toUpperCase();
        return ext === 'JPEG' ? 'JPG' : ext;
    }

    var ICONS = {
        edit: '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        copy: '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
    };

    function icon(name) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            + ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-icon="' + name + '">'
            + ICONS[name] + '</svg>';
    }

    var VIEW_KEY = 'lexicon:media-view';
    var pageSize = 24;
    var items = initial.items || [];
    var total = initial.total || 0;
    var totalBytes = initial.totalBytes || 0;
    var filters = { q: '', source: '', sort: 'date', dir: 'desc' };
    var view = 'grid';

    try {
        if (window.localStorage.getItem(VIEW_KEY) === 'list') view = 'list';
    } catch (e) {}

    var list = document.getElementById('media-items');
    var empty = document.getElementById('media-empty');
    var countEl = root.querySelector('[data-media-count]');
    var bytesEl = root.querySelector('[data-media-bytes]');
    var statusEl = root.querySelector('[data-media-status]');
    var loadMoreWrap = document.getElementById('media-load-more-wrap');

    var ACTION_CLASS = 'inline-flex items-center justify-center size-8 rounded-md text-slate-500 hover:text-slate-800 hover:bg-slate-100'
        + ' dark:text-zink-300 dark:hover:text-zink-50 dark:hover:bg-zink-600 transition-colors'
        + ' focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';
    var DELETE_CLASS = 'inline-flex items-center justify-center size-8 rounded-md text-red-500 hover:text-red-700 hover:bg-red-50'
        + ' dark:text-red-400 dark:hover:bg-red-500/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500';

    function actionsHtml(item) {
        var name = displayName(item);
        var label = function (key) { return esc(t(key, { name: name })); };

        return ''
            + '<button type="button" data-media-open class="' + ACTION_CLASS + '" aria-label="' + label('edit') + '" title="' + label('edit') + '">' + icon('edit') + '</button>'
            + '<a href="' + esc(item.url) + '" download="' + esc(name) + '" class="' + ACTION_CLASS + '" aria-label="' + label('download') + '" title="' + label('download') + '">' + icon('download') + '</a>'
            + '<button type="button" data-media-copy class="' + ACTION_CLASS + '" aria-label="' + label('copy') + '" title="' + label('copy') + '">' + icon('copy') + '</button>'
            + '<button type="button" data-media-delete class="' + DELETE_CLASS + '" aria-label="' + label('delete') + '" title="' + label('delete') + '">' + icon('trash') + '</button>';
    }

    function thumb(item) {
        return esc(versioned(item.url, item.updated_at || item.created_at));
    }

    function gridCard(item) {
        var name = esc(displayName(item));
        return ''
            + '<li class="relative flex flex-col overflow-hidden border rounded-md border-slate-200 bg-white dark:border-zink-500 dark:bg-zink-700'
            + ' data-[selected]:ring-2 data-[selected]:ring-custom-500" data-media-id="' + esc(item.id) + '">'
            + '  <label class="absolute z-10 flex items-center justify-center rounded top-2 start-2 size-7 bg-white/90 dark:bg-zink-700/90">'
            + selection.checkboxHtml(item) + '</label>'
            + '  <button type="button" data-media-open tabindex="-1" aria-hidden="true" class="block w-full overflow-hidden aspect-[4/3] bg-slate-100 dark:bg-zink-600">'
            + '    <img src="' + thumb(item) + '" alt="" loading="lazy" decoding="async" class="object-cover w-full h-full transition-transform hover:scale-105">'
            + '  </button>'
            + '  <div class="flex flex-col flex-1 gap-0.5 p-2">'
            + '    <p data-media-name class="text-[13px] font-medium truncate text-slate-800 dark:text-zink-50" title="' + name + '">' + name + '</p>'
            + '    <p class="text-xs text-slate-500 dark:text-zink-300">' + esc(typeLabel(item)) + ' · ' + esc(humanSize(item.size_bytes)) + '</p>'
            + '    <div class="flex items-center justify-end gap-0.5 mt-auto pt-1">' + actionsHtml(item) + '</div>'
            + '  </div>'
            + '</li>';
    }

    function listRow(item, largest) {
        var name = esc(displayName(item));
        var share = largest > 0 ? Math.max(2, Math.round((item.size_bytes || 0) / largest * 100)) : 0;
        return ''
            + '<li class="grid items-center gap-3 px-3 py-2 border-t first:border-t-0 border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600/40'
            + ' data-[selected]:bg-custom-50 dark:data-[selected]:bg-custom-500/10'
            + ' grid-cols-[1rem_2.5rem_minmax(0,1fr)_auto] sm:grid-cols-[1rem_2.5rem_minmax(0,1fr)_4rem_5rem_auto]" data-media-id="' + esc(item.id) + '">'
            + '  ' + selection.checkboxHtml(item)
            + '  <button type="button" data-media-open tabindex="-1" aria-hidden="true" class="block overflow-hidden rounded size-10 bg-slate-100 dark:bg-zink-600">'
            + '    <img src="' + thumb(item) + '" alt="" loading="lazy" decoding="async" class="object-cover w-full h-full">'
            + '  </button>'
            + '  <div class="min-w-0">'
            + '    <p data-media-name class="text-[13px] font-medium truncate text-slate-800 dark:text-zink-50" title="' + name + '">' + name + '</p>'
            + '    <p class="text-xs sm:hidden text-slate-500 dark:text-zink-300">' + esc(typeLabel(item)) + ' · ' + esc(humanSize(item.size_bytes)) + '</p>'
            + '    <div class="hidden sm:block h-1.5 mt-1 overflow-hidden rounded max-w-44 bg-slate-100 dark:bg-zink-600" aria-hidden="true">'
            + '      <div class="h-full rounded bg-slate-400 dark:bg-zink-300" style="width:' + share + '%"></div>'
            + '    </div>'
            + '  </div>'
            + '  <span class="hidden text-xs sm:block text-slate-500 dark:text-zink-300">' + esc(typeLabel(item)) + '</span>'
            + '  <span class="hidden text-xs whitespace-nowrap sm:block text-slate-500 dark:text-zink-300">' + esc(humanSize(item.size_bytes)) + '</span>'
            + '  <div class="grid grid-cols-2 gap-0.5 sm:flex">' + actionsHtml(item) + '</div>'
            + '</li>';
    }

    function render() {
        if (view === 'list') {
            var largest = items.reduce(function (max, item) { return Math.max(max, item.size_bytes || 0); }, 0);
            list.innerHTML = items.length === 0 ? '' : ''
                + '<ul data-media-list class="overflow-hidden border rounded-md border-slate-200 dark:border-zink-500">'
                + items.map(function (item) { return listRow(item, largest); }).join('')
                + '</ul>';
        } else {
            list.innerHTML = items.length === 0 ? '' : ''
                + '<ul data-media-grid class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">'
                + items.map(gridCard).join('')
                + '</ul>';
        }
        updateMeta();
        selection.sync();
    }

    function updateMeta() {
        countEl.textContent = total;
        bytesEl.textContent = t('total', { size: humanSize(totalBytes) });

        var filtered = filters.q !== '' || filters.source !== '';
        empty.classList.toggle('hidden', items.length > 0);
        empty.querySelector('[data-empty-title]').textContent = t(filtered ? 'noMatchTitle' : 'emptyTitle');
        empty.querySelector('[data-empty-body]').textContent = t(filtered ? 'noMatchBody' : 'emptyBody');
        loadMoreWrap.classList.toggle('hidden', items.length >= total);
    }

    function setView(next) {
        view = next;
        try { window.localStorage.setItem(VIEW_KEY, view); } catch (e) {}
        list.setAttribute('data-view', view);
        root.querySelectorAll('[data-media-view]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-media-view') === view ? 'true' : 'false');
        });
        render();
    }

    var statusTimer;
    function showStatus(message, kind) {
        clearTimeout(statusTimer);
        statusEl.textContent = message;
        statusEl.classList.remove('hidden', 'text-red-600', 'dark:text-red-400', 'text-emerald-600', 'text-slate-500');
        statusEl.classList.add.apply(statusEl.classList, kind === 'error'
            ? ['text-red-600', 'dark:text-red-400']
            : (kind === 'ok' ? ['text-emerald-600'] : ['text-slate-500']));
        if (kind !== 'busy') {
            statusTimer = setTimeout(function () { statusEl.classList.add('hidden'); }, 5000);
        }
    }

    function indexOf(id) {
        for (var i = 0; i < items.length; i++) {
            if (String(items[i].id) === String(id)) return i;
        }
        return -1;
    }

    function showCard(item) {
        var at = indexOf(item.id);
        if (at === -1) {
            items.unshift(item);
            total += 1;
            totalBytes += item.size_bytes || 0;
        } else {
            totalBytes += (item.size_bytes || 0) - (items[at].size_bytes || 0);
            items[at] = item;
        }
        render();
    }

    function removeItems(ids) {
        ids.forEach(function (id) {
            var at = indexOf(id);
            if (at === -1) return;
            totalBytes -= items[at].size_bytes || 0;
            total = Math.max(0, total - 1);
            items.splice(at, 1);
        });
        render();
    }

    function deleteItem(id) {
        return api(route('destroy', id), {}).then(function () { removeItems([id]); });
    }

    // The server empties banner, logo and featured or social image slots on delete,
    // but an image inside a post's text stays there and breaks, so say which is which.
    function confirmDelete(usages) {
        if (usages.length === 0) return cpConfirm(t('deleteConfirm'), { tone: 'danger' });

        var places = usages.map(function (use) { return '• ' + use.label + ' (' + use.context + ')'; }).join('\n');
        var inBody = usages.some(function (use) { return use.in_body; });
        var message = t('deleteUsed') + '\n\n' + places + '\n\n' + t('deleteLeaves')
            + (inBody ? ' ' + t('deleteInBody') : '') + '\n\n' + t('deleteAnyway');

        return cpConfirm(message, { tone: 'danger' });
    }

    function copyLink(item, button) {
        var url = new URL(item.url, window.location.origin).href;
        navigator.clipboard.writeText(url)
            .then(function () {
                button.innerHTML = icon('check');
                showStatus(t('copied'), 'ok');
                setTimeout(function () { button.innerHTML = icon('copy'); }, 1500);
            })
            .catch(function () { showStatus(t('copyFailed'), 'error'); });
    }

    function loadItems(replace) {
        var params = new URLSearchParams({
            q: filters.q,
            source: filters.source,
            sort: filters.sort,
            dir: filters.dir,
            limit: pageSize,
            offset: replace ? 0 : items.length,
        });

        return api(route('list') + '?' + params.toString()).then(function (data) {
            items = (replace ? [] : items).concat(data.items || []);
            total = data.total || 0;
            totalBytes = data.total_bytes || 0;
            if (replace) selection.clear();
            render();
        });
    }

    function reloadItems() {
        loadItems(true).catch(function (error) { showStatus(error.message, 'error'); });
    }

    var searchInput = document.getElementById('media-q');
    var searchTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { filters.q = searchInput.value.trim(); reloadItems(); }, 250);
    });
    document.getElementById('media-source').addEventListener('change', function (e) {
        filters.source = e.target.value;
        reloadItems();
    });
    document.getElementById('media-sort').addEventListener('change', function (e) {
        filters.sort = e.target.value;
        reloadItems();
    });

    var dirButton = document.getElementById('media-sort-dir');
    dirButton.addEventListener('click', function () {
        filters.dir = filters.dir === 'desc' ? 'asc' : 'desc';
        dirButton.querySelector('[data-dir-icon="desc"]').classList.toggle('hidden', filters.dir !== 'desc');
        dirButton.querySelector('[data-dir-icon="asc"]').classList.toggle('hidden', filters.dir !== 'asc');
        var next = t(filters.dir === 'desc' ? 'sortAsc' : 'sortDesc');
        dirButton.setAttribute('aria-label', next);
        dirButton.setAttribute('title', next);
        reloadItems();
    });

    root.querySelectorAll('[data-media-view]').forEach(function (button) {
        button.addEventListener('click', function () { setView(button.getAttribute('data-media-view')); });
    });

    document.getElementById('media-load-more').addEventListener('click', function () {
        loadItems(false).catch(function (error) { showStatus(error.message, 'error'); });
    });

    list.addEventListener('click', function (e) {
        var row = e.target.closest('[data-media-id]');
        if (!row) return;
        var id = row.getAttribute('data-media-id');
        var item = items[indexOf(id)];
        if (!item) return;

        if (e.target.closest('[data-media-delete]')) {
            api(route('details', id))
                .then(function (details) { return confirmDelete(details.usages || []); })
                .then(function (ok) { if (ok) return deleteItem(id); })
                .catch(function (error) { showStatus(error.message, 'error'); });
        } else if (e.target.closest('[data-media-copy]')) {
            copyLink(item, e.target.closest('[data-media-copy]'));
        } else if (e.target.closest('[data-media-open]')) {
            editor.open(id, row.querySelector('[data-media-open]:not([aria-hidden])'));
        }
    });

    var dropArea = document.getElementById('media-drop');
    var fileInput = document.getElementById('media-file-input');

    var queue = createMediaUploadQueue({
        container: document.getElementById('media-upload-feedback'),
        url: route('upload'),
        csrfToken: csrfToken,
        extensions: root.getAttribute('data-media-types').split(',').map(function (ext) { return ext.replace('.', ''); }),
        maxBytes: parseInt(root.getAttribute('data-media-max-bytes'), 10),
        typeNames: root.getAttribute('data-media-type-names'),
        sizeLabel: root.getAttribute('data-media-max-label'),
        t: t,
        esc: esc,
        onUploaded: showCard,
    });

    fileInput.addEventListener('change', function () {
        queue.add(fileInput.files);
        fileInput.value = '';
    });
    ['dragenter', 'dragover'].forEach(function (type) {
        dropArea.addEventListener(type, function (e) {
            e.preventDefault();
            dropArea.setAttribute('data-dragging', '');
        });
    });
    dropArea.addEventListener('dragleave', function (e) {
        if (dropArea.contains(e.relatedTarget)) return;
        dropArea.removeAttribute('data-dragging');
    });
    dropArea.addEventListener('drop', function (e) {
        e.preventDefault();
        dropArea.removeAttribute('data-dragging');
        if (e.dataTransfer && e.dataTransfer.files) queue.add(e.dataTransfer.files);
    });

    var busy = false;
    function runAction(workingLabel, request) {
        if (busy) return;
        busy = true;
        showStatus(workingLabel + '…', 'busy');

        request()
            .catch(function (error) { showStatus(error.message, 'error'); })
            .finally(function () { busy = false; });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-media-rescan]')) {
            runAction(t('rescanning'), function () {
                return api(route('rescan'), {}).then(function (data) {
                    if (data.added === 0) {
                        showStatus(t('rescanNone'), 'ok');
                        return;
                    }
                    showStatus(t('rescanDone', { count: data.added }), 'ok');
                    reloadItems();
                });
            });
        } else if (e.target.closest('[data-media-optimize]')) {
            cpConfirm(t('optimizeConfirm'), { tone: 'warning' }).then(function (ok) {
                if (!ok) return;
                runAction(t('optimizing'), function () {
                    return api(route('optimize'), {}).then(function (data) {
                        if (!data.processed) {
                            showStatus(t('optimizeNone'), 'ok');
                            return;
                        }
                        showStatus(t('optimizeDone', { count: data.processed, size: humanSize(data.bytes_saved) }), 'ok');
                        reloadItems();
                    });
                });
            });
        }
    });

    var selection = createMediaSelection({
        root: root,
        list: list,
        api: api,
        route: route,
        t: t,
        esc: esc,
        humanSize: humanSize,
        displayName: displayName,
        items: function () { return items; },
        maxItems: parseInt(root.getAttribute('data-media-bulk-max'), 10),
        notify: showStatus,
        onDeleted: removeItems,
    });

    var editor = createMediaEditor({
        api: api,
        route: route,
        t: t,
        sources: sources,
        notify: function (message) { showStatus(message, 'error'); },
        esc: esc,
        humanSize: humanSize,
        versioned: versioned,
        displayName: displayName,
        showCard: showCard,
        deleteItem: deleteItem,
        confirmDelete: confirmDelete,
    });

    setView(view);

    var autoEditId = parseInt(root.getAttribute('data-media-auto-edit'), 10) || 0;
    if (autoEditId > 0) editor.open(autoEditId);
})();
