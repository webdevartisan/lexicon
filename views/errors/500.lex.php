{% extends "error.lex.php" %}

{% block status %}500{% endblock %}
{% block title %}Server error{% endblock %}
{% block tone %}danger{% endblock %}
{% block heading %}Something broke on our side{% endblock %}
{% block message %}It isn't anything you did. Try again in a moment, and if it keeps happening, let us know.{% endblock %}
{% block action %}<a class="lx-error-btn" href="">Try again</a>{% endblock %}
