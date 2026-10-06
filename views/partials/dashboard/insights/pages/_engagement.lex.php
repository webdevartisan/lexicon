<?php
$cards = [
    ['engaged_visit_rate', 'analytics.metrics.engagedVisits', 'analytics.metrics.engagedVisitsHint', 'percent'],
    ['pages_per_visit', 'analytics.metrics.pagesPerVisit', 'analytics.metrics.pagesPerVisitHint', 'decimal'],
    ['avg_visit_seconds', 'analytics.metrics.visitLength', 'analytics.metrics.visitLengthHint', 'duration'],
    ['avg_read_seconds', 'analytics.metrics.avgRead', 'analytics.metrics.avgReadHint', 'duration'],
    ['read_ratio', 'analytics.metrics.readRatio', 'analytics.metrics.readRatioHint', 'percent'],
    ['read_to_end_ratio', 'analytics.metrics.readToEnd', 'analytics.metrics.readToEndHint', 'percent'],
];
?>
  {% include "partials/dashboard/insights/_metric_grid.lex.php" %}

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {% include "partials/dashboard/insights/_scroll.lex.php" %}
    {% include "partials/dashboard/insights/_reactions.lex.php" %}

    <section class="card mb-0" aria-labelledby="insights-read-by-channel">
      <div class="card-body">
        <h2 id="insights-read-by-channel" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.engagement.byChannel')) ?></h2>
        <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.engagement.byChannelHint')) ?></p>
        <?php if (($breakdowns['channel'] ?? []) === []) { ?>
        <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.breakdowns.empty')) ?></p>
        <?php } else { ?>
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($present->heading('channel')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.views')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.readRatio')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.avgRead')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($breakdowns['channel'] as $row) {
                $views = (int) $row['views'];
                $engaged = (int) $row['engaged_views']; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
              <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right text-slate-700 dark:text-zink-100"><?= e($present->label('channel', (string) $row['value'])) ?></th>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $row['read_views'] / $views : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null) ?? $none) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
        <?php } ?>
      </div>
    </section>
  </div>

  <?php $dimensions = ['next', 'related']; ?>
  {% include "partials/dashboard/insights/_breakdown_list.lex.php" %}
