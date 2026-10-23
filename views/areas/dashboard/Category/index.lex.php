{% extends "back.lex.php" %}
{% block title %}Categories & Tags{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">Organise this blog's posts. Both are optional, so use what helps.</span>{% endblock %}
{% block body %}
<?php
// Small renderer for one taxonomy panel so categories and tags stay in sync.
$panel = function (string $type, string $title, string $icon, array $items, string $addTitle, string $emptyText, array $blog): void {
    ?>
    <section class="card">
        <div class="card-body">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                    {% cache 'lucide:' . $icon %}<i data-lucide="<?= e($icon) ?>" class="size-4 text-custom-500"></i>{% endcache %}
                    <?= e($title) ?>
                    <span class="text-xs font-normal text-slate-500 dark:text-zink-300"><?= count($items) ?></span>
                </h2>
                <button type="button" data-modal-target="taxonomyModal" data-taxonomy="add" data-type="<?= e($type) ?>" data-title="<?= e($addTitle) ?>"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors shrink-0">
                    {% cache 'lucide:plus' ttl=31536000 %}<i data-lucide="plus" class="size-4"></i>{% endcache %} Add
                </button>
            </div>

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
                        data-modal-target="taxonomyModal" data-taxonomy="rename" data-type="<?= e($type) ?>" data-id="<?= (int) $item['id'] ?>" data-name="<?= e($item['name']) ?>" data-title="<?= e('Rename '.$type) ?>"
                        class="p-1.5 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
                        {% cache 'lucide:pencil' ttl=31536000 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                    </button>
                    <form method="POST" action="/dashboard/blog/<?= (int) $blog['id'] ?>/categories" class="m-0 shrink-0">
                        {{ csrf_field() }}
                        <input type="hidden" name="type" value="<?= e($type) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" title="Delete"
                            data-confirm="Delete <?= e($item['name']) ?>? Posts keep their content, they just lose this <?= e($type) ?>."
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php
        $panel('category', 'Categories', 'folder-tree', $categories, 'New category', 'No categories yet. A post can have one category.', $blog);
$panel('tag', 'Tags', 'tags', $tags, 'New tag', 'No tags yet. You can also add tags straight from the post editor.', $blog);
?>
    </div>

</div>

<?php ob_start(); ?>
<form method="POST" action="/dashboard/blog/<?= (int) $blog['id'] ?>/categories" id="taxonomyForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="id" value="">
    {% cmp="input" type="text" name="name" label="Name" required="1" %}
</form>
<?php $taxonomyBody = ob_get_clean(); ?>
{% cmp="modal" id="taxonomyModal" title="New category" icon="folder-tree" body="{$taxonomyBody}" form="taxonomyForm" confirmText="Save" confirmIcon="check" size="sm" %}
{% endblock %}
{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
(function () {
    var form = document.getElementById('taxonomyForm');
    if (!form) return;

    // Runs in the capture phase so the fields are filled before the modal opens and focuses the input
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-taxonomy]');
        if (!btn) return;

        var renaming = btn.dataset.taxonomy === 'rename';
        form.elements.type.value = btn.dataset.type;
        form.elements.action.value = renaming ? 'rename' : 'add';
        form.elements.id.value = renaming ? btn.dataset.id : '';
        form.elements.name.value = renaming ? btn.dataset.name : '';

        var title = document.getElementById('taxonomyModal-title');
        if (title) title.textContent = btn.dataset.title;
    }, true);
})();
</script>
{% endblock %}
