{% extends "back.lex.php" %}
{% block title %}{{ t('dashboard.overview.title') }}{% endblock %}
{% block subtitle %}
<?= e($home['summary']) ?>
<?php if ($blog['status'] !== 'published') {
    $blogStatus = $blog['status'];
    $statusLabel = $t('dashboard.blogStatus.'.$blogStatus); ?>
{% cmp="status-badge" status="{$blogStatus}" label="{$statusLabel}" %}
<?php } ?>
{% endblock %}
{% block actions %}
<div class="flex items-center gap-2">
    <a href="<?= e(lurl('/blog/'.$blog['slug'])) ?>" target="_blank" rel="noopener" aria-label="<?= e($t('dashboard.actions.viewLive')) ?>"
        class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-lg text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-transparent dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
        {% cache 'lucide:external-link' ttl=31536000 %}<i data-lucide="external-link" class="size-4"></i>{% endcache %}
        <span class="hidden sm:inline">{{ t('dashboard.actions.viewLive') }}</span>
    </a>
    <?php if ($canCreate) { ?>
    {% set newPostLabel = t('dashboard.actions.newPost') %}
    {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
    <?php } ?>
</div>
{% endblock %}
{% block body %}
<?php
$blogId = (int) $blog['id'];
$newPostLabel = $t('dashboard.actions.newPost');
$tones = [
    'green' => 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400',
    'slate' => 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200',
    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
    'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
];
$contextTones = [
    'good' => 'text-green-600 dark:text-green-400',
    'warn' => 'text-amber-600 dark:text-amber-400',
    'bad' => 'text-red-600 dark:text-red-400',
    'muted' => 'text-slate-500 dark:text-zink-300',
];
$linkClass = 'text-xs font-medium text-custom-500 hover:text-custom-600';
$activityShown = 6;
$activityRows = $home['activity'];

$linkList = static function (array $items): void { ?>
        <ul class="divide-y divide-slate-100 dark:divide-zink-600">
            <?php foreach ($items as $item) { ?>
            <li class="py-2.5 first:pt-0 last:pb-0">
                <a href="<?= e(lurl($item['href'])) ?>" class="flex items-center gap-3 group/post">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-900 dark:text-zink-50 truncate group-hover/post:text-custom-500" dir="auto"><?= e($item['text']) ?></span>
                        <?php if ($item['meta'] !== '') { ?>
                        <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($item['meta']) ?></span>
                        <?php } ?>
                    </span>
                    {% cache 'lucide:chevron-right:overview' ttl=31536000 %}<i data-lucide="chevron-right" class="size-4 text-slate-400 shrink-0 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
                </a>
            </li>
            <?php } ?>
        </ul>
<?php };
$activityItem = static function (array $row) use ($tones): void {
    $feedIcon = $row['icon']; ?>
                        <li>
                            <a href="<?= e(lurl($row['href'])) ?>" class="flex items-center gap-3 py-2.5 group/event">
                                <span class="inline-flex items-center justify-center rounded-md size-8 shrink-0 <?= $tones[$row['tone']] ?>">
                                    {% cache 'lucide:home:' . $feedIcon ttl=31536000 %}<i data-lucide="<?= e($feedIcon) ?>" class="size-4" aria-hidden="true"></i>{% endcache %}
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm text-slate-900 dark:text-zink-50 truncate group-hover/event:text-custom-500" dir="auto"><?= e($row['text']) ?></span>
                                    <?php if ($row['meta'] !== '') { ?>
                                    <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($row['meta']) ?></span>
                                    <?php } ?>
                                </span>
                                <span class="text-xs text-slate-500 dark:text-zink-300 shrink-0"><?= e($row['when']) ?></span>
                            </a>
                        </li>
<?php };
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    {% cmp="next-steps-card" setup="{$setup}" blogId="{$blogId}" blogUrl="{$blogUrl}" %}

    <?php if ($home['stats'] !== []) { ?>
    <div class="card stat-strip" style="--stat-count: <?= count($home['stats']) ?>">
        <div class="stat-grid">
            <?php foreach ($home['stats'] as $stat) { ?>
            <a href="<?= e(lurl($stat['href'])) ?>" class="stat-cell">
                <span class="block text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide"><?= e($stat['label']) ?></span>
                <span class="block mt-1 text-2xl font-semibold leading-tight text-slate-900 dark:text-zink-50"><?= e($stat['value']) ?></span>
                <?php if ($stat['context'] !== null) { ?>
                <span class="block mt-0.5 text-xs <?= $contextTones[$stat['context'][0]] ?>"<?php if ($stat['title'] !== '') { ?> title="<?= e($stat['title']) ?>"<?php } ?>><?= e($stat['context'][1]) ?></span>
                <?php } ?>
            </a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-5 items-start">

        <div class="lg:col-span-2 min-w-0">
            <?php if ($activityRows !== null) { ?>
            <section class="card" aria-labelledby="home-activity">
                <div class="card-body">
                    <h2 id="home-activity" class="flex items-center gap-2 mb-3 text-base font-semibold text-slate-900 dark:text-zink-50">
                        {% cache 'lucide:activity:home' ttl=31536000 %}<i data-lucide="activity" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                        {{ t('dashboard.home.activity.title') }}
                    </h2>
                    <?php if ($activityRows === []) { ?>
                    <p class="py-6 text-sm text-center text-slate-500 dark:text-zink-300">{{ t('dashboard.home.activity.empty') }}</p>
                    <?php } else { ?>
                    <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php foreach (array_slice($activityRows, 0, $activityShown) as $row) {
                            $activityItem($row);
                        } ?>
                    </ul>
                    <?php if (count($activityRows) > $activityShown) { ?>
                    <details class="more-toggle">
                        <summary class="<?= $linkClass ?> pt-3 pb-1 cursor-pointer select-none">
                            <span class="more-closed">{{ t('dashboard.home.activity.more') }}</span>
                            <span class="more-open">{{ t('dashboard.home.activity.less') }}</span>
                        </summary>
                        <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                            <?php foreach (array_slice($activityRows, $activityShown) as $row) {
                                $activityItem($row);
                            } ?>
                        </ul>
                    </details>
                    <?php } ?>
                    <?php } ?>
                </div>
            </section>
            <?php } elseif ($home['drafts'] !== null) { ?>
            <section class="card" aria-labelledby="home-mywork">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="home-mywork" class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-zink-50">
                            {% cache 'lucide:pencil:overview' ttl=31536000 %}<i data-lucide="pencil" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                            {{ t('dashboard.overview.yourDrafts') }}
                        </h2>
                        <a href="<?= e(lurl('/dashboard/post')) ?>" class="<?= $linkClass ?>">{{ t('dashboard.overview.allYourPosts') }}</a>
                    </div>
                    <?php if ($home['drafts'] === []) { ?>
                    <div class="py-6 text-center">
                        <p class="mb-4 text-sm text-slate-500 dark:text-zink-300">{{ t('dashboard.overview.noDrafts') }}</p>
                        {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
                    </div>
                    <?php } else {
                        $linkList($home['drafts']);
                    } ?>
                </div>
            </section>
            <?php } elseif ($home['reviewItems'] !== null) { ?>
            <section class="card" aria-labelledby="home-review">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="home-review" class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-zink-50">
                            {% cache 'lucide:clipboard-check:overview' ttl=31536000 %}<i data-lucide="clipboard-check" class="size-4 text-sky-500" aria-hidden="true"></i>{% endcache %}
                            {{ t('dashboard.overview.reviewTitle') }}
                        </h2>
                        <a href="<?= e(lurl('/dashboard/blog/'.$blogId.'/review-queue')) ?>" class="<?= $linkClass ?>">{{ t('dashboard.overview.openQueue') }}</a>
                    </div>
                    <?php if ($home['reviewItems'] === []) { ?>
                    <p class="py-6 text-sm text-center text-slate-500 dark:text-zink-300">{{ t('dashboard.overview.queueEmpty') }}</p>
                    <?php } else {
                        $linkList($home['reviewItems']);
                    } ?>
                </div>
            </section>
            <?php } ?>
        </div>

        <div class="min-w-0">
            <?php if ($canCreate) { ?>
            <form method="get" action="<?= e(lurl('/dashboard/post/new')) ?>" class="card">
                <div class="card-body">
                    <label for="home-quick-title" class="flex items-center gap-2 mb-2 text-base font-semibold text-slate-900 dark:text-zink-50">
                        {% cache 'lucide:pen-line:home' ttl=31536000 %}<i data-lucide="pen-line" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                        {{ t('dashboard.home.quickDraft.label') }}
                    </label>
                    <?php $quickSubmit = $t('dashboard.home.quickDraft.submit'); ?>
                    <div class="flex gap-2">
                        <input id="home-quick-title" type="text" name="title" maxlength="255" autocomplete="off" dir="auto"
                            placeholder="<?= e($t('dashboard.home.quickDraft.placeholder')) ?>"
                            class="form-input flex-1 min-w-0 px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white text-slate-700 placeholder:text-slate-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 dark:placeholder:text-zink-300">
                        {% cmp="btn" type="submit" variant="blue" label="{$quickSubmit}" addClass="shrink-0" %}
                    </div>
                </div>
            </form>
            <?php } ?>

            <?php if ($home['attention'] !== null) { ?>
            <section class="card" aria-labelledby="home-attention">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h2 id="home-attention" class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-zink-50">
                            {% cache 'lucide:alert-circle' ttl=31536000 %}<i data-lucide="alert-circle" class="size-4 text-amber-500"></i>{% endcache %}
                            {{ t('dashboard.sections.needsAttention') }}
                        </h2>
                        <?php if ($home['attentionHasDrafts']) { ?>
                        <a href="<?= e(lurl('/dashboard/post?status=draft')) ?>" class="<?= $linkClass ?>">{{ t('dashboard.overview.viewAll') }}</a>
                        <?php } ?>
                    </div>
                    <?php if ($home['attention'] === []) { ?>
                    <p class="py-4 text-sm text-center text-slate-500 dark:text-zink-300">{{ t('dashboard.home.attention.clear') }}</p>
                    <?php } else {
                        $linkList($home['attention']);
                    } ?>
                </div>
            </section>
            <?php } ?>

            <?php if ($home['topRead'] !== null) { ?>
            <section class="card" aria-labelledby="home-topread">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-3">
                        <h2 id="home-topread" class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-zink-50">
                            {% cache 'lucide:trending-up:home' ttl=31536000 %}<i data-lucide="trending-up" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                            {{ t('dashboard.home.mostRead.title') }}
                        </h2>
                        <a href="<?= e(lurl('/dashboard/blog/'.$blogId.'/insights?range=7d')) ?>" class="<?= $linkClass ?>">{{ t('analytics.home.open') }}</a>
                    </div>
                    <?php $linkList([$home['topRead']]); ?>
                </div>
            </section>
            <?php } ?>

            <?php if ($home['rhythm'] !== null) {
                $rhythm = $home['rhythm']; ?>
            <section class="card" aria-labelledby="home-rhythm">
                <div class="card-body">
                    <div class="flex items-baseline justify-between">
                        <h2 id="home-rhythm" class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-zink-50">
                            {% cache 'lucide:calendar-days:home' ttl=31536000 %}<i data-lucide="calendar-days" class="size-4 text-custom-500" aria-hidden="true"></i>{% endcache %}
                            {{ t('dashboard.home.rhythm.title') }}
                        </h2>
                        <span class="text-xs text-slate-500 dark:text-zink-300">{{ t('dashboard.home.rhythm.span') }}</span>
                    </div>
                    <div class="flex items-end h-12 gap-1 mt-4" role="img" aria-label="<?= e($rhythm['total']) ?>">
                        <?php foreach ($rhythm['bars'] as $bar) { ?>
                        <span class="flex-1 rounded-sm <?= $bar['current'] ? 'bg-custom-500' : 'bg-slate-200 dark:bg-zink-500' ?>"
                            style="height: <?= (int) $bar['height'] ?>%; min-height: 3px"
                            title="<?= e($bar['title']) ?>" aria-hidden="true"></span>
                        <?php } ?>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-zink-300"><?= e($rhythm['total']) ?></p>
                    <?php if ($rhythm['next'] !== null) { ?>
                    <p class="pt-3 mt-3 text-xs border-t text-slate-500 dark:text-zink-300 border-slate-100 dark:border-zink-600" dir="auto">
                        <a href="<?= e(lurl($rhythm['next']['href'])) ?>" class="hover:text-custom-500"><?= e($rhythm['next']['text']) ?></a>
                    </p>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>
        </div>

    </div>
</div>
{% endblock %}
{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/tooltip.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/next-steps.js')) ?>"></script>
{% endblock %}
