{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Review queue{% endblock %}
{% block subtitle %}Posts waiting on a reviewer — yours to claim or pass along.{% endblock %}
{% block body %}
<?php
$workflowBadge = [
    'in_review'     => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800',
    'needs_changes' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
];
$workflowLabel = [
    'in_review'     => 'In review',
    'needs_changes' => 'Needs changes',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/shared"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="arrow-left" class="size-4"></i>
            <span>Back to Shared</span>
        </a>
    </div>

    <section class="card">
        <div class="card-body">
            <header class="flex items-end justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        <i data-lucide="clipboard-check" class="size-4 text-custom-500"></i>
                        Review queue
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                        Posts on <span class="font-medium"><?= e($blog['blog_name']) ?></span> awaiting reviewer action.
                    </p>
                </div>
                <span class="text-xs text-slate-500 dark:text-zink-400"><?= count($posts) ?> waiting</span>
            </header>

            {% if posts|empty %}
            <div class="text-center py-12">
                <i data-lucide="check-circle" class="size-10 text-emerald-500 mx-auto mb-2"></i>
                <p class="text-sm text-slate-500 dark:text-zink-300">Nothing waiting. The queue is empty.</p>
            </div>
            {% else %}
            <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                {% foreach ($posts as $p): %}
                <?php
                    $state = (string) ($p['workflow_state'] ?? 'in_review');
                    $assignedToMe = (int) ($p['reviewer_id'] ?? 0) === (int) ($currentUserId ?? 0);
                    $unassigned = empty($p['reviewer_id']);
                ?>
                <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center justify-center size-9 rounded-md shrink-0
                        <?= $state === 'needs_changes' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/30 dark:text-amber-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300' ?>">
                        <i data-lucide="<?= $state === 'needs_changes' ? 'pen-line' : 'eye' ?>" class="size-4"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="/dashboard/post/<?= (int) $p['id'] ?>/review" class="block font-medium text-sm text-slate-900 dark:text-zink-50 hover:text-custom-500 truncate">
                            <?= e($p['title']) ?>
                        </a>
                        <p class="text-xs text-slate-500 dark:text-zink-400 mt-0.5 flex flex-wrap items-center gap-2">
                            <span>by <?= e($p['author_username']) ?></span>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded border <?= $workflowBadge[$state] ?? '' ?>">
                                <?= e($workflowLabel[$state] ?? $state) ?>
                            </span>
                            <?php if ($unassigned) { ?>
                                <span class="text-amber-600 dark:text-amber-400">Unassigned</span>
                            <?php } elseif ($assignedToMe) { ?>
                                <span class="text-emerald-600 dark:text-emerald-400">Assigned to you</span>
                            <?php } else { ?>
                                <span>Reviewing: <?= e($p['reviewer_username'] ?? 'someone') ?></span>
                            <?php } ?>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <?php if ($unassigned && in_array($blogRole, ['reviewer', 'editor', 'owner'], true)) { ?>
                        <form method="post" action="/dashboard/posts/<?= (int) $p['id'] ?>/workflow/assign-reviewer" class="m-0">
                            {{ csrf_field() }}
                            <input type="hidden" name="reviewer_id" value="<?= (int) ($currentUserId ?? 0) ?>">
                            {% cmp="btn" type="submit" variant="sky" icon="user-plus" label="Claim" %}
                        </form>
                        <?php } elseif ($assignedToMe) { ?>
                        <form method="post" action="/dashboard/posts/<?= (int) $p['id'] ?>/workflow/unassign-reviewer" class="m-0"
                              onsubmit="return confirm('Release this post back to the queue?');">
                            {{ csrf_field() }}
                            <input type="hidden" name="reviewer_id" value="<?= (int) ($currentUserId ?? 0) ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="log-out" label="Release" %}
                        </form>
                        <?php } elseif (!$unassigned && (in_array($blogRole, ['editor', 'owner'], true) || !empty($isAdmin))) { ?>
                        <form method="post" action="/dashboard/posts/<?= (int) $p['id'] ?>/workflow/unassign-reviewer" class="m-0"
                              onsubmit="return confirm('Unassign <?= e(addslashes($p['reviewer_username'] ?? 'this reviewer')) ?>?');">
                            {{ csrf_field() }}
                            <input type="hidden" name="reviewer_id" value="<?= (int) ($p['reviewer_id'] ?? 0) ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="user-minus" label="Unassign" %}
                        </form>
                        <?php } ?>
                        {% cmp="btn" href="/dashboard/post/{$p['id']}/review" variant="blue" icon="eye" label="Open" %}
                    </div>
                </li>
                {% endforeach %}
            </ul>
            {% endif %}
        </div>
    </section>

</div>
{% endblock %}
