{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.forgotTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.forgotTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.forgotSubtitle')) ?>{% endblock %}

{% block altAction %}
<a href="/register"><?= e($t('auth.createAccount')) ?></a>
{% endblock %}

{% block body %}
<form action="/password/forgot" method="post">
    <?= csrf_field() ?>

    <div class="lx-field">
        <label class="lx-field__label" for="email"><?= e($t('auth.emailLabel')) ?></label>
        <input class="lx-field__input"
               type="email"
               name="email"
               id="email"
               value="<?= e($email ?? '') ?>"
               placeholder="name@example.com"
               autocomplete="email"
               inputmode="email"
               required
               autofocus>
    </div>

    <button class="lx-btn lx-authsubmit" type="submit"><?= e($t('auth.forgotSubmit')) ?></button>
</form>
{% endblock %}

{% block help %}
<a href="/login"><?= e($t('auth.backToLogin')) ?></a>
{% endblock %}
