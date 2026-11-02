{% extends "back.lex.php" %}
{% block title %}<?= e($t('media.page.title')) ?>{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?= e($t('media.page.subtitle')) ?></span>{% endblock %}
{% block head %}
<link rel="stylesheet" href="/assets/vendor/cropper/cropper.min.css" />
{% endblock %}

{% block body %}
<?php
$mediaSources = [];
foreach (['', 'upload', 'post_image', 'branding', 'backfill', 'unused'] as $source) {
    $mediaSources[$source] = $t('media.sources.'.($source === '' ? 'all' : $source));
}

$uploadTypes = implode(', ', array_map('strtoupper', array_diff($uploadExtensions, ['jpeg'])));
$uploadLimit = (int) round($uploadMaxBytes / 1048576).' MB';
$uploadAccept = implode(',', array_map(static fn (string $ext): string => '.'.$ext, $uploadExtensions));

$jsText = [];
foreach ([
    'total', 'sortAsc', 'sortDesc', 'edit', 'download', 'copy', 'copied', 'copyFailed', 'delete',
    'emptyTitle', 'emptyBody', 'noMatchTitle', 'noMatchBody', 'uploaded', 'uploadFailed',
    'rescanning', 'rescanNone', 'rescanDone', 'optimizeConfirm', 'optimizing', 'optimizeNone', 'optimizeDone',
    'deleteConfirm', 'deleteUsed', 'deleteLeaves', 'deleteInBody', 'deleteAnyway', 'error',
    'wrongType', 'tooBig', 'cancelUpload', 'retry', 'dismiss',
    'select', 'selection', 'bulkDeleteConfirm', 'bulkDeleteUsed', 'bulkDeleted', 'tooMany',
    'output', 'modeConvert', 'modeOverwrite', 'modeNew', 'notUsed', 'usedIn', 'nameRequired',
] as $key) {
    $jsText[$key] = $t('media.js.'.$key);
}

$libraryActions = [
    ['label' => $t('media.actions.rescan'), 'icon' => 'refresh-cw', 'data' => ['media-rescan' => '1']],
    ['label' => $t('media.actions.optimize'), 'icon' => 'minimize-2', 'data' => ['media-optimize' => '1']],
];
$libraryTitle = $t('media.toolbar.menuTitle');

$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 w-full';
$toolClass = 'h-9 py-1.5 text-sm border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700';
$iconButton = 'inline-flex items-center justify-center size-9 border rounded-md border-slate-200 text-slate-600 bg-white hover:bg-slate-50 '
    .'aria-pressed:bg-slate-900 aria-pressed:text-white aria-pressed:border-slate-900 '
    .'dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600 '
    .'dark:aria-pressed:bg-zink-100 dark:aria-pressed:text-zink-800 transition-colors '
    .'focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';
$labelClass = 'inline-block mb-1 text-sm font-medium';
$hintClass = 'text-xs text-slate-500 dark:text-zink-300';

$toggleRow = static function (string $name, string $label, array $options, string $pressed): string {
    $buttons = '';
    foreach ($options as $value => $text) {
        $buttons .= '<button type="button" data-value="'.e((string) $value).'" aria-pressed="'.((string) $value === $pressed ? 'true' : 'false').'"'
            .' class="px-3 py-1.5 font-medium text-slate-600 dark:text-zink-200 aria-pressed:bg-custom-500 aria-pressed:text-white'
            .' border-slate-200 dark:border-zink-500 [&:not(:first-child)]:border-s disabled:opacity-40 disabled:cursor-not-allowed">'
            .e($text).'</button>';
    }

    return '<span id="media-'.e($name).'-label" class="inline-block mb-1 text-sm font-medium">'.e($label).'</span>'
        .'<div class="flex w-fit overflow-hidden text-xs border rounded-md border-slate-200 dark:border-zink-500"'
        .' role="group" aria-labelledby="media-'.e($name).'-label" data-toggle="'.e($name).'">'.$buttons.'</div>';
};

$mediaUrl = static fn (string $path = ''): string => lurl('/dashboard/blog/'.(int) $blog['id'].'/media'.$path);
$mediaRoutes = [
    'list' => $mediaUrl('/list'),
    'upload' => $mediaUrl(),
    'rescan' => $mediaUrl('/rescan'),
    'optimize' => $mediaUrl('/optimize'),
    'bulkUsages' => $mediaUrl('/bulk-usages'),
    'bulkDestroy' => $mediaUrl('/bulk-destroy'),
    'details' => $mediaUrl('/{id}/details'),
    'meta' => $mediaUrl('/{id}/meta'),
    'process' => $mediaUrl('/{id}/process'),
    'destroy' => $mediaUrl('/{id}/destroy'),
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto"
     data-media-library
     data-media-auto-edit="<?= (int) ($autoEditId ?? 0) ?>"
     data-media-types="<?= e($uploadAccept) ?>"
     data-media-max-bytes="<?= (int) $uploadMaxBytes ?>"
     data-media-type-names="<?= e($uploadTypes) ?>"
     data-media-max-label="<?= e($uploadLimit) ?>"
     data-media-bulk-max="<?= (int) $bulkMax ?>">
    <form class="hidden">{{ csrf_field() }}</form>
    <script type="application/json" data-media-initial><?= json_encode([
        'items' => $mediaItems ?? [],
        'total' => (int) ($total ?? 0),
        'totalBytes' => (int) ($totalBytes ?? 0),
        'text' => $jsText,
        'sources' => $mediaSources,
        'routes' => $mediaRoutes,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <section class="card">
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-baseline gap-3">
                    <h2 class="text-sm font-medium text-slate-800 dark:text-zink-50">
                        <?= e($t('media.toolbar.files')) ?>
                        <span class="text-slate-500 dark:text-zink-300">(<span data-media-count><?= (int) $total ?></span>)</span>
                    </h2>
                    <span class="text-xs text-slate-500 dark:text-zink-300" data-media-bytes></span>
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="relative grow sm:grow-0">
                        <label for="media-q" class="sr-only"><?= e($t('media.toolbar.searchLabel')) ?></label>
                        <input type="search" id="media-q" autocomplete="off"
                               placeholder="<?= e($t('media.toolbar.searchPlaceholder')) ?>"
                               class="form-input w-full sm:w-56 ps-8 <?= $toolClass ?>">
                        <span class="pointer-events-none absolute inset-y-0 start-2.5 flex items-center text-slate-400 dark:text-zink-300">
                            {% cache 'lucide:search:media' ttl=31536000 %}<i data-lucide="search" class="size-4" aria-hidden="true"></i>{% endcache %}
                        </span>
                    </div>

                    <label for="media-source" class="sr-only"><?= e($t('media.toolbar.sourceLabel')) ?></label>
                    <select id="media-source" class="form-select w-auto <?= $toolClass ?>">
                        <?php foreach ($mediaSources as $value => $label) { ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php } ?>
                    </select>

                    <div class="flex items-center gap-1">
                        <div class="flex items-center gap-1">
                            <label for="media-sort" class="sr-only"><?= e($t('media.toolbar.sortLabel')) ?></label>
                            <select id="media-sort" class="form-select w-auto <?= $toolClass ?>">
                                <?php foreach (['date', 'name', 'size'] as $sort) { ?>
                                <option value="<?= e($sort) ?>"><?= e($t('media.sorts.'.$sort)) ?></option>
                                <?php } ?>
                            </select>
                            <button type="button" id="media-sort-dir" data-dir="desc"
                                    aria-label="<?= e($jsText['sortAsc']) ?>" title="<?= e($jsText['sortAsc']) ?>"
                                    class="<?= $iconButton ?>">
                                <span data-dir-icon="desc">{% cache 'lucide:arrow-down-wide-narrow' ttl=31536000 %}<i data-lucide="arrow-down-wide-narrow" class="size-4" aria-hidden="true"></i>{% endcache %}</span>
                                <span data-dir-icon="asc" class="hidden">{% cache 'lucide:arrow-up-narrow-wide' ttl=31536000 %}<i data-lucide="arrow-up-narrow-wide" class="size-4" aria-hidden="true"></i>{% endcache %}</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-1" role="group" aria-label="<?= e($t('media.toolbar.viewLabel')) ?>">
                            <button type="button" data-media-view="list" aria-pressed="false"
                                    aria-label="<?= e($t('media.toolbar.listView')) ?>" title="<?= e($t('media.toolbar.listView')) ?>"
                                    class="<?= $iconButton ?>">
                                {% cache 'lucide:list:media' ttl=31536000 %}<i data-lucide="list" class="size-4" aria-hidden="true"></i>{% endcache %}
                            </button>
                            <button type="button" data-media-view="grid" aria-pressed="true"
                                    aria-label="<?= e($t('media.toolbar.gridView')) ?>" title="<?= e($t('media.toolbar.gridView')) ?>"
                                    class="<?= $iconButton ?>">
                                {% cache 'lucide:layout-grid:media' ttl=31536000 %}<i data-lucide="layout-grid" class="size-4" aria-hidden="true"></i>{% endcache %}
                            </button>
                        </div>

                        {% cmp="row-actions" title="{$libraryTitle}" items="{$libraryActions}" %}
                    </div>
                </div>
            </div>

            <div id="media-drop"
                 class="flex flex-wrap items-center justify-between gap-3 p-3 border border-dashed rounded-xl border-slate-300 dark:border-zink-500 transition-colors data-[dragging]:border-custom-500 data-[dragging]:bg-custom-50 dark:data-[dragging]:bg-custom-500/10">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center shrink-0 size-9 border rounded-full border-slate-200 bg-white text-slate-500 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200">
                        {% cache 'lucide:image:media-drop' ttl=31536000 %}<i data-lucide="image" class="size-4" aria-hidden="true"></i>{% endcache %}
                    </span>
                    <div class="text-xs">
                        <p class="font-medium text-slate-800 dark:text-zink-50"><?= e($t('media.drop.title')) ?></p>
                        <p class="text-slate-500 dark:text-zink-300"><?= e($t('media.drop.limits', ['types' => $uploadTypes, 'size' => $uploadLimit])) ?></p>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium border rounded-md cursor-pointer text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-custom-500">
                    {% cache 'lucide:upload:media-drop' ttl=31536000 %}<i data-lucide="upload" class="size-4" aria-hidden="true"></i>{% endcache %}
                    <span><?= e($t('media.drop.select')) ?></span>
                    <input type="file" id="media-file-input" class="sr-only" accept="<?= e($uploadAccept) ?>" multiple
                           aria-label="<?= e($t('media.drop.inputLabel')) ?>">
                </label>
            </div>

            <ul id="media-upload-feedback" class="divide-y divide-slate-100 dark:divide-zink-600 empty:hidden"></ul>
            <p data-upload-announce class="sr-only" aria-live="polite"></p>
            <p data-media-status role="status" aria-live="polite" class="hidden text-xs"></p>

            <div class="flex flex-wrap items-center justify-between gap-2 hidden" data-media-selection>
                <label class="inline-flex items-center gap-2 text-[13px] cursor-pointer text-slate-600 dark:text-zink-200">
                    <input type="checkbox" data-media-select-all class="size-4 rounded border-slate-300 accent-custom-500 dark:border-zink-500"
                           aria-label="<?= e($t('media.selection.selectAll')) ?>">
                    <span data-media-selection-text></span>
                </label>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-media-download-selected disabled
                        class="inline-flex items-center gap-2 whitespace-nowrap px-3 py-1.5 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                        {% cache 'lucide:download:media-bulk' ttl=31536000 %}<i data-lucide="download" class="size-4" aria-hidden="true"></i>{% endcache %}
                        <span><?= e($t('media.selection.download')) ?></span>
                    </button>
                    <button type="button" data-media-delete-selected disabled
                        class="inline-flex items-center gap-2 whitespace-nowrap px-3 py-1.5 text-sm font-medium border rounded-md text-red-600 bg-white border-slate-200 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-zink-700 dark:text-red-400 dark:border-zink-500 dark:hover:bg-red-500/10 transition-colors">
                        {% cache 'lucide:trash-2:media-bulk' ttl=31536000 %}<i data-lucide="trash-2" class="size-4" aria-hidden="true"></i>{% endcache %}
                        <span><?= e($t('media.selection.delete')) ?></span>
                    </button>
                </div>
            </div>

            <div id="media-items" data-view="grid"></div>

            <div id="media-empty" class="hidden py-10 text-center">
                <p class="text-sm font-medium text-slate-800 dark:text-zink-50" data-empty-title></p>
                <p class="mt-1 text-sm text-slate-500 dark:text-zink-300" data-empty-body></p>
            </div>

            <div class="flex justify-center hidden" id="media-load-more-wrap">
                <button type="button" id="media-load-more"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    {% cache 'lucide:chevron-down' ttl=31536000 %}<i data-lucide="chevron-down" class="size-4"></i>{% endcache %} <?= e($t('media.loadMore')) ?>
                </button>
            </div>
        </div>
    </section>
</div>

<?php
$deleteAttrs = ['id' => 'media-editor-delete'];
$cancelAttrs = ['data-modal-close' => 'media-editor'];
$saveDetailsAttrs = ['id' => 'media-details-save'];
$applyAttrs = ['id' => 'media-editor-apply'];
$editorTitle = $t('media.editor.title');
$saveDetailsLabel = $t('media.editor.saveDetails');
$deleteLabel = $t('media.editor.delete');
$cancelLabel = $t('media.editor.cancel');
$applyLabel = $t('media.editor.apply');

ob_start(); ?>
<form id="media-editor-form" class="grid md:grid-cols-[1.3fr_1fr]" novalidate>
    <div class="flex items-center justify-center p-4 bg-slate-100 dark:bg-zink-800">
        <div class="w-full h-[45vh] md:h-[70vh]">
            <img id="media-editor-image" alt="" class="block max-w-full">
        </div>
    </div>

    <div class="min-w-0 p-6 space-y-4 md:max-h-[calc(100vh-2rem)] md:overflow-y-auto">
        <div class="pe-8">
            <h2 id="media-editor-heading" class="text-base font-semibold break-all"></h2>
            <p id="media-editor-meta" class="mt-1 <?= $hintClass ?>"></p>
        </div>

        <div>
            <label for="media-name" class="<?= $labelClass ?>"><?= e($t('media.editor.name')) ?></label>
            <input type="text" id="media-name" name="name" maxlength="255" class="<?= $fieldClass ?>">
        </div>
        <div>
            <label for="media-alt" class="<?= $labelClass ?>"><?= e($t('media.editor.alt')) ?></label>
            <textarea id="media-alt" name="alt_text" rows="2" maxlength="255" class="<?= $fieldClass ?>"
                      placeholder="<?= e($t('media.editor.altPlaceholder')) ?>"></textarea>
        </div>
        <div class="flex justify-end">
            {% cmp="btn" variant="slate" label="{$saveDetailsLabel}" attrs="{$saveDetailsAttrs}" %}
        </div>

        <div id="media-editor-usage" class="p-3 text-sm border rounded-md border-slate-200 dark:border-zink-500 bg-slate-50 dark:bg-zink-700/40"></div>

        <hr class="border-slate-200 dark:border-zink-500">

        <div class="space-y-3">
            <p id="media-output" class="text-sm font-medium" aria-live="polite"></p>
            <div><?= $toggleRow('aspect', $t('media.editor.cropShape'), ['free' => $t('media.editor.free'), '1' => '1:1', '1.7778' => '16:9', '1.3333' => '4:3'], 'free') ?></div>
            <div><?= $toggleRow('scale', $t('media.editor.scaleDown'), ['1' => $t('media.editor.fullSize'), '0.75' => '75%', '0.5' => '50%', '0.25' => '25%'], '1') ?></div>
            <div>
                <label for="media-width" class="<?= $labelClass ?>"><?= e($t('media.editor.customWidth')) ?></label>
                <input type="number" id="media-width" name="width" min="1" class="<?= $fieldClass ?>" placeholder="<?= e($t('media.editor.customWidthPlaceholder')) ?>">
            </div>
            <div>
                <label for="media-quality" class="<?= $labelClass ?>"><?= e($t('media.editor.quality')) ?>: <span id="media-quality-value">82</span></label>
                <input type="range" id="media-quality" name="quality" min="40" max="100" value="82" class="w-full accent-custom-500">
                <p class="<?= $hintClass ?>"><?= e($t('media.editor.qualityHint')) ?></p>
            </div>
            <div>
                <label for="media-format" class="<?= $labelClass ?>"><?= e($t('media.editor.format')) ?></label>
                <select id="media-format" name="format" class="form-select <?= $fieldClass ?>">
                    <option value=""><?= e($t('media.editor.keepOriginal')) ?></option>
                    <option value="jpg">JPEG</option>
                    <option value="webp">WebP</option>
                    <option value="png">PNG</option>
                </select>
            </div>
            <div>
                <?= $toggleRow('mode', $t('media.editor.saveAs'), ['new' => $t('media.editor.newImage'), 'overwrite' => $t('media.editor.overwrite')], 'new') ?>
                <p id="media-mode-hint" class="mt-1 <?= $hintClass ?>"></p>
            </div>
        </div>

        <p id="media-editor-status" role="alert" class="hidden text-sm text-red-600 dark:text-red-400"></p>

        <div class="flex flex-wrap items-center gap-2 pt-2">
            {% cmp="btn" variant="red" label="{$deleteLabel}" attrs="{$deleteAttrs}" %}
            <span class="flex-1"></span>
            {% cmp="btn" variant="slate" label="{$cancelLabel}" attrs="{$cancelAttrs}" %}
            {% cmp="btn" variant="blue" label="{$applyLabel}" attrs="{$applyAttrs}" %}
        </div>
    </div>
</form>
<?php
$editorBody = ob_get_clean();
?>
{% cmp="modal" id="media-editor" title="{$editorTitle}" size="xl" bare="1" body="{$editorBody}" %}
{% endblock %}
{% block scripts %}
<script src="/assets/vendor/cropper/cropper.min.js" nonce="<?= csp_nonce() ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/media-editor.js')) ?>" nonce="<?= csp_nonce() ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/media-upload-queue.js')) ?>" nonce="<?= csp_nonce() ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/media-selection.js')) ?>" nonce="<?= csp_nonce() ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/media-library.js')) ?>" nonce="<?= csp_nonce() ?>"></script>
{% endblock %}
