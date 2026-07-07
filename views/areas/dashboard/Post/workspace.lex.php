{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · My work{% endblock %}
{% block subtitle %}Your posts on this blog: drafts, submissions, and what came back.{% endblock %}
{% block body %}
<?php
$workflowOn = !empty($workflowEnabled);
$blogId = (int) $blog['id'];
$basePath = "/dashboard/blog/{$blogId}/workspace";

$tabs = [
    ['key' => '',      'label' => 'All',    'count' => $counts['all']],
    ['key' => 'draft', 'label' => 'Drafts', 'count' => $counts['draft']],
];
if ($workflowOn) {
    $tabs[] = ['key' => 'pending',       'label' => 'In Review',     'count' => $counts['pending']];
    $tabs[] = ['key' => 'needs_changes', 'label' => 'Needs Changes', 'count' => $counts['needs_changes']];
}
$tabs[] = ['key' => 'published', 'label' => 'Published', 'count' => $counts['published']];

$statusBadge = [
    'published' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'draft' => 'bg-slate-100 text-slate-700 dark:bg-zink-600 dark:text-zink-200',
    'pending' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    'archived' => 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-300',
];
$workflowBadge = [
    'in_review' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800',
    'needs_changes' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
];
$workflowLabel = [
    'in_review' => 'In review',
    'needs_changes' => 'Needs changes',
    'approved' => 'Approved',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/shared"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="arrow-left" class="size-4"></i>
            <span>Back to Shared</span>
        </a>
        <?php $newPostHref = "/dashboard/post/new?blog_id={$blogId}"; ?>
        {% cmp="btn" href="{$newPostHref}" variant="blue" icon="pen" label="Write a post" %}
    </div>

    <section class="card">
        <div class="card-body">
            <header class="flex items-end justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        <i data-lucide="notebook-pen" class="size-4 text-custom-500"></i>
                        My work on <?= e($blog['blog_name']) ?>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                        <?php if ($workflowOn) { ?>
                        Save a draft, switch it to Pending when it's ready for review, and watch for feedback here.
                        <?php } else { ?>
                        Save drafts here. The blog's editor or owner publishes them.
                        <?php } ?>
                    </p>
                </div>
            </header>

            <div class="flex flex-wrap gap-2 mb-4">
                <?php foreach ($tabs as $tab) {
                    $isActive = (string) $status === (string) $tab['key'];
                    $href = $basePath.($tab['key'] !== '' ? '?status='.$tab['key'] : '');
                    ?>
                <a href="<?= e($href) ?>"
                   class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border rounded-full transition-colors
                          <?= $isActive
                                    ? 'bg-custom-500 text-white border-custom-500'
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600' ?>">
                    <span><?= e((string) $tab['label']) ?></span>
                    <span class="inline-flex items-center justify-center text-[10px] font-semibold rounded-full px-1.5 py-0.5
                          <?= $isActive ? 'bg-white/20' : 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-300' ?>">
                        <?= (int) $tab['count'] ?>
                    </span>
                </a>
                <?php } ?>
            </div>

            <?php if (empty($posts)) { ?>
            <div class="text-center py-12">
                <i data-lucide="feather" class="size-10 text-slate-300 dark:text-zink-500 mx-auto mb-2"></i>
                <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">Nothing here yet. Start writing.</p>
                {% cmp="btn" href="{$newPostHref}" variant="blue" icon="pen" label="Write a post" %}
            </div>
            <?php } else { ?>
            <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                <?php foreach ($posts as $post) {
                    $pid = (int) $post['id'];
                    $editHref = "/dashboard/post/{$pid}/edit";
                    $postStatus = (string) ($post['status'] ?? 'draft');
                    $wfState = (string) ($post['workflow_state'] ?? '');
                    $showWf = $workflowOn && in_array($wfState, ['in_review', 'needs_changes', 'approved'], true);
                    $inReviewLock = $wfState === 'in_review';
                    ?>
                <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <a href="<?= e($editHref) ?>" class="block text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 truncate">
                            <?= e($post['title']) ?>
                        </a>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-400 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded capitalize <?= $statusBadge[$postStatus] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= e($postStatus) ?>
                            </span>
                            <?php if ($showWf) { ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded border <?= $workflowBadge[$wfState] ?? '' ?>">
                                <?= e($workflowLabel[$wfState] ?? $wfState) ?>
                            </span>
                            <?php } ?>
                            <?php if ($inReviewLock) { ?>
                            <span class="text-slate-400 dark:text-zink-500">Locked while under review</span>
                            <?php } ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <?php if (!$inReviewLock) { ?>
                        {% cmp="btn" href="{$editHref}" variant="slate" icon="pen" label="Edit" %}
                        <?php } ?>
                    </div>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>

            <?php if (!empty($pagination) && $pagination['total_pages'] > 1) { ?>
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100 dark:border-zink-600 text-xs text-slate-500 dark:text-zink-400">
                <span>Page <?= (int) $pagination['current_page'] ?> of <?= (int) $pagination['total_pages'] ?></span>
                <div class="flex gap-2">
                    <?php $qs = $status !== '' ? '&status='.e($status) : ''; ?>
                    <?php if ($pagination['has_previous']) { ?>
                    <a href="<?= e($basePath.'?page='.($pagination['current_page'] - 1)).$qs ?>" class="px-3 py-1.5 rounded border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600">Previous</a>
                    <?php } ?>
                    <?php if ($pagination['has_next']) { ?>
                    <a href="<?= e($basePath.'?page='.($pagination['current_page'] + 1)).$qs ?>" class="px-3 py-1.5 rounded border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600">Next</a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </section>

</div>
{% endblock %}
