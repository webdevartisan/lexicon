/**
 * Media picker modal.
 *
 * Drop a `data-media-picker` button next to any image field, set:
 *   data-media-picker      blog id
 *   data-media-target      id of the hidden/text input to receive the URL
 *   data-media-preview     (optional) id of an <img> to update
 *   data-media-csrf-input  (optional) selector for the CSRF token input
 *                          (defaults to the nearest form's _token)
 *
 * MediaPicker.open(blogId, { onSelect, csrfToken }) is also exposed for
 * cases like TinyMCE that want to drive the picker from JS.
 */
(function () {
    var MOUNT_ID = 'media-picker-modal';
    var state = {
        blogId: null,
        items: [],
        total: 0,
        offset: 0,
        limit: 24,
        q: '',
        sort: 'newest',
        csrfToken: '',
        onSelect: null,
    };

    function ensureMount() {
        var el = document.getElementById(MOUNT_ID);
        if (el) return el;

        el = document.createElement('div');
        el.id = MOUNT_ID;
        el.className = 'fixed inset-0 z-[1100] hidden';
        el.innerHTML = ''
            + '<div class="absolute inset-0 bg-black/50" data-picker-close></div>'
            + '<div class="absolute inset-x-4 top-10 bottom-10 md:inset-x-auto md:left-1/2 md:right-auto md:-translate-x-1/2 md:w-[min(900px,90vw)] md:max-h-[80vh] bg-white dark:bg-zink-700 rounded-lg shadow-xl flex flex-col overflow-hidden">'
            + '  <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200 dark:border-zink-500">'
            + '    <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-50">Pick an image</h3>'
            + '    <button type="button" class="p-1 text-slate-500 hover:text-slate-700 dark:hover:text-zink-100" data-picker-close aria-label="Close">'
            + '      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>'
            + '    </button>'
            + '  </header>'
            + '  <div class="px-4 py-3 border-b border-slate-200 dark:border-zink-500 flex items-center gap-2">'
            + '    <input type="search" data-picker-q placeholder="Search filename…" class="form-input grow border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">'
            + '    <select data-picker-sort class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">'
            + '      <option value="newest">Newest</option>'
            + '      <option value="oldest">Oldest</option>'
            + '      <option value="largest">Largest</option>'
            + '    </select>'
            + '    <label class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors cursor-pointer">'
            + '      <span>Upload</span>'
            + '      <input type="file" data-picker-upload class="hidden" accept="image/*">'
            + '    </label>'
            + '  </div>'
            + '  <div class="flex-1 overflow-y-auto p-4">'
            + '    <div data-picker-grid class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"></div>'
            + '    <p data-picker-empty class="text-sm text-slate-500 dark:text-zink-300 py-10 text-center hidden">No images yet — use Upload to add one.</p>'
            + '    <div data-picker-more-wrap class="flex justify-center mt-4 hidden">'
            + '      <button type="button" data-picker-more class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">Load more</button>'
            + '    </div>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(el);
        wireMount(el);
        return el;
    }

    function wireMount(el) {
        el.querySelectorAll('[data-picker-close]').forEach(function (btn) {
            btn.addEventListener('click', close);
        });
        var q = el.querySelector('[data-picker-q]');
        var sort = el.querySelector('[data-picker-sort]');
        var grid = el.querySelector('[data-picker-grid]');
        var more = el.querySelector('[data-picker-more]');
        var upload = el.querySelector('[data-picker-upload]');
        var debounce;

        q.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { state.q = q.value; reload(); }, 250);
        });
        sort.addEventListener('change', function () { state.sort = sort.value; reload(); });
        more.addEventListener('click', function () { fetchPage(false); });

        grid.addEventListener('click', function (e) {
            var card = e.target.closest('[data-picker-pick]');
            if (!card) return;
            var url = card.getAttribute('data-picker-pick');
            if (state.onSelect) state.onSelect({
                url: url,
                id: card.getAttribute('data-picker-id'),
                alt: card.getAttribute('data-picker-alt') || '',
            });
            close();
        });

        upload.addEventListener('change', function () {
            if (!upload.files || upload.files.length === 0) return;
            uploadOne(upload.files[0]).then(function () {
                upload.value = '';
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !el.classList.contains('hidden')) close();
        });
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Cache-bust the thumbnail with the row's updated_at so an image edited in place
    // (same URL, new bytes) shows the processed version instead of the browser's cache.
    // The picked value stays the clean URL — only the preview <img> is versioned.
    function versioned(url, item) {
        var stamp = item.updated_at || item.created_at || '';
        if (!stamp) return url;
        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(stamp);
    }

    function cardHtml(item) {
        var url = esc(item.url);
        var id = esc(item.id);
        // Prefer the friendly/renamed display name over the hashed disk filename.
        var name = esc(item.original_name || item.filename || '');
        var alt = esc(item.alt_text || '');
        var thumb = esc(versioned(item.url, item));
        return ''
            + '<button type="button" data-picker-pick="' + url + '" data-picker-id="' + id + '" data-picker-alt="' + alt + '" '
            + 'class="group relative border border-slate-200 dark:border-zink-500 rounded-md overflow-hidden bg-slate-50 dark:bg-zink-600 hover:ring-2 hover:ring-custom-500 transition-shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">'
            + '  <div class="aspect-square overflow-hidden">'
            + '    <img src="' + thumb + '" alt="' + alt + '" loading="lazy" class="w-full h-full object-cover transition-transform group-hover:scale-105">'
            + '  </div>'
            + '  <span class="block px-2 py-1.5 text-[11px] text-slate-600 dark:text-zink-200 truncate text-left" title="' + name + '">' + name + '</span>'
            + '</button>';
    }

    function fetchPage(replace) {
        var mount = ensureMount();
        var grid = mount.querySelector('[data-picker-grid]');
        var empty = mount.querySelector('[data-picker-empty]');
        var moreWrap = mount.querySelector('[data-picker-more-wrap]');

        var params = new URLSearchParams({
            q: state.q,
            sort: state.sort,
            limit: state.limit,
            offset: state.offset,
        });

        return fetch('/dashboard/blog/' + state.blogId + '/media/list?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.success) return;
                var data = payload.data || {};
                state.total = data.total || 0;
                if (replace) grid.innerHTML = '';
                (data.items || []).forEach(function (item) {
                    grid.insertAdjacentHTML('beforeend', cardHtml(item));
                });
                state.offset = grid.children.length;

                empty.classList.toggle('hidden', grid.children.length > 0);
                moreWrap.classList.toggle('hidden', grid.children.length >= state.total);
            });
    }

    function reload() {
        state.offset = 0;
        fetchPage(true);
    }

    function uploadOne(file) {
        var form = new FormData();
        form.append('file', file);
        form.append('_token', state.csrfToken);
        return fetch('/dashboard/blog/' + state.blogId + '/media', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': state.csrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.success) {
                    var mount = ensureMount();
                    var grid = mount.querySelector('[data-picker-grid]');
                    grid.insertAdjacentHTML('afterbegin', cardHtml(payload.data));
                    state.total += 1;
                    mount.querySelector('[data-picker-empty]').classList.add('hidden');
                }
            });
    }

    function open(blogId, opts) {
        opts = opts || {};
        state.blogId = blogId;
        state.q = '';
        state.sort = 'newest';
        state.offset = 0;
        state.csrfToken = opts.csrfToken || resolveTokenFromDom();
        state.onSelect = opts.onSelect || null;

        var mount = ensureMount();
        mount.classList.remove('hidden');
        var q = mount.querySelector('[data-picker-q]');
        var sort = mount.querySelector('[data-picker-sort]');
        if (q) q.value = '';
        if (sort) sort.value = 'newest';
        reload();
    }

    function close() {
        var mount = document.getElementById(MOUNT_ID);
        if (mount) mount.classList.add('hidden');
        state.onSelect = null;
    }

    function resolveTokenFromDom() {
        var any = document.querySelector('input[name="_token"]');
        return any ? any.value : '';
    }

    // Wire any buttons declared in markup.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-media-picker]');
        if (!btn) return;
        e.preventDefault();

        var blogId = btn.getAttribute('data-media-picker');
        var targetId = btn.getAttribute('data-media-target');
        var previewId = btn.getAttribute('data-media-preview');
        var tokenSel = btn.getAttribute('data-media-csrf-input');

        var token = '';
        if (tokenSel) {
            var tin = document.querySelector(tokenSel);
            if (tin) token = tin.value;
        } else {
            var form = btn.closest('form');
            var fin = form && form.querySelector('input[name="_token"]');
            token = fin ? fin.value : resolveTokenFromDom();
        }

        open(blogId, {
            csrfToken: token,
            onSelect: function (picked) {
                var target = targetId && document.getElementById(targetId);
                if (target) {
                    target.value = picked.url;
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (previewId) {
                    var img = document.getElementById(previewId);
                    if (img) img.src = picked.url;
                }
                // Optionally carry the media's alt text into a companion field, but
                // only when it's empty so we never overwrite what the user typed.
                var altTargetId = btn.getAttribute('data-media-alt-target');
                if (altTargetId) {
                    var altEl = document.getElementById(altTargetId);
                    if (altEl && picked.alt && !altEl.value.trim()) {
                        altEl.value = picked.alt;
                        altEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            },
        });
    });

    window.MediaPicker = { open: open, close: close };
})();
