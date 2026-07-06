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
</div>
{% endblock %}
