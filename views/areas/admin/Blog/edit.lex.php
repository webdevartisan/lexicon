{% extends "back.lex.php" %}

{% block title %}Edit Blog{% endblock %}
{% block subtitle %}Update the blog's identity and visibility.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-3xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/blogs/<?= e((string) ($blog['id'] ?? '')) ?>/update" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Blog/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/blogs" submitLabel="Save Changes" submitIcon="save" %}
    </form>
</div>
{% endblock %}
