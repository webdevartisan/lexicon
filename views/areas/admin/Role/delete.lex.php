{% extends "back.lex.php" %}

{% block title %}Delete Role{% endblock %}
{% block subtitle %}This action is permanent and cannot be undone.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    <?php if ($usersCount > 0) { ?>
    <div class="card">
        <div class="card-body text-center py-10">
            <i data-lucide="users" class="size-12 text-amber-500 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                "<?= e($role['role_name']) ?>" still has <?= (int) $usersCount ?> user<?= $usersCount === 1 ? '' : 's' ?>
            </h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">
                Reassign those users to another role first so nobody silently loses access.
            </p>
            {% cmp="btn" href="/admin/users" variant="blue" icon="users" label="Go to Users" %}
        </div>
    </div>
    <?php } else { ?>
    <form method="post" action="/admin/roles/<?= e((string) $role['id']) ?>/destroy" class="card">
        {{ csrf_field() }}
        <div class="card-body text-center py-10">
            <i data-lucide="alert-triangle" class="size-12 text-red-500 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                Delete "<?= e($role['role_name']) ?>"?
            </h3>
            <p class="text-sm text-slate-500 dark:text-zink-300">
                Its permission grants will be removed with it. No users hold this role.
            </p>
        </div>
        {% cmp="form-footer" cancelHref="/admin/roles" submitLabel="Yes, delete role" submitIcon="trash-2" danger %}
    </form>
    <?php } ?>
</div>
{% endblock %}
