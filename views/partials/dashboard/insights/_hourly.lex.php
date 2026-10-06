<?php
$grid = $report['hourly'];
$weekdays = $present->weekdays();
$peak = 1;
foreach ($grid as $hours) {
    $peak = max($peak, ...array_values($hours));
}
?>
<section class="card mb-0" aria-labelledby="traffic-hourly">
  <div class="card-body">
    <h2 id="traffic-hourly" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.hourly.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($hourlyHint) ?></p>

    <?php if ($grid === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.breakdowns.empty')) ?></p>
    <?php } else { ?>
    <div class="overflow-x-auto">
      <table class="text-[11px] tabular-nums border-separate [border-spacing:2px]">
        <caption class="sr-only"><?= e($t('analytics.hourly.title')) ?></caption>
        <thead>
          <tr>
            <td></td>
            <?php for ($hour = 0; $hour < 24; $hour++) { ?>
            <th scope="col" class="w-5 font-normal text-slate-400 dark:text-zink-400">
              <span aria-hidden="true"><?= $hour % 6 === 0 ? e($present->hour($hour, 'HH')) : '' ?></span>
              <span class="sr-only"><?= e($present->hour($hour)) ?></span>
            </th>
            <?php } ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($weekdays as $dayOfWeek => $dayName) { ?>
          <tr>
            <th scope="row" class="ltr:pr-2 rtl:pl-2 font-normal ltr:text-left rtl:text-right text-slate-500 dark:text-zink-300 whitespace-nowrap"><?= e($dayName) ?></th>
            <?php for ($hour = 0; $hour < 24; $hour++) {
                $cellViews = $grid[$dayOfWeek][$hour] ?? 0;
                $shade = $cellViews === 0 ? 0.06 : 0.15 + 0.85 * $cellViews / $peak; ?>
            <td class="relative w-5 h-5 p-0" title="<?= e($t('analytics.hourly.cell', ['day' => $dayName, 'hour' => $present->hour($hour), 'views' => $present->number($cellViews)])) ?>">
              <span class="absolute inset-0 rounded-sm bg-custom-500" style="opacity: <?= round($shade, 2) ?>" aria-hidden="true"></span>
              <span class="sr-only"><?= e($present->number($cellViews)) ?></span>
            </td>
            <?php } ?>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</section>
