{% extends "back.lex.php" %}

{% block title %}New Blog{% endblock %}
{% block subtitle %}Create a blog owned by your account.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-3xl">
    {% include "areas/admin/_errors.lex.php" %}
    {% if error|notempty %}
    <div class="px-4 py-3 mb-4 text-sm text-red-600 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300">
        <?= e($error) ?>
    </div>
    {% endif %}

    <form method="post" action="/admin/blogs/create" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Blog/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/blogs" submitLabel="Create Blog" submitIcon="plus" %}
    </form>
</div>
{% endblock %}
