<?php
/**
 * One radio row of the review step: label and help text beside the button.
 *
 * Expects $choice with: name, value, checked, label, help and an optional note
 * (an amber line shown under the help, already translated).
 */
?>
<label class="flex items-start gap-3 py-1.5 cursor-pointer">
  <input type="radio" name="<?= e($choice['name']) ?>" value="<?= e($choice['value']) ?>" <?= $choice['checked'] ? 'checked' : '' ?> class="mt-1">
  <span>
    <span class="block text-sm font-medium text-slate-800 dark:text-zink-50"><?= e($choice['label']) ?></span>
    <span class="block text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($choice['help']) ?></span>
    <?php if (!empty($choice['note'])) { ?>
      <span class="block text-xs text-amber-600 dark:text-amber-400 mt-1"><?= e($choice['note']) ?></span>
    <?php } ?>
  </span>
</label>
