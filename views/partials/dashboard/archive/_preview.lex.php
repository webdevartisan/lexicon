<?php
$counts = $plan['counts'];
$stats = [
    ['label' => $t('archive.preview.ready'), 'value' => $counts['valid'], 'icon' => 'file-check'],
    ['label' => $t('archive.preview.invalid'), 'value' => $counts['invalid'], 'icon' => 'file-x'],
    ['label' => $t('archive.preview.categories'), 'value' => $counts['categories'], 'icon' => 'folder-tree'],
    ['label' => $t('archive.preview.tags'), 'value' => $counts['tags'], 'icon' => 'tags'],
    ['label' => $t('archive.preview.media'), 'value' => $counts['media'], 'icon' => 'image'],
    ['label' => $t('archive.preview.mediaSkipped'), 'value' => $counts['media_skipped'], 'icon' => 'image-off'],
];
$clashOptions = ['skip', 'copy', 'overwrite'];
$problemLimit = 50;
$exportedOn = substr((string) ($plan['source']['exported_at'] ?? ''), 0, 10);
?>
<div class="card">
  <div class="card-body">
    <h6 class="text-15"><?= e($t('archive.preview.heading')) ?></h6>
    <p class="text-sm text-slate-500 dark:text-zink-300 mt-1">
      <?= e($t('archive.preview.from', ['name' => $plan['source']['name'] !== '' ? $plan['source']['name'] : '?', 'date' => $exportedOn])) ?>
    </p>

    <dl class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mt-4">
      <?php foreach ($stats as $stat) { ?>
        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
          <dt class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-zink-300">
            <i data-lucide="<?= e($stat['icon']) ?>" class="size-3.5" aria-hidden="true"></i><?= e($stat['label']) ?>
          </dt>
          <dd class="text-lg font-semibold text-slate-800 dark:text-zink-50 mt-1"><?= (int) $stat['value'] ?></dd>
        </div>
      <?php } ?>
    </dl>
  </div>
</div>

