{% extends "base_dashboard.lex.php" %}

{% block title %}Edit User{% endblock %}

{% block body %}
<h1>Edit User</h1>

{% if errors|isset %}
    <ul>
    {% for error in errors %}
        <li>{{ error }}</li>
    {% endfor %}
    </ul>
{% endif %}

<form method="post" action="/admin/users/<?= $user['id'] ?>/update">
    {% include "Admin/Users/form.lex.php" %}
    <button type="submit">Update</button>
</form>
{% endblock %}
