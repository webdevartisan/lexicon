{% extends "back.lex.php" %}

{% block title %}Browse compiled templates{% endblock %}

{% block subtitle %}Each page template turned into PHP{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <h6 class="text-15"><?= number_format(count($views)) ?> compiled templates</h6>
                <a href="<?= buildLocalizedUrl('/admin/cache') ?>" class="text-sm text-custom-500 hover:underline">Back to cache management</a>
            </div>

            <form action="<?= buildLocalizedUrl('/admin/cache/browse-compiled') ?>" method="GET" class="flex flex-wrap gap-3 mb-5">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by template path, e.g. sidebar or themes/"
                       class="form-input grow border-slate-200 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100">
                <button type="submit" class="btn text-white bg-custom-500 border-custom-500 hover:bg-custom-600">Filter</button>
            </form>

            <?php if ($views === []) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300">No compiled templates match.</p>
            <?php } else { ?>
            <?php
            $bulkActions = [
                ['action' => 'delete', 'label' => 'Delete', 'icon' => 'trash-2', 'hover' => 'hover:bg-red-600/30', 'danger' => true, 'confirm' => 'Delete {n}?'],
            ];
            ?>
            {% cmp="bulk-actions-bar" actionUrl="/admin/cache/delete-selected-compiled" actions="{$bulkActions}" headerSelectAll="1" %}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 dark:text-zink-300 border-b border-slate-200 dark:border-zink-500">
                            <th class="py-2 pr-3 w-8">
                                <input type="checkbox" id="select-all" aria-label="Select all templates"
                                       class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500">
                            </th>
                            <th class="py-2 pr-4 font-medium">Template</th>
                            <th class="py-2 pr-4 font-medium">Size</th>
                            <th class="py-2 pr-4 font-medium">Built</th>
                            <th class="py-2 pr-4 font-medium">State</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($views as $view) { ?>
                        <tr data-bulk-row class="border-b border-slate-100 dark:border-zink-600">
                            <td class="py-2 pr-3">
                                <input type="checkbox" name="ids[]" value="<?= e($view['id']) ?>" form="bulk-form" aria-label="Select template"
                                       class="bulk-checkbox form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500">
                            </td>
                            <td class="py-2 pr-4"><code class="text-xs font-mono break-all"><?= e($view['source']) ?></code></td>
                            <td class="py-2 pr-4 whitespace-nowrap"><?= number_format($view['size_bytes'] / 1024, 1) ?> KB</td>
                            <td class="py-2 pr-4 whitespace-nowrap"><?= e(local_datetime(gmdate('Y-m-d H:i:s', $view['compiled_at']), 'M j, Y H:i')) ?></td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <?php if ($view['stale']) { ?>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-300">Stale</span>
                                <?php } else { ?>
                                    <span class="text-slate-500 dark:text-zink-300">Current</span>
                                <?php } ?>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a href="<?= buildLocalizedUrl('/admin/cache/preview') ?>?id=<?= e($view['id']) ?>" class="mr-3 text-xs font-medium text-custom-500 hover:underline">Preview</a>
                                <form class="inline-block" action="<?= buildLocalizedUrl('/admin/cache/delete-compiled') ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($view['id']) ?>">
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
