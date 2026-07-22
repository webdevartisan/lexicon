<?php
/**
 * Post editor action bar.
 *
 * One element, two positions: it sticks under the topbar on desktop and
 * becomes a fixed bottom bar on mobile, where the primary action needs to sit
 * under the thumb. Buttons submit through name="intent", so the whole thing
 * works with JavaScript off; PostActionPresenter decides which appear and
 * WorkflowService still clamps whatever arrives.
 *
 * Deleting deliberately lives in the sidebar danger zone instead of here, so
 * the always-visible bar holds nothing destructive.
 *
 * Expects: $actions (presenter output), $backUrl
 */
$pillTones = [
    'slate' => 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200',
    'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    'zinc' => 'bg-slate-800 text-slate-100 dark:bg-zink-900 dark:text-zink-100',
];

$primary = $actions['primary'];
$secondary = $actions['secondary'] ?? null;
$menu = $actions['menu'] ?? [];
$pill = $actions['pill'];

$primaryClass = $primary['variant'] === 'green'
    ? 'bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700 hover:border-emerald-700 focus:ring-emerald-100 dark:focus:ring-emerald-500/20'
    : 'bg-custom-500 text-white border-custom-500 hover:bg-custom-600 hover:border-custom-600 focus:ring-custom-100 dark:focus:ring-custom-500/20';
?>
<div
  data-post-actionbar
  class="sticky top-header z-30 -mx-4 mb-4 border-b border-slate-200 bg-white/95 px-4 py-2.5 backdrop-blur
         dark:border-zink-600 dark:bg-zink-700/95
         max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:top-auto max-lg:z-40 max-lg:mx-0 max-lg:mb-0
         max-lg:border-b-0 max-lg:border-t max-lg:shadow-[0_-2px_10px_rgba(0,0,0,0.08)]
         max-lg:pb-[calc(0.625rem+env(safe-area-inset-bottom))]">
  <div class="flex items-center gap-2">

    <a href="<?= e($backUrl ?? '/dashboard/post') ?>"
       class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 max-lg:size-11 max-lg:justify-center max-lg:px-0 dark:border-zink-500 dark:text-zink-200 dark:hover:bg-zink-600"
       title="Back">
      <i data-lucide="arrow-left" class="size-4" aria-hidden="true"></i>
      <span class="max-lg:sr-only">Back</span>
    </a>

    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-medium <?= $pillTones[$pill['tone']] ?? $pillTones['slate'] ?>">
      <?= e($pill['label']) ?>
    </span>

    <!-- Autosave keeps the IDs autosave.js already writes to -->
    <div id="autosave-indicator" class="flex items-center gap-1.5 text-xs text-slate-500 max-lg:hidden dark:text-zink-400" style="display: none;">
      <svg class="size-3.5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
      </svg>
      <span id="autosave-message"></span>
    </div>

    <div class="grow"></div>

    <?php if ($menu !== []) { ?>
    <details data-post-menu class="relative shrink-0">
      <summary
        class="flex cursor-pointer list-none items-center justify-center rounded-md border border-slate-200 px-2.5 py-2 text-slate-600 transition-colors hover:bg-slate-50 max-lg:size-11 max-lg:px-0 dark:border-zink-500 dark:text-zink-200 dark:hover:bg-zink-600"
        aria-haspopup="menu"
        title="More actions">
        <i data-lucide="ellipsis" class="size-4" aria-hidden="true"></i>
        <span class="sr-only">More actions</span>
      </summary>
      <div
        role="menu"
        class="absolute z-50 w-56 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg ltr:right-0 rtl:left-0 dark:border-zink-500 dark:bg-zink-600
               bottom-full mb-1 lg:bottom-auto lg:top-full lg:mb-0 lg:mt-1">
        <?php foreach ($menu as $item) { ?>
        <button type="submit" name="intent" value="<?= e($item['intent']) ?>" role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 transition-colors hover:bg-slate-50 dark:text-zink-200 dark:hover:bg-zink-500">
          <i data-lucide="<?= e($item['icon']) ?>" class="size-4 shrink-0" aria-hidden="true"></i>
          <?= e($item['label']) ?>
        </button>
        <?php } ?>
      </div>
    </details>
    <?php } ?>

    <?php if ($secondary !== null) { ?>
    <button type="submit" name="intent" value="<?= e($secondary['intent']) ?>"
      class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:ring focus:ring-slate-100 max-lg:h-11 max-sm:w-11 max-sm:px-0 dark:border-zink-500 dark:bg-zink-600 dark:text-zink-100 dark:hover:bg-zink-500">
      <i data-lucide="<?= e($secondary['icon']) ?>" class="size-4" aria-hidden="true"></i>
      <span class="max-sm:sr-only"><?= e($secondary['label']) ?></span>
    </button>
    <?php } ?>

    <button type="submit" name="intent" value="<?= e($primary['intent']) ?>"
      data-post-primary-action
      data-publish-label="<?= e($primary['label']) ?>"
      class="inline-flex items-center justify-center gap-1.5 rounded-md border px-4 py-2 text-sm font-medium transition-colors focus:ring max-lg:h-11 max-lg:grow <?= $primaryClass ?>">
      <i data-lucide="<?= e($primary['icon']) ?>" class="size-4" aria-hidden="true"></i>
      <span data-primary-label><?= e($primary['label']) ?></span>
    </button>

  </div>
</div>
