{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Categories & Tags{% endblock %}
{% block subtitle %}Organise this blog's posts. Both are optional — use what helps.{% endblock %}
{% block body %}
<?php
// Small renderer for one taxonomy panel so categories and tags stay in sync.
$panel = function (string $type, string $title, string $icon, array $items, string $addPlaceholder, string $emptyText, array $blog): void {
    ?>
    <section class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                    {% cache 'lucide:' . $icon %}<i data-lucide="<?= e($icon) ?>" class="size-4 text-custom-500"></i>{% endcache %}
                    <?= e($title) ?>
                </h2>
                <span class="text-xs text-slate-500 dark:text-zink-300"><?= count($items) ?></span>
            </div>

            <form method="POST" action="/dashboard/blog/<?= (int) $blog['id'] ?>/categories" class="flex items-end gap-2 mb-4">
                {{ csrf_field() }}
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <input type="hidden" name="action" value="add">
                <div class="grow">
                    {% cmp="input" type="text" name="name" placeholder="{$addPlaceholder}" required %}
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors shrink-0">
                    {% cache 'lucide:plus' ttl=31536000 %}<i data-lucide="plus" class="size-4"></i>{% endcache %} Add
                </button>
            </form>

            <?php if (empty($items)) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center"><?= e($emptyText) ?></p>
            <?php } else { ?>
            <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                <?php foreach ($items as $item) { ?>
                <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                    <div class="grow min-w-0">
                        <span class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate"><?= e($item['name']) ?></span>
                        <span class="text-xs text-slate-400 dark:text-zink-300 ml-1">/<?= e($item['slug']) ?></span>
                    </div>
                    <span class="text-[11px] text-slate-500 dark:text-zink-300 shrink-0">
                        <?= (int) ($item['post_count'] ?? 0) ?> <?= ((int) ($item['post_count'] ?? 0) === 1) ? 'post' : 'posts' ?>
                    </span>
                    <button type="button" title="Rename"
                        data-rename data-type="<?= e($type) ?>" data-id="<?= (int) $item['id'] ?>" data-name="<?= e($item['name']) ?>"
                        class="p-1.5 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
                        {% cache 'lucide:pencil' ttl=31536000 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                    </button>
                    <form method="POST" action="/dashboard/blog/<?= (int) $blog['id'] ?>/categories" class="m-0 shrink-0"
                        onsubmit="return confirm('Delete <?= e($item['name']) ?>? Posts keep their content, they just lose this <?= e($type) ?>.');">
                        {{ csrf_field() }}
                        <input type="hidden" name="type" value="<?= e($type) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" title="Delete"
                            class="p-1.5 text-slate-500 hover:text-red-600 rounded-md hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            {% cache 'lucide:trash-2' ttl=31536000 %}<i data-lucide="trash-2" class="size-4"></i>{% endcache %}
                        </button>
                    </form>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
        </div>
    </section>
    <?php
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/blog/{{ blog.id }}/show"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            {% cache 'lucide:arrow-left' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4"></i>{% endcache %}
            <span>Back to blog</span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php
        $panel('category', 'Categories', 'folder-tree', $categories, 'New category name…', 'No categories yet. A post can have one category.', $blog);
$panel('tag', 'Tags', 'tags', $tags, 'New tag name…', 'No tags yet. You can also add tags straight from the post editor.', $blog);
?>
    </div>

    <!-- Shared rename form, filled in by the rename buttons. -->
    <form method="POST" action="/dashboard/blog/{{ blog.id }}/categories" id="renameForm" class="hidden">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="type" id="renameType">
        <input type="hidden" name="id" id="renameId">
        <input type="hidden" name="name" id="renameName">
    </form>

</div>
{% endblock %}
{% block scripts %}
<script>
document.querySelectorAll('[data-rename]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var current = btn.dataset.name;
        var next = window.prompt('Rename "' + current + '" to:', current);
        if (next === null) return;
        next = next.trim();
        if (next === '' || next === current) return;

        document.getElementById('renameType').value = btn.dataset.type;
        document.getElementById('renameId').value = btn.dataset.id;
        document.getElementById('renameName').value = next;
        document.getElementById('renameForm').submit();
    });
});
</script>
{% endblock %}
