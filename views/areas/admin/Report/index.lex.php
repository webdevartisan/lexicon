{% extends "back.lex.php" %}

{% block title %}Moderation{% endblock %}
{% block subtitle %}Reported posts and comments, one case per item, most urgent first.{% endblock %}

{% block body %}
<?php
$tabLabels = [
    'active' => 'Needs attention',
    'open' => 'Open',
    'in_review' => 'In review',
    'escalated' => 'Escalated',
    'resolved' => 'Resolved',
    'all' => 'All',
];
$tabCounts = $counts + [
    'active' => $counts['open'] + $counts['in_review'] + $counts['escalated'],
    'all' => array_sum($counts),
];

$categoryLabels = array_column($categories, 'label', 'slug');
$categoryChoices = ['' => 'Any reason'] + $categoryLabels;
$typeChoices = ['' => 'Posts and comments', 'post' => 'Posts', 'comment' => 'Comments'];

$hasFilters = $filters['type'] !== '' || $filters['category'] !== '' || $filters['from'] !== ''
    || $filters['to'] !== '' || $filters['author'] !== '' || $filters['reporter'] !== '';

$tabHref = static function (string $tab) use ($basePath, $filters): string {
    $params = array_filter(['status' => $tab] + $filters, static fn ($v) => $v !== '');

    return $basePath.'?'.http_build_query($params);
};

