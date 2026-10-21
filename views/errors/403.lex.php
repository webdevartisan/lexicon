{% extends "error.lex.php" %}

{% block status %}403{% endblock %}
{% block title %}Access denied{% endblock %}
{% block tone %}warning{% endblock %}
{% block heading %}You don't have access to this page{% endblock %}
{% block message %}Your account can't open it. If someone sent you the link, ask them to add you, or sign in with a different account.{% endblock %}
{% block action %}<a class="lx-error-btn" href="<?= e(lurl('/login')) ?>">Sign in</a>{% endblock %}
