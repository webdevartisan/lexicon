{% extends "error.lex.php" %}

{% block status %}419{% endblock %}
{% block title %}Your session expired{% endblock %}
{% block tone %}warning{% endblock %}
{% block heading %}Your session expired{% endblock %}
{% block message %}The form was open too long, so it wasn't sent and nothing changed. Go back, reload the page and send it again.{% endblock %}
