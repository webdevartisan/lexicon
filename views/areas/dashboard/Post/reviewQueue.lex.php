{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · {{ t('navigation.reviewQueue') }}{% endblock %}
{% block subtitle %}{{ t('dashboard.reviewQueue.subtitle') }}{% endblock %}
{% block body %}
<?php
$workflowBadge = [
    'in_review' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800',
    'needs_changes' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
];
$workflowLabel = [
    'in_review' => $t('dashboard.reviewQueue.states.in_review'),
    'needs_changes' => $t('dashboard.reviewQueue.states.needs_changes'),
];
$claimLabel = $t('dashboard.reviewQueue.claim');
$releaseLabel = $t('dashboard.reviewQueue.release');
$unassignLabel = $t('dashboard.reviewQueue.unassign');
$openLabel = $t('dashboard.reviewQueue.open');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <section class="card">
        <div class="card-body">
            <header class="flex items-end justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        <i data-lucide="clipboard-check" class="size-4 text-custom-500"></i>
                        <?= e($t('navigation.reviewQueue')) ?>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                        <?= e($t('dashboard.reviewQueue.intro', ['blog' => $blog['blog_name']])) ?>
                    </p>
                </div>
                <span class="text-xs text-slate-500 dark:text-zink-400"><?= e($t('dashboard.reviewQueue.waiting', ['count' => count($posts)])) ?></span>
            </header>

            {% if posts|empty %}
            <div class="text-center py-12">
                <i data-lucide="check-circle" class="size-10 text-emerald-500 mx-auto mb-2"></i>
                <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('dashboard.reviewQueue.empty')) ?></p>
            </div>
            {% else %}
            <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                {% foreach ($posts as $p): %}
                <?php
                    $state = (string) ($p['workflow_state'] ?? 'in_review');
$assignedToMe = (int) ($p['reviewer_id'] ?? 0) === (int) ($currentUserId ?? 0);
$unassigned = empty($p['reviewer_id']);
$postId = (int) $p['id'];
$reviewHref = "/dashboard/post/{$postId}/review";
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
                            <span><?= e($t('dashboard.reviewQueue.by', ['author' => $p['author_handle']])) ?></span>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded border <?= $workflowBadge[$state] ?? '' ?>">
                                <?= e($workflowLabel[$state] ?? $state) ?>
                            </span>
                            <?php if ($unassigned) { ?>
                                <span class="text-amber-600 dark:text-amber-400"><?= e($t('dashboard.reviewQueue.unassigned')) ?></span>
                            <?php } elseif ($assignedToMe) { ?>
                                <span class="text-emerald-600 dark:text-emerald-400"><?= e($t('dashboard.reviewQueue.assignedToYou')) ?></span>
                            <?php } else { ?>
                                <span><?= e($t('dashboard.reviewQueue.reviewing', ['reviewer' => $p['reviewer_handle']])) ?></span>
                            <?php } ?>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <?php if ($unassigned && !empty($canClaim)) { ?>
                        <form method="post" action="/dashboard/posts/<?= (int) $p['id'] ?>/workflow/assign-reviewer" class="m-0">
                            {{ csrf_field() }}
                            <input type="hidden" name="reviewer_id" value="<?= (int) ($currentUserId ?? 0) ?>">
                            {% cmp="btn" type="submit" variant="sky" icon="user-plus" label="{$claimLabel}" %}
                        </form>
                        <?php } elseif ($assignedToMe) { ?>
                        <form method="post" action="/dashboard/posts/<?= (int) $p['id'] ?>/workflow/unassign-reviewer" class="m-0"
                              data-confirm="<?= e($t('dashboard.reviewQueue.releaseConfirm')) ?>">
                            {{ csrf_field() }}
                            <input type="hidden" name="reviewer_id" value="<?= (int) ($currentUserId ?? 0) ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="log-out" label="{$releaseLabel}" %}
                        </form>
                        <?php } elseif (!$unassigned && !empty($canManageReviewers)) { ?>
                        <form method="post" action="/dashboard/posts/<?= (int) $p['id'] ?>/workflow/unassign-reviewer" class="m-0"
                              data-confirm="<?= e($t('dashboard.reviewQueue.unassignConfirm', ['reviewer' => $p['reviewer_handle']])) ?>">
                            {{ csrf_field() }}
                            <input type="hidden" name="reviewer_id" value="<?= (int) ($p['reviewer_id'] ?? 0) ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="user-minus" label="{$unassignLabel}" %}
                        </form>
                        <?php } ?>
                        {% cmp="btn" href="{$reviewHref}" variant="blue" icon="eye" label="{$openLabel}" %}
                    </div>
                </li>
                {% endforeach %}
            </ul>
            {% endif %}
        </div>
    </section>

</div>
{% endblock %}
