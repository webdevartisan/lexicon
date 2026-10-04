{% extends "back.lex.php" %}

{% block title %}Traffic{% endblock %}
{% block subtitle %}Reading across every blog: how much, where readers come from, and which blogs and posts they read.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
<?php
$present = new \App\Presenters\TrafficPresenter(
    \App\Services\LocaleState::get()->chromeLocale,
    $t,
    app(\App\Services\LocaleRegistry::class)
);

$pagePath = $basePath;
$pageUrl = static fn (array $query): string => lurl($pagePath).'?'.http_build_query($query);
$exportUrl = static fn (string $dimension): string => lurl($basePath.'/export').'?'.http_build_query($range->query() + ['dimension' => $dimension]);
$metrics = $report['metrics'];
$breakdowns = $report['breakdowns'];
$previous = $range->previous();
$hasViews = $metrics['views']['value'] > 0;
$none = $t('traffic.metrics.none');
$canConfigure = \App\Gate::allows('manageSettings', \App\Resources\SystemResource::class, auth()->user() ?? []);

// Label and hint are plain English here, like the rest of the control panel.
$cards = [
    ['views', $t('traffic.metrics.views'), 'Pages opened by readers on any blog. Reloading the same page within 30 minutes counts once.', 'number'],
    ['visitors', $t('traffic.metrics.visitors'), 'Different people each day, counted once however many blogs they read, then added up over the days.', 'number'],
    ['active_blogs', 'Blogs read', 'Blogs that had at least one view in this period.', 'number'],
    ['counting_blogs', 'Blogs counting', 'Blogs whose owner has turned visit counting on, read or not. Only these can show up here.', 'number'],
    ['avg_read_seconds', $t('traffic.metrics.avgRead'), 'Average time a reader spent with a page open and in front of them, over every blog.', 'duration'],
    ['read_ratio', $t('traffic.metrics.readRatio'), 'Share of views where the reader stayed at least 30 seconds.', 'percent'],
    ['bounce_rate', $t('traffic.metrics.bounceRate'), 'Share of visits to a blog that opened one page and left within 10 seconds.', 'percent'],
    ['returning_share', $t('traffic.metrics.returning'), 'Of the readers we can recognise (signed in, or who allowed the analytics cookie), the share who had read that blog before.', 'percent'],
];
$metrics['counting_blogs'] = ['value' => $report['countingBlogs'], 'previous' => null, 'change' => null];

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