<form method="post" action="/dashboard/blog/{{ blog.id }}/backup/import/confirm" data-archive-confirm
      data-msg-busy="<?= e($t('archive.preview.working')) ?>">
  {{ csrf_field() }}
  <input type="hidden" name="import_id" value="<?= e($importId) ?>">

  <?php if ($plan['clashes'] !== []) { ?>
  <div class="card">
    <div class="card-body">
      <h6 class="text-15"><?= e($t('archive.preview.clashesHeading')) ?></h6>
      <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= e($t('archive.preview.clashesBody', ['count' => count($plan['clashes'])])) ?></p>

      <fieldset class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
        <legend class="sr-only"><?= e($t('archive.preview.clashesHeading')) ?></legend>
        <?php foreach ($clashOptions as $option) { ?>
          <label class="flex items-start gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 cursor-pointer has-[:checked]:border-custom-500">
            <input type="radio" name="on_clash" value="<?= e($option) ?>" <?= $option === 'skip' ? 'checked' : '' ?> class="mt-1">
            <span>
              <span class="block text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($t('archive.preview.'.$option)) ?></span>
              <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($t('archive.preview.'.$option.'Help')) ?></span>
            </span>
          </label>
        <?php } ?>
      </fieldset>

      <div class="overflow-x-auto mt-4">
        <table class="w-full">
          <thead class="ltr:text-left rtl:text-right">
            <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 border-b border-slate-200 dark:border-zink-500">
              <th class="px-3 py-2.5 font-semibold"><?= e($t('archive.preview.posts')) ?></th>
              <th class="px-3 py-2.5 font-semibold"><?= e($t('archive.preview.existing')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($plan['clashes'] as $clash) { ?>
              <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
                <td class="px-3 py-2.5 text-sm text-slate-800 dark:text-zink-100">
                  <?= e($clash['title']) ?>
                  <span class="block text-xs text-slate-500 dark:text-zink-300" dir="ltr"><?= e($clash['slug']) ?></span>
                </td>
                <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-300">
                  <span class="inline-flex flex-wrap items-center gap-2">
                    <?= e($clash['existing_title']) ?>
                    <?php $existingStatus = $clash['existing_status']; ?>
                    {% cmp="status-badge" status="{$existingStatus}" %}
                  </span>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php } else { ?>
    <input type="hidden" name="on_clash" value="skip">
  <?php } ?>

  <div class="card">
    <div class="card-body">
      <h6 class="text-15"><?= e($t('archive.preview.statusHeading')) ?></h6>
      <fieldset class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
        <legend class="sr-only"><?= e($t('archive.preview.statusHeading')) ?></legend>
        <label class="flex items-start gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 cursor-pointer has-[:checked]:border-custom-500">
          <input type="radio" name="keep_status" value="0" checked class="mt-1">
          <span>
            <span class="block text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($t('archive.preview.drafts')) ?></span>
            <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($t('archive.preview.draftsHelp')) ?></span>
          </span>
        </label>
        <label class="flex items-start gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 cursor-pointer has-[:checked]:border-custom-500">
          <input type="radio" name="keep_status" value="1" class="mt-1">
          <span>
            <span class="block text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($t('archive.preview.keep')) ?></span>
            <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($t('archive.preview.keepHelp')) ?></span>
            <?php if ($counts['scheduled_past'] > 0) { ?>
              <span class="block text-xs text-amber-600 dark:text-amber-400 mt-1"><?= e($t('archive.preview.scheduledPast', ['count' => $counts['scheduled_past']])) ?></span>
            <?php } ?>
          </span>
        </label>
      </fieldset>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6 class="text-15"><?= e($t('archive.preview.problemsHeading')) ?></h6>
      <?php if ($plan['problems'] === []) { ?>
        <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= e($t('archive.preview.noProblems')) ?></p>
      <?php } ?>
      <?php foreach ($plan['problems'] as $type => $items) { ?>
        <details class="mt-3 rounded-md border border-slate-200 dark:border-zink-500" <?= count($items) <= 5 ? 'open' : '' ?>>
          <summary class="px-3 py-2 text-sm font-medium text-slate-800 dark:text-zink-50 cursor-pointer">
            <?= e($t('archive.problems.'.$type)) ?> (<?= count($items) ?>)
          </summary>
          <ul class="px-3 pb-3 space-y-1.5">
            <?php foreach (array_slice($items, 0, $problemLimit) as $item) { ?>
              <li class="text-sm text-slate-600 dark:text-zink-200">
                <span class="font-medium"><?= e($item['subject']) ?></span>
                <span class="block text-xs text-slate-500 dark:text-zink-300 break-all"><?= e($item['detail']) ?></span>
              </li>
            <?php } ?>
            <?php if (count($items) > $problemLimit) { ?>
              <li class="text-xs text-slate-500 dark:text-zink-300">+<?= count($items) - $problemLimit ?></li>
            <?php } ?>
          </ul>
        </details>
      <?php } ?>
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-3 mb-5">
    <?php $confirmLabel = $t('archive.preview.confirm'); $confirmAttrs = $counts['valid'] > 0 ? ['data-archive-submit' => '1'] : ['disabled' => 'disabled']; ?>
    {% cmp="btn" type="submit" variant="blue" icon="upload" label="{$confirmLabel}" attrs="{$confirmAttrs}" %}
    <button type="submit" form="archive-cancel-form" class="text-sm text-slate-500 hover:text-red-500 dark:text-zink-300"><?= e($t('archive.import.cancel')) ?></button>
  </div>
</form>

<form id="archive-cancel-form" method="post" action="/dashboard/blog/{{ blog.id }}/backup/import/cancel">
  {{ csrf_field() }}
  <input type="hidden" name="import_id" value="<?= e($importId) ?>">
</form>
