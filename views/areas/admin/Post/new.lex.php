{% extends "base_dashboard.lex.php" %}

{% block title %}New Post{% endblock %}

{% block body %}
<h1>Create New Post</h1>

{% if (!empty($errors)): %}
    <ul>
    {% foreach ($errors as $error): %}
        <li>{{ error }}</li>
    {% endforeach; %}
    </ul>
{% endif; %}

<form method="post" action="/admin/posts/create">
    {% include "admin/Posts/form.lex.php" %}
    <button type="submit">Save</button>
</form>
{% endblock %}



{% foreach ($products as $product): %}
    <h2>
        <a href="/products/{{ product['id'] }}/show">{{ product['name'] }}</a>
    </h2>
{% endforeach; %}