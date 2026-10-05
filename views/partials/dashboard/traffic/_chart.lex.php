<?php
$interval = $report['interval'];
$points = $report['series'];
$withBlogs = $scope === 'site';
$comparedLabel = $range->compare === \App\ValueObjects\TrafficRange::COMPARE_YEAR
    ? $t('traffic.chart.lastYear')
    : $t('traffic.chart.before');

// Each marker joins the point whose day, week or month it falls in.
$pointMarkers = [];
foreach ($report['markers'] as $marker) {
    foreach ($points as $i => $point) {
        if ($marker['day'] >= $point['date'] && $marker['day'] <= $point['to']) {
            $pointMarkers[$i][] = $marker['kind'] === 'post'
                ? $t('traffic.chart.published', ['title' => $marker['title']])
                : $t('traffic.chart.themeChanged');
            break;
        }
    }
}

$chartPoints = array_map(static fn (array $point): array => [
    'label' => $present->point($point['date'], $interval),
    'views' => $point['views'],
    'visitors' => $point['visitors'],
    'previous_views' => $point['previous_views'],
    'blogs' => $point['blogs'] ?? null,
], $points);

$chartMarkers = [];
foreach ($pointMarkers as $index => $labels) {
    $chartMarkers[] = ['index' => $index, 'labels' => $labels];
}
$chartHasViews = $report['metrics']['views']['value'] > 0;
?>
<div class="card mb-0">
  <div class="card-body">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h2 class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.chart.title')) ?></h2>
      <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-zink-300" aria-hidden="true">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 bg-custom-500"></span><?= e($t('traffic.chart.views')) ?></span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 bg-sky-400"></span><?= e($t('traffic.chart.visitors')) ?></span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 border-t border-dashed border-custom-500"></span><?= e($t('traffic.chart.viewsThen', ['period' => $comparedLabel])) ?></span>
        <?php if ($chartMarkers !== []) { ?>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2 rounded-full bg-amber-500"></span><?= e($t('traffic.chart.markers')) ?></span>
        <?php } ?>
      </div>
    </div>

    <?php if ($chartHasViews) { ?>
    <div class="relative h-72" data-traffic-chart>
      <canvas role="img" aria-label="<?= e($t('traffic.chart.title')) ?>"></canvas>
      <span class="hidden text-custom-500" data-chart-color="views"></span>
      <span class="hidden text-sky-400" data-chart-color="visitors"></span>
      <span class="hidden text-amber-500" data-chart-color="marker"></span>
      <script type="application/json" data-chart-series><?= json_encode([
          'labels' => ['views' => $t('traffic.chart.views'), 'visitors' => $t('traffic.chart.visitors')],
          'previous' => ['views' => $t('traffic.chart.viewsThen', ['period' => $comparedLabel])],
          'points' => $chartPoints,
          'markers' => $chartMarkers,
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    </div>
    <?php if ($interval !== 'day') { ?>
    <p class="mt-2 text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.chart.per'.ucfirst($interval))) ?></p>
    <?php } ?>
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
              <?php if ($withBlogs) { ?>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.chart.blogs')) ?></th>
              <?php } ?>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.chart.viewsThen', ['period' => $comparedLabel])) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('traffic.chart.markers')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_reverse($chartPoints, true) as $i => $point) { ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
              <th scope="row" class="px-3 py-1.5 font-normal ltr:text-left rtl:text-right whitespace-nowrap"><?= e($point['label']) ?></th>
              <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['views'])) ?></td>
              <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($point['visitors'])) ?></td>
              <?php if ($withBlogs) { ?>
              <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $point['blogs'])) ?></td>
              <?php } ?>
              <td class="px-3 py-1.5 ltr:text-right rtl:text-left tabular-nums text-slate-500 dark:text-zink-300"><?= e($present->number($point['previous_views'])) ?></td>
              <td class="px-3 py-1.5 text-xs text-slate-500 dark:text-zink-300" dir="auto"><?= e(implode(' · ', $pointMarkers[$i] ?? [])) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>
</div>
