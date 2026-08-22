{% extends "back.lex.php" %}

{% block title %}Edit User{% endblock %}
{% block subtitle %}Update account details and role assignments.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-3xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/update" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/User/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/users" submitLabel="Save Changes" submitIcon="save" %}
    </form>

    <?php
    // Blog involvement is separate from the site role above: a person can own
    // blogs and hold different roles on shared ones. Read-only here; roles are
    // changed on each blog's own team page.
    $ownerBadge = 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800';
    $roleBadge = 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500';
    ?>
    <div class="card mt-5">
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Blog access</h3>
            <p class="text-xs text-slate-400 dark:text-zink-400 mb-3">Blogs this account owns or collaborates on. Change these on each blog's team page.</p>
            <?php if (empty($blogs)) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300">Not a member of any blog.</p>
            <?php } else { ?>
            <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                <?php foreach ($blogs as $b) {
                    $isOwner = ($b['user_role'] ?? '') === 'owner'; ?>
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="/admin/blogs/<?= e((string) $b['id']) ?>/show" class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 truncate">
                        <?= e((string) $b['blog_name']) ?>
                    </a>
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border capitalize <?= $isOwner ? $ownerBadge : $roleBadge ?>">
                        <?= e((string) ($b['user_role'] ?? '')) ?>
                    </span>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
