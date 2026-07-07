{% extends "back.lex.php" %}

{% block title %}New Role{% endblock %}
{% block subtitle %}Create a custom role, then grant it permissions.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-2xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/roles/create" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Role/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/roles" submitLabel="Create Role" submitIcon="plus" %}
    </form>
</div>
{% endblock %}
