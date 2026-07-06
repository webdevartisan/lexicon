<?php
/**
 * Searchable Select (Choices.js)
 *
 * Select with built-in search for long option lists. Pages using it must
 * include choices.css in the head block and choices.min.js plus
 * /cp-assets/js/searchable-select.init.js in the scripts block.
 *
 * Attributes:
 * - name: input name
 * - label: field label (optional)
 * - options: assoc array value => label
 * - selectedKey: currently selected value
 * - placeholder: placeholder text shown in the empty option
 * - required: flag
 * - underlabel: helper text under the field (optional)
 */
$name = (string) ($name ?? '');
$label = (string) ($label ?? '');
$options = $options ?? [];
$selectedKey = $selectedKey ?? '';
$placeholder = (string) ($placeholder ?? 'Choose...');
$required = !empty($required);
$underlabel = (string) ($underlabel ?? '');
?>
<div>
    <?php if ($label !== '') { ?>
    <label for="<?= e($name) ?>" class="inline-block mb-2 text-base font-medium"><?= e($label) ?></label>
    <?php } ?>
    <select id="<?= e($name) ?>" name="<?= e($name) ?>"
            class="searchable-select"
            data-placeholder="<?= e($placeholder) ?>"
            <?= $required ? 'required' : '' ?>>
        <option value=""><?= e($placeholder) ?></option>
        <?php foreach ($options as $value => $optionLabel) { ?>
        <option value="<?= e((string) $value) ?>" <?= (string) $selectedKey === (string) $value ? 'selected' : '' ?>>
            <?= e((string) $optionLabel) ?>
        </option>
        <?php } ?>
    </select>
    <?php if ($underlabel !== '') { ?>
    <p class="mt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($underlabel) ?></p>
    <?php } ?>
</div>
