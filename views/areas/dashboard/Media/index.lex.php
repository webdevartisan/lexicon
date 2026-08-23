{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Media Library{% endblock %}
{% block subtitle %}Every image for this blog in one place — upload, find, and clean up.{% endblock %}
{% block head %}
<link rel="stylesheet" href="/assets/vendor/cropper/cropper.min.css" />
<style>
  .lx-modal[hidden]{display:none}
  .lx-modal{position:fixed;inset:0;z-index:1050;display:flex;align-items:center;justify-content:center;padding:1rem}
  .lx-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55)}
  .lx-modal-panel{position:relative;background:#fff;color:#1f2937;border-radius:.75rem;max-width:56rem;width:100%;max-height:90vh;overflow:auto;padding:1.25rem;box-shadow:0 20px 50px rgba(0,0,0,.3)}
  .dark .lx-modal-panel{background:#1f2a37;color:#e5e7eb}
  .lx-modal-title{margin:0 0 .25rem;font-size:1.05rem;font-weight:600;word-break:break-all}
  .lx-name-edit{display:flex;gap:.4rem;margin:.25rem 0 .5rem}
  .lx-name-edit input{flex:1;min-width:0;border:1px solid #d1d5db;border-radius:.375rem;padding:.35rem .5rem;background:transparent;color:inherit;font-size:.8rem}
  .lx-alt-input{width:100%;border:1px solid #d1d5db;border-radius:.375rem;padding:.35rem .5rem;background:transparent;color:inherit;font-size:.8rem;resize:vertical}
  .lx-editor-grid{display:grid;grid-template-columns:1fr;gap:1rem}
  @media (min-width:768px){.lx-editor-grid{grid-template-columns:1.3fr 1fr}}
  .lx-editor-stage{background:#f2f2f2;border-radius:.5rem;overflow:hidden;max-height:60vh}
  .dark .lx-editor-stage{background:#111827}
  .lx-editor-stage img{max-width:100%;display:block}
  .lx-editor-meta{display:grid;grid-template-columns:auto 1fr;gap:.15rem .75rem;font-size:.8rem;margin:.5rem 0}
  .lx-editor-meta dt{color:#6b7280;font-weight:500}
  .lx-editor-usage{font-size:.8rem;margin:.5rem 0;border-radius:.5rem;padding:.5rem .625rem;background:#f8fafc;border:1px solid #e5e7eb}
  .dark .lx-editor-usage{background:#111827;border-color:#374151}
  .lx-editor-usage b{display:block;margin-bottom:.25rem}
  .lx-editor-controls{display:grid;gap:.35rem;font-size:.8rem;margin-top:.5rem}
  .lx-editor-controls label{font-weight:600;margin-top:.35rem}
  .lx-editor-controls input,.lx-editor-controls select{width:100%;border:1px solid #d1d5db;border-radius:.375rem;padding:.35rem .5rem;background:transparent;color:inherit}
  .lx-btn-row{display:flex;flex-wrap:wrap;gap:.35rem}
  .lx-btn-row button{border:1px solid #d1d5db;border-radius:.375rem;padding:.3rem .6rem;background:transparent;color:inherit;cursor:pointer;font-size:.78rem}
  .lx-btn-row button.is-active{background:#4f46e5;border-color:#4f46e5;color:#fff}
  .lx-editor-hint{color:#6b7280;font-size:.72rem;min-height:1em}
  .lx-editor-output{font-size:.85rem;font-weight:600;background:#eef2ff;color:#3730a3;border-radius:.375rem;padding:.4rem .6rem;margin-bottom:.35rem}
  .dark .lx-editor-output{background:#312e81;color:#e0e7ff}
  .lx-editor-usage a{color:#4f46e5;text-decoration:underline}
  .dark .lx-editor-usage a{color:#a5b4fc}
  .lx-modal-actions{display:flex;align-items:center;gap:.5rem;margin-top:1rem}
  .lx-primary{background:#4f46e5;color:#fff;border:0;border-radius:.375rem;padding:.45rem .9rem;cursor:pointer}
  .lx-primary:disabled{opacity:.5;cursor:not-allowed}
  .lx-ghost{background:transparent;border:1px solid #d1d5db;border-radius:.375rem;padding:.45rem .9rem;cursor:pointer;color:inherit}
  .lx-danger-text{color:#dc2626;border-color:#dc2626}
  .lx-modal .cropper-view-box,.lx-modal .cropper-face{border-radius:0}
  #media-grid figure .aspect-square{cursor:pointer}
</style>
{% endblock %}

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

    return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1).' '.$units[$i];
};

$mediaSourceOptions = [
    '' => 'All sources',
    'upload' => 'Library uploads',
    'post_image' => 'Post images',
    'branding' => 'Branding',
    'backfill' => 'Imported',
    'unused' => 'Unused only',
];
$mediaSortOptions = [
    'newest' => 'Newest first',
    'oldest' => 'Oldest first',
    'largest' => 'Largest first',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto"
     data-media-blog-id="{{ blog.id }}"
     data-media-auto-edit="<?= (int) ($autoEditId ?? 0) ?>">
    <form class="hidden">{{ csrf_field() }}</form>

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/blog/{{ blog.id }}/show"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            {% cache 'lucide:arrow-left' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4"></i>{% endcache %}
            <span>Back to blog</span>
        </a>
        <div class="flex items-center gap-3">
            <button type="button" id="media-rescan"
                title="Index any images on disk (e.g. banners) that aren't in the library yet"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                {% cache 'lucide:refresh-cw' ttl=31536000 %}<i data-lucide="refresh-cw" class="size-4"></i>{% endcache %}
                <span>Rescan</span>
            </button>
            <button type="button" id="media-optimize"
                title="Shrink images larger than 2048px to save space; links keep working"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                {% cache 'lucide:minimize-2' ttl=31536000 %}<i data-lucide="minimize-2" class="size-4"></i>{% endcache %}
                <span>Shrink oversized</span>
            </button>
            <span class="text-xs text-slate-500 dark:text-zink-300" data-media-total>
                <?= (int) ($total ?? 0) ?> item<?= ((int) ($total ?? 0)) === 1 ? '' : 's' ?>
            </span>
        </div>
    </div>

    <!-- Upload area -->
    <section class="card mb-5">
        <div class="card-body">
            <div id="media-uploader"
                 class="border-2 border-dashed border-slate-200 dark:border-zink-500 rounded-lg p-6 text-center cursor-pointer hover:border-custom-400 transition-colors">
                {% cache 'lucide:upload-cloud:lg' ttl=31536000 %}<i data-lucide="upload-cloud" class="size-8 text-slate-400 dark:text-zink-300 mx-auto mb-2"></i>{% endcache %}
                <p class="text-sm font-medium text-slate-700 dark:text-zink-100">Drop images here or click to upload</p>
                <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">PNG, JPG, WebP, SVG or GIF · up to 50 MB each</p>
                <input type="file" id="media-uploader-input" class="hidden" accept="image/*" multiple>
            </div>
            <div id="media-upload-feedback" class="mt-3 space-y-1 text-xs"></div>
        </div>
    </section>

    <!-- Filters -->
    <section class="card mb-5">
        <div class="card-body">
            <div class="flex flex-wrap items-end gap-3">
                <div class="grow min-w-[200px]">
                    {% cmp="input" type="search" name="media-q" placeholder="Search filename…" %}
                </div>
                {% cmp="select" name="media-source" options="{$mediaSourceOptions}" selectedKey="" %}
                {% cmp="select" name="media-sort" options="{$mediaSortOptions}" selectedKey="newest" %}
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
                    <?php $ver = !empty($item['updated_at']) ? '?v='.rawurlencode((string) $item['updated_at']) : ''; ?>
                    <div class="aspect-square overflow-hidden">
                        <img src="<?= e($item['url'].$ver) ?>" alt="" loading="lazy"
                             class="w-full h-full object-cover transition-transform group-hover:scale-105">
                    </div>
                    <?php $displayName = !empty($item['original_name']) ? $item['original_name'] : $item['filename']; ?>
                    <figcaption class="px-2 py-1.5 text-[11px] text-slate-600 dark:text-zink-200 truncate" title="<?= e($displayName) ?>">
                        <?= e($displayName) ?>
                    </figcaption>
                    <div class="absolute inset-x-0 bottom-0 px-2 py-1.5 bg-gradient-to-t from-black/60 to-transparent text-[10px] text-white flex justify-between opacity-0 group-hover:opacity-100 transition-opacity">
                        <span><?= e(strtoupper((string) $item['extension'])) ?></span>
                        <span><?= e($humanSize((int) $item['size_bytes'])) ?></span>
                    </div>
                    <button type="button" title="Delete"
                            data-media-delete="<?= (int) $item['id'] ?>"
                            class="absolute top-1.5 right-1.5 p-1 rounded-md bg-white/90 text-red-600 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                        {% cache 'lucide:trash-2:sm' ttl=31536000 %}<i data-lucide="trash-2" class="size-3.5"></i>{% endcache %}
                    </button>
                </figure>
                <?php } ?>
            </div>

            <div id="media-empty" class="<?= !empty($mediaItems) ? 'hidden' : '' ?>">
                {% cmp="empty-state" icon="image-off" title="Nothing here yet" message="Drop your first image above to start the library." %}
            </div>

            <div class="flex justify-center mt-5 <?= ((int) ($total ?? 0)) <= count($mediaItems ?? []) ? 'hidden' : '' ?>" id="media-load-more-wrap">
                <button type="button" id="media-load-more"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    {% cache 'lucide:chevron-down' ttl=31536000 %}<i data-lucide="chevron-down" class="size-4"></i>{% endcache %} Load more
                </button>
            </div>
        </div>
    </section>

</div>

<div id="media-editor" class="lx-modal" hidden role="dialog" aria-modal="true" aria-labelledby="media-editor-name">
    <div class="lx-modal-backdrop" data-close></div>
    <div class="lx-modal-panel">
        <div class="lx-editor-grid">
            <div>
                <div class="lx-editor-stage"><img id="media-editor-image" alt=""></div>
            </div>
            <div>
                <h3 class="lx-modal-title" id="media-editor-name">Image</h3>
                <div class="lx-name-edit">
                    <input type="text" id="media-name-input" maxlength="255" aria-label="Image name" placeholder="Image name">
                </div>
                <textarea id="media-alt-input" class="lx-alt-input" maxlength="255" rows="2"
                          aria-label="Alt text" placeholder="Alt text — describe the image for screen readers"></textarea>
                <div style="text-align:right;margin:.35rem 0 .5rem">
                    <button type="button" class="lx-ghost" id="media-name-save">Save details</button>
                </div>
                <dl class="lx-editor-meta" id="media-editor-meta"></dl>
                <div class="lx-editor-usage" id="media-editor-usage" hidden></div>

                <div class="lx-editor-controls">
                    <div class="lx-editor-output" id="media-output">Output: —</div>

                    <label>Crop shape</label>
                    <div class="lx-btn-row" id="media-aspect">
                        <button type="button" data-ar="free" class="is-active">Free</button>
                        <button type="button" data-ar="1">1:1</button>
                        <button type="button" data-ar="1.7778">16:9</button>
                        <button type="button" data-ar="1.3333">4:3</button>
                    </div>

                    <label>Scale down</label>
                    <div class="lx-btn-row" id="media-scale">
                        <button type="button" data-scale="1" class="is-active">Full size</button>
                        <button type="button" data-scale="0.75">75%</button>
                        <button type="button" data-scale="0.5">50%</button>
                        <button type="button" data-scale="0.25">25%</button>
                    </div>

                    <label for="media-width">Custom width (px) <span class="lx-editor-hint" id="media-dim-hint"></span></label>
                    <input type="number" id="media-width" min="1" placeholder="Auto — height follows the ratio">

                    <label for="media-quality">Quality — lower means smaller file: <span id="media-quality-val">82</span></label>
                    <input type="range" id="media-quality" min="40" max="100" value="82">

                    <label for="media-format">Format</label>
                    <select id="media-format">
                        <option value="">Keep original</option>
                        <option value="jpg">JPEG</option>
                        <option value="webp">WebP</option>
                        <option value="png">PNG</option>
                    </select>

                    <label>Save</label>
                    <div class="lx-btn-row" id="media-mode">
                        <button type="button" data-mode="new" class="is-active">Save as new</button>
                        <button type="button" data-mode="overwrite">Overwrite</button>
                    </div>
                    <p class="lx-editor-hint" id="media-mode-hint"></p>
                </div>

                <div class="lx-modal-actions">
                    <button type="button" class="lx-ghost lx-danger-text" id="media-editor-delete">Delete</button>
                    <span style="flex:1"></span>
                    <button type="button" class="lx-ghost" data-close>Cancel</button>
                    <button type="button" class="lx-primary" id="media-editor-apply">Apply</button>
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
{% block scripts %}
<script src="/assets/vendor/cropper/cropper.min.js" nonce="<?= csp_nonce() ?>"></script>
<script nonce="<?= csp_nonce() ?>">
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
        var name = esc(item.original_name || item.filename || '');
        var ext = esc((item.extension || '').toUpperCase());
        var size = esc(humanSize(item.size_bytes));
        var stamp = item.updated_at || item.created_at || '';
        var thumb = esc(item.url + (stamp ? (item.url.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(stamp) : ''));
        return ''
            + '<figure class="group relative border border-slate-200 dark:border-zink-500 rounded-md overflow-hidden bg-slate-50 dark:bg-zink-700" data-media-id="' + id + '">'
            + '  <div class="aspect-square overflow-hidden">'
            + '    <img src="' + thumb + '" alt="" loading="lazy" class="w-full h-full object-cover transition-transform group-hover:scale-105">'
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
<script nonce="<?= csp_nonce() ?>">
// Media editor: click a card to open, crop/resize/compress/convert the stored file,
// then save as a new library item or overwrite the existing one. Reuses the same
// Cropper.js the avatar flow uses; here aspect is selectable instead of locked.
(function () {
    var root = document.querySelector('[data-media-blog-id]');
    if (!root || typeof Cropper === 'undefined') return;

    var blogId = root.getAttribute('data-media-blog-id');
    var tokenInput = root.querySelector('input[name="_token"]');
    var csrfToken = tokenInput ? tokenInput.value : '';
    var base = '/dashboard/blog/' + blogId + '/media';

    var grid = document.getElementById('media-grid');
    var modal = document.getElementById('media-editor');
    var image = document.getElementById('media-editor-image');
    var nameEl = document.getElementById('media-editor-name');
    var nameInput = document.getElementById('media-name-input');
    var altInput = document.getElementById('media-alt-input');
    var nameSave = document.getElementById('media-name-save');
    var metaEl = document.getElementById('media-editor-meta');
    var usageEl = document.getElementById('media-editor-usage');
    var widthEl = document.getElementById('media-width');
    var qualityEl = document.getElementById('media-quality');
    var qualityVal = document.getElementById('media-quality-val');
    var formatEl = document.getElementById('media-format');
    var aspectRow = document.getElementById('media-aspect');
    var scaleRow = document.getElementById('media-scale');
    var modeRow = document.getElementById('media-mode');
    var modeHint = document.getElementById('media-mode-hint');
    var outputEl = document.getElementById('media-output');
    var dimHint = document.getElementById('media-dim-hint');
    var applyBtn = document.getElementById('media-editor-apply');
    var deleteBtn = document.getElementById('media-editor-delete');

    var cropper = null;
    var current = null;       // { id, url, extension, usages }
    var activeScale = 1;      // 1 = full crop size; otherwise a fraction of the crop width
    var estimateTimer = null;

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function humanSize(bytes) {
        bytes = bytes || 0;
        if (bytes <= 0) return '0 B';
        var u = ['B', 'KB', 'MB', 'GB'], i = Math.min(u.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
        return (i === 0 ? bytes : (bytes / Math.pow(1024, i)).toFixed(1)) + ' ' + u[i];
    }
    function mode() {
        var b = modeRow.querySelector('.is-active');
        return b ? b.getAttribute('data-mode') : 'new';
    }
    function setMode(m) {
        modeRow.querySelectorAll('button').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-mode') === m);
        });
    }

    // A format change rewrites the URL, so overwriting in place is not offered then.
    function syncModeAvailability() {
        var converting = formatEl.value && formatEl.value !== normExt(current && current.extension);
        var ovr = modeRow.querySelector('[data-mode="overwrite"]');
        ovr.disabled = !!converting;
        ovr.style.opacity = converting ? '.4' : '';
        if (converting && mode() === 'overwrite') setMode('new');
        modeHint.textContent = converting
            ? 'Converting format saves a new image so existing links keep working.'
            : (mode() === 'overwrite' ? 'Overwrites the file in place; posts using it update automatically.' : 'Creates a new library image; originals are untouched.');
    }
    function normExt(ext) {
        ext = (ext || '').toLowerCase();
        return ext === 'jpeg' ? 'jpg' : ext;
    }

    function renderMeta(d) {
        metaEl.innerHTML =
            '<dt>Name</dt><dd>' + esc(d.original_name || d.filename) + '</dd>' +
            '<dt>Dimensions</dt><dd>' + (d.width || '?') + ' × ' + (d.height || '?') + '</dd>' +
            '<dt>Size</dt><dd>' + humanSize(d.size_bytes) + '</dd>' +
            '<dt>Format</dt><dd>' + esc((d.extension || '').toUpperCase()) + '</dd>' +
            '<dt>Source</dt><dd>' + esc(d.source || '') + '</dd>';

        if (d.usages && d.usages.length) {
            usageEl.hidden = false;
            usageEl.innerHTML = '<b>Used in ' + d.usages.length + ' place' + (d.usages.length === 1 ? '' : 's') + '</b>' +
                d.usages.map(function (u) {
                    var label = u.link
                        ? '<a href="' + esc(u.link) + '" target="_blank" rel="noopener">' + esc(u.label) + '</a>'
                        : esc(u.label);
                    return '· ' + label + ' <span style="color:#6b7280">(' + esc(u.context) + ')</span>';
                }).join('<br>');
        } else {
            usageEl.hidden = false;
            usageEl.innerHTML = '<b>Not used anywhere yet</b>';
        }
    }

    // The output box: the crop size, optionally scaled to a custom width with the
    // height following the ratio. This is exactly what the server will produce.
    function outputBox() {
        var c = cropper.getData(true);
        var w = Math.round(c.width), h = Math.round(c.height);
        var tw = parseInt(widthEl.value, 10) || 0;
        if (tw > 0 && w > 0) {
            h = Math.max(1, Math.round(tw * h / w));
            w = tw;
        }
        return { w: w, h: h };
    }

    function currentMime() {
        var f = formatEl.value || normExt(current && current.extension);
        return f === 'png' ? 'image/png' : (f === 'webp' ? 'image/webp' : 'image/jpeg');
    }

    // Render the chosen crop at the chosen size/quality to a canvas and measure the
    // encoded blob, so the user sees real dimensions and an estimated file size before
    // saving. This is what makes the quality/size trade-off obvious.
    function updateEstimate() {
        if (!cropper) return;
        var box = outputBox();
        dimHint.textContent = '→ ' + box.w + ' × ' + box.h;
        var canvas = cropper.getCroppedCanvas({ width: box.w, height: box.h, imageSmoothingQuality: 'high' });
        if (!canvas || !canvas.toBlob) { outputEl.textContent = 'Output: ' + box.w + ' × ' + box.h; return; }
        var mime = currentMime();
        var q = parseInt(qualityEl.value, 10) / 100;
        canvas.toBlob(function (blob) {
            outputEl.textContent = 'Output: ' + box.w + ' × ' + box.h + (blob ? ' · ~' + humanSize(blob.size) : '') +
                ' · ' + (currentMime().split('/')[1].toUpperCase());
        }, mime, mime === 'image/png' ? undefined : q);
    }

    function scheduleEstimate() {
        clearTimeout(estimateTimer);
        estimateTimer = setTimeout(updateEstimate, 180);
    }

    function applyScale() {
        if (activeScale >= 1) { widthEl.value = ''; scheduleEstimate(); return; }
        var c = cropper.getData(true);
        widthEl.value = Math.max(1, Math.round(c.width * activeScale));
        scheduleEstimate();
    }

    function open(id) {
        fetch(base + '/' + id + '/details', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                if (!p || !p.success) return;
                var d = p.data;
                current = { id: d.id, url: d.url, extension: d.extension, usages: d.usages || [] };
                nameEl.textContent = d.original_name || d.filename;
                nameInput.value = d.original_name || d.filename;
                altInput.value = d.alt_text || '';
                renderMeta(d);
                widthEl.value = '';
                qualityEl.value = 82; qualityVal.textContent = '82';
                formatEl.value = '';
                activeScale = 1;
                setMode('new');
                aspectRow.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-ar') === 'free'); });
                scaleRow.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-scale') === '1'); });
                syncModeAvailability();

                modal.hidden = false;
                image.src = d.url + '?v=' + Date.now();
                image.onload = function () {
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(image, {
                        viewMode: 1, autoCropArea: 1, background: false, responsive: true,
                        ready: updateEstimate,
                        cropend: function () { applyScale(); },
                    });
                };
            });
    }

    function close() {
        if (cropper) { cropper.destroy(); cropper = null; }
        modal.hidden = true;
        image.removeAttribute('src');
        current = null;
    }

    // Open on clicking a card image (the trash button keeps its own handler).
    grid.addEventListener('click', function (e) {
        if (e.target.closest('[data-media-delete]')) return;
        var fig = e.target.closest('[data-media-id]');
        if (!fig || !e.target.closest('.aspect-square')) return;
        open(fig.getAttribute('data-media-id'));
    });

    aspectRow.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b || !cropper) return;
        aspectRow.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        var ar = b.getAttribute('data-ar');
        cropper.setAspectRatio(ar === 'free' ? NaN : parseFloat(ar));
        applyScale();
    });
    scaleRow.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b || !cropper) return;
        scaleRow.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        activeScale = parseFloat(b.getAttribute('data-scale'));
        applyScale();
    });
    // Typing a custom width means the user is driving the size directly, so drop any
    // active scale preset.
    widthEl.addEventListener('input', function () {
        activeScale = 0;
        scaleRow.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-active'); });
        scheduleEstimate();
    });
    modeRow.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b || b.disabled) return;
        setMode(b.getAttribute('data-mode'));
        syncModeAvailability();
    });
    formatEl.addEventListener('change', function () { syncModeAvailability(); scheduleEstimate(); });
    qualityEl.addEventListener('input', function () { qualityVal.textContent = qualityEl.value; scheduleEstimate(); });

    applyBtn.addEventListener('click', function () {
        if (!cropper || !current) return;
        var c = cropper.getData(true);
        var img = cropper.getImageData();
        var natW = Math.round(img.naturalWidth), natH = Math.round(img.naturalHeight);
        var form = new FormData();
        form.append('_token', csrfToken);
        form.append('mode', mode());
        form.append('quality', qualityEl.value);
        if (widthEl.value) form.append('width', widthEl.value);
        if (formatEl.value) form.append('format', formatEl.value);
        // Only send a crop when the box is actually a sub-region; a full-image box is
        // just a resize/convert, which the server handles aspect-correctly.
        var isFull = c.x <= 1 && c.y <= 1 && c.width >= natW - 2 && c.height >= natH - 2;
        if (!isFull && c.width && c.height) {
            form.append('crop_x', Math.max(0, c.x)); form.append('crop_y', Math.max(0, c.y));
            form.append('crop_w', c.width); form.append('crop_h', c.height);
        }
        applyBtn.disabled = true;

        fetch(base + '/' + current.id + '/process', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                applyBtn.disabled = false;
                if (!p || !p.success) { alert((p && p.error) || 'Could not process the image.'); return; }
                if (mode() === 'overwrite') {
                    var img = grid.querySelector('[data-media-id="' + current.id + '"] img');
                    if (img) img.src = current.url + '?v=' + Date.now();
                } else {
                    location.reload();
                }
                close();
            })
            .catch(function () { applyBtn.disabled = false; alert('Could not process the image.'); });
    });

    deleteBtn.addEventListener('click', function () {
        if (!current) return;
        var msg = current.usages.length
            ? 'This image is used in ' + current.usages.length + ' place(s):\n\n' +
              current.usages.map(function (u) { return '• ' + u.label + ' (' + u.context + ')'; }).join('\n') +
              '\n\nDeleting it will leave broken images there. Delete anyway?'
            : 'Delete this image? This cannot be undone.';
        if (!confirm(msg)) return;

        var form = new FormData();
        form.append('_token', csrfToken);
        fetch(base + '/' + current.id + '/destroy', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                if (p && p.success) {
                    var fig = grid.querySelector('[data-media-id="' + current.id + '"]');
                    if (fig) fig.remove();
                    close();
                }
            });
    });

    nameSave.addEventListener('click', function () {
        if (!current) return;
        var name = nameInput.value.trim();
        if (!name) { alert('Name cannot be empty.'); return; }
        var form = new FormData();
        form.append('_token', csrfToken);
        form.append('name', name);
        form.append('alt_text', altInput.value);
        nameSave.disabled = true;

        fetch(base + '/' + current.id + '/meta', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                nameSave.disabled = false;
                if (!p || !p.success) { alert((p && p.error) || 'Could not rename.'); return; }
                var newName = p.data.original_name || name;
                nameEl.textContent = newName;
                var cap = grid.querySelector('[data-media-id="' + current.id + '"] figcaption');
                if (cap) { cap.textContent = newName; cap.title = newName; }
                var metaName = metaEl.querySelector('dd');
                if (metaName) metaName.textContent = newName;
            })
            .catch(function () { nameSave.disabled = false; alert('Could not rename.'); });
    });

    modal.querySelectorAll('[data-close]').forEach(function (el) { el.addEventListener('click', close); });

    // Arrived from an "Edit" link elsewhere (e.g. the appearance page): open straight
    // onto that image.
    var autoEdit = parseInt(root.getAttribute('data-media-auto-edit'), 10) || 0;
    if (autoEdit > 0) open(autoEdit);

    var rescanBtn = document.getElementById('media-rescan');
    if (rescanBtn) rescanBtn.addEventListener('click', function () {
        var label = rescanBtn.querySelector('span');
        var original = label ? label.textContent : '';
        if (label) label.textContent = 'Rescanning…';
        rescanBtn.disabled = true;
        var form = new FormData();
        form.append('_token', csrfToken);
        fetch(base + '/rescan', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                rescanBtn.disabled = false;
                if (label) label.textContent = original;
                if (!p || !p.success) { alert((p && p.error) || 'Rescan failed.'); return; }
                if (p.data.added > 0) { alert('Found and indexed ' + p.data.added + ' new image(s).'); location.reload(); }
                else { alert('Everything on disk is already in the library.'); }
            })
            .catch(function () { rescanBtn.disabled = false; if (label) label.textContent = original; alert('Rescan failed.'); });
    });

    var optimizeBtn = document.getElementById('media-optimize');
    if (optimizeBtn) optimizeBtn.addEventListener('click', function () {
        if (!confirm('Shrink every image larger than 2048px and re-save it in place? Links to these images keep working. This cannot be undone.')) return;
        var label = optimizeBtn.querySelector('span');
        var original = label ? label.textContent : '';
        if (label) label.textContent = 'Optimizing…';
        optimizeBtn.disabled = true;

        var form = new FormData();
        form.append('_token', csrfToken);
        fetch(base + '/optimize', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: form,
        })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                optimizeBtn.disabled = false;
                if (label) label.textContent = original;
                if (!p || !p.success) { alert((p && p.error) || 'Optimization failed.'); return; }
                var d = p.data;
                if (!d.processed) { alert('Nothing to shrink — every image is already within size.'); return; }
                alert('Shrank ' + d.processed + ' image(s) and saved ' + humanSize(d.bytes_saved) + '.');
                location.reload();
            })
            .catch(function () { optimizeBtn.disabled = false; if (label) label.textContent = original; alert('Optimization failed.'); });
    });
})();
</script>
{% endblock %}
