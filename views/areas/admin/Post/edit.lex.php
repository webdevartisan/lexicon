{% extends "back.lex.php" %}

{% block title %}Edit Post{% endblock %}
{% block subtitle %}Content, organization, publishing and SEO for this post.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/dropzone.css">
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
{% endblock %}

{% block body %}
<?php
$errors = errors();
$postId = (int) ($post['id'] ?? 0);

$sectionTitle = 'text-base font-semibold text-slate-900 dark:text-zink-50 mb-1';
$sectionHint = 'text-sm text-slate-500 dark:text-zink-300 mb-5';
$small = 'text-xs text-slate-500 dark:text-zink-300';

$title = old('title', $post['title'] ?? '');
$slug = old('slug', $post['slug'] ?? '');
$content = old('content', $post['content'] ?? '');
$excerpt = old('excerpt', $post['excerpt'] ?? '');

$moderated = ($post['status'] ?? '') === 'moderated';
$statusChoices = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
$selectedStatus = old('status', $post['status'] ?? 'draft');
$blogChoices = array_column($blogs ?? [], 'blog_name', 'id');
$selectedBlog = (string) old('blog_id', $post['blog_id'] ?? '');
$visibilityChoices = ['public' => 'Public', 'unlisted' => 'Unlisted (link only)', 'private' => 'Private'];
$selectedVisibility = old('visibility', $post['visibility'] ?? 'public');
$selectedCategory = (string) old('category_id', $post['category_id'] ?? '');
$commentsOn = (string) old('comments_enabled', (string) ($post['comments_enabled'] ?? '1')) === '1';

$metaTitle = old('meta_title', $post['meta_title'] ?? '');
$metaDescription = old('meta_description', $post['meta_description'] ?? '');
$focusKeyword = old('focus_keyword', $post['focus_keyword'] ?? '');
$canonicalUrl = old('canonical_url', $post['canonical_url'] ?? '');
$ogTitle = old('og_title', $post['og_title'] ?? '');
$ogDescription = old('og_description', $post['og_description'] ?? '');

$publishedAt = !empty($post['published_at']) ? local_datetime($post['published_at'], 'M j, Y · g:i a') : 'Not published yet';

$saveBar = static function (string $section): string {
    return '<div class="flex justify-end mt-6">'
        .'<button type="submit" name="_section" value="'.e($section).'" class="btn inline-flex items-center gap-2 font-medium rounded-lg bg-slate-900 text-white border-slate-900 hover:bg-slate-700 hover:border-slate-700 dark:bg-zink-100 dark:text-zink-800 dark:border-zink-100 dark:hover:bg-white">'
        .'<i data-lucide="save" class="size-4"></i> Save changes</button></div>';
};

$switch = static function (string $name, string $label, string $hint, bool $on) use ($small): string {
    return '<div class="flex items-center justify-between gap-4"><div class="flex flex-col gap-0.5">'
        .'<label for="'.e($name).'" class="text-sm font-medium">'.e($label).'</label>'
        .'<span class="'.$small.'">'.e($hint).'</span></div>'
        .'<input type="hidden" name="'.e($name).'" value="0">'
        .'<input type="checkbox" role="switch" class="switch" id="'.e($name).'" name="'.e($name).'" value="1"'.($on ? ' checked' : '').'></div>';
};

