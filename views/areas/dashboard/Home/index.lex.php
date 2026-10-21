{% extends "back.lex.php" %}
{% block title %}{{ t('dashboard.overview.title') }}{% endblock %}
{% block body %}
<?php
$blogId = (int) $blog['id'];
$statusLabel = $t('dashboard.blogStatus.'.$blog['status']);
$newPostLabel = $t('dashboard.actions.newPost');
$cardLink = 'card hover:border-custom-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';
$tones = [
    'green' => 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400',
    'slate' => 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200',
    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
    'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
];

// One row of numbers, built from whichever sections this person's permissions opened.
$kpis = [];
if ($blogPosts !== null) {
    $kpis[] = ['label' => 'dashboard.stats.published', 'value' => $blogPosts['counts']['published'], 'href' => '/dashboard/post?status=published', 'icon' => 'check-circle-2', 'tone' => 'green'];
    $kpis[] = ['label' => 'dashboard.stats.drafts', 'value' => $blogPosts['counts']['draft'], 'href' => '/dashboard/post?status=draft', 'icon' => 'file-text', 'tone' => 'slate'];
    if ($workflowEnabled) {
        $kpis[] = ['label' => 'dashboard.stats.pending', 'value' => $blogPosts['counts']['pending'], 'href' => '/dashboard/post?status=pending', 'icon' => 'clock', 'tone' => 'amber'];
    }
    $kpis[] = ['label' => 'dashboard.stats.archived', 'value' => $blogPosts['counts']['archived'], 'href' => '/dashboard/post?status=archived', 'icon' => 'archive', 'tone' => 'slate'];
}
if ($myWork !== null) {
    $kpis[] = ['label' => 'dashboard.overview.myDrafts', 'value' => $myWork['counts']['draft'], 'href' => '/dashboard/post?status=draft', 'icon' => 'file-text', 'tone' => 'slate'];
    if ($workflowEnabled) {
        $kpis[] = ['label' => 'dashboard.overview.submitted', 'value' => $myWork['counts']['pending'], 'href' => '/dashboard/post?status=pending', 'icon' => 'send', 'tone' => 'sky'];
        $kpis[] = ['label' => 'dashboard.overview.needsRevision', 'value' => $myWork['counts']['needs_changes'], 'href' => '/dashboard/post?status=needs_changes', 'icon' => 'pen-line', 'tone' => 'amber'];
    }
    $kpis[] = ['label' => 'dashboard.overview.myPublished', 'value' => $myWork['counts']['published'], 'href' => '/dashboard/post?status=published', 'icon' => 'check-circle-2', 'tone' => 'green'];
}
if ($review !== null && $blogPosts === null) {
    $kpis[] = ['label' => 'dashboard.overview.awaitingReview', 'value' => $review['inReview'], 'href' => '/dashboard/blog/'.$blogId.'/review-queue', 'icon' => 'eye', 'tone' => 'sky'];
    $kpis[] = ['label' => 'dashboard.overview.needsRevisit', 'value' => $review['needsChanges'], 'href' => '/dashboard/blog/'.$blogId.'/review-queue', 'icon' => 'pen-line', 'tone' => 'amber'];
}
if ($comments !== null) {
    $kpis[] = ['label' => 'dashboard.stats.comments', 'value' => $comments['approved'], 'href' => '/dashboard/blog/'.$blogId.'/comments?status=pending', 'icon' => 'message-square', 'tone' => 'sky',
        'note' => $comments['pending'] > 0 ? $t('dashboard.overview.pendingCount', ['count' => $comments['pending']]) : null];
}
if ($subscribers !== null) {
    $kpis[] = ['label' => 'dashboard.stats.subscribers', 'value' => $subscribers, 'href' => '/dashboard/blog/'.$blogId.'/subscribers', 'icon' => 'mail', 'tone' => 'violet'];
}
$kpiCols = ['', 'lg:grid-cols-1', 'lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4', 'lg:grid-cols-5', 'lg:grid-cols-3 xl:grid-cols-6', 'lg:grid-cols-4 xl:grid-cols-7'][count($kpis)] ?? 'lg:grid-cols-4';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
        <div class="flex items-center gap-3 min-w-0">
            <?php if ($logoPath !== '') { ?>
            <img src="<?= e($logoPath) ?>" alt="" class="size-12 rounded-md object-cover shrink-0 border border-slate-200 dark:border-zink-500">
            <?php } else { ?>
            <span class="inline-flex items-center justify-center size-12 rounded-md shrink-0 bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300 text-lg font-semibold" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($blog['name'], 0, 1))) ?></span>
            <?php } ?>
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300">{{ t('dashboard.overview.title') }}</p>
                <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50 truncate" dir="auto"><?= e($blog['name']) ?></h1>
                <p class="flex flex-wrap items-center gap-2 mt-1 text-xs text-slate-500 dark:text-zink-300">
                    <?php $blogStatus = $blog['status']; ?>
                    {% cmp="status-badge" status="{$blogStatus}" label="{$statusLabel}" %}
                    <span dir="ltr">/blog/<?= e($blog['slug']) ?></span>
                    <?php if ($roleLabel !== null) { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/40 dark:text-violet-300 dark:border-violet-800"><?= e($t('dashboard.overview.yourRole', ['role' => $roleLabel])) ?></span>
                    <?php } ?>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= e(lurl('/blog/'.$blog['slug'])) ?>" target="_blank" rel="noopener"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                {% cache 'lucide:external-link' ttl=31536000 %}<i data-lucide="external-link" class="size-4"></i>{% endcache %}
                <span>{{ t('dashboard.actions.viewLive') }}</span>
            </a>
            <?php if ($canCreate) { ?>
            {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
            <?php } ?>
        </div>
    </header>

    {% cmp="next-steps-card" setup="{$setup}" blogId="{$blogId}" blogUrl="{$blogUrl}" %}

    <?php if ($kpis !== []) { ?>
    <div class="grid grid-cols-2 <?= $kpiCols ?> gap-4 mb-6">
        <?php foreach ($kpis as $kpi) {
            $kpiIcon = $kpi['icon']; ?>
        <a href="<?= e(lurl($kpi['href'])) ?>" class="<?= $cardLink ?>">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide"><?= e($t($kpi['label'])) ?></span>
                    <span class="inline-flex items-center justify-center size-8 rounded-md <?= $tones[$kpi['tone']] ?>">
                        {% cache 'lucide:overview:' . $kpiIcon ttl=31536000 %}<i data-lucide="<?= e($kpiIcon) ?>" class="size-4" aria-hidden="true"></i>{% endcache %}
                    </span>
                </div>
                <div class="flex items-end gap-2">
                    <span class="text-2xl font-semibold text-slate-900 dark:text-zink-50"><?= (int) $kpi['value'] ?></span>
                    <?php if (!empty($kpi['note'])) { ?>
                    <span class="mb-1 px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/40 dark:border-amber-800"><?= e($kpi['note']) ?></span>
                    <?php } ?>
                </div>
            </div>
        </a>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if (!empty($analytics)) {
        $analyticsPresent = new \App\Presenters\AnalyticsPresenter(\App\Services\LocaleState::get()->chromeLocale, $t, app(\App\Services\LocaleRegistry::class), 'blog');
        $analyticsPrevious = $analytics['range']->previous();
        $analyticsPeriod = $t('analytics.range.span', ['from' => $analyticsPresent->date($analyticsPrevious->fromDate()), 'to' => $analyticsPresent->date($analyticsPrevious->toDate())]);
        $analyticsUrl = '/dashboard/blog/'.$blogId.'/insights?range=7d';
        $analyticsTones = [
            'good' => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400',
            'bad' => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
            'neutral' => 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-200',
        ]; ?>
    <section class="card mb-6" aria-labelledby="home-traffic">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h2 id="home-traffic" class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                    {% cache 'lucide:trending-up:home' ttl=31536000 %}<i data-lucide="trending-up" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                    <?= e($t($myWork !== null ? 'dashboard.overview.yourTraffic' : 'analytics.home.title')) ?>
                </h2>
                <a href="<?= e(lurl($analyticsUrl)) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600"><?= e($t('analytics.home.open')) ?></a>
            </div>
            <?php if ((int) $analytics['metrics']['views']['value'] === 0) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.home.empty')) ?></p>
            <?php } else { ?>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <?php foreach (['views' => 'analytics.home.views', 'visitors' => 'analytics.home.visitors'] as $analyticsKey => $analyticsLabel) {
                    $analyticsMetric = $analytics['metrics'][$analyticsKey];
                    $analyticsChange = $analyticsPresent->change($analyticsMetric['change'], $analyticsPresent->number((int) $analyticsMetric['previous']), $analyticsPeriod); ?>
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide"><?= e($t($analyticsLabel)) ?></dt>
                    <dd class="flex items-center gap-2 mt-1">
                        <span class="text-2xl font-semibold text-slate-900 dark:text-zink-50"><?= e($analyticsPresent->number((int) $analyticsMetric['value'])) ?></span>
                        <?php if ($analyticsChange['direction'] !== 'none') { ?>
                        <span class="px-1.5 py-0.5 text-xs font-medium rounded <?= $analyticsTones[$analyticsChange['tone']] ?>" title="<?= e($analyticsChange['label']) ?>">
                            <span aria-hidden="true"><?= e($analyticsChange['short']) ?></span>
                            <span class="sr-only"><?= e($analyticsChange['label']) ?></span>
                        </span>
                        <?php } ?>
                    </dd>
                </div>
                <?php } ?>
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide"><?= e($t('analytics.home.topPost')) ?></dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-zink-50 truncate" dir="auto">
                        <?php if (($analytics['topPost']['title'] ?? null) !== null) { ?>
                        <a href="<?= e(lurl('/dashboard/blog/'.$blogId.'/insights/posts/'.(int) $analytics['topPost']['post_id']).'?range=7d') ?>" class="hover:text-custom-500"><?= e((string) $analytics['topPost']['title']) ?></a>
                        <span class="text-slate-500 dark:text-zink-300">· <?= e($analyticsPresent->number((int) $analytics['topPost']['views'])) ?></span>
                        <?php } else { ?>
                        <?= e($t('analytics.metrics.none')) ?>
                        <?php } ?>
                    </dd>
                </div>
            </dl>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <?php
    // Each list is a link to the post: the editor when this person may change it, the review page otherwise.
    $postList = static function (array $rows, string $hrefPattern, callable $meta) use ($t): void { ?>
        <ul class="divide-y divide-slate-100 dark:divide-zink-600">
            <?php foreach ($rows as $row) { ?>
            <li class="py-2.5 first:pt-0 last:pb-0">
                <a href="<?= e(lurl(sprintf($hrefPattern, (int) $row['id']))) ?>" class="flex items-center gap-3 group/post">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-900 dark:text-zink-50 truncate group-hover/post:text-custom-500" dir="auto"><?= e((string) $row['title']) ?></span>
                        <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($meta($row)) ?></span>
                    </span>
                    {% cache 'lucide:chevron-right:overview' ttl=31536000 %}<i data-lucide="chevron-right" class="size-4 text-slate-400 shrink-0 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
                </a>
            </li>
            <?php } ?>
        </ul>
    <?php };
    $updatedMeta = static fn (array $row): string => $t('dashboard.blogStatus.'.($row['status'] ?? 'draft')).' · '.local_datetime($row['updated_at'] ?? $row['created_at'] ?? null, 'M j');
    ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <?php if ($review !== null) { ?>
        <section class="card" aria-labelledby="home-review">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="home-review" class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        {% cache 'lucide:clipboard-check:overview' ttl=31536000 %}<i data-lucide="clipboard-check" class="size-4 text-sky-500" aria-hidden="true"></i>{% endcache %}
                        {{ t('dashboard.overview.reviewTitle') }}
                    </h2>
                    <a href="<?= e(lurl('/dashboard/blog/'.($blogId).'/review-queue')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">{{ t('dashboard.overview.openQueue') }}</a>
                </div>
                <?php if ($review['items'] === []) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-6 text-center">{{ t('dashboard.overview.queueEmpty') }}</p>
                <?php } else {
                    $postList($review['items'], '/dashboard/post/%d/review', static fn (array $row): string => $t(match ($row['workflow_state']) {
                        'needs_changes' => 'dashboard.overview.stateNeedsChanges',
                        'approved' => 'dashboard.reviewQueue.states.approved',
                        default => 'dashboard.overview.stateInReview',
                    }).' · '.(empty($row['reviewer_handle']) ? $t('dashboard.overview.unassigned') : '@'.$row['reviewer_handle']));
                } ?>
            </div>
        </section>
        <?php } ?>

        <?php if ($myWork !== null) { ?>
        <section class="card" aria-labelledby="home-mywork">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="home-mywork" class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        {% cache 'lucide:pencil:overview' ttl=31536000 %}<i data-lucide="pencil" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                        {{ t('dashboard.overview.yourDrafts') }}
                    </h2>
                    <a href="<?= e(lurl('/dashboard/post')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">{{ t('dashboard.overview.allYourPosts') }}</a>
                </div>
                <?php if ($myWork['drafts'] === []) { ?>
                <div class="text-center py-6">
                    <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">{{ t('dashboard.overview.noDrafts') }}</p>
                    {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
                </div>
                <?php } else {
                    $postList($myWork['drafts'], '/dashboard/post/%d/edit', $updatedMeta);
                } ?>
            </div>
        </section>
        <?php } ?>

        <?php if ($blogPosts !== null) { ?>
        <section class="card" aria-labelledby="home-attention">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="home-attention" class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        {% cache 'lucide:alert-circle' ttl=31536000 %}<i data-lucide="alert-circle" class="size-4 text-amber-500"></i>{% endcache %}
                        {{ t('dashboard.sections.needsAttention') }}
                    </h2>
                    <?php if ($blogPosts['needsAttention'] !== []) { ?>
                    <a href="<?= e(lurl('/dashboard/post?status=draft')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">{{ t('dashboard.overview.viewAll') }}</a>
                    <?php } ?>
                </div>
                <?php if ($blogPosts['needsAttention'] === []) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-6 text-center">{{ t('dashboard.empty.noDrafts') }}</p>
                <?php } else {
                    $postList($blogPosts['needsAttention'], '/dashboard/post/%d/edit', $updatedMeta);
                } ?>
            </div>
        </section>

        <section class="card" aria-labelledby="home-recent">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="home-recent" class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        {% cache 'lucide:rss' ttl=31536000 %}<i data-lucide="rss" class="size-4 text-green-500"></i>{% endcache %}
                        {{ t('dashboard.sections.recent') }}
                    </h2>
                    <?php if ($blogPosts['recent'] !== []) { ?>
                    <a href="<?= e(lurl('/dashboard/post?status=published')) ?>" class="text-xs font-medium text-custom-500 hover:text-custom-600">{{ t('dashboard.actions.managePosts') }}</a>
                    <?php } ?>
                </div>
                <?php if ($blogPosts['recent'] === []) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-6 text-center">{{ t('dashboard.empty.noRecent') }}</p>
                <?php } else {
                    $postList($blogPosts['recent'], '/dashboard/post/%d/edit', static fn (array $row): string => local_datetime($row['published_at'] ?? $row['created_at'] ?? null, 'M j, Y').(empty($row['author_handle']) ? '' : ' · @'.$row['author_handle']));
                } ?>
            </div>
        </section>
        <?php } ?>

    </div>
</div>
{% endblock %}
{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/tooltip.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/next-steps.js')) ?>"></script>
{% endblock %}
