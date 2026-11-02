<?php
/**
 * Header shared by the three Start your blog steps: the title, "Step N of 3" and the dots.
 *
 * Expects: $step (1, 2 or 3)
 */
$step = (int) ($step ?? 1);
?>
<div class="start-head">
  <h1 class="start-title"><?= e($t('setup.start.title')) ?></h1>
  <p class="start-count"><?= e($t('setup.start.stepOf', ['n' => $step])) ?></p>
  <ol class="start-dots" aria-hidden="true">
    <?php for ($i = 1; $i <= 3; $i++) { ?>
    <li<?= $i === $step ? ' class="is-current"' : ($i < $step ? ' class="is-done"' : '') ?>></li>
    <?php } ?>
  </ol>
</div>
