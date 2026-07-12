{% extends "back.lex.php" %}

{% block title %}Categories{% endblock %}
{% block subtitle %}Organize posts into browsable topics.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/categories';
$emptyTitle = $q !== '' ? 'No categories match your search' : 'No categories yet';
$emptyMessage = $q !== '' ? 'Try a different name, slug, or blog.' : 'Create one to start organizing posts.';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" class="flex flex-col sm:flex-row gap-3 grow max-w-xl">
            {% cmp="input" type="text" name="q" value="{$q}" placeholder="Search name, slug, or blog..." %}
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                {% cache 'lucide:search' ttl=31536000 %}<i data-lucide="search" class="size-4"></i>{% endcache %} Search
            </button>
        </form>
        <div class="shrink-0">
            {% cmp="btn" href="/admin/categories/new" variant="blue" icon="plus" label="New Category" %}
        </div>
    </div>

    {% if categories|empty %}
        {% cmp="empty-state" icon="folder-tree" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="px-3.5 py-2.5 font-semibold">ID</th>
                        <th class="px-3.5 py-2.5 font-semibold">Name</th>
                        <th class="px-3.5 py-2.5 font-semibold">Blog</th>
                        <th class="px-3.5 py-2.5 font-semibold">Slug</th>
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
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
