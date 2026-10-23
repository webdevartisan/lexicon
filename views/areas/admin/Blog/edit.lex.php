{% extends "back.lex.php" %}

{% block title %}Edit Blog{% endblock %}
{% block subtitle %}Identity, visibility, discussion and ownership for this blog.{% endblock %}

{% block body %}
<?php
$errors = errors();
$blogId = (int) ($blog['id'] ?? 0);

$sectionTitle = 'text-base font-semibold text-slate-900 dark:text-zink-50 mb-1';
$sectionHint = 'text-sm text-slate-500 dark:text-zink-300 mb-5';
$small = 'text-xs text-slate-500 dark:text-zink-300';

$blogName = old('blog_name', $blog['blog_name'] ?? '');
$blogSlug = old('blog_slug', $blog['blog_slug'] ?? '');
$description = old('description', $blog['description'] ?? '');
$status = old('status', $blog['status'] ?? 'draft');
$suspended = ($blog['status'] ?? '') === 'suspended';

$theme = old('theme', $settings['theme'] ?? 'folio');
$tagline = old('tagline', $settings['tagline'] ?? '');
$locale = old('default_locale', $settings['default_locale'] ?? '');
$timezone = old('timezone', $settings['timezone'] ?? 'UTC');
$metaTitle = old('meta_title', $settings['meta_title'] ?? '');
$metaDescription = old('meta_description', $settings['meta_description'] ?? '');

$switches = [
    'discussion' => [
        ['comments_enabled', 'Allow comments', 'Readers can comment on posts.', 1],
        ['comments_auto_publish', 'Publish comments straight away', 'Off holds new comments as pending until approved.', 1],
        ['replies_auto_publish', 'Publish replies straight away', 'Off holds replies to comments as pending too.', 1],
    ],
    'seo' => [
        ['indexable', 'Let search engines index this blog', 'Off adds a noindex hint to every page.', 1],
    ],
];

$saveBar = static function (string $section): string {
    return '<div class="flex justify-end mt-6">'
        .'<button type="submit" name="_section" value="'.e($section).'" class="btn inline-flex items-center gap-2 font-medium rounded-lg bg-slate-900 text-white border-slate-900 hover:bg-slate-700 hover:border-slate-700 dark:bg-zink-100 dark:text-zink-800 dark:border-zink-100 dark:hover:bg-white">'
        .'<i data-lucide="save" class="size-4"></i> Save changes</button></div>';
};

