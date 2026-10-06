{% extends "back.lex.php" %}

{% block title %}Sign-ups{% endblock %}
{% block subtitle %}How many visitors sign up, where those visits came from, and how many new accounts go on to read and write.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
<?php
$present = new \App\Presenters\AnalyticsPresenter(
    \App\Services\LocaleState::get()->chromeLocale,
    $t,
    app(\App\Services\LocaleRegistry::class),
    $scope
);

$pagePath = $basePath;
$pageUrl = static fn (array $query): string => lurl($pagePath).'?'.http_build_query($query);
$metrics = $report['metrics'];
$funnel = $report['funnel'];
$breakdowns = $report['breakdowns'];
$previous = $range->previous();
$none = $t('analytics.metrics.none');
$canConfigure = \App\Gate::allows('manageSettings', \App\Resources\SystemResource::class, auth()->user() ?? []);
$firstDays = "their first {$windowDays} days";

// Label and hint are plain English here, like the rest of the control panel.
$cards = [
    ['signups', 'Sign-ups', 'New accounts created in this period.', 'number'],
    ['signup_rate', 'Sign-up rate', 'Sign-ups for every visitor to the site in this period.', 'percent'],
    ['active_readers', 'Became active readers', "Of the accounts at least {$windowDays} days old, the share who commented, liked, saved a post or followed a blog in {$firstDays}.", 'percent'],
    ['started_blog', 'Started a blog', "Of the accounts at least {$windowDays} days old, the share who started a blog in {$firstDays}.", 'percent'],
    ['published_post', 'Published a post', "Of the accounts at least {$windowDays} days old, the share who published a post in {$firstDays}.", 'percent'],
];

$steps = [
    ['Visitors', $funnel['visitors'], null],
    ['Signed up', $funnel['accounts'], [$funnel['accounts'], $funnel['visitors'], 'of visitors']],
];
$firstDaySteps = [
    ['Became active readers', $funnel['active_readers']],
    ['Started a blog', $funnel['started_blog']],
    ['Published a post', $funnel['published_post']],
];
$waiting = $funnel['accounts'] - $funnel['judged'];

$previousPeriod = $t('analytics.range.span', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())]);
$lowerIsBetter = [];
$changeIcons = ['up' => 'trending-up', 'down' => 'trending-down', 'flat' => 'minus'];
$changeTones = [
    'good' => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400',
    'bad' => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
    'neutral' => 'bg-slate-100 text-slate-500 dark:bg-zink-600 dark:text-zink-200',
];

$noticeTones = [
    'danger' => 'border-red-200 bg-red-50 text-red-700 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300',
    'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-300',
];
$notices = [];
if (!$trackingEnabled) {
    $notices[] = ['danger', 'Visit counting is switched off, so new sign-ups are counted without where they came from.'];
}
if ($range->rejected) {
    $notices[] = ['info', $t('analytics.range.rejected')];
}

$chartSeries = array_map(static fn (array $day): array => [
    'label' => $present->date($day['date']),
    'signups' => $day['signups'],
], $report['series']);
$hasSignups = $funnel['accounts'] > 0;

