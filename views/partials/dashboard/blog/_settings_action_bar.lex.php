<?php
/**
 * Blog settings action bar, laid out like the post editor's: it sticks under the
 * topbar on desktop and docks to the bottom edge on mobile.
 *
 * Visibility is chosen here instead of inside a tab so it can be changed from any
 * section and always travels with the save.
 *
 * Expects: $blogStatus, $backUrl
 */
$pillTones = [
    'draft' => 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200',
    'published' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    'archived' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
];
$visibilityOptions = ['draft', 'published', 'archived'];
$saveLabel = $blogStatus === 'published' ? $t('blog.form.actions.update') : $t('blog.form.actions.saveDraft');
?>
<div
  data-blog-actionbar
  class="sticky top-header z-30 mb-4 rounded-lg border border-slate-200 bg-white/95 px-4 py-2.5 shadow-sm backdrop-blur
         dark:border-zink-600 dark:bg-zink-700/95
         max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:top-auto max-lg:z-40 max-lg:mb-0 max-lg:rounded-none
         max-lg:border-x-0 max-lg:border-b-0 max-lg:shadow-[0_-2px_10px_rgba(0,0,0,0.08)]
         max-lg:pb-[calc(0.625rem+env(safe-area-inset-bottom))]">
  <div class="flex items-center gap-2">

    <a href="<?= e($backUrl) ?>"
       class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 max-lg:size-11 max-lg:justify-center max-lg:px-0 dark:border-zink-500 dark:text-zink-200 dark:hover:bg-zink-600"
       title="<?= e($t('blog.form.actions.back')) ?>">
      <i data-lucide="arrow-left" class="size-4" aria-hidden="true"></i>
      <span class="max-lg:sr-only"><?= e($t('blog.form.actions.back')) ?></span>
    </a>

    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-medium max-sm:hidden <?= $pillTones[$blogStatus] ?? $pillTones['draft'] ?>">
      <?= e($t('blog.form.fields.visibility.options.'.$blogStatus.'.title')) ?>
    </span>

    <div class="grow"></div>

    <label for="status" class="text-sm text-slate-500 max-md:sr-only dark:text-zink-300">
      <?= e($t('blog.form.fields.visibility.label')) ?>
    </label>
    <select id="status" name="status" aria-describedby="status_hint"
      class="form-select w-auto shrink-0 border-slate-200 py-2 text-sm focus:border-custom-500 focus:outline-none max-lg:h-11 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 dark:focus:border-custom-800">
      <?php foreach ($visibilityOptions as $option) { ?>
      <option value="<?= e($option) ?>" <?= $option === $blogStatus ? 'selected' : '' ?>
              title="<?= e($t('blog.form.fields.visibility.options.'.$option.'.description')) ?>">
        <?= e($t('blog.form.fields.visibility.options.'.$option.'.title')) ?>
      </option>
      <?php } ?>
    </select>
    <span id="status_hint" class="sr-only"><?= e($t('blog.settings.visibilityHint')) ?></span>

    <button type="submit"
      class="inline-flex items-center justify-center gap-1.5 rounded-md border border-custom-500 bg-custom-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:border-custom-600 hover:bg-custom-600 focus:ring focus:ring-custom-100 max-lg:h-11 max-lg:grow dark:focus:ring-custom-500/20">
      <i data-lucide="save" class="size-4" aria-hidden="true"></i>
      <span><?= e($saveLabel) ?></span>
    </button>

  </div>
</div>
