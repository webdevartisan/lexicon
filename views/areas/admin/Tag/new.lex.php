{% extends "back.lex.php" %}

{% block title %}New Tag{% endblock %}
{% block subtitle %}Add a tag scoped to one blog.{% endblock %}

{% block head %}
{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-2xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/tags/create" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Tag/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/tags" submitLabel="Create Tag" submitIcon="plus" %}
    </form>
</div>
{% endblock %}

{% block scripts %}
{% endblock %}
