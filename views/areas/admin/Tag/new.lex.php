{% extends "base_dashboard.lex.php" %}

{% block title %}New Tag{% endblock %}

{% block body %}
<h1>Create New Tag</h1>

{% if errors|isset %}
    <ul>
    {% foreach ($errors as $error): %}
        <li><?= htmlspecialchars($error) ?></li>
    {% endforeach; %}
    </ul>
{% endif %}

<form method="post" action="/admin/tags/create">
    {% include "Admin/Tags/form.lex.php" %}
    <button type="submit">Save</button>
</form>
{% endblock %}
