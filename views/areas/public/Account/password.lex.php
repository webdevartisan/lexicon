{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.security.changePassword')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'security'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        $fieldErrors = errors();
        $signInEmail = (string) ($user['email'] ?? '');

        $passwordField = function (string $name, string $label, string $autocomplete) use ($fieldErrors): void {
            $invalid = !empty($fieldErrors[$name]);
            ?>
            <div class="acct-field<?= $invalid ? ' is-invalid' : '' ?>">
                <label class="acct-label" for="<?= e($name) ?>"><?= e($label) ?></label>
                <input class="acct-input" type="password" name="<?= e($name) ?>" id="<?= e($name) ?>"
                       autocomplete="<?= e($autocomplete) ?>"
                       <?= $invalid ? 'aria-invalid="true" aria-describedby="'.e($name).'_error"' : '' ?> required>
                <?php foreach ($fieldErrors[$name] ?? [] as $message) { ?>
                <p class="acct-error" id="<?= e($name) ?>_error"><?= e($message) ?></p>
                <?php } ?>
            </div>
        <?php };
        ?>

        <div class="acct-head">
            <p><a class="acct-back" data-panel-link href="<?= e(lurl('/account/security')) ?>">&larr; <?= e($t('account.nav.security')) ?></a></p>
            <h1 class="acct-title"><?= e($t('account.security.changePassword')) ?></h1>
            <p class="acct-intro"><?= e($t('account.security.intro')) ?></p>
        </div>

        <form method="post" action="<?= e(lurl('/account/security/password')) ?>" class="acct-form" autocomplete="on">
            <?= csrf_field() ?>
            <?php // The sign-in identifier, so a password manager files the new credential under it. ?>
            <input type="text" name="username" value="<?= e($signInEmail) ?>" autocomplete="username" class="acct-sr-only" aria-hidden="true" tabindex="-1" readonly>

            <?php $passwordField('current_password', $t('account.security.currentPassword'), 'current-password'); ?>
            <div class="acct-grid">
                <?php $passwordField('new_password', $t('account.security.newPassword'), 'new-password'); ?>
                <?php $passwordField('new_password_confirm', $t('account.security.confirmPassword'), 'new-password'); ?>
            </div>

            <div class="acct-actions">
                <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.security.save')) ?></button>
            </div>
        </form>
    </div>
</section>
{% endblock %}
