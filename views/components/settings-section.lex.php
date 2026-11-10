<?php
/**
 * A settings section that folds away: the heading and description always show,
 * the fields sit underneath.
 *
 * Attributes:
 * - id: anchor for links such as #discussion
 * - titleKey, descriptionKey: translation keys for the heading and its line
 * - body: pre-rendered, already escaped HTML
 * - open: folded out on load, defaults to folded
 */
$id = $id ?? '';
$titleKey = $titleKey ?? '';
$descriptionKey = $descriptionKey ?? '';
$body = $body ?? '';
$open = $open ?? false;
?>
<details id="<?= e($id) ?>" class="group p-4 md:p-5" <?= !empty($open) ? 'open' : '' ?>>
  <summary class="flex items-center justify-between gap-3 list-none cursor-pointer">
    <span>
      <span class="block text-sm font-semibold text-slate-900 dark:text-zink-100"><?= e($t($titleKey)) ?></span>
      <span class="block mt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($t($descriptionKey)) ?></span>
    </span>
    <i data-lucide="chevron-down" class="size-4 shrink-0 text-slate-500 transition-transform group-open:rotate-180 dark:text-zink-300" aria-hidden="true"></i>
  </summary>

  <div class="mt-4 space-y-4">
    <?= $body ?>
  </div>
</details>
