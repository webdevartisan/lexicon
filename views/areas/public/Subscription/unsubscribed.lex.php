{% extends "auth.lex.php" %}

{% block title %}<?= e($t('subscription.unsubscribedTitle')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block heading %}<?= e($t('subscription.unsubscribedTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('subscription.unsubscribedBody', ['blog' => $blogName])) ?>{% endblock %}

{% block help %}
<a href="/"><?= e($t('auth.returnHome')) ?></a>
{% endblock %}
