{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.security.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$username = (string) ($user['username'] ?? '');

$passwordField = function (string $name, string $label, string $autocomplete) use ($fieldErrors): void {
    $invalid = !empty($fieldErrors[$name]);
    ?>
    <div class="lx-field<?= $invalid ? ' lx-field--invalid' : '' ?>">
        <label class="lx-field__label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <input class="lx-field__input" type="password" name="<?= e($name) ?>" id="<?= e($name) ?>"
               autocomplete="<?= e($autocomplete) ?>"
               <?= $invalid ? 'aria-invalid="true" aria-describedby="'.e($name).'_error"' : '' ?> required>
        <?php foreach ($fieldErrors[$name] ?? [] as $message) { ?>
        <p class="lx-field__error" id="<?= e($name) ?>_error"><?= e($message) ?></p>
        <?php } ?>
    </div>
<?php };
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'security'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <header class="lx-account-head">
            <h1><?= e($t('account.security.heading')) ?></h1>
            <p class="lx-muted"><?= e($t('account.security.intro')) ?></p>
        </header>

        <form method="post" action="<?= e(lurl('/account/security/password')) ?>" class="lx-account-form" autocomplete="on">
            <?= csrf_field() ?>
            <?php // Username here so a password manager associates the credential with this account. ?>
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" class="lx-visually-hidden" aria-hidden="true" tabindex="-1" readonly>

            <?php $passwordField('current_password', $t('account.security.currentPassword'), 'current-password'); ?>
            <div class="lx-grid-2">
                <?php $passwordField('new_password', $t('account.security.newPassword'), 'new-password'); ?>
                <?php $passwordField('new_password_confirm', $t('account.security.confirmPassword'), 'new-password'); ?>
            </div>

            <div class="lx-account-actions">
                <button type="submit" class="lx-btn lx-btn--primary"><?= e($t('account.security.save')) ?></button>
            </div>
        </form>

        <aside class="lx-account-aside">
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.security.activityHeading')) ?></h2>
                <p class="lx-stat">
                    <span><?= e($t('account.security.lastLogin')) ?></span>
                    <strong><?= !empty($user['last_login']) ? e($user['last_login']) : e($t('account.security.never')) ?></strong>
                </p>
            </section>
        </aside>
    </div>
</section>
{% endblock %}