$chartSeries = array_map(static fn (array $day): array => [
    'label' => $present->date($day['date']),
    'views' => $day['views'],
    'visitors' => $day['visitors'],
    'blogs' => $day['blogs'],
], $report['series']);

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

  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-300">
      <span>
        <?= e($t('traffic.range.comparedTo', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())])) ?>
        ·
        <?= e($collectingSince !== null
            ? $t('traffic.status.collectingSince', ['date' => $present->date($collectingSince)])
            : 'No visits counted on any blog yet.') ?>
        · Days are UTC.
      </span>
      <span class="inline-flex items-center gap-1.5 cursor-help" tabindex="0"
            data-tooltip data-tooltip-content="Different visitors on any blog in the last 30 minutes." data-tooltip-placement="bottom">
        <span class="inline-block size-2 rounded-full <?= $rightNow > 0 ? 'bg-green-500' : 'bg-slate-400' ?>" aria-hidden="true"></span>
        <?= e($t('traffic.rightNow', ['count' => $present->number($rightNow)])) ?>
      </span>
    </p>

    <div class="flex flex-wrap items-center gap-2">
      {% include "partials/dashboard/traffic/_range_picker.lex.php" %}
      <?php if ($canConfigure) { ?>
      {% cmp="btn" variant="slate" icon="sliders-horizontal" label="{$settingsLabel}" href="{$settingsHref}" %}
      <?php } ?>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as [$key, $label, $hint, $format]) {
        $metric = $metrics[$key];
        $display = $present->metric($format, $metric['value']);
        $change = $present->change(
            $metric['change'],
            (string) $present->metric($format, $metric['previous']),
            $previousPeriod,
            in_array($key, $lowerIsBetter, true)
        );
        $changeIcon = $changeIcons[$change['direction']] ?? 'minus';
        $changeClass = $changeTones[$change['tone']]; ?>
    <div class="card mb-0">
      <div class="card-body">
        <div class="flex items-start justify-between gap-2">
          <h2 class="text-sm font-medium text-slate-500 dark:text-zink-300"><?= e($label) ?></h2>
          <button type="button" class="shrink-0 -m-1 p-1 rounded text-slate-400 hover:text-custom-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 dark:text-zink-400"
                  data-tooltip data-tooltip-content="<?= e($hint) ?>" data-tooltip-placement="top"
                  aria-label="<?= e($t('traffic.metrics.about', ['metric' => $label])) ?>">
            {% cache 'lucide:help-circle:traffic-card' ttl=31536000 %}<i data-lucide="help-circle" class="size-4" aria-hidden="true"></i>{% endcache %}
          </button>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-2">
          <p class="text-2xl font-semibold text-slate-800 dark:text-zink-50 truncate"><?= e($display ?? $none) ?></p>
          <?php if ($key !== 'counting_blogs' && $change['direction'] !== 'none') { ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium rounded cursor-help <?= $changeClass ?>" tabindex="0"
                data-tooltip data-tooltip-content="<?= e($change['label']) ?>" data-tooltip-placement="bottom">
            {% cache 'lucide:traffic-change:' . $changeIcon ttl=31536000 %}<i data-lucide="<?= e($changeIcon) ?>" class="size-3.5" aria-hidden="true"></i>{% endcache %}
            <span aria-hidden="true"><?= e($change['short']) ?></span>
            <span class="sr-only"><?= e($change['label']) ?></span>
          </span>
          <?php } ?>
        </div>
      </div>
    </div>
    <?php } ?>
  </div>

  <div class="card mb-0">
    <div class="card-body">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.chart.title')) ?></h2>
        <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-zink-300" aria-hidden="true">
          <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 bg-custom-500"></span><?= e($t('traffic.chart.views')) ?></span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 bg-sky-400"></span><?= e($t('traffic.chart.visitors')) ?></span>
        </div>
      </div>

      <?php if ($hasViews) { ?>
      <div class="relative h-72" data-traffic-chart>
        <canvas role="img" aria-label="<?= e($t('traffic.chart.title')) ?>"></canvas>
        <span class="hidden text-custom-500" data-chart-color="views"></span>
        <span class="hidden text-sky-400" data-chart-color="visitors"></span>
        <script type="application/json" data-chart-series><?= json_encode([
            'labels' => ['views' => $t('traffic.chart.views'), 'visitors' => $t('traffic.chart.visitors')],
            'points' => $chartSeries,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
      </div>
      <?php } else { ?>
      <p class="py-10 text-center text-sm text-slate-500 dark:text-zink-300">No views on any blog in this period.</p>
      <?php } ?>

      <details class="mt-4">
        <summary class="text-sm cursor-pointer text-custom-500 hover:underline"><?= e($t('traffic.chart.showTable')) ?></summary>
        <div class="mt-3 overflow-x-auto max-h-96">
          <table class="w-full text-sm">
            <caption class="sr-only"><?= e($t('traffic.chart.title')) ?></caption>
            <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
              <tr class="border-b border-slate-200 dark:border-zink-500">
                <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('traffic.chart.date')) ?></th>
                <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.chart.views')) ?></th>
                <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.chart.visitors')) ?></th>
                <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Blogs read</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_reverse($chartSeries) as $point) { ?>
              <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
                <th scope="row" class="px-3 py-1.5 font-normal ltr:text-left rtl:text-right"><?= e($point['label']) ?></th>
                <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['views'])) ?></td>
                <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['visitors'])) ?></td>
                <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['blogs'])) ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </details>
    </div>
  </div>

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
                  <a href="/admin/blogs/<?= (int) $row['blog_id'] ?>/show" class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"><?= e((string) $row['blog_name']) ?></a>
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

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
    <?php
    $order = ['source', 'channel', 'country', 'device', 'browser', 'os', 'locale', 'utm_campaign', 'utm_source', 'utm_medium'];
    foreach ($order as $dimension) {
        $rows = $breakdowns[$dimension] ?? []; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php } ?>
  </div>

  <details class="card mb-0">
    <summary class="card-body cursor-pointer text-sm font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.about.title')) ?></summary>
    <div class="px-5 pb-5 -mt-2 flex flex-col gap-2 text-sm leading-relaxed text-slate-600 dark:text-zink-200">
      <p>Only blogs whose owner turned counting on are included. Crawlers, link previews and browsers that ask not to be tracked are left out, and the blog's own team is left out unless the owner chose otherwise.</p>
      <p>Views, visitors and the daily chart use UTC days. The other numbers add up each blog's own days, which follow the blog's timezone, so they can differ slightly at the start and end of the range. Visitors in the breakdowns are counted per blog, so someone who read two blogs counts twice there.</p>
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
