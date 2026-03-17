{% extends "base_dashboard.lex.php" %}

{% block title %}Delete Post{% endblock %}

{% block body %}
<h1>Delete Post</h1>

<p>Are you sure you want to delete the post <strong>{{ post.title }}</strong>?</p>

<form method="post" action="/admin/posts/{{ post.id }}/destroy">
    <button type="submit">Yes, delete</button>
    <a href="/admin/posts">Cancel</a>
</form>
{% endblock %}
