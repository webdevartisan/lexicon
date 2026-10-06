<?php
$postPerformance = $performance['posts'][(int) $post['id']] ?? null;
$usualFirstWeek = $performance['usualFirstWeek'];
?>
<section class="card mb-0" aria-labelledby="traffic-performance">
  <div class="card-body">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 id="traffic-performance" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.performance.title')) ?></h2>
      <?php if (($postPerformance['label'] ?? null) !== null) { ?>
      {% include "partials/dashboard/insights/_shape_badge.lex.php" %}
      <?php } ?>
    </div>
    <?php if ($postPerformance === null) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.performance.none')) ?></p>
    <?php } else { ?>
    <dl class="grid grid-cols-2 gap-3 mt-3 sm:grid-cols-4">
      <?php foreach ([
          ['analytics.performance.published', $present->date($postPerformance['published'])],
          ['analytics.performance.total', $present->number($postPerformance['total'])],
          ['analytics.performance.firstWeek', $postPerformance['seen_from_start'] ? $present->number($postPerformance['first_week']) : $t('analytics.metrics.none')],
          ['analytics.performance.lastMonth', $present->number($postPerformance['last_month'])],
      ] as [$termKey, $termValue]) { ?>
      <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600/40">
        <dt class="text-xs text-slate-500 dark:text-zink-300"><?= e($t($termKey)) ?></dt>
        <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-zink-50"><?= e($termValue) ?></dd>
      </div>
      <?php } ?>
    </dl>
    <p class="mt-3 text-xs text-slate-500 dark:text-zink-300">
      <?php if (!$postPerformance['seen_from_start']) { ?>
      <?= e($t('analytics.performance.olderThanCounting')) ?>
      <?php } elseif ($usualFirstWeek !== null) { ?>
      <?= e($t('analytics.performance.usualFirstWeek', ['views' => $present->number($usualFirstWeek)])) ?>
      <?php } ?>
    </p>
    <?php } ?>
  </div>
</section>
