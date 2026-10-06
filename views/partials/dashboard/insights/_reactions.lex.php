<?php
/**
 * Likes next to dislikes. Dislikes are only ever shown here, to whoever sees these numbers.
 */
$likes = (int) ($report['reactions']['like'] ?? 0);
$dislikes = (int) ($report['reactions']['dislike'] ?? 0);
$likeShare = $likes + $dislikes > 0 ? $likes / ($likes + $dislikes) : null;
?>
<section class="card mb-0" aria-labelledby="insights-reactions">
  <div class="card-body">
    <h2 id="insights-reactions" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.engagement.reactions')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.engagement.reactionsHint')) ?></p>
    <dl class="grid grid-cols-2 gap-3">
      <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600/40">
        <dt class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.engagement.likes')) ?></dt>
        <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-zink-50"><?= e($present->number($likes)) ?></dd>
      </div>
      <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600/40">
        <dt class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.engagement.dislikes')) ?></dt>
        <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-zink-50"><?= e($present->number($dislikes)) ?></dd>
      </div>
    </dl>
    <?php if ($likeShare !== null) { ?>
    <div class="mt-3 h-2 rounded-full overflow-hidden bg-red-200 dark:bg-red-500/30" aria-hidden="true">
      <div class="h-full bg-green-500" style="width: <?= round($likeShare * 100, 1) ?>%"></div>
    </div>
    <p class="mt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.engagement.likeShare', ['percent' => (string) $present->percent($likeShare)])) ?></p>
    <?php } ?>
  </div>
</section>
