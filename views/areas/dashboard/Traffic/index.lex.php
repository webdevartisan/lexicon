{% extends "back.lex.php" %}

{% block title %}<?= e($scope === 'post' ? (string) ($post['title'] ?? '') : (string) $blog['blog_name']) ?> · <?= e($t('traffic.pageTitle')) ?>{% endblock %}
{% block subtitle %}<?= e($t($scope === 'post' ? 'traffic.postSubtitle' : 'traffic.pageSubtitle')) ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
<?php
$present = new \App\Presenters\TrafficPresenter(
    \App\Services\LocaleState::get()->chromeLocale,
    $t,
    app(\App\Services\LocaleRegistry::class)
);

$isPost = $scope === 'post';
$showSettings = $canConfigure && !$isPost;
$pagePath = $isPost ? $basePath.'/posts/'.(int) $post['id'] : $basePath;
$pageUrl = static fn (array $query): string => lurl($pagePath).'?'.http_build_query($query);
$metrics = $report['metrics'];
$breakdowns = $report['breakdowns'];
$previous = $range->previous();
$hasViews = $metrics['views']['value'] > 0;
$none = $t('traffic.metrics.none');

$cards = [
    ['views', 'traffic.metrics.views', 'traffic.metrics.viewsHint', 'number'],
    ['visitors', 'traffic.metrics.visitors', $scope === 'author' ? 'traffic.metrics.visitorsPerPostHint' : 'traffic.metrics.visitorsHint', 'number'],
    ['avg_read_seconds', 'traffic.metrics.avgRead', 'traffic.metrics.avgReadHint', 'duration'],
    ['read_ratio', 'traffic.metrics.readRatio', 'traffic.metrics.readRatioHint', 'percent'],
];

if ($scope === 'blog') {
    $cards[] = ['bounce_rate', 'traffic.metrics.bounceRate', 'traffic.metrics.bounceRateHint', 'percent'];
}

$cards[] = ['returning_share', 'traffic.metrics.returning', 'traffic.metrics.returningHint', 'percent'];
$cards[] = ['avg_scroll', 'traffic.metrics.scroll', 'traffic.metrics.scrollHint', 'scroll'];
$cards[] = ['top_source', 'traffic.metrics.topSource', 'traffic.metrics.topSourceHint', 'source'];

$previousPeriod = $t('traffic.range.span', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())]);
$lowerIsBetter = ['bounce_rate'];
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
    $notices[] = ['danger', $t('traffic.status.trackingOff'), false];
} elseif (!$blogCounting) {
    $notices[] = ['info', $t($canConfigure ? 'traffic.status.blogOffOwner' : 'traffic.status.blogOff'), $showSettings];
}
if (!$aggregationEnabled) {
    $notices[] = ['warning', $t('traffic.status.aggregationOff'), false];
} elseif ($delayed) {
    $notices[] = ['warning', $aggregatedAt === null
        ? $t('traffic.status.neverAggregated')
        : $t('traffic.status.delayed', ['time' => substr((string) $aggregatedAt, 0, 16)]), false];
}
if ($range->rejected) {
    $notices[] = ['info', $t('traffic.range.rejected'), false];
}

$chartSeries = array_map(static fn (array $day): array => [
    'label' => $present->date($day['date']),
    'views' => $day['views'],
    'visitors' => $day['visitors'],
], $report['series']);

