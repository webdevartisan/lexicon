{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.security.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'security'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        // Signing a device out is a details block per row instead of a shared modal: each
        // row carries its own action and wording, and it needs no script.
        // One sign-out control: a button that opens its own confirmation, with the password when the server asks for it.
        $signOutForm = function (string $action, string $title, string $text, string $label, string $summaryClass = 'acct-btn') use ($t, $needsPassword): void { ?>
            <details class="acct-details acct-signout">
                <summary class="<?= e($summaryClass) ?>"><?= e($label) ?></summary>
                <form method="post" action="<?= e($action) ?>">
                    <?= csrf_field() ?>
                    <p class="acct-label"><?= e($title) ?></p>
                    <p class="acct-intro"><?= e($text) ?></p>
                    <?php if ($needsPassword) { ?>
                    <div class="acct-field">
                        <label class="acct-label" for="session_password_<?= e(md5($action)) ?>"><?= e($t('account.security.currentPassword')) ?></label>
                        <input class="acct-input" type="password" name="current_password" id="session_password_<?= e(md5($action)) ?>" required autocomplete="current-password">
                        <p class="acct-hint"><?= e($t('account.sessions.passwordHint')) ?></p>
                    </div>
                    <?php } ?>
                    <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.sessions.signOut')) ?></button>
                </form>
            </details>
        <?php };
        ?>

        <div class="acct-head">
            <h2 class="acct-title"><?= e($t('account.nav.security')) ?></h2>
            <p class="acct-intro"><?= e($t('account.security.pageIntro')) ?></p>
        </div>

        <section class="acct-card acct-card-row">
            <div>
                <h3 class="acct-card-title"><?= e($t('account.security.passwordHeading')) ?></h3>
                <p class="acct-intro"><?= e($t('account.security.intro')) ?></p>
            </div>
            <a class="acct-btn" data-panel-link href="<?= e(lurl('/account/security/password')) ?>"><?= e($t('account.security.changePassword')) ?></a>
        </section>

        <section class="acct-card acct-card-row">
            <div>
                <h3 class="acct-card-title"><?= e($t('account.twoFactor.heading')) ?></h3>
                <p class="acct-intro">
                    <?= e($t('account.twoFactor.status')) ?>:
                    <strong><?= !empty($user['two_factor_confirmed_at']) ? e($t('account.twoFactor.statusOn')) : e($t('account.twoFactor.statusOff')) ?></strong>
                </p>
            </div>
            <a class="acct-btn" data-panel-link href="<?= e(lurl('/account/security/two-factor')) ?>"><?= e($t('account.twoFactor.manage')) ?></a>
        </section>

        <section aria-labelledby="sessions-heading">
            <h3 class="acct-section" id="sessions-heading"><?= e($t('account.sessions.heading')) ?></h3>
            <p class="acct-intro"><?= e($t('account.sessions.intro')) ?></p>

            <?php if (count($sessions) > 1) {
                $signOutForm(
                    lurl('/account/security/sessions/sign-out-others'),
                    $t('account.sessions.othersTitle'),
                    $t('account.sessions.othersText'),
                    $t('account.sessions.signOutOthers')
                );
            } ?>

            <ul class="acct-sessions">
                <?php foreach ($sessions as $s) { ?>
                <li class="acct-session<?= $s['current'] ? ' is-current' : '' ?>">
                    <p class="acct-session-client">
                        <?= e($s['client']) ?>
                        <?php if ($s['current']) { ?><span class="acct-badge is-on"><?= e($t('account.sessions.thisDevice')) ?></span><?php } ?>
                    </p>
                    <?php if ($s['place'] !== null || $s['ip'] !== null) { ?>
                    <p class="acct-hint">
                        <?php if ($s['place'] !== null) { ?><span><?= e($s['place']) ?></span><?php } ?>
                        <?php if ($s['ip'] !== null) { ?><span dir="ltr"><?= e($s['ip']) ?></span><?php } ?>
                    </p>
                    <?php } ?>
                    <p class="acct-hint">
                        <span><?= e($t('account.sessions.signedIn', ['date' => $s['signed_in']])) ?></span>
                        <span><?= $s['active_now'] ? e($t('account.sessions.activeNow')) : e($t('account.sessions.lastActive', ['date' => $s['last_active']])) ?></span>
                    </p>
                    <?php $signOutForm(
                        lurl('/account/security/sessions/'.$s['hash'].'/sign-out'),
                        $t($s['current'] ? 'account.sessions.currentTitle' : 'account.sessions.oneTitle'),
                        $s['current'] ? $t('account.sessions.currentText') : $t('account.sessions.oneText', ['device' => $s['client']]),
                        $t('account.sessions.signOut')
                    ); ?>
                </li>
                <?php } ?>
            </ul>

            <?php if (count($sessions) === 1) { ?>
            <p class="acct-intro"><?= e($t('account.sessions.onlyDevice')) ?></p>
            <?php } ?>
            <p class="acct-hint">
                <?= e($t('account.sessions.approximate')) ?>
                <a href="https://db-ip.com" rel="noopener" target="_blank">IP Geolocation by DB-IP</a>
            </p>
        </section>

        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.preferences.dataHeading')) ?></h3>
            <p class="acct-intro"><?= e($t('account.preferences.dataText')) ?></p>
            <form method="post" action="<?= e(lurl('/account/export')) ?>" data-panel-native>
                <?= csrf_field() ?>
                <button type="submit" class="acct-btn"><?= e($t('account.preferences.dataDownload')) ?></button>
            </form>
        </section>
    </div>
</section>
{% endblock %}
