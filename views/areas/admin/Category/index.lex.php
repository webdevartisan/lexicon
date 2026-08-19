{% extends "back.lex.php" %}

{% block title %}Categories{% endblock %}
{% block subtitle %}Organize posts into browsable topics.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/categories';
$hasFilters = $q !== '' || $used !== '' || $blogId > 0;
$emptyTitle = $hasFilters ? 'No categories match these filters' : 'No categories yet';
$emptyMessage = $hasFilters ? 'Try a different name, blog, or usage filter.' : 'Create one to start organizing posts.';

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
            {% cmp="btn" href="/admin/categories/new" variant="blue" icon="plus" label="New Category" %}
        </div>
    </div>

    <div data-table-region>
    {% if categories|empty %}
        {% cmp="empty-state" icon="folder-tree" title="{$emptyTitle}" message="{$emptyMessage}" %}
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
                    {% foreach ($categories as $category): %}
                    <?php
                        $editUrl = '/admin/categories/'.$category['id'].'/edit';
$deleteUrl = '/admin/categories/'.$category['id'].'/delete';
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $category['id']) ?></td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50"><?= e($category['name']) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($category['blog_name'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e($category['slug']) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300 tabular-nums"><?= (int) ($category['post_count'] ?? 0) ?></td>
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
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="category" itemPlural="categories" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