$turnOnLabel = $t('traffic.status.turnOn');
$settingsLabel = $t('traffic.settings.button');
$downloadLabel = $t('traffic.breakdowns.export');
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto flex flex-col gap-5">

  <?php foreach ($notices as [$tone, $message, $withTurnOn]) { ?>
  <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm border rounded-md <?= $noticeTones[$tone] ?>" role="status">
    <span><?= e($message) ?></span>
    <?php if ($withTurnOn) { ?>
    {% cmp="btn" variant="blue" icon="power" label="{$turnOnLabel}" dataModalTarget="trafficSettingsModal" %}
    <?php } ?>
  </div>
  <?php } ?>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
      <?php if ($isPost) { ?>
      <a href="<?= e(lurl($basePath).'?'.http_build_query($range->query())) ?>" class="inline-flex items-center gap-1 text-custom-500 hover:underline">
        {% cache 'lucide:arrow-left:traffic-back' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
        <?= e($t('traffic.allTraffic')) ?>
      </a>
        <?php if (($post['status'] ?? '') === 'published') { ?>
      <a href="<?= e(lurl('/blog/'.rawurlencode((string) $blog['blog_slug']).'/'.rawurlencode((string) $post['slug']))) ?>"
         target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-slate-500 hover:text-custom-500 dark:text-zink-300">
        {% cache 'lucide:external-link:traffic-open' ttl=31536000 %}<i data-lucide="external-link" class="size-4" aria-hidden="true"></i>{% endcache %}
        <?= e($t('traffic.openPost')) ?>
      </a>
        <?php } ?>
      <?php } elseif ($scope === 'author') { ?>
      <p class="text-slate-500 dark:text-zink-300"><?= e($t('traffic.authorNotice')) ?></p>
      <?php } ?>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      {% include "partials/dashboard/traffic/_range_picker.lex.php" %}
      <?php if ($showSettings) { ?>
      {% cmp="btn" variant="slate" icon="sliders-horizontal" label="{$settingsLabel}" dataModalTarget="trafficSettingsModal" %}
      <?php } ?>
    </div>
  </div>

  <p class="-mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-300">
    <span>
      <?= e($t('traffic.range.comparedTo', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())])) ?>
      ·
      <?= e($collectingSince !== null
          ? $t('traffic.status.collectingSince', ['date' => $present->date($collectingSince)])
          : $t('traffic.status.notCollecting')) ?>
    </span>
    <?php if ($rightNow !== null) { ?>
    <span class="inline-flex items-center gap-1.5 cursor-help" tabindex="0"
          data-tooltip data-tooltip-content="<?= e($t('traffic.rightNowHint')) ?>" data-tooltip-placement="bottom">
      <span class="inline-block size-2 rounded-full <?= $rightNow > 0 ? 'bg-green-500' : 'bg-slate-400' ?>" aria-hidden="true"></span>
      <?= e($t('traffic.rightNow', ['count' => $present->number($rightNow)])) ?>
    </span>
    <?php } ?>
  </p>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as [$key, $labelKey, $hintKey, $format]) {
        $metric = $metrics[$key] ?? null;
        $display = $format === 'source' ? $present->topSource($breakdowns) : $present->metric($format, $metric['value'] ?? null);
        $change = $metric === null ? null : $present->change(
            $metric['change'],
            (string) $present->metric($format, $metric['previous']),
            $previousPeriod,
            in_array($key, $lowerIsBetter, true)
        );
        $changeIcon = $changeIcons[$change['direction'] ?? 'flat'] ?? 'minus';
        $changeClass = $changeTones[$change['tone'] ?? 'neutral']; ?>
    <div class="card mb-0">
      <div class="card-body">
        <div class="flex items-start justify-between gap-2">
          <h2 class="text-sm font-medium text-slate-500 dark:text-zink-300"><?= e($t($labelKey)) ?></h2>
          <button type="button" class="shrink-0 -m-1 p-1 rounded text-slate-400 hover:text-custom-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 dark:text-zink-400"
                  data-tooltip data-tooltip-content="<?= e($t($hintKey)) ?>" data-tooltip-placement="top"
                  aria-label="<?= e($t('traffic.metrics.about', ['metric' => $t($labelKey)])) ?>">
            {% cache 'lucide:help-circle:traffic-card' ttl=31536000 %}<i data-lucide="help-circle" class="size-4" aria-hidden="true"></i>{% endcache %}
          </button>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-2">
          <p class="text-2xl font-semibold text-slate-800 dark:text-zink-50 truncate" dir="auto"><?= e($display ?? $none) ?></p>
          <?php if ($change !== null && $change['direction'] !== 'none') { ?>
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
      <p class="py-10 text-center text-sm text-slate-500 dark:text-zink-300"><?= e($t('traffic.chart.empty')) ?></p>
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
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_reverse($chartSeries) as $point) { ?>
              <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
                <th scope="row" class="px-3 py-1.5 font-normal ltr:text-left rtl:text-right"><?= e($point['label']) ?></th>
                <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['views'])) ?></td>
                <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['visitors'])) ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </details>
    </div>
  </div>

  <?php if (!$isPost) { ?>
  <div class="card mb-0">
    <div class="card-body">
      <div class="flex items-center justify-between gap-2 mb-3">
        <h2 class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.topPosts.title')) ?></h2>
        <?php if ($report['topPosts'] !== []) { ?>
        <a href="<?= e(lurl($basePath.'/export').'?'.http_build_query($range->query() + ['dimension' => 'posts'])) ?>"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300"
           data-tooltip data-tooltip-content="<?= e($t('traffic.topPosts.exportHint')) ?>" data-tooltip-placement="top">
          {% cache 'lucide:download:traffic-export' ttl=31536000 %}<i data-lucide="download" class="size-3.5" aria-hidden="true"></i>{% endcache %}
          <?= e($downloadLabel) ?>
        </a>
        <?php } ?>
      </div>
      <?php if ($report['topPosts'] === []) { ?>
      <p class="py-6 text-center text-sm text-slate-500 dark:text-zink-300"><?= e($t('traffic.topPosts.empty')) ?></p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('traffic.topPosts.post')) ?></th>
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
                $readRatio = $views > 0 ? (int) $row['read_views'] / $views : null;
                $readTime = $engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0 hover:bg-slate-50 dark:hover:bg-zink-600/50">
              <td class="px-3 py-2">
                <?php if ($row['title'] === null) { ?>
                <span class="text-slate-400 dark:text-zink-400"><?= e($t('traffic.topPosts.deleted')) ?></span>
                <?php } else { ?>
                <a href="<?= e(lurl($basePath.'/posts/'.(int) $row['post_id']).'?'.http_build_query($range->query())) ?>"
                   class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"><?= e((string) $row['title']) ?></a>
                <?php } ?>
              </td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $row['visitors'])) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($readRatio) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($readTime) ?? $none) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </div>
  <?php } ?>

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
    <?php
    $order = ['source', 'channel', 'page', 'country', 'device', 'browser', 'os', 'locale', 'utm_campaign', 'utm_source', 'utm_medium'];
    foreach ($order as $dimension) {
        if (!array_key_exists($dimension, $breakdowns)) {
            continue;
        }
        $rows = $breakdowns[$dimension]; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php } ?>
  </div>

  <details class="card mb-0">
    <summary class="card-body cursor-pointer text-sm font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.about.title')) ?></summary>
    <p class="px-5 pb-5 -mt-2 text-sm leading-relaxed text-slate-600 dark:text-zink-200"><?= e($t('traffic.about.counted')) ?></p>
  </details>
</div>

<?php if ($showSettings) {
    ob_start(); ?>
{% include "partials/dashboard/traffic/_settings_form.lex.php" %}
<?php
    $settingsBody = ob_get_clean();
    $reopenSettings = !empty($errors['excluded_paths']);
    $settingsTitle = $t('traffic.settings.title');
    $settingsSave = $t('traffic.settings.save');
    $settingsCancel = $t('traffic.settings.cancel');
    ?>
{% cmp="modal" id="trafficSettingsModal" title="{$settingsTitle}" icon="sliders-horizontal" size="lg" body="{$settingsBody}" form="trafficSettingsForm" confirmText="{$settingsSave}" cancelText="{$settingsCancel}" openOnLoad="{$reopenSettings}" %}
<?php } ?>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/chart.js/chart.umd.min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="/cp-assets/js/traffic-chart.js"></script>
<script src="/cp-assets/js/traffic-range.js"></script>
<script src="/cp-assets/js/modal.js"></script>
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
