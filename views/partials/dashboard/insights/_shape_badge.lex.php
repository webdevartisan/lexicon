<?php
$shape = (string) $postPerformance['label'];
$shapeTones = [
    'new' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    'spike' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
    'evergreen' => 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
];
?>
<span class="inline-flex px-2 py-0.5 text-[11px] font-medium rounded-full cursor-help <?= $shapeTones[$shape] ?? $shapeTones['new'] ?>" tabindex="0"
      data-tooltip data-tooltip-content="<?= e($t('analytics.performance.hints.'.$shape)) ?>" data-tooltip-placement="top">
  <?= e($t('analytics.performance.labels.'.$shape)) ?>
  <span class="sr-only">: <?= e($t('analytics.performance.hints.'.$shape)) ?></span>
</span>
