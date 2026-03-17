{% extends "front.lex.php" %}

{% block title %}New Tag{% endblock %}

{% block body %}
<h1>New Tag</h1>

<form action="/tags/create" method="post">

    {% include "Tags/form.lex.php" %}

</form>
{% endblock %}
