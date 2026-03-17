{% extends "base_dashboard.lex.php" %}

{% block title %}Delete Blog{% endblock %}

{% block body %}
<h1>Delete Blog</h1>

<p>Are you sure you want to delete the blog <strong>{{ blog.title }}</strong>?</p>

<form method="post" action="/admin/blogs/{{ blog.id }}/destroy">
    <button type="submit">Yes, delete</button>
    <a href="/admin/blogs">Cancel</a>
</form>
{% endblock %}
