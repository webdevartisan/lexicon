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
    'blue' => 'inline-flex items-center justify-center gap-2 rounded-lg font-medium btn bg-slate-900 text-white border-slate-900 hover:bg-slate-700 hover:border-slate-700 focus:ring focus:ring-slate-300 dark:bg-zink-100 dark:text-zink-800 dark:border-zink-100 dark:hover:bg-white dark:hover:border-white dark:focus:ring-zink-500',
    'green' => 'inline-flex items-center justify-center gap-2 rounded-lg font-medium focus:ring text-green-500 bg-white border-green-500 btn hover:text-white hover:bg-green-600 hover:border-green-600 dark:bg-zink-700 dark:hover:bg-green-500',
    'red' => 'inline-flex items-center justify-center gap-2 rounded-lg font-medium focus:ring text-red-500 bg-white border-red-500 btn hover:text-white hover:bg-red-600 hover:border-red-600 dark:bg-zink-700 dark:hover:bg-red-500',
    'yellow' => 'inline-flex items-center justify-center gap-2 rounded-lg font-medium focus:ring text-yellow-500 bg-white border-yellow-500 btn hover:text-white hover:bg-yellow-600 hover:border-yellow-600 dark:bg-zink-700 dark:hover:bg-yellow-500',
    'slate' => 'inline-flex items-center justify-center gap-2 rounded-lg font-medium btn bg-white text-slate-700 border-slate-200 hover:bg-slate-50 focus:ring focus:ring-slate-200 dark:bg-transparent dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 dark:focus:ring-zink-500',
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
      {% cache "lucide:btn:" . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="inline-block size-4 text-inherit" aria-hidden="true"></i>{% endcache %}
    <?php } ?>
    <span><?= e($label) ?></span>
  </button>

<?php } else { ?>

  <a
    href="<?= e(lurl($href)) ?>"
    class="<?= e(trim($class.' '.$addClass)) ?>"<?= $extraAttributes ?>>
      <?php if ($icon) { ?>
        {% cache "lucide:btn:" . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="inline-block size-4 text-inherit" aria-hidden="true"></i>{% endcache %}
      <?php } ?>
    <?= e($label) ?>
  </a>

<?php } ?>
