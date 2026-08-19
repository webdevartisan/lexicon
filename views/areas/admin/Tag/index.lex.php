{% extends "back.lex.php" %}

{% block title %}Tags{% endblock %}
{% block subtitle %}Label posts with keywords readers can filter by.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/tags';
$hasFilters = $q !== '' || $used !== '' || $blogId > 0;
$emptyTitle = $hasFilters ? 'No tags match these filters' : 'No tags yet';
$emptyMessage = $hasFilters ? 'Try a different name, blog, or usage filter.' : 'Create one to start tagging posts.';

$usedChoices = ['' => 'Used or not', 'yes' => 'In use', 'no' => 'Unused'];
$blogChoices = ['' => 'All blogs'] + $blogOptions;
$selectedBlogKey = $blogId > 0 ? (string) $blogId : '';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" data-table-filter class="flex flex-wrap items-center gap-3 grow">
            {% cmp="input" type="search" name="q" value="{$q}" placeholder="Search name, slug, or blog..." %}
            {% cmp="select" name="blog_id" options="{$blogChoices}" selectedKey="{$selectedBlogKey}" onchange="this.form.submit()" %}
            {% cmp="select" name="used" options="{$usedChoices}" selectedKey="{$used}" onchange="this.form.submit()" %}
            {% cmp="btn" type="submit" variant="blue" icon="search" label="Search" %}
            <?php /* Marked for table-sort.js: the region swap does not reach
                     into the filter form, so this is refreshed separately. */ ?>
            <span data-table-sync="filter-clear" class="contents">
            <?php if ($hasFilters) { ?>
            {% cmp="btn" href="{$basePath}" variant="slate" icon="x" label="Clear" %}
            <?php } ?>
            </span>
        </form>
        <div class="shrink-0">
            {% cmp="btn" href="/admin/tags/new" variant="blue" icon="plus" label="New Tag" %}
        </div>
    </div>

    <div data-table-region>
    {% if tags|empty %}
        {% cmp="empty-state" icon="tags" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="id" label="ID" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="name" label="Name" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="blog" label="Blog" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="slug" label="Slug" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="posts" label="Posts" %}
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($tags as $tag): %}
                    <?php
                        $editUrl = '/admin/tags/'.$tag['id'].'/edit';
$deleteUrl = '/admin/tags/'.$tag['id'].'/delete';
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $tag['id']) ?></td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50"><?= e($tag['name']) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($tag['blog_name'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e($tag['slug']) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300 tabular-nums"><?= (int) ($tag['post_count'] ?? 0) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                {% cmp="icon-action" href="{$editUrl}" icon="pencil" tip="Edit" %}
                                {% cmp="icon-action" href="{$deleteUrl}" icon="trash-2" tip="Delete" danger %}
                            </div>
                        </td>
                    </tr>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="tag" itemPlural="tags" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
