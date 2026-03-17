{% extends "base_dashboard.lex.php" %}

{% block title %}Manage blogs{% endblock %}

{% block body %}
<h1>Blogs</h1>

<p><a href="/admin/blogs/new">+ New Blog</a></p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Updated</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($blogs as $blog): %}
        <tr>
            <td>{{ blog['id'] }}</td>
            <td>{{ blog['blog_name'] }}</td>
            <td>{{ blog['blog_slug'] }}</td>
            <td>{{ blog['description'] }}</td>
            <td>{{ blog['updated_at'] }}</td>
            <td>
                <a href="/admin/blogs/{{ blog['id'] }}/show">View</a> |
                <a href="/admin/blogs/{{ blog['id'] }}/edit">Edit</a> |
                <a href="/admin/blogs/{{ blog['id'] }}/delete">Delete</a>
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}