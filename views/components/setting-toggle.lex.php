<?php
/**
 * One labelled checkbox with optional help text, used for on/off settings.
 *
 * Expects: $name (also the input id), $label, optional $help, optional $checked
 */
$name = $name ?? '';
$label = $label ?? '';
$help = $help ?? '';
$checked = !empty($checked);
?>
<div class="flex items-start gap-2">
  <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="checkbox" value="1"
    class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
    <?= $checked ? 'checked' : '' ?>>
  <div>
    <label for="<?= e($name) ?>" class="text-xs font-medium text-slate-800 dark:text-zink-100"><?= e($label) ?></label>
    <?php if ($help !== '') { ?>
    <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300"><?= e($help) ?></p>
    <?php } ?>
  </div>
</div>
