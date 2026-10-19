<div class="card">
  <div class="card-body">
    <div class="flex items-start gap-3 mb-4">
      <span class="inline-flex items-center justify-center size-10 shrink-0 rounded-md bg-custom-50 text-custom-500 dark:bg-custom-500/20">
        <i data-lucide="arrow-right-left" class="size-5" aria-hidden="true"></i>
      </span>
      <div>
        <h6 class="text-15"><?= e($t('archive.foreign.heading')) ?></h6>
        <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= e($t('archive.foreign.body')) ?></p>
      </div>
    </div>

    <form method="post" action="/dashboard/blog/{{ blog.id }}/backup/import/preview" enctype="multipart/form-data"
          data-foreign-form
          data-msg-busy="<?= e($t('archive.import.checking')) ?>">
      {{ csrf_field() }}

      <div class="mb-4">
        <label for="foreign-source" class="inline-block mb-2 text-base font-medium"><?= e($t('archive.foreign.platform')) ?></label>
        <select id="foreign-source" name="source" data-foreign-source class="form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100 w-full">
          <option value="wordpress">WordPress</option>
          <option value="ghost">Ghost</option>
        </select>
        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300" data-foreign-help="wordpress"><?= e($t('archive.foreign.wordpressHelp')) ?></p>
        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300" data-foreign-help="ghost" hidden><?= e($t('archive.foreign.ghostHelp')) ?></p>
      </div>

      <div class="mb-4">
        <label for="foreign-file" class="inline-block mb-2 text-base font-medium"><?= e($t('archive.foreign.file')) ?></label>
        <input id="foreign-file" type="file" name="archive" required data-foreign-file accept=".xml"
               class="form-file w-full border border-slate-200 dark:border-zink-500 rounded-md text-sm">
      </div>

      <div class="mb-4" data-foreign-site hidden>
        <label for="foreign-site" class="inline-block mb-2 text-base font-medium"><?= e($t('archive.foreign.siteUrl')) ?></label>
        <input id="foreign-site" type="url" name="site_url" placeholder="https://myoldblog.com"
               class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100 w-full">
        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($t('archive.foreign.siteUrlHelp')) ?></p>
      </div>

      <p class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('archive.foreign.images')) ?></p>

      <div class="mt-4">
        <?php $foreignLabel = $t('archive.import.button'); $foreignAttrs = ['data-archive-submit' => '1']; ?>
        {% cmp="btn" type="submit" variant="blue" icon="search-check" label="{$foreignLabel}" attrs="{$foreignAttrs}" %}
      </div>
    </form>
  </div>
</div>
