{% extends "base_dashboard.lex.php" %}

{% block title %}Manage Tags{% endblock %}

{% block body %}
<h1>Tags</h1>

<p><a href="/admin/tags/new">+ New Tag</a></p>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($tags as $tag): %}
        <tr>
            <td><?= htmlspecialchars($tag['id']) ?></td>
            <td><?= htmlspecialchars($tag['name']) ?></td>
            <td><?= htmlspecialchars($tag['slug']) ?></td>
            <td>
                <a href="/admin/tags/<?= $tag['id'] ?>/edit">Edit</a> |
                <a href="/admin/tags/<?= $tag['id'] ?>/delete">Delete</a>
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}