$tabs = [
    'content' => ['file-text', 'Content'],
    'organization' => ['folder', 'Organization'],
    'publishing' => ['send', 'Publishing'],
    'seo' => ['search', 'SEO & social'],
    'danger' => ['alert-triangle', 'Danger zone'],
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-6xl">
    {% include "areas/admin/_errors.lex.php" %}

    <div class="settings-layout" data-panel-tabs>
        <nav class="settings-nav" role="tablist" aria-label="Post sections">
            <?php foreach ($tabs as $key => [$icon, $label]) { ?>
            <button type="button" role="tab" data-panel-tab="<?= e($key) ?>" class="settings-nav-item <?= $key === 'content' ? 'active' : '' ?>">
                <i data-lucide="<?= e($icon) ?>" class="size-4"></i> <?= e($label) ?>
            </button>
            <?php } ?>
        </nav>

        <div class="settings-panels">
            <form method="post" action="/admin/posts/<?= $postId ?>/update" enctype="multipart/form-data" data-dropzone-form>
                {{ csrf_field() }}

                <section data-panel="content" class="card">
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Content</h3>
                        <p class="<?= $sectionHint ?>">The words readers see.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {% cmp="input" type="text" label="Title" name="title" value="{$title}" required %}
                            {% cmp="input" type="text" label="Slug" name="slug" value="{$slug}" underlabel="The post's address. Changing it breaks existing links to it." %}
                        </div>
                        <div class="mt-5">
                            {% cmp="input" type="textarea" label="Content" name="content" value="{$content}" rows="14" %}
                        </div>
                        <div class="mt-5">
                            {% cmp="input" type="textarea" label="Excerpt" name="excerpt" value="{$excerpt}" rows="3" %}
                        </div>
                        <div class="mt-5">
                            {% cmp="dropzone" label="Featured image" name="featured_image" resource="{$post}" imageClass="object-cover w-full max-w-md rounded-md h-40" %}
                        </div>
                        <?= $saveBar('content') ?>
                    </div>
                </section>

                <section data-panel="organization" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Organization</h3>
                        <p class="<?= $sectionHint ?>">Where the post lives, how it is filed, and who can see it.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {% cmp="searchable-select" name="blog_id" label="Blog" options="{$blogChoices}" selectedKey="{$selectedBlog}" placeholder="Choose a blog..." required %}
                            {% cmp="select" label="Category" name="category_id" options="{$categoryChoices}" selectedKey="{$selectedCategory}" %}
                            {% cmp="select" label="Visibility" name="visibility" options="{$visibilityChoices}" selectedKey="{$selectedVisibility}" %}
                            <div>
                                <p class="text-sm font-medium mb-2">Author</p>
                                <p class="text-sm"><?= !empty($author['handle']) ? '@'.e((string) $author['handle']) : 'Unknown' ?></p>
                            </div>
                        </div>
                        <p class="<?= $small ?> mt-3">Moving the post to another blog clears its category and tags.</p>

                        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-zink-600">
                            <p class="text-sm font-medium mb-3">Tags</p>
                            <?php if (empty($blogTags)) { ?>
                            <p class="<?= $small ?>">This blog has no tags yet.</p>
                            <?php } else { ?>
                            <div class="flex flex-wrap gap-x-5 gap-y-2">
                                <?php foreach ($blogTags as $tag) {
                                    $checked = in_array((int) $tag['id'], array_map('intval', (array) old('tag_ids', $postTagIds)), true); ?>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500" <?= $checked ? 'checked' : '' ?>>
                                    <?= e((string) $tag['name']) ?>
                                </label>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-zink-600">
                            <?= $switch('comments_enabled', 'Allow comments on this post', 'Off hides the comment box and closes the thread.', $commentsOn) ?>
                        </div>
                        <?= $saveBar('organization') ?>
                    </div>
                </section>

                <section data-panel="publishing" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Publishing</h3>
                        <p class="<?= $sectionHint ?>">Whether the post is live.</p>
                        <?php if ($moderated) { ?>
                        <div class="p-3 mb-5 text-sm rounded-lg border border-amber-200 bg-amber-50 text-slate-700 dark:bg-amber-900/20 dark:border-amber-800 dark:text-zink-200">
                            Hidden by a moderation decision<?php if ((int) ($post['reports_count'] ?? 0) > 0) { ?> after <?= (int) $post['reports_count'] ?> report<?= (int) $post['reports_count'] === 1 ? '' : 's' ?><?php } ?>.
                            Restore it from its report case; saving here keeps it hidden.
                        </div>
                        <?php } ?>
                        <div class="max-w-xs">
                            {% cmp="select" label="Status" name="status" options="{$statusChoices}" selectedKey="{$selectedStatus}" %}
                        </div>
                        <p class="<?= $small ?> mt-4">Published: <?= e($publishedAt) ?></p>
                        <?= $saveBar('publishing') ?>
                    </div>
                </section>

                <section data-panel="seo" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">SEO &amp; social</h3>
                        <p class="<?= $sectionHint ?>">How the post appears in search results and when shared. Blank fields fall back to the title and excerpt.</p>
                        <div class="grid grid-cols-1 gap-5">
                            {% cmp="input" type="text" label="Meta title" name="meta_title" value="{$metaTitle}" %}
                            {% cmp="input" type="textarea" label="Meta description" name="meta_description" value="{$metaDescription}" rows="3" %}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                {% cmp="input" type="text" label="Focus keyword" name="focus_keyword" value="{$focusKeyword}" %}
                                {% cmp="input" type="text" label="Canonical URL" name="canonical_url" value="{$canonicalUrl}" placeholder="https://" %}
                            </div>
                            {% cmp="input" type="text" label="Social title" name="og_title" value="{$ogTitle}" %}
                            {% cmp="input" type="textarea" label="Social description" name="og_description" value="{$ogDescription}" rows="2" %}
                        </div>
                        <div class="flex flex-col gap-4 mt-6 pt-6 border-t border-slate-100 dark:border-zink-600">
                            <?= $switch('meta_noindex', 'Ask search engines not to index this post', 'Adds noindex to the page.', (string) old('meta_noindex', (string) ($post['meta_noindex'] ?? '0')) === '1') ?>
                            <?= $switch('meta_nofollow', 'Ask search engines not to follow its links', 'Adds nofollow to the page.', (string) old('meta_nofollow', (string) ($post['meta_nofollow'] ?? '0')) === '1') ?>
                        </div>
                        <?= $saveBar('seo') ?>
                    </div>
                </section>
            </form>

            <section data-panel="danger" class="card" hidden>
                <div class="card-body">
                    <h3 class="<?= $sectionTitle ?>">Danger zone</h3>
                    <p class="<?= $sectionHint ?>">Deleting a post removes it and its comments for good.</p>
                    <?php $deleteHref = '/admin/posts/'.$postId.'/delete'; ?>
                    {% cmp="btn" href="{$deleteHref}" variant="red" icon="trash-2" label="Delete this post" %}
                </div>
            </section>
        </div>
    </div>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/dropzone/dropzone-min.js"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/dropzone.init.js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>">
(function () {
    function startEditor() {
        var isDark = document.documentElement.getAttribute('data-mode') === 'dark';
        tinymce.init({
            selector: '#content',
            height: 480,
            license_key: 'gpl',
            promotion: false,
            relative_urls: false,
            remove_script_host: true,
            skin: isDark ? 'oxide-dark' : 'oxide',
            content_css: isDark ? 'dark' : 'default',
            content_style: 'body { background-color: ' + (isDark ? '#171717' : '#ffffff') + '; } ' + (isDark ? '.mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before { color: rgba(255, 255, 255, 0.4); } ' : '') + 'img { max-width: 100%; height: auto; }',
            menubar: false,
            plugins: 'lists link table code fullscreen searchreplace preview wordcount visualblocks',
            toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link table | visualblocks preview code fullscreen',
            branding: false,
            statusbar: true,
            setup: function (editor) {
                editor.on('change', function () { tinymce.triggerSave(); });
            },
        });
    }

    startEditor();

    // The skin is fixed when TinyMCE starts, so a mode switch needs a fresh instance; triggerSave keeps the typed content.
    var lastMode = document.documentElement.getAttribute('data-mode');
    new MutationObserver(function () {
        var mode = document.documentElement.getAttribute('data-mode');
        if (mode === lastMode) { return; }
        lastMode = mode;
        tinymce.triggerSave();
        tinymce.remove('#content');
        startEditor();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-mode'] });
})();
</script>
{% endblock %}
