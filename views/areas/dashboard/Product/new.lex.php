{% extends "front.lex.php" %}

{% block title %}New Product{% endblock %}

{% block body %}
<h1>New Product</h1>

<form action="/products/create" method="post">

{% include "Products/form.lex.php" %}

</form>
{% endblock %}