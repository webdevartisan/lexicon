{% extends "back.lex.php" %}

{% block title %}Audit Log{% endblock %}
{% block subtitle %}Who did what, and when, across the whole site.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/audit-log';

// Color-code by verb so destructive actions stand out when scanning
$actionColor = static function (string $action): string {
    if (str_contains($action, 'delete')) {
        return 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800';
    }
    if (str_contains($action, 'create')) {
        return 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800';
    }
    if (str_contains($action, 'update') || str_contains($action, 'approve')) {
        return 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/40 dark:border-sky-800';
    }

    return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <!-- Filters -->
    <form method="GET" action="<?= e($basePath) ?>" data-table-filter class="card mb-4">
        <div class="card-body">
            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <?php
                // The select component maps a flat list to value => label; prepend the
                // "all" sentinel so it reads as a real filter option. Action and resource
                // slugs are already lowercase keys, so they are shown verbatim.
                $actionChoices = ['' => 'All actions'];
foreach ($actionOptions as $opt) {
    $actionChoices[$opt] = $opt;
}

$resourceChoices = ['' => 'All resource types'];
foreach ($resourceTypeOptions as $opt) {
    $resourceChoices[$opt] = $opt;
}
?>
                {% cmp="select" name="action" options="{$actionChoices}" selectedKey="{$actionFilter}" onchange="this.form.submit()" %}
                {% cmp="select" name="resource_type" options="{$resourceChoices}" selectedKey="{$resourceTypeFilter}" onchange="this.form.submit()" %}
                {% cmp="btn" type="submit" variant="blue" icon="filter" label="Apply" %}
                <?php /* Marked for table-sort.js: the region swap does not reach
                         into the filter form, so this is refreshed separately. */ ?>
                <span data-table-sync="filter-clear" class="contents">
                <?php if ($actionFilter !== '' || $resourceTypeFilter !== '') { ?>
                {% cmp="btn" href="{$basePath}" variant="slate" icon="x" label="Clear" %}
                <?php } ?>
                </span>
            </div>
        </div>
    </form>

    <div data-table-region>
    {% if entries|empty %}
        {% cmp="empty-state" icon="history" title="No audit entries" message="Actions like deletions, approvals, and role changes will be recorded here." %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="when" label="When" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="user" label="User" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="action" label="Action" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="resource" label="Resource" %}
                        <th class="px-3.5 py-2.5 font-semibold">Details</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="ip" label="IP" %}
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($entries as $entry): %}
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors align-top">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">
                            <?= e(local_datetime($entry['created_at'] ?? null, 'M j, Y · g:i a')) ?>
                        </td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">
                            <?= e((string) ($entry['username'] ?? 'system')) ?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $actionColor((string) $entry['action']) ?>">
                                <?= e((string) $entry['action']) ?>
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">
                            <?= e((string) $entry['resource_type']) ?><?= $entry['resource_id'] !== null ? ' #'.e((string) $entry['resource_id']) : '' ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-xs text-slate-500 dark:text-zink-300 whitespace-normal max-w-md break-words">
                            <?php
                            $details = json_decode((string) ($entry['details'] ?? ''), true);
if (is_array($details)) {
    $pairs = [];
    foreach ($details as $k => $v) {
        $pairs[] = $k.': '.(is_scalar($v) ? (string) $v : json_encode($v));
    }
    echo e(truncate(implode(' · ', $pairs), 160));
}
?>
                        </td>
                        <td class="px-3.5 py-2.5 text-xs text-slate-400 dark:text-zink-300"><?= e((string) ($entry['ip_address'] ?? '')) ?></td>
                    </tr>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" basePath="{$basePath}" itemSingular="entry" itemPlural="entries" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}
