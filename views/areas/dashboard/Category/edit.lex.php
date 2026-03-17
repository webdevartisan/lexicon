{% extends "front.lex.php" %}

{% block title %}Edit Category{% endblock %}

{% block body %}
<h1>Edit Category</h1>

<form action="/categories/{{ category['id'] }}/update" method="post">

    {% include "Categories/form.lex.php" %}

</form>
{% endblock %}
