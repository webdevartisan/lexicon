{% extends "back.lex.php" %}

{% block title %}Blogs{% endblock %}
{% block subtitle %}All blogs on the platform, with owners and content counts.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/blogs';
$emptyTitle = $q !== '' ? 'No blogs match your search' : 'No blogs yet';
$emptyMessage = $q !== '' ? 'Try a different name, slug, or owner.' : 'Create the first blog to get the site going.';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" class="flex flex-col sm:flex-row gap-3 grow max-w-xl">
            {% cmp="input" type="text" name="q" value="{$q}" placeholder="Search name, slug, or owner..." %}
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                {% cache 'lucide:search' ttl=3600 %}<i data-lucide="search" class="size-4"></i>{% endcache %} Search
            </button>
        </form>
        <div class="shrink-0">
            {% cmp="btn" href="/admin/blogs/new" variant="blue" icon="plus" label="New Blog" %}
        </div>
    </div>

    {% if blogs|empty %}
        {% cmp="empty-state" icon="book-open" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="px-3.5 py-2.5 font-semibold">ID</th>
                        <th class="px-3.5 py-2.5 font-semibold">Name</th>
                        <th class="px-3.5 py-2.5 font-semibold">Owner</th>
                        <th class="px-3.5 py-2.5 font-semibold">Posts</th>
                        <th class="px-3.5 py-2.5 font-semibold">Team</th>
                        <th class="px-3.5 py-2.5 font-semibold">Active</th>
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($blogs as $blog): %}
                    <?php
                        $activeStatus = !empty($blog['is_active']) ? 'active' : 'inactive';
$activeLabel = !empty($blog['is_active']) ? 'Active' : 'Inactive';
$showUrl = '/admin/blogs/'.$blog['id'].'/show';
$editUrl = '/admin/blogs/'.$blog['id'].'/edit';
$deleteUrl = '/admin/blogs/'.$blog['id'].'/delete';
$featuredOnExplore = (int) ($blog['is_featured'] ?? 0) === 1;
$featureTip = $featuredOnExplore ? 'Remove from explore featured' : 'Feature on explore page';
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $blog['id']) ?></td>
                        <td class="px-3.5 py-2.5">
                            <span class="font-medium text-slate-900 dark:text-zink-50"><?= e($blog['blog_name']) ?></span>
                            <span class="block text-xs text-slate-400 dark:text-zink-300">/<?= e((string) ($blog['blog_slug'] ?? '')) ?></span>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($blog['owner_name'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) ($blog['post_count'] ?? 0) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) ($blog['author_count'] ?? 0) ?></td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$activeStatus}" label="{$activeLabel}" %}
                        </td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <form method="POST" action="/admin/blogs/<?= (int) $blog['id'] ?>/feature-explore">
                                    {{ csrf_field() }}
                                    <button type="submit"
                                            data-tooltip data-tooltip-content="<?= e($featureTip) ?>" data-tooltip-placement="top"
                                            aria-label="<?= e($featureTip) ?>"
                                            class="p-2 rounded-md transition-colors <?= $featuredOnExplore ? 'text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30' : 'text-slate-500 hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10' ?>">
                                        
                                            <?php if ($featuredOnExplore) { ?>
                                            {% cache 'lucide:star-fill' ttl=3600 %}<i data-lucide="star" class="size-4 fill-current"></i>{% endcache %}
                                            <?php } else { ?>
                                            {% cache 'lucide:star' ttl=3600 %}<i data-lucide="star" class="size-4"></i>{% endcache %}
                                            <?php } ?>
                                    </button>
                                </form>
                                {% cmp="icon-action" href="{$showUrl}" icon="eye" tip="View" %}
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
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="blog" itemPlural="blogs" %}
    </div>
    {% endif %}
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
