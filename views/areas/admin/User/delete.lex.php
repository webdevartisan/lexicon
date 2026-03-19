{% extends "base_dashboard.lex.php" %}

{% block title %}Delete User{% endblock %}

{% block body %}
<h1>Delete User</h1>

<p>Are you sure you want to delete <strong><?= e($user['username']) ?></strong>?</p>

<form method="post" action="/admin/users/<?= $user['id'] ?>/destroy">
    <button type="submit">Yes, delete</button>
    <a href="/admin/users">Cancel</a>
</form>
{% endblock %}
