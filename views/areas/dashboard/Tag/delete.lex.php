{% extends "front.lex.php" %}

{% block title %}Delete Tag{% endblock %}

{% block body %}
<h1>Delete Tag</h1>

<p>Are you sure you want to delete the tag <strong>"{{ tag['name'] }}"</strong>?</p>

<form action="/tags/{{ tag['id'] }}/destroy" method="post">
    <button type="submit">Yes, delete</button>
    <a href="/tags/{{ tag['id'] }}/show">Cancel</a>
</form>
{% endblock %}
