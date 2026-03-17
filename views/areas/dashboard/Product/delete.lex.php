{% extends "front.lex.php" %}

{% block title %}Delete Product{% endblock %}

{% block body %}
<h1>Delete Product</h1>

<form action="/products/{{ product['id'] }}/destroy" method="post">

<p>Delete this product?</p>

<button type="submit">Yes</button>

</form>
<p><a href="/products/{{ product['id'] }}/show">Cancel</a></p>
{% endblock %}