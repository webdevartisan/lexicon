{% extends "base_dashboard.lex.php" %}

{% block title %}Roles{% endblock %}

{% block body %}
<h1>Roles</h1>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Level</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    {% foreach ($roles as $role): %}
        <tr>
            <td>{{ role['id'] }}</td>
            <td>{{ role['role_name'] }}</td>
            <td>{{ role['role_slug'] }}</td>
            <td>{{ role['description'] }}</td>
            <td>{{ role['level'] }}</td>
            <td>
                <a href="/admin/roles/{{ role['id'] }}/show">View</a> 
              <!--  | <a href="/admin/roles/{{ role['id'] }}/edit">Edit</a>  -->
              <!--  | <a href="/admin/roles/{{ role['id'] }}/delete">Delete</a>  -->
            </td>
        </tr>
    {% endforeach; %}
    </tbody>
</table>
{% endblock %}
