{% extends "back.lex.php" %}

{% block title %}Traffic{% endblock %}
{% block subtitle %}<?= e($scope === 'site'
    ? 'Reading across the whole website: how much, where readers come from, and which blogs and posts they read.'
    : 'The platform\'s own pages: the home page, Discover, the guides, profiles, sign-in and sign-up.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
<?php
$present = new \App\Presenters\TrafficPresenter(
    \App\Services\LocaleState::get()->chromeLocale,
    $t,
    app(\App\Services\LocaleRegistry::class),
    $scope
);

$pagePath = $basePath;
$pageUrl = static fn (array $query): string => lurl($pagePath).'?'.http_build_query($query);
$filterHref = static fn (string $dimension, string $value): string => $pageUrl($range->query() + ['f' => [$dimension => $value] + $filters]);
$filterWithout = static function (?string $dimension) use ($pageUrl, $range, $filters): string {
    $kept = $dimension === null ? [] : array_diff_key($filters, [$dimension => true]);

    return $pageUrl($range->query() + ($kept === [] ? [] : ['f' => $kept]));
};
$filterNames = [];
$exportUrl = static fn (string $dimension): string => lurl($basePath.'/export').'?'.http_build_query($range->query() + ['dimension' => $dimension]);
$metrics = $report['metrics'];
$breakdowns = $report['breakdowns'];
$previous = $range->previous();
$hasViews = $metrics['views']['value'] > 0;
$none = $t('traffic.metrics.none');
$canConfigure = \App\Gate::allows('manageSettings', \App\Resources\SystemResource::class, auth()->user() ?? []);
$isOverview = $scope === 'site';
$tabs = [
    ['/admin/traffic', 'Overview'],
    ['/admin/traffic/platform', 'Platform pages'],
];

// Label and hint are plain English here, like the rest of the control panel.
$cards = $isOverview ? [
    ['views', $t('traffic.metrics.views'), 'Pages opened anywhere on the site: its own pages and every blog. Reloading the same page within 30 minutes counts once.', 'number'],
    ['visitors', $t('traffic.metrics.visitors'), 'Different people each day, counted once however many pages and blogs they read, then added up over the days.', 'number'],
    ['active_blogs', 'Blogs read', 'Blogs that had at least one view in this period.', 'number'],
    ['avg_read_seconds', $t('traffic.metrics.avgRead'), 'Average time a reader spent with a page open and in front of them, over the whole site.', 'duration'],
    ['read_ratio', $t('traffic.metrics.readRatio'), 'Share of views where the reader stayed at least 30 seconds.', 'percent'],
    ['bounce_rate', $t('traffic.metrics.bounceRate'), 'Share of visitors who opened one page on the site that day and left within 10 seconds.', 'percent'],
    ['returning_share', $t('traffic.metrics.returning'), 'Of the readers we can recognise (those who allowed analytics), the share who had visited the site on an earlier day in the last 30 days.', 'percent'],
    ['avg_scroll', $t('traffic.metrics.scroll'), 'How far down the page readers got, on average.', 'scroll'],
] : [
    ['views', $t('traffic.metrics.views'), 'Pages of the platform itself opened by readers. Reloading the same page within 30 minutes counts once.', 'number'],
    ['visitors', $t('traffic.metrics.visitors'), 'Different people each day on the platform\'s own pages, then added up over the days.', 'number'],
    ['avg_read_seconds', $t('traffic.metrics.avgRead'), 'Average time a reader spent with one of these pages open and in front of them.', 'duration'],
    ['read_ratio', $t('traffic.metrics.readRatio'), 'Share of views where the reader stayed at least 30 seconds.', 'percent'],
    ['returning_share', $t('traffic.metrics.returning'), 'Of the readers we can recognise (those who allowed analytics), the share who had opened these pages on an earlier day in the last 30 days.', 'percent'],
];

$previousPeriod = $t('traffic.range.span', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())]);
$lowerIsBetter = ['bounce_rate'];
$changeIcons = ['up' => 'trending-up', 'down' => 'trending-down', 'flat' => 'minus'];
$changeTones = [
    'good' => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400',
    'bad' => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
    'neutral' => 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-200',
];
$statusBadge = [
    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
    'suspended' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
];

