<?php
/**
 * Header shared by the three Start your blog steps: "Step N of 3" and the dots.
 *
 * Expects: $step (1, 2 or 3)
 */
$step = (int) ($step ?? 1);
?>
<div class="mb-4 flex items-center justify-between gap-3">
  <p class="text-sm font-medium text-slate-500 dark:text-zink-300">
    <?= e($t('setup.start.stepOf', ['n' => $step])) ?> · <?= e($t('setup.start.title')) ?>
  </p>
  <ol class="flex items-center gap-1.5" aria-hidden="true">
    <?php for ($i = 1; $i <= 3; $i++) { ?>
    <li class="h-1.5 rounded-full transition-all <?= $i === $step ? 'w-6 bg-custom-500' : ($i < $step ? 'w-1.5 bg-custom-500/60' : 'w-1.5 bg-slate-300 dark:bg-zink-500') ?>"></li>
    <?php } ?>
  </ol>
</div>
