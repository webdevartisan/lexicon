{% extends "base_dashboard.lex.php" %}

{% block title %}Edit Blog{% endblock %}

{% block body %}
<h1>Edit Blog</h1>

{% if errors|notempty %}
    <ul>
    {% for error in errors %}
        <li>{{ error }}</li>
    {% endfor %}
    </ul>
{% endif %}

<form method="post" action="/admin/blogs/{{ blog.id }}/update">
    {% include "Admin/Blogs/form.lex.php" %}
    <button type="submit">Update</button>
</form>
{% endblock %}
