{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · All posts{% endblock %}
{% block subtitle %}Everything written on this blog, regardless of author.{% endblock %}
{% block body %}
<?php
$showReviewPills = !empty($workflowEnabled);
$blogId = (int) $blog['id'];

$tabs = [
    ['key' => '',          'label' => 'All',        'count' => $counts['all']],
    ['key' => 'published', 'label' => 'Published',  'count' => $counts['published']],
    ['key' => 'draft',     'label' => 'Drafts',     'count' => $counts['draft']],
];

// Only worth a tab once something is actually waiting to go out.
if (!empty($counts['scheduled'])) {
    $tabs[] = ['key' => 'scheduled', 'label' => 'Scheduled', 'count' => $counts['scheduled']];
}

if ($showReviewPills) {
    $tabs[] = ['key' => 'pending',       'label' => 'In Review',     'count' => $counts['pending']];
    $tabs[] = ['key' => 'needs_changes', 'label' => 'Needs Changes', 'count' => $counts['needs_changes'] ?? 0];
}

$tabs[] = ['key' => 'archived', 'label' => 'Archived', 'count' => $counts['archived']];

$makeQuery = static function (array $overrides = []) use ($q, $status, $sort): string {
    $params = [
        'q' => $overrides['q'] ?? $q,
        'status' => array_key_exists('status', $overrides) ? $overrides['status'] : $status,
        'sort' => array_key_exists('sort', $overrides) ? $overrides['sort'] : $sort,
        'page' => $overrides['page'] ?? null,
    ];

    if (($params['sort'] ?? 'newest') === 'newest') {
        unset($params['sort']);
    }

    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return $params ? '?'.http_build_query($params) : '';
};

$basePath = "/dashboard/blog/{$blogId}/posts";

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
$statusBadge = [
    'published' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'draft' => 'bg-slate-100 text-slate-700 dark:bg-zink-600 dark:text-zink-200',
    'pending' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    'scheduled' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    'archived' => 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-300',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/blog/<?= $blogId ?>/show"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="arrow-left" class="size-4"></i>
            <span>Back to overview</span>
        </a>
        <?php $newPostHref = "/dashboard/post/new?blog_id={$blogId}"; ?>
        {% cmp="btn" href="{$newPostHref}" variant="blue" icon="plus" label="New post" %}
    </div>

    <form method="GET" action="<?= e($basePath) ?>" class="card mb-4">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-6">
                    {% cmp="input" type="text" label="Search" name="q" value="{$q}" placeholder="Search by title or content..." %}
                </div>
                <div class="md:col-span-3">
                    <?php $sortOptions = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'title_asc' => 'Title A → Z', 'title_desc' => 'Title Z → A']; ?>
                    {% cmp="select" label="Sort" name="sort" options="{$sortOptions}" selectedKey="{$sort}" %}
                </div>
                <div class="md:col-span-3">
                    {% cmp="btn" type="submit" variant="blue" icon="filter" label="Apply" addClass="w-full" %}
                </div>
            </div>
        </div>
    </form>

    <div class="flex flex-wrap gap-2 mb-4">
        <?php foreach ($tabs as $tab) {
            $isActive = (string) $status === (string) $tab['key'];
            $href = $basePath.$makeQuery(['status' => $tab['key'], 'page' => null]);
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
    <div class="card">
        <div class="card-body text-center py-16 px-6">
            <i data-lucide="feather" class="size-10 text-slate-300 dark:text-zink-500 mx-auto mb-2"></i>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">Nothing here yet.</p>
            <?php $emptyNewHref = "/dashboard/post/new?blog_id={$blogId}"; ?>
            {% cmp="btn" href="{$emptyNewHref}" variant="blue" icon="plus" label="Write the first post" %}
        </div>
    </div>
    <?php } else { ?>
    <div class="card">
        <div class="card-body p-0">
            <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                <?php foreach ($posts as $post) {
                    $pid = (int) $post['id'];
                    $editHref = "/dashboard/post/{$pid}/edit";
                    $reviewHref = "/dashboard/post/{$pid}/review";
                    $postStatus = (string) ($post['status'] ?? 'draft');
                    $wfState = (string) ($post['workflow_state'] ?? '');
                    $showWf = $showReviewPills && in_array($wfState, ['in_review', 'needs_changes', 'approved'], true);
                    ?>
                <li class="px-4 py-3 flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <a href="<?= e($editHref) ?>" class="block text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 truncate">
                            <?= e($post['title']) ?>
                        </a>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-400 flex flex-wrap items-center gap-2">
                            <span>by <?= e($post['author_username'] ?? 'unknown') ?></span>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded capitalize <?= $statusBadge[$postStatus] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= e($postStatus) ?>
                            </span>
                            <?php if ($showWf) { ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded border <?= $workflowBadge[$wfState] ?? '' ?>">
                                <?= e($workflowLabel[$wfState] ?? $wfState) ?>
                            </span>
                            <?php } ?>
                            <?php if (!empty($post['comment_count'])) { ?>
                            <span><?= (int) $post['comment_count'] ?> comment<?= (int) $post['comment_count'] === 1 ? '' : 's' ?></span>
                            <?php } ?>
                            <?php if ((int) ($post['reports_count'] ?? 0) > 0) { ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                Reported &times;<?= (int) $post['reports_count'] ?>
                            </span>
                            <?php } ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <?php if ($wfState === 'in_review' || $wfState === 'needs_changes') { ?>
                        {% cmp="btn" href="{$reviewHref}" variant="sky" icon="clipboard-check" label="Review" %}
                        <?php } ?>
                        {% cmp="btn" href="{$editHref}" variant="slate" icon="pen" label="Edit" %}
                    </div>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <?php if (!empty($pagination) && $pagination['total_pages'] > 1) { ?>
    <div class="flex items-center justify-between mt-4 text-xs text-slate-500 dark:text-zink-400">
        <span>
            Page <?= (int) $pagination['current_page'] ?> of <?= (int) $pagination['total_pages'] ?>
            · <?= (int) $pagination['total_records'] ?> post<?= (int) $pagination['total_records'] === 1 ? '' : 's' ?>
        </span>
        <div class="flex gap-2">
            <?php if ($pagination['has_previous']) { ?>
            <a href="<?= e($basePath.$makeQuery(['page' => $pagination['current_page'] - 1])) ?>" class="px-3 py-1.5 rounded border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600">Previous</a>
            <?php } ?>
            <?php if ($pagination['has_next']) { ?>
            <a href="<?= e($basePath.$makeQuery(['page' => $pagination['current_page'] + 1])) ?>" class="px-3 py-1.5 rounded border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600">Next</a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
    <?php } ?>

</div>
{% endblock %}
