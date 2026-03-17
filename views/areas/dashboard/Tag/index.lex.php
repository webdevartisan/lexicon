{% extends "front.lex.php" %}

{% block title %}Tags{% endblock %}

{% block body %}
<h1>Tags</h1>

<a href="/tags/new">New tag</a>

{% if tags is empty %}
    <p>No tags found.</p>
{% else %}
    <ul>
        {% foreach ($tags as $tag): %}
            <li>
                <a href="/tags/{{ tag['id'] }}/show">
                    {{ tag['name'] }}
                </a>
            </li>
        {% endforeach; %}
    </ul>
{% endif %}
{% endblock %}
