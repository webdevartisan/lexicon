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
    app(\App\Services\LocaleRegistry::class),
    $scope
);

$isPost = $scope === 'post';
$showSettings = $canConfigure && !$isPost;
$pagePath = $isPost ? $basePath.'/posts/'.(int) $post['id'] : $basePath;
$pageUrl = static fn (array $query): string => lurl($pagePath).'?'.http_build_query($query);
$metrics = $report['metrics'];
$breakdowns = $report['breakdowns'];
$previous = $range->previous();
$none = $t('traffic.metrics.none');

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

$cards = [
    ['views', 'traffic.metrics.views', 'traffic.metrics.viewsHint', 'number'],
    ['visitors', 'traffic.metrics.visitors', $scope === 'author' ? 'traffic.metrics.visitorsPerPostHint' : 'traffic.metrics.visitorsHint', 'number'],
    ['avg_read_seconds', 'traffic.metrics.avgRead', 'traffic.metrics.avgReadHint', 'duration'],
    ['read_ratio', 'traffic.metrics.readRatio', 'traffic.metrics.readRatioHint', 'percent'],
];

if ($scope === 'blog') {
    $cards[] = ['bounce_rate', 'traffic.metrics.bounceRate', 'traffic.metrics.bounceRateHint', 'percent'];
}

$cards[] = ['returning_share', 'traffic.metrics.returning', $filters === [] ? 'traffic.metrics.returningHint' : 'traffic.filters.noReturning', 'percent'];
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
    $notices[] = ['danger', $t('traffic.status.trackingOff')];
}
if (!$aggregationEnabled) {
    $notices[] = ['warning', $t('traffic.status.aggregationOff')];
} elseif ($delayed) {
    $notices[] = ['warning', $aggregatedAt === null
        ? $t('traffic.status.neverAggregated')
        : $t('traffic.status.delayed', ['time' => substr((string) $aggregatedAt, 0, 16)])];
}
if ($range->rejected) {
    $notices[] = ['info', $t('traffic.range.rejected')];
}
if ($filtersDropped) {
    $notices[] = ['info', $t('traffic.filters.dropped', ['days' => $present->number($rawRetentionDays)])];
}