$noticeTones = [
    'danger' => 'border-red-200 bg-red-50 text-red-700 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300',
    'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:bg-yellow-500/10 dark:border-yellow-500/30 dark:text-yellow-300',
    'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-300',
];
$notices = [];
if (!$trackingEnabled) {
    $notices[] = ['danger', 'Visit counting is switched off for the whole platform, so nothing new is being recorded.'];
}
if (!$aggregationEnabled) {
    $notices[] = ['warning', 'Updating the traffic numbers is paused, so this page may be out of date.'];
} elseif ($delayed) {
    $notices[] = ['warning', $aggregatedAt === null
        ? 'The traffic numbers have not been worked out yet. Check that the scheduler is running.'
        : 'These numbers are behind. They were last updated '.substr((string) $aggregatedAt, 0, 16).' UTC and normally update every five minutes.'];
}
if ($range->rejected) {
    $notices[] = ['info', $t('traffic.range.rejected')];
}
if ($filtersDropped) {
    $notices[] = ['info', 'Narrowing only works for the last '.$rawRetentionDays.' days, so the whole range is shown without it.'];
}

$hourlyHint = 'Views by day of the week and hour, in UTC. Darker squares had more views.';
$missingHint = 'Readers who arrived at an address on the platform that isn\'t a page, and the sites that sent them.';
$rightNowHint = 'Different visitors anywhere on the site in the last 30 minutes.';
$goalKeys = ['subscribe', 'comment', 'like', 'save', 'signup'];
$clickCounts = ['clicks' => $t('traffic.clicks.clicks')];
$outcomeLabels = [
    'recorded' => ['Counted', 'Real readers\' page views, stored.'],
    'duplicate' => ['Repeat views', 'The same page again within 30 minutes, counted once.'],
    'bot' => ['Crawlers', 'User agents on the crawler list, built in or added in Settings.'],
    'hosting' => ['Server networks', 'Views from hosting providers\' networks, which people don\'t read from. Network data: IP geolocation by DB-IP.'],
    'opted_out' => ['Asked not to be counted', 'Browsers sending Global Privacy Control or Do Not Track.'],
    'member' => ['Teams and staff', 'Administrators, people acting as someone else, and blog teams on their own blogs.'],
    'prefetch' => ['Prefetches', 'Pages loaded ahead of time that may never be seen.'],
    'excluded_path' => ['Left out by owners', 'Paths a blog owner chose not to count.'],
    'unknown_page' => ['Not a public page', 'Drafts, previews and paths that match no page.'],
    'not_found' => ['Missing pages', 'Readers who reached an address that isn\'t a page.'],
    'spam' => ['Referrer spam', 'Views claiming to come from a known spam site.'],
    'disabled' => ['Counting off', 'Beacons that arrived while counting was switched off.'],
    'cross_site' => ['Other sites', 'Requests that didn\'t come from a page on this site.'],
    'too_large' => ['Too large', 'Request bodies over the size limit.'],
    'invalid' => ['Malformed', 'Bodies that weren\'t a valid page view.'],
];

