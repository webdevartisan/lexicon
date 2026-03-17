{% extends "base_dashboard.lex.php" %}

{% block title %}View Comment{% endblock %}

{% block body %}
<h1>Comment #<?= $comment['id'] ?></h1>

<p><strong>Author:</strong> <?= htmlspecialchars($comment['author_name']) ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($comment['author_email']) ?></p>
<p><strong>Content:</strong></p>
<div><?= nl2br(htmlspecialchars($comment['content'])) ?></div>
<p><strong>Status:</strong> <?= $comment['status'] ?></p>

<p>
    <a href="/admin/comments/<?= $comment['id'] ?>/approve">Approve</a> |
    <a href="/admin/comments/<?= $comment['id'] ?>/delete">Delete</a> |
    <a href="/admin/comments">Back</a>
</p>
{% endblock %}
