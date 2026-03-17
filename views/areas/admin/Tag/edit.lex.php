{% extends "base_dashboard.lex.php" %}

{% block title %}Edit Tag{% endblock %}

{% block body %}
<h1>Edit Tag</h1>

{% if errors|isset %}
    <ul>
    {% foreach ($errors as $error): %}
        <li><?= htmlspecialchars($error) ?></li>
    {% endforeach; %}
    </ul>
{% endif %}

<form method="post" action="/admin/tags/<?= $tag['id'] ?>/update">
    {% include "Admin/Tags/form.lex.php" %}
    <button type="submit">Update</button>
</form>
{% endblock %}
