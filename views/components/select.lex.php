<?php
$classSelect = 'form-select appearance-none border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200';
$label = $label ?? 'Label';
$options = $options ?? [];
$name = $name ?? '';
$selectedKey = $selectedKey ?? '';
$groups = $groups ?? [];
$onchange = $onchange ?? '';
$emptyDefault = $emptyDefault ?? false;

if (!empty($name)) {
    $elementName = str_replace(' ', '_', strtolower($name));
} else {
    $elementName = str_replace(' ', '_', strtolower($label));
}

if (!function_exists('is_assoc')) {
    function is_assoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

if (!is_assoc($options)) {
    $assoc = [];
    foreach ($options as $value) {
        $assoc[$value] = ucfirst($value);
    }
    $options = $assoc;
}
?>
<?php if (empty($groups)) { ?>
<div class="relative">
    <label for="<?= $elementName ?>" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
        <?= e($label) ?>
    </label>
    <select 
        style="background-image: none;"
        class="<?= $classSelect ?>" 
        id="<?= $elementName ?>" 
        name="<?= $elementName ?>"
        <?php if (!empty($onchange)) { ?>
            onchange="<?= $onchange ?>"
        <?php } ?>
        >
        <?php if (!empty($emptyDefault)) { ?>
            <option selected="" disabled></option>
        <?php } ?>

        <?php foreach ($options as $key => $lbl) { ?>
            <option value="<?= $key ?>" <?= $selectedKey == $key ? 'selected' : ''; ?>><?= $lbl ?></option>
        <?php } ?>
    </select>
    
    <span class="pointer-events-none absolute right-3 top-1/2 text-slate-500 dark:text-zink-300"> ▼ </span>
</div>

<?php } else { ?>
<?php $classGrouped = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200'; ?>
<div>
<label for="<?= $elementName ?>" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
    <?= e($label) ?>
</label>

<select 
    class="<?= $classGrouped ?>" 
    id="<?= $elementName ?>" 
    data-choices="" 
    data-choices-groups="" 
    data-placeholder="<?= e($label) ?>" 
    name="<?= $elementName ?>">

    <?php if (!empty($emptyDefault)) { ?>
        <option value="" disabled <?= empty($selectedKey) ? 'selected' : '' ?>>
        </option>
    <?php } ?>
    
    <?php foreach (($groups ?? []) as $group => $options) { ?>
            <?php if (!empty($group)) { ?>
                <optgroup label="<?= e($group) ?>">
            <?php } ?>

                <?php foreach ($options as $option) { ?>
                    <?php $val = (string) $option; ?>
                    <option 
                        value="<?= e($val) ?>"
                        <?= ((string) ($selectedKey ?? '') === $val) ? 'selected' : '' ?>>
                        <?= $val ?>
                    </option>
                <?php } ?>

            <?php if (!empty($group)) { ?>
                </optgroup>
            <?php } ?>

    <?php } ?>

</select>
</div>

<?php } ?>