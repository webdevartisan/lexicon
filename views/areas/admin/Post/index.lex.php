{% extends "base_dashboard.lex.php" %}

{% block title %}Manage Posts{% endblock %}

{% block body %}
<h1>Posts</h1>

<p><a href="/admin/posts/new">+ New Post</a></p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Author</th>
            <th>Updated</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($posts as $post): %}
        <tr>
            <td>{{ post['id'] }}</td>
            <td>{{ post['title'] }}</td>
            <td>{{ post['status'] }}</td>
            <td>{{ post['author_id'] }}</td>
            <td>{{ post['updated_at'] }}</td>
            <td>
                <a href="/admin/posts/{{ post['id'] }}/show">View</a> |
                <a href="/admin/posts/{{ post['id'] }}/edit">Edit</a> |
                <a href="/admin/posts/{{ post['id'] }}/delete">Delete</a>
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}