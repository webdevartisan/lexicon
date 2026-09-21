<?php
$type = $type ?? 'button';
$variant = $variant ?? 'slate';
$label = $label ?? 'Button';
$icon = $icon ?? null;
$href = $href ?? null;
$name = $name ?? null;
$value = $value ?? null;
$addClass = $addClass ?? '';

$dataBtn = $dataBtn ?? '';

$dataAction = $dataAction ?? '';
$dataTarget = $dataTarget ?? '';
$dataModalTarget = $dataModalTarget ?? '';

// Extra attributes as name => value pairs, escaped here so callers never build attribute strings by hand.
$attrs = $attrs ?? [];
$extraAttributes = '';
foreach ($attrs as $attrName => $attrValue) {
    if ($attrValue === null || $attrValue === '') {
        continue;
    }
    $extraAttributes .= ' '.e($attrName).'="'.e((string) $attrValue).'"';
}

$variants = [
    'blue' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium bg-white text-custom-500 btn border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 active:ring active:ring-custom-100 dark:bg-zink-700 dark:hover:bg-custom-500 dark:ring-custom-400/20 dark:focus:bg-custom-500',
    'green' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring text-green-500 bg-white border-green-500 btn hover:text-white hover:bg-green-600 hover:border-green-600 dark:bg-zink-700 dark:hover:bg-green-500',
    'red' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring text-red-500 bg-white border-red-500 btn hover:text-white hover:bg-red-600 hover:border-red-600 dark:bg-zink-700 dark:hover:bg-red-500',
    'yellow' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring text-yellow-500 bg-white border-yellow-500 btn hover:text-white hover:bg-yellow-600 hover:border-yellow-600 dark:bg-zink-700 dark:hover:bg-yellow-500',
    'slate' => 'inline-flex items-center justify-center gap-2 rounded-md font-medium focus:ring bg-white text-slate-500 border-slate-500 btn hover:text-white hover:bg-slate-600 hover:border-slate-600 dark:bg-zink-700 dark:hover:bg-slate-500',
];

$class = $variants[$variant] ?? $variants['slate'];

?>
<?php if (empty($href)) { ?>

  <button type="<?= e($type) ?>"
          class="<?= e(trim($class.' '.$addClass)) ?>"
          <?php if ($dataAction) { ?>
            data-action="<?= e($dataAction) ?>"
          <?php } ?>
          <?php if ($dataTarget) { ?>
            data-target="<?= e($dataTarget) ?>"
          <?php } ?>
          <?php if ($dataModalTarget) { ?>
            data-modal-target="<?= e($dataModalTarget) ?>"
          <?php } ?>
          <?= $dataBtn ?><?= $extraAttributes ?>

          <?php if ($name) { ?>
            name="<?= e($name) ?>"
          <?php } ?>
          <?php if ($value) { ?>
            value="<?= e($value) ?>"
          <?php } ?>
          >
    <?php if ($icon) { ?>
      {% cache "lucide:btn:" . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="inline-block size-5 text-inherit" aria-hidden="true"></i>{% endcache %}
    <?php } ?>
    <span><?= e($label) ?></span>
  </button>

<?php } else { ?>

  <a
    href="<?= e($href) ?>"
    class="<?= e(trim($class.' '.$addClass)) ?>"<?= $extraAttributes ?>>
      <?php if ($icon) { ?>
        {% cache "lucide:btn:" . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="inline-block size-5 text-inherit" aria-hidden="true"></i>{% endcache %}
      <?php } ?>
    <?= e($label) ?>
  </a>

<?php } ?>
