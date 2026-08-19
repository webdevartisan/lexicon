{% extends "back.lex.php" %}

{% block title %}Users{% endblock %}
{% block subtitle %}Manage accounts, roles, and access across the site.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/users';
$hasFilters = $q !== '' || $active !== '' || $role !== '';
$emptyTitle = $hasFilters ? 'No users match these filters' : 'No users yet';
$emptyMessage = $hasFilters ? 'Try a different name, role, or status.' : 'Create the first account to get started.';

$activeChoices = ['' => 'Any status', 'yes' => 'Active only', 'no' => 'Deactivated only'];

$roleChoices = ['' => 'All roles'];
foreach ($roleOptions as $r) {
    $roleChoices[(string) $r['role_slug']] = (string) ($r['role_name'] ?? $r['role_slug']);
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" data-table-filter class="flex flex-col sm:flex-row sm:items-center gap-3 grow">
            {% cmp="input" type="search" name="q" value="{$q}" placeholder="Search username, email, or name..." %}
            {% cmp="select" name="role" options="{$roleChoices}" selectedKey="{$role}" onchange="this.form.submit()" %}
            {% cmp="select" name="active" options="{$activeChoices}" selectedKey="{$active}" onchange="this.form.submit()" %}
            {% cmp="btn" type="submit" variant="blue" icon="search" label="Search" %}
            <?php /* Marked for table-sort.js: the region swap does not reach
                     into the filter form, so this is refreshed separately. */ ?>
            <span data-table-sync="filter-clear" class="contents">
            <?php if ($hasFilters) { ?>
            {% cmp="btn" href="{$basePath}" variant="slate" icon="x" label="Clear" %}
            <?php } ?>
            </span>
        </form>
        <div class="shrink-0">
            {% cmp="btn" href="/admin/users/new" variant="blue" icon="user-plus" label="New User" %}
        </div>
    </div>

    <div data-table-region>
    {% if users|empty %}
        {% cmp="empty-state" icon="users" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="id" label="ID" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="username" label="Username" %}
                        <th class="px-3.5 py-2.5 font-semibold">Name</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="email" label="Email" %}
                        <th class="px-3.5 py-2.5 font-semibold">Roles</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="active" label="Status" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="created" label="Joined" %}
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($users as $user): %}
                    <?php
                        $editUrl = '/admin/users/'.$user['id'].'/edit';
$deleteUrl = '/admin/users/'.$user['id'].'/delete';
$activeStatus = !empty($user['is_active']) ? 'active' : 'inactive';
$activeLabel = !empty($user['is_active']) ? 'Active' : 'Inactive';
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">{{ user['id'] }}</td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">{{ user['username'] }}</td>
                        <td class="px-3.5 py-2.5">
                            <?= e(trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: '—') ?>
                        </td>
                        <td class="px-3.5 py-2.5">{{ user['email'] }}</td>
                        <td class="px-3.5 py-2.5">
                            <?php foreach (array_filter(explode(',', (string) $user['roles'])) as $roleName) { ?>
                            <span class="inline-flex items-center px-2 py-0.5 mr-1 text-[10px] font-medium rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500">
                                <?= e($roleName) ?>
                            </span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$activeStatus}" label="{$activeLabel}" %}
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(local_datetime($user['created_at'] ?? null, 'M j, Y')) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                {% cmp="icon-action" href="{$editUrl}" icon="pencil" tip="Edit user" %}
                                {% cmp="icon-action" href="{$deleteUrl}" icon="trash-2" tip="Delete user" danger %}
                            </div>
                        </td>
                    </tr>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="user" itemPlural="users" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