$breakdownCounts = ['signups' => 'Sign-ups'];
$breakdownHeadings = [
    'source' => 'Sources',
    'channel' => 'Channels',
    'came_from' => 'Signed up from',
    'utm_campaign' => 'Campaigns',
];
$settingsLabel = 'Insights settings';
$settingsHref = '/admin/settings#analytics';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto flex flex-col gap-5">

  <?php foreach ($notices as [$tone, $message]) { ?>
  <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm border rounded-md <?= $noticeTones[$tone] ?>" role="status">
    <span><?= e($message) ?></span>
    <?php if ($canConfigure && $tone === 'danger') { ?>
    <a href="<?= e($settingsHref) ?>" class="font-medium underline hover:no-underline"><?= e($settingsLabel) ?></a>
    <?php } ?>
  </div>
  <?php } ?>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-xs text-slate-500 dark:text-zink-300">
      <?= e($t('analytics.range.comparedTo', ['from' => $present->date($previous->fromDate()), 'to' => $present->date($previous->toDate())])) ?>
      · Days are UTC.
    </p>
    {% include "partials/dashboard/insights/_range_picker.lex.php" %}
  </div>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <?php foreach ($cards as [$key, $cardLabel, $cardHint, $format]) {
        $metric = $metrics[$key];
        $cardValue = $present->metric($format, $metric['value']);
        $cardChange = $present->change(
            $metric['change'],
            (string) $present->metric($format, $metric['previous']),
            $previousPeriod,
            in_array($key, $lowerIsBetter, true)
        ); ?>
    {% include "partials/dashboard/insights/_metric_card.lex.php" %}
    <?php } ?>
  </div>

  <section class="card mb-0" aria-labelledby="signups-funnel">
    <div class="card-body">
      <h2 id="signups-funnel" class="mb-4 text-15 font-semibold text-slate-800 dark:text-zink-50">From visit to writer</h2>
      <ol class="flex flex-col gap-3">
        <?php foreach ($steps as [$stepLabel, $count, $share]) {
            $ratio = $share === null ? null : \App\Services\Analytics\AnalyticsReportService::ratio($share[0], $share[1]); ?>
        <li class="grid grid-cols-[minmax(8rem,12rem)_1fr_auto] items-center gap-3 text-sm">
          <span class="text-slate-700 dark:text-zink-100"><?= e($stepLabel) ?></span>
          <span class="h-2 rounded-full bg-slate-100 dark:bg-zink-600" aria-hidden="true">
            <span class="block h-2 rounded-full bg-custom-500" style="width: <?= $share === null ? 100 : max(1, (int) round(($ratio ?? 0) * 100)) ?>%"></span>
          </span>
          <span class="tabular-nums text-slate-700 dark:text-zink-100">
            <?= e($present->number($count)) ?>
            <?php if ($share !== null) { ?>
            <span class="text-slate-500 dark:text-zink-300">· <?= e(($present->percent($ratio) ?? $none).' '.$share[2]) ?></span>
            <?php } ?>
          </span>
        </li>
        <?php } ?>
      </ol>

      <h3 class="mt-6 mb-1 text-sm font-semibold text-slate-800 dark:text-zink-50">In <?= e($firstDays) ?></h3>
      <p class="mb-3 text-xs text-slate-500 dark:text-zink-300">
        Of the <?= e($present->number($funnel['judged'])) ?> new accounts at least <?= (int) $windowDays ?> days old.
        <?php if ($waiting > 0) { ?>
        The other <?= e($present->number($waiting)) ?> join in once their <?= (int) $windowDays ?> days are up.
        <?php } ?>
      </p>
      <ol class="flex flex-col gap-3">
        <?php foreach ($firstDaySteps as [$stepLabel, $count]) {
            $ratio = \App\Services\Analytics\AnalyticsReportService::ratio($count, $funnel['judged']); ?>
        <li class="grid grid-cols-[minmax(8rem,12rem)_1fr_auto] items-center gap-3 text-sm">
          <span class="text-slate-700 dark:text-zink-100"><?= e($stepLabel) ?></span>
          <span class="h-2 rounded-full bg-slate-100 dark:bg-zink-600" aria-hidden="true">
            <span class="block h-2 rounded-full bg-custom-500" style="width: <?= max(1, (int) round(($ratio ?? 0) * 100)) ?>%"></span>
          </span>
          <span class="tabular-nums text-slate-700 dark:text-zink-100">
            <?= e($present->number($count)) ?>
            <span class="text-slate-500 dark:text-zink-300">· <?= e($present->percent($ratio) ?? $none) ?></span>
          </span>
        </li>
        <?php } ?>
      </ol>
    </div>
  </section>

  <div class="card mb-0">
    <div class="card-body">
      <h2 class="mb-4 text-15 font-semibold text-slate-800 dark:text-zink-50">Sign-ups per day</h2>

      <?php if ($hasSignups) { ?>
      <div class="relative h-64" data-insights-chart>
        <canvas role="img" aria-label="Sign-ups per day"></canvas>
        <span class="hidden text-custom-500" data-chart-color="signups"></span>
        <script type="application/json" data-chart-series><?= json_encode([
            'labels' => ['signups' => 'Sign-ups'],
            'points' => $chartSeries,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
      </div>
      <?php } else { ?>
      <p class="py-10 text-center text-sm text-slate-500 dark:text-zink-300">No sign-ups in this period.</p>
      <?php } ?>

      <details class="mt-4">
        <summary class="text-sm cursor-pointer text-custom-500 hover:underline"><?= e($t('analytics.chart.showTable')) ?></summary>
        <div class="mt-3 overflow-x-auto max-h-96">
          <table class="w-full text-sm">
            <caption class="sr-only">Sign-ups per day</caption>
            <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
              <tr class="border-b border-slate-200 dark:border-zink-500">
                <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.chart.date')) ?></th>
                <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Sign-ups</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_reverse($chartSeries) as $point) { ?>
              <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
                <th scope="row" class="px-3 py-1.5 font-normal ltr:text-left rtl:text-right"><?= e($point['label']) ?></th>
                <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['signups'])) ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </details>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <?php foreach (array_keys($breakdownHeadings) as $dimension) {
        $rows = $breakdowns[$dimension]; ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
    <?php } ?>
  </div>

  <details class="card mb-0">
    <summary class="card-body cursor-pointer text-sm font-semibold text-slate-800 dark:text-zink-50">What is counted</summary>
    <div class="px-5 pb-5 -mt-2 flex flex-col gap-2 text-sm leading-relaxed text-slate-600 dark:text-zink-200">
      <p>Sign-ups are the accounts created in this period. Visitors are the site's visitors on the Insights overview.</p>
      <p>Where a sign-up came from is read from the visit it happened in: how that visit began, and the last page read before signing up. Visits that weren't counted, such as from browsers that ask not to be tracked, have no sources, so these lists can add up to fewer than the sign-ups.</p>
      <p>Active readers commented, liked a post or comment, saved a post or followed a blog. Each account is followed for its first <?= (int) $windowDays ?> days from the moment it was created.</p>
    </div>
  </details>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/chart.js/chart.umd.min.js"></script>
<script src="/cp-assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="/cp-assets/js/insights-chart.js"></script>
<script src="/cp-assets/js/insights-range.js"></script>
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
