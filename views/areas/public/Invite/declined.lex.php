{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.inviteDeclinedTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.inviteDeclinedTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.inviteDeclinedBody')) ?>{% endblock %}

{% block help %}
<a href="/"><?= e($t('auth.returnHome')) ?></a>
{% endblock %}
