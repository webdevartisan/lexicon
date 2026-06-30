{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Media Library{% endblock %}
{% block subtitle %}Every image for this blog in one place — upload, find, and clean up.{% endblock %}
{% block body %}
<?php
// Tiny helper for the size label on each card, kept inline so the
// list page is fully self-contained.
$humanSize = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);

    return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto"
     data-media-blog-id="{{ blog.id }}">
    <form class="hidden">{{ csrf_field() }}</form>

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/blog/{{ blog.id }}/show"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="arrow-left" class="size-4"></i>
            <span>Back to blog</span>
        </a>
        <span class="text-xs text-slate-500 dark:text-zink-300" data-media-total>
            <?= (int) ($total ?? 0) ?> item<?= ((int) ($total ?? 0)) === 1 ? '' : 's' ?>
        </span>
    </div>

    <!-- Upload area -->
    <section class="card mb-5">
        <div class="card-body">
            <div id="media-uploader"
                 class="border-2 border-dashed border-slate-200 dark:border-zink-500 rounded-lg p-6 text-center cursor-pointer hover:border-custom-400 transition-colors">
                <i data-lucide="upload-cloud" class="size-8 text-slate-400 dark:text-zink-300 mx-auto mb-2"></i>
                <p class="text-sm font-medium text-slate-700 dark:text-zink-100">Drop images here or click to upload</p>
                <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">PNG, JPG, WebP, SVG or GIF · up to 5 MB each</p>
                <input type="file" id="media-uploader-input" class="hidden" accept="image/*" multiple>
            </div>
            <div id="media-upload-feedback" class="mt-3 space-y-1 text-xs"></div>
        </div>
    </section>

    <!-- Filters -->
    <section class="card mb-5">
        <div class="card-body">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative grow min-w-[200px]">
                    <i data-lucide="search" class="size-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="search" id="media-q" placeholder="Search filename…"
                        class="form-input w-full pl-9 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                </div>
                <select id="media-source"
                    class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                    <option value="">All sources</option>
                    <option value="upload">Library uploads</option>
                    <option value="post_image">Post images</option>
                    <option value="branding">Branding</option>
                    <option value="backfill">Imported</option>
                </select>
                <select id="media-sort"
                    class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="largest">Largest first</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Grid -->
    <section class="card">
        <div class="card-body">
            <div id="media-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <?php foreach (($mediaItems ?? []) as $item) { ?>
                <figure class="group relative border border-slate-200 dark:border-zink-500 rounded-md overflow-hidden bg-slate-50 dark:bg-zink-700"
                        data-media-id="<?= (int) $item['id'] ?>">
                    <div class="aspect-square overflow-hidden">
                        <img src="<?= e($item['url']) ?>" alt="" loading="lazy"
                             class="w-full h-full object-cover transition-transform group-hover:scale-105">
                    </div>
                    <figcaption class="px-2 py-1.5 text-[11px] text-slate-600 dark:text-zink-200 truncate" title="<?= e($item['filename']) ?>">
                        <?= e($item['filename']) ?>
                    </figcaption>
                    <div class="absolute inset-x-0 bottom-0 px-2 py-1.5 bg-gradient-to-t from-black/60 to-transparent text-[10px] text-white flex justify-between opacity-0 group-hover:opacity-100 transition-opacity">
                        <span><?= e(strtoupper((string) $item['extension'])) ?></span>
                        <span><?= e($humanSize((int) $item['size_bytes'])) ?></span>
                    </div>
                    <button type="button" title="Delete"
                            data-media-delete="<?= (int) $item['id'] ?>"
                            class="absolute top-1.5 right-1.5 p-1 rounded-md bg-white/90 text-red-600 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                        <i data-lucide="trash-2" class="size-3.5"></i>
                    </button>
                </figure>
                <?php } ?>
            </div>

            <p id="media-empty" class="text-sm text-slate-500 dark:text-zink-300 py-10 text-center <?= !empty($mediaItems) ? 'hidden' : '' ?>">
                Nothing here yet. Drop your first image above to start the library.
            </p>

            <div class="flex justify-center mt-5 <?= ((int) ($total ?? 0)) <= count($mediaItems ?? []) ? 'hidden' : '' ?>" id="media-load-more-wrap">
                <button type="button" id="media-load-more"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    <i data-lucide="chevron-down" class="size-4"></i> Load more
                </button>
            </div>
        </div>
    </section>

