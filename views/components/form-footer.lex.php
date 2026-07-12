<?php
/**
 * Form Footer
 *
 * Card footer with a cancel link and a submit button. Belongs inside a
 * <form class="card"> so the submit posts the surrounding form.
 *
 * Attributes:
 * - cancelHref: where the cancel link goes
 * - cancelLabel: cancel text (default "Cancel")
 * - submitLabel: submit button text
 * - submitIcon: lucide icon for the submit button
 * - danger: flag for destructive styling (red submit)
 */
$cancelHref = (string) ($cancelHref ?? '/admin');
$cancelLabel = (string) ($cancelLabel ?? 'Cancel');
$submitLabel = (string) ($submitLabel ?? 'Save');
$submitIcon = (string) ($submitIcon ?? 'save');
$danger = !empty($danger);

$submitClass = $danger
    ? 'text-white bg-red-500 border-red-500 hover:bg-red-600'
    : 'text-white bg-custom-500 border-custom-500 hover:bg-custom-600';
?>
<div class="card-body flex items-center justify-end gap-2 border-t border-slate-100 dark:border-zink-600">
    <a href="<?= e($cancelHref) ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors"><?= e($cancelLabel) ?></a>
    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md transition-colors <?= $submitClass ?>">
        {% cache 'lucide:form-footer:' . $submitIcon ttl=31536000 %}<i data-lucide="<?= e($submitIcon) ?>" class="size-4"></i>{% endcache %} <?= e($submitLabel) ?>
    </button>
</div>
