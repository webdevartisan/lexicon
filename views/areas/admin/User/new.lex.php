{% extends "back.lex.php" %}

{% block title %}New User{% endblock %}
{% block subtitle %}Create an account and assign roles.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-3xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/users/create" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/User/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/users" submitLabel="Create User" submitIcon="user-plus" %}
    </form>
</div>
{% endblock %}
