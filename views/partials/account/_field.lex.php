<?php
// Shared field row for the profile, public page and social panels.
$fieldErrors = errors();

/**
 * One field row. Errors set aria-invalid and aria-describedby so the message is
 * announced with the control rather than stranded.
 */
$field = function (string $name, string $label, string $type = 'text', string $value = '', string $placeholder = '', string $hint = '') use ($fieldErrors): void {
    $invalid = !empty($fieldErrors[$name]);
    $describedBy = $invalid ? $name.'_error' : ($hint !== '' ? $name.'_hint' : '');
    ?>
    <div class="acct-field<?= $invalid ? ' is-invalid' : '' ?>">
        <label class="acct-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea') { ?>
        <textarea class="acct-input" name="<?= e($name) ?>" id="<?= e($name) ?>" rows="4"
                  <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="'.e($describedBy).'"' : '' ?>><?= e(old($name) ?? $value) ?></textarea>
        <?php } else { ?>
        <input class="acct-input" type="<?= e($type) ?>" name="<?= e($name) ?>" id="<?= e($name) ?>"
               value="<?= e(old($name) ?? $value) ?>"<?= $placeholder !== '' ? ' placeholder="'.e($placeholder).'"' : '' ?>
               <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="'.e($describedBy).'"' : '' ?>>
        <?php } ?>
        <?php if ($hint !== '') { ?><p class="acct-hint" id="<?= e($name) ?>_hint"><?= e($hint) ?></p><?php } ?>
        <?php foreach ($fieldErrors[$name] ?? [] as $message) { ?>
        <p class="acct-error" id="<?= e($name) ?>_error"><?= e($message) ?></p>
        <?php } ?>
    </div>
<?php };
?>