$goalKeys = match ($scope) {
    'blog' => ['subscribe', 'comment', 'like', 'save', 'signup'],
    default => ['comment', 'like', 'save'],
};
$hourlyHint = $t('traffic.hourly.hint');
$missingHint = $t('traffic.missing.hint');
$rightNowHint = $t('traffic.rightNowHint');
$settingsLabel = $t('traffic.settings.button');
$linksLabel = $t('traffic.links.button');
$downloadLabel = $t('traffic.breakdowns.export');
$clickCounts = ['clicks' => $t('traffic.clicks.clicks')];
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
      {% cmp="btn" variant="slate" icon="link" label="{$linksLabel}" dataModalTarget="trafficLinkModal" %}
      <?php if ($showSettings) { ?>
      {% cmp="btn" variant="slate" icon="sliders-horizontal" label="{$settingsLabel}" dataModalTarget="trafficSettingsModal" %}
      <?php } ?>
    </div>
  </div>

  <div class="-mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-300">
    <span>
      <?= e($t('traffic.range.comparedTo', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())])) ?>
      ·
      <?= e($collectingSince !== null
          ? $t('traffic.status.collectingSince', ['date' => $present->date($collectingSince)])
          : $t('traffic.status.notCollecting')) ?>
    </span>
    <?php if ($rightNow !== null) { ?>
    {% include "partials/dashboard/traffic/_recent.lex.php" %}
    <?php } ?>
  </div>

  {% include "partials/dashboard/traffic/_filters.lex.php" %}

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as [$key, $labelKey, $hintKey, $format]) {
        $metric = $metrics[$key] ?? null;
        $cardLabel = $t($labelKey);
        $cardHint = $t($hintKey);
        $cardValue = $format === 'source' ? $present->topSource($breakdowns) : $present->metric($format, $metric['value'] ?? null);
        $cardChange = $metric === null ? null : $present->change(
            $metric['change'],
            (string) $present->metric($format, $metric['previous']),
            $previousPeriod,
            in_array($key, $lowerIsBetter, true)
        ); ?>
    {% include "partials/dashboard/traffic/_metric_card.lex.php" %}
    <?php } ?>
  </div>

  {% include "partials/dashboard/traffic/_chart.lex.php" %}

  <?php if ($isPost) { ?>
  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {% include "partials/dashboard/traffic/_performance.lex.php" %}
    {% include "partials/dashboard/traffic/_languages.lex.php" %}
  </div>
  <?php } else { ?>
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
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('traffic.topPosts.shape')) ?></th>
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
                $readTime = $engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null;
                $postPerformance = $performance['posts'][(int) $row['post_id']] ?? null; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0 hover:bg-slate-50 dark:hover:bg-zink-600/50">
              <td class="px-3 py-2">
                <?php if ($row['title'] === null) { ?>
                <span class="text-slate-400 dark:text-zink-400"><?= e($t('traffic.topPosts.deleted')) ?></span>
                <?php } else { ?>
                <a href="<?= e(lurl($basePath.'/posts/'.(int) $row['post_id']).'?'.http_build_query($range->query())) ?>"
                   class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"><?= e((string) $row['title']) ?></a>
                <?php } ?>
              </td>
              <td class="px-3 py-2">
                <?php if (($postPerformance['label'] ?? null) !== null) { ?>
                {% include "partials/dashboard/traffic/_shape_badge.lex.php" %}
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

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {% include "partials/dashboard/traffic/_goals.lex.php" %}
    {% include "partials/dashboard/traffic/_scroll.lex.php" %}
  </div>

  {% include "partials/dashboard/traffic/_hourly.lex.php" %}

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
    <?php
    $order = [
        'source', 'lexicon', 'channel', 'entry', 'exit', 'next', 'page', 'category', 'tag', 'author',
        'country', 'device', 'browser', 'os', 'locale', 'utm_campaign', 'utm_source', 'utm_medium',
    ];
    foreach ($order as $dimension) {
        // A post page shows its languages as a table of their own.
        if (!array_key_exists($dimension, $breakdowns) || ($isPost && $dimension === 'locale')) {
            continue;
        }
        $rows = $breakdowns[$dimension];
        $breakdownCounts = null;
        $breakdownEmpty = null;
        $exportable = true; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php }

    $breakdownHeadings = ['outbound' => $t('traffic.clicks.outbound'), 'download' => $t('traffic.clicks.download')];
    foreach (['outbound' => $report['outbound'], 'download' => $report['downloads']] as $dimension => $rows) {
        $rows = array_map(static fn (array $row): array => ['value' => $row['value'], 'clicks' => $row['clicks']], $rows);
        $breakdownCounts = $clickCounts;
        $breakdownEmpty = $t('traffic.clicks.empty');
        $exportable = false; ?>
    {% include "partials/dashboard/traffic/_breakdown.lex.php" %}
    <?php }
    $breakdownHeadings = [];
    $breakdownCounts = null;
    $breakdownEmpty = null; ?>
  </div>

  <?php if ($scope === 'blog') { ?>
  {% include "partials/dashboard/traffic/_missing.lex.php" %}
  <?php } ?>

  <details class="card mb-0">
    <summary class="card-body cursor-pointer text-sm font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.about.title')) ?></summary>
    <p class="px-5 pb-5 -mt-2 text-sm leading-relaxed text-slate-600 dark:text-zink-200"><?= e($t('traffic.about.counted')) ?></p>
  </details>
</div>

<?php
ob_start(); ?>
{% include "partials/dashboard/traffic/_link_builder.lex.php" %}
<?php
$linkBody = ob_get_clean();
$linkTitle = $t('traffic.links.title');
$linkClose = $t('traffic.links.close');
?>
{% cmp="modal" id="trafficLinkModal" title="{$linkTitle}" icon="link" size="lg" body="{$linkBody}" cancelText="{$linkClose}" noConfirm="true" %}

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
<script src="/cp-assets/js/traffic-links.js"></script>
<script src="/cp-assets/js/modal.js"></script>
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
