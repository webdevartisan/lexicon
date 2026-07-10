{% extends "back.lex.php" %}

{% block title %}Edit Page{% endblock %}
{% block subtitle %}{{ page.title }} ({{ page.locale }}) at /{{ page.slug }}{% endblock %}

{% block body %}
<?php
$formErrors = errors();
$pageTitle = old('title', $page['title'] ?? '');
$pageMeta = old('meta_description', $page['meta_description'] ?? '');
$pageContent = old('content', $page['content'] ?? '');
$isPublished = (int) old('is_published', $page['is_published'] ?? 0) === 1;
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-5xl">

    <form method="POST" action="/admin/pages/<?= (int) $page['id'] ?>/update" class="card">
        {{ csrf_field() }}

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">Title</label>
                    <input type="text" name="title" value="<?= e($pageTitle) ?>" required
                           class="form-input w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500" />
                    <?php if (!empty($formErrors['title'])) { ?>
                        <p class="mt-1 text-xs text-red-500"><?= e(is_array($formErrors['title']) ? implode(' ', $formErrors['title']) : $formErrors['title']) ?></p>
                    <?php } ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">Meta description</label>
                    <input type="text" name="meta_description" value="<?= e($pageMeta) ?>" maxlength="160"
                           class="form-input w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500" />
                    <p class="mt-1 text-xs text-slate-400 dark:text-zink-300">Shown in search results. 160 characters max.</p>
                </div>
            </div>

            <div class="mt-5">
                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1">Content</label>
                <textarea name="content" rows="22" required
                          class="form-input w-full font-mono text-sm border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500"><?= e($pageContent) ?></textarea>
                <p class="mt-1 text-xs text-slate-400 dark:text-zink-300">HTML is rendered as written. Use p, h2, ul and a tags; headings start at h2 because the page title is the h1.</p>
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
                <i data-lucide="arrow-left" class="size-4"></i> Back to pages
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                <i data-lucide="save" class="size-4"></i> Save Page
            </button>
        </div>
    </form>
</div>
{% endblock %}
