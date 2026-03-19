{% extends "base_dashboard.lex.php" %}

{% block title %}New User{% endblock %}

{% block body %}
<h1>Create New User</h1>

{% if errors|isset %}
    <ul>
    {% foreach ($errors as $error): %}
        <li><?= e($error) ?></li>
    {% endforeach; %}
    </ul>
{% endif %}

<form method="post" action="/admin/users/create">
    {% include "Admin/Users/form.lex.php" %}
    <button type="submit">Save</button>
</form>
{% endblock %}
