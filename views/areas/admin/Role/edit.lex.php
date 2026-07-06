{% extends "back.lex.php" %}

{% block title %}Edit Role{% endblock %}
{% block subtitle %}Rename or reword a role; permissions are managed on the role's permission page.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-2xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/roles/<?= e((string) $role['id']) ?>/update" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Role/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/roles" submitLabel="Save Changes" submitIcon="check" %}
    </form>
</div>
{% endblock %}
