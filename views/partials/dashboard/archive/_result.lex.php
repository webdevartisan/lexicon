<?php
$outcomes = [
    'created' => ['icon' => 'file-plus', 'tone' => 'text-green-600 dark:text-green-400'],
    'overwritten' => ['icon' => 'file-pen', 'tone' => 'text-custom-500'],
    'skipped' => ['icon' => 'file-minus', 'tone' => 'text-slate-500 dark:text-zink-300'],
    'failed' => ['icon' => 'file-x', 'tone' => 'text-red-600 dark:text-red-400'],
];
?>
<div class="card">
  <div class="card-body">
    <h6 class="text-15"><?= e($t('archive.result.heading')) ?></h6>

    <dl class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mt-4">
      <?php foreach ($outcomes as $key => $style) { ?>
        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
          <dt class="flex items-center gap-1.5 text-xs <?= $style['tone'] ?>">
            <i data-lucide="<?= e($style['icon']) ?>" class="size-3.5" aria-hidden="true"></i><?= e($t('archive.result.'.$key)) ?>
          </dt>
          <dd class="text-lg font-semibold text-slate-800 dark:text-zink-50 mt-1"><?= count($result[$key]) ?></dd>
        </div>
      <?php } ?>
      <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
        <dt class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-zink-300">
          <i data-lucide="image" class="size-3.5" aria-hidden="true"></i><?= e($t('archive.result.mediaStored')) ?>
        </dt>
        <dd class="text-lg font-semibold text-slate-800 dark:text-zink-50 mt-1"><?= (int) $result['media']['stored'] ?></dd>
      </div>
    </dl>
  </div>
</div>

<?php foreach ($outcomes as $key => $style) { ?>
  <?php if ($result[$key] === []) { continue; } ?>
  <div class="card">
    <div class="card-body">
      <h6 class="text-15 flex items-center gap-2 <?= $style['tone'] ?>">
        <i data-lucide="<?= e($style['icon']) ?>" class="size-4" aria-hidden="true"></i><?= e($t('archive.result.'.$key)) ?>
      </h6>
      <ul class="mt-3 divide-y divide-slate-100 dark:divide-zink-600">
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
    </div>
  </div>
<?php } ?>

<?php if ($result['media']['failed'] !== []) { ?>
  <div class="card">
    <div class="card-body">
      <h6 class="text-15 text-red-600 dark:text-red-400"><?= e($t('archive.result.mediaFailed')) ?></h6>
      <ul class="mt-3 space-y-1.5">
        <?php foreach ($result['media']['failed'] as $failure) { ?>
          <li class="text-sm text-slate-700 dark:text-zink-100">
            <span class="font-medium"><?= e($failure['name']) ?></span>
            <span class="block text-xs text-slate-500 dark:text-zink-300"><?= e($failure['reason']) ?></span>
          </li>
        <?php } ?>
      </ul>
    </div>
  </div>
<?php } ?>

<div class="flex flex-wrap items-center gap-3 mb-5">
  <?php $postsUrl = lurl('/dashboard/post'); $postsLabel = $t('archive.result.viewPosts'); ?>
  {% cmp="btn" href="{$postsUrl}" variant="blue" icon="files" label="{$postsLabel}" %}
  <a href="<?= e(lurl($basePath)) ?>" class="text-sm text-slate-500 hover:text-custom-500 dark:text-zink-300"><?= e($t('archive.result.back')) ?></a>
</div>
