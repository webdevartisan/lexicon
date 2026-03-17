{% extends "front.lex.php" %}

{% block title %}New Category{% endblock %}

{% block body %}
<h1>New Category</h1>

<form action="/categories/create" method="post">

    {% include "Categories/form.lex.php" %}

</form>
{% endblock %}
