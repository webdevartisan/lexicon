<section class="card mb-0" aria-labelledby="traffic-languages">
  <div class="card-body">
    <h2 id="traffic-languages" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.breakdowns.translations')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.breakdowns.translationsHint')) ?></p>
    <?php if ($breakdowns['locale'] === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('traffic.breakdowns.empty')) ?></p>
    <?php } else { ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
          <tr class="border-b border-slate-200 dark:border-zink-500">
            <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('traffic.breakdowns.locale')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.views')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.visitors')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.readRatio')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('traffic.metrics.avgRead')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($breakdowns['locale'] as $row) { ?>
          <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
            <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right"><?= e($present->label('locale', (string) $row['value'])) ?></th>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($row['views'])) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($row['visitors'])) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent(\App\Services\Traffic\TrafficReportService::ratio($row['read_views'], $row['views'])) ?? $none) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration(\App\Services\Traffic\TrafficReportService::ratio($row['engaged_seconds'], $row['engaged_views'])) ?? $none) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</section>
