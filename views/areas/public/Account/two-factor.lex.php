{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.twoFactor.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'security'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        $passwordField = function (string $id) use ($t): void { ?>
            <div class="acct-field">
                <label class="acct-label" for="<?= e($id) ?>"><?= e($t('account.twoFactor.passwordLabel')) ?></label>
                <input class="acct-input" type="password" name="current_password" id="<?= e($id) ?>" autocomplete="current-password" required>
            </div>
        <?php };
        $pending = !$enabled && $secret !== null;
        ?>

        <div class="acct-head">
            <p><a class="acct-back" data-panel-link href="<?= e(lurl('/account/security')) ?>">&larr; <?= e($t('account.nav.security')) ?></a></p>
            <h2 class="acct-title"><?= e($t('account.twoFactor.heading')) ?></h2>
            <p class="acct-intro"><?= e($t('account.twoFactor.intro')) ?></p>
        </div>

        <?php if ($newCodes !== null) { ?>
        <section class="acct-card" aria-labelledby="codes-heading">
            <h3 class="acct-card-title" id="codes-heading"><?= e($t('account.twoFactor.codesHeading')) ?></h3>
            <p class="acct-intro"><?= e($t('account.twoFactor.codesIntro')) ?></p>
            <ul class="acct-codes">
                <?php foreach ($newCodes as $code) { ?>
                <li><code><?= e($code) ?></code></li>
                <?php } ?>
            </ul>
        </section>
        <?php } ?>

        <?php if ($enabled) { ?>
        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.twoFactor.onHeading')) ?></h3>
            <p class="acct-intro"><?= e($t('account.twoFactor.onText')) ?></p>
            <p class="acct-intro"><?= e($t('account.twoFactor.codesLeft')) ?>: <strong><?= (int) $codesLeft ?></strong></p>

            <form method="post" action="<?= e(lurl('/account/security/two-factor/recovery-codes')) ?>" class="acct-form">
                <?= csrf_field() ?>
                <?php $passwordField('codes_password'); ?>
                <div class="acct-actions">
                    <button type="submit" class="acct-btn"><?= e($t('account.twoFactor.renewCodes')) ?></button>
                </div>
            </form>
        </section>

        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.twoFactor.offHeading')) ?></h3>
            <?php if ($required) { ?>
            <p class="acct-intro"><?= e($t('account.twoFactor.requiredForRole')) ?></p>
            <?php } else { ?>
            <p class="acct-intro"><?= e($t('account.twoFactor.offText')) ?></p>
            <form method="post" action="<?= e(lurl('/account/security/two-factor/disable')) ?>" class="acct-form">
                <?= csrf_field() ?>
                <?php $passwordField('disable_password'); ?>
                <div class="acct-actions">
                    <button type="submit" class="acct-btn acct-btn-danger"><?= e($t('account.twoFactor.turnOff')) ?></button>
                </div>
            </form>
            <?php } ?>
        </section>

        <?php } elseif ($pending) { ?>
        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.twoFactor.scanHeading')) ?></h3>
            <p class="acct-intro"><?= e($t('account.twoFactor.scanText')) ?></p>
            <div class="acct-qr" role="img" aria-label="<?= e($t('account.twoFactor.qrLabel')) ?>"><?php // Markup from the QR library, built from our own secret and address, never from user input. ?><?= $qr ?></div>
            <p class="acct-intro"><?= e($t('account.twoFactor.manualText')) ?></p>
            <p><code class="acct-secret"><?= e($secret) ?></code></p>

            <form method="post" action="<?= e(lurl('/account/security/two-factor/confirm')) ?>" class="acct-form">
                <?= csrf_field() ?>
                <div class="acct-field">
                    <label class="acct-label" for="setup_code"><?= e($t('account.twoFactor.codeLabel')) ?></label>
                    <input class="acct-input" type="text" name="code" id="setup_code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
                </div>
                <div class="acct-actions">
                    <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.twoFactor.confirm')) ?></button>
                </div>
            </form>
        </section>

        <?php } else { ?>
        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.twoFactor.offStateHeading')) ?></h3>
            <p class="acct-intro"><?= e($t('account.twoFactor.offStateText')) ?></p>
            <?php if ($required) { ?>
            <p class="acct-intro"><strong><?= e($t('account.twoFactor.requiredToContinue')) ?></strong></p>
            <?php } ?>
            <form method="post" action="<?= e(lurl('/account/security/two-factor/start')) ?>" class="acct-form">
                <?= csrf_field() ?>
                <?php $passwordField('start_password'); ?>
                <div class="acct-actions">
                    <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.twoFactor.start')) ?></button>
                </div>
            </form>
        </section>
        <?php } ?>
    </div>
</section>
{% endblock %}
