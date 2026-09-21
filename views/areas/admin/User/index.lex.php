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
            {% cmp="input" type="search" name="q" value="{$q}" placeholder="Search tag, email, or name..." %}
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
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="handle" label="Tag" %}
                        <th class="px-3.5 py-2.5 font-semibold">Name</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="email" label="Email" %}
                        <th class="px-3.5 py-2.5 font-semibold">Site role</th>
                        <th class="px-3.5 py-2.5 font-semibold">Blogs</th>
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="active" label="Status" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="created" label="Joined" %}
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($users as $user): %}
                    <?php
                        $rowBase = '/admin/users/'.(int) $user['id'];
$isSuspended = $user['suspended_at'] !== null;
$activeStatus = $isSuspended ? 'suspended' : (!empty($user['is_active']) ? 'active' : 'inactive');
$activeLabel = $isSuspended ? 'Suspended' : (!empty($user['is_active']) ? 'Active' : 'Inactive');
$openCases = (int) ($user['open_cases'] ?? 0);
$unfounded = (int) ($user['unfounded_reports'] ?? 0);
$reportingPaused = \App\Services\ReporterStandingService::pausedUntil($user) !== null;
$openCasesLabel = $openCases.' open report'.($openCases === 1 ? '' : 's');
$openCasesHref = $canHandleReports ? '/admin/reports?status=active&author='.rawurlencode((string) $user['handle']) : '';
$unfoundedLabel = $unfounded.' unfounded report'.($unfounded === 1 ? '' : 's');
$reporterHref = $canHandleReports ? '/admin/reports/reporters/'.(int) $user['id'] : '';
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">{{ user['id'] }}</td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">
                            <a href="<?= e($rowBase) ?>" class="hover:text-custom-500 hover:underline">{{ user['handle'] }}</a>
                            <?php if ($openCases > 0) { ?><div>{% cmp="report-pill" label="{$openCasesLabel}" href="{$openCasesHref}" %}</div><?php } ?>
                            <?php if ($unfounded > 0) { ?><div>{% cmp="report-pill" tone="amber" label="{$unfoundedLabel}" href="{$reporterHref}" %}</div><?php } ?>
                            <?php if ($reportingPaused) { ?><div>{% cmp="report-pill" tone="slate" label="Reporting paused" href="{$reporterHref}" %}</div><?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <?= e(trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: '—') ?>
                        </td>
                        <td class="px-3.5 py-2.5">{{ user['email'] }}</td>
                        <td class="px-3.5 py-2.5">
                            <?php
                                $siteRoles = array_filter(explode(',', (string) $user['roles']));
if ($siteRoles === []) { ?>
                            <span class="text-xs text-slate-400 dark:text-zink-400">—</span>
                            <?php } foreach ($siteRoles as $roleName) { ?>
                            <span class="inline-flex items-center px-2 py-0.5 mr-1 text-[10px] font-medium rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500">
                                <?= e($roleName) ?>
                            </span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-xs text-slate-500 dark:text-zink-300">
                            <?php
$owned = (int) ($user['owned_blogs'] ?? 0);
$member = (int) ($user['member_blogs'] ?? 0);
$bits = [];
if ($owned > 0) {
    $bits[] = 'owns '.$owned;
}
if ($member > 0) {
    $bits[] = 'in '.$member;
}
echo $bits === [] ? '<span class="text-slate-400 dark:text-zink-400">—</span>' : e(implode(' · ', $bits));
?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$activeStatus}" label="{$activeLabel}" %}
                            <?php if ($isSuspended) { ?>
                            <span class="block text-[10px] text-slate-400 dark:text-zink-400 mt-0.5"><?= $user['suspended_until'] ? 'until '.e(local_datetime($user['suspended_until'], 'M j, Y')) : 'permanent' ?></span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(local_datetime($user['created_at'] ?? null, 'M j, Y')) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <?php
                                $rowTitle = '@'.$user['handle'];
                                $isSelfRow = (int) $user['id'] === (int) $actorId;
                                $rowIsAdmin = in_array('administrator', explode(',', (string) $user['roles']), true);
                                // An administrator row is only actionable by another administrator.
                                $mayAct = !$rowIsAdmin || $actorIsAdmin;
                                $canLogInAs = $canImpersonate && !$isSelfRow && !$rowIsAdmin && !$isSuspended && !empty($user['is_active']);
                                $rowActions = [
                                    ['label' => 'View user', 'icon' => 'eye', 'href' => $rowBase],
                                    ['label' => 'Preview profile', 'icon' => 'external-link', 'href' => $rowBase.'/preview', 'newTab' => true],
                                    ['label' => 'Edit profile', 'icon' => 'pencil', 'href' => $rowBase.'/edit', 'can' => $mayAct],
                                    ['label' => 'Reset password', 'icon' => 'key-round', 'href' => $rowBase.'/password', 'can' => $mayAct && !$isSelfRow],
                                    ['label' => 'Change site role', 'icon' => 'shield', 'href' => $rowBase.'/site-role', 'can' => $canAssignSiteRoles],
                                    ['label' => 'Change blog roles', 'icon' => 'users', 'href' => $rowBase.'/blog-roles', 'can' => $mayAct],
                                    ['label' => 'Log in as', 'icon' => 'log-in', 'href' => $rowBase.'/impersonate', 'can' => $canLogInAs],
                                    ['label' => $isSuspended ? 'Lift suspension' : 'Suspend user', 'icon' => $isSuspended ? 'unlock' : 'ban', 'href' => $rowBase.'/suspend', 'danger' => true, 'can' => $mayAct && !$isSelfRow],
                                    ['label' => 'Delete user', 'icon' => 'trash-2', 'href' => $rowBase.'/delete', 'danger' => true, 'can' => $mayAct && !$isSelfRow],
                                ];
                                ?>
                                {% cmp="row-actions" title="{$rowTitle}" items="{$rowActions}" %}
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
