{% extends "front.lex.php" %}

{% block title %}Edit Tag{% endblock %}

{% block body %}
<h1>Edit Tag</h1>

<form action="/tags/{{ tag['id'] }}/update" method="post">

    {% include "Tags/form.lex.php" %}

</form>
{% endblock %}
