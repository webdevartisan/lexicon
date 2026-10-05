<?php
$goalVisitors = (int) $metrics['visitors']['value'];
?>
<section class="card mb-0" aria-labelledby="traffic-goals">
  <div class="card-body">
    <h2 id="traffic-goals" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.goals.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">
      <?= e($t('traffic.goals.hint')) ?>
      <?php if ($filters !== []) { ?><?= e($t('traffic.goals.unfiltered')) ?><?php } ?>
    </p>
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
      <?php foreach ($goalKeys as $goal) {
          $reached = (int) ($report['goals'][$goal] ?? 0);
          $rate = $present->percent(\App\Services\Traffic\TrafficReportService::ratio($reached, $goalVisitors)); ?>
      <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600/40">
        <dt class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.goals.'.$goal)) ?></dt>
        <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-zink-50"><?= e($present->number($reached)) ?></dd>
        <?php if ($rate !== null && $reached > 0) { ?>
        <dd class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.goals.rate', ['rate' => $rate])) ?></dd>
        <?php } ?>
      </div>
      <?php } ?>
    </dl>
  </div>
</section>
