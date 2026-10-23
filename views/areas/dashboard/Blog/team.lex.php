{% extends "back.lex.php" %}
{% block title %}{{ t('navigation.team') }}{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">{{ t('dashboard.team.subtitle') }}</span>{% endblock %}
{% block body %}
<?php
$roleBadge = [
    'owner' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'editor' => 'bg-custom-50 text-custom-700 border-custom-100 dark:bg-custom-500/20 dark:border-custom-800 dark:text-custom-300',
    'author' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'contributor' => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/40 dark:border-sky-800',
    'reviewer' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
];
$roleDocs = [];
foreach (['owner', 'editor', 'author', 'contributor', 'reviewer'] as $shipped) {
    $roleDocs[$shipped] = $t('dashboard.team.roles.'.$shipped);
}

// Reviewer role is workflow-only. Drop it from the doc grid when the pipeline is off so we don't promise something the blog can't deliver.
if (empty($workflowEnabled)) {
    unset($roleDocs['reviewer']);
}

// Custom control-panel roles have no shipped doc; explain where they come from.
foreach (($roles ?? []) as $r) {
    if (!isset($roleDocs[$r])) {
        $roleDocs[$r] = $t('dashboard.team.roles.custom');
    }
}

$fallbackBadge = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500';
$initials = static function (string $name): string {
    $parts = preg_split('/[\s_.-]+/', trim($name)) ?: [];
    $a = mb_substr($parts[0] ?? $name, 0, 1);
    $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

    return mb_strtoupper($a.$b) ?: 'U';
};
$roleName = static fn (string $role): string => (string) ($roleLabels[$role] ?? ucfirst($role));
$turnOn = explode('{link}', $t('dashboard.team.workflowOffTurnOn'), 2);
$removeLabel = $t('dashboard.team.members.remove');
$emailLabel = $t('dashboard.team.invite.email');
$sendInviteLabel = $t('dashboard.team.invite.send');
$cancelLabel = $t('dashboard.team.invite.cancel');
$resendLabel = $t('dashboard.team.invite.resend');
$dropLabel = $t('dashboard.team.invite.drop');
$passwordLabel = $t('dashboard.team.transfer.password');
$transferLabel = $t('dashboard.team.transfer.title');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if (empty($workflowEnabled)) { ?>
    <div class="mb-5 p-3 rounded-md border border-slate-200 bg-slate-50 dark:bg-zink-700 dark:border-zink-600 flex items-start gap-2 text-xs text-slate-600 dark:text-zink-200">
        <i data-lucide="info" class="size-4 shrink-0 mt-0.5 text-slate-400"></i>
        <span>
            <?= e($t('dashboard.team.workflowOff')) ?>
            <?= e($turnOn[0]) ?><a href="<?= e(lurl('/dashboard/blog/'.(int) $blog['id'].'/settings#discussion')) ?>" class="font-medium text-custom-500 hover:text-custom-600 underline"><?= e($t('navigation.editBlogSettings')) ?></a><?= e($turnOn[1] ?? '') ?>
        </span>
    </div>
    <?php } ?>

    <?php if (!empty($workflowHealth)) {
        $wh = $workflowHealth;
        $hasUnassigned = (int) ($wh['in_review_unassigned'] ?? 0) > 0;
        ?>
    <section class="card mb-6 <?= $hasUnassigned ? 'border-amber-200 dark:border-amber-500/30' : '' ?>">
        <div class="card-body">
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                <i data-lucide="activity" class="size-4 text-custom-500"></i>
                <?= e($t('dashboard.team.health.title')) ?>
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="p-3 rounded-md border border-slate-100 dark:border-zink-600">
                    <div class="text-[10px] uppercase tracking-wide font-medium text-slate-500 dark:text-zink-400"><?= e($t('dashboard.team.health.assigned')) ?></div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1"><?= (int) $wh['in_review_assigned'] ?></div>
                </div>
                <div class="p-3 rounded-md border <?= $hasUnassigned ? 'border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30' : 'border-slate-100 dark:border-zink-600' ?>">
                    <div class="text-[10px] uppercase tracking-wide font-medium <?= $hasUnassigned ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-zink-400' ?>"><?= e($t('dashboard.team.health.unassigned')) ?></div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1 flex items-center gap-2">
                        <?= (int) $wh['in_review_unassigned'] ?>
                        <?php if ($hasUnassigned) { ?>
                        <i data-lucide="alert-circle" class="size-4 text-amber-500"></i>
                        <?php } ?>
                    </div>
                </div>
                <div class="p-3 rounded-md border border-slate-100 dark:border-zink-600">
                    <div class="text-[10px] uppercase tracking-wide font-medium text-slate-500 dark:text-zink-400"><?= e($t('dashboard.team.health.needsChanges')) ?></div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1"><?= (int) $wh['needs_changes'] ?></div>
                </div>
                <div class="p-3 rounded-md border border-slate-100 dark:border-zink-600">
                    <div class="text-[10px] uppercase tracking-wide font-medium text-slate-500 dark:text-zink-400"><?= e($t('dashboard.team.health.approved')) ?></div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1"><?= (int) $wh['approved'] ?></div>
                </div>
            </div>

            <?php if (!empty($wh['recent'])) { ?>
            <div class="border-t border-slate-100 dark:border-zink-600 pt-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-2"><?= e($t('dashboard.team.health.inReviewNow')) ?></h3>
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($wh['recent'] as $row) { ?>
                    <li class="py-2">
                        <a href="<?= e(lurl('/dashboard/post/'.((int) $row['id']).'/review')) ?>" class="flex items-center gap-3 group/post">
                            <span class="inline-flex items-center justify-center size-7 rounded-md bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300 shrink-0">
                                <i data-lucide="lock" class="size-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate group-hover/post:text-custom-500"><?= e($row['title']) ?></p>
                                <p class="text-xs text-slate-500 dark:text-zink-400 mt-0.5">
                                    <?= e($t('dashboard.team.health.byLine', ['author' => $row['author_handle'], 'reviewer' => $row['reviewer_handle']])) ?>
                                </p>
                            </div>
                            <i data-lucide="chevron-right" class="size-4 text-slate-400 shrink-0 group-hover/post:text-custom-500"></i>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Active members -->
        <section class="lg:col-span-2 card">
            <div class="card-body">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                    <i data-lucide="users" class="size-4 text-custom-500"></i>
                    <?= e($t('dashboard.team.members.title')) ?>
                </h2>

                {% if members|empty %}
                <div class="text-center py-10">
                    <i data-lucide="user-plus" class="size-10 text-slate-400 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('dashboard.team.members.empty')) ?></p>
                </div>
                {% else %}
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($members as $m): %}
                    <li class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                        <span class="flex items-center justify-center size-9 rounded-full bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300 text-xs font-semibold shrink-0">
                            <?= e($initials((string) $m['handle'])) ?>
                        </span>
                        <div class="min-w-0 grow">
                            <p class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate"><?= e($m['handle']) ?></p>
                            <p class="text-xs text-slate-500 dark:text-zink-300 truncate"><?= e($m['email'] ?? '') ?></p>
                        </div>

                        <?php $currentRole = (string) ($m['role'] ?? ''); ?>
                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/<?= (int) ($m['user_id'] ?? 0) ?>/role" class="m-0 shrink-0 w-40">
                            {{ csrf_field() }}
                            <label for="role-<?= (int) ($m['user_id'] ?? 0) ?>" class="sr-only"><?= e($t('dashboard.team.members.role')) ?></label>
                            <select id="role-<?= (int) ($m['user_id'] ?? 0) ?>" name="role" data-auto-submit
                                class="form-select appearance-none border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-1 pr-7">
                                <?php foreach ($roles as $role) { ?>
                                    <option value="<?= e($role) ?>" <?= $role === $currentRole ? 'selected' : '' ?>><?= e($roleName($role)) ?></option>
                                <?php } ?>
                            </select>
                        </form>

                        <?php
                        $revokeConfirmMsg = $t('dashboard.team.members.removeConfirm', ['handle' => $m['handle']]);
$revokeConfirmAttr = 'data-confirm="'.e($revokeConfirmMsg).'"';
?>
                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/<?= (int) ($m['user_id'] ?? 0) ?>/revoke" class="m-0 shrink-0">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="red" icon="user-minus" label="{$removeLabel}" dataBtn="{$revokeConfirmAttr}" %}
                        </form>
                    </li>
                    {% endforeach %}
                </ul>
                {% endif %}
            </div>
        </section>

        <!-- Invite collaborator -->
        <aside class="card self-start">
            <div class="card-body">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                    <i data-lucide="mail-plus" class="size-4 text-custom-500"></i>
                    <?= e($t('dashboard.team.invite.title')) ?>
                </h2>

                <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/invite" class="space-y-3">
                    {{ csrf_field() }}
                    {% cmp="input" type="email" name="email" label="{$emailLabel}" placeholder="person@example.com" required="true" %}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="invite-role" class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($t('dashboard.team.invite.role')) ?></label>
                            <span id="invite-role-hint" class="text-[11px] text-slate-500 dark:text-zink-300"></span>
                        </div>
                        <select id="invite-role" name="role"
                            class="form-select w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-2">
                            <?php foreach ($roles as $role) { ?>
                                <option value="<?= e($role) ?>" data-doc="<?= e($roleDocs[$role] ?? '') ?>" <?= $role === 'author' ? 'selected' : '' ?>><?= e($roleName($role)) ?></option>
                            <?php } ?>
                        </select>
                        <p id="invite-role-doc" class="text-[11px] text-slate-500 dark:text-zink-300 mt-1 leading-snug min-h-[2em]"></p>
                    </div>
                    {% cmp="btn" type="submit" variant="blue" icon="send" label="{$sendInviteLabel}" addClass="w-full" %}
                </form>

                {% if pendingInvites|notempty %}
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300 mt-6 mb-2"><?= e($t('dashboard.team.invite.pending')) ?></h3>
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($pendingInvites as $inv): %}
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-zink-100 truncate"><?= e($inv['email'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-500 dark:text-zink-300"><?= e($t('dashboard.team.invite.expires', ['role' => $roleName((string) ($inv['role'] ?? '')), 'when' => relative_time($inv['expires_at'] ?? null)])) ?></p>
                        </div>
                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/cancel-invite" class="m-0 shrink-0">
                            {{ csrf_field() }}
                            <input type="hidden" name="email" value="<?= e($inv['email'] ?? '') ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="x" label="{$cancelLabel}" %}
                        </form>
                    </li>
                    {% endforeach %}
                </ul>
                {% endif %}

                <?php if (!empty($expiredInvites)) { ?>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300 mt-6 mb-2 flex items-center gap-1">
                    <i data-lucide="alert-circle" class="size-3.5"></i>
                    <?= e($t('dashboard.team.invite.expiredTitle')) ?>
                </h3>
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($expiredInvites as $inv) { ?>
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-zink-100 truncate"><?= e($inv['email'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-500 dark:text-zink-300"><?= e($t('dashboard.team.invite.expired', ['role' => $roleName((string) ($inv['role'] ?? '')), 'date' => local_datetime($inv['expires_at'] ?? null, 'M j, Y')])) ?></p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/invite" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="email" value="<?= e($inv['email'] ?? '') ?>">
                                <input type="hidden" name="role" value="<?= e($inv['role'] ?? '') ?>">
                                {% cmp="btn" type="submit" variant="blue" icon="send" label="{$resendLabel}" %}
                            </form>
                            <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/cancel-invite" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="email" value="<?= e($inv['email'] ?? '') ?>">
                                {% cmp="btn" type="submit" variant="slate" icon="x" label="{$dropLabel}" %}
                            </form>
                        </div>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </aside>
    </div>

    <?php if ($canTransfer && !empty($members)) { ?>
    <section class="card mt-6">
        <div class="card-body">
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1 flex items-center gap-2">
                <i data-lucide="key-round" class="size-4 text-custom-500"></i>
                <?= e($transferLabel) ?>
            </h2>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-4"><?= e($t('dashboard.team.transfer.text')) ?></p>

            <?php $transferConfirm = 'data-confirm="'.e($t('dashboard.team.transfer.confirm')).'"'; ?>
            <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/transfer" class="grid grid-cols-1 lg:grid-cols-3 gap-3 items-end">
                {{ csrf_field() }}
                <div>
                    <label for="new-owner" class="text-sm font-medium text-slate-700 dark:text-zink-100 mb-1 block"><?= e($t('dashboard.team.transfer.newOwner')) ?></label>
                    <select id="new-owner" name="new_owner_id" required
                        class="form-select w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-2">
                        <?php foreach ($members as $m) { ?>
                            <option value="<?= (int) $m['user_id'] ?>"><?= e($m['handle']) ?> (<?= e($roleName((string) $m['role'])) ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                {% cmp="input" type="password" name="password" label="{$passwordLabel}" required="true" %}
                {% cmp="btn" type="submit" variant="red" icon="key-round" label="{$transferLabel}" dataBtn="{$transferConfirm}" %}
            </form>
        </div>
    </section>
    <?php } ?>

    <!-- Role reference -->
    <section class="card mt-6">
        <div class="card-body">
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                <i data-lucide="shield" class="size-4 text-custom-500"></i>
                <?= e($t('dashboard.team.roles.title')) ?>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <?php foreach ($roleDocs as $rDoc => $desc) { ?>
                    <div class="p-3 rounded-lg border border-slate-100 dark:border-zink-600">
                        <span class="inline-flex items-center px-2 py-0.5 mb-1.5 text-[11px] font-medium rounded-full border <?= $roleBadge[$rDoc] ?? $fallbackBadge ?>"><?= e($roleName($rDoc)) ?></span>
                        <p class="text-xs text-slate-500 dark:text-zink-300 leading-relaxed"><?= e($desc) ?></p>
                    </div>
                <?php } ?>
            </div>
            <p class="mt-4 text-xs text-slate-500 dark:text-zink-300 flex items-start gap-2">
                <i data-lucide="info" class="size-4 shrink-0 mt-0.5"></i>
                <?= e($t('dashboard.team.roles.note')) ?>
            </p>
        </div>
    </section>

</div>
{% endblock %}
{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/tooltip.js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>">
// Live-update the small role explainer under the role select on the invite form.
(function () {
    var sel = document.getElementById('invite-role');
    var doc = document.getElementById('invite-role-doc');
    if (!sel || !doc) return;
    function paint() {
        var opt = sel.options[sel.selectedIndex];
        doc.textContent = opt ? (opt.getAttribute('data-doc') || '') : '';
    }
    sel.addEventListener('change', paint);
    paint();
})();
</script>
{% endblock %}
