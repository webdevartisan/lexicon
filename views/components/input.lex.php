<?php
$errors = errors();
$type = $type ?? 'text';
$label = $label ?? 'label';
$value = $value ?? '';
$name = $name ?? '';
$placeholder = $placeholder ?? '';
$underlabel = $underlabel ?? null;
$disabled = $disabled ?? false;
$required = $required ?? false;
$prefix = $prefix ?? '';
$rows = $rows ?? '8';

$classLabel = 'inline-block mb-2 text-base font-medium';
$classInput = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200';
$classInvalid = 'valid:border-green-500 invalid:border-red-500 dark:valid:border-green-800 dark:invalid:border-red-800';
$classPrefix = 'ltr:rounded-l-none rtl:rounded-r-none';

if (!empty($name)) {
    $elementName = str_replace(' ', '_', strtolower($name));
} else {
    $elementName = str_replace(' ', '_', strtolower($label));
}

$label = ucwords($label);
$old = old($elementName);
?>

<?php if ($type !== 'textarea') { ?>

<div>
    <?php if ($type !== 'hidden') { ?>
        <label for="<?= $elementName ?>" class="<?= $classLabel ?>">
            <p class="first-letter:uppercase">
                <?= $label ?>
            </p>
        </label>
    <?php } ?>

<?php if (!empty($prefix)) { ?>
<div class="flex items-center">
    <span id="<?= $elementName ?>_prefix"
        class="inline-block px-3 py-2 border ltr:border-r-0 rtl:border-l-0 border-slate-200 bg-slate-100 dark:border-zink-500 dark:bg-zink-600 ltr:rounded-l-md rtl:rounded-r-md whitespace-nowrap flex-shrink-0">
        <?= $prefix ?>
    </span>
<?php } ?>
    <input 
        type="<?= $type ?>" 
        id="<?= $elementName ?>" 
        name="<?= $elementName ?>"
        class="<?= $classInput ?> <?= !empty($prefix) ? $classPrefix : '' ?> <?= !empty($errors["$elementName"]) ? $classInvalid : '' ?>" 
        value="<?= $old ?? $value ?>" 
        <?php if ($type === 'password') {
            echo 'autocomplete="'.str_replace('_', '-', $elementName).'"';
        }  ?>
        <?php if (!empty($placeholder)) {
            echo 'placeholder="'.$placeholder.'"';
        }  ?>
        <?php if ($required == true) {
            echo 'required';
        }  ?>
        <?php if ($disabled == true) {
            echo 'disabled=""';
        }  ?>
        <?php if (!empty($errors[$elementName])) { ?>
            aria-invalid="true"
            aria-describedby="<?= e($elementName) ?>"
        <?php } ?>
    >
<?php if (!empty($prefix)) { ?>
</div>
<?php } ?>

    <?php if (!empty($underlabel)) { ?>
        <div class="mt-1 text-xs text-slate-500 dark:text-zink-200">
            <?= $underlabel ?>
        </div>
    <?php } ?>

    <?php if (!empty($errors["$elementName"])) { ?>
        <?php foreach ($errors["$elementName"] as $msg) {  ?>
            <p class="mt-1 text-sm text-red-600 dark:text-red-400"> <?= $msg ?> </p>
        <?php }  ?>
    <?php } ?>
</div>

<?php } else { ?>

<div>
    <label for="<?= $elementName ?>" class="<?= $classLabel ?>">
        <p class="first-letter:uppercase">
            <?= e($label) ?>
        </p>
    </label>
    <textarea 
        <?php if ($disabled == true) {
            echo 'disabled';
        }  ?>
        class="<?= $classInput ?>" 
        id="<?= $elementName ?>" 
        name="<?= $elementName ?>"
        <?php if (!empty($placeholder)) {
            echo 'placeholder="'.$placeholder.'"';
        }  ?>
        rows="<?= $rows ?>"><?= e($value) ?></textarea>

    <?php if (!empty($errors["$elementName"])) { ?>
        <?php foreach ($errors["$elementName"] as $msg) {  ?>
            <p class="mt-1 text-sm text-red-600 dark:text-red-400"> <?= $msg ?> </p>
        <?php }  ?>
    <?php } ?>
</div>

<?php } ?>