{% extends "back.lex.php" %}

{% block title %}Edit Page{% endblock %}
{% block subtitle %}{{ page.title }} ({{ page.locale }}) at /{{ page.slug }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
{% endblock %}

{% block body %}
<?php
$formErrors = errors();
$pageTitle = old('title', $page['title'] ?? '');
$pageMeta = old('meta_description', $page['meta_description'] ?? '');
$pageContent = old('content', $page['content'] ?? '');
$isPublished = (int) old('is_published', $page['is_published'] ?? 0) === 1;
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-5xl">

    <form method="POST" action="/admin/pages/<?= (int) $page['id'] ?>/update"
          enctype="multipart/form-data" data-dropzone-form class="space-y-5">
        {{ csrf_field() }}

        <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">
            <div class="card">
                <div class="card-body">
                    <div class="grid grid-cols-1 gap-5">
                        {% cmp="input" type="text" label="Title" name="title" value="{$pageTitle}" required %}
                        {% cmp="input" type="text" label="Meta description" name="meta_description" value="{$pageMeta}" underlabel="Shown in search results. 160 characters max." %}
                    </div>

                    <div class="mt-5">
                        <label for="content" class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">Content</label>
                        <textarea id="content" name="content" rows="18" required
                                  class="form-input w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500"><?= e($pageContent) ?></textarea>
                        <p class="mt-1 text-xs text-slate-400 dark:text-zink-300">Headings start at h2 because the page title is the h1.</p>
                        <?php if (!empty($formErrors['content'])) { ?>
                            <p class="mt-1 text-xs text-red-500"><?= e(is_array($formErrors['content']) ? implode(' ', $formErrors['content']) : $formErrors['content']) ?></p>
                        <?php } ?>
                    </div>

                    <div class="mt-5">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-zink-100">
                            <input type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>
                                   class="form-checkbox border-slate-200 dark:border-zink-500" />
                            Published (visible to visitors)
                        </label>
                    </div>
                </div>

                <div class="card-body flex items-center justify-between border-t border-slate-100 dark:border-zink-600">
                    <a href="/admin/pages" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 dark:text-zink-200 border border-slate-200 dark:border-zink-500 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                        {% cache 'lucide:arrow-left' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4"></i>{% endcache %} Back to pages
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                        {% cache 'lucide:save' ttl=31536000 %}<i data-lucide="save" class="size-4"></i>{% endcache %} Save Page
                    </button>
                </div>
            </div>

            <aside class="space-y-4">
                {% cmp="dropzone" label="Thumbnail" name="thumbnail" resource="{$page}" imageClass="object-cover w-full rounded-md h-32" %}
            </aside>
        </div>
    </form>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="/cp-assets/js/dropzone.init.js"></script>
<script nonce="<?= csp_nonce() ?>">
(function () {
    var isDark = localStorage.getItem('data-mode') === 'dark';
    tinymce.init({
        selector: '#content',
        height: 460,
        license_key: 'gpl',
        promotion: false,
        skin: isDark ? 'oxide-dark' : 'oxide',
        content_css: isDark ? 'dark' : 'default',
        menubar: false,
        plugins: 'lists link image table code fullscreen searchreplace preview wordcount visualblocks',
        toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link image table | visualblocks preview code fullscreen',
        branding: false,
        statusbar: true,
        setup: function (editor) {
            editor.on('change', function () { tinymce.triggerSave(); });
        },
    });
})();
</script>
{% endblock %}
