{% extends "back.lex.php" %}

{% block title %}Email Templates{% endblock %}
{% block subtitle %}Layouts made of blocks. Each email the site sends uses one, with its own wording.{% endblock %}

{% block body %}
<?php $activeTab = 'templates'; ?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <div class="card">
        <div class="card-body">
            <div class="flex flex-col gap-3 mb-4 md:flex-row md:items-center">
                <div class="grow md:max-w-xs">
                    <label for="template-search" class="sr-only">Search templates</label>
                    <input type="search" id="template-search" value="<?= e($search) ?>" placeholder="Search templates…" class="<?= e($fieldClass) ?>">
                </div>
                <div>
                    <label for="template-category" class="sr-only">Category</label>
                    <select id="template-category" class="<?= e($selectClass) ?>">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $key => $label) { ?>
                        <option value="<?= e($key) ?>" <?= $categoryFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="md:ml-auto">
                    <a href="<?= e(lurl('/admin/email-templates/create')) ?>" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600 inline-flex items-center gap-2">
                        <i data-lucide="plus" class="size-4"></i> New template
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="text-left text-slate-500 dark:text-zink-200 bg-slate-100 dark:bg-zink-600">
                        <tr>
                            <th class="px-3.5 py-2.5 font-semibold">Template</th>
                            <th class="px-3.5 py-2.5 font-semibold">Category</th>
                            <th class="px-3.5 py-2.5 font-semibold">Blocks</th>
                            <th class="px-3.5 py-2.5 font-semibold">Used by</th>
                            <th class="px-3.5 py-2.5 font-semibold">Source</th>
                            <th class="px-3.5 py-2.5 font-semibold text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody data-filter-rows>
                        <?php foreach ($templates as $slug => $template) {
                            $emails = $usage[$slug] ?? []; ?>
                        <tr class="border-b border-slate-200 dark:border-zink-500"
                            data-search="<?= e(strtolower($template['label'].' '.$slug.' '.$template['description'])) ?>"
                            data-category="<?= e($template['category']) ?>">
                            <td class="px-3.5 py-2.5 whitespace-normal">
                                <a href="<?= e(lurl('/admin/email-templates/'.$slug.'/edit')) ?>" class="font-medium text-slate-900 hover:text-custom-500 dark:text-zink-50"><?= e($template['label']) ?></a>
                                <code class="ml-1 text-xs text-slate-400"><?= e($slug) ?></code>
                                <?php if ($template['description'] !== '') { ?>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-300 max-w-md"><?= e($template['description']) ?></p>
                                <?php } ?>
                            </td>
                            <td class="px-3.5 py-2.5"><?= e($categories[$template['category']] ?? $template['category']) ?></td>
                            <td class="px-3.5 py-2.5"><?= count($template['layout']) ?></td>
                            <td class="px-3.5 py-2.5">
                                <?php if ($emails === []) { ?>
                                <span class="text-slate-400">No emails</span>
                                <?php } else { ?>
                                <span title="<?= e(implode(', ', $emails)) ?>"><?= count($emails) ?> email<?= count($emails) === 1 ? '' : 's' ?></span>
                                <?php } ?>
                            </td>
                            <td class="px-3.5 py-2.5"><?= $sourceBadge($template['source']) ?></td>
                            <td class="px-3.5 py-2.5 text-right">
                                <a href="<?= e(lurl('/admin/email-templates/'.$slug.'/preview')) ?>" class="inline-flex items-center gap-1 px-2 py-1 text-slate-500 hover:text-custom-500" title="Preview">
                                    <i data-lucide="eye" class="size-4"></i><span class="sr-only">Preview <?= e($template['label']) ?></span>
                                </a>
                                <a href="<?= e(lurl('/admin/email-templates/'.$slug.'/edit')) ?>" class="inline-flex items-center gap-1 px-2 py-1 text-slate-500 hover:text-custom-500" title="Edit">
                                    <i data-lucide="pencil" class="size-4"></i><span class="sr-only">Edit <?= e($template['label']) ?></span>
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <p data-filter-empty hidden class="py-8 text-center text-sm text-slate-500">No templates match.</p>
            </div>
        </div>
    </div>
</div>

{% include "areas/admin/EmailTemplate/_filter.lex.php" %}
{% endblock %}
