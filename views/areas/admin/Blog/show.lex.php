{% extends "back.lex.php" %}

{% block title %}View Blog{% endblock %}
{% block subtitle %}Blog details and its posts.{% endblock %}

{% block body %}
<?php
$statusBadge = [
    'published' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="card mb-5">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-zink-50"><?= e($blog['blog_name']) ?></h2>
                    <?php if (!empty($blog['is_active'])) { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800">Active</span>
                    <?php } else { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500">Inactive</span>
                    <?php } ?>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/blog/<?= e((string) ($blog['blog_slug'] ?? '')) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                        <i data-lucide="external-link" class="size-3.5"></i> Visit
                    </a>
                    <a href="/admin/blogs/<?= e((string) $blog['id']) ?>/edit" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                        <i data-lucide="pencil" class="size-3.5"></i> Edit
                    </a>
                    <a href="/admin/blogs/<?= e((string) $blog['id']) ?>/delete" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 dark:bg-zink-700 dark:border-red-500/40 dark:hover:bg-red-900/20 transition-colors">
                        <i data-lucide="trash-2" class="size-3.5"></i> Delete
                    </a>
                    <a href="/admin/blogs" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                        Back to list
                    </a>
                </div>
            </div>

            <?php if (!empty($blog['description'])) { ?>
            <p class="text-sm text-slate-600 dark:text-zink-200"><?= e($blog['description']) ?></p>
            <?php } ?>
        </div>
    </div>

    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-3">Posts in this blog</h3>

    {% if posts|empty %}
    <div class="card">
        <div class="card-body text-center py-10">
            <i data-lucide="files" class="size-10 text-slate-400 mx-auto mb-2"></i>
            <p class="text-sm text-slate-500 dark:text-zink-300">No posts in this blog yet.</p>
        </div>
    </div>
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="px-3.5 py-2.5 font-semibold">ID</th>
                        <th class="px-3.5 py-2.5 font-semibold">Title</th>
                        <th class="px-3.5 py-2.5 font-semibold">Status</th>
                        <th class="px-3.5 py-2.5 font-semibold">Updated</th>
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($posts as $post): %}
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $post['id']) ?></td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50 max-w-xs truncate"><?= e(truncate((string) $post['title'], 60)) ?></td>
                        <td class="px-3.5 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $statusBadge[$post['status']] ?? $statusBadge['draft'] ?>">
                                <?= e(ucfirst((string) $post['status'])) ?>
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(date('M j, Y', strtotime((string) $post['updated_at']))) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="/admin/posts/<?= e((string) $post['id']) ?>/show" title="View"
                                   class="p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-custom-50 dark:hover:bg-custom-500/10 transition-colors">
                                    <i data-lucide="eye" class="size-4"></i>
                                </a>
                                <a href="/admin/posts/<?= e((string) $post['id']) ?>/edit" title="Edit"
                                   class="p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-custom-50 dark:hover:bg-custom-500/10 transition-colors">
                                    <i data-lucide="pencil" class="size-4"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>
    {% endif %}
</div>
{% endblock %}
