{% extends "back.lex.php" %}

{% block title %}Delete User{% endblock %}
{% block subtitle %}This action is permanent and cannot be undone.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-xl">
    <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/destroy" class="card">
        {{ csrf_field() }}
        <div class="card-body text-center py-10">
            <i data-lucide="alert-triangle" class="size-12 text-red-500 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                Delete <?= e($user['username']) ?>?
            </h3>
            <p class="text-sm text-slate-500 dark:text-zink-300">
                The account <?= e($user['email']) ?> and its role assignments will be removed permanently.
            </p>
        </div>
        {% cmp="form-footer" cancelHref="/admin/users" submitLabel="Yes, delete user" submitIcon="trash-2" danger %}
    </form>
</div>
{% endblock %}
