{% extends "base_dashboard.lex.php" %}

{% block title %}View blog{% endblock %}

{% block body %}
<h1>{{ blog.blog_name }}</h1>

<p><strong>Status:</strong> {{ blog.is_active }}</p>
<p><strong>Author:</strong> {{ blog.owner_id }}</p>
<p><strong>Description:</strong></p>
<div>{{ blog.description|raw }}</div>

<p>
    <a href="/admin/blogs/{{ blog.id }}/edit">Edit</a> |
    <a href="/admin/blogs/{{ blog.id }}/delete">Delete</a> |
    <a href="/admin/blogs">Back to list</a>
</p>



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
