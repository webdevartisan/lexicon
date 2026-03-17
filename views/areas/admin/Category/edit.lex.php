{% extends "base_dashboard.lex.php" %}

{% block title %}Edit Category{% endblock %}

{% block body %}
<h1>Edit Category</h1>

{% if errors|isset %}
    <ul>
    {% foreach ($errors as $error): %}
        <li><?= htmlspecialchars($error) ?></li>
    {% endforeach; %}
    </ul>
{% endif %}

<form method="post" action="/admin/categories/<?= $category['id'] ?>/update">
    {% include "Admin/Categories/form.lex.php" %}
    <button type="submit">Update</button>
</form>
{% endblock %}
