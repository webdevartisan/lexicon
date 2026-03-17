{% extends "front.lex.php" %}

{% block title %}Categories{% endblock %}

{% block body %}
<h1>Categories</h1>

<a href="/categories/new">New category</a>

{% if categories is empty %}
    <p>No categories found.</p>
{% else %}
    <ul>
        {% foreach ($categories as $category): %}
            <li>
                <a href="/categories/{{ category['id'] }}/show">
                    {{ category['name'] }}
                </a>
            </li>
        {% endforeach; %}
    </ul>
{% endif %}
{% endblock %}
