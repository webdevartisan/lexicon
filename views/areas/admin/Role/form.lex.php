<?php
$roleName = old('role_name', $role['role_name'] ?? '');
$description = old('description', $role['description'] ?? '');
$scope = old('scope', $role['scope'] ?? 'blog');
$level = old('level', (string) ($role['level'] ?? '10'));
$isSystem = !empty($role['is_system']);
$scopeOptions = [
    'system' => 'Control panel — grants access to admin areas',
    'blog' => 'Blog collaboration — assignable to blog collaborators',
];
?>
<div class="grid grid-cols-1 gap-5">
    <?php if ($isSystem) { ?>
    <div class="px-4 py-3 rounded-md border border-slate-200 bg-slate-50 text-slate-600 dark:bg-zink-600/40 dark:border-zink-500 dark:text-zink-200 text-sm flex items-start gap-2">
        <i data-lucide="lock" class="size-4 mt-0.5 shrink-0"></i>
        <span>This is a system role. The platform references it by slug, so only the description can be changed here. Its permissions are managed on the role's permission page.</span>
    </div>
    <div>
        <span class="inline-block mb-2 text-base font-medium">Name</span>
        <p class="text-sm text-slate-900 dark:text-zink-50"><?= e($roleName) ?> <span class="text-xs text-slate-400">(<?= e($role['role_slug']) ?>)</span></p>
    </div>
    <?php } else { ?>
    {% cmp="input" type="text" label="Name" name="role_name" value="{$roleName}" required underlabel="The slug is generated from the name at creation and never changes." %}
    <?php } ?>

    {% cmp="input" type="text" label="Description" name="description" value="{$description}" placeholder="What is this role for?" %}

    <?php if (!$isSystem) { ?>
    <div>
        <label for="scope" class="inline-block mb-2 text-base font-medium">Scope</label>
        <select id="scope" name="scope" class="form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100 w-full" required>
            <?php foreach ($scopeOptions as $value => $label) { ?>
            <option value="<?= e($value) ?>"<?= $scope === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php } ?>
        </select>
        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">Control panel roles unlock admin areas through their permissions. Blog roles appear in collaborator invitations.</p>
    </div>

    {% cmp="input" type="number" label="Level" name="level" value="{$level}" required underlabel="Hierarchy weight from 0 to 99. Higher outranks lower; 100 is reserved for Administrator." %}
    <?php } ?>
</div>
