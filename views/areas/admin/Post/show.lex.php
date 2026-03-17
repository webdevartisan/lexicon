{% extends "base_dashboard.lex.php" %}

{% block title %}View Post{% endblock %}

{% block body %}
<h1>{{ post.title }}</h1>

<p><strong>Status:</strong> {{ post.status }}</p>
<p><strong>Author:</strong> {{ post.author_id }}</p>
<p><strong>Content:</strong></p>
<div>{{ post.content|raw }}</div>

<p>
    <a href="/admin/posts/{{ post.id }}/edit">Edit</a> |
    <a href="/admin/posts/{{ post.id }}/delete">Delete</a> |
    <a href="/admin/posts">Back to list</a>
</p>
{% endblock %}
