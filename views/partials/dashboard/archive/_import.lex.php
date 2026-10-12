<div class="card">
  <div class="card-body">
    <div class="flex items-start gap-3 mb-4">
      <span class="inline-flex items-center justify-center size-10 shrink-0 rounded-md bg-custom-50 text-custom-500 dark:bg-custom-500/20">
        <i data-lucide="upload" class="size-5" aria-hidden="true"></i>
      </span>
      <div>
        <h6 class="text-15"><?= e($t('archive.import.heading')) ?></h6>
        <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= e($t('archive.import.body')) ?></p>
      </div>
    </div>

    <form method="post" action="/dashboard/blog/{{ blog.id }}/backup/import/preview" enctype="multipart/form-data"
          data-archive-form
          data-max-bytes="<?= (int) $maxMb * 1024 * 1024 ?>"
          data-msg-type="<?= e($t('archive.import.wrongType')) ?>"
          data-msg-size="<?= e($t('archive.import.tooBig', ['size' => (int) $maxMb])) ?>"
          data-msg-busy="<?= e($t('archive.import.checking')) ?>">
      {{ csrf_field() }}

      <label data-archive-drop
             class="flex flex-col items-center justify-center gap-1 px-4 py-8 text-center rounded-md border-2 border-dashed border-slate-200 dark:border-zink-500 cursor-pointer hover:border-custom-500 transition-colors">
        <i data-lucide="file-archive" class="size-8 text-slate-400 mb-1" aria-hidden="true"></i>
        <span class="text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($t('archive.import.drop')) ?></span>
        <span class="text-sm text-custom-500 underline"><?= e($t('archive.import.choose')) ?></span>
        <span class="text-xs text-slate-500 dark:text-zink-300 mt-1">
          <?= e($t('archive.import.limits', ['size' => (int) $maxMb, 'posts' => (int) $maxPosts])) ?>
        </span>
        <input type="file" name="archive" accept=".zip,application/zip" required class="sr-only" data-archive-input>
      </label>

      <div data-archive-file hidden
           class="flex items-center gap-3 mt-3 px-3 py-2 rounded-md border border-slate-200 dark:border-zink-500">
        <i data-lucide="file-archive" class="size-4 shrink-0 text-custom-500" aria-hidden="true"></i>
        <span class="min-w-0 flex-1 truncate text-sm text-slate-800 dark:text-zink-50" data-archive-name></span>
        <span class="shrink-0 text-xs text-slate-500 dark:text-zink-300" data-archive-size></span>
        <button type="button" data-archive-clear class="shrink-0 p-1 rounded text-slate-500 hover:text-red-500"
                aria-label="<?= e($t('archive.import.remove')) ?>">
          <i data-lucide="x" class="size-4" aria-hidden="true"></i>
        </button>
      </div>

      <p data-archive-error hidden role="alert" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

      <p class="text-xs text-slate-500 dark:text-zink-300 mt-3"><?= e($t('archive.import.reencode')) ?></p>

      <div class="mt-4">
        <?php $checkLabel = $t('archive.import.button'); $checkAttrs = ['data-archive-submit' => '1']; ?>
        {% cmp="btn" type="submit" variant="blue" icon="search-check" label="{$checkLabel}" attrs="{$checkAttrs}" %}
      </div>
    </form>
  </div>
</div>
