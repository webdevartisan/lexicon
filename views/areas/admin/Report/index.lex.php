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

    <?php if ($canConfigure) { ?>
    <div class="flex justify-end mb-3">
        {% cmp="btn" href="/admin/reports/settings" variant="slate" icon="settings" label="Settings" %}
    </div>
    <?php } ?>

    <nav class="flex flex-wrap gap-2 mb-4" aria-label="Case status">
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

    <form method="GET" action="<?= e($basePath) ?>" class="card mb-4" aria-label="Filter cases">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <div class="card-body grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
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
            <div class="flex gap-2 lg:col-span-6">
                {% cmp="btn" type="submit" variant="blue" icon="filter" label="Filter" %}
                <?php if ($hasFilters) { ?>
                <?php $clearHref = $basePath.'?status='.rawurlencode($filters['status']); ?>
                {% cmp="btn" href="{$clearHref}" variant="slate" icon="x" label="Clear" %}
                <?php } ?>
            </div>
        </div>
    </form>

    <?php if ($unknownHandles !== []) { ?>
    <div class="mb-4 p-3 rounded-md border border-yellow-200 bg-yellow-50 text-sm text-slate-700 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-zink-200" role="status">
        No account is tagged <?= e(implode(' or ', $unknownHandles)) ?>, so nothing matches.
    </div>
    <?php } ?>

    <?php if ($overLimit['total'] > 0) { ?>
    <div class="mb-4 p-3 rounded-md border border-amber-200 bg-amber-50 text-sm text-slate-700 dark:bg-amber-900/20 dark:border-amber-800 dark:text-zink-200" role="status">
        <p class="font-medium">
            <?= $overLimit['total'] ?> <?= $overLimit['total'] === 1 ? 'person has' : 'people have' ?> reached the unfounded-report limit in the last <?= (int) $unfoundedWindow ?> days.
            Their reports no longer count toward the rules. Decide whether to warn or pause them.
        </p>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
            <?php foreach ($overLimit['reporters'] as $reporter) { ?>
            <li>
                <a href="/admin/reports/reporters/<?= (int) $reporter['id'] ?>" class="font-medium hover:text-custom-500 hover:underline">@<?= e((string) $reporter['handle']) ?></a>
                <span class="text-slate-500 dark:text-zink-200"><?= (int) $reporter['unfounded'] ?> unfounded, <?= $reporter['warned_at'] === null ? 'not warned yet' : 'warned '.e(local_datetime((string) $reporter['warned_at'], 'M j')) ?></span>
            </li>
            <?php } ?>
            <?php if ($overLimit['total'] > count($overLimit['reporters'])) { ?>
            <li class="text-slate-500 dark:text-zink-200">and <?= $overLimit['total'] - count($overLimit['reporters']) ?> more</li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>

    <div data-table-region>
    {% if cases|empty %}
        {% cmp="empty-state" icon="flag" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="priority" label="Priority" %}
                        <th class="px-3.5 py-2.5 font-semibold">Reported item</th>
                        <th class="px-3.5 py-2.5 font-semibold">Author</th>
                        <th class="px-3.5 py-2.5 font-semibold">Reason</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="reports" label="Reports" %}
                        <th class="px-3.5 py-2.5 font-semibold">State</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="recent" label="Last report" %}
                        <th class="px-3.5 py-2.5 font-semibold text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    <?php foreach ($cases as $row) {
                        $caseUrl = $basePath.'/'.(int) $row['id'];
                        $reasons = array_filter(explode(',', (string) $row['categories']));
                        $reporters = array_filter(explode(',', (string) $row['first_reporters']));
                        $status = (string) $row['status'];
                        $statusLabel = (string) $row['status_label'];
                        $contentState = (string) $row['content_state'];
                        $contentLabel = (string) $row['content_label'];
                        $rowTitle = 'case #'.(int) $row['id'];
                        $rowActions = [
                            ['label' => 'Open case', 'icon' => 'eye', 'href' => $caseUrl],
                            ['label' => 'View reported item', 'icon' => 'external-link', 'href' => (string) $row['public_url'], 'newTab' => true, 'can' => $row['public_url'] !== null],
                            ['label' => 'Start review', 'icon' => 'play', 'post' => $caseUrl.'/review', 'can' => in_array($status, ['open', 'escalated'], true)],
                            ['label' => 'View author', 'icon' => 'user', 'href' => '/admin/users/'.(int) $row['subject_author_id'], 'can' => $canViewUsers && $row['subject_author_id'] !== null],
                        ]; ?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors align-top">
                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                            <span class="font-semibold text-slate-900 dark:text-zink-50"><?= (int) $row['priority'] ?></span>
                            <?php if ($row['last_error'] !== null) { ?>
                            <span class="block text-[10px] font-medium text-red-600 dark:text-red-400">Automatic action failed</span>
                            <?php } elseif ($row['pending_action'] !== null) { ?>
                            <span class="block text-[10px] font-medium text-amber-700 dark:text-amber-300">Rule waiting for you</span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 min-w-[16rem]">
                            <a href="<?= e($caseUrl) ?>" class="font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 hover:underline">
                                <?= e(mb_strimwidth((string) ($row['subject_snapshot'] ?? ''), 0, 90, '...') ?: '(empty)') ?>
                            </a>
                            <span class="block <?= $muted ?>">
                                <?= $row['subject_type'] === 'post' ? 'Post' : 'Comment' ?>
                                <?= $row['blog_name'] ? ' in '.e((string) $row['blog_name']) : '' ?>
                                &middot; case #<?= (int) $row['id'] ?>
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                            <?php if ($row['subject_author_id'] !== null && $canViewUsers) { ?>
                            <a href="/admin/users/<?= (int) $row['subject_author_id'] ?>" class="hover:text-custom-500 hover:underline">@<?= e((string) ($row['subject_author_handle'] ?? 'unknown')) ?></a>
                            <?php } elseif ($row['subject_author_id'] !== null) { ?>
                            @<?= e((string) ($row['subject_author_handle'] ?? 'unknown')) ?>
                            <?php } else { ?>
                            <span class="<?= $muted ?>">No account</span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <?php foreach ($reasons as $slug) { ?>
                            <span class="inline-flex items-center px-2 py-0.5 mr-1 mb-1 text-[10px] font-medium rounded-full border <?= $slug === $row['top_category'] ? 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800' : 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500' ?>"><?= e($categoryLabels[$slug] ?? $slug) ?></span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                            <?= (int) $row['report_count'] ?>
                            <?php if ((int) $row['counted_report_count'] !== (int) $row['report_count']) { ?>
                            <span class="<?= $muted ?>">(<?= (int) $row['counted_report_count'] ?> counted)</span>
                            <?php } ?>
                            <?php if ($reporters !== []) { ?>
                            <span class="block <?= $muted ?>">
                                <?= e(implode(', ', array_map(static fn ($h) => $h === '' ? 'deleted account' : '@'.$h, $reporters))) ?><?= (int) $row['report_count'] > count($reporters) ? ' and '.((int) $row['report_count'] - count($reporters)).' more' : '' ?>
                            </span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                            {% cmp="status-badge" status="{$status}" label="{$statusLabel}" %}
                            <?php if ($contentState !== 'visible') { ?>
                            {% cmp="status-badge" status="{$contentState}" label="{$contentLabel}" %}
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 whitespace-nowrap text-slate-500 dark:text-zink-300"><?= e(local_datetime($row['last_reported_at'] ?? null, 'M j, Y H:i')) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end">
                                {% cmp="row-actions" title="{$rowTitle}" items="{$rowActions}" %}
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" basePath="{$basePath}" itemSingular="case" itemPlural="cases" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}
