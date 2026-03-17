<?php
$type = $type ?? 'button';
$variant = $variant ?? 'slate';
$label = $label ?? 'Button';
$icon = $icon ?? null;
$href = $href ?? null;

$dataBtn = $dataBtn ?? '';

$dataAction = $dataAction ?? '';
$dataTarget = $dataTarget ?? '';
$dataModalTarget = $dataModalTarget ?? '';

$variants = [
    'blue' => 'bg-white text-custom-500 btn border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 active:ring active:ring-custom-100 dark:bg-zink-700 dark:hover:bg-custom-500 dark:ring-custom-400/20 dark:focus:bg-custom-500',
    'green' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring text-green-500 bg-white border-green-500 btn hover:text-white hover:bg-green-600 hover:border-green-600 dark:bg-zink-700 dark:hover:bg-green-500',
    'red' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring text-red-500 bg-white border-red-500 btn hover:text-white hover:bg-red-600 hover:border-red-600 dark:bg-zink-700 dark:hover:bg-red-500',
    'yellow' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring text-yellow-500 bg-white border-yellow-500 btn hover:text-white hover:bg-yellow-600 hover:border-yellow-600 dark:bg-zink-700 dark:hover:bg-yellow-500',
    'slate' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring bg-white text-slate-500 border-slate-500 btn hover:text-white hover:bg-slate-600 hover:border-slate-600 dark:bg-zink-700 dark:hover:bg-slate-500',
];

$class = $variants[$variant] ?? $variants['slate'];

?>
<?php if (empty($href)) { ?>

  <button type="<?= e($type) ?>"
          class="<?= e($class) ?> <?= $addClass ?? '' ?>"
          <?php if ($dataAction) { ?>
            data-action="<?= ($dataAction) ?>"
          <?php } ?>
          <?php if ($dataTarget) { ?>
            data-target="<?= ($dataTarget) ?>"
          <?php } ?>
          <?= $dataBtn ?>
          >
    <?php if ($icon) { ?>
      <i data-lucide="<?= e($icon) ?>"
        class="inline-block size-5 text-inherit"
        aria-hidden="true"></i>
    <?php } ?>
    <span><?= e($label) ?></span>
  </button>

<?php } else { ?>

  <a 
    href="<?= e($href) ?>" 
    class="<?= e($class) ?>">
      <?php if ($icon) { ?>
        <i data-lucide="<?= e($icon) ?>"
          class="inline-block size-5 text-inherit"
          aria-hidden="true"></i>
      <?php } ?>
    <?= e($label) ?>
  </a>

<?php } ?>