</div>
{% endblock %}
{% block scripts %}
<script>
(function () {
    var root = document.querySelector('[data-media-blog-id]');
    if (!root) return;

    var blogId = root.getAttribute('data-media-blog-id');
    var tokenInput = root.querySelector('input[name="_token"]');
    var csrfToken = tokenInput ? tokenInput.value : '';

    var grid = document.getElementById('media-grid');
    var empty = document.getElementById('media-empty');
    var totalEl = document.querySelector('[data-media-total]');
    var loadMoreWrap = document.getElementById('media-load-more-wrap');
    var loadMoreBtn = document.getElementById('media-load-more');

    var qInput = document.getElementById('media-q');
    var sourceSel = document.getElementById('media-source');
    var sortSel = document.getElementById('media-sort');

    var state = { q: '', source: '', sort: 'newest', offset: 0, limit: 24, total: 0 };

    function humanSize(bytes) {
        bytes = bytes || 0;
        if (bytes <= 0) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
        var n = bytes / Math.pow(1024, i);
        return (i === 0 ? n.toFixed(0) : n.toFixed(1)) + ' ' + units[i];
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cardHtml(item) {
        var id = esc(item.id);
        var url = esc(item.url);
        var name = esc(item.filename || '');
        var ext = esc((item.extension || '').toUpperCase());
        var size = esc(humanSize(item.size_bytes));
        return ''
            + '<figure class="group relative border border-slate-200 dark:border-zink-500 rounded-md overflow-hidden bg-slate-50 dark:bg-zink-700" data-media-id="' + id + '">'
            + '  <div class="aspect-square overflow-hidden">'
            + '    <img src="' + url + '" alt="" loading="lazy" class="w-full h-full object-cover transition-transform group-hover:scale-105">'
            + '  </div>'
            + '  <figcaption class="px-2 py-1.5 text-[11px] text-slate-600 dark:text-zink-200 truncate" title="' + name + '">' + name + '</figcaption>'
            + '  <div class="absolute inset-x-0 bottom-0 px-2 py-1.5 bg-gradient-to-t from-black/60 to-transparent text-[10px] text-white flex justify-between opacity-0 group-hover:opacity-100 transition-opacity">'
            + '    <span>' + ext + '</span><span>' + size + '</span>'
            + '  </div>'
            + '  <button type="button" title="Delete" data-media-delete="' + id + '" class="absolute top-1.5 right-1.5 p-1 rounded-md bg-white/90 text-red-600 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">'
            + '    <i data-lucide="trash-2" class="size-3.5"></i>'
            + '  </button>'
            + '</figure>';
    }

    function fetchList(replace) {
        var params = new URLSearchParams({
            q: state.q,
            source: state.source,
            sort: state.sort,
            limit: state.limit,
            offset: state.offset,
        });

        return fetch('/dashboard/blog/' + blogId + '/media/list?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.success) return;
                var data = payload.data || {};
                state.total = data.total || 0;
                if (totalEl) totalEl.textContent = state.total + ' item' + (state.total === 1 ? '' : 's');

                if (replace) grid.innerHTML = '';
                (data.items || []).forEach(function (item) {
                    grid.insertAdjacentHTML('beforeend', cardHtml(item));
                });
                state.offset = (data.items || []).length + (replace ? 0 : state.offset);

                empty.classList.toggle('hidden', grid.children.length > 0);
                loadMoreWrap.classList.toggle('hidden', grid.children.length >= state.total);
                if (window.lucide) window.lucide.createIcons();
            });
    }

    function reload() {
        state.offset = 0;
        fetchList(true);
    }

    var debounce;
    qInput.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(function () { state.q = qInput.value; reload(); }, 250);
    });
    sourceSel.addEventListener('change', function () { state.source = sourceSel.value; reload(); });
    sortSel.addEventListener('change', function () { state.sort = sortSel.value; reload(); });
    loadMoreBtn.addEventListener('click', function () { fetchList(false); });

    // Delete (event delegation so it survives re-renders)
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-media-delete]');
        if (!btn) return;
        if (!confirm('Delete this image? Posts that reference it will show a broken image.')) return;

        var id = btn.getAttribute('data-media-delete');
        var form = new FormData();
        form.append('_token', csrfToken);

        fetch('/dashboard/blog/' + blogId + '/media/' + id + '/destroy', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.success) {
                    var fig = grid.querySelector('[data-media-id="' + id + '"]');
                    if (fig) fig.remove();
                    state.total = Math.max(0, state.total - 1);
                    if (totalEl) totalEl.textContent = state.total + ' item' + (state.total === 1 ? '' : 's');
                    empty.classList.toggle('hidden', grid.children.length > 0);
                }
            });
    });

    // Upload (drop + click)
    var dropArea = document.getElementById('media-uploader');
    var fileInput = document.getElementById('media-uploader-input');
    var feedback = document.getElementById('media-upload-feedback');

    function flash(msg, ok) {
        var line = document.createElement('div');
        line.className = ok ? 'text-emerald-600' : 'text-red-600';
        line.textContent = msg;
        feedback.appendChild(line);
        setTimeout(function () { line.remove(); }, 4000);
    }

    function uploadOne(file) {
        var form = new FormData();
        form.append('file', file);
        form.append('_token', csrfToken);
        return fetch('/dashboard/blog/' + blogId + '/media', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.success) {
                    grid.insertAdjacentHTML('afterbegin', cardHtml(payload.data));
                    state.total += 1;
                    if (totalEl) totalEl.textContent = state.total + ' item' + (state.total === 1 ? '' : 's');
                    empty.classList.add('hidden');
                    if (window.lucide) window.lucide.createIcons();
                    flash(file.name + ' uploaded', true);
                } else {
                    flash((payload && payload.error) || 'Upload failed', false);
                }
            })
            .catch(function () { flash('Upload failed', false); });
    }

    function handleFiles(list) {
        Array.prototype.forEach.call(list, function (f) { uploadOne(f); });
    }

    dropArea.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        handleFiles(fileInput.files);
        fileInput.value = '';
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
        dropArea.addEventListener(ev, function (e) {
            e.preventDefault();
            dropArea.classList.add('border-custom-500');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dropArea.addEventListener(ev, function (e) {
            e.preventDefault();
            dropArea.classList.remove('border-custom-500');
        });
    });
    dropArea.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files) handleFiles(e.dataTransfer.files);
    });
})();
</script>
{% endblock %}
