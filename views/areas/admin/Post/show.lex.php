{% extends "back.lex.php" %}

{% block title %}View Post{% endblock %}
{% block subtitle %}Read-only preview of the stored post.{% endblock %}

{% block body %}
<?php
$statusBadge = [
    'published' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-4xl">
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-zink-50"><?= e($post['title']) ?></h2>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $statusBadge[$post['status']] ?? $statusBadge['draft'] ?>">
                        <?= e(ucfirst((string) $post['status'])) ?>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/admin/posts/<?= e((string) $post['id']) ?>/edit" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                        <i data-lucide="pencil" class="size-3.5"></i> Edit
                    </a>
                    <a href="/admin/posts/<?= e((string) $post['id']) ?>/delete" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 dark:bg-zink-700 dark:border-red-500/40 dark:hover:bg-red-900/20 transition-colors">
                        <i data-lucide="trash-2" class="size-3.5"></i> Delete
                    </a>
                    <a href="/admin/posts" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                        Back to list
                    </a>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Slug</dt>
                    <dd class="text-slate-900 dark:text-zink-50"><?= e($post['slug']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Created</dt>
                    <dd class="text-slate-900 dark:text-zink-50"><?= e(local_datetime($post['created_at'] ?? null, 'M j, Y · g:i a')) ?></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Updated</dt>
                    <dd class="text-slate-900 dark:text-zink-50"><?= e(local_datetime($post['updated_at'] ?? null, 'M j, Y · g:i a')) ?></dd>
                </div>
            </dl>

            <?php if (!empty($post['excerpt'])) { ?>
            <p class="text-sm italic text-slate-500 dark:text-zink-300 border-l-2 border-custom-500 pl-3 mb-4"><?= e($post['excerpt']) ?></p>
            <?php } ?>

            <div class="text-sm text-slate-700 dark:text-zink-100 whitespace-pre-line break-words">
                <?= e($post['content']) ?>
            </div>
        </div>
    </div>
</div>
{% endblock %}
