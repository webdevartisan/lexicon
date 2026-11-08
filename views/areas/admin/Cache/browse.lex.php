{% extends "back.lex.php" %}

{% block title %}Browse cache{% endblock %}

{% block subtitle %}Look at stored entries and remove single ones{% endblock %}

{% block body %}
<?php
$entries = $listing['entries'];
$filterQuery = array_filter(['layer' => $layer, 'q' => $search]);
$browseUrl = buildLocalizedUrl('/admin/cache/browse');
$returnQuery = '?'.http_build_query($filterQuery + ['page' => $listing['page']]);
$now = time();
$bulkActions = [
    ['action' => 'delete', 'label' => 'Delete', 'icon' => 'trash-2', 'hover' => 'hover:bg-red-600/30', 'danger' => true, 'confirm' => 'Delete {n}?'],
];
$bulkHiddenFields = ['return' => $returnQuery];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <h6 class="text-15"><?= number_format($listing['total']) ?> entries</h6>
                <a href="<?= buildLocalizedUrl('/admin/cache') ?>" class="text-sm text-custom-500 hover:underline">Back to cache management</a>
            </div>

            <form action="<?= $browseUrl ?>" method="GET" class="flex flex-wrap gap-3 mb-5">
                <select name="layer" class="form-select border-slate-200 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100">
                    <option value="">All layers</option>
                    <option value="pages" <?= $layer === 'pages' ? 'selected' : '' ?>>Pages</option>
                    <option value="fragments" <?= $layer === 'fragments' ? 'selected' : '' ?>>Fragments</option>
                </select>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search keys, e.g. sidebar or /discover"
                       class="form-input grow border-slate-200 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100">
                <button type="submit" class="btn text-white bg-custom-500 border-custom-500 hover:bg-custom-600">Filter</button>
            </form>

            <?php if ($entries === []) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300">Nothing stored matches this filter.</p>
            <?php } else { ?>
            {% cmp="bulk-actions-bar" actionUrl="/admin/cache/delete-selected" actions="{$bulkActions}" headerSelectAll="1" hiddenFields="{$bulkHiddenFields}" %}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 dark:text-zink-300 border-b border-slate-200 dark:border-zink-500">
                            <th class="py-2 pr-3 w-8">
                                <input type="checkbox" id="select-all" aria-label="Select all on this page"
                                       class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500">
                            </th>
                            <th class="py-2 pr-4 font-medium">Key</th>
                            <th class="py-2 pr-4 font-medium">Layer</th>
                            <th class="py-2 pr-4 font-medium">Size</th>
                            <th class="py-2 pr-4 font-medium">Expires</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry) { ?>
                        <tr data-bulk-row class="border-b border-slate-100 dark:border-zink-600">
                            <td class="py-2 pr-3">
                                <input type="checkbox" name="keys[]" value="<?= e($entry['key']) ?>" form="bulk-form" aria-label="Select entry"
                                       class="bulk-checkbox form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500">
                            </td>
                            <td class="py-2 pr-4"><code class="text-xs font-mono break-all"><?= e($entry['key']) ?></code></td>
                            <td class="py-2 pr-4"><?= $entry['layer'] === 'pages' ? 'Page' : 'Fragment' ?></td>
                            <td class="py-2 pr-4 whitespace-nowrap"><?= number_format($entry['size_bytes'] / 1024, 1) ?> KB</td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <?= $entry['expires_at'] <= $now ? 'expired' : e(relative_time(gmdate('Y-m-d H:i:s', $entry['expires_at']))) ?>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a href="<?= buildLocalizedUrl('/admin/cache/preview') ?>?key=<?= e(rawurlencode($entry['key'])) ?>" class="mr-3 text-xs font-medium text-custom-500 hover:underline">Preview</a>
                                <form class="inline-block" action="<?= buildLocalizedUrl('/admin/cache/delete-entry') ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="key" value="<?= e($entry['key']) ?>">
                                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php if ($listing['pages'] > 1) { ?>
            <div class="flex items-center justify-between mt-5 text-sm">
                <?php if ($listing['page'] > 1) { ?>
                    <a href="<?= e($browseUrl.'?'.http_build_query($filterQuery + ['page' => $listing['page'] - 1])) ?>" class="text-custom-500 hover:underline">Previous</a>
                <?php } else { ?>
                    <span></span>
                <?php } ?>
                <span class="text-slate-500 dark:text-zink-300">Page <?= $listing['page'] ?> of <?= $listing['pages'] ?></span>
                <?php if ($listing['page'] < $listing['pages']) { ?>
                    <a href="<?= e($browseUrl.'?'.http_build_query($filterQuery + ['page' => $listing['page'] + 1])) ?>" class="text-custom-500 hover:underline">Next</a>
                <?php } else { ?>
                    <span></span>
                <?php } ?>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
