<?php
/*
 * Shared by every Email Templates page: the section tabs, the form messages
 * and a few helpers. Included, so it runs in the including page's scope; set
 * $activeTab first.
 *
 * Never write two opening braces in a row in these views: the template
 * compiler treats them as its own variable tag, even inside PHP or script.
 * $ph() writes a placeholder for display instead.
 */
$emailTabs = [
    'emails' => ['Emails', '/admin/email-templates', 'mail'],
    'layouts' => ['Layouts', '/admin/email-templates/layouts', 'layout-template'],
];

$ph = static fn (string $name): string => '{'.'{ '.$name.' }'.'}';

$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200';
$selectClass = 'form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800';
$codeClass = $fieldClass.' font-mono text-xs leading-relaxed whitespace-pre';
$labelClass = 'inline-block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-100';
$hintClass = 'mt-1 text-xs text-slate-500 dark:text-zink-300';
$buttonClass = 'btn bg-white border-slate-300 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100';

$sourceBadge = static function (string $source): string {
    [$label, $class, $title] = match ($source) {
        'customized' => ['Edited', 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300', 'Shipped with the site, with your changes saved over it'],
        'custom' => ['Custom', 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300', 'Made in the control panel'],
        default => ['Shipped', 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200', 'As it ships with the site'],
    };

    return '<span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded '.$class.'" title="'.e($title).'">'.e($label).'</span>';
};
?>
<nav class="flex flex-wrap gap-1 mb-5 border-b border-slate-200 dark:border-zink-500" aria-label="Email templates sections">
    <?php foreach ($emailTabs as $tabKey => [$tabLabel, $tabHref, $tabIcon]) {
        $isActive = ($activeTab ?? '') === $tabKey; ?>
    <a href="<?= e(lurl($tabHref)) ?>"
       class="inline-flex items-center gap-2 px-4 py-2 -mb-px text-sm font-medium border-b-2 transition-colors <?= $isActive
           ? 'border-custom-500 text-custom-500'
           : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300 dark:text-zink-200 dark:hover:text-zink-50' ?>"
       <?= $isActive ? 'aria-current="page"' : '' ?>>
        <i data-lucide="<?= e($tabIcon) ?>" class="size-4"></i><?= e($tabLabel) ?>
    </a>
    <?php } ?>
</nav>

<?php if (!empty($formErrors)) { ?>
<div class="px-4 py-3 mb-4 text-sm text-red-700 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300" role="alert">
    <div class="flex items-center gap-2 mb-1 font-medium"><i data-lucide="alert-circle" class="size-4"></i> Nothing was saved</div>
    <ul class="ltr:pl-5 rtl:pr-5 list-disc space-y-0.5">
        <?php foreach ($formErrors as $formError) { ?>
        <li><?= e($formError) ?></li>
        <?php } ?>
    </ul>
</div>
<?php } ?>

<?php if (!empty($formWarnings)) { ?>
<div class="px-4 py-3 mb-4 text-sm text-amber-800 border border-amber-200 rounded-md bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-200">
    <div class="flex items-center gap-2 mb-1 font-medium"><i data-lucide="alert-triangle" class="size-4"></i> Worth a look</div>
    <ul class="ltr:pl-5 rtl:pr-5 list-disc space-y-0.5">
        <?php foreach ($formWarnings as $formWarning) { ?>
        <li><?= e($formWarning) ?></li>
        <?php } ?>
    </ul>
</div>
<?php } ?>
