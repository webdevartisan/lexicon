{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Team{% endblock %}
{% block subtitle %}Invite collaborators and manage who can write, review, and publish for this blog.{% endblock %}
{% block body %}
<?php
$roleBadge = [
    'owner' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'editor' => 'bg-custom-50 text-custom-700 border-custom-100 dark:bg-custom-500/20 dark:border-custom-800 dark:text-custom-300',
    'author' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'contributor' => 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/40 dark:border-sky-800',
    'reviewer' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
];
$roleDocs = [
    'owner' => 'Full control — invite collaborators, change roles, manage settings and publishing, and delete the blog. Structural: assigned by creating the blog.',
    'editor' => 'Operational lead — create and edit any post, move posts through the workflow, approve, and publish.',
    'author' => 'Create and edit their own posts and submit them for review. Cannot edit others\' posts or publish.',
    'contributor' => 'Lightest write access — draft and submit posts for review. Cannot publish or approve.',
    'reviewer' => 'Quality gate — review submissions, request changes, and approve. Cannot publish or manage the roster.',
];

// Reviewer role is workflow-only. Drop it from the doc grid when the pipeline is off so we don't promise something the blog can't deliver.
if (empty($workflowEnabled)) {
    unset($roleDocs['reviewer']);
}

$fallbackBadge = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500';
$initials = static function (string $name): string {
    $parts = preg_split('/[\s_.-]+/', trim($name)) ?: [];
    $a = mb_substr($parts[0] ?? $name, 0, 1);
    $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

    return mb_strtoupper($a.$b) ?: 'U';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex items-center justify-between gap-3 mb-5">
        <a href="/dashboard/blog/{{ blog.id }}/show"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="arrow-left" class="size-4"></i>
            <span>Back to blog</span>
        </a>
    </div>

    <?php if (empty($workflowEnabled)) { ?>
    <div class="mb-5 p-3 rounded-md border border-slate-200 bg-slate-50 dark:bg-zink-700 dark:border-zink-600 flex items-start gap-2 text-xs text-slate-600 dark:text-zink-200">
        <i data-lucide="info" class="size-4 shrink-0 mt-0.5 text-slate-400"></i>
        <span>
            Editorial workflow is off for this blog, so reviewer-related controls (review queue, reviewer role, workflow health) are hidden.
            Turn it on in <a href="/dashboard/blog/{{ blog.id }}/edit" class="font-medium text-custom-500 hover:text-custom-600 underline">Blog Settings</a> if you want submissions to go through a review pipeline before publishing.
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
                Workflow health
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="p-3 rounded-md border border-slate-100 dark:border-zink-600">
                    <div class="text-[10px] uppercase tracking-wide font-medium text-slate-500 dark:text-zink-400">In review, assigned</div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1"><?= (int) $wh['in_review_assigned'] ?></div>
                </div>
                <div class="p-3 rounded-md border <?= $hasUnassigned ? 'border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30' : 'border-slate-100 dark:border-zink-600' ?>">
                    <div class="text-[10px] uppercase tracking-wide font-medium <?= $hasUnassigned ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-zink-400' ?>">In review, unassigned</div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1 flex items-center gap-2">
                        <?= (int) $wh['in_review_unassigned'] ?>
                        <?php if ($hasUnassigned) { ?>
                        <i data-lucide="alert-circle" class="size-4 text-amber-500"></i>
                        <?php } ?>
                    </div>
                </div>
                <div class="p-3 rounded-md border border-slate-100 dark:border-zink-600">
                    <div class="text-[10px] uppercase tracking-wide font-medium text-slate-500 dark:text-zink-400">Needs changes</div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1"><?= (int) $wh['needs_changes'] ?></div>
                </div>
                <div class="p-3 rounded-md border border-slate-100 dark:border-zink-600">
                    <div class="text-[10px] uppercase tracking-wide font-medium text-slate-500 dark:text-zink-400">Approved, not published</div>
                    <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mt-1"><?= (int) $wh['approved'] ?></div>
                </div>
            </div>

            <?php if (!empty($wh['recent'])) { ?>
            <div class="border-t border-slate-100 dark:border-zink-600 pt-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-2">In review now</h3>
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($wh['recent'] as $row) { ?>
                    <li class="py-2">
                        <a href="/dashboard/post/<?= (int) $row['id'] ?>/review" class="flex items-center gap-3 group">
                            <span class="inline-flex items-center justify-center size-7 rounded-md bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300 shrink-0">
                                <i data-lucide="lock" class="size-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate group-hover:text-custom-500"><?= e($row['title']) ?></p>
                                <p class="text-xs text-slate-500 dark:text-zink-400 mt-0.5">
                                    by <?= e($row['author_username']) ?>
                                    · reviewed by <?= e($row['reviewer_username']) ?>
                                </p>
                            </div>
                            <i data-lucide="chevron-right" class="size-4 text-slate-400 shrink-0 group-hover:text-custom-500"></i>
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
                    Active collaborators
                </h2>

                {% if members|empty %}
                <div class="text-center py-10">
                    <i data-lucide="user-plus" class="size-10 text-slate-400 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-500 dark:text-zink-300">No collaborators yet. Invite someone using the form to start working together.</p>
                </div>
                {% else %}
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($members as $m): %}
                    <li class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                        <span class="flex items-center justify-center size-9 rounded-full bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300 text-xs font-semibold shrink-0">
                            <?= e($initials((string) ($m['username'] ?? 'U'))) ?>
                        </span>
                        <div class="min-w-0 grow">
                            <p class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate"><?= e($m['username'] ?? 'Unknown') ?></p>
                            <p class="text-xs text-slate-500 dark:text-zink-300 truncate"><?= e($m['email'] ?? '') ?></p>
                        </div>

                        <?php $currentRole = (string) ($m['role'] ?? ''); ?>
                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/<?= (int) ($m['user_id'] ?? 0) ?>/role" class="m-0 shrink-0 w-40">
                            {{ csrf_field() }}
                            <label for="role-<?= (int) ($m['user_id'] ?? 0) ?>" class="sr-only">Role</label>
                            <select id="role-<?= (int) ($m['user_id'] ?? 0) ?>" name="role" onchange="this.form.submit()"
                                class="form-select appearance-none border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-1 pr-7">
                                <?php foreach ($roles as $role) { ?>
                                    <option value="<?= e($role) ?>" <?= $role === $currentRole ? 'selected' : '' ?>><?= ucfirst(e($role)) ?></option>
                                <?php } ?>
                            </select>
                        </form>

                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/<?= (int) ($m['user_id'] ?? 0) ?>/revoke" class="m-0 shrink-0"
                            onsubmit="return confirm('Remove <?= e(addslashes($m['username'] ?? 'this collaborator')) ?> from this blog?\n\nThey will need a new invitation to rejoin.');">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="red" icon="user-minus" label="Remove" %}
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
                    Invite by email
                </h2>

                <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/invite" class="space-y-3">
                    {{ csrf_field() }}
                    {% cmp="input" type="email" name="email" label="Email" placeholder="person@example.com" required="true" %}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="invite-role" class="text-sm font-medium text-slate-700 dark:text-zink-100">Role</label>
                            <span id="invite-role-hint" class="text-[11px] text-slate-500 dark:text-zink-300"></span>
                        </div>
                        <select id="invite-role" name="role"
                            class="form-select w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm py-2">
                            <?php foreach ($roles as $role) { ?>
                                <option value="<?= e($role) ?>" data-doc="<?= e($roleDocs[$role] ?? '') ?>"><?= ucfirst(e($role)) ?></option>
                            <?php } ?>
                        </select>
                        <p id="invite-role-doc" class="text-[11px] text-slate-500 dark:text-zink-300 mt-1 leading-snug min-h-[2em]"></p>
                    </div>
                    {% cmp="btn" type="submit" variant="blue" icon="send" label="Send invite" addClass="w-full" %}
                </form>

                {% if pendingInvites|notempty %}
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300 mt-6 mb-2">Pending invitations</h3>
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($pendingInvites as $inv): %}
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-zink-100 truncate"><?= e($inv['email'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-500 dark:text-zink-300 capitalize"><?= e($inv['role'] ?? '') ?> · expires <?= e($inv['expires_at'] ?? '') ?></p>
                        </div>
                        <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/cancel-invite" class="m-0 shrink-0">
                            {{ csrf_field() }}
                            <input type="hidden" name="email" value="<?= e($inv['email'] ?? '') ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="x" label="Cancel" %}
                        </form>
                    </li>
                    {% endforeach %}
                </ul>
                {% endif %}

                <?php if (!empty($expiredInvites)) { ?>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300 mt-6 mb-2 flex items-center gap-1">
                    <i data-lucide="alert-circle" class="size-3.5"></i>
                    Expired (resend to retry)
                </h3>
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($expiredInvites as $inv) { ?>
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-zink-100 truncate"><?= e($inv['email'] ?? '') ?></p>
                            <p class="text-[11px] text-slate-500 dark:text-zink-300 capitalize"><?= e($inv['role'] ?? '') ?> · expired <?= e($inv['expires_at'] ?? '') ?></p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/invite" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="email" value="<?= e($inv['email'] ?? '') ?>">
                                <input type="hidden" name="role" value="<?= e($inv['role'] ?? '') ?>">
                                {% cmp="btn" type="submit" variant="blue" icon="send" label="Resend" %}
                            </form>
                            <form method="POST" action="/dashboard/blog/{{ blog.id }}/team/cancel-invite" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="email" value="<?= e($inv['email'] ?? '') ?>">
                                {% cmp="btn" type="submit" variant="slate" icon="x" label="Drop" %}
                            </form>
                        </div>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </aside>
    </div>

    <!-- Role reference -->
    <section class="card mt-6">
        <div class="card-body">
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4 flex items-center gap-2">
                <i data-lucide="shield" class="size-4 text-custom-500"></i>
                What each role can do
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <?php foreach ($roleDocs as $rDoc => $desc) { ?>
                    <div class="p-3 rounded-lg border border-slate-100 dark:border-zink-600">
                        <span class="inline-flex items-center px-2 py-0.5 mb-1.5 text-[11px] font-medium rounded-full border capitalize <?= $roleBadge[$rDoc] ?? $fallbackBadge ?>"><?= e($rDoc) ?></span>
                        <p class="text-xs text-slate-500 dark:text-zink-300 leading-relaxed"><?= e($desc) ?></p>
                    </div>
                <?php } ?>
            </div>
            <p class="mt-4 text-xs text-slate-500 dark:text-zink-300 flex items-start gap-2">
                <i data-lucide="info" class="size-4 shrink-0 mt-0.5"></i>
                Only the blog owner can invite collaborators or change roles. To give an author full editing rights, promote them to editor.
            </p>
        </div>
    </section>

</div>
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>
<script>
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
