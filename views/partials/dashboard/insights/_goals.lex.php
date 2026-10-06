<?php
/**
 * How often each goal was reached. The Goals page adds the rate per visit,
 * counted from the first day visits were kept; Overview links there instead.
 */
$goalRates = $report['goalRates'] ?? null;
$goalsMore ??= null;
?>
<section class="card mb-0" aria-labelledby="insights-goals">
  <div class="card-body">
    <div class="flex items-center justify-between gap-2">
      <h2 id="insights-goals" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.goals.title')) ?></h2>
      <?php if ($goalsMore !== null) { ?>
      <a href="<?= e($goalsMore) ?>" data-insights-page-link class="text-xs font-medium text-custom-500 hover:underline"><?= e($t('analytics.pages.seeAll')) ?></a>
      <?php } ?>
    </div>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">
      <?= e($t('analytics.goals.hint')) ?>
      <?php if ($goalRates !== null && $goalRates['from'] !== $range->fromDate()) { ?>
      <?= e($t('analytics.goals.ratesFrom', ['date' => $present->date($goalRates['from'])])) ?>
      <?php } ?>
      <?php if ($filters !== []) { ?><?= e($t('analytics.goals.unfiltered')) ?><?php } ?>
    </p>
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
      <?php foreach ($goalKeys as $goal) {
          $reached = (int) ($report['goals'][$goal] ?? 0);
          $rate = $goalRates === null ? null : $present->percent($goalRates['rates'][$goal] ?? null); ?>
      <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600/40">
        <dt class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.goals.'.$goal)) ?></dt>
        <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-zink-50"><?= e($present->number($reached)) ?></dd>
        <?php if ($rate !== null) { ?>
        <dd class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.goals.ratePerVisit', ['rate' => $rate])) ?></dd>
        <?php } ?>
      </div>
      <?php } ?>
    </dl>
  </div>
</section>
