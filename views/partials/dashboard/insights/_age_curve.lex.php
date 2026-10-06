<?php
/**
 * Average views per post on each day after publishing: how fast posts are found
 * and how long they keep being read.
 */
$byAge = $report['byAge'];
$agePeak = max(0.0001, ...array_map(static fn (array $day): float => $day['views'], $byAge['days'] ?: [['views' => 0.0]]));
$ageMarks = [0, 7, 14, 21, 29];
?>
<section class="card mb-0" aria-labelledby="insights-by-age">
  <div class="card-body">
    <h2 id="insights-by-age" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.byAge.title')) ?></h2>
    <?php if ($byAge['posts'] === 0) { ?>
    <p class="mt-1 py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.byAge.noPosts', ['date' => $present->date($byAge['since'])])) ?></p>
    <?php } else { ?>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">
      <?= e($t('analytics.byAge.hint', ['posts' => $present->number($byAge['posts']), 'date' => $present->date($byAge['since'])])) ?>
    </p>
    <?php } ?>
    <?php if ($byAge['posts'] > 0 && $byAge['days'] === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.byAge.empty')) ?></p>
    <?php } elseif ($byAge['days'] !== []) { ?>
    <div class="flex items-end gap-0.5 h-32" role="img" aria-label="<?= e($t('analytics.byAge.summary', [
        'first' => $present->decimal($byAge['days'][0]['views']),
        'later' => $present->decimal(end($byAge['days'])['views']),
        'day' => $present->number(end($byAge['days'])['day']),
    ])) ?>">
      <?php foreach ($byAge['days'] as $ageDay) {
          $ageLabel = $t('analytics.byAge.bar', [
              'day' => $present->number($ageDay['day']),
              'views' => $present->decimal($ageDay['views']),
              'posts' => $present->number($ageDay['posts']),
          ]); ?>
      <div class="flex-1 min-w-0 h-full flex items-end" data-tooltip data-tooltip-content="<?= e($ageLabel) ?>" data-tooltip-placement="top">
        <div class="w-full rounded-t bg-custom-500/80 hover:bg-custom-500" style="height: <?= max(1, round($ageDay['views'] / $agePeak * 100, 1)) ?>%"></div>
      </div>
      <?php } ?>
    </div>
    <div class="relative mt-1 h-4 text-[11px] text-slate-500 dark:text-zink-300" aria-hidden="true">
      <?php foreach ($ageMarks as $mark) {
          if ($mark >= count($byAge['days'])) {
              continue;
          }
          $markAt = ($mark + 0.5) / count($byAge['days']) * 100; ?>
      <span class="absolute ltr:-translate-x-1/2 rtl:translate-x-1/2 ltr:left-[var(--at)] rtl:right-[var(--at)]" style="--at: <?= round($markAt, 2) ?>%"><?= e($mark === 0 ? $t('analytics.byAge.publishDay') : $t('analytics.byAge.day', ['day' => $present->number($mark)])) ?></span>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</section>
