{% extends "front.lex.php" %}

{% block title %}Delete Category{% endblock %}

{% block body %}
<h1>Delete Category</h1>

<p>Are you sure you want to delete the category <strong>"{{ category['name'] }}"</strong>?</p>

<form action="/categories/{{ category['id'] }}/destroy" method="post">
    <button type="submit">Yes, delete</button>
    <a href="/categories/{{ category['id'] }}/show">Cancel</a>
</form>
{% endblock %}
