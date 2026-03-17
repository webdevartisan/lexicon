{% extends "base_dashboard.lex.php" %}

{% block title %}Manage Categories{% endblock %}

{% block body %}
<h1>Categories</h1>

<p><a href="/admin/categories/new">+ New Category</a></p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($categories as $category): %}
        <tr>
            <td><?= htmlspecialchars($category['id']) ?></td>
            <td><?= htmlspecialchars($category['name']) ?></td>
            <td><?= htmlspecialchars($category['slug']) ?></td>
            <td>
                <a href="/admin/categories/<?= $category['id'] ?>/edit">Edit</a> |
                <a href="/admin/categories/<?= $category['id'] ?>/delete">Delete</a>
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}
