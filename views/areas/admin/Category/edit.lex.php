{% extends "back.lex.php" %}

{% block title %}Edit Category{% endblock %}
{% block subtitle %}Rename or change the URL slug.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-2xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/categories/<?= e((string) ($category['id'] ?? '')) ?>/update" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Category/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/categories" submitLabel="Save Changes" submitIcon="save" %}
    </form>
</div>
{% endblock %}
