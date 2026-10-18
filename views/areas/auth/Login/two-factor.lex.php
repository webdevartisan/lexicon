{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.twoFactorTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.twoFactorTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.twoFactorSubtitle')) ?>{% endblock %}

{% block body %}
<form action="<?= e(lurl('/login/two-factor')) ?>" method="post">
    <?= csrf_field() ?>

    <div class="lx-field">
        <label class="lx-field-label" for="code"><?= e($t('auth.twoFactorCodeLabel')) ?></label>
        <input class="lx-field-input"
               type="text"
               name="code"
               id="code"
               autocomplete="one-time-code"
               inputmode="text"
               autocapitalize="off"
               spellcheck="false"
               required
               autofocus>
        <p class="lx-muted"><?= e($t('auth.twoFactorHint')) ?></p>
    </div>

    <button class="lx-btn lx-authsubmit" type="submit"><?= e($t('auth.twoFactorSubmit')) ?></button>
</form>
{% endblock %}

{% block help %}
<a href="<?= e(lurl('/login')) ?>"><?= e($t('auth.twoFactorBack')) ?></a>
{% endblock %}
