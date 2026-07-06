{% extends "back.lex.php" %}

{% block title %}New Category{% endblock %}
{% block subtitle %}Add a category scoped to one blog.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-2xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/categories/create" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Category/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/categories" submitLabel="Create Category" submitIcon="plus" %}
    </form>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/cp-assets/js/searchable-select.init.js"></script>
{% endblock %}
