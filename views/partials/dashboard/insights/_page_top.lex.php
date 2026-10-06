<?php
/**
 * The top of every Insights page, a blog's, a post's and the control panel's: notices,
 * the range picker and toolbar, the comparison line, right now, and the filter chips.
 * It opens the page column that _page_bottom closes.
 * Also sets up what the cards below share: $present, $pageUrl, $filterHref,
 * $metrics, $breakdowns and the change badge styles.
 */
$present = new \App\Presenters\AnalyticsPresenter(
    \App\Services\LocaleState::get()->chromeLocale,
    $t,
    app(\App\Services\LocaleRegistry::class),
    $scope
);

$isPost = $scope === 'post';
$isAdmin = $scope === 'site' || $scope === 'platform';
// The control panel's scope rides along on every link the page makes.
$extraQuery ??= [];
$pageUrl = static fn (array $query): string => lurl($pagePath).'?'.http_build_query($query + $extraQuery);
$metrics = $report['metrics'] ?? [];
$breakdowns = $report['breakdowns'] ?? [];
$previous = $range->previous();
$none = $t('analytics.metrics.none');
$previousPeriod = $t('analytics.range.span', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())]);

// Another Insights page with the same range, comparison and filters.
$insightsLink = static fn (string $page): string => lurl(\App\Services\Analytics\InsightsPages::path($basePath, $page))
    .'?'.http_build_query($range->query() + ($filters === [] ? [] : ['f' => $filters]) + $extraQuery);

// A post's own Insights page. The control panel opens it in the post's blog.
$postInsightsHref = static fn (array $row): string => lurl($isAdmin
    ? '/dashboard/blog/'.(int) $row['blog_id'].'/insights/posts/'.(int) $row['post_id']
    : $basePath.'/posts/'.(int) $row['post_id']).'?'.http_build_query($range->query());

// Narrowing: a row adds its filter, a chip takes one away.
$filterHref = static fn (string $dimension, string $value): string => $pageUrl($range->query() + ['f' => [$dimension => $value] + $filters]);
$filterWithout = static function (?string $dimension) use ($pageUrl, $range, $filters): string {
    $kept = $dimension === null ? [] : array_diff_key($filters, [$dimension => true]);

    return $pageUrl($range->query() + ($kept === [] ? [] : ['f' => $kept]));
};
$filterNames = [];
foreach ($filters as $filterDimension => $filterValue) {
    foreach ($breakdowns[$filterDimension] ?? [] as $row) {
        if ((string) $row['value'] === $filterValue && isset($row['name'])) {
            $filterNames[$filterDimension] = $row['name'];
        }
    }
}

$lowerIsBetter = [];
$changeIcons = ['up' => 'trending-up', 'down' => 'trending-down', 'flat' => 'minus'];
$changeTones = [
    'good' => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400',
    'bad' => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
    'neutral' => 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-200',
];

$noticeTones = [
    'danger' => 'border-red-200 bg-red-50 text-red-700 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300',
    'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:bg-yellow-500/10 dark:border-yellow-500/30 dark:text-yellow-300',
    'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-300',
];
$notices = [];
if (!$trackingEnabled) {
    $notices[] = ['danger', $t('analytics.status.trackingOff')];
}
if (!$aggregationEnabled) {
    $notices[] = ['warning', $t('analytics.status.aggregationOff')];
} elseif ($delayed) {
    $notices[] = ['warning', $aggregatedAt === null
        ? $t('analytics.status.neverAggregated')
        : $t('analytics.status.delayed', ['time' => substr((string) $aggregatedAt, 0, 16)])];
}
if ($range->rejected) {
    $notices[] = ['info', $t('analytics.range.rejected')];
}
if ($filtersDropped) {
    $notices[] = ['info', $t('analytics.filters.dropped', ['days' => $present->number($rawRetentionDays)])];
}

