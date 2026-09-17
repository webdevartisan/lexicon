{% extends "auth.lex.php" %}

{% block title %}<?= e($t('subscription.invalidTitle')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block heading %}<?= e($t('subscription.invalidTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('subscription.invalidBody')) ?>{% endblock %}

{% block help %}
<a href="/"><?= e($t('auth.returnHome')) ?></a>
{% endblock %}
