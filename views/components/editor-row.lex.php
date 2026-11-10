<?php
/**
 * One line of a settings list: an icon, a name, the current value and a
 * chevron. The whole line is a button that opens a dialog.
 *
 * Attributes:
 * - target: id of the modal it opens
 * - icon: lucide icon name
 * - label: the setting's name
 * - value: key the page's script fills the current value under (data-row-value)
 * - invalid: marks the row when the dialog holds a field the server rejected
 */
$target = (string) ($target ?? '');
$icon = (string) ($icon ?? 'settings');
$label = (string) ($label ?? '');
$value = (string) ($value ?? '');
$invalid = !empty($invalid);
?>
<button type="button" data-modal-target="<?= e($target) ?>"
    class="flex min-h-12 w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 focus:bg-slate-50 focus:outline-none dark:hover:bg-zink-600 dark:focus:bg-zink-600">
    {% cache 'lucide:editor-row:' . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="size-4 shrink-0 text-slate-400 dark:text-zink-300" aria-hidden="true"></i>{% endcache %}
    <span class="shrink-0 text-sm font-medium text-slate-800 dark:text-zink-100"><?= e($label) ?></span>
    <?php if ($invalid) { ?>
    <span class="size-2 shrink-0 rounded-full bg-red-500" title="Needs a fix"></span>
    <?php } ?>
    <span data-row-value="<?= e($value) ?>" class="min-w-0 grow truncate text-xs text-slate-500 ltr:text-right rtl:text-left dark:text-zink-300"></span>
    {% cache 'lucide:editor-row:chevron' ttl=31536000 %}<i data-lucide="chevron-right" class="size-4 shrink-0 text-slate-400 rtl:rotate-180 dark:text-zink-300" aria-hidden="true"></i>{% endcache %}
</button>
