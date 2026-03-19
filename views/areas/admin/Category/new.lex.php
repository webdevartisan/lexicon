{% extends "base_dashboard.lex.php" %}

{% block title %}New Category{% endblock %}

{% block body %}
<h1>Create New Category</h1>

{% if errors|isset %}
    <ul>
    {% foreach ($errors as $error): %}
        <li><?= e($error) ?></li>
    {% endforeach; %}
    </ul>
{% endif %}

<form method="post" action="/admin/categories/create">
    {% include "Admin/Categories/form.lex.php" %}
    <button type="submit">Save</button>
</form>
{% endblock %}
