{% extends "auth.lex.php" %}

{% block title %}<?= e($t('auth.registerTitle')) ?>{% endblock %}

{% block heading %}<?= e($t('auth.registerTitle')) ?>{% endblock %}

{% block sub %}<?= e($t('auth.registerSubtitle')) ?>{% endblock %}

{% block altAction %}
<?php
// Keep the reading context alive across the register hop.
$returnTo = !empty($return_to) ? $return_to : (old('return_to') ?? '');
$loginHref = '/login'.($returnTo !== '' ? '?return_to='.urlencode($returnTo) : '');
?>
<a href="<?= e($loginHref) ?>"><?= e($t('auth.signIn')) ?></a>
{% endblock %}

{% block body %}
<?php
$returnTo = !empty($return_to) ? $return_to : (old('return_to') ?? '');
$fieldErrors = errors();
?>
<form action="/register/submit" method="post" autocomplete="on">
    <?= csrf_field() ?>
    <?php if ($returnTo !== '') { ?>
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <?php } ?>

    <div class="lx-field<?= !empty($fieldErrors['email']) ? ' lx-field-invalid' : '' ?>">
        <label class="lx-field-label" for="email"><?= e($t('auth.emailLabel')) ?></label>
        <input class="lx-field-input"
               type="email"
               name="email"
               id="email"
               value="<?= e(old('email') ?? '') ?>"
               placeholder="name@example.com"
               autocomplete="email"
               inputmode="email"
               required
               autofocus>
        <?php foreach ($fieldErrors['email'] ?? [] as $message) { ?>
            <p class="lx-field-error"><?= e($message) ?></p>
        <?php } ?>
    </div>

    <div class="lx-field<?= !empty($fieldErrors['password']) ? ' lx-field-invalid' : '' ?>">
        <label class="lx-field-label" for="password"><?= e($t('auth.passwordLabel')) ?></label>

        <div class="lx-field-control">
            <input class="lx-field-input"
                   type="password"
                   name="password"
                   id="password"
                   autocomplete="new-password"
                   aria-describedby="password_help"
                   minlength="<?= password_length_bounds()['min'] ?>"
                   maxlength="<?= password_length_bounds()['max'] ?>"
                   required>
            <button type="button"
                    class="lx-field-toggle"
                    data-password-toggle="password"
                    data-label-show="<?= e($t('auth.show')) ?>"
                    data-label-hide="<?= e($t('auth.hide')) ?>"
                    data-aria-show="<?= e($t('auth.showPassword')) ?>"
                    data-aria-hide="<?= e($t('auth.hidePassword')) ?>"
                    aria-label="<?= e($t('auth.showPassword')) ?>"
                    aria-pressed="false"><?= e($t('auth.show')) ?></button>
        </div>

        <?php foreach ($fieldErrors['password'] ?? [] as $message) { ?>
            <p class="lx-field-error"><?= e($message) ?></p>
        <?php } ?>

        <?php
        // The requirement is stated plainly and always; the meter below only
        // rates what has been typed. Keeping them separate stops the meter
        // from reading like a rule the server does not actually enforce.
?>
        <p class="lx-field-hint" id="password_help"><?= e($t('auth.passwordMin', ['min' => password_length_bounds()['min']])) ?></p>

        <?php
// Rating labels ride along as data so the script stays free of copy.
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
             data-hint-from="email"
             data-levels="<?= e($meterLevels) ?>"
             hidden>
            <div class="lx-meter-track" aria-hidden="true">
                <span class="lx-meter-seg"></span>
                <span class="lx-meter-seg"></span>
                <span class="lx-meter-seg"></span>
                <span class="lx-meter-seg"></span>
            </div>
            <p class="lx-meter-label" data-meter-label role="status" aria-live="polite"></p>
        </div>
    </div>

    <div class="lx-field<?= !empty($fieldErrors['age_confirmed']) ? ' lx-field-invalid' : '' ?>">
        <label class="lx-check">
            <input type="checkbox" name="age_confirmed" value="1" required<?= old('age_confirmed') === '1' ? ' checked' : '' ?>>
            <span class="lx-check-title"><?= e($t('auth.ageConfirm', ['age' => minimum_signup_age()])) ?></span>
        </label>
        <?php foreach ($fieldErrors['age_confirmed'] ?? [] as $message) { ?>
            <p class="lx-field-error"><?= e($message) ?></p>
        <?php } ?>
    </div>

    <p class="lx-field-hint"><?= strtr(e($t('auth.legalNotice')), [
        '{terms}' => '<a href="'.e(lurl('/terms')).'">'.e($t('auth.termsLink')).'</a>',
        '{privacy}' => '<a href="'.e(lurl('/privacy')).'">'.e($t('auth.privacyLink')).'</a>',
    ]) ?></p>

    <button type="submit" class="lx-btn lx-authsubmit"><?= e($t('auth.registerSubmit')) ?></button>
</form>
{% endblock %}

{% block legal %}
<?php
// See the note in Login: the locale file owns the sentence, the anchors are
// ours, and neither comes from user input.
echo $t('auth.legal', [
    'terms' => '<a href="/terms">'.e($t('footer.linkTerms')).'</a>',
    'privacy' => '<a href="/privacy">'.e($t('footer.linkPrivacy')).'</a>',
]);
?>
{% endblock %}
