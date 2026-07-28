{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.loginTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.loginTitle')) ?>{% endblock %}

{% block sub %}
<?php
// Keep the reading context alive across the login hop.
$returnTo = !empty($return_to) ? $return_to : '';
echo e($returnTo !== '' ? $t('auth.loginSubtitleReturn') : $t('auth.loginSubtitle'));
?>
{% endblock %}

{% block altAction %}
<?php
$returnTo = !empty($return_to) ? $return_to : '';
$registerHref = '/register'.($returnTo !== '' ? '?return_to='.urlencode($returnTo) : '');
?>
<a href="<?= e($registerHref) ?>"><?= e($t('auth.createAccount')) ?></a>
{% endblock %}

{% block body %}
<?php
$returnTo = !empty($return_to) ? $return_to : '';
$email = old('email') ?? '';
?>
<form action="/login/submit" method="post">
    <?= csrf_field() ?>
    <?php if ($returnTo !== '') { ?>
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <?php } ?>

    <div class="lx-field">
        <label class="lx-field__label" for="email"><?= e($t('auth.emailLabel')) ?></label>
        <input class="lx-field__input"
               type="email"
               name="email"
               id="email"
               value="<?= e($email) ?>"
               placeholder="name@example.com"
               autocomplete="email"
               inputmode="email"
               required
               <?= $email === '' ? 'autofocus' : '' ?>>
    </div>

    <div class="lx-field">
        <label class="lx-field__label" for="password"><?= e($t('auth.passwordLabel')) ?></label>
        <div class="lx-field__control">
            <input class="lx-field__input"
                   type="password"
                   name="password"
                   id="password"
                   autocomplete="current-password"
                   required
                   <?= $email !== '' ? 'autofocus' : '' ?>>
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
    </div>

    <button class="lx-btn lx-authsubmit" type="submit"><?= e($t('auth.loginSubmit')) ?></button>
</form>
{% endblock %}

{% block help %}
<a href="/password/forgot"><?= e($t('auth.cantLogIn')) ?></a>
{% endblock %}

{% block legal %}
<?php
// The sentence shape differs per language, so the locale file owns the whole
// line and the two links go in as placeholders. Both the text and the anchors
// are ours, never user input, which is what makes the raw echo safe here.
echo $t('auth.legal', [
    'terms' => '<a href="/terms">'.e($t('footer.linkTerms')).'</a>',
    'privacy' => '<a href="/privacy">'.e($t('footer.linkPrivacy')).'</a>',
]);
?>
{% endblock %}
