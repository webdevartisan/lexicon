<?php
$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$firstName = $user['first_name'] ?? '';
$lastName = $user['last_name'] ?? '';
$passwordHint = !empty($user['id']) ? 'Leave blank to keep the current password.' : '';
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    {% cmp="input" type="text" label="Username" name="username" value="{$username}" required %}
    {% cmp="input" type="email" label="Email" name="email" value="{$email}" required %}
    {% cmp="input" type="text" label="First Name" name="first_name" value="{$firstName}" %}
    {% cmp="input" type="text" label="Last Name" name="last_name" value="{$lastName}" %}
    {% cmp="input" type="password" label="Password" name="password" underlabel="{$passwordHint}" %}

    <div class="md:col-span-2">
        <span class="inline-block mb-2 text-base font-medium">Roles</span>
        <?php
        // Group by scope so it's clear what each grant unlocks
        $roleGroups = [
            'system' => ['Control panel roles', 'Unlock admin areas through their permissions'],
            'blog' => ['Blog roles', 'Site-wide content roles used for blog collaboration'],
        ];
$rolesByScope = ['system' => [], 'blog' => []];
foreach ($roles as $r) {
    $rolesByScope[$r['scope'] ?? 'blog'][] = $r;
}
?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($roleGroups as $scope => [$groupLabel, $groupHint]) { ?>
            <?php if (empty($rolesByScope[$scope])) {
                continue;
            } ?>
            <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300"><?= e($groupLabel) ?></p>
                <p class="text-xs text-slate-400 dark:text-zink-400 mb-2"><?= e($groupHint) ?></p>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    <?php foreach ($rolesByScope[$scope] as $role) { ?>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="roles[]" value="<?= e((string) $role['id']) ?>"
                               class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500"
                            <?php if (!empty($user['roles']) && in_array($role['role_slug'], $user['roles'])) { ?>checked<?php } ?>>
                        <?= e($role['role_name']) ?>
                    </label>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
