/**
 * The per-blog media library page: the card grid with search, filters, paging
 * and uploads, and the Rescan and Shrink actions. Clicking a card opens the
 * image editor from media-editor.js, which gets its helpers handed in here.
 *
 * The page hands over its first batch of items as JSON, so every card is built by
 * cardHtml() whether it came from the first render, paging, an upload or an edit.
 */
(function () {
    var root = document.querySelector('[data-media-library]');
    if (!root) return;

    var base = root.getAttribute('data-media-base');
    var csrfToken = root.querySelector('input[name="_token"]').value;

    /* ---------- Shared helpers ---------- */

    // GET when there are no fields, otherwise a CSRF-protected POST. Resolves with
    // the payload's data and rejects with a message that can be shown as is.
    function api(path, fields) {
        var options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
        if (fields) {
            var body = new FormData();
            body.append('_token', csrfToken);
            Object.keys(fields).forEach(function (key) {
                if (fields[key] !== null && fields[key] !== undefined) body.append(key, fields[key]);
            });
            options.method = 'POST';
            options.headers['X-CSRF-TOKEN'] = csrfToken;
            options.body = body;
        }

        return fetch(base + path, options)
            .then(function (response) { return response.json().catch(function () { return null; }); })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.error) || 'Something went wrong. Please try again.');
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

    /* ---------- Grid ---------- */

    var grid = document.getElementById('media-grid');
    var empty = document.getElementById('media-empty');
    var totalEl = root.querySelector('[data-media-total]');
    var loadMoreWrap = document.getElementById('media-load-more-wrap');
    var filters = { q: '', source: '', sort: 'newest' };
    var pageSize = 24;
    var total = 0;

    function cardHtml(item) {
        var name = esc(displayName(item));
        var thumb = esc(versioned(item.url, item.updated_at || item.created_at));
        return ''
            + '<figure class="group/media relative border border-slate-200 dark:border-zink-500 rounded-md overflow-hidden bg-slate-50 dark:bg-zink-700" data-media-id="' + esc(item.id) + '">'
            + '  <button type="button" data-media-open aria-label="Edit ' + name + '" class="block w-full aspect-square overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-custom-500">'
            + '    <img src="' + thumb + '" alt="" loading="lazy" class="w-full h-full object-cover transition-transform group-hover/media:scale-105">'
            + '  </button>'
            + '  <figcaption class="px-2 py-1.5 text-[11px] text-slate-600 dark:text-zink-200 truncate" title="' + name + '">' + name + '</figcaption>'
            + '  <div class="pointer-events-none absolute inset-x-0 bottom-0 px-2 py-1.5 bg-gradient-to-t from-black/60 to-transparent text-[10px] text-white flex justify-between opacity-0 group-hover/media:opacity-100 transition-opacity">'
            + '    <span>' + esc(String(item.extension || '').toUpperCase()) + '</span><span>' + esc(humanSize(item.size_bytes)) + '</span>'
            + '  </div>'
            + '  <button type="button" data-media-delete aria-label="Delete ' + name + '" title="Delete" class="absolute top-1.5 right-1.5 p-1 rounded-md bg-white/90 text-red-600 hover:bg-red-50 opacity-0 group-hover/media:opacity-100 transition-opacity focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">'
            + '    <i data-lucide="trash-2" class="size-3.5"></i>'
            + '  </button>'
            + '</figure>';
    }

    function cardFor(id) {
        return grid.querySelector('[data-media-id="' + Number(id) + '"]');
    }

    function setTotal(count) {
        total = Math.max(0, count);
        totalEl.textContent = total + ' item' + (total === 1 ? '' : 's');
        empty.classList.toggle('hidden', grid.children.length > 0);
        loadMoreWrap.classList.toggle('hidden', grid.children.length >= total);
    }

    function render(html, position) {
        grid.insertAdjacentHTML(position, html);
        if (window.lucide) window.lucide.createIcons();
    }

    // Uploads and edits can come back as an item the grid already shows (the same
    // file uploaded twice, an image overwritten in place), so replace before adding.
    function showCard(item) {
        var existing = cardFor(item.id);
        if (existing) {
            existing.insertAdjacentHTML('afterend', cardHtml(item));
            existing.remove();
            if (window.lucide) window.lucide.createIcons();
            return;
        }
        render(cardHtml(item), 'afterbegin');
        setTotal(total + 1);
    }

    function removeCard(id) {
        var card = cardFor(id);
        if (!card) return;
        card.remove();
        setTotal(total - 1);
    }

    function deleteItem(id) {
        return api('/' + id + '/destroy', {}).then(function () { removeCard(id); });
    }

    // The next page starts after the cards on screen, which stays right after
    // uploads and deletes shift the list.
    function loadItems(replace) {
        var params = new URLSearchParams({
            q: filters.q,
            source: filters.source,
            sort: filters.sort,
            limit: pageSize,
            offset: replace ? 0 : grid.children.length,
        });

        return api('/list?' + params.toString()).then(function (data) {
            if (replace) grid.innerHTML = '';
            render((data.items || []).map(cardHtml).join(''), 'beforeend');
            setTotal(data.total || 0);
        });
    }

    function reloadItems() {
        loadItems(true).catch(function (error) { window.alert(error.message); });
    }

    var initial = JSON.parse(root.querySelector('[data-media-initial]').textContent);
    render(initial.items.map(cardHtml).join(''), 'beforeend');
    setTotal(initial.total);

    var searchInput = document.getElementById('media-q');
    var searchTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { filters.q = searchInput.value; reloadItems(); }, 250);
    });
    document.getElementById('media-source').addEventListener('change', function (e) { filters.source = e.target.value; reloadItems(); });
    document.getElementById('media-sort').addEventListener('change', function (e) { filters.sort = e.target.value; reloadItems(); });
    document.getElementById('media-load-more').addEventListener('click', function () {
        loadItems(false).catch(function (error) { window.alert(error.message); });
    });

    grid.addEventListener('click', function (e) {
        var card = e.target.closest('[data-media-id]');
        if (!card) return;
        var id = card.getAttribute('data-media-id');

        if (e.target.closest('[data-media-delete]')) {
            cpConfirm('Delete this image? Posts that reference it will show a broken image.').then(function (ok) {
                if (ok) deleteItem(id).catch(function (error) { window.alert(error.message); });
            });
        } else if (e.target.closest('[data-media-open]')) {
            editor.open(id, e.target.closest('[data-media-open]'));
        }
    });

    /* ---------- Upload ---------- */

    var uploadToggle = document.getElementById('media-upload-toggle');
    var uploadPanel = document.getElementById('media-upload-panel');
    var dropArea = document.getElementById('media-uploader');
    var fileInput = document.getElementById('media-uploader-input');
    var feedback = document.getElementById('media-upload-feedback');

    function uploadFeedback(message, ok) {
        var line = document.createElement('div');
        line.className = ok ? 'text-emerald-600' : 'text-red-600';
        line.textContent = message;
        feedback.appendChild(line);
        setTimeout(function () { line.remove(); }, 4000);
    }

    function upload(files) {
        Array.prototype.forEach.call(files, function (file) {
            api('', { file: file })
                .then(function (item) {
                    showCard(item);
                    uploadFeedback(file.name + ' uploaded', true);
                })
                .catch(function (error) { uploadFeedback(file.name + ': ' + error.message, false); });
        });
    }

    uploadToggle.addEventListener('click', function () {
        var open = uploadPanel.classList.toggle('hidden') === false;
        uploadToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    dropArea.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        upload(fileInput.files);
        fileInput.value = '';
    });
    ['dragenter', 'dragover'].forEach(function (type) {
        dropArea.addEventListener(type, function (e) {
            e.preventDefault();
            dropArea.classList.add('border-custom-500');
        });
    });
    ['dragleave', 'drop'].forEach(function (type) {
        dropArea.addEventListener(type, function (e) {
            e.preventDefault();
            dropArea.classList.remove('border-custom-500');
        });
    });
    dropArea.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files) upload(e.dataTransfer.files);
    });

    /* ---------- Library-wide actions ---------- */

    // Shows the working label while the request runs and puts the button back after
    function runAction(button, workingLabel, request) {
        var label = button.querySelector('span');
        var idle = label.textContent;
        label.textContent = workingLabel;
        button.disabled = true;

        return request()
            .catch(function (error) { window.alert(error.message); })
            .finally(function () {
                label.textContent = idle;
                button.disabled = false;
            });
    }

    var rescanButton = document.getElementById('media-rescan');
    rescanButton.addEventListener('click', function () {
        runAction(rescanButton, 'Rescanning…', function () {
            return api('/rescan', {}).then(function (data) {
                if (data.added === 0) {
                    window.alert('Everything on disk is already in the library.');
                    return;
                }
                window.alert('Found and indexed ' + data.added + ' new image(s).');
                reloadItems();
            });
        });
    });

    var optimizeButton = document.getElementById('media-optimize');
    optimizeButton.addEventListener('click', function () {
        cpConfirm('Shrink every image larger than 2048px and re-save it in place? Links to these images keep working. This cannot be undone.').then(function (ok) {
            if (!ok) return;
            runAction(optimizeButton, 'Optimizing…', function () {
                return api('/optimize', {}).then(function (data) {
                    if (!data.processed) {
                        window.alert('Nothing to shrink. Every image is already within size.');
                        return;
                    }
                    window.alert('Shrank ' + data.processed + ' image(s) and saved ' + humanSize(data.bytes_saved) + '.');
                    reloadItems();
                });
            });
        });
    });

    /* ---------- Editor ---------- */

    var editor = createMediaEditor({
        api: api,
        esc: esc,
        humanSize: humanSize,
        versioned: versioned,
        displayName: displayName,
        showCard: showCard,
        deleteItem: deleteItem,
    });

    // Arriving from an "Edit" link elsewhere (the appearance page) opens straight onto that image
    var autoEditId = parseInt(root.getAttribute('data-media-auto-edit'), 10) || 0;
    if (autoEditId > 0) editor.open(autoEditId);
})();
