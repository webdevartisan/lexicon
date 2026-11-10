<section class="pb-6 mb-6 border-b border-slate-200 dark:border-zink-500">
  <form method="post" action="/dashboard/blog/{{ blog.id }}/backup/export">
    {{ csrf_field() }}
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="w-full sm:w-auto sm:flex-1 min-w-0">
        <h6 class="text-15"><?= e($t('archive.export.heading')) ?></h6>
        <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= e($t('archive.export.body')) ?></p>
      </div>
      <?php $exportLabel = $t('archive.export.button'); ?>
      {% cmp="btn" type="submit" variant="blue" icon="download" label="{$exportLabel}" %}
    </div>

    <div class="flex items-center gap-2 mt-4">
      <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-zink-100 cursor-pointer">
        <input type="checkbox" name="include_drafts" value="1" checked
               class="size-4 border rounded-sm appearance-none cursor-pointer bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-custom-500 checked:border-custom-500">
        <?= e($t('archive.export.includeDrafts')) ?>
      </label>
      <span class="cursor-help text-slate-400 dark:text-zink-300" tabindex="0" role="note"
            aria-label="<?= e($t('archive.export.moderated')) ?>"
            data-tooltip data-tooltip-content="<?= e($t('archive.export.moderated')) ?>" data-tooltip-placement="top">
        <i data-lucide="info" class="size-4" aria-hidden="true"></i>
      </span>
    </div>
  </form>
</section>