$emptyTitle = $hasFilters ? 'No cases match these filters' : 'Nothing to review';
$emptyMessage = $hasFilters ? 'Try a wider date range or a different reason.' : 'New reports from readers will show up here.';
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700';
$labelClass = 'inline-block mb-1 text-xs font-medium text-slate-500 dark:text-zink-300';
$muted = 'text-xs text-slate-400 dark:text-zink-400';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if ($unknownHandles !== []) { ?>
    <div class="mb-4 p-3 rounded-lg border border-yellow-200 bg-yellow-50 text-sm text-slate-700 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-zink-200" role="status">
        No account is tagged <?= e(implode(' or ', $unknownHandles)) ?>, so nothing matches.
    </div>
    <?php } ?>

    <?php if ($overLimit['total'] > 0) { ?>
    <div class="mb-4 p-4 rounded-lg border border-amber-200 bg-amber-50 text-sm text-slate-700 dark:bg-amber-900/20 dark:border-amber-800 dark:text-zink-200" role="status">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-triangle" class="size-5 mt-0.5 shrink-0 text-amber-600 dark:text-amber-400"></i>
            <div class="grow min-w-0">
                <p class="font-medium text-slate-900 dark:text-zink-50">
                    <?= $overLimit['total'] ?> <?= $overLimit['total'] === 1 ? 'person keeps' : 'people keep' ?> filing reports that turn out to be unfounded
                </p>
                <p class="mt-1">
                    Their reports no longer count toward the rules. To clear this notice, open each person below and pause their reports.
                    They drop off this list once paused, or when <?= (int) $unfoundedWindow ?> days pass without another unfounded report.
                </p>
                <ul class="mt-3 flex flex-col gap-2">
                    <?php foreach ($overLimit['reporters'] as $reporter) { ?>
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-medium">@<?= e((string) $reporter['handle']) ?></span>
                        <span class="text-slate-500 dark:text-zink-300"><?= (int) $reporter['unfounded'] ?> unfounded, <?= $reporter['warned_at'] === null ? 'not warned yet' : 'warned '.e(local_datetime((string) $reporter['warned_at'], 'M j')) ?></span>
                        <a href="<?= e(lurl('/admin/reports/reporters/'.((int) $reporter['id']))) ?>" class="font-medium text-custom-500 hover:underline">Warn or pause</a>
                    </li>
                    <?php } ?>
                    <?php if ($overLimit['total'] > count($overLimit['reporters'])) { ?>
                    <li class="text-slate-500 dark:text-zink-300">and <?= $overLimit['total'] - count($overLimit['reporters']) ?> more</li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php
    $activeFilters = count(array_filter([$filters['type'], $filters['category'], $filters['from'], $filters['to'], $filters['author'], $filters['reporter']], static fn ($v) => $v !== ''));
    $clearHref = $basePath.'?status='.rawurlencode($filters['status']);
    ?>
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <nav class="flex flex-wrap gap-2" aria-label="Case status">
            <?php foreach ($tabs as $tab) {
                $isActive = $filters['status'] === $tab;
                $classes = 'inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 '
                    .($isActive
                        ? 'bg-custom-500 border-custom-500 text-white'
                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200'); ?>
            <a href="<?= e($tabHref($tab)) ?>" class="<?= $classes ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= e($tabLabels[$tab]) ?>
                <span class="px-1.5 rounded-full <?= $isActive ? 'bg-white/20' : 'bg-slate-100 dark:bg-zink-600' ?>"><?= (int) ($tabCounts[$tab] ?? 0) ?></span>
            </a>
            <?php } ?>
        </nav>

        <div class="flex items-center gap-2">
            <?php if ($activeFilters > 0) { ?>
            <a href="<?= e($clearHref) ?>" class="text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300">Clear filters</a>
            <?php } ?>
            <details class="relative" data-popover>
                <summary class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border cursor-pointer select-none list-none bg-white border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 dark:hover:bg-zink-600">
                    <i data-lucide="sliders-horizontal" class="size-4"></i> Filters
                    <?php if ($activeFilters > 0) { ?><span class="pill bg-slate-900 text-white dark:bg-zink-100 dark:text-zink-800"><?= $activeFilters ?></span><?php } ?>
                </summary>
                <form method="GET" action="<?= e($basePath) ?>" class="absolute z-20 mt-2 card shadow-lg ltr:right-0 rtl:left-0 w-[min(34rem,calc(100vw-2rem))]" aria-label="Filter cases">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                        <div>
                            <label for="f-type" class="<?= $labelClass ?>">Type</label>
                            <select id="f-type" name="type" class="form-select <?= $fieldClass ?> w-full">
                                <?php foreach ($typeChoices as $value => $label) { ?>
                                <option value="<?= e($value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label for="f-category" class="<?= $labelClass ?>">Reason</label>
                            <select id="f-category" name="category" class="form-select <?= $fieldClass ?> w-full">
                                <?php foreach ($categoryChoices as $value => $label) { ?>
                                <option value="<?= e((string) $value) ?>" <?= $filters['category'] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label for="f-from" class="<?= $labelClass ?>">Reported from</label>
                            <input type="date" id="f-from" name="from" value="<?= e($filters['from']) ?>" class="<?= $fieldClass ?> w-full">
                        </div>
                        <div>
                            <label for="f-to" class="<?= $labelClass ?>">Reported to</label>
                            <input type="date" id="f-to" name="to" value="<?= e($filters['to']) ?>" class="<?= $fieldClass ?> w-full">
                        </div>
                        <div>
                            <label for="f-author" class="<?= $labelClass ?>">Author tag</label>
                            <input type="text" id="f-author" name="author" value="<?= e($filters['author']) ?>" placeholder="@tag" class="<?= $fieldClass ?> w-full">
                        </div>
                        <div>
                            <label for="f-reporter" class="<?= $labelClass ?>">Reporter tag</label>
                            <input type="text" id="f-reporter" name="reporter" value="<?= e($filters['reporter']) ?>" placeholder="@tag" class="<?= $fieldClass ?> w-full">
                        </div>
                        <div class="flex gap-2 sm:col-span-2">
                            {% cmp="btn" type="submit" variant="blue" icon="filter" label="Apply" %}
                            <?php if ($hasFilters) { ?>
                            {% cmp="btn" href="{$clearHref}" variant="slate" icon="x" label="Clear" %}
                            <?php } ?>
                        </div>
                    </div>
                </form>
            </details>
            <?php if ($canConfigure) { ?>
            {% cmp="btn" variant="slate" icon="settings" label="Settings" dataModalTarget="moderationSettings" %}
            <?php } ?>
        </div>
    </div>

    <div data-table-region>
    {% if cases|empty %}
        {% cmp="empty-state" icon="flag" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="queue">
        <div class="queue-list">
            <div>
                <div class="queue-head">
                    <span><?= (int) $pagination['total_records'] ?> <?= (int) $pagination['total_records'] === 1 ? 'case' : 'cases' ?></span>
                    <span class="flex items-center gap-3">
                        <?php foreach (['priority' => 'Priority', 'reports' => 'Reports', 'recent' => 'Latest'] as $key => $text) {
                            if (!$sort->has($key)) {
                                continue;
                            }
                            $on = $sort->isOn($key); ?>
                        <a href="<?= e($sort->urlFor($basePath, $key)) ?>" data-sort-link class="inline-flex items-center gap-0.5 hover:text-custom-500 <?= $on ? 'font-semibold text-slate-900 dark:text-zink-50' : '' ?>">
                            <?= e($text) ?><?= $on ? ($sort->direction() === 'asc' ? ' &uarr;' : ' &darr;') : '' ?>
                        </a>
                        <?php } ?>
                    </span>
                </div>
                <ul role="listbox" aria-label="Cases">
                    <?php foreach ($cases as $row) {
                        $reasons = array_filter(explode(',', (string) $row['categories']));
                        $status = (string) $row['status'];
                        $statusLabel = (string) $row['status_label']; ?>
                    <li role="presentation">
                        <button type="button" role="option" data-queue-item="<?= (int) $row['id'] ?>" aria-selected="false" class="queue-item border-b border-slate-100 dark:border-zink-600">
                            <span class="queue-avatar" aria-hidden="true"><?= e(mb_substr((string) ($row['subject_author_handle'] ?? '?'), 0, 1)) ?></span>
                            <span class="grow min-w-0">
                                <span class="flex items-center justify-between gap-2 mb-0.5">
                                    <span class="text-xs <?= $muted ?>"><?= $row['subject_type'] === 'post' ? 'Post' : 'Comment' ?><?= $row['blog_name'] ? ' in '.e((string) $row['blog_name']) : '' ?></span>
                                    <span class="text-xs shrink-0 <?= $muted ?>"><?= e(local_datetime($row['last_reported_at'] ?? null, 'M j')) ?></span>
                                </span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-zink-50 line-clamp-2 break-words"><?= e(mb_strimwidth((string) ($row['subject_snapshot'] ?? ''), 0, 120, '...') ?: '(empty)') ?></span>
                                <span class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    {% cmp="status-badge" status="{$status}" label="{$statusLabel}" %}
                                    <?php foreach (array_slice($reasons, 0, 2) as $slug) { ?>
                                    <span class="pill bg-slate-100 text-slate-700 dark:bg-zink-500 dark:text-zink-100"><?= e($categoryLabels[$slug] ?? $slug) ?></span>
                                    <?php } ?>
                                    <span class="<?= $muted ?>"><?= (int) $row['report_count'] ?> report<?= (int) $row['report_count'] === 1 ? '' : 's' ?></span>
                                </span>
                            </span>
                        </button>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <div class="p-4">
                {% cmp="paginator" pagination="{$pagination}" pageParam="page" basePath="{$basePath}" itemSingular="case" itemPlural="cases" %}
            </div>
        </div>

        <div class="min-w-0 h-full overflow-hidden">
            <?php foreach ($cases as $row) {
                $caseUrl = $basePath.'/'.(int) $row['id'];
                $reasons = array_filter(explode(',', (string) $row['categories']));
                $reporters = array_filter(explode(',', (string) $row['first_reporters']));
                $status = (string) $row['status'];
                $statusLabel = (string) $row['status_label'];
                $contentState = (string) $row['content_state'];
                $contentLabel = (string) $row['content_label']; ?>
            <section class="queue-detail h-full" data-queue-panel="<?= (int) $row['id'] ?>" aria-label="Case #<?= (int) $row['id'] ?>">
                <div class="queue-bar">
                        <button type="button" data-queue-back class="queue-icon queue-back" aria-label="Back to cases"><i data-lucide="arrow-left" class="size-4"></i></button>
                        <button type="button" data-queue-step="-1" class="queue-icon" aria-label="Previous"><i data-lucide="chevron-up" class="size-4"></i></button>
                        <button type="button" data-queue-step="1" class="queue-icon" aria-label="Next"><i data-lucide="chevron-down" class="size-4"></i></button>
                    </div>
                    <div class="queue-body">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        {% cmp="status-badge" status="{$status}" label="{$statusLabel}" %}
                        <?php if ($contentState !== 'visible') { ?>
                        {% cmp="status-badge" status="{$contentState}" label="{$contentLabel}" %}
                        <?php } ?>
                        <span class="<?= $muted ?>">Case #<?= (int) $row['id'] ?> &middot; priority <?= (int) $row['priority'] ?></span>
                    </div>
                    <p class="<?= $muted ?> mb-3">
                        <?= $row['subject_type'] === 'post' ? 'Post' : 'Comment' ?><?= $row['blog_name'] ? ' in '.e((string) $row['blog_name']) : '' ?>
                    </p>

                    <?php if ($row['last_error'] !== null) { ?>
                    <p class="mb-3 p-3 rounded-lg text-sm bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200" role="alert">Automatic action failed. Nothing was changed.</p>
                    <?php } elseif ($row['pending_action'] !== null) { ?>
                    <p class="mb-3 p-3 rounded-lg text-sm bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200" role="status">A rule proposed an action and is waiting for you.</p>
                    <?php } ?>

                    <blockquote class="p-3 mb-4 rounded-lg text-sm whitespace-pre-line break-words bg-slate-50 dark:bg-zink-700"><?= e(mb_strimwidth((string) ($row['subject_snapshot'] ?? ''), 0, 600, '...') ?: '(empty)') ?></blockquote>

                    <dl class="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm mb-4">
                        <dt class="<?= $muted ?>">Author</dt>
                        <dd>
                            <?php if ($row['subject_author_id'] !== null && $canViewUsers) { ?>
                            <a href="<?= e(lurl('/admin/users/'.((int) $row['subject_author_id']))) ?>" class="hover:text-custom-500 hover:underline">@<?= e((string) ($row['subject_author_handle'] ?? 'unknown')) ?></a>
                            <?php } elseif ($row['subject_author_id'] !== null) { ?>
                            @<?= e((string) ($row['subject_author_handle'] ?? 'unknown')) ?>
                            <?php } else { ?>
                            <span class="<?= $muted ?>">No account</span>
                            <?php } ?>
                        </dd>
                        <dt class="<?= $muted ?>">Reason</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            <?php foreach ($reasons as $slug) { ?>
                            <span class="pill <?= $slug === $row['top_category'] ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-slate-100 text-slate-700 dark:bg-zink-500 dark:text-zink-100' ?>"><?= e($categoryLabels[$slug] ?? $slug) ?></span>
                            <?php } ?>
                        </dd>
                        <dt class="<?= $muted ?>">Reports</dt>
                        <dd>
                            <?= (int) $row['report_count'] ?>
                            <?php if ((int) $row['counted_report_count'] !== (int) $row['report_count']) { ?>
                            <span class="<?= $muted ?>">(<?= (int) $row['counted_report_count'] ?> counted)</span>
                            <?php } ?>
                            <?php if ($reporters !== []) { ?>
                            <span class="block <?= $muted ?>">
                                <?= e(implode(', ', array_map(static fn ($h) => $h === '' ? 'deleted account' : '@'.$h, $reporters))) ?><?= (int) $row['report_count'] > count($reporters) ? ' and '.((int) $row['report_count'] - count($reporters)).' more' : '' ?>
                            </span>
                            <?php } ?>
                        </dd>
                        <dt class="<?= $muted ?>">Last report</dt>
                        <dd><?= e(local_datetime($row['last_reported_at'] ?? null, 'M j, Y H:i')) ?></dd>
                    </dl>

                    <div class="flex flex-wrap gap-2">
                        {% cmp="btn" href="{$caseUrl}" variant="blue" icon="eye" label="Open case" %}
                        <?php if (in_array($status, ['open', 'escalated'], true)) { ?>
                        <form method="post" action="<?= e($caseUrl) ?>/review" class="m-0">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="slate" icon="play" label="Start review" %}
                        </form>
                        <?php } ?>
                        <?php if ($row['public_url'] !== null) { ?>
                        <?php $publicUrl = (string) $row['public_url']; ?>
                        {% cmp="btn" href="{$publicUrl}" variant="slate" icon="external-link" label="View item" %}
                        <?php } ?>
                    </div>
                </div>
            </section>
            <?php } ?>
        </div>
    </div>
    {% endif %}
    </div>
</div>

<?php if ($canConfigure) { ?>
{% include "areas/admin/Report/_settings_modal.lex.php" %}
<?php } ?>
{% endblock %}
