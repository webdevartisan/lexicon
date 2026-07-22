{% extends "back.lex.php" %}

{% block title %}Posts{% endblock %}
{% block subtitle %}Every post on the site, across all blogs.{% endblock %}

{% block body %}
<?php
$statusOptions = ['' => 'All statuses', 'published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived'];
$basePath = '/admin/posts';
$emptyTitle = ($q !== '' || $status !== '') ? 'No posts match your filters' : 'No posts yet';
$emptyMessage = ($q !== '' || $status !== '') ? 'Try a different search or clear the status filter.' : 'Posts from every blog will appear here.';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" class="flex flex-col sm:flex-row gap-3 grow max-w-2xl">
            {% cmp="input" type="text" name="q" value="{$q}" placeholder="Search title or content..." %}
            {% cmp="select" name="status" options="{$statusOptions}" selectedKey="{$status}" onchange="this.form.submit()" %}
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                <i data-lucide="search" class="size-4"></i> Search
            </button>
        </form>
        <div class="shrink-0">
            {% cmp="btn" href="/admin/posts/new" variant="blue" icon="plus" label="New Post" %}
        </div>
    </div>

    {% if posts|empty %}
        {% cmp="empty-state" icon="files" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="px-3.5 py-2.5 font-semibold">ID</th>
                        <th class="px-3.5 py-2.5 font-semibold">Title</th>
                        <th class="px-3.5 py-2.5 font-semibold">Blog</th>
                        <th class="px-3.5 py-2.5 font-semibold">Author</th>
                        <th class="px-3.5 py-2.5 font-semibold">Status</th>
                        <th class="px-3.5 py-2.5 font-semibold">Updated</th>
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($posts as $post): %}
                    <?php
                        $postStatus = (string) $post['status'];
$showUrl = '/admin/posts/'.$post['id'].'/show';
$editUrl = '/admin/posts/'.$post['id'].'/edit';
$deleteUrl = '/admin/posts/'.$post['id'].'/delete';
$featuredOnHome = (int) ($post['featured_on_home'] ?? 0) === 1;
$featureTip = $featuredOnHome ? 'Remove from front page' : 'Feature on front page';
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $post['id']) ?></td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50 max-w-xs truncate">
                            <?= e(truncate((string) $post['title'], 60)) ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($post['blog_name'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($post['author_username'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$postStatus}" %}
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(local_datetime($post['updated_at'] ?? null, 'M j, Y')) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <form method="POST" action="/admin/posts/<?= (int) $post['id'] ?>/feature-home">
                                    {{ csrf_field() }}
                                    <button type="submit"
                                            data-tooltip data-tooltip-content="<?= e($featureTip) ?>" data-tooltip-placement="top"
                                            aria-label="<?= e($featureTip) ?>"
                                            class="p-2 rounded-md transition-colors <?= $featuredOnHome ? 'text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30' : 'text-slate-500 hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10' ?>">
                                            <?php if ($featuredOnHome) { ?>
                                            {% cache 'lucide:star-fill' ttl=31536000 %}<i data-lucide="star" class="size-4 fill-current"></i>{% endcache %}
                                            <?php } else { ?>
                                            {% cache 'lucide:star' ttl=31536000 %}<i data-lucide="star" class="size-4"></i>{% endcache %}
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
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="post" itemPlural="posts" %}
    </div>
    {% endif %}
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
