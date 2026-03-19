{% extends "base_dashboard.lex.php" %}

{% block title %}Manage Users{% endblock %}

{% block body %}
<h1>Users</h1>

<p><a href="/admin/users/new">+ New User</a></p>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Roles</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($users as $user): %}
        <tr>
            <td><?= e($user['id']) ?></td>
            <td><?= e($user['username']) ?></td>
            <td><?= e($user['email']) ?></td>
            <td><?= e($user['roles']) ?></td>
            <td>
                <a href="/admin/users/<?= $user['id'] ?>/edit">Edit</a> |
                <a href="/admin/users/<?= $user['id'] ?>/delete">Delete</a>
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}
