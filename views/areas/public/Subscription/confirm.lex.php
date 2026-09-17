{% extends "auth.lex.php" %}

{% block title %}<?= e($t('subscription.confirmTitle')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block heading %}<?= e($t('subscription.confirmTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('subscription.confirmBody', ['blog' => $blogName, 'email' => $email])) ?>{% endblock %}

{% block body %}
<form action="/subscriptions/confirm/<?= e($token) ?>" method="post">
    <?= csrf_field() ?>
    <button class="lx-btn lx-authsubmit" type="submit"><?= e($t('subscription.confirmSubmit')) ?></button>
</form>
{% endblock %}

{% block help %}
<?= e($t('subscription.confirmIgnore')) ?>
{% endblock %}
