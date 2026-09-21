<?php
/**
 * Account fields for the new-user form. The edit form has its own, fuller
 * layout in edit.lex.php, because an existing account has profile, preference
 * and link data a brand-new one does not.
 */
$handle = $user['handle'] ?? '';
$email = $user['email'] ?? '';
$firstName = $user['first_name'] ?? '';
$lastName = $user['last_name'] ?? '';
$checkboxClass = 'form-radio border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    {% cmp="input" type="text" label="Tag" name="handle" value="{$handle}" required %}
    {% cmp="input" type="email" label="Email" name="email" value="{$email}" required %}
    {% cmp="input" type="text" label="First Name" name="first_name" value="{$firstName}" %}
    {% cmp="input" type="text" label="Last Name" name="last_name" value="{$lastName}" %}
    {% cmp="input" type="password" label="Password" name="password" required %}

    <fieldset class="md:col-span-2">
        <legend class="inline-block mb-2 text-base font-medium">Site role</legend>
        <?php if ($canAssignSiteRoles) { ?>
        <p class="text-xs text-slate-400 dark:text-zink-400 mb-2">Unlocks control panel areas. Blog roles are given per blog, after the account exists.</p>
        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500 flex flex-wrap gap-x-5 gap-y-2">
            <?php foreach ($roles as $role) {
                $checked = (string) old('role_id', '') !== ''
                    ? (int) old('role_id') === (int) $role['id']
                    : $role['role_slug'] === $defaultRole; ?>
            <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" name="role_id" value="<?= e((string) $role['id']) ?>" class="<?= $checkboxClass ?>" <?= $checked ? 'checked' : '' ?>>
                <?= e($role['role_name']) ?>
            </label>
            <?php } ?>
        </div>
        <?php } else { ?>
        <p class="text-sm text-slate-500 dark:text-zink-300">New accounts start as <strong><?= e($defaultRole) ?></strong>. Only an administrator can choose a different site role.</p>
        <?php } ?>
    </fieldset>
</div>
