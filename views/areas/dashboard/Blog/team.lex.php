{% extends "back.lex.php" %}
{% block title %}{{ t('navigation.team') }}{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">{{ t('dashboard.team.subtitle') }}</span>{% endblock %}
{% block actions %}
<?php
$inviteOpenLabel = $t('dashboard.team.invite.open');
$transferOpenLabel = $t('dashboard.team.transfer.title');
// A phone header has no room for two labelled buttons beside the title, so this one keeps only its icon there.
$iconOnlyOnPhone = 'max-sm:[&>span]:hidden';
$transferAttrs = ['aria-label' => $transferOpenLabel, 'title' => $transferOpenLabel];
?>
<?php if ($canTransfer && !empty($members)) { ?>
{% cmp="btn" variant="slate" icon="key-round" label="{$transferOpenLabel}" dataModalTarget="teamTransferModal" addClass="{$iconOnlyOnPhone}" attrs="{$transferAttrs}" %}
<?php } ?>
{% cmp="btn" variant="blue" icon="user-plus" label="{$inviteOpenLabel}" dataModalTarget="teamInviteModal" %}
{% endblock %}
{% block body %}
<?php
$blogId = (int) $blog['id'];
$teamBase = lurl('/dashboard/blog/'.$blogId.'/team');

$roleBadge = [
    'owner' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'editor' => 'bg-custom-50 text-custom-700 border-custom-100 dark:bg-custom-500/20 dark:border-custom-800 dark:text-custom-300',
    'author' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'contributor' => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/40 dark:border-sky-800',
    'reviewer' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
];
$fallbackBadge = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500';

$roleDocs = [];
foreach (['owner', 'editor', 'author', 'contributor', 'reviewer'] as $shipped) {
    $roleDocs[$shipped] = $t('dashboard.team.roles.'.$shipped);
}

// Reviewer role is workflow-only. Drop it from the glossary when the pipeline is off so we don't promise something the blog can't deliver.
if (empty($workflowEnabled)) {
    unset($roleDocs['reviewer']);
}

// Custom control-panel roles have no shipped doc; explain where they come from.
foreach (($roles ?? []) as $customRole) {
    if (!isset($roleDocs[$customRole])) {
        $roleDocs[$customRole] = $t('dashboard.team.roles.custom');
    }
}

$initials = static function (string $name): string {
    $parts = preg_split('/[\s_.-]+/', trim($name)) ?: [];
    $a = mb_substr($parts[0] ?? $name, 0, 1);
    $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

    return mb_strtoupper($a.$b) ?: 'U';
};
$roleName = static function (string $role) use ($roleLabels): string {
    return (string) ($roleLabels[$role] ?? ucfirst($role));
};
$roleChip = static function (string $role) use ($roleBadge, $fallbackBadge, $roleName): string {
    return '<span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border '
        .($roleBadge[$role] ?? $fallbackBadge).'">'.e($roleName($role)).'</span>';
};
$helpButton = static function () use ($t): string {
    $label = e($t('dashboard.team.roles.title'));
    $icon = fragment()->remember(
        'lucide:help-circle:team',
        static function (): string {
            return '<i data-lucide="help-circle" class="size-4" aria-hidden="true"></i>';
        },
        31536000,
        false
    );

    return '<button type="button" data-modal-target="teamRolesModal" title="'.$label.'" aria-label="'.$label.'"'
        .' class="shrink-0 p-1 rounded-md text-slate-400 hover:text-custom-500 hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-600'
        .' transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">'.$icon.'</button>';
};

$roleOptions = static function (array $roleList, string $selected) use ($roleDocs, $roleName): string {
    $html = '';
    foreach ($roleList as $role) {
        $html .= '<option value="'.e($role).'" data-doc="'.e($roleDocs[$role] ?? '').'"'.($role === $selected ? ' selected' : '').'>'
            .e($roleName($role)).'</option>';
    }

    return $html;
};
// The invite and change-role dialogs ask the same question, so they share one field.
$roleField = static function (string $selectId, string $selected) use ($t, $roles, $roleOptions, $helpButton): string {
    return '<div><div class="flex items-center justify-between mb-1">'
        .'<label for="'.e($selectId).'" class="text-sm font-medium text-slate-700 dark:text-zink-100">'.e($t('dashboard.team.invite.role')).'</label>'
        .$helpButton().'</div>'
        .'<select id="'.e($selectId).'" name="role" data-native data-role-doc="'.e($selectId).'-doc"'
        .' class="form-select w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-2">'
        .$roleOptions($roles, $selected).'</select>'
        .'<p id="'.e($selectId).'-doc" class="text-[11px] text-slate-500 dark:text-zink-300 mt-1 leading-snug min-h-[2em]"></p></div>';
};

// Members and invitations share one table, so both are normalised into the same row shape here.
$rows = [];
foreach ($members as $m) {
    $userId = (int) ($m['user_id'] ?? 0);
    $rows[] = [
        'kind' => 'member',
        'userId' => $userId,
        'name' => (string) $m['handle'],
        'detail' => (string) ($m['email'] ?? ''),
        'role' => (string) ($m['role'] ?? ''),
        'status' => 'active',
        'statusLabel' => $t('dashboard.team.status.active'),
        'actions' => [[
            'label' => $t('dashboard.team.members.changeRole'),
            'icon' => 'user-cog',
            'data' => [
                'modal-target' => 'teamRoleModal',
                'role-action' => $teamBase.'/'.$userId.'/role',
                'role-member' => (string) $m['handle'],
                'role-current' => (string) ($m['role'] ?? ''),
                'role-label' => $roleName((string) ($m['role'] ?? '')),
            ],
        ], [
            'label' => $t('dashboard.team.members.remove'),
            'icon' => 'user-minus',
            'post' => $teamBase.'/'.$userId.'/revoke',
            'confirm' => $t('dashboard.team.members.removeConfirm', ['handle' => $m['handle']]),
            'danger' => true,
        ]],
    ];
}

$inviteRows = static function (array $invites, bool $expired) use ($t, $teamBase, $roleName): array {
    $out = [];
    foreach ($invites as $inv) {
        $email = (string) ($inv['email'] ?? '');
        $role = (string) ($inv['role'] ?? '');
        $dropItem = [
            'label' => $t($expired ? 'dashboard.team.invite.drop' : 'dashboard.team.invite.cancel'),
            'icon' => 'x',
            'post' => $teamBase.'/cancel-invite',
            'fields' => ['email' => $email],
            'danger' => true,
        ];
        $actions = [$dropItem];
        if ($expired) {
            array_unshift($actions, [
                'label' => $t('dashboard.team.invite.resend'),
                'icon' => 'send',
                'post' => $teamBase.'/invite',
                'fields' => ['email' => $email, 'role' => $role],
            ]);
        }
        $out[] = [
            'kind' => 'invite',
            'name' => $email,
            'detail' => $expired
                ? $t('dashboard.team.invite.expired', ['date' => local_datetime($inv['expires_at'] ?? null, 'M j, Y')])
                : $t('dashboard.team.invite.expires', ['when' => relative_time($inv['expires_at'] ?? null)]),
            'role' => $role,
            'status' => $expired ? 'inactive' : 'pending',
            'statusLabel' => $t($expired ? 'dashboard.team.status.expired' : 'dashboard.team.status.invited'),
            'actions' => $actions,
        ];
    }

    return $out;
};
$rows = array_merge($rows, $inviteRows($pendingInvites ?? [], false), $inviteRows($expiredInvites ?? [], true));

$turnOn = explode('{link}', $t('dashboard.team.workflowOffTurnOn'), 2);
$cell = 'px-2.5 sm:px-3.5 py-2.5';
$colClass = $cell.' font-semibold';
$statusClass = 'hidden sm:table-cell';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if (!empty($unassignedInReview)) { ?>
    <div class="mb-5 p-3 rounded-md border border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 flex items-center justify-between gap-3 text-sm text-amber-700 dark:text-amber-300">
        <span><?= e($t('dashboard.team.health.alert', ['count' => (int) $unassignedInReview])) ?></span>
        <a href="<?= e(lurl('/dashboard/blog/'.$blogId.'/review-queue')) ?>" class="shrink-0 font-medium underline"><?= e($t('navigation.reviewQueue')) ?></a>
    </div>
    <?php } ?>

    <div class="card">
        <?php if ($rows === []) { ?>
        <div class="card-body text-center py-12">
            <i data-lucide="user-plus" class="size-10 text-slate-400 mx-auto mb-2"></i>
            <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('dashboard.team.members.empty')) ?></p>
        </div>
        <?php } else { ?>
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="ltr:text-left rtl:text-right bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="<?= $colClass ?>"><?= e($t('dashboard.team.columns.member')) ?></th>
                        <th class="<?= $colClass ?>">
                            <span class="inline-flex items-center gap-1"><?= e($t('dashboard.team.columns.role')) ?> <?= $helpButton() ?></span>
                        </th>
                        <th class="<?= $colClass ?> <?= $statusClass ?>"><?= e($t('dashboard.team.columns.status')) ?></th>
                        <th class="<?= $colClass ?> ltr:text-right rtl:text-left"><?= e($t('dashboard.team.columns.actions')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    <?php foreach ($rows as $row) {
                        $rowName = $row['name'];
                        $rowStatus = $row['status'];
                        $rowStatusLabel = $row['statusLabel'];
                        $rowActions = $row['actions'];
                        ?>
                    <tr class="transition-colors hover:bg-slate-50/60 dark:hover:bg-zink-700/40">
                        <td class="<?= $cell ?>">
                            <div class="flex items-center gap-3">
                                <span class="hidden sm:flex items-center justify-center size-9 rounded-full bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300 text-xs font-semibold shrink-0">
                                    <?php if ($row['kind'] === 'member') { ?>
                                    <?= e($initials($row['name'])) ?>
                                    <?php } else { ?>
                                    {% cache 'lucide:mail:team-row' ttl=31536000 %}<i data-lucide="mail" class="size-4" aria-hidden="true"></i>{% endcache %}
                                    <?php } ?>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900 dark:text-zink-50 truncate max-w-[7rem] sm:max-w-[16rem]" title="<?= e($row['name']) ?>"><?= e($row['name']) ?></p>
                                    <p class="text-xs text-slate-500 dark:text-zink-300 truncate max-w-[7rem] sm:max-w-[16rem]"><?= e($row['detail']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="<?= $cell ?>">
                            <?= $roleChip($row['role']) ?>
                        </td>
                        <td class="<?= $cell ?> <?= $statusClass ?>">
                            {% cmp="status-badge" status="{$rowStatus}" label="{$rowStatusLabel}" %}
                        </td>
                        <td class="<?= $cell ?>">
                            <div class="flex items-center justify-end gap-1">
                                {% cmp="row-actions" title="{$rowName}" items="{$rowActions}" %}
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>

<?php
$emailLabel = $t('dashboard.team.invite.email');
$cancelText = $t('dashboard.team.modal.cancel');
$sendInviteLabel = $t('dashboard.team.invite.send');
$inviteTitle = $t('dashboard.team.invite.title');
ob_start(); ?>
<form method="POST" action="<?= e($teamBase.'/invite') ?>" id="teamInviteForm" class="space-y-3 text-left">
    <?= csrf_field() ?>
    {% cmp="input" type="email" name="email" label="{$emailLabel}" placeholder="person@example.com" required="true" %}
    <?= $roleField('invite-role', 'author') ?>
</form>
<?php $inviteBody = ob_get_clean(); ?>
{% cmp="modal" id="teamInviteModal" title="{$inviteTitle}" icon="mail-plus" body="{$inviteBody}" form="teamInviteForm" confirmText="{$sendInviteLabel}" confirmIcon="send" cancelText="{$cancelText}" %}

<?php
$roleModalTitle = $t('dashboard.team.members.changeRole');
$saveRoleLabel = $t('dashboard.team.members.saveRole');
ob_start(); ?>
<form method="POST" action="" id="teamRoleForm" class="space-y-3 text-left">
    <?= csrf_field() ?>
    <p class="text-sm font-medium text-slate-700 dark:text-zink-100" data-role-member></p>
    <?= $roleField('member-role', '') ?>
</form>
<?php $roleBody = ob_get_clean(); ?>
{% cmp="modal" id="teamRoleModal" title="{$roleModalTitle}" icon="user-cog" body="{$roleBody}" form="teamRoleForm" confirmText="{$saveRoleLabel}" confirmIcon="check" cancelText="{$cancelText}" %}

<?php
$rolesTitle = $t('dashboard.team.roles.title');
$closeText = $t('dashboard.team.modal.close');
ob_start(); ?>
<div class="space-y-2 text-left">
    <?php foreach ($roleDocs as $docRole => $desc) { ?>
    <div class="p-3 rounded-lg border border-slate-100 dark:border-zink-600">
        <div class="mb-1.5"><?= $roleChip($docRole) ?></div>
        <p class="text-xs text-slate-500 dark:text-zink-300 leading-relaxed"><?= e($desc) ?></p>
    </div>
    <?php } ?>
    <p class="pt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($t('dashboard.team.roles.note')) ?></p>
    <?php if (empty($workflowEnabled)) { ?>
    <p class="text-xs text-slate-500 dark:text-zink-300">
        <?= e($t('dashboard.team.workflowOff')) ?>
        <?= e($turnOn[0]) ?><a href="<?= e(lurl('/dashboard/blog/'.$blogId.'/settings#discussion')) ?>" class="font-medium text-custom-500 hover:text-custom-600 underline"><?= e($t('navigation.editBlogSettings')) ?></a><?= e($turnOn[1] ?? '') ?>
    </p>
    <?php } ?>
</div>
<?php $rolesBody = ob_get_clean(); ?>
{% cmp="modal" id="teamRolesModal" title="{$rolesTitle}" icon="shield" body="{$rolesBody}" noConfirm="true" cancelText="{$closeText}" %}

<?php if ($canTransfer && !empty($members)) {
    $transferTitle = $t('dashboard.team.transfer.title');
    $passwordLabel = $t('dashboard.team.transfer.password');
    ob_start(); ?>
<form method="POST" action="<?= e($teamBase.'/transfer') ?>" id="teamTransferForm" class="space-y-3 text-left">
    <?= csrf_field() ?>
    <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('dashboard.team.transfer.text')) ?> <?= e($t('dashboard.team.transfer.confirm')) ?></p>
    <div>
        <label for="new-owner" class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1 block"><?= e($t('dashboard.team.transfer.newOwner')) ?></label>
        <select id="new-owner" name="new_owner_id" required data-native
            class="form-select w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-2">
            <?php foreach ($members as $m) { ?>
            <option value="<?= (int) $m['user_id'] ?>"><?= e($m['handle']) ?> (<?= e($roleName((string) $m['role'])) ?>)</option>
            <?php } ?>
        </select>
    </div>
    {% cmp="input" type="password" name="password" label="{$passwordLabel}" required="true" autocomplete="current-password" %}
</form>
<?php $transferBody = ob_get_clean(); ?>
{% cmp="modal" id="teamTransferModal" title="{$transferTitle}" icon="key-round" variant="warning" body="{$transferBody}" form="teamTransferForm" confirmText="{$transferTitle}" cancelText="{$cancelText}" %}
<?php } ?>
{% endblock %}
{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
(function () {
    // Show the description of the chosen role under every role select.
    function paint(select) {
        var doc = document.getElementById(select.getAttribute('data-role-doc'));
        var option = select.options[select.selectedIndex];
        if (doc) doc.textContent = option ? (option.getAttribute('data-doc') || '') : '';
    }
    var selects = document.querySelectorAll('select[data-role-doc]');
    Array.prototype.forEach.call(selects, function (select) {
        select.addEventListener('change', function () { paint(select); });
        paint(select);
    });

    var form = document.getElementById('teamRoleForm');
    if (!form) return;
    var select = form.elements.role;

    // Capture phase, so the dialog is filled in before it opens and moves focus into it.
    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-role-action]');
        if (!button) return;

        form.action = button.dataset.roleAction;
        form.querySelector('[data-role-member]').textContent = button.dataset.roleMember;

        // A role the list no longer offers (reviewer with the workflow off) still has to show as current.
        var stale = select.querySelector('option[data-stale]');
        if (stale) stale.remove();
        var offered = Array.prototype.some.call(select.options, function (option) {
            return option.value === button.dataset.roleCurrent;
        });
        if (!offered) {
            var extra = new Option(button.dataset.roleLabel, button.dataset.roleCurrent);
            extra.setAttribute('data-stale', '');
            select.add(extra);
        }
        select.value = button.dataset.roleCurrent;
        paint(select);
    }, true);
})();
</script>
{% endblock %}
