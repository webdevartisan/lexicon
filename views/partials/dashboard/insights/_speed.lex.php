<?php
/**
 * Page speed at the 75th percentile per kind of page, and the slowest pages,
 * each value coloured by Google's band.
 */
$speedColumns = ['lcp_ms' => 'lcp', 'inp_ms' => 'inp', 'cls' => 'cls', 'ttfb_ms' => 'ttfb'];
$bandTones = [
    'good' => 'bg-green-500',
    'improve' => 'bg-yellow-500',
    'poor' => 'bg-red-500',
];
$speedValue = static function (string $column, ?array $measured) use ($present, $bandTones, $t, $none): string {
    if ($measured === null) {
        return '<span class="text-slate-400 dark:text-zink-400">'.e($none).'</span>';
    }

    $band = \App\Services\Analytics\PageSpeed::band($column, $measured['p75']);

    return '<span class="inline-flex items-center gap-1.5"><span class="inline-block size-2 rounded-full '.$bandTones[$band].'" aria-hidden="true"></span>'
        .e($present->speed($column, $measured['p75']))
        .'<span class="sr-only"> ('.e($t('analytics.speed.bands.'.$band)).')</span></span>';
};
?>
<section class="card mb-0" aria-labelledby="insights-speed">
  <div class="card-body">
    <h2 id="insights-speed" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.speed.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.speed.hint', ['days' => $present->number(\App\Services\Analytics\PageSpeed::WINDOW_DAYS)])) ?></p>
    <?php if ($report['speed'] === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.speed.empty', ['days' => $present->number(\App\Services\Analytics\PageSpeed::WINDOW_DAYS)])) ?></p>
    <?php } else { ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
          <tr class="border-b border-slate-200 dark:border-zink-500">
            <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.speed.pageType')) ?></th>
            <?php foreach ($speedColumns as $speedKey) { ?>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left whitespace-nowrap">
              <span class="cursor-help" tabindex="0" data-tooltip data-tooltip-content="<?= e($t('analytics.speed.'.$speedKey.'Hint')) ?>" data-tooltip-placement="top"><?= e($t('analytics.speed.'.$speedKey)) ?></span>
            </th>
            <?php } ?>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.speed.samples')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($report['speed'] as $pageType => $measures) { ?>
          <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
            <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right text-slate-700 dark:text-zink-100"><?= e($t('analytics.pageTypes.'.$pageType)) ?></th>
            <?php foreach ($speedColumns as $column => $speedKey) { ?>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums whitespace-nowrap"><?= $speedValue($column, $measures[$column] ?? null) ?></td>
            <?php } ?>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums text-slate-500 dark:text-zink-300"><?= e($present->number(max(array_column($measures, 'samples')))) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-300">
      <?php foreach ($bandTones as $band => $tone) { ?>
      <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2 rounded-full <?= $tone ?>" aria-hidden="true"></span><?= e($t('analytics.speed.bands.'.$band)) ?></span>
      <?php } ?>
    </p>
    <?php } ?>
  </div>
</section>

<section class="card mb-0" aria-labelledby="insights-slowest">
  <div class="card-body">
    <h2 id="insights-slowest" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.speed.slowest')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.speed.slowestHint')) ?></p>
    <?php if ($report['slowest'] === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.speed.slowestEmpty')) ?></p>
    <?php } else { ?>
    <ul class="flex flex-col divide-y divide-slate-100 dark:divide-zink-600 text-sm">
      <?php foreach ($report['slowest'] as $slowPage) { ?>
      <li class="flex items-center justify-between gap-3 py-2">
        <code class="min-w-0 truncate text-slate-700 dark:text-zink-100" dir="ltr"><?= e($slowPage['path']) ?></code>
        <span class="shrink-0 tabular-nums"><?= $speedValue('lcp_ms', ['p75' => (float) $slowPage['lcp']]) ?></span>
      </li>
      <?php } ?>
    </ul>
    <?php } ?>
  </div>
</section>
