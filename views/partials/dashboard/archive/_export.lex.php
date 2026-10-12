<div class="card">
  <div class="card-body">
    <div class="flex items-start gap-3 mb-4">
      <span class="inline-flex items-center justify-center size-10 shrink-0 rounded-md bg-custom-50 text-custom-500 dark:bg-custom-500/20">
        <i data-lucide="download" class="size-5" aria-hidden="true"></i>
      </span>
      <div>
        <h6 class="text-15"><?= e($t('archive.export.heading')) ?></h6>
        <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= e($t('archive.export.body')) ?></p>
      </div>
    </div>

    <form method="post" action="/dashboard/blog/{{ blog.id }}/backup/export">
      {{ csrf_field() }}
      <label class="flex items-start gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 cursor-pointer">
        <input type="checkbox" name="include_drafts" value="1" checked
               class="mt-0.5 size-4 border rounded-sm appearance-none cursor-pointer bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-custom-500 checked:border-custom-500">
        <span>
          <span class="block text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($t('archive.export.includeDrafts')) ?></span>
          <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($t('archive.export.includeDraftsHelp')) ?></span>
        </span>
      </label>

      <p class="text-xs text-slate-500 dark:text-zink-300 mt-3"><?= e($t('archive.export.moderated')) ?></p>

      <div class="mt-4">
        <?php $exportLabel = $t('archive.export.button'); ?>
        {% cmp="btn" type="submit" variant="blue" icon="download" label="{$exportLabel}" %}
      </div>
    </form>
  </div>
</div>
