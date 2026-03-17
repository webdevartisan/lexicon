{% extends "base_dashboard.lex.php" %}

{% block title %}Edit Post{% endblock %}

{% block body %}
<h1>Edit Post</h1>

{% if errors|notempty %}
    <ul>
    {% for error in errors %}
        <li>{{ error }}</li>
    {% endfor %}
    </ul>
{% endif %}

<form method="post" action="/admin/posts/{{ post.id }}/update">
    {% include "areas/admin/posts/form.lex.php" %}
    <button type="submit">Update</button>
</form>
{% endblock %}
