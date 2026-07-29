{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.inviteExpiredTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.inviteExpiredTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.inviteExpiredBody')) ?>{% endblock %}

{% block help %}
<a href="/"><?= e($t('auth.returnHome')) ?></a>
{% endblock %}