$settingsLabel = 'Traffic settings';
$settingsHref = '/admin/settings#traffic';
$downloadLabel = $t('traffic.breakdowns.export');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto flex flex-col gap-5">

  <?php foreach ($notices as [$tone, $message]) { ?>
  <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm border rounded-md <?= $noticeTones[$tone] ?>" role="status">
    <span><?= e($message) ?></span>
    <?php if ($canConfigure) { ?>
    <a href="<?= e($settingsHref) ?>" class="font-medium underline hover:no-underline"><?= e($settingsLabel) ?></a>
    <?php } ?>
  </div>
  <?php } ?>

  <nav class="flex flex-wrap gap-2" aria-label="Traffic views">
    <?php foreach ($tabs as [$tabPath, $tabLabel]) {
        $isActive = $tabPath === $basePath;
        $classes = 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 '
            .($isActive
                ? 'bg-custom-500 border-custom-500 text-white'
                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200'); ?>
    <a href="<?= e(lurl($tabPath).'?'.http_build_query($range->query())) ?>" class="<?= $classes ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($tabLabel) ?></a>
    <?php } ?>
  </nav>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-300">
      <span>
        <?= e($t('traffic.range.comparedTo', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())])) ?>
        ·
        <?= e($collectingSince !== null
            ? $t('traffic.status.collectingSince', ['date' => $present->date($collectingSince)])
            : 'No visits counted yet.') ?>
        · Days are UTC.
      </span>
    </p>
    <?php if ($rightNow !== null) { ?>
    <div class="text-xs text-slate-500 dark:text-zink-300">
      {% include "partials/dashboard/traffic/_recent.lex.php" %}
    </div>
    <?php } ?>

    <div class="flex flex-wrap items-center gap-2">
      {% include "partials/dashboard/traffic/_range_picker.lex.php" %}
      <?php if ($canConfigure) { ?>
      {% cmp="btn" variant="slate" icon="sliders-horizontal" label="{$settingsLabel}" href="{$settingsHref}" %}
      <?php } ?>
    </div>
  </div>

  {% include "partials/dashboard/traffic/_filters.lex.php" %}

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as [$key, $cardLabel, $cardHint, $format]) {
        $metric = $metrics[$key];
        $cardValue = $present->metric($format, $metric['value']);
        $cardChange = $present->change(
            $metric['change'],
            (string) $present->metric($format, $metric['previous']),
            $previousPeriod,
            in_array($key, $lowerIsBetter, true)
        ); ?>
    {% include "partials/dashboard/traffic/_metric_card.lex.php" %}
    <?php } ?>
  </div>

  {% include "partials/dashboard/traffic/_chart.lex.php" %}

  <?php if ($isOverview) { ?>
  <div class="card mb-0">
    <div class="card-body">
      <div class="flex items-center justify-between gap-2 mb-3">
        <h2 class="text-15 font-semibold text-slate-800 dark:text-zink-50">Top blogs</h2>
        <?php if ($report['topBlogs'] !== []) { ?>
        <a href="<?= e($exportUrl('blogs')) ?>"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300"
           data-tooltip data-tooltip-content="Every blog with views in this period, as a CSV file." data-tooltip-placement="top">
          {% cache 'lucide:download:traffic-export' ttl=31536000 %}<i data-lucide="download" class="size-3.5" aria-hidden="true"></i>{% endcache %}
          <?= e($downloadLabel) ?>
        </a>
        <?php } ?>
      </div>
      <?php if ($report['topBlogs'] === []) { ?>
      <p class="py-6 text-center text-sm text-slate-500 dark:text-zink-300">No blog had views in this period.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold">Blog</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.views')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><span class="sr-only">Change in views</span></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.visitors')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.readRatio')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.avgRead')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.bounceRate')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['topBlogs'] as $row) {
                $views = (int) $row['views'];
                $visitors = (int) $row['visitors'];
                $engaged = (int) $row['engaged_views'];
                $status = (string) ($row['status'] ?? '');
                $change = $present->change(
                    $row['change'],
                    $present->number((int) $row['previous_views']),
                    $previousPeriod
                );
                $changeIcon = $changeIcons[$change['direction']] ?? 'minus'; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0 hover:bg-slate-50 dark:hover:bg-zink-600/50">
              <td class="px-3 py-2">
                <?php if ($row['blog_name'] === null) { ?>
                <span class="text-slate-400 dark:text-zink-400">Deleted blog</span>
                <?php } else { ?>
                <span class="inline-flex flex-wrap items-center gap-2">
                  <a href="<?= e(lurl('/dashboard/blog/'.(int) $row['blog_id'].'/analytics/traffic').'?'.http_build_query($range->query())) ?>"
                     class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"
                     title="Open this blog's Traffic page"><?= e((string) $row['blog_name']) ?></a>
                  <?php if (isset($statusBadge[$status])) { ?>
                  <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full border <?= $statusBadge[$status] ?>"><?= e(ucfirst($status)) ?></span>
                  <?php } ?>
                </span>
                <?php } ?>
              </td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left">
                <?php if ($change['direction'] === 'none') { ?>
                <span class="text-xs text-slate-400 dark:text-zink-400 cursor-help" tabindex="0"
                      data-tooltip data-tooltip-content="No views in the previous period to compare with." data-tooltip-placement="bottom">New</span>
                <?php } else { ?>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium rounded cursor-help <?= $changeTones[$change['tone']] ?>" tabindex="0"
                      data-tooltip data-tooltip-content="<?= e($change['label']) ?>" data-tooltip-placement="bottom">
                  {% cache 'lucide:traffic-change:' . $changeIcon ttl=31536000 %}<i data-lucide="<?= e($changeIcon) ?>" class="size-3.5" aria-hidden="true"></i>{% endcache %}
                  <span aria-hidden="true"><?= e($change['short']) ?></span>
                  <span class="sr-only"><?= e($change['label']) ?></span>
                </span>
                <?php } ?>
              </td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($visitors)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $row['read_views'] / $views : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($visitors > 0 ? (int) $row['bounces'] / $visitors : null) ?? $none) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </div>

  <div class="card mb-0">
    <div class="card-body">
      <div class="flex items-center justify-between gap-2 mb-3">
        <h2 class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.topPosts.title')) ?></h2>
        <?php if ($report['topPosts'] !== []) { ?>
        <a href="<?= e($exportUrl('posts')) ?>"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300"
           data-tooltip data-tooltip-content="Every post on any blog with views in this period, as a CSV file." data-tooltip-placement="top">
          {% cache 'lucide:download:traffic-export' ttl=31536000 %}<i data-lucide="download" class="size-3.5" aria-hidden="true"></i>{% endcache %}
          <?= e($downloadLabel) ?>
        </a>
        <?php } ?>
      </div>
      <?php if ($report['topPosts'] === []) { ?>
      <p class="py-6 text-center text-sm text-slate-500 dark:text-zink-300">No post had views in this period.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('traffic.topPosts.post')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold">Blog</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.views')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.visitors')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.readRatio')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.avgRead')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['topPosts'] as $row) {
                $views = (int) $row['views'];
                $engaged = (int) $row['engaged_views'];
                $published = $row['title'] !== null && $row['status'] === 'published' && $row['blog_slug'] !== null; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0 hover:bg-slate-50 dark:hover:bg-zink-600/50">
              <td class="px-3 py-2">
                <?php if ($row['title'] === null) { ?>
                <span class="text-slate-400 dark:text-zink-400"><?= e($t('traffic.topPosts.deleted')) ?></span>
                <?php } elseif ($published) { ?>
                <a href="<?= e(lurl('/blog/'.rawurlencode((string) $row['blog_slug']).'/'.rawurlencode((string) $row['slug']))) ?>"
                   target="_blank" rel="noopener" class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"><?= e((string) $row['title']) ?></a>
                <?php } else { ?>
                <span class="text-slate-800 dark:text-zink-100" dir="auto"><?= e((string) $row['title']) ?></span>
                <?php } ?>
              </td>
              <td class="px-3 py-2">
                <?php if ($row['blog_name'] !== null) { ?>
                <a href="/admin/blogs/<?= (int) $row['blog_id'] ?>/show" class="text-slate-500 hover:text-custom-500 dark:text-zink-300" dir="auto"><?= e((string) $row['blog_name']) ?></a>
                <?php } ?>
              </td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $row['visitors'])) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $row['read_views'] / $views : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null) ?? $none) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </div>
  <?php } ?>

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <?php if ($isOverview) { ?>
    {% include "partials/dashboard/traffic/_goals.lex.php" %}
    <?php } ?>
    {% include "partials/dashboard/traffic/_scroll.lex.php" %}
  </div>

  {% include "partials/dashboard/traffic/_hourly.lex.php" %}

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
    <?php
    $order = ['page', 'entry', 'exit', 'source', 'lexicon', 'channel', 'country', 'device', 'browser', 'os', 'locale', 'utm_campaign', 'utm_source', 'utm_medium'];
    foreach ($order as $dimension) {
        if (!array_key_exists($dimension, $breakdowns)) {
            continue;
        }
        $rows = $breakdowns[$dimension];
        $breakdownCounts = null;
        $breakdownEmpty = null;
        $exportable = true; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php }

    if ($isOverview) {
        $breakdownHeadings = ['outbound' => 'Links to other sites', 'download' => 'Downloads'];
        foreach (['outbound' => $report['outbound'], 'download' => $report['downloads']] as $dimension => $rows) {
            $rows = array_map(static fn (array $row): array => ['value' => $row['value'], 'clicks' => $row['clicks']], $rows);
            $breakdownCounts = $clickCounts;
            $breakdownEmpty = $t('traffic.clicks.empty');
            $exportable = false; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php   }
        $breakdownHeadings = [];
        $breakdownCounts = null;
        $breakdownEmpty = null;
    }

    if ($searchTerms !== null) {
        $dimension = 'search';
        $breakdownHeadings = ['search' => 'Searched on Discover'];
        $rows = array_map(static fn (array $row): array => ['value' => $row['value'], 'searches' => $row['searches'], 'visitors' => $row['visitors']], $searchTerms);
        $breakdownCounts = ['searches' => 'Searches', 'visitors' => $t('traffic.breakdowns.visitors')];
        $breakdownEmpty = 'No search was made by at least 3 different visitors in this range.';
        $exportable = false; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php
        $breakdownHeadings = [];
        $breakdownCounts = null;
        $breakdownEmpty = null;
    } ?>
  </div>

  <?php if (!$isOverview) { ?>
  {% include "partials/dashboard/traffic/_missing.lex.php" %}
  <?php } ?>

  <?php if ($isOverview) {
      $refused = array_sum($outcomes) - ($outcomes['recorded'] ?? 0); ?>
  <section class="card mb-0" aria-labelledby="traffic-outcomes">
    <div class="card-body">
      <h2 id="traffic-outcomes" class="text-15 font-semibold text-slate-800 dark:text-zink-50">What the counter let through</h2>
      <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">
        Every page view beacon in this range and what happened to it. Script-like views were stored but are left out of every total:
        <?= e($present->number($scriptViews)) ?>, from visitors who opened <?= e($present->number($scriptMinViews)) ?> or more pages in a day without once closing or hiding one.
      </p>
      <?php if ($outcomes === []) { ?>
      <p class="py-4 text-sm text-slate-500 dark:text-zink-300">No beacons in this range.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold">Outcome</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Beacons</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php $allBeacons = array_sum($outcomes);
            foreach ($outcomes as $outcome => $count) {
                [$outcomeLabel, $outcomeHint] = $outcomeLabels[$outcome] ?? [$outcome, '']; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
              <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right">
                <span class="text-slate-800 dark:text-zink-100"><?= e($outcomeLabel) ?></span>
                <span class="block text-xs text-slate-500 dark:text-zink-300"><?= e($outcomeHint) ?></span>
              </th>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($count)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e((string) $present->percent($count / max(1, $allBeacons))) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <p class="mt-2 text-xs text-slate-500 dark:text-zink-300"><?= e($present->number($refused)) ?> of <?= e($present->number($allBeacons)) ?> beacons were not counted.</p>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

  <details class="card mb-0">
    <summary class="card-body cursor-pointer text-sm font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.about.title')) ?></summary>
    <div class="px-5 pb-5 -mt-2 flex flex-col gap-2 text-sm leading-relaxed text-slate-600 dark:text-zink-200">
      <?php if ($isOverview) { ?>
      <p>Every public page counts: the platform's own pages and every blog. Crawlers, link previews, browsers that ask not to be tracked and administrators are left out, and a blog's own team is left out of that blog unless its owner chose otherwise.</p>
      <p>The cards, the chart and the breakdowns use UTC days and count a visitor once across the whole site, however many blogs they read. Top blogs and Top posts show each blog's own numbers, in that blog's timezone, the same as its owner sees them.</p>
      <?php } else { ?>
      <p>The home page, Discover, the guides, the about and legal pages, contact, sign-in, sign-up and profiles, which are counted together as one page.</p>
      <p>Days are UTC.</p>
      <?php } ?>
    </div>
  </details>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/chart.js/chart.umd.min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="/cp-assets/js/traffic-chart.js"></script>
<script src="/cp-assets/js/traffic-range.js"></script>
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
