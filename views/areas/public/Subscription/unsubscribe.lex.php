{% extends "auth.lex.php" %}

{% block title %}<?= e($t('subscription.unsubscribeTitle')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block heading %}<?= e($t('subscription.unsubscribeTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('subscription.unsubscribeBody', ['blog' => $blogName, 'email' => $email])) ?>{% endblock %}

{% block body %}
<form action="/subscriptions/unsubscribe/<?= e($token) ?>" method="post">
    <?= csrf_field() ?>
    <button class="lx-btn lx-authsubmit" type="submit"><?= e($t('subscription.unsubscribeSubmit')) ?></button>
</form>
{% endblock %}

{% block help %}
<?= e($t('subscription.unsubscribeIgnore')) ?>
{% endblock %}
