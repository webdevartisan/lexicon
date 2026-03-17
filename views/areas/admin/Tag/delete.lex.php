{% extends "base_dashboard.lex.php" %}

{% block title %}Delete Tag{% endblock %}

{% block body %}
<h1>Delete Tag</h1>

<p>Are you sure you want to delete <strong><?= htmlspecialchars($tag['name']) ?></strong>?</p>

<form method="post" action="/admin/tags/<?= $tag['id'] ?>/destroy">
    <button type="submit">Yes, delete</button>
    <a href="/admin/tags">Cancel</a>
</form>
{% endblock %}
