{% extends "base_dashboard.lex.php" %}

{% block title %}Manage Comments{% endblock %}

{% block body %}
<h1>Comments</h1>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Post</th>
            <th>Author</th>
            <th>Content</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($comments as $comment): %}
        <tr>
            <td><?= $comment['id'] ?></td>
            <td><?= $comment['post_id'] ?></td>
            <td><?= htmlspecialchars($comment['author_name']) ?></td>
            <td><?= htmlspecialchars($comment['content']) ?></td>
            <td><?= $comment['status'] ?></td>
            <td>
                <a href="/admin/comments/<?= $comment['id'] ?>/show">View</a> |
                {% if $comment['status'] !== 'approved': %}
                    <a href="/admin/comments/<?= $comment['id'] ?>/approve">Approve</a> |
                {% endif %}
                <a href="/admin/comments/<?= $comment['id'] ?>/delete">Delete</a>
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}
