{% extends "back.lex.php" %}
{% block title %}Media Library{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">Every image for this blog in one place. Upload, find and clean up.</span>{% endblock %}
{% block head %}
<link rel="stylesheet" href="/assets/vendor/cropper/cropper.min.css" />
{% endblock %}

{% block body %}
<?php
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

$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 w-full';
$labelClass = 'inline-block mb-1 text-sm font-medium';
$hintClass = 'text-xs text-slate-500 dark:text-zink-300';

// One pressed-button group per editor setting; the script reads and sets aria-pressed
$toggleRow = static function (string $name, string $label, array $options, string $pressed): string {
    $buttons = '';
    foreach ($options as $value => $text) {
        $buttons .= '<button type="button" data-value="'.e((string) $value).'" aria-pressed="'.((string) $value === $pressed ? 'true' : 'false').'"'
            .' class="px-3 py-1.5 font-medium text-slate-600 dark:text-zink-200 aria-pressed:bg-custom-500 aria-pressed:text-white'
            .' border-slate-200 dark:border-zink-500 [&:not(:first-child)]:border-l disabled:opacity-40 disabled:cursor-not-allowed">'
            .e($text).'</button>';
    }

    return '<span id="media-'.e($name).'-label" class="inline-block mb-1 text-sm font-medium">'.e($label).'</span>'
        .'<div class="flex w-fit overflow-hidden text-xs border rounded-md border-slate-200 dark:border-zink-500"'
        .' role="group" aria-labelledby="media-'.e($name).'-label" data-toggle="'.e($name).'">'.$buttons.'</div>';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto"
     data-media-library
     data-media-base="<?= e(lurl('/dashboard/blog/'.(int) $blog['id'].'/media')) ?>"
     data-media-auto-edit="<?= (int) ($autoEditId ?? 0) ?>">
    <form class="hidden">{{ csrf_field() }}</form>
    <script type="application/json" data-media-initial><?= json_encode([
        'items' => $mediaItems ?? [],
        'total' => (int) ($total ?? 0),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <div class="flex items-center justify-end gap-3 mb-5">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <button type="button" id="media-upload-toggle" aria-controls="media-upload-panel" aria-expanded="<?= empty($mediaItems) ? 'true' : 'false' ?>"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-white bg-slate-900 border-slate-900 hover:bg-slate-700 dark:bg-zink-100 dark:text-zink-800 dark:border-zink-100 transition-colors">
                {% cache 'lucide:upload' ttl=31536000 %}<i data-lucide="upload" class="size-4"></i>{% endcache %}
                <span>Upload</span>
            </button>
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
            <span class="text-xs text-slate-500 dark:text-zink-300" data-media-total></span>
        </div>
    </div>

    <!-- Upload area -->
    <section class="card mb-5 <?= empty($mediaItems) ? '' : 'hidden' ?>" id="media-upload-panel">
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
            <div id="media-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3"></div>

            <div id="media-empty" class="hidden">
                {% cmp="empty-state" icon="image-off" title="Nothing here yet" message="Drop your first image above to start the library." %}
            </div>

            <div class="flex justify-center mt-5 hidden" id="media-load-more-wrap">
                <button type="button" id="media-load-more"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    {% cache 'lucide:chevron-down' ttl=31536000 %}<i data-lucide="chevron-down" class="size-4"></i>{% endcache %} Load more
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

ob_start(); ?>
<form id="media-editor-form" class="grid md:grid-cols-[1.3fr_1fr]" novalidate>
    <div class="flex items-center justify-center p-4 bg-slate-100 dark:bg-zink-800">
        <div class="w-full h-[45vh] md:h-[70vh]">
            <img id="media-editor-image" alt="" class="block max-w-full">
        </div>
    </div>

    <div class="min-w-0 p-6 space-y-4 md:max-h-[calc(100vh-2rem)] md:overflow-y-auto">
        <div class="pe-8">
            <h2 id="media-editor-heading"class="text-base font-semibold break-all">Image</h2>
            <p id="media-editor-meta" class="mt-1 <?= $hintClass ?>"></p>
        </div>

        <div>
            <label for="media-name" class="<?= $labelClass ?>">Name</label>
            <input type="text" id="media-name" name="name" maxlength="255" class="<?= $fieldClass ?>">
        </div>
        <div>
            <label for="media-alt" class="<?= $labelClass ?>">Alt text</label>
            <textarea id="media-alt" name="alt_text" rows="2" maxlength="255" class="<?= $fieldClass ?>"
                      placeholder="Describe the image for screen readers"></textarea>
        </div>
        <div class="flex justify-end">
            {% cmp="btn" variant="slate" label="Save details" attrs="{$saveDetailsAttrs}" %}
        </div>

        <div id="media-editor-usage" class="p-3 text-sm border rounded-md border-slate-200 dark:border-zink-500 bg-slate-50 dark:bg-zink-700/40"></div>

        <hr class="border-slate-200 dark:border-zink-500">

        <div class="space-y-3">
            <p id="media-output" class="text-sm font-medium" aria-live="polite"></p>
            <div><?= $toggleRow('aspect', 'Crop shape', ['free' => 'Free', '1' => '1:1', '1.7778' => '16:9', '1.3333' => '4:3'], 'free') ?></div>
            <div><?= $toggleRow('scale', 'Scale down', ['1' => 'Full size', '0.75' => '75%', '0.5' => '50%', '0.25' => '25%'], '1') ?></div>
            <div>
                <label for="media-width" class="<?= $labelClass ?>">Custom width (px)</label>
                <input type="number" id="media-width" name="width" min="1" class="<?= $fieldClass ?>" placeholder="Auto, height follows the ratio">
            </div>
            <div>
                <label for="media-quality" class="<?= $labelClass ?>">Quality: <span id="media-quality-value">82</span></label>
                <input type="range" id="media-quality" name="quality" min="40" max="100" value="82" class="w-full accent-custom-500">
                <p class="<?= $hintClass ?>">Lower means a smaller file.</p>
            </div>
            <div>
                <label for="media-format" class="<?= $labelClass ?>">Format</label>
                <select id="media-format" name="format" class="form-select <?= $fieldClass ?>">
                    <option value="">Keep original</option>
                    <option value="jpg">JPEG</option>
                    <option value="webp">WebP</option>
                    <option value="png">PNG</option>
                </select>
            </div>
            <div>
                <?= $toggleRow('mode', 'Save as', ['new' => 'New image', 'overwrite' => 'Overwrite'], 'new') ?>
                <p id="media-mode-hint" class="mt-1 <?= $hintClass ?>"></p>
            </div>
        </div>

        <p id="media-editor-status" role="alert" class="hidden text-sm text-red-600 dark:text-red-400"></p>

        <div class="flex flex-wrap items-center gap-2 pt-2">
            {% cmp="btn" variant="red" label="Delete" attrs="{$deleteAttrs}" %}
            <span class="flex-1"></span>
            {% cmp="btn" variant="slate" label="Cancel" attrs="{$cancelAttrs}" %}
            {% cmp="btn" variant="blue" label="Apply" attrs="{$applyAttrs}" %}
        </div>
    </div>
</form>
<?php
$editorBody = ob_get_clean();
?>
{% cmp="modal" id="media-editor" title="Edit image" size="xl" bare="1" body="{$editorBody}" %}
{% endblock %}
{% block scripts %}
<script src="/assets/vendor/cropper/cropper.min.js" nonce="<?= csp_nonce() ?>"></script>
<script src="/cp-assets/js/media-editor.js" nonce="<?= csp_nonce() ?>"></script>
<script src="/cp-assets/js/media-library.js" nonce="<?= csp_nonce() ?>"></script>
{% endblock %}
