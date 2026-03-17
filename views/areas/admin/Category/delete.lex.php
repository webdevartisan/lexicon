{% extends "base_dashboard.lex.php" %}

{% block title %}Delete Category{% endblock %}

{% block body %}
<h1>Delete Category</h1>

<p>Are you sure you want to delete <strong><?= htmlspecialchars($category['name']) ?></strong>?</p>

<form method="post" action="/admin/categories/<?= $category['id'] ?>/destroy">
    <button type="submit">Yes, delete</button>
    <a href="/admin/categories">Cancel</a>
</form>
{% endblock %}
