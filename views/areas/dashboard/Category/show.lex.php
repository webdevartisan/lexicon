{% extends "front.lex.php" %}

{% block title %}{{ category['name'] }}{% endblock %}

{% block body %}
<h1>{{ category['name'] }}</h1>

<p>
    <strong>Slug:</strong> {{ category['slug'] }}
</p>

<a href="/categories/{{ category['id'] }}/edit">Edit</a> |
<a href="/categories/{{ category['id'] }}/delete">Delete</a>

<hr>

<h2>Posts in this category</h2>

{% if posts is empty %}
    <p>No posts in this category yet.</p>
{% else %}
    <ul>
        {% foreach ($posts as $post): %}
            <li>
                <a href="/posts/{{ post['slug'] }}/show">{{ post['title'] }}</a>
                <small> ({{ post['created_at'] }})</small>
            </li>
        {% endforeach; %}
    </ul>
{% endif %}
{% endblock %}
