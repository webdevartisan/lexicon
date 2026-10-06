{% extends "back.lex.php" %}

{% block title %}Email Blocks{% endblock %}
{% block subtitle %}The pieces templates are built from. Change a block and every template using it changes with it.{% endblock %}

{% block body %}
<?php
$activeTab = 'components';
$basePath = '/admin/email-templates/components';

$byCategory = [];
foreach ($components as $slug => $component) {
    $byCategory[$component['category']][$slug] = $component;
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <div class="flex flex-col gap-3 mb-5 md:flex-row md:items-center">
        <div class="grow md:max-w-xs">
            <label for="component-search" class="sr-only">Search blocks</label>
            <input type="search" id="component-search" value="<?= e($search) ?>" placeholder="Search blocks…" class="<?= e($fieldClass) ?>">
        </div>
        <div>
            <label for="component-category" class="sr-only">Category</label>
            <select id="component-category" class="<?= e($selectClass) ?>">
                <option value="">All categories</option>
                <?php foreach ($categories as $key => $label) { ?>
                <option value="<?= e($key) ?>" <?= $categoryFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="md:ml-auto">
            <a href="<?= e(lurl($basePath.'/create')) ?>" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                <i data-lucide="plus" class="size-4"></i> New block
            </a>
        </div>
    </div>

    <div data-filter-rows class="space-y-6">
        <?php foreach ($categories as $categoryKey => $categoryLabel) {
            if (empty($byCategory[$categoryKey])) {
                continue;
            } ?>
        <section data-filter-group>
            <h2 class="mb-3 text-base font-semibold text-slate-900 dark:text-zink-50"><?= e($categoryLabel) ?></h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($byCategory[$categoryKey] as $slug => $component) {
                    $names = \App\Services\TemplateRendererService::placeholdersOfComponent($component);
                    $templatesUsing = $usage[$slug] ?? []; ?>
                <a href="<?= e(lurl($basePath.'/'.$slug.'/edit')) ?>"
                   data-search="<?= e(strtolower($component['label'].' '.$slug.' '.$component['description'].' '.implode(' ', $names))) ?>"
                   data-category="<?= e($categoryKey) ?>"
                   class="block p-4 transition-shadow bg-white border rounded-md border-slate-200 hover:shadow-md hover:border-custom-300 dark:bg-zink-700 dark:border-zink-500">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-medium text-slate-900 dark:text-zink-50"><?= e($component['label']) ?></h3>
                            <code class="text-xs text-slate-400"><?= e($slug) ?></code>
                        </div>
                        <?= $sourceBadge($component['source']) ?>
                    </div>
                    <?php if ($component['description'] !== '') { ?>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zink-300"><?= e($component['description']) ?></p>
                    <?php } ?>
                    <div class="flex flex-wrap gap-1 mt-3">
                        <?php foreach ($names as $name) { ?>
                        <code class="px-1.5 py-0.5 text-xs rounded bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200"><?= e($ph($name)) ?></code>
                        <?php } ?>
                        <?php if ($names === []) { ?>
                        <span class="text-xs text-slate-400">No placeholders</span>
                        <?php } ?>
                    </div>
                    <p class="mt-3 text-xs text-slate-500 dark:text-zink-300">
                        <?= $templatesUsing === [] ? 'Not used in any template' : 'Used in '.count($templatesUsing).' template'.(count($templatesUsing) === 1 ? '' : 's').': '.e(implode(', ', $templatesUsing)) ?>
                    </p>
                </a>
                <?php } ?>
            </div>
        </section>
        <?php } ?>
    </div>
    <p data-filter-empty hidden class="py-8 text-center text-sm text-slate-500">No blocks match.</p>
</div>

{% include "areas/admin/EmailTemplate/_filter.lex.php" %}
{% endblock %}
