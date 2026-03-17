{% extends "base_dashboard.lex.php" %}

{% block title %}New Blog{% endblock %}

{% block body %}
<h1>Create New Blog</h1>

{% if (!empty($errors)): %}
    <ul>
    {% foreach ($errors as $error): %}
        <li>{{ error }}</li>
    {% endforeach; %}
    </ul>
{% endif; %}

<form method="post" action="/admin/blogs/create">
    {% include "Admin/Blogs/form.lex.php" %}
    <button type="submit">Save</button>
</form>
{% endblock %}