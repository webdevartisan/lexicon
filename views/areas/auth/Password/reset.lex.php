{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.resetTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.resetTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.resetSubtitle')) ?>{% endblock %}

{% block body %}
<form action="/password/reset" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <input type="hidden" name="email" value="<?= e($email ?? '') ?>">

    <div class="lx-field">
        <label class="lx-field__label" for="password"><?= e($t('auth.newPasswordLabel')) ?></label>
        <div class="lx-field__control">
            <input class="lx-field__input"
                   type="password"
                   name="password"
                   id="password"
                   autocomplete="new-password"
                   aria-describedby="password_help"
                   minlength="6"
                   required
                   autofocus>
            <button type="button"
                    class="lx-field__toggle"
                    data-password-toggle="password"
                    data-label-show="<?= e($t('auth.show')) ?>"
                    data-label-hide="<?= e($t('auth.hide')) ?>"
                    data-aria-show="<?= e($t('auth.showPassword')) ?>"
                    data-aria-hide="<?= e($t('auth.hidePassword')) ?>"
                    aria-label="<?= e($t('auth.showPassword')) ?>"
                    aria-pressed="false"><?= e($t('auth.show')) ?></button>
        </div>
        <p class="lx-field__hint" id="password_help"><?= e($t('auth.passwordMin')) ?></p>

        <?php
        // Same advisory meter as registration: this is the other screen where
        // someone picks a password, so it gets the same feedback.
        $meterLevels = implode('|', [
            $t('auth.strengthTooShort'),
            $t('auth.strengthWeak'),
            $t('auth.strengthFair'),
            $t('auth.strengthGood'),
            $t('auth.strengthStrong'),
        ]);
?>
        <div class="lx-meter"
             data-password-meter="password"
             data-levels="<?= e($meterLevels) ?>"
             hidden>
            <div class="lx-meter__track" aria-hidden="true">
                <span class="lx-meter__seg"></span>
                <span class="lx-meter__seg"></span>
                <span class="lx-meter__seg"></span>
                <span class="lx-meter__seg"></span>
            </div>
            <p class="lx-meter__label" data-meter-label role="status" aria-live="polite"></p>
        </div>
    </div>

    <div class="lx-field">
        <?php // The `required` here was misspelled `requiredd`, so the confirm field was optional.?>
        <label class="lx-field__label" for="password_confirm"><?= e($t('auth.confirmPasswordLabel')) ?></label>
        <input class="lx-field__input"
               type="password"
               name="password_confirm"
               id="password_confirm"
               autocomplete="new-password"
               required>
    </div>

    <button class="lx-btn lx-authsubmit" type="submit"><?= e($t('auth.resetSubmit')) ?></button>
</form>
{% endblock %}

{% block help %}
<a href="/login"><?= e($t('auth.backToLogin')) ?></a>
{% endblock %}
