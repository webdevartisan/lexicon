{% extends "base_dashboard.lex.php" %}

{% block title %}Delete Comment{% endblock %}

{% block body %}
<h1>Delete Comment</h1>

<p>Are you sure you want to delete this comment?</p>
<blockquote><?= e($comment['content']) ?></blockquote>

<form method="post" action="/admin/comments/<?= $comment['id'] ?>/destroy">
    <button type="submit">Yes, delete</button>
    <a href="/admin/comments">Cancel</a>
</form>
{% endblock %}
