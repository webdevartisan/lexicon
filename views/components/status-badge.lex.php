<?php
/**
 * Status Badge
 *
 * Small pill badge with a color mapped to the given status.
 *
 * Attributes:
 * - status: one of published|scheduled|draft|archived|pending|approved|spam|active|inactive
 * - label: optional display text (defaults to ucfirst of status)
 */
$status = (string) ($status ?? 'draft');
$label = (string) ($label ?? ucfirst($status));

$map = [
    'published' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'approved' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'active' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'pending' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
    'scheduled' => 'bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/40 dark:text-violet-200 dark:border-violet-800',
    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'inactive' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'spam' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
];

$classes = $map[$status] ?? $map['draft'];
?>
<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $classes ?>"><?= e($label) ?></span>
