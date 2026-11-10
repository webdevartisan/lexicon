<?php
$limitParams = ['size' => (int) $maxMb, 'posts' => (int) $maxPosts];
$sources = [
    'lexicon' => [
        'label' => $t('archive.import.sourceLexicon'),
        'ext' => 'zip',
        'help' => $t('archive.import.lexiconHelp'),
        'limits' => $t('archive.import.limits', $limitParams),
        'site' => '',
    ],
    'wordpress' => [
        'label' => $t('archive.import.sourceWordpress'),
        'ext' => 'xml',
        'help' => $t('archive.foreign.wordpressHelp'),
        'limits' => $t('archive.import.limitsFile', $limitParams),
        'site' => '',
    ],
    'ghost' => [
        'label' => $t('archive.import.sourceGhost'),
        'ext' => 'json',
        'help' => $t('archive.foreign.ghostHelp'),
        'limits' => $t('archive.import.limitsFile', $limitParams),
        'site' => '1',
    ],
];
?>
<section>
  <h6 class="text-15"><?= e($t('archive.import.heading')) ?></h6>
  <p class="text-sm text-slate-500 dark:text-zink-300 mt-1" data-archive-help><?= e($sources['lexicon']['help']) ?></p>

  <form method="post" action="/dashboard/blog/{{ blog.id }}/backup/import/preview" enctype="multipart/form-data"
        class="mt-4"
        data-archive-form
        data-max-bytes="<?= (int) $maxMb * 1024 * 1024 ?>"
        data-drop-template="<?= e($t('archive.import.drop')) ?>"
        data-msg-type="<?= e($t('archive.foreign.wrongType')) ?>"
        data-msg-size="<?= e($t('archive.import.tooBig', ['size' => (int) $maxMb])) ?>"
        data-msg-busy="<?= e($t('archive.import.checking')) ?>">
    {{ csrf_field() }}

    <fieldset>
      <legend class="sr-only"><?= e($t('archive.import.sourceLabel')) ?></legend>
      <div class="inline-flex gap-0.5 p-0.5 rounded-md border border-slate-200 dark:border-zink-500">
        <?php foreach ($sources as $key => $source) { ?>
          <label class="cursor-pointer">
            <input type="radio" name="source" value="<?= e($key) ?>" <?= $key === 'lexicon' ? 'checked' : '' ?>
                   class="peer sr-only" data-archive-source
                   data-ext="<?= e($source['ext']) ?>"
                   data-help="<?= e($source['help']) ?>"
                   data-limits="<?= e($source['limits']) ?>"
                   data-site="<?= e($source['site']) ?>">
            <span class="block px-3 py-1.5 text-sm rounded text-slate-600 dark:text-zink-200 peer-checked:bg-custom-500 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-custom-500">
              <?= e($source['label']) ?>
            </span>
          </label>
        <?php } ?>
      </div>
    </fieldset>

    <label data-archive-drop
           class="flex flex-col items-center justify-center gap-1 px-4 py-6 mt-4 text-center rounded-md border-2 border-dashed border-slate-200 dark:border-zink-500 cursor-pointer hover:border-custom-500 transition-colors">
      <i data-lucide="file-archive" class="size-8 text-slate-400 mb-1" aria-hidden="true"></i>
      <span class="text-sm font-medium text-slate-800 dark:text-zink-50" data-archive-drop-text><?= e($t('archive.import.drop', ['type' => '.zip'])) ?></span>
      <span class="text-sm text-custom-500 underline"><?= e($t('archive.import.choose')) ?></span>
      <span class="text-xs text-slate-500 dark:text-zink-300 mt-1" data-archive-limits><?= e($sources['lexicon']['limits']) ?></span>
      <input type="file" name="archive" accept=".zip,application/zip" required class="sr-only" data-archive-input>
    </label>

    <div data-archive-file
         class="hidden flex items-center gap-3 mt-4 px-3 py-2 rounded-md border border-slate-200 dark:border-zink-500">
      <i data-lucide="file-archive" class="size-4 shrink-0 text-custom-500" aria-hidden="true"></i>
      <span class="min-w-0 flex-1 truncate text-sm text-slate-800 dark:text-zink-50" data-archive-name></span>
      <span class="shrink-0 text-xs text-slate-500 dark:text-zink-300" data-archive-size></span>
      <button type="button" data-archive-clear class="shrink-0 p-1 rounded text-slate-500 hover:text-red-500"
              aria-label="<?= e($t('archive.import.remove')) ?>">
        <i data-lucide="x" class="size-4" aria-hidden="true"></i>
      </button>
    </div>

    <p data-archive-error hidden role="alert" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

    <div class="mt-4" data-archive-site hidden>
      <label for="archive-site-url" class="inline-block mb-2 text-sm font-medium"><?= e($t('archive.foreign.siteUrl')) ?></label>
      <input id="archive-site-url" type="url" name="site_url" placeholder="https://myoldblog.com" disabled
             class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100 w-full">
      <p class="mt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($t('archive.foreign.siteUrlHelp')) ?></p>
    </div>

    <div class="mt-4">
      <?php $checkLabel = $t('archive.import.button'); $checkAttrs = ['data-archive-submit' => '1']; ?>
      {% cmp="btn" type="submit" variant="blue" icon="search-check" label="{$checkLabel}" attrs="{$checkAttrs}" %}
    </div>
  </form>
</section>
