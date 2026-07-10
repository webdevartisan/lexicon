<?php
/**
 * Icon Action Link
 *
 * Icon-only action link for table rows, with a tippy tooltip
 * (requires /cp-assets/js/tooltip.js on the page).
 *
 * Attributes:
 * - href: link target
 * - icon: lucide icon name
 * - tip: tooltip text (also used as aria-label)
 * - danger: flag for destructive styling (red hover)
 */
$href = (string) ($href ?? '#');
$icon = (string) ($icon ?? 'circle');
$tip = (string) ($tip ?? '');
$danger = !empty($danger);

$hover = $danger
    ? 'hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30'
    : 'hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10';
?>
<a href="<?= e($href) ?>"
   data-tooltip data-tooltip-content="<?= e($tip) ?>" data-tooltip-placement="top"
   aria-label="<?= e($tip) ?>"
   class="p-2 text-slate-500 rounded-md transition-colors <?= $hover ?>">
    {% cache 'lucide:icon-action:' . $icon ttl=3600 %}<i data-lucide="<?= e($icon) ?>" class="size-4"></i>{% endcache %}
</a>
