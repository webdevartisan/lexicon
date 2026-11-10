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
    // Tailwind's sm breakpoint, where the phone layout ends
    var phone = window.matchMedia('(max-width: 639px)');

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

    function thumbUrl(item) {
        return versioned(item.url, item.updated_at || item.created_at);
    }

    var cards = createMediaCards({
        t: t,
        esc: esc,
        humanSize: humanSize,
        displayName: displayName,
        typeLabel: typeLabel,
        thumbUrl: thumbUrl,
        checkbox: function (item) { return selection.checkboxHtml(item); },
    });

    function render() {
        if (items.length === 0) {
            list.innerHTML = '';
        } else {
            list.innerHTML = view === 'list' ? cards.list(items) : cards.grid(items);
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
        viewButtons.forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-media-view') === view ? 'true' : 'false');
        });
        render();
    }

    // Work in progress stays on the page's own line, results go to a toast
    function showStatus(message, kind) {
        statusEl.textContent = message;
        statusEl.classList.toggle('hidden', kind !== 'busy');
        if (kind !== 'busy') cpToast(message, kind === 'error' ? 'error' : 'success');
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
        return api(route('destroy', id), {}).then(function () {
            removeItems([id]);
            showStatus(t('deleted'), 'ok');
        });
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

    function askToDelete(id) {
        api(route('details', id))
            .then(function (details) { return confirmDelete(details.usages || []); })
            .then(function (ok) { if (ok) return deleteItem(id); })
            .catch(function (error) { showStatus(error.message, 'error'); });
    }

    function copyLink(item, button) {
        var url = new URL(item.url, window.location.origin).href;
        navigator.clipboard.writeText(url)
            .then(function () {
                showStatus(t('copied'), 'ok');
                if (!button) return;
                button.innerHTML = cards.icon('check');
                setTimeout(function () { button.innerHTML = cards.icon('copy'); }, 1500);
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

    var viewButtons = root.querySelectorAll('[data-media-view]');
    viewButtons.forEach(function (button) {
        button.addEventListener('click', function () { setView(button.getAttribute('data-media-view')); });
    });

    var filterControls = root.querySelector('[data-media-filters]');
    var filterHome = filterControls.parentNode;
    var filterSheet = document.getElementById('media-filters-sheet');
    root.querySelector('[data-media-filters-open]').addEventListener('click', function (e) {
        filterSheet.querySelector('[data-media-filters-slot]').appendChild(filterControls);
        cpModal.open('media-filters-sheet', e.currentTarget);
    });
    filterSheet.addEventListener('modal:close', function () { filterHome.appendChild(filterControls); });

    document.getElementById('media-load-more').addEventListener('click', function () {
        loadItems(false).catch(function (error) { showStatus(error.message, 'error'); });
    });

    // Clicks inside a checkbox label tick it natively, so they are left alone here
    list.addEventListener('click', function (e) {
        var row = e.target.closest('[data-media-id]');
        if (!row || e.target.closest('label')) return;
        var id = row.getAttribute('data-media-id');
        var item = items[indexOf(id)];
        if (!item) return;

        if (selection.selecting()) {
            selection.toggle(id);
        } else if (phone.matches) {
            sheet.open(item, row.querySelector('[data-media-more]'));
        } else if (e.target.closest('[data-media-delete]')) {
            askToDelete(id);
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

    root.querySelector('[data-media-upload]').addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        queue.add(fileInput.files);
        fileInput.value = '';
        if (phone.matches) root.scrollIntoView({ block: 'start' });
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
        phone: phone,
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
        notify: showStatus,
        esc: esc,
        humanSize: humanSize,
        versioned: versioned,
        displayName: displayName,
        showCard: showCard,
        deleteItem: deleteItem,
        confirmDelete: confirmDelete,
    });

    var sheet = createMediaItemSheet({
        id: 'media-item-sheet',
        thumb: thumbUrl,
        displayName: displayName,
        meta: function (item) { return typeLabel(item) + ' · ' + humanSize(item.size_bytes); },
        onEdit: function (item, trigger) { editor.open(item.id, trigger); },
        onCopy: function (item) { copyLink(item); },
        onDelete: function (item) { askToDelete(item.id); },
    });

    setView(view);

    var autoEditId = parseInt(root.getAttribute('data-media-auto-edit'), 10) || 0;
    if (autoEditId > 0) editor.open(autoEditId);
})();
