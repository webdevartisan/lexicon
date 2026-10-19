{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.twoFactor.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$passwordField = function (string $id) use ($t): void { ?>
    <div class="lx-field">
        <label class="lx-field-label" for="<?= e($id) ?>"><?= e($t('account.twoFactor.passwordLabel')) ?></label>
        <input class="lx-field-input" type="password" name="current_password" id="<?= e($id) ?>" autocomplete="current-password" required>
    </div>
<?php };
$pending = !$enabled && $secret !== null;
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'security'; ?>
    <header class="lx-account-head">
        <h1><?= e($t('account.twoFactor.heading')) ?></h1>
        <p class="lx-muted"><?= e($t('account.twoFactor.intro')) ?></p>
    </header>

    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <div class="lx-account-main">
            <?php if ($newCodes !== null) { ?>
            <section class="lx-card" aria-labelledby="codes-heading">
                <h2 class="lx-card-title" id="codes-heading"><?= e($t('account.twoFactor.codesHeading')) ?></h2>
                <p class="lx-muted"><?= e($t('account.twoFactor.codesIntro')) ?></p>
                <ul class="lx-recovery-codes">
                    <?php foreach ($newCodes as $code) { ?>
                    <li><code><?= e($code) ?></code></li>
                    <?php } ?>
                </ul>
            </section>
            <?php } ?>

            <?php if ($enabled) { ?>
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.twoFactor.onHeading')) ?></h2>
                <p class="lx-muted"><?= e($t('account.twoFactor.onText')) ?></p>
                <p class="lx-stat">
                    <span><?= e($t('account.twoFactor.codesLeft')) ?></span>
                    <strong><?= (int) $codesLeft ?></strong>
                </p>

                <form method="post" action="<?= e(lurl('/account/security/two-factor/recovery-codes')) ?>" class="lx-account-form">
                    <?= csrf_field() ?>
                    <?php $passwordField('codes_password'); ?>
                    <div class="lx-account-actions">
                        <button type="submit" class="lx-btn lx-btn-subtle"><?= e($t('account.twoFactor.renewCodes')) ?></button>
                    </div>
                </form>
            </section>

            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.twoFactor.offHeading')) ?></h2>
                <?php if ($required) { ?>
                <p class="lx-muted"><?= e($t('account.twoFactor.requiredForRole')) ?></p>
                <?php } else { ?>
                <p class="lx-muted"><?= e($t('account.twoFactor.offText')) ?></p>
                <form method="post" action="<?= e(lurl('/account/security/two-factor/disable')) ?>" class="lx-account-form">
                    <?= csrf_field() ?>
                    <?php $passwordField('disable_password'); ?>
                    <div class="lx-account-actions">
                        <button type="submit" class="lx-btn lx-btn-subtle"><?= e($t('account.twoFactor.turnOff')) ?></button>
                    </div>
                </form>
                <?php } ?>
            </section>

            <?php } elseif ($pending) { ?>
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.twoFactor.scanHeading')) ?></h2>
                <p class="lx-muted"><?= e($t('account.twoFactor.scanText')) ?></p>
                <div class="lx-twofactor-qr" role="img" aria-label="<?= e($t('account.twoFactor.qrLabel')) ?>"><?php // Markup from the QR library, built from our own secret and address, never from user input.?><?= $qr ?></div>
                <p class="lx-muted"><?= e($t('account.twoFactor.manualText')) ?></p>
                <p><code class="lx-twofactor-secret"><?= e($secret) ?></code></p>

                <form method="post" action="<?= e(lurl('/account/security/two-factor/confirm')) ?>" class="lx-account-form">
                    <?= csrf_field() ?>
                    <div class="lx-field">
                        <label class="lx-field-label" for="setup_code"><?= e($t('account.twoFactor.codeLabel')) ?></label>
                        <input class="lx-field-input" type="text" name="code" id="setup_code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
                    </div>
                    <div class="lx-account-actions">
                        <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('account.twoFactor.confirm')) ?></button>
                    </div>
                </form>
            </section>

            <?php } else { ?>
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.twoFactor.offStateHeading')) ?></h2>
                <p class="lx-muted"><?= e($t('account.twoFactor.offStateText')) ?></p>
                <?php if ($required) { ?>
                <p class="lx-muted"><strong><?= e($t('account.twoFactor.requiredToContinue')) ?></strong></p>
                <?php } ?>
                <form method="post" action="<?= e(lurl('/account/security/two-factor/start')) ?>" class="lx-account-form">
                    <?= csrf_field() ?>
                    <?php $passwordField('start_password'); ?>
                    <div class="lx-account-actions">
                        <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('account.twoFactor.start')) ?></button>
                    </div>
                </form>
            </section>
            <?php } ?>
        </div>
    </div>
</section>
{% endblock %}