$showSettings = $canConfigure && $page === 'overview';
$showLinks = !$isAdmin && ($isPost || $page === 'acquisition');
$rightNowHint = $isAdmin ? 'Different visitors anywhere on the site in the last 30 minutes.' : $t('analytics.rightNowHint');
$settingsLabel = $t('analytics.settings.button');
$linksLabel = $t('analytics.links.button');
$downloadLabel = $t('analytics.breakdowns.export');
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto flex flex-col gap-5">

  <?php foreach ($notices as [$tone, $message]) { ?>
  <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm border rounded-md <?= $noticeTones[$tone] ?>" role="status">
    <span><?= e($message) ?></span>
  </div>
  <?php } ?>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
      <?php if ($isPost) { ?>
      <a href="<?= e($insightsLink('content')) ?>" class="inline-flex items-center gap-1 text-custom-500 hover:underline">
        {% cache 'lucide:arrow-left:insights-back' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
        <?= e($t('analytics.allAnalytics')) ?>
      </a>
        <?php if (($post['status'] ?? '') === 'published') { ?>
      <a href="<?= e(lurl('/blog/'.rawurlencode((string) $blog['blog_slug']).'/'.rawurlencode((string) $post['slug']))) ?>"
         target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-slate-500 hover:text-custom-500 dark:text-zink-300">
        {% cache 'lucide:external-link:insights-open' ttl=31536000 %}<i data-lucide="external-link" class="size-4" aria-hidden="true"></i>{% endcache %}
        <?= e($t('analytics.openPost')) ?>
      </a>
        <?php } ?>
      <?php } elseif ($scope === 'author') { ?>
      <p class="text-slate-500 dark:text-zink-300"><?= e($t('analytics.authorNotice')) ?></p>
      <?php } elseif ($scopeSwitch ?? false) { ?>
      <nav class="flex flex-wrap gap-2" aria-label="What to count">
        <?php foreach (['site' => 'Whole site', 'platform' => 'Platform pages'] as $switchScope => $switchLabel) {
            $switchActive = $switchScope === $scope;
            $switchQuery = $range->query() + ($switchScope === 'platform' ? ['scope' => 'platform'] : []); ?>
        <a href="<?= e(lurl($pagePath).'?'.http_build_query($switchQuery)) ?>"
           class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 <?= $switchActive
               ? 'bg-custom-500 border-custom-500 text-white'
               : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200' ?>"
           <?= $switchActive ? 'aria-current="page"' : '' ?>><?= e($switchLabel) ?></a>
        <?php } ?>
      </nav>
      <?php } ?>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      {% include "partials/dashboard/insights/_range_picker.lex.php" %}
      <?php if ($showLinks) { ?>
      {% cmp="btn" variant="slate" icon="link" label="{$linksLabel}" dataModalTarget="analyticsLinkModal" %}
      <?php } ?>
      <?php if ($showSettings && $isAdmin) { ?>
      {% cmp="btn" variant="slate" icon="sliders-horizontal" label="{$settingsLabel}" href="/admin/settings#analytics" %}
      <?php } elseif ($showSettings) { ?>
      {% cmp="btn" variant="slate" icon="sliders-horizontal" label="{$settingsLabel}" dataModalTarget="analyticsSettingsModal" %}
      <?php } ?>
    </div>
  </div>

  <div class="-mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-300">
    <span>
      <?= e($t('analytics.range.comparedTo', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())])) ?>
      ·
      <?= e($collectingSince !== null
          ? $t('analytics.status.collectingSince', ['date' => $present->date($collectingSince)])
          : $t('analytics.status.notCollecting')) ?>
      <?php if ($isAdmin) { ?>· Days are UTC.<?php } ?>
    </span>
    <?php if (($rightNow ?? null) !== null) { ?>
    {% include "partials/dashboard/insights/_recent.lex.php" %}
    <?php } ?>
  </div>

  {% include "partials/dashboard/insights/_filters.lex.php" %}
