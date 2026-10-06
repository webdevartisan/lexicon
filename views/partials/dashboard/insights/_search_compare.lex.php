<?php
/**
 * How readers who came from a search engine read, next to everyone: their share
 * of views, read rate and read time.
 */
$everyone = $report['everyone'];
$search = $report['search'] ?? ['views' => 0, 'read_views' => 0, 'engaged_views' => 0, 'engaged_seconds' => 0];
$compareRows = [
    [$t('analytics.seo.searchReaders'), $search, $present->percent(\App\Services\Analytics\AnalyticsReportService::ratio((int) $search['views'], (int) $everyone['views']))],
    [$t('analytics.seo.everyone'), $everyone, null],
];
?>
<section class="card mb-0" aria-labelledby="insights-search-compare">
  <div class="card-body">
    <h2 id="insights-search-compare" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.seo.compare')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.seo.compareHint')) ?></p>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
          <tr class="border-b border-slate-200 dark:border-zink-500">
            <th scope="col" class="px-3 py-2 font-semibold"><span class="sr-only"><?= e($t('analytics.seo.readers')) ?></span></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.views')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.seo.shareOfViews')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.readRatio')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.avgRead')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compareRows as [$compareLabel, $numbers, $share]) {
              $views = (int) $numbers['views'];
              $engaged = (int) $numbers['engaged_views']; ?>
          <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
            <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right text-slate-700 dark:text-zink-100"><?= e($compareLabel) ?></th>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($share ?? $present->percent(1.0)) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $numbers['read_views'] / $views : null) ?? $none) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($engaged > 0 ? (int) $numbers['engaged_seconds'] / $engaged : null) ?? $none) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
