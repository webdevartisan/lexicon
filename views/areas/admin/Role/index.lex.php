{% extends "back.lex.php" %}

{% block title %}Roles{% endblock %}
{% block subtitle %}Control panel roles gate admin areas; blog roles are assigned to collaborators inside a blog.{% endblock %}

{% block body %}
<?php
$scopeLabels = [
    'system' => ['Control panel', 'Grant access to admin areas via permissions'],
    'blog' => ['Blog collaboration', 'Assigned to collaborators within a single blog'],
];
$byScope = ['system' => [], 'blog' => []];
foreach ($roles as $r) {
    $byScope[$r['scope'] ?? 'blog'][] = $r;
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex justify-end mb-4">
        {% cmp="btn" href="/admin/roles/new" variant="blue" icon="plus" label="New Role" %}
    </div>

    <?php foreach ($scopeLabels as $scope => [$scopeTitle, $scopeHint]) { ?>
    <div class="mb-5">
        <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50"><?= e($scopeTitle) ?></h2>
        <p class="text-xs text-slate-500 dark:text-zink-300 mb-3"><?= e($scopeHint) ?></p>
        <div class="card">
            <div class="card-body p-0 overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="text-left bg-slate-100 dark:bg-zink-600">
                        <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                            <th class="px-3.5 py-2.5 font-semibold">Name</th>
                            <th class="px-3.5 py-2.5 font-semibold">Slug</th>
                            <th class="px-3.5 py-2.5 font-semibold">Description</th>
                            <th class="px-3.5 py-2.5 font-semibold">Level</th>
                            <th class="px-3.5 py-2.5 font-semibold">Users</th>
                            <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                        <?php foreach ($byScope[$scope] as $role) { ?>
                        <?php
                            $showUrl = '/admin/roles/'.$role['id'].'/show';
                            $editUrl = '/admin/roles/'.$role['id'].'/edit';
                            $deleteUrl = '/admin/roles/'.$role['id'].'/delete';
                            $isSystem = !empty($role['is_system']);
                            ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                            <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">
                                <?= e($role['role_name']) ?>
                                <?php if ($isSystem) { ?>
                                <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide rounded-full border bg-slate-100 text-slate-500 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500" title="Shipped with the platform; cannot be deleted">
                                    <i data-lucide="lock" class="size-2.5"></i> System
                                </span>
                                <?php } ?>
                            </td>
                            <td class="px-3.5 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500">
                                    <?= e($role['role_slug']) ?>
                                </span>
                            </td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300 whitespace-normal max-w-md"><?= e((string) ($role['description'] ?? '')) ?></td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($role['level'] ?? '')) ?></td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) ($role['users_count'] ?? 0) ?></td>
                            <td class="px-3.5 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    {% cmp="icon-action" href="{$showUrl}" icon="shield" tip="Manage permissions" %}
                                    {% cmp="icon-action" href="{$editUrl}" icon="pencil" tip="Edit role" %}
                                    <?php if (!$isSystem) { ?>
                                    {% cmp="icon-action" href="{$deleteUrl}" icon="trash-2" tip="Delete role" danger %}
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <?php if (empty($byScope[$scope])) { ?>
                        <tr>
                            <td colspan="6" class="px-3.5 py-6 text-center text-sm text-slate-400 dark:text-zink-300">No <?= e(strtolower($scopeTitle)) ?> roles yet.</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
