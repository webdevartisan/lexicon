<?php
/**
 * Report Pill
 *
 * The small moderation signal shown next to a row in an admin table, such as
 * "2 open reports" on a user or a post.
 *
 * Attributes:
 * - label: the text
 * - tone: red|amber|slate (default red)
 * - href: optional link; left out when the viewer cannot open the target
 */
$label = (string) ($label ?? '');
$tone = (string) ($tone ?? 'red');
$href = (string) ($href ?? '');

$tones = [
    'red' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'amber' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
    'slate' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
];
$classes = 'inline-flex items-center px-2 py-0.5 mt-0.5 text-[10px] font-medium rounded-full border whitespace-nowrap '.($tones[$tone] ?? $tones['red']);
?>
<?php if ($href !== '') { ?>
<a href="<?= e($href) ?>" class="<?= $classes ?> hover:underline"><?= e($label) ?></a>
<?php } else { ?>
<span class="<?= $classes ?>"><?= e($label) ?></span>
<?php } ?>