$tabs = [
    'general' => ['book-open', 'General'],
    'seo' => ['search', 'Visibility & SEO'],
    'discussion' => ['message-square', 'Discussion'],
    'appearance' => ['palette', 'Appearance'],
    'members' => ['users', 'Owner & members'],
    'danger' => ['alert-triangle', 'Danger zone'],
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-6xl">
    {% include "areas/admin/_errors.lex.php" %}

    <div class="settings-layout" data-panel-tabs>
        <nav class="settings-nav" role="tablist" aria-label="Blog sections">
            <?php foreach ($tabs as $key => [$icon, $label]) { ?>
            <button type="button" role="tab" data-panel-tab="<?= e($key) ?>" class="settings-nav-item <?= $key === 'general' ? 'active' : '' ?>">
                <i data-lucide="<?= e($icon) ?>" class="size-4"></i> <?= e($label) ?>
            </button>
            <?php } ?>
        </nav>

        <div class="settings-panels">
            <form method="post" action="/admin/blogs/<?= $blogId ?>/update">
                {{ csrf_field() }}

                <section data-panel="general" class="card">
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">General</h3>
                        <p class="<?= $sectionHint ?>">What the blog is called and whether visitors can see it.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {% cmp="input" type="text" label="Name" name="blog_name" value="{$blogName}" required %}
                            {% cmp="input" type="text" label="Slug" name="blog_slug" value="{$blogSlug}" underlabel="The blog's address. Changing it breaks existing links to it." %}
                        </div>
                        <div class="mt-5">
                            {% cmp="input" type="textarea" label="Description" name="description" value="{$description}" rows="4" %}
                        </div>
                        <div class="mt-5 max-w-xs">
                            <?php if ($suspended) { ?>
                            <p class="text-sm font-medium mb-2">Status</p>
                            <span class="pill bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Suspended</span>
                            <p class="<?= $small ?> mt-2">Hidden because its owner is suspended. Lift that suspension to change the status.</p>
                            <?php } else { ?>
                            {% cmp="select" label="Status" name="status" options="{$statusChoices}" selectedKey="{$status}" %}
                            <?php } ?>
                        </div>
                        <?= $saveBar('general') ?>
                    </div>
                </section>

                <section data-panel="seo" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Visibility &amp; SEO</h3>
                        <p class="<?= $sectionHint ?>">How the blog appears in search results.</p>
                        <div class="grid grid-cols-1 gap-5">
                            {% cmp="input" type="text" label="Meta title" name="meta_title" value="{$metaTitle}" %}
                            {% cmp="input" type="textarea" label="Meta description" name="meta_description" value="{$metaDescription}" rows="3" %}
                        </div>
                        <div class="flex flex-col gap-4 mt-6 pt-6 border-t border-slate-100 dark:border-zink-600">
                            <?php foreach ($switches['seo'] as [$name, $switchLabel, $hint]) {
                                $on = (string) old($name, (string) ($settings[$name] ?? '1')) === '1'; ?>
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex flex-col gap-0.5">
                                    <label for="<?= e($name) ?>" class="text-sm font-medium"><?= e($switchLabel) ?></label>
                                    <span class="<?= $small ?>"><?= e($hint) ?></span>
                                </div>
                                <input type="hidden" name="<?= e($name) ?>" value="0">
                                <input type="checkbox" role="switch" class="switch" id="<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= $on ? 'checked' : '' ?>>
                            </div>
                            <?php } ?>
                        </div>
                        <?= $saveBar('seo') ?>
                    </div>
                </section>

                <section data-panel="discussion" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Discussion</h3>
                        <p class="<?= $sectionHint ?>">Whether readers can comment, and whether comments need approval first.</p>
                        <div class="flex flex-col gap-4">
                            <?php foreach ($switches['discussion'] as [$name, $switchLabel, $hint]) {
                                $on = (string) old($name, (string) ($settings[$name] ?? '1')) === '1'; ?>
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex flex-col gap-0.5">
                                    <label for="<?= e($name) ?>" class="text-sm font-medium"><?= e($switchLabel) ?></label>
                                    <span class="<?= $small ?>"><?= e($hint) ?></span>
                                </div>
                                <input type="hidden" name="<?= e($name) ?>" value="0">
                                <input type="checkbox" role="switch" class="switch" id="<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= $on ? 'checked' : '' ?>>
                            </div>
                            <?php } ?>
                        </div>
                        <?= $saveBar('discussion') ?>
                    </div>
                </section>

                <section data-panel="appearance" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Appearance</h3>
                        <p class="<?= $sectionHint ?>">The theme, tagline, language and timezone the blog uses.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {% cmp="select" label="Theme" name="theme" options="{$themeChoices}" selectedKey="{$theme}" %}
                            {% cmp="input" type="text" label="Tagline" name="tagline" value="{$tagline}" %}
                            {% cmp="select" label="Language" name="default_locale" options="{$localeChoices}" selectedKey="{$locale}" %}
                            {% cmp="searchable-select" name="timezone" label="Timezone" options="{$timezoneChoices}" selectedKey="{$timezone}" placeholder="Search timezones..." %}
                        </div>
                        <?= $saveBar('appearance') ?>
                    </div>
                </section>
            </form>

            <section data-panel="members" class="card" id="ownership" hidden>
                <div class="card-body">
                    <h3 class="<?= $sectionTitle ?>">Owner &amp; members</h3>
                    <p class="<?= $sectionHint ?>">
                        Owned by <strong><?= e((string) ($owner['handle'] ?? '')) ?></strong>.
                        <?php if (empty($members)) { ?>Ownership can only go to a collaborator, and this blog has none.<?php } else { ?>Hand it to a collaborator; the current owner stays on as an editor.<?php } ?>
                    </p>

                    <?php if (!empty($members)) { ?>
                    <ul class="mb-6 divide-y divide-slate-100 dark:divide-zink-600 border border-slate-100 dark:border-zink-600 rounded-lg">
                        <?php foreach ($members as $m) { ?>
                        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="font-medium">@<?= e((string) $m['handle']) ?></span>
                            <span class="pill bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200"><?= e((string) $m['role']) ?></span>
                        </li>
                        <?php } ?>
                    </ul>

                    <form method="post" action="/admin/blogs/<?= $blogId ?>/transfer" class="flex flex-wrap items-end gap-3">
                        {{ csrf_field() }}
                        <div>
                            <label for="new_owner_id" class="inline-block mb-2 text-base font-medium">New owner</label>
                            <select id="new_owner_id" name="new_owner_id" required class="form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                                <?php foreach ($members as $m) { ?>
                                <option value="<?= (int) $m['user_id'] ?>"><?= e($m['handle']) ?> (<?= e((string) $m['role']) ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php $transferConfirm = 'data-confirm="'.e('Transfer ownership of this blog?').'"'; ?>
                        {% cmp="btn" type="submit" variant="red" icon="key-round" label="Transfer ownership" dataBtn="{$transferConfirm}" %}
                    </form>
                    <?php } ?>
                </div>
            </section>

            <section data-panel="danger" class="card" hidden>
                <div class="card-body">
                    <h3 class="<?= $sectionTitle ?>">Danger zone</h3>
                    <p class="<?= $sectionHint ?>">Deleting a blog removes its posts, comments and settings for good.</p>
                    <?php $deleteHref = '/admin/blogs/'.$blogId.'/delete'; ?>
                    {% cmp="btn" href="{$deleteHref}" variant="red" icon="trash-2" label="Delete this blog" %}
                </div>
            </section>
        </div>
    </div>
</div>
{% endblock %}
