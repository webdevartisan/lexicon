<?php
$type = $type ?? 'button';
$variant = $variant ?? 'slate';
$label = $label ?? 'Button';
$icon = $icon ?? null;
$href = $href ?? null;

// Data attributes
$dataAction = $dataAction ?? '';
$dataTarget = $dataTarget ?? '';
$dataModalTarget = $dataModalTarget ?? '';
$dataOnclick = $dataOnclick ?? '';

// Base classes - matching your example exactly
$baseClasses = 'flex items-center justify-center w-full gap-2 px-3 py-2 text-xs font-medium transition-colors border rounded-md';
// Variants - exact pattern from your example
$variants = [
    'custom' => 'text-custom-600 border-custom-200 hover:bg-custom-50 hover:border-custom-300 focus:outline-none focus:ring-2 focus:ring-custom-500 focus:ring-offset-2 dark:border-custom-500/30 dark:text-custom-400 dark:hover:bg-custom-500/10 dark:focus:ring-offset-zink-700',

    'green' => 'text-green-600 border-green-200 hover:bg-green-50 hover:border-green-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:border-green-500/30 dark:text-green-400 dark:hover:bg-green-500/10 dark:focus:ring-offset-zink-700',

    'red' => 'text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10 dark:focus:ring-offset-zink-700',

    'yellow' => 'text-yellow-600 border-yellow-200 hover:bg-yellow-50 hover:border-yellow-300 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:border-yellow-500/30 dark:text-yellow-400 dark:hover:bg-yellow-500/10 dark:focus:ring-offset-zink-700',

    'blue' => 'text-blue-600 border-blue-200 hover:bg-blue-50 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-blue-500/30 dark:text-blue-400 dark:hover:bg-blue-500/10 dark:focus:ring-offset-zink-700',

    'slate' => 'text-slate-600 border-slate-200 hover:bg-slate-50 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:border-slate-500/30 dark:text-slate-400 dark:hover:bg-slate-500/10 dark:focus:ring-offset-zink-700',
];

$variantClass = $variants[$variant] ?? $variants['slate'];
$class = "$baseClasses $variantClass";

// Build data attributes safely
$dataAttrs = [];
if ($dataAction) {
    $dataAttrs[] = 'data-action="'.e($dataAction).'"';
}
if ($dataTarget) {
    $dataAttrs[] = 'data-target="'.e($dataTarget).'"';
}
if ($dataModalTarget) {
    $dataAttrs[] = 'data-modal-target="'.e($dataModalTarget).'"';
}
if ($dataOnclick) {
    $dataAttrs[] = 'data-onclick="'.e($dataOnclick).'"';
}
$dataAttrsString = implode(' ', $dataAttrs);
?>

<?php if (empty($href)) { ?>
  <button 
    type="<?= e($type) ?>"
    class="<?= e($class) ?> <?= e($addClass ?? '') ?>"
    <?= $dataAttrsString ?>>
    <?php if ($icon) { ?>
      <i data-lucide="<?= e($icon) ?>"
         class="size-4"
         aria-hidden="true"></i>
    <?php } ?>
    <span><?= e($label) ?></span>
  </button>
<?php } else { ?>
  <a 
    href="<?= e($href) ?>" 
    class="<?= e($class) ?> <?= e($addClass ?? '') ?>"
    <?= $dataAttrsString ?>>
    <?php if ($icon) { ?>
      <i data-lucide="<?= e($icon) ?>"
         class="size-4"
         aria-hidden="true"></i>
    <?php } ?>
    <span><?= e($label) ?></span>
  </a>
<?php } ?>
