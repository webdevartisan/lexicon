<?php
$outcomes = [
    'created' => ['icon' => 'file-plus', 'tone' => 'text-green-600 dark:text-green-400'],
    'overwritten' => ['icon' => 'file-pen', 'tone' => 'text-custom-500'],
    'skipped' => ['icon' => 'file-minus', 'tone' => 'text-slate-500 dark:text-zink-300'],
    'failed' => ['icon' => 'file-x', 'tone' => 'text-red-600 dark:text-red-400'],
];

$summary = [];
foreach ($outcomes as $key => $style) {
    if ($result[$key] !== []) {
        $summary[] = $t('archive.result.'.$key).' '.count($result[$key]);
    }
}
if ((int) $result['media']['stored'] > 0) {
    $summary[] = $t('archive.result.mediaStored').' '.(int) $result['media']['stored'];
}
?>
<div class="card">
  <div class="card-body">
    <h6 class="text-15"><?= e($t('archive.result.heading')) ?></h6>
    <p class="text-sm text-slate-500 dark:text-zink-300 mt-1">
      <?= e($summary !== [] ? implode(' · ', $summary) : $t('archive.result.none')) ?>
    </p>

    <?php foreach ($outcomes as $key => $style) { ?>
      <?php if ($result[$key] === []) { continue; } ?>
      <details class="mt-4 rounded-md border border-slate-200 dark:border-zink-500" <?= $key === 'created' ? '' : 'open' ?>>
        <summary class="flex items-center gap-2 px-3 py-2 text-sm font-medium cursor-pointer <?= $style['tone'] ?>">
          <i data-lucide="<?= e($style['icon']) ?>" class="size-4" aria-hidden="true"></i><?= e($t('archive.result.'.$key)) ?> (<?= count($result[$key]) ?>)
        </summary>
        <ul class="px-3 pb-2 divide-y divide-slate-100 dark:divide-zink-600">
          <?php foreach ($result[$key] as $item) { ?>
            <li class="py-2">
              <span class="block text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($item['title'] !== '' ? $item['title'] : $item['slug']) ?></span>
              <?php if ($item['slug'] !== '') { ?>
                <span class="block text-xs text-slate-500 dark:text-zink-300" dir="ltr"><?= e($item['slug']) ?></span>
              <?php } ?>
              <?php if (!empty($item['reason'])) { ?>
                <span class="block text-xs text-red-600 dark:text-red-400 mt-0.5"><?= e($item['reason']) ?></span>
              <?php } ?>
              <?php foreach ($item['notes'] as $note) { ?>
                <span class="block text-xs text-amber-600 dark:text-amber-400 mt-0.5"><?= e($note) ?></span>
              <?php } ?>
            </li>
          <?php } ?>
        </ul>
      </details>
    <?php } ?>

    <?php foreach (['skipped' => 'modulesSkipped', 'failed' => 'modulesFailed'] as $moduleOutcome => $moduleMessage) { ?>
      <?php $moduleKeys = $result['modules'][$moduleOutcome] ?? []; ?>
      <?php if ($moduleKeys === []) { continue; } ?>
      <p class="flex items-start gap-2 mt-4 text-sm text-amber-700 dark:text-amber-400">
        <i data-lucide="puzzle" class="size-4 shrink-0 mt-0.5" aria-hidden="true"></i>
        <span><?= e($t('archive.result.'.$moduleMessage, ['names' => implode(', ', $moduleKeys)])) ?></span>
      </p>
    <?php } ?>

    <?php if ($result['media']['failed'] !== []) { ?>
      <details class="mt-4 rounded-md border border-slate-200 dark:border-zink-500" open>
        <summary class="px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 cursor-pointer">
          <?= e($t('archive.result.mediaFailed')) ?> (<?= count($result['media']['failed']) ?>)
        </summary>
        <ul class="px-3 pb-3 space-y-1.5">
          <?php foreach ($result['media']['failed'] as $failure) { ?>
            <li class="text-sm text-slate-700 dark:text-zink-100">
              <span class="font-medium"><?= e($failure['name']) ?></span>
              <span class="block text-xs text-slate-500 dark:text-zink-300"><?= e($failure['reason']) ?></span>
            </li>
          <?php } ?>
        </ul>
      </details>
    <?php } ?>

    <div class="flex flex-wrap items-center gap-3 mt-5">
      <?php $postsUrl = lurl('/dashboard/post'); $postsLabel = $t('archive.result.viewPosts'); ?>
      {% cmp="btn" href="{$postsUrl}" variant="blue" icon="files" label="{$postsLabel}" %}
      <a href="<?= e(lurl($basePath)) ?>" class="text-sm text-slate-500 hover:text-custom-500 dark:text-zink-300"><?= e($t('archive.result.back')) ?></a>
    </div>
  </div>
</div>
