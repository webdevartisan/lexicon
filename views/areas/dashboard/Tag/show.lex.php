{% extends "front.lex.php" %}

{% block title %}{{ tag['name'] }}{% endblock %}

{% block body %}
<h1>{{ tag['name'] }}</h1>

<p>
    <strong>Slug:</strong> {{ tag['slug'] }}
</p>

<a href="/tags/{{ tag['id'] }}/edit">Edit</a> |
<a href="/tags/{{ tag['id'] }}/delete">Delete</a>

<hr>

<h2>Posts with this tag</h2>

{% if posts is empty %}
    <p>No posts are tagged with "{{ tag['name'] }}" yet.</p>
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
